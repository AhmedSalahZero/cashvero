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
     * Uses a ±1 tolerance band rather than exact equality, matching the pattern already proven
     * elsewhere in this codebase (see UnappliedAmountForContractAsDownPaymentRule's non-down-
     * payment branch). Summed financial amounts can differ from an exact float comparison by a
     * tiny rounding amount even when they are correct in real-world terms; a strict `==` on
     * floats can reject a genuinely correct submission for that reason alone.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
		$diff = $this->firstNo - $this->secondNo;
        return $diff >= -1 && $diff <= 1;
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
