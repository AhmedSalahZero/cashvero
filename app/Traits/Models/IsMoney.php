<?php
namespace App\Traits\Models;

use App\Models\Branch;
use App\Models\CashExpense;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FinancialInstitution;
use App\Models\ForeignExchangeRate;
use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use App\Models\Partner;
use App\Services\Api\CashExpenseOdooService;
use App\Services\Api\OdooPayment;
use App\Services\Api\OdooSync;
use Carbon\Carbon;

/**
 * * ال تريت دا مشترك بين
 * * MoneyReceived || MoneyPayment
 */
trait IsMoney
{


    public function getId()
    {
        return $this->id ;
    }
    public function getType():string
    {
        return $this->type ;
    }
    public function getSettlementAndWithholdAmountInMainCurrency($receivingCurrencyOrPaymentCurrency, $invoiceCurrency, $exchangeRate, $foreignExchangeRate, $invoiceExchangeRate, $settlementAmountInInvoiceCurrency, $withholdAmountInInvoiceCurrency):array
    {
		$company = currentCompany();
        $mainFunctionCurrency = $company->getMainFunctionalCurrency();
        if ($receivingCurrencyOrPaymentCurrency == $mainFunctionCurrency && $mainFunctionCurrency ==  $invoiceCurrency) {
            return [
                'settlement_amount_in_main_currency'=>$settlementAmountInInvoiceCurrency ,
                'withhold_amount_in_main_currency'=>$withholdAmountInInvoiceCurrency,
                'settlement_in_invoice_exchange_rate'=>$settlementAmountInInvoiceCurrency
            ] ;
        }
        if ($receivingCurrencyOrPaymentCurrency != $invoiceCurrency && $receivingCurrencyOrPaymentCurrency == $mainFunctionCurrency) {
            return [
                'settlement_amount_in_main_currency'=>$settlementAmountInInvoiceCurrency * $exchangeRate ,
                'withhold_amount_in_main_currency'=>$withholdAmountInInvoiceCurrency* $invoiceExchangeRate,
                'settlement_in_invoice_exchange_rate'=>$settlementAmountInInvoiceCurrency*$invoiceExchangeRate
            ]  ;
        }
        if ($receivingCurrencyOrPaymentCurrency ==$invoiceCurrency && $receivingCurrencyOrPaymentCurrency != $mainFunctionCurrency
            ||
            $receivingCurrencyOrPaymentCurrency != $invoiceCurrency && $receivingCurrencyOrPaymentCurrency != $mainFunctionCurrency
        ) {
            return [
                'settlement_amount_in_main_currency'=>$settlementAmountInInvoiceCurrency * $foreignExchangeRate ,
                'withhold_amount_in_main_currency'=>$withholdAmountInInvoiceCurrency* $invoiceExchangeRate,
                'settlement_in_invoice_exchange_rate'=>$settlementAmountInInvoiceCurrency*$invoiceExchangeRate
            ] ;
        }
        return [
            'settlement_amount_in_main_currency'=>0,
            'withhold_amount_in_main_currency'=>0,
            'settlement_in_invoice_exchange_rate'=>0
        ];
    }
    public function storeNewSettlement(array $settlements, int $partnerId, Company $company, bool $isFromDownPayment = false, bool $syncWithOdoo = true):array
    {
		
        $totalWithholdAmount= 0 ;
		$storedSettlements = [];
		$shouldSyncWithOdoo = $company->hasOdooIntegrationCredentials() && $syncWithOdoo ;
        foreach ($settlements as $settlementArr) {
            $settlementArr['settlement_amount'] = isset($settlementArr['settlement_amount']) ?  unformat_number($settlementArr['settlement_amount']) :  0 ;
            if ($settlementArr['settlement_amount'] > 0) {
                $settlementArr['company_id'] = $company->id ;
                $settlementArr['partner_id'] = $partnerId;
                $settlementArr['is_from_down_payment'] = $isFromDownPayment ;
                $withholdAmount = isset($settlementArr['withhold_amount']) ? unformat_number($settlementArr['withhold_amount']) : 0 ;
                $settlementArr['withhold_amount'] = $withholdAmount ;
                $totalWithholdAmount += $withholdAmount  ;
                unset($settlementArr['net_balance']);
				$settlement =  $this->settlements()->create($settlementArr);
                if ($shouldSyncWithOdoo && $company->withinIntegrationDate($this->getDate())) {
					/**
					 * * كل تسوية ليها استدعاء مستقل بعد الكوميت
					 * * فشل تسوية في أودو مش بيوقف باقي التسويات ولا بيرجع الداتا المحلية
					 */
                    OdooSync::defer(function () use ($company, $settlement) {
						(new OdooPayment($company))->createPayment($settlement);
					}, $this, 'Create Odoo payment for settlement');
                }
				$storedSettlements[]=$settlement;
                
            }
        }
        return [
			'total_withhold_amount'=>$totalWithholdAmount ,
			'settlements'=>$storedSettlements
			] ;
    }
    public function getTotalSettlementAmount()
    {
        return $this->settlements->sum('settlement_amount');
    }
    
