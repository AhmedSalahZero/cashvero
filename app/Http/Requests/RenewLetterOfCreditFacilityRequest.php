<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Facility Renewal — Phase 6 (LC Facility). A hybrid of
 * RenewFullySecuredOverdraftRequest (the flat Financing Terms &
 * Conditions fields, including the CD/TD lending percentage note —
 * see LetterOfCreditFacility::renew() for the authoritative
 * server-side limit recalculation when Fully Secured) and
 * RenewLetterOfGuaranteeFacilityRequest (the required, non-optional
 * per-type rate matrix — here 3 rows, one per LC type, instead of
 * LG's 4).
 */
class RenewLetterOfCreditFacilityRequest extends FormRequest
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
            'cd_or_td_lending_percentage' => ['nullable', 'numeric', 'gt:0'],
            'borrowing_rate' => ['nullable', 'numeric', 'gte:0'],
            'bank_margin_rate' => ['nullable', 'numeric', 'gte:0'],
            'min_interest_rate' => ['nullable', 'numeric', 'gte:0'],
            'highest_debt_balance_rate' => ['nullable', 'numeric', 'gte:0'],
            'admin_fees_rate' => ['nullable', 'numeric', 'gte:0'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'termAndConditions' => ['required', 'array', 'size:3'],
            'termAndConditions.*.lc_type' => ['required', 'string'],
            'termAndConditions.*.cash_cover_rate' => ['nullable', 'numeric', 'gte:0'],
            'termAndConditions.*.commission_rate' => ['nullable', 'numeric', 'gte:0'],
            'termAndConditions.*.min_commission_fees' => ['nullable', 'numeric', 'gte:0'],
            'termAndConditions.*.issuance_fees' => ['nullable', 'numeric', 'gte:0'],
        ];
    }
}
