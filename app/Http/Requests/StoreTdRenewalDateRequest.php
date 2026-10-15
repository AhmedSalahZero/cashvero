<?php

namespace App\Http\Requests;

use App\Rules\DateMustBeGreaterThanDate;
use Illuminate\Foundation\Http\FormRequest;

class StoreTdRenewalDateRequest extends FormRequest
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
            'renewal_date'=>['required',new DateMustBeGreaterThanDate($this->get('renewal_date'),$this->get('expiry_date'),__('Renewal Date Must Be Greater Than Expiry Date'))]
        ];
    }
}