    public function getTotalSettlementAmountFormatted()
    {
        return number_format($this->getTotalSettlementAmount());
    }
    public function getTotalSettlementAmountForDownPayment()
    {
        if ($this->isInvoiceSettlementWithDownPayment()) {
            return $this->settlementsForDownPaymentThatComeFromMoneyModel->sum('settlement_amount');
        }
        return $this->getTotalSettlementAmount();
    }
    public function getTotalSettlementAmountForDownPaymentFormatted()
    {
        return number_format($this->getTotalSettlementAmountForDownPayment());
    }
    public function getTotalSettlementsNetBalance()
    {
        return $this->getAmount()  - $this->getTotalSettlementAmount();
    }
    public function getTotalSettlementsNetBalanceForDownPayment()
    {
        if ($this->isInvoiceSettlementWithDownPayment()) {
            return $this->getDownPaymentAmount()  - $this->getTotalSettlementAmountForDownPayment();
        }
        return $this->getAmountInInvoiceCurrency()  - $this->getTotalSettlementAmount();
    }
    public function setDownPaymentSettlementDateAttribute($value)
    {
        $date = explode('/', $value);
        if (count($date) != 3) {
            $this->attributes['down_payment_settlement_date'] = $value ;

            return ;
        }
        $month = $date[0];
        $day = $date[1];
        $year = $date[2];

        $this->attributes['down_payment_settlement_date'] = $year . '-' . $month . '-' . $day;
    }
    public function getDownPaymentSettlementDate()
    {
        return $this->down_payment_settlement_date;
    }

    public function getDownPaymentSettlementDateFormatted()
    {
        $downPaymentSettlement = $this->getDownPaymentSettlementDate();

        return  $downPaymentSettlement ? Carbon::make($downPaymentSettlement)->format('d-m-Y') : null ;
    }
    public function isUserType(string $type):bool
    {
        return $this->partner->{$type} == 1 ;
    }
    
    
    public function getDownPaymentAmount()
    {
        if ($this->isDownPayment()) {
            return $this->getAmountInInvoiceCurrency();
        } elseif ($this->isInvoiceSettlementWithDownPayment()) {
            return $this->downPaymentSettlements->sum('down_payment_amount') ;
        }
        throw new \Exception('Customer Exception .. Not Down Payment');
    }
    public function getDownPaymentAmountFormatted()
    {
        return number_format($this->getDownPaymentAmount());
    }
   
    // public function getReceivingOrPaymentCurrency():string
    // {
    //     if ($this instanceof MoneyReceived) {
    //         return $this->getReceivingCurrency();
    //     }
    //     return $this->getPaymentCurrency();

