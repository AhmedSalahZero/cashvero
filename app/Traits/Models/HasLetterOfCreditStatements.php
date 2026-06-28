<?php
namespace App\Traits\Models;



trait HasLetterOfCreditStatements
{
    public function generateLetterOfCreditData(int $financialInstitutionId , string $source  , int $lcFacilityId,string $lcType,$companyId,string $date,$beginningBalance,$debit , $credit,string $currencyName , int $lcAdvancedPaymentId = 0 , int $cdOrTdId = 0 , $type =null , $commentEn = null , $commentAr = null):array
    {
        return [
			'type'=>$type , // beginning-balance for example
			'lc_facility_id'=>$lcFacilityId ,
			'cd_or_td_id'=>$cdOrTdId,
			'source'=>$source,
			'financial_institution_id'=>$financialInstitutionId,
			'lc_type'=>$lcType ,
			'currency'=>$currencyName ,
			'lc_advanced_payment_history_id'=>$lcAdvancedPaymentId,
			'company_id'=>$companyId ,
			'beginning_balance'=>$beginningBalance,
			'debit'=>$debit,
			'credit'=>$credit ,
			'date'=>$date,
			'comment_en'=>$commentEn,
			'comment_ar'=>$commentAr
		];
    }
		/**
	 * * هنا لو اليوزر ضاف فلوس في الحساب
	 * * بنحطها في الاستيت منت
	 * * سواء كانت كاش استيتمنت او بانك استيتمنت علي حسب نوع الحساب او الحركة يعني
	 */	
	public function handleLetterOfCreditStatement(int $financialInstitutionId , string $source  , int $lcFacilityId,string $lcType,$companyId,string $date,$beginningBalance,$debit , $credit,string $currencyName , $lcAdvancedPaymentId = 0 , $cdOrTdId = 0 , $type =null , $commentEn = null , $commentAr= null)
	{
		$cdOrTdId = is_null($cdOrTdId) ? 0 : $cdOrTdId;
		$data = $this->generateLetterOfCreditData($financialInstitutionId , $source  , $lcFacilityId, $lcType,$companyId,$date,$beginningBalance,$debit , $credit,$currencyName ,$lcAdvancedPaymentId, $cdOrTdId , $type,$commentEn,$commentAr) ;
		$this->letterOfCreditStatements()->create($data);

	}


}
