<?php

namespace App\Rules;

use App\Helpers\HArr;
use App\Helpers\HDate;
use Illuminate\Contracts\Validation\ImplicitRule;

class OutstandingBreakdownRule implements ImplicitRule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
	protected $totalOutstandingBalance ; 
	protected $contractStartDate ; 
	protected $failedMessage ; 
    public function __construct($totalOutstandingBalance = 0 , $contractStartDate = null )
    {
        $this->totalOutstandingBalance = $totalOutstandingBalance;
        $this->contractStartDate =$contractStartDate;
		$this->failedMessage = __('Repeater Outstanding Balance Must Be Equal To Total Outstanding Balance') ;
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
	
        if($this->totalOutstandingBalance == 0){
			return true;
		}
		$repeaterSettlementDate = array_column($value,'settlement_date');
		$allDatesGreaterThanOrEqual = HDate::allDatesGreaterThanOrEqual($repeaterSettlementDate,$this->contractStartDate);
		if(!$allDatesGreaterThanOrEqual){
			$this->failedMessage = __('Settlement Dates Must Be Greater Than Or Equal Contract Start Date'); 
			return false ;
		}
		$amounts = HArr::unformatValues(array_column($value,'amount')) ;
		return array_sum($amounts) == $this->totalOutstandingBalance;
		
		
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->failedMessage;
    }
}
