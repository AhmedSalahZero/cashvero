<?php

namespace App\Http\Requests;

use App\Models\CashExpense;
use App\Models\MoneyPayment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UnmarkChequeAsPaidRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'cheques' => ['required'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $isCashExpense = $this->route()->getName() === 'cash.expense.payable.cheque.unmark.as.paid';
            $modelClass = $isCashExpense ? CashExpense::class : MoneyPayment::class;
            $company = $this->route('company');
            $companyId = is_object($company) ? $company->id : $company;

            foreach ($this->chequeIds() as $id) {
                $row = $modelClass::query()->with('payableCheque')->find($id);

                if (! $row || (int) $row->company_id !== (int) $companyId || ! $row->payableCheque) {
                    $validator->errors()->add('cheques', __('Invalid cheque'));

                    return;
                }

                if (! $row->payableCheque->isPaid()) {
                    $validator->errors()->add('cheques', __('Cheque is not paid'));

                    return;
                }

                if ($isCashExpense && $row->isOpenBalance()) {
                    $validator->errors()->add('cheques', __('Opening balance cheques cannot be unmarked as unpaid'));

                    return;
                }
            }
        });
    }

    /**
     * @return array<int, int|string>
     */
    public function chequeIds(): array
    {
        $ids = $this->input('cheques');
        $ids = is_array($ids) ? $ids : explode(',', (string) $ids);

        return array_values(array_filter($ids, fn ($id) => $id !== '' && $id !== null));
    }
}
