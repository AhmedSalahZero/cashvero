<?php

namespace App\Http\Requests;

use App\Rules\LendingRateRule;
use App\Rules\OutstandingBreakdownRule;
use App\Rules\UniqueAccountNumberRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOverdraftAgainstCommercialPaperRequest extends FormRequest
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
     * fixed on Fully Secured Overdraft and Clean Overdraft):
     * 'balance_date' was never actually validated here — only a
     * browser-only `required` hint on the old Blade form.
     * OverdraftAgainstCommercialPaper::boot()'s created() hook uses
     * this exact field's raw value as the `date` for the very first
     * rate-history row it creates, and that column is NOT NULL in the
     * database — so an empty Balance Date would reach a hard SQL
     * error instead of a clean validation message. Fixed here before
     * it could cause the same crash.
     */
    public function rules(array $excludeAccountNumbers = [])
    {

	
        return [
			'contract_start_date'=>'required|date',
			'contract_end_date'=>'required|date|after:contract_start_date',
            'account_number'=>['required',new UniqueAccountNumberRule($excludeAccountNumbers)],
			'currency'=>'required',
			'balance_date'=>'required|date',
			'limit'=>['required','gt:0'],
			'interest_rate'=>['sometimes','required','gt:0'],
	//		'infos'=>[new LendingRateRule()],
			'max_lending_limit_per_customer'=>'required|gt:0',
			// ⚠️ REAL BUG FIXED HERE (2026-07-26 audit Stage 4 §3.1):
			// negative outstanding_balance previously wiped breakdowns silently.
			'outstanding_balance'=>['nullable','numeric','gte:0'],
			'outstanding_breakdowns'=>[new OutstandingBreakdownRule($this->outstanding_balance?:0,$this->contract_start_date)],
        ];
    }
	public function messages()
	{
		return [
			'max_lending_limit_per_customer.required'=>__('Please Max Set Lending Limit Per Customer Or Write Down The Contract Limit'),
			'max_lending_limit_per_customer.gt'=>__('Please Max Set Lending Limit Per Customer Or Write Down The Contract Limit'),
			
		];
	}
}
