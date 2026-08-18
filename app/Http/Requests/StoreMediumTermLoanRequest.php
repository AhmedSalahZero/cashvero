<?php

namespace App\Http\Requests;

use App\Helpers\HVero;
use App\Models\MediumTermLoan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Client-requested (2026-08-17): the MTL create/edit form previously had
 * no server-side validation at all — MediumTermLoanController@store()
 * called storeBasicForm() directly, which just copies whatever the
 * request has onto matching columns and saves. Every field on the form
 * is marked "*" (required) visually, but nothing actually enforced that,
 * so a completely blank form — including Installment Payment Interval —
 * saved successfully.
 *
 * Used by BOTH store() and update(): update() re-enters store() by
 * calling it directly (see that method's own docblock), so validating
 * once here, on both routes, is enough to cover create and edit with
 * identical rules — no duplicated rule set to drift out of sync.
 *
 * Deliberately does NOT require the "Already Running Facility?" section
 * (already_paid_amount / remaining_installment_count /
 * first_installment_date) or Odoo Code — the form itself labels those as
 * optional ("Fill this in only if...") and doesn't mark them with "*".
 */
class StoreMediumTermLoanRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'currency' => ['required', 'string'],
            'limit' => ['required', 'numeric'],
            'account_number' => ['required', 'string'],
            'borrowing_rate' => ['required', 'numeric'],
            'margin_rate' => ['required', 'numeric'],
            'duration' => ['required', 'integer'],
            'installment_payment_interval' => ['required', Rule::in(array_column(HVero::getDurationIntervalTypesForSelect(), 'value'))],
            'consumption_status' => ['required', Rule::in(array_column(MediumTermLoan::getConsumptionStatusesForSelect(), 'value'))],
        ];
    }

    public function messages()
    {
        return [
            'installment_payment_interval.required' => __('Please select the Installment Payment Interval.'),
            'installment_payment_interval.in' => __('Please select a valid Installment Payment Interval.'),
        ];
    }
}
