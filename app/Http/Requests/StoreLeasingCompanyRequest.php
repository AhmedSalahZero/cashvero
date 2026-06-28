<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeasingCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->route('company')?->id;
        $leasingCompanyId = $this->route('leasingCompany')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('leasing_companies', 'name')
                    ->where('company_id', $companyId)
                    ->ignore($leasingCompanyId),
            ],
        ];
    }
}
