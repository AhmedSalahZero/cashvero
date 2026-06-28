<?php

namespace App\Rules;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\ImplicitRule;
use Illuminate\Support\Facades\DB;

class DateCanNotBeAfterAnyStatementRule implements ImplicitRule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
	protected int $financial_institution_account_id;
	protected string $new_balance_date;
    public function __construct(int $financial_institution_account_id , string $new_balance_date)
    {
        $this->financial_institution_account_id = $financial_institution_account_id;
        $this->new_balance_date = Carbon::make($new_balance_date)->format('Y-m-d');
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
		$row = DB::table('current_account_bank_statements')->where('is_beginning_balance',0)->where('financial_institution_account_id',$this->financial_institution_account_id)->where('date','<',$this->new_balance_date)->first();
	
		return ! $row ; 
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('There Are Transactions Before This Date .. You Have To Delete Them First');
    }
	
}
