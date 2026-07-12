<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RenewContractRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }


    public function rules(): array
    {
        return [
            'contract_start_date' => 'required',
            'contract_end_date' => 'required|after:contract_start_date',
            'annually_increase_rate' => 'nullable|numeric|min:0|max:100',
            'collection_policy' => 'nullable|string',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'contract_start_date.required' => __('Contract start date is required'),
            'contract_end_date.required' => __('Contract end date is required'),
            'contract_end_date.after' => __('Contract end date must be after start date'),
        ];
    }
}
