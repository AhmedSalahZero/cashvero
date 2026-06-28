<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ImplicitRule;

class SettlementPlusWithoutCanNotBeGreaterNetBalance implements ImplicitRule
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

        foreach($this->settlements as $invoiceNumber => $invoiceArr){
			
			$currentNetBalance = isset($invoiceArr['net_balance'])  ? unformat_number($invoiceArr['net_balance']) : 0 ;
			$settlementAmount = isset($invoiceArr['settlement_amount']) ? unformat_number($invoiceArr['settlement_amount']) : 0 ;
			$withoutAmount =  isset($invoiceArr['withhold_amount']) ? unformat_number($invoiceArr['withhold_amount']) : 0 ;
			if($currentNetBalance < $settlementAmount+ $withoutAmount){
				$this->errorMessage = __('Settlement Amount Must Be Equal Or Less Than Net Balance For Invoice '  . $invoiceNumber);
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
        return $this->errorMessage;
    }
}
