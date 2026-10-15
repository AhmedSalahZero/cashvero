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

    /**
     * ⚠️ REAL BUG FIXED HERE (found and confirmed with the project
     * owner — creating a new Fully Secured Overdraft with an empty
     * Balance Date crashed with a database error instead of a clean
     * validation message).
     *
     * 'balance_date' was never actually validated here — the original
     * Blade form only marked it required in the browser (HTML5
     * `required`), which is easy to bypass and provides no real
     * server-side guarantee. That mattered because
     * FullySecuredOverdraft::boot()'s created() hook uses this exact
     * field's raw value as the `date` for the very first rate-history
     * row it creates — and that column is NOT NULL in the database.
     * An empty Balance Date therefore reached a hard SQL error instead
     * of a friendly "this field is required" message. Added here so
     * it's now enforced the same way for both store() and update()
     * (UpdateFullySecuredOverdraftRequest inherits these rules).
     */
    public function rules(array $excludeAccountNumbers = [])
    {

	
        return [
			'contract_start_date'=>'required|date',
			'contract_end_date'=>'required|date|after:contract_start_date',
            'account_number'=>['required',new UniqueAccountNumberRule($excludeAccountNumbers)],
			'balance_date'=>'required|date',
			'cd_or_td_lending_percentage'=>'required|gt:0',
			'limit'=>['required','gt:0'],
			'interest_rate'=>['sometimes','required','gt:0'],
			// ⚠️ REAL BUG FIXED HERE (2026-07-26 audit Stage 4 §3.1):
			// negative outstanding_balance previously wiped breakdowns silently.
			'outstanding_balance'=>['nullable','numeric','gte:0'],
			'outstanding_breakdowns'=>[new OutstandingBreakdownRule($this->outstanding_balance?:0,$this->contract_start_date)]
        ];
    }
}