    // }
    public function getReceivingOrPaymentMoneyDate():string
    {
        if ($this instanceof MoneyReceived) {
            return $this->getReceivingDate();
        }
        return $this->getDeliveryDate();
        // if($this instanceof MoneyPayment){
            
        // }
        // throw new \Exception('Customer Exception Invalid Money Type');
    }
    public function getReceivingOrPaymentMoneyDateFormatted():string
    {
        if ($this instanceof MoneyReceived) {
            return $this->getReceivingDateFormatted();
        }
        return $this->getDeliveryDateFormatted();
        // if($this instanceof MoneyPayment){
        // }
        // throw new \Exception('Customer Exception Invalid Money Type');
    }
    public static function getAllUniquePartnerIdsForCheques(int $companyId, $currencyName)
    {
        return self::where('company_id', $companyId)
        ->where('type', 'cheque')
        ->where('currency', $currencyName)
        ->get()->pluck('partner_id', 'partner_id')->toArray();
    }
    public function getFinancialInstitution()
    {
        return FinancialInstitution::find($this->getFinancialInstitutionId());
    }
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id', 'id');
    }
    public function partner()
    {
        return $this->belongsTo(Partner::class, 'partner_id', 'id');
    }
    public function getDownPaymentType()
    {
        return $this->down_payment_type ;
    }
    public function isDownPaymentOverContract()
    {
        return $this->getDownPaymentType() == self::DOWN_PAYMENT_OVER_CONTRACT;
    }
    public function getContractName()
    {
        return $this->contract ? $this->contract->getName() : '-';
    }
    public function getContractCode()
    {
        return $this->contract ? $this->contract->getCode() : '-';
    }
    public function getContractAmount()
    {
        return $this->contract ? $this->contract->getAmount() : 0;
    }
    public function getContractAmountFormatted()
    {
        return $this->contract ? $this->contract->getAmountFormatted() : 0;
    }
    public function isDownPayment()
    {
        return $this->getMoneyType() == 'down-payment';
    }
    public function isGeneralDownPayment()
    {
        return $this->isDownPayment() && $this->getDownPaymentType() == self::DOWN_PAYMENT_GENERAL;
    }
    public function isSettlementOfOpeningBalance()
    {
        return $this->isDownPayment() && $this->getDownPaymentType() == self::SETTLEMENT_OF_OPENING_BALANCE;
    }
    public function isOverContractDownPayment()
    {
        return $this->isDownPayment() && $this->getDownPaymentType() == self::DOWN_PAYMENT_OVER_CONTRACT;
    }
    public function getForeignExchangeRateAtDate(string $currency, Company $company)
    {
        return ForeignExchangeRate::getExchangeRateForCurrencyAndClosestDate($currency, $company->getMainFunctionalCurrency(), $this->getDate(), $this->company->id);
    }
    public function getAmountForMainCurrency()
    {
        $company =$this->company ;
        $mainFunctionalCurrency = $company->getMainFunctionalCurrency() ;
        $receivingCurrency=  $this->getReceivingOrPaymentCurrency();
        $receivingDate = $this->getReceivingOrPaymentMoneyDate();
        $foreignExchangeRate = ForeignExchangeRate::getExchangeRateForCurrencyAndClosestDate($receivingCurrency, $mainFunctionalCurrency, $receivingDate, $company->id);
        $amount  = $this->getAmount();
        if ($mainFunctionalCurrency == $receivingCurrency) {
            return $amount ;
        }
        return $amount * $foreignExchangeRate;
    
    }
    public function getTotalWithholdInInvoiceExchangeRate()
    {
        $totalWithhold = 0 ;
        foreach ($this->settlements as $settlement) {
			
            $invoiceExchangeRate = $settlement->invoice->getExchangeRate();
            $totalWithhold+= $settlement->getWithhold() * $invoiceExchangeRate;
        }
        return $totalWithhold;
    }
    public function getOdooReferenceNames():array
    {
        $result = [];
        foreach ($this->settlements as $settlement) {
            if ($settlement->odoo_reference_name) {
                $result[]=$settlement->odoo_reference_name;
            }
            if ($settlement->odoo_reference) {
                $result[]=$settlement->odoo_reference;
            }
        }
        if ($this->odoo_reference) {
            $result[] = $this->odoo_reference ;
        }
        
        return $result;
    }
    public function getInboundOrOutbound()
    {
        return $this instanceof MoneyReceived ? 'inbound':'outbound';
    }
    public function isCash():bool
    {
        $isCashInSafeOrCashPayment = false ;
        if ($this instanceof MoneyReceived && $this->isCashInSafe()) {
            $isCashInSafeOrCashPayment = true ;
        }
        if ($this instanceof MoneyPayment && $this->isCashPayment()) {
            $isCashInSafeOrCashPayment = true ;
        }
        return $isCashInSafeOrCashPayment ;
    }
    public function isAdvancedOpeningBalance():bool
    {
        return $this->advanced_opening_balance_id != null ;
    }
    public function hasOdooError():bool
    {
        return !$this->synced_with_odoo && $this->odoo_error_message;
    }
    public function fullyIntegratedWithOdoo():bool
    {
        return !$this->hasOdooError() && count($this->getOdooReferenceNames());
    }
    public function getOdooError()
    {
        if ($this->hasOdooError()) {
            return $this->odoo_error_message;
        }
        return '';
    }
    public function hasUnappliedOrDownPayment():bool
    {
        return (bool) $this->has_unapplied_or_down_payment;
    }
    public function getBranch():?Branch
    {
        if ($this instanceof MoneyReceived) {
            return $this->cashInSafeReceivingBranch() ;
        }
        return $this->cashPaymentDeliveryBranch();
    }
    public function getPaymentMethodLineId()
    {
        if ($this instanceof MoneyReceived) {
            if ($this->isCashInSafe()) {
                return (int)$this->getBranch()->getOdooInboundTransferPaymentMethodId();
            }
            if ($this->isCashInBank()) {
                $financialInstitution = $this->cashInBank->receivingBank;
                $accountTypeId = $this->getCashInBankAccountTypeId();
                $accountNumber = $this->getCashInBankAccountNumber();
                return (int)$financialInstitution->getOdooPaymentIds($accountTypeId, $accountNumber)['odoo_inbound_transfer_payment_method_id'];
                
            }
            if ($this->isIncomingTransfer()) {
                $financialInstitution = $this->incomingTransferReceivingBank();
                $accountTypeId = $this->getIncomingTransferAccountTypeId();
                $accountNumber = $this->getIncomingTransferAccountNumber();
                return (int)$financialInstitution->getOdooPaymentIds($accountTypeId, $accountNumber)['odoo_inbound_transfer_payment_method_id'];
            }
            if ($this->isCheque()) {
                $cheque = $this->cheque ;
                if ($cheque->isInSafe()) {
                    return (int)$cheque->branch->getOdooInboundChequePaymentMethodId();
                }
                $financialInstitution = $cheque->drawlBank;
                $accountTypeId = $cheque->account_type;
                $accountNumber  = $cheque->account_number;
                return (int)$financialInstitution->getOdooPaymentIds($accountTypeId, $accountNumber)['odoo_inbound_cheque_payment_method_id'];
            }
            
        }
        
        else{
			if ($this->isCashPayment()) {
				return (int)$this->getBranch()->getOdooOutboundTransferPaymentMethodId();
			}
			if ($this->isOutgoingTransfer()) {
				$financialInstitution = $this->outgoingTransferDeliveryBank();
				$accountTypeId = $this->getOutgoingTransferAccountTypeId();
				$accountNumber = $this->getOutgoingTransferAccountNumber();
				return (int)$financialInstitution->getOdooPaymentIds($accountTypeId, $accountNumber)['odoo_outbound_transfer_payment_method_id'];
			}
			if ($this->isPayableCheque()) {
				$payableCheque = $this->payableCheque ;
				$financialInstitution = $payableCheque->deliveryBank;
				$accountTypeId = $payableCheque->account_type;
				$accountNumber  = $payableCheque->account_number;
				return (int)$financialInstitution->getOdooPaymentIds($accountTypeId, $accountNumber)['odoo_outbound_cheque_payment_method_id'];
			}
		}
        
            
        
        
    }
    // public function isChequeOrChequePayment():bool
    // {
    //     if ($this instanceof MoneyReceived) {
    //         return $this->isCheque();
    //     }
    //     return $this->isPayableCheque();
    // }
    public function getChequeJournalId()
    {
        $this->refresh();
        if ($this instanceof MoneyReceived) {
			$cheque = $this->cheque;
            if ($cheque->isInSafe()) {
                return $cheque->branch->getJournalId();
            }
                
            $financialInstitution = $cheque->drawlBank;
            
            $accountTypeId = $cheque->account_type;
            $accountNumber  = $cheque->account_number;
            return $financialInstitution->getJournalIdForAccount($accountTypeId, $accountNumber);
                
        }
        // elseif ($this instanceof MoneyPayment ) {
			$payableCheque = $this->payableCheque;
			if($payableCheque){
				$financialInstitution = $payableCheque->deliveryBank;
				$accountTypeId = $payableCheque->account_type;
				$accountNumber  = $payableCheque->account_number;
				return $financialInstitution->getJournalIdForAccount($accountTypeId, $accountNumber);
					
				
			}
        // }
    
        return 0 ;
    }
    
    public function getChequeOdooId():int
    {
        if ($this instanceof MoneyReceived) {
			$cheque = $this->cheque;
            if ($cheque->isInSafe()) {
                return $cheque->branch->getOdooId();
            }
         
        }
        if ($this instanceof MoneyPayment ) {
			$payableCheque = $this->payableCheque;
			if($payableCheque){
				$financialInstitution = $payableCheque->deliveryBank;
				$accountTypeId = $payableCheque->account_type;
				$accountNumber = $payableCheque->account_number;

				return (int) $financialInstitution->getOdooIdForAccount($accountTypeId, $accountNumber);
			}
		}
		return 0 ;
	}
    
    /**
     * * بيأجل إلغاء ربط التسوية في أودو لبعد ما الترانزاكشن تكومِت
     * * بنقرأ ال ids دلوقتي لأن صف التسوية نفسه هيتحذف قبل ما الاستدعاء يتنفذ
     */
    protected function deferSettlementOdooUnlink(Company $company, $settlement): void
    {
        $bankStatementLineId = $settlement->account_bank_statement_line_id;
        $odooMoveId = $settlement->odoo_move_id;
        $odooId = $settlement->odoo_id;
        $invoiceOdooId = $settlement->invoice ? $settlement->invoice->odoo_id : null;

        if ($bankStatementLineId) {
            OdooSync::defer(function () use ($company, $bankStatementLineId) {
                (new OdooPayment($company))->unlinkBankCollection($bankStatementLineId);
            }, null, 'Unlink Odoo bank collection #'.$bankStatementLineId);
        } elseif ($odooMoveId && $invoiceOdooId) {
            OdooSync::defer(function () use ($company, $odooMoveId) {
                (new OdooPayment($company))->unlink($odooMoveId);
            }, null, 'Unlink Odoo move #'.$odooMoveId);
        } elseif ($odooId) {
            OdooSync::defer(function () use ($company, $odooId) {
                (new OdooPayment($company))->cancelPayments($odooId);
            }, null, 'Cancel Odoo payment #'.$odooId);
        }
    }

    public function handleOdooDownPayments($OdooPaymentService, $hasOdooIntegration)
    {
        
        if ($hasOdooIntegration && $this->isDownPayment()) {
            $OdooPaymentService->reCreateDownPayment($this);
        } elseif ($hasOdooIntegration && $this->isChequeAndNotCustomerOrSupplier()) {
            $OdooPaymentService->reCreateDownPayment($this);
        }
        
    }
    
    public function markOpeningPayableChequeAsPaidInOdoo(bool $isMoneyReceived)
    {
        $cheque = null;
        if ($this instanceof MoneyReceived) {
			/** @phpstan-ignore-next-line */
			$cheque = $this->cheque;
		}
		if ($this instanceof MoneyPayment) {
			/** @phpstan-ignore-next-line */
			$cheque = $this->payableCheque;
		}
		if(!$cheque){
			return;
		}
        $actualPaymentDate = $isMoneyReceived ? $cheque->actual_collection_date : $cheque->actual_payment_date  ;
        $company = $this->company;
        //  $odooPaymentService = new OdooPayment($company);
        $odooSetting = $company->odooSetting;
        $financialInstitution = $isMoneyReceived ? $cheque->drawlBank : $cheque->deliveryBank;
		/** @phpstan-ignore-next-line */
        $currency = $isMoneyReceived  ? $this->getReceivingCurrency() :  $this->getPaymentCurrency();
        $hasSettlements =  $this->settlements->count()  ;
        // $hasSettlements = $this->settlements && $this->settlements->count()  ;
        $items = $hasSettlements ? $this->settlements : [$this];
        //      $debitAccountOdooId = $odooSetting->getChequesPayableId();
        $odooCurrencyId =Currency::getOdooId($currency);
        $accountTypeId=$cheque->getAccountTypeId();
        $accountNumber = $cheque->getAccountNumber();
        $journalId = $financialInstitution->getJournalIdForAccount($accountTypeId, $accountNumber);
        
        $odooPartnerId = $this->getPartnerOdooId();
     
        $debitOdooAccountId = null ;
        $creditOdooAccountId = null ;
        if ($isMoneyReceived) {
            $debitOdooAccountId = $financialInstitution->getOdooIdForAccount($accountTypeId, $accountNumber);
            $creditOdooAccountId = $odooSetting->getChequesReceivableId();
        } else {
            $debitOdooAccountId =  $odooSetting->getChequesPayableId();
            $creditOdooAccountId = $financialInstitution->getOdooIdForAccount($accountTypeId, $accountNumber);
        }
        $mainFunctionalCurrency = $company->getMainFunctionalCurrency();
        if ($this->isInvoiceSettlementWithDownPayment()) {
            $items->push($this);
        }
        $this->unlinkNonCustomerOrSupplierOdooExpense();
        foreach ($items as $settlementOrMoneyModel) {
            $ref = $isMoneyReceived ?  __('Cheque Collection') : __('Cheque Payment') ;
            $amount= $settlementOrMoneyModel->getAmount();
            $isMoneyPayment  = $settlementOrMoneyModel instanceof MoneyPayment ;
            if ($isMoneyPayment && $this->isInvoiceSettlementWithDownPayment()) {
                $amount = $this->downPaymentSettlements->sum('down_payment_amount');
            }
           
            $cashExpenseOdooService = new CashExpenseOdooService($company);
            $amountInMainFunctionalCurrency  = $currency != $mainFunctionalCurrency  ? $amount * $this->getExchangeRate() : $amount ;
            $result = $cashExpenseOdooService->createCashExpense('', $actualPaymentDate, $amount, $amountInMainFunctionalCurrency, $journalId, $odooCurrencyId, $debitOdooAccountId, $creditOdooAccountId, [], $ref, $odooPartnerId, $isMoneyReceived);
            $settlementOrMoneyModel->update([
                 'account_bank_statement_line_id'=>$result['account_bank_statement_line_id'],
                'odoo_reference'=>$result['reference'],
                'journal_entry_id'=>$result['journal_entry_id']
            ]);
                
        }
                
    }
	public function generateDownPaymentMessage():string
	{
		$isReceiving = $this instanceof MoneyReceived ;
		$receivingOrPaidText = $isReceiving ? __('Receiving')  : __('Paid');
		$receivingOrPaidToText = $isReceiving ? __('Receiving From')  : __('Paid To');
		$downPaymentFromOrToText = $isReceiving ? __('DownPayment From') : __('DownPayment To');
		$partnerTypeFormatted = $this->partner->getTypeFormatted($this->partner_type);
			if($this->isGeneralDownPayment() || $this->isOverContractDownPayment()){
				return $receivingOrPaidText . ' '. __(' DownPayment');
			}
			if($this->isInvoiceSettlementWithDownPayment()){
				return  $downPaymentFromOrToText . ' ' . $partnerTypeFormatted;
			}
			return $receivingOrPaidToText .' '. $partnerTypeFormatted ;
	}
	public function getTransactionType()
    {
        return $this->transaction_type;
    }
    // public function markOpeningReceivedChequeAsPaidInOdoo()
    // {
    //     $actualPaymentDate = $this->payableCheque->actual_payment_date  ;
    //     $company = $this->company;
    //     //  $odooPaymentService = new OdooPayment($company);
    //     $odooSetting = $company->odooSetting;
    //     $financialInstitution = $this->payableCheque->deliveryBank;
    //     $currency = $this->getCurrency();
    //     $hasSettlements = $this->settlements && $this->settlements->count()  ;
    //     $items = $hasSettlements ? $this->settlements : [$this];
    //     //      $debitAccountOdooId = $odooSetting->getChequesPayableId();
    //     $odooCurrencyId =Currency::getOdooId($currency);
    //     $accountTypeId=$this->payableCheque->getAccountTypeId();
    //     $accountNumber = $this->payableCheque->getAccountNumber();
    //     $journalId = $financialInstitution->getJournalIdForAccount($accountTypeId, $accountNumber);
    //     $creditOdooAccountId = $financialInstitution->getOdooIdForAccount($accountTypeId, $accountNumber);
    //     $odooPartnerId = $this->getPartnerOdooId();
    //     $debitOdooAccountId = $odooSetting->getChequesPayableId();
    //     $mainFunctionalCurrency = $company->getMainFunctionalCurrency();
    //     $this->unlinkNonCustomerOrSupplierOdooExpense();
    //     foreach ($items as $settlementOrMoneyModel) {
    //         $ref = 'Cheque Payment ' . $settlementOrMoneyModel->getInvoiceNumber();
    //         $amount= $settlementOrMoneyModel->getAmount();
    //         $cashExpenseOdooService = new CashExpenseOdooService($company);
    //         $amountInMainFunctionalCurrency  = $currency != $mainFunctionalCurrency  ? $amount * ForeignExchangeRate::getExchangeRateForCurrencyAndClosestDate($currency, $mainFunctionalCurrency, $actualPaymentDate, $company->id) : $amount ;
    //         $result = $cashExpenseOdooService->createCashExpense('', $actualPaymentDate, $amount, $amountInMainFunctionalCurrency, $journalId, $odooCurrencyId, $debitOdooAccountId, $creditOdooAccountId, [], $ref, $odooPartnerId);
    //         $settlementOrMoneyModel->update([
    //                 'account_bank_statement_line_id'=>$result['account_bank_statement_line_id'],
    //             'odoo_reference'=>$result['reference'],
    //             'journal_entry_id'=>$result['journal_entry_id']
    //         ]);
                
    //     }
                
    // }
    
    

}
