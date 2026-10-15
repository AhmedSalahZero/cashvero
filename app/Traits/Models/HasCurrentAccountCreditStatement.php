<?php
namespace App\Traits\Models;



trait HasCurrentAccountCreditStatement
{
	public function storeCurrentAccountCreditBankStatement(string $date , $credit , int $financialInstitutionAccountId , int $lgAdvancedPaymentHistoryId = 0 ,  $isActive = 1 , ? string $commentEn = null , ? string $commentAr = null , bool $isRenewalFees = false, bool $isCommissionFees = false , ?int $lgRenewalDateHistoryId = null, int $isIssuanceFees = 0 )
	{
		return $this->currentAccountCreditBankStatement()->create([
			'financial_institution_account_id'=>$financialInstitutionAccountId,
			'company_id'=>$this->company_id ,
			'lg_advanced_payment_history_id'=>$lgAdvancedPaymentHistoryId,
			'is_active'=>$isActive , // is active خاصة بجزئيه ال commission فقط
			'credit'=>$credit,
			'debit'=>0,
			'date'=>$date,
			'comment_en'=>$commentEn ,
			'comment_ar'=>$commentAr,
			'is_renewal_fees'=>$isRenewalFees,
			'is_commission_fees'=>$isCommissionFees,
			'lg_renewal_date_history_id'=>$lgRenewalDateHistoryId,
			'is_issuance_fees'=>$isIssuanceFees
		]);
	}
	
	
	
	
}
