<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Facility Renewal — Phase 4 (ODA Against Assignment of Contract).
 * Mirrors RenewCleanOverdraftRequest — no tier array needed here,
 * unlike Commercial Paper, since lending rates are already locked
 * per-contract at assignment time.
 */
class RenewOverdraftAgainstAssignmentOfContractRequest extends FormRequest
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
            'max_lending_limit_per_contract' => ['nullable', 'numeric', 'gt:0'],
            'highest_debt_balance_rate' => ['nullable', 'numeric', 'gte:0'],
            'admin_fees_rate' => ['nullable', 'numeric', 'gte:0'],
            'to_be_setteled_max_within_days' => ['nullable', 'integer', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
