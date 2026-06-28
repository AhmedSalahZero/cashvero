<?php

namespace App\Http\Requests;

use App\Rules\UniqueToCompanyAndAdditionalColumnsRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
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
            'name'=>['required',new UniqueToCompanyAndAdditionalColumnsRule('Partner','name',$this->id,[['is_supplier','=',1]],__('This Supplier Already Exist'))]
        ];
    }
}
