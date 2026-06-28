<?php

namespace App\Http\Requests;

use App\Rules\UniqueAccountNumberRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCurrentAccountRequest extends FormRequest
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
	
        return [
            'accounts.*.account_number'=>new UniqueAccountNumberRule($excludeAccountNumbers)
        ];
    }
}
