<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ImplicitRule;

class DeductionAmountRule implements ImplicitRule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
	protected float $net_balance ; 
    public function __construct($netBalance)
    {
		$this->net_balance = $netBalance;
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
		$amounts = array_sum(array_column(Request()->get('deductions'),'amount'));
        return $this->net_balance  >= $amounts ; 
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('Deduction Amount Must Be Less Than Or Equal Net Balance');
    }
}
