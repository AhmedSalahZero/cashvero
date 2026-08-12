<?php

namespace App\Http\Requests;

use App\Rules\UniqueToCompanyAndAdditionalColumnsRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePartnerRequest extends FormRequest
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
     * its own uniqueness check) was never actually set. Matters most
     * here since this covers Customers and Suppliers.
     */
    protected function prepareForValidation(): void
    {
        if ($this->route('partner')) {
            $this->merge(['id' => $this->route('partner')->id]);
        }
    }


    public function rules()
    {
        return [
            'name'=>['required',new UniqueToCompanyAndAdditionalColumnsRule('Partner','name',$this->id,[['is_customer','=',1]],__('This Customer Already Exist'))]
        ];
    }
}
