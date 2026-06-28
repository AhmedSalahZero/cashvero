<?php

namespace App\Rules;

use App\Models\FinancialInstitutionAccount;
use Illuminate\Contracts\Validation\ImplicitRule;

class AtLeastOneMainFunctionalCurrencyExistAtAccountRule implements ImplicitRule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
	protected string $old_currency, $main_functional_currency ;
	protected int $financial_institution_id ;
	protected bool  $is_delete_mode = false ;
    public function __construct(string $oldCurrency,string $mainFunctionalCurrency,int $financialInstitutionId,bool $isDeleteMode = false)
    {
        $this->old_currency = $oldCurrency ;
		$this->main_functional_currency = $mainFunctionalCurrency;
		$this->financial_institution_id = $financialInstitutionId;
		$this->is_delete_mode = $isDeleteMode;
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
		
		$onlyOneAccountExistWithThisCurrency = FinancialInstitutionAccount::where('financial_institution_id',$this->financial_institution_id)->where('currency',$this->old_currency)->count() <= 1;
		
        if(!$this->is_delete_mode && $this->old_currency == $this->main_functional_currency && $this->old_currency != $value && $onlyOneAccountExistWithThisCurrency ){
			return false;
		}
		
		if($this->is_delete_mode && $this->old_currency == $this->main_functional_currency && $onlyOneAccountExistWithThisCurrency) // in delete mode
		{
			return false ; 
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
        return __('At Least There Must Be One Current Account in :currencyName',['currencyName'=>$this->main_functional_currency],app()->getLocale());
    }
	
}
