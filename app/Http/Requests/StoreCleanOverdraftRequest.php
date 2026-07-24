<?php

namespace App\Http\Requests;

use App\Rules\OutstandingBreakdownRule;
use App\Rules\UniqueAccountNumberRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCleanOverdraftRequest extends FormRequest
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

    /**
     * ⚠️ REAL BUG FIXED HERE (same class of bug already found and
     * fixed on StoreFullySecuredOverdraftRequest): 'balance_date' was
     * never actually validated here — only a browser-only `required`
     * hint on the old Blade form. CleanOverdraft::boot()'s created()
     * hook uses this exact field's raw value as the `date` for the
     * very first rate-history row it creates, and that column is NOT
     * NULL in the database — so an empty Balance Date would reach a
     * hard SQL error instead of a clean validation message. Added
     * here before it could cause the same crash it did on Fully
     * Secured Overdraft.
     */
    public function rules(array $excludeAccountNumbers = [])
    {

	
        return [
			'contract_start_date'=>'required|date',
			'contract_end_date'=>'required|date|after:contract_start_date',
            'account_number'=>['required',new UniqueAccountNumberRule($excludeAccountNumbers)],
			'balance_date'=>'required|date',
			'limit'=>['required','gt:0'],
			'interest_rate'=>['sometimes','required','gt:0'],
			'outstanding_breakdowns'=>[new OutstandingBreakdownRule($this->outstanding_balance?:0,$this->contract_start_date)]
        ];
    }
}
