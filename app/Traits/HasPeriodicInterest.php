<?php
namespace App\Traits;

use App\Models\AccountType;
use App\Models\CertificatesOfDeposit;
use App\Models\Company;
use App\Models\Currency;
use App\Models\CurrentAccountBankStatement;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionAccount;
use App\Models\ForeignExchangeRate;
use App\Models\TimeOfDeposit;
use App\Services\Api\CashExpenseOdooService;
use Carbon\Carbon;

trait HasPeriodicInterest
{
    /**
     * * يعني الفايدة بتنزل كاملة اخر المدة
     */
    public function isAtMaturity()
    {
        return $this->is_at_maturity;
    }
    /**
     * * يعني الفائدة هتنزل خلال فترة معينه
     */
    public function isPeriodically()
    {
        return !$this->isAtMaturity();
    }
    public function applyPeriodicInterestInStatement(FinancialInstitution $financialInstitution, float $periodInterestAmount, string $periodInterestDate)
    {
       
        $accountType = AccountType::where('slug', AccountType::CURRENT_ACCOUNT)->first() ;
        $periodInterestDate = Carbon::make($periodInterestDate)->format('Y-m-d');
        
        if ($periodInterestAmount > 0) {
            $accountNumber = $this->getMaturityAmountAddedToAccountNumber();
            $isPeriodInterest = true ;
            $commentEn=$this instanceof TimeOfDeposit ? __('Time Of Deposit') . ' '. $accountNumber : __('Certificate Of Deposit')  . ' '. $accountNumber;
            $commentAr=$this instanceof TimeOfDeposit ? __('Time Of Deposit') . ' '. $accountNumber : __('Certificate Of Deposit')  . ' '. $accountNumber;
            $accountStatement = $this->handleDebitStatement($financialInstitution->id, $accountType, $this->getMaturityAmountAddedToAccountNumber(), null, $periodInterestDate, $periodInterestAmount, null, null, 1, $commentEn, $commentAr, $isPeriodInterest);
            $this->storePeriodInterestOdooRelations($accountStatement,$periodInterestDate,$periodInterestAmount);
        }
    }
    public function deletePeriodInterestAmounts()
    {
		$currentAccountBankStatements = $this->currentAccountBankStatements()->where(function($q){
			$q->where('is_period_cd_or_td_interest', 1)->orWhere('is_break_interest',1);
		})->get();
		
		foreach($currentAccountBankStatements as $currentAccountBankStatement){
			$this->deletePeriodInterest($currentAccountBankStatement);
		}
    }
    public function storePeriodInterestOdooRelations($accountStatement , string $periodInterestDate, float $amount , $financialInstitutionId = null , $financialInstitutionAccountId = null,$company = null)
    {
		/**
		 * @var  Company $company
		 */
		 $company = is_null($company) ?  $this->company : $company;
		$hasOdooIntegration = $company->hasOdooIntegrationCredentials();
		$canBeIntegratedWithOdoo  = $hasOdooIntegration && $company->withinIntegrationDate($periodInterestDate) ;
		if(!$canBeIntegratedWithOdoo){
			return ;
		}
		$date = $periodInterestDate;
        $financialInstitutionId = is_null($financialInstitutionId) ?  $this->financial_institution_id : $financialInstitutionId;
		$financialInstitutionAccountId = is_null($financialInstitutionAccountId) ? $this->maturity_amount_added_to_account_id : $financialInstitutionAccountId;
        $financialInstitutionAccount = FinancialInstitutionAccount::find($financialInstitutionAccountId);
        $journalId = $financialInstitutionAccount->journal_id ;
        $currencyName = $financialInstitutionAccount->getCurrency();
        $amountInCurrency = $amount;
    
        $mainFunctionalCurrency = $company->getMainFunctionalCurrency();
        $amountInMainFunctionalCurrency  = $currencyName != $mainFunctionalCurrency  ? $amountInCurrency * ForeignExchangeRate::getExchangeRateForCurrencyAndClosestDate($currencyName, $mainFunctionalCurrency, $date, $company->id) : $amountInCurrency ;
        $creditOdooAccountId = $company->interestRevenuesAccounts->where('financial_institution_id', $financialInstitutionId)->first()->odoo_id;
        $cashExpenseOdooService = new CashExpenseOdooService($company);
        $odooCurrencyId = Currency::getOdooId($currencyName);
        $debitOdooAccountId = $financialInstitutionAccount->odoo_id;
        $paymentRef = __('Interest Revenue');
        $result = $cashExpenseOdooService->createCashExpense(null, $date, $amountInCurrency, $amountInMainFunctionalCurrency, $journalId, $odooCurrencyId, $debitOdooAccountId, $creditOdooAccountId,[],$paymentRef,null,true);
        // $accountStatement->interest_account_bank_statement_odoo_id=$result['account_bank_statement_line_id'];
        $accountStatement->interest_journal_entry_id=$result['journal_entry_id'];
        $accountStatement->interest_odoo_reference=$result['reference'];
        $accountStatement->save();
    }
	public function deletePeriodInterest(CurrentAccountBankStatement $currentAccountBankStatement)
	{
		$currentAccountBankStatements = $this->currentAccountBankStatements->where('id',$currentAccountBankStatement->id) ;
		$currentAccountBankStatement = $currentAccountBankStatements->first();
		if($currentAccountBankStatement && $currentAccountBankStatement->interest_journal_entry_id){
			(new CashExpenseOdooService($this->company))->unlink($currentAccountBankStatement->interest_journal_entry_id);
		}
		CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($currentAccountBankStatements);
	}
}
