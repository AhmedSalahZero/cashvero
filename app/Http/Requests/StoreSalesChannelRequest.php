<?php

namespace App\Http\Requests;

use App\Rules\UniqueToCompanyAndAdditionalColumnsRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSalesChannelRequest extends FormRequest
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
            'name'=>['required',new UniqueToCompanyAndAdditionalColumnsRule('CashVeroSalesChannel','name',$this->id,[],__('This Sales Channel Already Exist'))]
        ];
    }
}
