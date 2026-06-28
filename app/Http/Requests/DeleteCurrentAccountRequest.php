<?php

namespace App\Http\Requests;

use App\Models\FinancialInstitutionAccount;
use App\Rules\AtLeastOneMainFunctionalCurrencyExistAtAccountRule;


class DeleteCurrentAccountRequest extends StoreCurrentAccountRequest
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
		$company = Request()->route('company') ;
		$financialInstitutionAccount = $this->route('financialInstitutionAccount');
		/**
		 * @var FinancialInstitutionAccount $financialInstitutionAccount 
		 */
		$currency = $financialInstitutionAccount->getCurrency();
		$mainFunctionalCurrency = $company->getMainFunctionalCurrency();
		$financialInstitutionId = $financialInstitutionAccount->getFinancialInstitutionId();
        return [
			'currency'=>[new AtLeastOneMainFunctionalCurrencyExistAtAccountRule($currency,$mainFunctionalCurrency,$financialInstitutionId,true)]
		];
    }
}
