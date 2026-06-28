<?php

namespace App\Http\Requests;

use App\Rules\UniqueToCompanyAndAdditionalColumnsRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreBusinessUnitRequest extends FormRequest
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
            'name'=>['required',new UniqueToCompanyAndAdditionalColumnsRule('CashVeroBusinessUnit','name',$this->id,[],__('This Business Unit Already Exist'))]
        ];
    }
}
