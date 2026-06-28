<?php
namespace App\Traits\Models;



trait HasCurrentAccountCreditBankStatement
{

	
	public function storeCurrentAccountCreditBankStatement(string $date , $credit , int $financialInstitutionAccountId , int $lcAdvancedPaymentHistoryId = 0 ,  $isActive = 1 , ?string $commentEn = null, ?string $commentAr = null , bool $isRenewalFees = false, bool $isCommissionFees = false , ?int $lcRenewalDateHistoryId = null,int $isIssuanceFees = 0)
	{
		return $this->currentAccountCreditBankStatement()->create([
			'financial_institution_account_id'=>$financialInstitutionAccountId,
			'company_id'=>$this->company_id ,
			'lc_advanced_payment_history_id'=>$lcAdvancedPaymentHistoryId,
			'is_active'=>$isActive , // is active خاصة بجزئيه ال commission فقط
			'credit'=>$credit,
			'debit'=>0,
			'date'=>$date,
			'comment_en'=>$commentEn,
			'comment_ar'=>$commentAr,
			'is_commission_fees'=>$isCommissionFees,
			'is_issuance_fees'=>$isIssuanceFees
		]);
	}
	

	
}
