<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ImplicitRule;
use Illuminate\Contracts\Validation\Rule;

class TwoNumericsAreEqual implements ImplicitRule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
	public float $firstNo,$secondNo;
	public string $failedMessage;
	
    public function __construct(float $firstNo,float $secondNo,string $failedMessage)
    {
        $this->firstNo = $firstNo;
		$this->secondNo = $secondNo ;
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
	
        return $this->firstNo == $this->secondNo;
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
