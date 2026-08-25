<?php 
namespace App\Models\Traits\Controllers;

use App\Helpers\HArr;
use App\Models\Company;
use App\Models\FactoringTransaction;
use App\Models\InvoiceDeduction;
use App\Models\LetterOfCreditIssuance;
use Carbon\Carbon;
use Illuminate\Support\Collection;


trait HasBalances 
{
	public static function formatForStatementReport(Collection $invoices,int $partnerId,string $startDate,string $endDate,string $currency,string $modelType){
		$isMainCurrency = $currency == 'main_currency' ;
		$startDateFormatted = Carbon::make($startDate)->format('d-m-Y');
		$index = -1 ;
		
		$oneDayBeforeStartDate = Carbon::make($startDate)->subDays(1000)->format('Y-m-d');
	
		$startDateMinusOne = Carbon::make($startDate)->subDay()->format('Y-m-d');
		$fullClassName = ('\App\Models\\' . $modelType) ;
		$clientIdColumnName = $fullClassName::CLIENT_ID_COLUMN_NAME ;
		
		$clientInvoiceIds = $fullClassName::getForPartner($partnerId,$currency,$isMainCurrency);
		$invoicesForBeginningBalance = $fullClassName::getInvoicesForInvoiceStartAndEndDate( $clientIdColumnName, $partnerId, currentCompany() ,  $currency ,  $oneDayBeforeStartDate,$startDateMinusOne );
		$formattedData = [];
		$beginningBalance = self::appendBalances($isMainCurrency , $currency,$invoicesForBeginningBalance, $index, $formattedData, $partnerId, $oneDayBeforeStartDate,$startDateMinusOne,$clientInvoiceIds,$modelType,false) ;
		$index = 0 ;
		$currentData['date'] = $startDateFormatted;
		$currentData['document_type'] = 'Beginning Balance';
		$currentData['document_no'] = null;
		$currentData['debit'] = $debit = $beginningBalance >= 0 ? $beginningBalance : 0;
		$currentData['credit'] = $credit = $beginningBalance < 0 ? $beginningBalance * -1 : 0 ;
		$currentData['end_balance'] =$debit - $credit;
		$currentData['comment'] =null;
		$index++ ;
		$formattedData[$index] = $currentData;
		self::appendBalances($isMainCurrency , $currency,$invoices, $index, $formattedData, $partnerId, $startDate, $endDate,$clientInvoiceIds,$modelType,true);
		return HArr::sortBasedOnKey($formattedData,'date');
}

