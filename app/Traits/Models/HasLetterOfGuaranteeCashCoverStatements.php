<?php
namespace App\Traits\Models;



trait HasLetterOfGuaranteeCashCoverStatements
{
   
		/**
	 * * هنا لو اليوزر ضاف فلوس في الحساب
	 * * بنحطها في الاستيت منت
	 * * سواء كانت كاش استيتمنت او بانك استيتمنت علي حسب نوع الحساب او الحركة يعني
	 */

	public function handleLetterOfGuaranteeCashCoverStatement(int $financialInstitutionId , string $source  , ?int $lgFacilityId,string $lgType,$companyId,string $date,$beginningBalance,$debit , $credit,string $currencyName ,int $lgAdvancedPaymentHistoryId = 0, $type =null)
	{
		$data = [
			'type'=>$type , // beginning-balance for example
			'lg_facility_id'=>$lgFacilityId ,
			'source'=>$source,
			'financial_institution_id'=>$financialInstitutionId,
			'lg_type'=>$lgType ,
			'lg_advanced_payment_history_id'=>$lgAdvancedPaymentHistoryId,
			'currency'=>$currencyName ,
			'company_id'=>$companyId ,
			'beginning_balance'=>$beginningBalance,
			'debit'=>$debit,
			'credit'=>$credit ,
			'date'=>$date,
		] ;
		$this->letterOfGuaranteeCashCoverStatements()->create($data);

	}

	
	public function storeCurrentAccountDebitBankStatement(string $date , $debit , int $financialInstitutionAccountId , int $lgAdvancedPaymentHistoryId = 0 , int $letterOfGuaranteeIssuanceId = 0 , $commentEn = null , $commentAr= null)
	{
		return $this->currentAccountDebitBankStatement()->create([
			'financial_institution_account_id'=>$financialInstitutionAccountId,
			'company_id'=>$this->company_id ,
			'credit'=>0,
			'debit'=>$debit,
			'lg_advanced_payment_history_id'=>$lgAdvancedPaymentHistoryId,
			'letter_of_guarantee_issuance_id'=>$letterOfGuaranteeIssuanceId,
			'date'=>$date,
			'comment_en'=>$commentEn,
			'comment_ar'=>$commentAr
		]);
	}
	
	
	
	
}
