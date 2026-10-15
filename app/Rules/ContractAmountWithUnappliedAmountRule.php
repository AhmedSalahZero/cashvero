<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ImplicitRule;

class ContractAmountWithUnappliedAmountRule implements ImplicitRule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
	protected $unapplied_amount , $contract_id ; 
    public function __construct($unappliedAmount , $contractId)
    {
        $this->unapplied_amount = $unappliedAmount ; 
		$this->contract_id = $contractId ;
    }

    /**
     * Determine if the validation rule passes.
     *
     * Note: previously returned `$this->contract_id` directly (a raw int) rather than a real
     * boolean. Laravel's validator treats that loosely-truthy value as pass/fail, and it worked
     * in practice only because real contract IDs are always positive integers. Made explicit
     * here since a method contracted to return a boolean should actually return one.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
		if($this->unapplied_amount <= 0){
			return true ;
		}
		
        return (bool) $this->contract_id;
		
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('Please Select Contract Id');
    }
}
