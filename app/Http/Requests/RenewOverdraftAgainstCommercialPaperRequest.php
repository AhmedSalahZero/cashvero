<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Facility Renewal — Phase 3 (ODA Against Commercial Paper).
 *
 * Unlike the other three facility types, this expects a whole new
 * 'tiers' array (the renewal's own complete lending-rate schedule) —
 * see OverdraftAgainstCommercialPaper::renew() for why it can't be
 * optional (an empty schedule would mean every cheque deposited after
 * this renewal gets zero contribution, silently).
 */
class RenewOverdraftAgainstCommercialPaperRequest extends FormRequest
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
            'max_lending_limit_per_customer' => ['nullable', 'numeric', 'gt:0'],
            'highest_debt_balance_rate' => ['nullable', 'numeric', 'gte:0'],
            'admin_fees_rate' => ['nullable', 'numeric', 'gte:0'],
            'to_be_setteled_max_within_days' => ['nullable', 'integer', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'tiers' => ['required', 'array', 'min:1'],
            'tiers.*.for_commercial_papers_due_within_days' => ['required', 'integer', 'gt:0'],
            'tiers.*.lending_rate' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
