<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLetterOfGuaranteeFacilityRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $letterOfGuaranteeFacility = $this->route('letterOfGuaranteeFacility');
        $financialInstitutionId = $this->route('financialInstitution')?->id;

        /**
         * Facility Renewal: once a renewal exists, Edit only ever
         * submits limit/contract_end_date — name/dates/currency and
         * the Term & Conditions matrix belong to whichever chapter is
         * current and can't be touched from this reduced form (same
         * pattern as every other facility type).
         */
        if ($letterOfGuaranteeFacility?->hasRenewals()) {
            return [
                'contract_end_date' => ['required', 'date'],
                'limit' => ['required', 'numeric', 'gt:0'],
            ];
        }

        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('letter_of_guarantee_facilities', 'name')
                    ->where('financial_institution_id', $financialInstitutionId)
                    ->ignore($letterOfGuaranteeFacility?->id),
            ],
            'contract_start_date' => ['required', 'date'],
            'contract_end_date' => ['required', 'date', 'after:contract_start_date'],
            'currency' => ['required', 'string'],
            'limit' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
