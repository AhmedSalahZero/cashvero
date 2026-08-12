<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Facility Renewal — Phase 2 (Fully Secured Overdraft). Mirrors
 * RenewCleanOverdraftRequest exactly — same rules, same reasoning.
 */
class RenewFullySecuredOverdraftRequest extends FormRequest
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
            /**
             * Client-flagged (2026-08-11): the limit isn't directly
             * typed for this facility type — it's calculated from the
             * linked CD/TD account's amount × this percentage, same as
             * the original facility form. See
             * FullySecuredOverdraft::renew() for the authoritative
             * server-side recalculation.
             */
            'cd_or_td_lending_percentage' => ['nullable', 'numeric', 'gt:0'],
            'highest_debt_balance_rate' => ['nullable', 'numeric', 'gte:0'],
            'admin_fees_rate' => ['nullable', 'numeric', 'gte:0'],
            'to_be_setteled_max_within_days' => ['nullable', 'integer', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
