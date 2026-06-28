<?php
namespace App\Traits\Models;

use App\Interfaces\Models\IHaveCreditOverdraftStatement;
use App\Models\AccountType;
use App\Models\CleanOverdraft;
use App\Models\FinancialInstitutionAccount;
use App\Models\FullySecuredOverdraft;
use App\Models\LetterOfCreditIssuance;
use App\Models\MoneyPayment;
use App\Models\OverdraftAgainstAssignmentOfContract;
use App\Models\OverdraftAgainstCommercialPaper;

trait HasCreditStatements
{
		/**
	 * * هنا لو اليوزر ضاف فلوس في الحساب
	 * * بنحطها في الاستيت منت
	 * * سواء كانت كاش استيتمنت او بانك استيتمنت علي حسب نوع الحساب او الحركة يعني
	 */
	public function handleCreditStatement(int $companyId , $bankId = null ,?AccountType $accountType = null , ?string $accountNumber = null,?string $moneyType = null,?string $statementDate = null,?float $paidAmount = null,$deliveryBranchId=null,?string $currencyName = null , ?string $commentEn = null , ?string $commentAr = null , ?string $type = null)
	{
		if($this instanceof IHaveCreditOverdraftStatement &&  $accountType && $accountType->getSlug() == AccountType::CLEAN_OVERDRAFT){
			$cleanOverdraft  = CleanOverdraft::findByAccountNumber($accountNumber,$companyId,$bankId);
			$this->cleanOverdraftCreditBankStatement()->create([
				'type'=>$moneyType ,
				'clean_overdraft_id'=>$cleanOverdraft->id ,
				'company_id'=>$this->company_id ,
				'date'=>$statementDate,
				'limit'=>$cleanOverdraft->getLimit(),
				'beginning_balance'=>0 ,
				'debit'=>0,
				'credit'=>$paidAmount,
				'comment_en'=>$commentEn,
				'comment_ar'=>$commentAr
			]);
		}
		elseif($this instanceof IHaveCreditOverdraftStatement &&  $accountType && $accountType->getSlug() == AccountType::FULLY_SECURED_OVERDRAFT){
			/**
			 * @var FullySecuredOverdraft $fullySecuredOverdraft
			 */
			$fullySecuredOverdraft  = FullySecuredOverdraft::findByAccountNumber($accountNumber,$companyId,$bankId);
			$this->fullySecuredOverdraftCreditBankStatement()->create([
				'type'=>$moneyType ,
				'fully_secured_overdraft_id'=>$fullySecuredOverdraft->id ,
				'company_id'=>$this->company_id ,
				'date'=>$statementDate,
				'limit'=>$fullySecuredOverdraft->getLimit(),
				'beginning_balance'=>0 ,
				'debit'=>0,
				'credit'=>$paidAmount,
				'comment_en'=>$commentEn,
				'comment_ar'=>$commentAr
			]);
			
			// $this->storeFullySecuredOverdraftCreditBankStatement($moneyType,$fullySecuredOverdraft,$statementDate,$paidAmount,$commentEn,$commentAr);
		}
		elseif($this instanceof IHaveCreditOverdraftStatement &&  $accountType && $accountType->getSlug() == AccountType::OVERDRAFT_AGAINST_COMMERCIAL_PAPER){
			/**
			 * @var OverdraftAgainstCommercialPaper $overdraftAgainstCommercialPaper
			 */
			$overdraftAgainstCommercialPaper  = OverdraftAgainstCommercialPaper::findByAccountNumber($accountNumber,$companyId,$bankId);
			$this->overdraftAgainstCommercialPaperCreditBankStatement()->create([
				'type'=>$moneyType ,
				'overdraft_against_commercial_paper_id'=>$overdraftAgainstCommercialPaper->id ,
				'company_id'=>$this->company_id ,
				'date'=>$statementDate,
				'limit'=>$overdraftAgainstCommercialPaper->getLimit(),
				'beginning_balance'=>0 ,
				'debit'=>0,
				'credit'=>$paidAmount,
				'comment_en'=>$commentEn,
				'comment_ar'=>$commentAr
			]);
			
			// $this->storeOverdraftAgainstCommercialPaperCreditBankStatement($moneyType,$overdraftAgainstCommercialPaper,$statementDate,$paidAmount,$commentEn,$commentAr);
		}
		elseif($this instanceof IHaveCreditOverdraftStatement && $accountType && $accountType->getSlug() == AccountType::OVERDRAFT_AGAINST_ASSIGNMENT_OF_CONTRACTS){
			$odAgainstAssignmentOfContract  = OverdraftAgainstAssignmentOfContract::findByAccountNumber($accountNumber,$companyId,$bankId);
			$this->overdraftAgainstAssignmentOfContractCreditBankStatement()->create([
				'type'=>$moneyType ,
				'overdraft_against_assignment_of_contract_id'=>$odAgainstAssignmentOfContract->id ,
				'company_id'=>$this->company_id ,
				'date'=>$statementDate,
				'limit'=>$odAgainstAssignmentOfContract->getLimit(),
				'beginning_balance'=>0 ,
				'debit'=>0,
				'credit'=>$paidAmount,
				'comment_en'=>$commentEn,
				'comment_ar'=>$commentAr
			]) ;
			
			// $this->storeOverdraftAgainstAssignmentOfContractCreditBankStatement($moneyType,$odAgainstAssignmentOfContract,$statementDate,$paidAmount,$commentEn,$commentAr);
		}
		elseif($this instanceof IHaveCreditOverdraftStatement && $this->isCashPayment()){
			$this->cashInSafeCreditStatement()->create([
				'branch_id'=>$deliveryBranchId,
				'currency'=>$currencyName ,
				'company_id'=>$this->company_id ,
				'credit'=>$paidAmount,
				'date'=>$statementDate,
				'comment_en'=>$commentEn , 
				'comment_ar'=>$commentAr
			]);
			
		}
		elseif($accountType && $accountType->getSlug() == AccountType::CURRENT_ACCOUNT){
			$financialInstitutionAccount = FinancialInstitutionAccount::findByAccountNumber($accountNumber,$companyId,$bankId);
			$this->currentAccountCreditBankStatement()->create([
				'financial_institution_account_id'=>$financialInstitutionAccount->id ,
				'company_id'=>$this->company_id?:getCurrentCompanyId() ,
				'credit'=>$paidAmount,
				'date'=>$statementDate,
				'comment_en'=>$commentEn,
				'comment_ar'=>$commentAr,
				'type'=>$type
			]);
			
			// $this->storeCurrentAccountCreditBankStatement($statementDate,$paidAmount,$financialInstitutionAccount->id,$commentEn,$commentAr,$type);
		}
		
	}

