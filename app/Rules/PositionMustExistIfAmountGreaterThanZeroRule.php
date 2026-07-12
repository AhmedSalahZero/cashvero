<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ImplicitRule;

class PositionMustExistIfAmountGreaterThanZeroRule implements ImplicitRule
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
        //
		foreach($value as $fixedAsset){
			$amount = $fixedAsset['ffe_item_cost']??0;
			if($amount == 0){
				continue;
			}
			if(!count($fixedAsset['position_ids']??[])){
				return false ;
			}
		}
		return true ;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('Please Select At Least One Position');
    }
}