	public static function appendBalances($isMainCurrency ,string $currency,$invoices,int &$index,array &$formattedData,int $partnerId,string $startDate,string $endDate , array $clientInvoiceIds , string $modelType , bool $isNotBegBalance = true )
	{
		$isCustomer = $modelType == 'CustomerInvoice';
		$tempArr = [];
		$fullInvoiceModelName = 'App\Models\\'.$modelType;
		$fullMoneyModelName ='App\Models\\'.$fullInvoiceModelName::MONEY_MODEL_NAME ;
		$dateColumnName = $fullInvoiceModelName::RECEIVING_OR_PAYMENT_DATE_COLUMN_NAME;
		foreach($invoices as $customerInvoice){
			$currentAmount =  $isMainCurrency ?  $customerInvoice->getNetInvoiceInMainCurrencyAmount() : $customerInvoice->getNetInvoiceAmount();
			$currentDebit = $isCustomer ? $currentAmount : 0;
			$currentCredit = $isCustomer ? 0 : $currentAmount ;
			$invoiceExchangeRate = $customerInvoice->getExchangeRate();
			$currentData = [];
			$invoiceDate = $customerInvoice->getInvoiceDateFormatted() ;
			$invoiceNumber  = $customerInvoice->getInvoiceNumber() ;
			$currentData['date'] = $invoiceDate;
			$currentData['document_type'] = 'Invoice';
			$currentData['document_no'] = $invoiceNumber;
			$currentData['debit'] = $currentDebit  ;
			$currentData['credit'] =$currentCredit;
			$currentData['end_balance'] =$currentDebit-$currentCredit;
			$currentData['comment'] =null;
			if($isNotBegBalance){
				$index++ ;
				$formattedData[$index]=$currentData;
			}else{
				$index++ ;
				$tempArr[$index] = $currentData ;
			}
			/**
			 * * for customer
			 */
			if($customerInvoice->odoo_collected_amount>0){
					$currentData['date'] = $invoiceDate;
					$currentData['document_type'] = 'Collection';
					$currentData['document_no'] = $invoiceNumber;
					$currentData['debit'] = 0  ;
					$currentData['credit'] = $isMainCurrency ? $customerInvoice->odoo_collected_amount_in_main_currency : $customerInvoice->odoo_collected_amount;
					$currentData['comment'] =__('Collected Amount');
					$index++ ;
						if($isNotBegBalance){
				$formattedData[$index]=$currentData;
					}else{
						$tempArr[$index] = $currentData ;
					}
					
					
			}
			if($customerInvoice->excel_collected_amount>0){
					$currentData['date'] = $invoiceDate;
					$currentData['document_type'] = 'Collection';
					$currentData['document_no'] = $invoiceNumber;
					$currentData['debit'] = 0  ;
					$currentData['credit'] = $isMainCurrency ? $customerInvoice->excel_collected_amount_in_main_currency : $customerInvoice->excel_collected_amount;
					$currentData['comment'] =__('Collected Amount');
					$index++ ;
						if($isNotBegBalance){
				$formattedData[$index]=$currentData;
					}else{
						$tempArr[$index] = $currentData ;
					}
					
					
			}
			/**
			 * * for supplier
			 */
			if($customerInvoice->odoo_paid_amount>0){
					$currentData['date'] = $invoiceDate;
					$currentData['document_type'] = 'Paid';
					$currentData['document_no'] = $invoiceNumber;
					$currentData['debit'] = $isMainCurrency  ? $customerInvoice->odoo_paid_amount_in_main_currency : $customerInvoice->odoo_paid_amount  ;
					$currentData['credit'] =0;
					$currentData['comment'] =__('Paid Amount');
					$index++ ;
					
						if($isNotBegBalance){
				$formattedData[$index]=$currentData;
					}else{
						$tempArr[$index] = $currentData ;
					}
			}
			if($customerInvoice->excel_paid_amount>0){
					$currentData['date'] = $invoiceDate;
					$currentData['document_type'] = 'Paid';
					$currentData['document_no'] = $invoiceNumber;
					$currentData['debit'] = $isMainCurrency  ? $customerInvoice->excel_paid_amount_in_main_currency : $customerInvoice->excel_paid_amount  ;
					$currentData['credit'] =0;
					$currentData['comment'] =__('Paid Amount');
					$index++ ;
					
						if($isNotBegBalance){
				$formattedData[$index]=$currentData;
					}else{
						$tempArr[$index] = $currentData ;
					}
			}
			if($customerInvoice->odoo_withhold_amount>0){
				$currentWithholdAmount = $isMainCurrency ?  $customerInvoice->odoo_withhold_amount_in_main_currency : $customerInvoice->odoo_withhold_amount ; 
					$currentData['date'] = $invoiceDate;
					$currentData['document_type'] = 'Withhold Taxes';
					$currentData['document_no'] = $invoiceNumber;
					$currentData['debit'] = $isCustomer ? 0 : $currentWithholdAmount  ;
					$currentData['credit'] = $isCustomer ? $currentWithholdAmount : 0;
					$currentData['comment'] =__('Withhold Taxes');
						$index++ ;
					if($isNotBegBalance){
				$formattedData[$index]=$currentData;
					}else{
						
						$tempArr[$index] = $currentData ;
					}
				
			}
		}
		foreach(InvoiceDeduction::getForInvoices($clientInvoiceIds,$modelType,$startDate,$endDate) as $invoiceDeduction){
			$invoice = $invoiceDeduction->getInvoice();
			$invoiceExchangeRate = $invoice->getExchangeRate();
			$currentInvoiceNumber = $invoice->getInvoiceNumber();
			$deductionAmount = $invoiceDeduction->getAmount() ;
			$currentDeductionAmount =$isMainCurrency ? $invoiceExchangeRate * $deductionAmount : $deductionAmount;
			$currentDebit = $isCustomer  ?  0 : $currentDeductionAmount  ;
			$currentCredit = $isCustomer  ?  $currentDeductionAmount : 0 ;
			$deductionDate = $invoiceDeduction->getDate() ;
			$currentData['date'] = Carbon::make($deductionDate)->format('d-m-Y');
			$currentData['document_type'] = 'Deduction';
			$currentData['document_no'] = $currentInvoiceNumber;
			$currentData['debit'] = $currentDebit;
			$currentData['credit'] = $currentCredit;
			$currentData['comment'] =$invoiceDeduction->getDeductionName() . ' [ '  . $currentInvoiceNumber .' ] ' ;
			if($isNotBegBalance){
				$index++ ;
				$formattedData[$index]=$currentData;
			}else{
				$index++ ;
				$tempArr[$index] = $currentData ;
			}
		}
		/**
		 * Internal settlements — the same partner offset against
		 * themselves (see App\Models\InternalSettlement).
		 *
		 * One stored row produces a line on BOTH statements, which is
		 * what makes the offset explainable from either side:
		 *   - customer statement: a CREDIT, they owe us that much less
		 *   - supplier statement: a DEBIT, we owe them that much less
		 * That is the same debit/credit convention this method already
		 * uses for a collection and a payment respectively, so the
		 * running balance treats it exactly like the settlement it
		 * stands in for.
		 *
		 * The main-currency view reads the amount converted at the rate
		 * stamped on the settlement's own date, not today's — same
		 * reasoning as everything else on this statement.
		 */
		foreach (\App\Models\InternalSettlement::query()
			->where('company_id', getCurrentCompanyId())
			->where('partner_id', $partnerId)
			->when(!$isMainCurrency, fn ($q) => $q->where('currency', $currency))
			->whereBetween('settlement_date', [$startDate, $endDate])
			->get() as $internalSettlement) {
			$settlementAmount = $isMainCurrency
				? $internalSettlement->getAmountInMainCurrency()
				: $internalSettlement->getAmount();

			if (!$settlementAmount) {
				continue;
			}

			$currentData = [];
			$currentData['date'] = $internalSettlement->getDateFormatted();
			$currentData['document_type'] = \App\Models\InternalSettlement::documentType();
			$currentData['document_no'] = null;
			$currentData['debit'] = $isCustomer ? 0 : $settlementAmount;
			$currentData['credit'] = $isCustomer ? $settlementAmount : 0;
			$currentData['comment'] = trim(
				\App\Models\InternalSettlement::statementComment($isCustomer)
				. ($internalSettlement->getUserComment() ? ' — ' . $internalSettlement->getUserComment() : '')
			);

			$index++ ;
			if($isNotBegBalance){
				$formattedData[] = $currentData ;
			}else{
				$tempArr[] = $currentData ;
			}
		}

		$partnerType = $modelType =='SupplierInvoice' ? 'is_supplier' : 'is_customer' ;
		$allMoneyModels =  $fullMoneyModelName::
		where('company_id',getCurrentCompanyId())
		->whereBetween($dateColumnName,[$startDate,$endDate])
		->where('partner_id',$partnerId)
		->where('partner_type',$partnerType)
		// ->when(!$isMainCurrency , function($q) use ($currency){
		// 	// $q->where('currency',$currency);
		// })
		->get() ; 
		
		if($modelType == 'SupplierInvoice'){
			$letterOfCreditIssuance  = LetterOfCreditIssuance::where('company_id',getCurrentCompanyId())
			->whereBetween('payment_date',[$startDate,$endDate])
			->where('partner_id',$partnerId)->has('settlements')->get();
			$allMoneyModels = $allMoneyModels->merge($letterOfCreditIssuance);
		}
		
		foreach($allMoneyModels as $moneyModel) {
			$dateReceivingFormatted = $moneyModel->getReceivingOrPaymentMoneyDateFormatted() ;
			$isAdvancedOpeningBalance = $moneyModel->isAdvancedOpeningBalance();
			$moneyModelType = $moneyModel->getType();
			$moneyModelType = $isAdvancedOpeningBalance ?  __('Down Payments') : $moneyModelType;
			$docNumber = $moneyModel->getNumber();
				$moneyModelAmount = $isMainCurrency ? $moneyModel->getAmountForMainCurrency() :$moneyModel->getAmountInInvoiceCurrency() ;
				if($moneyModelAmount){
					if($moneyModel->getInvoiceCurrency() == $currency  || $isMainCurrency	 ){
						$currentAmount =  $moneyModelAmount ;
						$currentDebit = $isCustomer ? 0 : $currentAmount;
						$currentCredit = $isCustomer ? $currentAmount : 0 ;
						$invoiceNumbers = implode('/',$moneyModel->settlements->pluck('invoice.invoice_number')->toArray());
						/**
						 * ⚠️ REAL BUG FIXED HERE (client-flagged, 2026-08-17): this checked
						 * method_exists($fullMoneyModelName, ...) — but $fullMoneyModelName
						 * is fixed to MoneyPayment for the whole loop (from
						 * SupplierInvoice::MONEY_MODEL_NAME), even on the rows in this
						 * same collection that are actually LetterOfCreditIssuance (merged
						 * in just above for SupplierInvoice). Since MoneyPayment::generateComment()
						 * always exists, the check was always true and always called
						 * MoneyPayment::generateComment($moneyModel, ...) even when $moneyModel
						 * was an LC row — crashing, since that method is type-hinted to
						 * MoneyPayment and its body calls MoneyPayment-only methods
						 * (isPayableCheque(), isCashPayment(), etc.) that don't exist on LC
						 * Issuance. The "LC Settlement..." fallback right here was clearly
						 * written for exactly this case; it just could never be reached.
						 * Checking the row's OWN class fixes that.
						 */
						$currentComment = method_exists($moneyModel,'generateComment')  ? $moneyModel::generateComment($moneyModel,app()->getLocale(),$invoiceNumbers,'') : __('LC Settlement Paid Invoices [ :numbers ]',['numbers'=>$invoiceNumbers],app()->getLocale());
						$currentData = []; 
						$currentData['date'] = $dateReceivingFormatted;
						$currentData['document_type'] = $moneyModelType;
						$currentData['document_no'] = $docNumber  ;
						$currentData['debit'] = $currentDebit;
						$currentData['credit'] =$currentCredit;
						$currentData['comment'] = $currentComment ;
						if($isNotBegBalance){
							$index++ ;
							$formattedData[] = $currentData ;
						}else{
							$index++ ;
							$tempArr[] = $currentData ;
						}
					$totalWithholdAmount = $isMainCurrency ? $moneyModel->getTotalWithholdInInvoiceExchangeRate() : $moneyModel->getTotalWithholdAmount();
					if($isNotBegBalance){
						$isMainCurrency  ? $moneyModel->appendForeignExchangeGainOrLoss($formattedData,$index) : null ; 
					}else{
						$isMainCurrency  ? $moneyModel->appendForeignExchangeGainOrLoss($tempArr,$index) : null ; 
					}
					if($totalWithholdAmount){
						$currentDebit = $isCustomer ? 0 : $totalWithholdAmount;
						$currentCredit = $isCustomer ? $totalWithholdAmount:0;
						$currentData = []; 
						$currentData['date'] = $dateReceivingFormatted;
						$currentData['document_type'] = __('Withhold Taxes');
						$currentData['document_no'] =  $docNumber ;
						$currentData['debit'] = $currentDebit;
						$currentData['credit'] =$currentCredit;
						$currentData['comment'] =__('Withhold Taxes For Invoice No.') . ' [ ' . implode('/',$moneyModel->settlements->where('withhold_amount','>',0)->pluck('invoice.invoice_number')->toArray()) . ' ]';
						if($isNotBegBalance){
							$index++ ;
							$formattedData[] = $currentData ;
						}
						else{
							$index++ ;
							$tempArr[] = $currentData ;
						}
					}
					}
					
					elseif($moneyModel->getReceivingOrPaymentCurrency() == $currency ){
						  // start down payment from receiving currency 
				
							$receivedAmountOrPaidAmount = $moneyModel->getAmount();
							$exchangeRate =  $moneyModel->getExchangeRate() ;
							$currentAmount =  $receivedAmountOrPaidAmount -  ($moneyModelAmount*$exchangeRate) ;
							if($currentAmount >= -5 && $currentAmount<=5){
								continue ;
							}
						  $currentDebit = $isCustomer ? 0 : $currentAmount;
						  $currentCredit = $isCustomer ? $currentAmount : 0 ;
						  $invoiceNumbers = implode('/',$moneyModel->settlements->pluck('invoice.invoice_number')->toArray());
						  /**
						   * * Same fix as the other generateComment() call above in this
						   * * method — guard against the row's own class, not the fixed
						   * * $fullMoneyModelName, so an LC row here can't hit the same crash.
						   * * (Currently unreachable for LC specifically, since
						   * * LetterOfCreditIssuance::getReceivingOrPaymentCurrency() and
						   * * ::getInvoiceCurrency() always return the same value, so this
						   * * elseif can never be true when the sibling if() above was false —
						   * * fixed anyway so it isn't a live trap if that ever changes.)
						   */
						  $currentComment = method_exists($moneyModel,'generateComment') ? $moneyModel::generateComment($moneyModel,app()->getLocale(),$invoiceNumbers,'') : __('LC Settlement Paid Invoices [ :numbers ]',['numbers'=>$invoiceNumbers],app()->getLocale());
						  $currentData = []; 
						  $currentData['date'] = $dateReceivingFormatted;
						  $currentData['document_type'] = $moneyModelType;
						  $currentData['document_no'] = $docNumber  ;
						  $currentData['debit'] = $currentDebit;
						  $currentData['credit'] =$currentCredit;
						  $currentData['comment'] = $currentComment ;
						  if($isNotBegBalance){
							  $index++ ;
							  $formattedData[] = $currentData ;
						  }else{
							  $index++ ;
							  $tempArr[] = $currentData ;
						  }
						  
					  
					  // end down payment from receiving currency
						
					}
					
					
				}
		}

		if ($isCustomer) {
			$factoringTransactions = FactoringTransaction::query()
				->where('company_id', getCurrentCompanyId())
				->where('customer_id', $partnerId)
				->where('recourse_type', FactoringTransaction::WITHOUT_RECOURSE)
				->whereBetween('factoring_date', [$startDate, $endDate])
				->with(['factoringCompany', 'customerInvoice'])
				->get();

			foreach ($factoringTransactions as $factoringTransaction) {
				$invoice = $factoringTransaction->customerInvoice;
				if (!$invoice) {
					continue;
				}

				$invoiceCurrency = $factoringTransaction->invoice_currency;
				if (!$isMainCurrency && $invoiceCurrency !== $currency) {
					continue;
				}

				$currentAmount = $isMainCurrency
					? (float) $factoringTransaction->invoice_amount * (float) $invoice->getExchangeRate()
					: (float) $factoringTransaction->invoice_amount;

				if (!$currentAmount) {
					continue;
				}

				$currentData = [];
				$currentData['date'] = Carbon::make($factoringTransaction->factoring_date)->format('d-m-Y');
				$currentData['document_type'] = __('Factoring');
				$currentData['document_no'] = $invoice->getInvoiceNumber();
				$currentData['debit'] = 0;
				$currentData['credit'] = $currentAmount;
				$currentData['comment'] = FactoringTransaction::getStatementComment(
					$factoringTransaction->factoringCompany?->getName() ?? ''
				);

				if ($isNotBegBalance) {
					$index++;
					$formattedData[] = $currentData;
				} else {
					$index++;
					$tempArr[] = $currentData;
				}
			}
		}
		
		if(!$isNotBegBalance){
			return array_sum(array_column($tempArr,'debit')) - array_sum(array_column($tempArr,'credit'));
		}
		return $formattedData;
	}
	
	

}