	// public function storeCashInSafeCreditStatement(string $date , $paidAmount , string $currencyName,int $branchId,?string $commentEn , ?string $commentAr)
	// {
		
	// 	return ;
	// }

	// public function storeCurrentAccountCreditBankStatement(string $date , $paidAmount , int $financialInstitutionAccountId,?string $commentEn = null , ?string $commentAr = null , ?string $type = null )
	// {
	// 	return $this->currentAccountCreditBankStatement()->create([
	// 		'financial_institution_account_id'=>$financialInstitutionAccountId ,
	// 		'company_id'=>$this->company_id?:getCurrentCompanyId() ,
	// 		'credit'=>$paidAmount,
	// 		'date'=>$date,
	// 		'comment_en'=>$commentEn,
	// 		'comment_ar'=>$commentAr,
	// 		'type'=>$type
	// 	]);
	// }

	// public function storeCleanOverdraftCreditBankStatement(string $moneyType , CleanOverdraft $cleanOverdraft , string $date , $paidAmount,?string $commentEn  , ?string $commentAr )
	// {
	// 	return   ;

	// }
	// public function storeFullySecuredOverdraftCreditBankStatement(string $moneyType , FullySecuredOverdraft $fullySecuredOverdraft , string $date , $paidAmount,?string $commentEn , ?string $commentAr )
	// {
	// 	return  ;

	// }
	// public function storeOverdraftAgainstCommercialPaperCreditBankStatement(string $moneyType , OverdraftAgainstCommercialPaper $overdraftAgainstCommercialPaper , string $date , $paidAmount , ?string $commentEn , ?string $commentAr )
	// {
	// 	return   ;

	// }
	
	// public function storeOverdraftAgainstAssignmentOfContractCreditBankStatement(string $moneyType , OverdraftAgainstAssignmentOfContract $odAgainstAssignmentOfContract , string $date , $paidAmount , ?string $commentEn , ?string $commentAr )
	// {
	// 	return  ;

	// }
	
	
}
