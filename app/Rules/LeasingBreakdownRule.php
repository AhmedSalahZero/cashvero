<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ImplicitRule;

class LeasingBreakdownRule implements ImplicitRule
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
		foreach(request()->get('leasingRevenueStreamBreakdown',[]) as $breakdownArr){
			$currentCategory = $breakdownArr['category_id']??null;
			if(is_null($currentCategory)){
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
        return __('Please Choose Leasing Category');
    }
}
