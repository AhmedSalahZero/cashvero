<?php
namespace App\Traits\Models;



trait HasLetterOfGuaranteeStatements
{
    public function generateLetterOfGuaranteeData(int $financialInstitutionId , string $source  , ?int $lgFacilityId,string $lgType,$companyId,string $date,$beginningBalance,$debit , $credit,string $currencyName , int $lgAdvancedPaymentId = 0 , int $cdOrTdId = 0 , $type =null,$commentEn=null,$commentAr=null):array
    {
        return [
			'type'=>$type , // beginning-balance for example
			'lg_facility_id'=>$lgFacilityId ,
			'cd_or_td_id'=>$cdOrTdId,
			'source'=>$source,
			'financial_institution_id'=>$financialInstitutionId,
			'lg_type'=>$lgType ,
			'currency'=>$currencyName ,
			'lg_advanced_payment_history_id'=>$lgAdvancedPaymentId,
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
	public function handleLetterOfGuaranteeStatement(int $financialInstitutionId , string $source  , ?int $lgFacilityId,string $lgType,$companyId,string $date,$beginningBalance,$debit , $credit,string $currencyName , $lgAdvancedPaymentId = 0 , $cdOrTdId = 0 , $type =null,$commentEn = null, $commentAr=null)
	{
		$cdOrTdId = is_null($cdOrTdId) ? 0 : $cdOrTdId;
		$data = $this->generateLetterOfGuaranteeData($financialInstitutionId , $source  , $lgFacilityId, $lgType,$companyId,$date,$beginningBalance,$debit , $credit,$currencyName ,$lgAdvancedPaymentId, $cdOrTdId , $type,$commentEn,$commentAr) ;
		$this->letterOfGuaranteeStatements()->create($data);

	}

}
