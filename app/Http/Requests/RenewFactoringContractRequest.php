<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Facility Renewal — Phase 7 (Factoring Contract, final facility
 * type). Mirrors RenewCleanOverdraftRequest exactly — same rules,
 * same reasoning, just with Margin Rate in place of a single
 * combined rate field.
 */
class RenewFactoringContractRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'effective_date' => ['required', 'date'],
            'contract_end_date' => ['required', 'date', 'after:effective_date'],
            'limit' => ['nullable', 'numeric', 'gt:0'],
            'borrowing_rate' => ['nullable', 'numeric', 'gte:0'],
            'margin_rate' => ['nullable', 'numeric', 'gte:0'],
            'min_interest_rate' => ['nullable', 'numeric', 'gte:0'],
            'highest_debt_balance_rate' => ['nullable', 'numeric', 'gte:0'],
            'admin_fees_rate' => ['nullable', 'numeric', 'gte:0'],
            'to_be_setteled_max_within_days' => ['nullable', 'integer', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
