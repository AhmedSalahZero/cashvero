<?php
namespace App\Traits\Models;

use App\Models\CashExpense;
use App\Models\Currency;
use App\Models\ForeignExchangeRate;
use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use App\Services\Api\MoneyPaymentOdooService;
use App\Services\Api\OdooPayment;

trait HasNonCustomerOrSupplier
{
    public function storeNonCustomerOrSupplierOdooExpense(bool $isDownPayment)
    {
		
        $company = $this->company ;
        $date = $this->getDate();
        if ($company->hasOdooIntegrationCredentials() && $company->withinIntegrationDate($date)) {
            $isMoneyReceived = $this instanceof MoneyReceived ;
            $odooPaymentService = new OdooPayment($company);
            if ($this->isChequeAndNotCustomerOrSupplier()) {
                $result = $odooPaymentService->createDownPayment($this);
                return ;
            }
		
            $moneyPaymentOdooService = new MoneyPaymentOdooService($company);
			$isNotCashExpense = !($this instanceof CashExpense);
            $amountInCurrency = $isDownPayment && $isNotCashExpense  ? $this->getDownPaymentAmount() :  $this->getAmount();
            $paidCurrencyName = $this->getReceivingOrPaymentCurrency();
            $mainFunctionalCurrency = $company->getMainFunctionalCurrency();
			$exchangeRate = $paidCurrencyName != $mainFunctionalCurrency ? ForeignExchangeRate::getExchangeRateForCurrencyAndClosestDate($paidCurrencyName, $mainFunctionalCurrency, $date, $company->id) : $this->getExchangeRate();
            $amountInMainFunctionalCurrency = $mainFunctionalCurrency != $paidCurrencyName ? $exchangeRate * $amountInCurrency : $amountInCurrency;
            $journalId = $moneyPaymentOdooService->getJournalId($this) ;
			if($this->isOverContractDownPayment() && $paidCurrencyName == $mainFunctionalCurrency ){
				$exchangeRate = $this->getExchangeRate();
				$amountInCurrency = $exchangeRate * $amountInCurrency;
				$amountInMainFunctionalCurrency = $amountInCurrency;
			}
			/**
			 * * money payments
			 */
            $odooIdWithRef =  $this->getOdooIdWithRefOfTransaction() ;
            $chartOfAccountOdooId = $moneyPaymentOdooService->getChartOfAccountId($this);
            $odooCurrencyId = Currency::getOdooId($paidCurrencyName);
            $creditOdooAccountId=$isMoneyReceived ? $odooIdWithRef['id'] : $chartOfAccountOdooId;
            $debitOdooAccountId = $isMoneyReceived ?  $chartOfAccountOdooId : $odooIdWithRef['id'] ;
            $isTax = $this->partner->isTax();
            $odooPartnerId = $this->partner->getOdooId();
	
            $ref =$odooIdWithRef['ref'] ;
			/**
			 * * في الماني ريسيد هنضربها في سالب عليشان بتنضرب جوة في السالب فا تبقي موجب
			 */
			$userComment = $this->getUserComment();
            $result   = $moneyPaymentOdooService->createCashExpense($date, $amountInCurrency, $amountInMainFunctionalCurrency, $journalId, $odooCurrencyId, $debitOdooAccountId, $creditOdooAccountId, $odooPartnerId, $ref, $isTax,$isMoneyReceived,$userComment);
            $this->account_bank_statement_line_id = $result['account_bank_statement_line_id'];
            $this->journal_entry_id = $result['journal_entry_id'];
            $this->odoo_reference = $result['odoo_reference'];
            $this->save();
                
            
        }
    }
    public function unlinkNonCustomerOrSupplierOdooExpense()
    {
        $company = $this->company ;
        $journalEntryId = $this->journal_entry_id;
      
        if ($company->hasOdooIntegrationCredentials() && $journalEntryId) {
            $moneyPaymentOdooService = new MoneyPaymentOdooService($company);
            $moneyPaymentOdooService->unlink($journalEntryId);
        } elseif ($company->hasOdooIntegrationCredentials()) {
            $company =$this->company;
            if ($company->hasOdooIntegrationCredentials()) {
                $odooId = $this->odoo_id ;
                if ($odooId) {
                    $odooPaymentService = new OdooPayment($company);
                    $odooPaymentService->cancelDownPayment($odooId);
                }
            }
        }
        
            
    }

}
