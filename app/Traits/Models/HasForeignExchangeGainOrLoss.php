<?php
namespace App\Traits\Models;

use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\ForeignExchangeRate;
use App\Models\LetterOfCreditIssuance;
use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use App\Models\Partner;
use Carbon\Carbon;


 
/**
 * * ال تريت دا مشترك بين
 * * MoneyReceived || MoneyPayment
 */
trait HasForeignExchangeGainOrLoss 
{

	public function appendForeignExchangeGainOrLoss(array &$formattedData,int &$index):array 
	{
		$isCustomer = $this instanceof MoneyReceived;
		$isSupplier = $this instanceof MoneyPayment ;
		$isLetterOfCreditIssuance = $this instanceof LetterOfCreditIssuance ;
		$invoiceCurrency = $this->getInvoiceCurrency();
		$receivingCurrency = $this->getReceivingOrPaymentCurrency();
		$company = $this->company;
		$mainFunctionalCurrency = $company->getMainFunctionalCurrency();
		$receivingOrPaymentDate = $this->getDate();
		$receivingOrPaymentExchangeRate = $this->getExchangeRate();
			if($invoiceCurrency ==$receivingCurrency && $receivingCurrency ==  $mainFunctionalCurrency){
				return $formattedData ;
			}
		
			foreach($this->settlements as $settlement){
				$fxGainOrLossAmount = 0 ;
				$settlementAmount = $settlement->getAmount() ;
				$invoiceExchangeRate = $settlement->getInvoiceExchangeRate();
				$foreignExchangeRate = ForeignExchangeRate::getExchangeRateForCurrencyAndClosestDate($receivingCurrency,$mainFunctionalCurrency,$receivingOrPaymentDate,$company->id);
				if($invoiceCurrency ==$receivingCurrency && $receivingCurrency !=  $mainFunctionalCurrency){
					
					if($isLetterOfCreditIssuance){
						$foreignExchangeRate = $this->getExchangeRate();
					}
					$fxGainOrLossAmount = $settlementAmount * ($foreignExchangeRate - $invoiceExchangeRate);
				}elseif($invoiceCurrency !=$receivingCurrency && $receivingCurrency ==  $mainFunctionalCurrency){
					$fxGainOrLossAmount = $settlementAmount * ($receivingOrPaymentExchangeRate - $invoiceExchangeRate);
				}
				elseif($invoiceCurrency != $receivingCurrency && $receivingCurrency !=  $mainFunctionalCurrency){
					$fxGainOrLossAmount = $settlementAmount * (($receivingOrPaymentExchangeRate * $foreignExchangeRate) - $invoiceExchangeRate);
				}
			
				$currentInvoiceNumber = $settlement->getInvoiceNumber();
				if($fxGainOrLossAmount == 0){
					continue;
				}
				$currentData = []; 
				$currentData['date'] = Carbon::make($receivingOrPaymentDate)->format('d-m-Y');
				$currentData['document_type'] = __('FX Gain Or Loss') ;
				$currentData['document_no'] =  $currentInvoiceNumber ;
				if($isCustomer){
					$isGain = $fxGainOrLossAmount > 0 ;
					$currentData['debit'] = $isGain ? $fxGainOrLossAmount  : 0;
					$currentData['credit'] =!$isGain ? $fxGainOrLossAmount * -1  : 0;
					$currentData['comment'] = $isGain ? __('Foreign Exchange Gain [ :invoiceNumber ]',['invoiceNumber'=>$currentInvoiceNumber]) :__('Foreign Exchange Loss [ :invoiceNumber ]',['invoiceNumber'=>$currentInvoiceNumber]) ;
				}elseif($isSupplier || $isLetterOfCreditIssuance){
					$isGain = $fxGainOrLossAmount < 0 ;
					$currentData['debit'] = $isGain ? $fxGainOrLossAmount* -1  : 0;
					$currentData['credit'] =!$isGain ? $fxGainOrLossAmount   : 0;
					$currentData['comment'] = $isGain ? __('Foreign Exchange Gain [ :invoiceNumber ]',['invoiceNumber'=>$currentInvoiceNumber]) :__('Foreign Exchange Loss [ :invoiceNumber ]',['invoiceNumber'=>$currentInvoiceNumber]) ;
				}
				$index++;
				$formattedData[] = $currentData ;
				
			}
	
		
		return $formattedData ; 
	}
	
	
}
