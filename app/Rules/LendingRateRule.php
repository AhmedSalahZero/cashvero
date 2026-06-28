<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ImplicitRule;

class LendingRateRule implements ImplicitRule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
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
        $lendingRates = array_column($value,'lending_rate');
		return array_sum($lendingRates) <= 100 ;
		
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('Lending Rate Must Be 100%');
    }
}
