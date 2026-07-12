<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\ImplicitRule;

class NumberMustBeGreaterThanOrEqualRule implements ImplicitRule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
	protected $greaterNumber ;
	protected $lessThanOrEqualNumber ;
	protected $errorMessage ;
	
    public function __construct(float $greaterNumber , float $lessThanOrEqualNumber ,string $errorMessage)
    {
		$this->greaterNumber = $greaterNumber;
		$this->lessThanOrEqualNumber = $lessThanOrEqualNumber;
		$this->errorMessage = $errorMessage;
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
        return $this->greaterNumber >= $this->lessThanOrEqualNumber;
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
