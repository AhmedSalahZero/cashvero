<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Facility Renewal — Phase 1 (Clean Overdraft).
 *
 * Deliberately does NOT include account_number, currency, or
 * contract_start_date — a renewal never changes the facility's identity,
 * only what terms apply from the effective date forward.
 */
class RenewCleanOverdraftRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'effective_date' => ['required', 'date'],
            // All optional EXCEPT contract_end_date: a blank field means
            // "unchanged from the previous chapter's terms" (see
            // CleanOverdraft::renew()) — but the end date can't carry
            // forward unchanged, since the effective date is now
            // required to be after the CURRENT end date, so a renewal
            // must always state its own new end date.
            'contract_end_date' => ['required', 'date', 'after:effective_date'],
            'limit' => ['nullable', 'numeric', 'gt:0'],
            'highest_debt_balance_rate' => ['nullable', 'numeric', 'gte:0'],
            'admin_fees_rate' => ['nullable', 'numeric', 'gte:0'],
            'to_be_setteled_max_within_days' => ['nullable', 'integer', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
