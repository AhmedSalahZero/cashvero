<?php

namespace App\Http\Requests;

use App\Rules\OutstandingBreakdownRule;
use App\Rules\UniqueAccountNumberRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFullySecuredOverdraftRequest extends FormRequest
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
			'contract_start_date'=>'required|date',
			'contract_end_date'=>'required|date|after:contract_start_date',
            'account_number'=>['required',new UniqueAccountNumberRule($excludeAccountNumbers)],
			'cd_or_td_lending_percentage'=>'required|gt:0',
			'limit'=>['required','gt:0'],
			'interest_rate'=>['sometimes','required','gt:0'],
			'outstanding_breakdowns'=>[new OutstandingBreakdownRule($this->outstanding_balance?:0,$this->contract_start_date)]
        ];
    }
}
