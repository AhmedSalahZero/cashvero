<?php 
namespace App\Traits\Models;

use App\Models\CleanOverdraft;
use App\Models\FullySecuredOverdraft;
use App\Models\OverdraftAgainstAssignmentOfContract;
use App\Models\OverdraftAgainstCommercialPaper;



trait HasDebitOverdraftStatement 
{
	public function storeCleanOverdraftDebitBankStatement(string $moneyType , CleanOverdraft $cleanOverdraft , string $date , $debit )
	{
		return $this->cleanOverdraftDebitBankStatement()->create([
			'type'=>$moneyType ,
			'clean_overdraft_id'=>$cleanOverdraft->id ,
			'company_id'=>$this->company_id ,
			'date'=>$date,
			'limit'=>$cleanOverdraft->getLimit(),
			'beginning_balance'=>0 ,
			'debit'=>$debit,
			'credit'=>0
		]) ;
	}
	public function storeFullySecuredOverdraftDebitBankStatement(string $moneyType , FullySecuredOverdraft $fullySecuredOverdraft , string $date , $debit )
	{
		return $this->fullySecuredOverdraftDebitBankStatement()->create([
			'type'=>$moneyType ,
			'fully_secured_overdraft_id'=>$fullySecuredOverdraft->id ,
			'company_id'=>$this->company_id ,
			'date'=>$date,
			'limit'=>$fullySecuredOverdraft->getLimit(),
			'beginning_balance'=>0 ,
			'debit'=>$debit,
			'credit'=>0
		]) ;
	}
	public function storeOverdraftAgainstCommercialPaperDebitBankStatement(string $moneyType , OverdraftAgainstCommercialPaper $overdraftAgainstCommercialPaper , string $date , $debit )
	{

		return $this->overdraftAgainstCommercialPaperDebitBankStatement()->create([
			'type'=>$moneyType ,
			'overdraft_against_commercial_paper_id'=>$overdraftAgainstCommercialPaper->id ,
			'company_id'=>$this->company_id ,
			'date'=>$date,
			'limit'=>$overdraftAgainstCommercialPaper->getLimit(),
			'beginning_balance'=>0 ,
			'debit'=>$debit,
			'credit'=>0
		]) ;
	}
	
	public function storeOverdraftAgainstAssignmentOfContractDebitBankStatement(string $moneyType , OverdraftAgainstAssignmentOfContract $odAgainstAssignmentOfContract , string $date , $debit )
	{

		return $this->overdraftAgainstAssignmentOfContractDebitBankStatement()->create([
			'type'=>$moneyType ,
			'overdraft_against_assignment_of_contract_id'=>$odAgainstAssignmentOfContract->id ,
			'company_id'=>$this->company_id ,
			'date'=>$date,
			'limit'=>$odAgainstAssignmentOfContract->getLimit(),
			'beginning_balance'=>0 ,
			'debit'=>$debit,
			'credit'=>0
		]) ;
	}	
}
