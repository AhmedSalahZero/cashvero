<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ValidAmountRule implements Rule
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
     * Accepts plain numbers (e.g. "1234.56") and comma-formatted numbers (e.g. "1,000,000" —
     * the thousands-separator style customers are used to typing, matching the original app).
     * Strips the comma(s) first, then requires what's left to be a genuine number — rather than
     * the previous check, which accepted ANY string containing a comma or period anywhere in it
     * (e.g. "1.2.3" or "not,a,number" would have passed).
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
		$withoutThousandsSeparators = str_replace(',', '', (string) $value);
		return is_numeric($withoutThousandsSeparators);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return __('Invalid Amount');
    }
}
