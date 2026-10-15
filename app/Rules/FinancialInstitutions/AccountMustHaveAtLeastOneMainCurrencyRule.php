<?php

namespace App\Rules\FinancialInstitutions;

use App\Models\Company;
use Illuminate\Contracts\Validation\Rule;

class AccountMustHaveAtLeastOneMainCurrencyRule implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
	protected Company $company ; 
    public function __construct(Company $company)
    {
        $this->company= $company;
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
        foreach(Request('accounts',[]) as $index=>$accountArr){
			if($accountArr['currency'] == $this->company->getMainFunctionalCurrency()){
				return true ;
			}
		}
		return false ;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('You Must Enter At Least One Main Functional Currency');
    }
}
