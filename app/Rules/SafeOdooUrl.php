<?php

namespace App\Rules;

use App\Support\Odoo\OdooUrlGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** Validation rule for a company's Odoo address (see OdooUrlGuard). */
class SafeOdooUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $reason = OdooUrlGuard::violation(is_string($value) ? $value : null);

        if ($reason !== null) {
            $fail(__($reason));
        }
    }
}
