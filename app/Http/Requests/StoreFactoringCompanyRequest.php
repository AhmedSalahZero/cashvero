<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFactoringCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $companyId = $this->route('company')?->id;
        $factoringCompanyId = $this->route('factoringCompany')?->id;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('factoring_companies', 'name')
                    ->where('company_id', $companyId)
                    ->ignore($factoringCompanyId),
            ],
        ];
    }
}
