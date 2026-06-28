<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Rules\FinancialInstitutions\AccountMustHaveAtLeastOneMainCurrencyRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFinancialInstitutionRequest extends FormRequest
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
		
        return [
            'accounts'=>['sometimes','required',new AccountMustHaveAtLeastOneMainCurrencyRule(currentCompany())]
        ];
    }
}
