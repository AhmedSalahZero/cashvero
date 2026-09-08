<?php

namespace App\Http\Requests;

use App\Models\MultipleCashExpense;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * * التحقق من المصروف النقدي المتعدد
 *
 * * الحاجة اللي تخصه لوحده : لازم يكون فيه بند واحد على الأقل بمبلغ ،
 * * لأن الإجمالي اللي بيتصرف من الخزنة/البنك بيتحسب منهم — حركة من غير
 * * بنود معناها صرف بصفر
 */
class StoreMultipleCashExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'in:'.implode(',', [
                MultipleCashExpense::CASH_PAYMENT,
                MultipleCashExpense::PAYABLE_CHEQUE,
                MultipleCashExpense::OUTGOING_TRANSFER,
            ])],
            'payment_date' => ['required', 'date'],
            'currency' => ['required', 'string'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'user_comment' => ['nullable', 'string'],

            /* البنود */
            'items' => ['required', 'array', 'min:1'],
            'items.*.cash_expense_category_name_id' => ['required', 'integer', 'exists:cash_expense_category_names,id'],
            'items.*.paid_amount' => ['required', 'numeric', 'gt:0'],

            /* تفاصيل الدفع */
            'delivery_branch_id' => ['nullable', 'integer'],
            'delivery_bank_id' => ['nullable', 'integer'],
            'account_type' => ['nullable', 'integer'],
            'account_number' => ['nullable', 'string'],
            'receipt_number' => ['nullable', 'string', 'max:255'],
            'cheque_number' => ['nullable', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],

            /* التوزيع على العقود — مربوط ببند بعينه */
            'allocations' => ['nullable', 'array'],
            'allocations.*.item_index' => ['required_with:allocations.*.contract_id', 'integer', 'min:0'],
            'allocations.*.contract_id' => ['nullable', 'integer', 'exists:contracts,id'],
            'allocations.*.amount' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            /**
             * * التوزيع بيشاور على بند بترتيبه في الـ repeater ، فلازم
             * * الترتيب ده يكون موجود فعلا — و إلا التوزيع هيبقى معلّق
             * * على بند مش موجود
             */
            $itemCount = count($this->input('items', []));

            foreach ((array) $this->input('allocations', []) as $index => $allocation) {
                if (empty($allocation['contract_id'])) {
                    continue;
                }

                $itemIndex = $allocation['item_index'] ?? null;

                if ($itemIndex === null || $itemIndex < 0 || $itemIndex >= $itemCount) {
                    $validator->errors()->add(
                        "allocations.{$index}.item_index",
                        __('Choose which expense this allocation belongs to.')
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'items.required' => __('Add at least one expense line.'),
            'items.min' => __('Add at least one expense line.'),
            'items.*.paid_amount.gt' => __('Each expense line needs an amount greater than zero.'),
            'items.*.cash_expense_category_name_id.required' => __('Each expense line needs an expense name.'),
        ];
    }
}
