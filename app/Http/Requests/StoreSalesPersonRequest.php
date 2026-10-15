<?php

namespace App\Http\Requests;

use App\Rules\UniqueToCompanyAndAdditionalColumnsRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSalesPersonRequest extends FormRequest
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
     * ⚠️ REAL BUG FIXED HERE (client-confirmed, 2026-08-11): same class
     * of bug found and fixed on StoreLetterOfCreditFacilityRequest —
     * editing without changing the name failed with a false "already
     * exists" error, since $this->id (used to exclude this record from
     * its own uniqueness check) was never actually set.
     */
    protected function prepareForValidation(): void
    {
        if ($this->route('salesPerson')) {
            $this->merge(['id' => $this->route('salesPerson')->id]);
        }
    }

 
    public function rules()
    {
        return [
            'name'=>['required',new UniqueToCompanyAndAdditionalColumnsRule('CashVeroSalesPerson','name',$this->id,[],__('This Sales Person Already Exist'))]
        ];
    }
}
