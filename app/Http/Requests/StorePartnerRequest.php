<?php

namespace App\Http\Requests;

use App\Rules\UniqueToCompanyAndAdditionalColumnsRule;
use Illuminate\Foundation\Http\FormRequest;

class StorePartnerRequest extends FormRequest
{
    private const TYPE_FLAGS = [
        'is_customer',
        'is_supplier',
        'is_employee',
        'is_subsidiary_company',
        'is_other_partner',
        'is_shareholder',
    ];

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
            'name' => ['required', new UniqueToCompanyAndAdditionalColumnsRule('Partner', 'name', $this->id, [['is_customer', '=', 1]], __('This Customer Already Exist'))],
            'is_customer' => ['boolean'],
            'is_supplier' => ['boolean'],
            'is_employee' => ['boolean'],
            'is_subsidiary_company' => ['boolean'],
            'is_other_partner' => ['boolean'],
            'is_shareholder' => ['boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach (self::TYPE_FLAGS as $flag) {
                if ($this->boolean($flag)) {
                    return;
                }
            }

            $validator->errors()->add(
                'partner_type',
                __('Select at least one partner type.')
            );
        });
    }
}
