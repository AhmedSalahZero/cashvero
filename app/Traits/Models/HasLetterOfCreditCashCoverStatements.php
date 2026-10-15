<?php
namespace App\Traits\Models;



trait HasLetterOfCreditCashCoverStatements
{

		/**
	 * * هنا لو اليوزر ضاف فلوس في الحساب
	 * * بنحطها في الاستيت منت
	 * * سواء كانت كاش استيتمنت او بانك استيتمنت علي حسب نوع الحساب او الحركة يعني
	 */

	public function handleLetterOfCreditCashCoverStatement(int $financialInstitutionId , string $source  , int $lcFacilityId,string $lcType,$companyId,string $date,$beginningBalance,$debit , $credit,string $currencyName ,int $lcAdvancedPaymentHistoryId = 0, $type =null)
	{
		$data =   [
			'type'=>$type , // beginning-balance for example
			'lc_facility_id'=>$lcFacilityId ,
			'source'=>$source,
			'financial_institution_id'=>$financialInstitutionId,
			'lc_type'=>$lcType ,
			'lc_advanced_payment_history_id'=>$lcAdvancedPaymentHistoryId,
			'currency'=>$currencyName ,
			'company_id'=>$companyId ,
			'beginning_balance'=>$beginningBalance,
			'debit'=>$debit,
			'credit'=>$credit ,
			'date'=>$date,
		] ;
		$this->letterOfCreditCashCoverStatements()->create($data);

	}
	
	

	
}
