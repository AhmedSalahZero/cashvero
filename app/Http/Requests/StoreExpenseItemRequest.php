<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Rules\Odoo\ExpenseCategoryItemMustBeExistRule;
use App\Services\Api\OdooService;
use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseItemRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

   
    public function rules()
    {
		$rules = [];
		$company  = Company::find($this->company_id);
		if($company->hasOdooIntegrationCredentials()){
            $odooService = new OdooService($company);
			$rules['odoo_chart_of_account_number'] = [new ExpenseCategoryItemMustBeExistRule($odooService , $this->input('cashExpenseCategoryNames') , $company)];
		}
        return $rules;
    }
}
