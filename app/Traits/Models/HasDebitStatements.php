<?php 
namespace App\Traits\Models;

use App\Interfaces\Models\IHasDebitCurrentAccountStatement;
use App\Models\AccountType;
use App\Models\CleanOverdraft;
use App\Models\FinancialInstitutionAccount;
use App\Models\FullySecuredOverdraft;
use App\Models\MoneyReceived;
use App\Models\OverdraftAgainstAssignmentOfContract;
use App\Models\OverdraftAgainstCommercialPaper;

trait HasDebitStatements 
{
	/**
	 * * هنا لو اليوزر ضاف فلوس في الحساب
	 * * بنحطها في الاستيت منت
	 * * سواء كانت كاش استيتمنت او بانك استيتمنت علي حسب نوع الحساب او الحركة يعني
	 */
	public function handleDebitStatement(?int $financialInstitutionId = 0 ,?AccountType $accountType = null , ?string $accountNumber = null,?string $moneyType = null,?string $date = null,?float $debit = 0,?string $currencyName = null,?int $receivingBranchId = null,$exchangeRate=1 , $commentEn = null , $commentAr = null,$isPeriodInterest=false , $isBreakInterest = false )
	{
		if($accountType  && $accountType->getSlug() == AccountType::CURRENT_ACCOUNT && $this instanceof IHasDebitCurrentAccountStatement){
			$financialInstitutionAccount = FinancialInstitutionAccount::findByAccountNumber($accountNumber,getCurrentCompanyId(),$financialInstitutionId);
			return $this->storeCurrentAccountDebitBankStatement($date,$debit,$financialInstitutionAccount->id,false,$commentEn,$commentAr,$isPeriodInterest,$isBreakInterest);
		}
		elseif($accountType && $accountType->getSlug() == AccountType::CLEAN_OVERDRAFT && $this instanceof MoneyReceived){
			$cleanOverdraft  = CleanOverdraft::findByAccountNumber($accountNumber,getCurrentCompanyId(),$financialInstitutionId);
			return $this->storeCleanOverdraftDebitBankStatement($moneyType,$cleanOverdraft,$date,$debit);
		}
		elseif($accountType && $accountType->getSlug() == AccountType::FULLY_SECURED_OVERDRAFT && $this instanceof MoneyReceived){
			$fullySecuredOverdraft  = FullySecuredOverdraft::findByAccountNumber($accountNumber,getCurrentCompanyId(),$financialInstitutionId);
			return $this->storeFullySecuredOverdraftDebitBankStatement($moneyType,$fullySecuredOverdraft,$date,$debit);
		}
		elseif($accountType && $accountType->getSlug() == AccountType::OVERDRAFT_AGAINST_COMMERCIAL_PAPER && $this instanceof MoneyReceived){
			$overdraftAgainstCommercialPaper  = OverdraftAgainstCommercialPaper::findByAccountNumber($accountNumber,getCurrentCompanyId(),$financialInstitutionId);
			return $this->storeOverdraftAgainstCommercialPaperDebitBankStatement($moneyType,$overdraftAgainstCommercialPaper,$date,$debit);
		}
		elseif($accountType && $accountType->getSlug() == AccountType::OVERDRAFT_AGAINST_ASSIGNMENT_OF_CONTRACTS && $this instanceof MoneyReceived){
			$odAgainstAssignmentOfContract  = OverdraftAgainstAssignmentOfContract::findByAccountNumber($accountNumber,getCurrentCompanyId(),$financialInstitutionId);
			return $this->storeOverdraftAgainstAssignmentOfContractDebitBankStatement($moneyType,$odAgainstAssignmentOfContract,$date,$debit);
		}
		elseif($this instanceof MoneyReceived && $this->isCashInSafe()){
			return $this->storeCashInSafeDebitStatement($date,$debit,$currencyName,$receivingBranchId,$exchangeRate);
		}
	}
	
	
	
	
	
}
