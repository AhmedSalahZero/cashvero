<?php

namespace App\Http\Requests;

use App\Rules\UniqueToCompanyAndAdditionalColumnsRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLetterOfCreditFacilityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * ⚠️ REAL BUG FIXED HERE (client-flagged, 2026-08-11): editing an
     * existing facility without changing its name failed with "This
     * Letter Of Credit Facility Already Exist" — because the
     * uniqueness rule below reads $this->id to know which record to
     * exclude from its own check, but nothing ever set it. The form
     * only submits the facility's fields, not its own id (that comes
     * from the URL) — so on every edit, $this->id was null, the rule's
     * "exclude this record" filter never actually excluded anything,
     * and the facility's own name matched itself as a "duplicate".
     * This merges the route-bound facility's real id in before
     * validation runs, only when actually editing (a create request
     * has no bound model, so $this->id correctly stays null there).
     */
    protected function prepareForValidation(): void
    {
        if ($this->route('letterOfCreditFacility')) {
            $this->merge(['id' => $this->route('letterOfCreditFacility')->id]);
        }
    }

  
    public function rules()
    {
        return [
			'name'=>['required',new UniqueToCompanyAndAdditionalColumnsRule('LetterOfCreditFacility','name',$this->id,[['financial_institution_id','=',$this->financial_institution_id]],__('This Letter OF Credit Facility Already Exist'))]
        ];
    }
}
