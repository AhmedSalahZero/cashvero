<?php

namespace App\Rules;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\ImplicitRule;

class DateMustBeLessThanOrEqualDate implements ImplicitRule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
	public $lessOrEqualDate , $date ,$failedMessage,$isMultiValueRule ; 
	
    public function __construct(?string $lessOrEqualDate , ?string $date,string $failedMessage, $isMultiValueRule = false)
    {
        $this->lessOrEqualDate = $lessOrEqualDate;
        $this->date = $date;
		$this->failedMessage = $failedMessage;
		$this->isMultiValueRule = $isMultiValueRule;
		
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
		if($this->isMultiValueRule || is_null($this->lessOrEqualDate)){
			$this->lessOrEqualDate = $value;
		}
		if(is_null($this->date)){
			return false;
		}
        return Carbon::make($this->lessOrEqualDate)->lessThanOrEqualTo(Carbon::make($this->date));
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
