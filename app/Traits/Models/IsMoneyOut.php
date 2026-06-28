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
use Carbon\Carbon;

/**
 * * ال تريت دا مشترك بين
 * * MoneyPayment || CashExpense
 */
trait IsMoneyOut
{

/**
     * * This Code Is The Same For Money Payments And Cash Expenses
	 * * So If You Edit Here You Should Edit In CashExpense Model Also
     */
    public function markPayableChequeAsPaidInOdoo()
    {
			
			$actualPaymentDate =  $this->payableCheque->actual_payment_date ;
        
			$company = $this->company;
			$odooPaymentService = new OdooPayment($company);
			$odooSetting = $company->odooSetting;
			$financialInstitution =  $this->payableCheque->deliveryBank ;
			$currency = $this->getCurrency();
			$hasSettlements = $this instanceof MoneyPayment  && $this->settlements->count();
			$items = $hasSettlements ? $this->settlements : [$this];
			$debitAccountOdooId = $odooSetting->getChequesPayableId();
			$odooCurrencyId =Currency::getOdooId($currency);
			$accountTypeId=$this->payableCheque->getAccountTypeId();
			$accountNumber = $this->payableCheque->getAccountNumber();
			$journalId = $financialInstitution->getJournalIdForAccount($accountTypeId, $accountNumber);
			$creditOdooAccountId = $financialInstitution->getOdooIdForAccount($accountTypeId, $accountNumber);
			$odooPartnerId = $this->getPartnerOdooId();
			if ($this->isInvoiceSettlementWithDownPayment()) {
				$items->push($this);
			}
			foreach ($items as $settlementOrMoneyModel) {
				$odooId = $settlementOrMoneyModel->odoo_id ;
				$ref = 'Cheque Payment ' . $settlementOrMoneyModel->getInvoiceNumber();
				$amount= $settlementOrMoneyModel->getAmountInReceivingCurrency();
				$isMoneyPayment  = $settlementOrMoneyModel instanceof MoneyPayment ;
				if ($isMoneyPayment && $this->isInvoiceSettlementWithDownPayment()) {
						$amount = $this->downPaymentSettlements->sum('down_payment_amount')* $this->getExchangeRate();
				
				}
				if ($settlementOrMoneyModel->account_bank_statement_line_id) {
					$odooPaymentService->unlinkBankCollection($settlementOrMoneyModel->account_bank_statement_line_id);
				}
				$res = $odooPaymentService->chequePayment($odooId, $amount, $actualPaymentDate, $odooCurrencyId, $journalId, $debitAccountOdooId, $creditOdooAccountId, $odooPartnerId, $ref);
				$settlementOrMoneyModel->update([
				'account_bank_statement_line_id'=>$res['statement_entry_id']??null,
					'odoo_reference'=>$res['bank_reference']??null
				]);
					
			}
			
       
                
    }
    
    

}
