<?php

namespace App\Http\Requests;

use App\Models\CertificatesOfDeposit;


class UpdateCertificateOfDepositRequest extends StoreCertificateOfDepositRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true ;
    }


    public function rules(array $excludeAccountNumbers = [])
    {
		$certificatesOfDeposit = Request()->route('certificatesOfDeposit') ;
		/**
		 * @var CertificatesOfDeposit $certificatesOfDeposit 
		 */
		$excludeAccountNumbers = (array)$certificatesOfDeposit->getAccountNumber();
        return array_merge(
			parent::rules($excludeAccountNumbers),
			[]
		);
    }
}
