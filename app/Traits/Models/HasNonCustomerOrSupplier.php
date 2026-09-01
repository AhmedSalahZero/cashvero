<?php
namespace App\Traits\Models;

use App\Models\CashExpense;
use App\Models\Currency;
use App\Models\ForeignExchangeRate;
use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use App\Services\Api\MoneyPaymentOdooService;
use App\Services\Api\OdooPayment;
use App\Services\Api\OdooSync;

trait HasNonCustomerOrSupplier
{
    public function storeNonCustomerOrSupplierOdooExpense(bool $isDownPayment)
    {

        $company = $this->company ;
        $date = $this->getDate();
        if ($company->hasOdooIntegrationCredentials() && $company->withinIntegrationDate($date)) {
            /**
             * * كل الاتصال بأودو اتأجل لما الترانزاكشن تكومِت
             * * لو أودو ضرب إيرور مش هيأثر علي الداتا المحفوظة محليًا
             */
            OdooSync::defer(function () use ($isDownPayment, $company, $date) {
                $this->createNonCustomerOrSupplierOdooExpense($isDownPayment, $company, $date);
            }, $this, 'Create Odoo expense');
        }
    }

    /**
     * * الجزء اللي بيتكلم مع أودو فعليًا
     * * بيتنادي من خلال OdooSync بعد ما الداتا المحلية تتحفظ
     */
    protected function createNonCustomerOrSupplierOdooExpense(bool $isDownPayment, $company, $date): void
    {
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
    public function unlinkNonCustomerOrSupplierOdooExpense()
    {
        $company = $this->company ;
        $journalEntryId = $this->journal_entry_id;
        $odooId = $this->odoo_id ;

        if (! $company->hasOdooIntegrationCredentials()) {
            return ;
        }
        /**
         * * الحذف مش زي الإنشاء : لو الحذف في اودو فشل ما ينفعش نكون
         * * حذفنا الصف عندنا . فبنناديه جوه الترانزاكشن نفسها عشان
         * * الاستثناء يعمل rollback للداتا المحلية ، و اودو نفسه بيرجع
         * * لحالته الاصلية جوه unlink (راجع HasAtomicOdooDeletion) —
         * * فالنتيجة اما الحذف يتم عندنا و عند اودو ، او مايتمش خالص
         *
         * * (الإنشاء و التعديل لسه بيعدّوا على OdooSync::defer لأن فشلهم
         * * ما يستاهلش نلغي بيه عملية محلية صح)
         */
        if ($journalEntryId) {
            (new MoneyPaymentOdooService($company))->unlink($journalEntryId);
        } elseif ($odooId) {
            (new OdooPayment($company))->cancelDownPayment($odooId);
        }
    }

}
