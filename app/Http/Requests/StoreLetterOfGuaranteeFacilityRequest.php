<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * ⚠️ REAL GAP FIXED HERE (client-flagged, 2026-08-11): store() and
 * update() previously used a plain Illuminate\Http\Request — there was
 * NO server-side validation at all for this facility type (not even a
 * required-fields check, let alone the name-uniqueness the client
 * asked for). This is the first dedicated validation class LG Facility
 * has had.
 */
class StoreLetterOfGuaranteeFacilityRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $financialInstitutionId = $this->route('financialInstitution')?->id;

        return [
            'name' => [
                'required', 'string', 'max:255',
                // Client-confirmed (2026-08-11): unique per bank only —
                // two different banks can each have their own
                // "LG Facility A".
                Rule::unique('letter_of_guarantee_facilities', 'name')
                    ->where('financial_institution_id', $financialInstitutionId),
            ],
            'contract_start_date' => ['required', 'date'],
            'contract_end_date' => ['required', 'date', 'after:contract_start_date'],
            'currency' => ['required', 'string'],
            'limit' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
