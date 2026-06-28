<?php 
namespace App\Traits\Models;



trait HasDebitCurrentAccountStatement 
{
	public function storeCurrentAccountDebitBankStatement(string $date , $debit , int $financialInstitutionAccountId , bool $isTdRenewal = false  , ?string $commentEn = null , ?string $commentAr = null,$isPeriodCdOrTdInterest = false  , $isBreakInterest =false )
	{
		return $this->currentAccountDebitBankStatement()->create([
			'financial_institution_account_id'=>$financialInstitutionAccountId,
			'company_id'=>$this->company_id ,
			'credit'=>0,
			'debit'=>$debit,
			'date'=>$date,
			'is_td_renewal'=>$isTdRenewal,
			'comment_en'=>$commentEn,
			'comment_ar'=>$commentAr,
			'is_period_cd_or_td_interest'=>$isPeriodCdOrTdInterest,
			'is_break_interest'=>$isBreakInterest
		]);
	}	
}
