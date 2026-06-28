<?php
namespace App\Traits;

use App\Models\AccountType;
use App\Models\Currency;
use App\Models\CurrentAccountBankStatement;
use App\Models\FinancialInstitutionAccount;
use App\Models\TimeOfDeposit;
use App\OdooSetting;
use App\Services\Api\CashExpenseOdooService;
use App\Services\Api\TimeOrCertificateOfDepositOdooService;

trait HasDepositAccount
{
	/**
	 * * نوع الحساب اللي هيتخصم منه الوديعة وهنا احنا معتبرينه علطول حساب جاري ولو فاضي هنعتبرها 
	 * * opening balance
	 */
	// public function getDeductedFromAccountTypeId():?int 
	// {
	// 	return $this->deducted_from_account_type_id;
	// }
	public function getDeductedFromAccountId():?int 
	{
		return $this->deducted_from_account_id;
	}
	public function handleDeductedForBankStatement(int $financialInstitutionId,string $date , float $amount , int $companyId,int $deductedFromAccountId,$accountNumber)
	{
		$commentEn=$this instanceof TimeOfDeposit ? __('Time Of Deposit') . ' '. $accountNumber : __('Certificate Of Deposit')  . ' '. $accountNumber;
		$commentAr=$this instanceof TimeOfDeposit ? __('Time Of Deposit') . ' '. $accountNumber : __('Certificate Of Deposit')  . ' '. $accountNumber;
		
		CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($this->currentAccountBankStatements->where('type',CurrentAccountBankStatement::DEDUCTED_FOR_CURRENT_ACCOUNT));
		if($deductedFromAccountId){
			$accountType = AccountType::onlyCurrentAccount()->first();
			$accountNumber = FinancialInstitutionAccount::find($deductedFromAccountId)->getAccountNumber();
			$amount = number_unformat($amount);
			$type = CurrentAccountBankStatement::DEDUCTED_FOR_CURRENT_ACCOUNT;
			$this->handleCreditStatement($companyId,$financialInstitutionId , $accountType , $accountNumber , null , $date,$amount,null,null,$commentEn,$commentAr,$type);
			
		}
	}
	public function isOpeningBalance():bool
	{
		return is_null($this->deducted_from_account_id) || $this->deducted_from_account_id ==0 ;
	}
	public function handleTdOrCdStoreDepositForOdoo(bool $isBreakOrApplyDeposit)
	{
		
		$company = $this->company ; 
		$isOpeningBalance = $this->isOpeningBalance(); 
		$date = $this->getStartDate();
		$this->deleteOdooRelations($isBreakOrApplyDeposit);
		if($company->hasOdooIntegrationCredentials() && $company->withinIntegrationDate($date) && (! $isOpeningBalance || $isBreakOrApplyDeposit) ){
			$this->handleTdOrCdStoreDepositWithoutJournalForOdoo($isBreakOrApplyDeposit);
		}
	}
	
	
	
