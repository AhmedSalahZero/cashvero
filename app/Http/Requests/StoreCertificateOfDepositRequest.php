<?php

namespace App\Http\Requests;

use App\Models\FinancialInstitutionAccount;
use App\Rules\DateMustBeGreaterThanOrEqualDate;
use App\Rules\Odoo\ChartOfAccountMustBeExistRule;
use App\Rules\UniqueAccountNumberRule;
use App\Services\Api\OdooService;
use Illuminate\Foundation\Http\FormRequest;

class StoreCertificateOfDepositRequest extends FormRequest
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
		$company  = $this->route('company');
		$financialInstitutionAccount = FinancialInstitutionAccount::find(Request()->get('maturity_amount_added_to_account_id'));
		$financialInstitutionBalanceDate = $financialInstitutionAccount->getOpeningBalanceDate() ;
		$rules = [
            'account_number'=>new UniqueAccountNumberRule($excludeAccountNumbers),
			'end_date'=>['required',new DateMustBeGreaterThanOrEqualDate(null,$financialInstitutionBalanceDate,__('End Date Must Be Greater Than Or Equal Account Opening Balance Date'))]
			
        ];
		
		if($company->hasOdooIntegrationCredentials()){
            $odooService = new OdooService($company);
			$rules['odoo_code'] = [new ChartOfAccountMustBeExistRule($odooService  , $company)];
		}
		
        return $rules;
    }
}
