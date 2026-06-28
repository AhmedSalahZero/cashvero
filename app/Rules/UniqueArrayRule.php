<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class UniqueArrayRule implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
	public array $items ;
	public string $failed_message ;
    public function __construct(array $items, string $failedMessage )
    {
        $this->items = $items ; 
        $this->failed_message = $failedMessage ; 
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
        return count($this->items) == count(array_unique($this->items));
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->failed_message ;
    }
}
