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
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
		if($this->unapplied_amount <= 0){
			return true ;
		}
		
        return  $this->contract_id;
		
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
