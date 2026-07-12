<?php

namespace App\Http\Requests;

use App\Models\FactoringContract;
use App\Rules\OutstandingBreakdownRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFactoringContractRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contract_start_date' => 'required|date',
            'contract_end_date' => 'required|date|after:contract_start_date',
            'recourse_type' => ['required', Rule::in(array_keys(FactoringContract::recourseTypes()))],
            'currency' => 'required|string',
            'limit' => ['required', 'gt:0'],
            'outstanding_balance' => 'required|numeric|gte:0',
            'balance_date' => 'required|date',
            'interest_rate' => ['sometimes', 'required', 'gt:0'],
            'outstanding_breakdowns' => [new OutstandingBreakdownRule($this->outstanding_balance ?: 0, $this->contract_start_date)],
        ];
    }
}