	public function handleTdOrCdStoreDepositWithoutJournalForOdoo(bool $isBreakOrApplyDeposit)
	{
		
		$company = $this->company ; 
		// $isOpeningBalance = $this->isOpeningBalance();
		$this->deleteOdooRelations($isBreakOrApplyDeposit);
		// if($company->hasOdooIntegrationCredentials() && !$isOpeningBalance){
			$referenceColumnName = $isBreakOrApplyDeposit ? 'inbound_break_odoo_reference' : 'inbound_odoo_reference';
			$journalColumnName = $isBreakOrApplyDeposit ? 'store_break_journal_entry_id' : 'inbound_journal_entry_id';
  			$timeOfCertificateOdooService = new TimeOrCertificateOfDepositOdooService($company);
			$fromFinancialInstitution = $this->financialInstitution;
			$fromAccountTypeId = 27 ;
			$toAccountTypeId = $this->getAccountTypeId()  ;
			$toAccountNumber = $this->getAccountNumber() ;
			
			// $toAccountTypeId = $isBreakOrApplyDeposit ? $fromAccountTypeId : ($this instanceof TimeOfDeposit ? 28 : 29  );
			// $fromAccountTypeId = $isBreakOrApplyDeposit ? ($this instanceof TimeOfDeposit ? 28 : 29) :$fromAccountTypeId ; 
			
			
			$amount = $this->getAmount();
			$currencyName = $this->getCurrency();
			$date = $isBreakOrApplyDeposit ?  $this->getDepositDateOrBreakDate() : $this->getStartDate();
			$odooCurrencyId = Currency::getOdooId($currencyName);
			$fromAccountNumber = FinancialInstitutionAccount::find($this->deducted_from_account_id)->getAccountNumber();
			$fromJournalId = $fromFinancialInstitution->getJournalIdForAccount($fromAccountTypeId,$fromAccountNumber);
			 $fromOdooId = $fromFinancialInstitution->getOdooIdForAccount($fromAccountTypeId,$fromAccountNumber);
			$toOdooId = $fromFinancialInstitution->getOdooIdForAccount($toAccountTypeId,$toAccountNumber);
			$ref = $isBreakOrApplyDeposit ? $this->getBreakReference() : $this->getCreateReference();
			$message = $ref;
			$result = $timeOfCertificateOdooService->createAndPostJournalEntry($date,$amount*-1,$odooCurrencyId,$fromJournalId,$fromOdooId,$toOdooId,$ref,null,$message,$isBreakOrApplyDeposit);
			// $this->{$storeAccountBankStatementLineColumnName} = $result['account_bank_statement_line_id'];
			$this->{$journalColumnName} = $result['journal_entry_id'];
			$this->{$referenceColumnName} = $result['reference'];
			$this->save();
				
		// }
	}

	public function storeRenewal(string $expiryDate,float $newInterestRate)
	{
		$company = $this->company ;
		$isOpeningBalance = $this->isOpeningBalance();
		if($company->hasOdooIntegrationCredentials() && !$isOpeningBalance){
				$interestAmount = $this->getInterestAmount();
			$timeOfCertificateOdooService = new TimeOrCertificateOfDepositOdooService($company);
			$fromFinancialInstitution = $this->financialInstitution;
			$debitAccountTypeId = 27 ;
			$currencyName = $this->getCurrency();
			$date = $expiryDate;
			$odooCurrencyId = Currency::getOdooId($currencyName);
			/**
			 * @var OdooSetting $odooSetting
			 */
			$odooSetting = $company->odooSetting ;
	//		$creditAccountTypeId = $this instanceof TimeOfDeposit ? 28 : 29  ;
			$debitAccountNumber = FinancialInstitutionAccount::find($this->deducted_from_account_id)->getAccountNumber();
			//$creditAccountNumber = $this->getAccountNumber() ;
			$toFinancialInstitution = $fromFinancialInstitution;
			// $creditJournalId = $fromFinancialInstitution->getJournalIdForAccount($creditAccountTypeId,$creditAccountNumber);
			//  $creditOdooId = $fromFinancialInstitution->getOdooIdForAccount($creditAccountTypeId,$creditAccountNumber);
			$debitJournalId = $toFinancialInstitution->getJournalIdForAccount($debitAccountTypeId,$debitAccountNumber);
			$debitOdooId = $toFinancialInstitution->getOdooIdForAccount($debitAccountTypeId,$debitAccountNumber);
			$creditAccountTypeId = $odooSetting->getInterestRevenueOdooId();
			$ref =$this instanceof TimeOfDeposit ? __('Time Of Deposit Renewal Interest') : __('Certificate Of Deposit Renewal Interest');
			$message=$ref;
			$interestAmount = $newInterestRate;
			$result = $timeOfCertificateOdooService->createMoneyDepositInBank($date,$interestAmount,$odooCurrencyId,$debitJournalId,$debitOdooId,$creditAccountTypeId,$ref,null,$message);
			$this->renewal_account_bank_statement_line_id = $result['account_bank_statement_line_id'];
			$this->renewal_journal_entry_id = $result['journal_entry_id'];
			$this->save();
		}
	
			
	}

	public function reverseOdooDeposit(CurrentAccountBankStatement $breakInterestStatement)
	{
		$company = $this->company;
		if($company->hasOdooIntegrationCredentials()){
			$this->deletePeriodInterest($breakInterestStatement);
			(new CashExpenseOdooService($company))->unlink($this->store_break_journal_entry_id);
		}
	}	
	
}
