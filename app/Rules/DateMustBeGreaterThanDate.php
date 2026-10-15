<?php

namespace App\Rules;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\ImplicitRule;

class DateMustBeGreaterThanDate implements ImplicitRule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
	public $largerDate , $date ,$failedMessage ; 
	
    public function __construct(?string $largerDate , string $date,string $failedMessage)
    {
        $this->largerDate = $largerDate;
        $this->date = $date;
		$this->failedMessage = $failedMessage;
		
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
		if(!$this->largerDate || !$this->date ){
			return false ;
		}
        return Carbon::make($this->largerDate)->greaterThan(Carbon::make($this->date));
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
