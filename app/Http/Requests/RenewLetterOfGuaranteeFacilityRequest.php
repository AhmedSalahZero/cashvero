<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Facility Renewal — Phase 5 (LG Facility). Requires the full 4-row
 * Term & Conditions matrix, same non-optional rule as Commercial
 * Paper's tiers — an empty matrix would leave every LG issued after
 * this renewal with no rate at all.
 */
class RenewLetterOfGuaranteeFacilityRequest extends FormRequest
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
            'notes' => ['nullable', 'string', 'max:1000'],
            'termAndConditions' => ['required', 'array', 'size:4'],
            'termAndConditions.*.lg_type' => ['required', 'string'],
            'termAndConditions.*.cash_cover_rate' => ['nullable', 'numeric', 'gte:0'],
            'termAndConditions.*.commission_rate' => ['nullable', 'numeric', 'gte:0'],
            'termAndConditions.*.commission_interval' => ['required', 'string'],
            'termAndConditions.*.min_commission_fees' => ['nullable', 'numeric', 'gte:0'],
            'termAndConditions.*.issuance_fees' => ['nullable', 'numeric', 'gte:0'],
        ];
    }
}
