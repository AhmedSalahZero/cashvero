<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ImplicitRule;

class AtLeaseOneSettlementMustBeExist implements ImplicitRule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
	protected array $settlements ; 
	protected string $errorMessage ; 
    public function __construct(array $settlements)
    {
        $this->settlements = $settlements ;
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
		if(Request()->get('is_down_payment')){
			return true ;
		}
		return array_sum(array_column($this->settlements,'settlement_amount')) > 0 ;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('At Lease One Settlement Is Required');
    }
}
