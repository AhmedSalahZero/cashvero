<?php

namespace App\Http\Requests;

use App\Models\CustomerInvoice;
use App\Models\FactoringContract;
use App\Models\FactoringTransaction;
use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFactoringWithoutRecourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'factoring_interest_amount' => unformat_number($this->input('factoring_interest_amount')),
            'received_amount' => unformat_number($this->input('received_amount')),
            'other_charges' => unformat_number($this->input('other_charges')),
        ]);
    }

    public function rules(): array
    {
        $companyId = $this->route('company')?->id;

        return [
            'factoring_date' => 'required|date',
            'factoring_company_id' => [
                'required',
                Rule::exists('factoring_companies', 'id')->where('company_id', $companyId),
            ],
            'factoring_contract_id' => [
                'required',
                Rule::exists('factoring_contracts', 'id')
                    ->where('company_id', $companyId)
                    ->where('factoring_company_id', $this->input('factoring_company_id'))
                    ->where('recourse_type', FactoringContract::WITHOUT_RECOURSE),
            ],
            'customer_id' => [
                'required',
                Rule::exists('partners', 'id')->where('company_id', $companyId)->where('is_customer', 1),
            ],
            'invoice_currency' => 'required|string',
            'customer_invoice_id' => [
                'required',
                Rule::exists('customer_invoices', 'id')->where('company_id', $companyId),
            ],
            'factoring_percentage' => 'required|numeric|min:0|max:100',
            'factoring_interest_amount' => 'required|numeric|min:0',
            'other_charges' => 'required|numeric|min:0',
            'received_amount' => 'required|numeric|min:0',
            'financial_institution_id' => [
                'required',
                Rule::exists('financial_institutions', 'id')->where('company_id', $companyId),
            ],
            'account_type_id' => 'required|exists:account_types,id',
            'account_number' => 'required|string',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $invoice = CustomerInvoice::find($this->input('customer_invoice_id'));
            if (!$invoice) {
                return;
            }

            $exceptTransactionId = $this->exceptFactoringTransactionId();

            if ((int) $invoice->customer_id !== (int) $this->input('customer_id')) {
                $validator->errors()->add('customer_invoice_id', __('The selected invoice does not belong to this customer.'));
            }

            if ($invoice->getCurrency() !== $this->input('invoice_currency')) {
                $validator->errors()->add('invoice_currency', __('The selected invoice currency does not match.'));
            }

            if ((float) $invoice->getCollectedOrPaidAmount() > 0 && !$this->isEditingCurrentInvoice($invoice)) {
                $validator->errors()->add('customer_invoice_id', __('Only invoices with zero collection amount can be factored.'));
            }

            if ((float) $invoice->net_balance <= 0 && !$this->isEditingCurrentInvoice($invoice)) {
                $validator->errors()->add('customer_invoice_id', __('Only invoices with an outstanding balance can be factored.'));
            }

            if (FactoringTransaction::query()
                ->where('customer_invoice_id', $invoice->id)
                ->when($exceptTransactionId, fn ($query) => $query->where('id', '!=', $exceptTransactionId))
                ->exists()) {
                $validator->errors()->add('customer_invoice_id', __('This invoice has already been used in a factoring transaction.'));
            }

            $factoringDate = Carbon::make(parseDatePickerValue($this->input('factoring_date')) ?? $this->input('factoring_date'))->startOfDay();
            $dueDate = Carbon::make($invoice->getInvoiceDueDate())->startOfDay();
            if ($factoringDate->lt($dueDate)) {
                $validator->errors()->add('factoring_date', __('Factoring date must be greater than or equal to invoice due date.'));
            }

            $contract = FactoringContract::find($this->input('factoring_contract_id'));
            if (!$contract) {
                return;
            }

            $contractDate = $factoringDate->format('Y-m-d');
            $isCurrentContract = $exceptTransactionId
                && (int) FactoringTransaction::find($exceptTransactionId)?->factoring_contract_id === (int) $this->input('factoring_contract_id');
            if (
                !$isCurrentContract
                && (
                    !$contract->contract_start_date
                    || !$contract->contract_end_date
                    || $contract->contract_start_date > $contractDate
                    || $contract->contract_end_date < $contractDate
                )
            ) {
                $validator->errors()->add('factoring_contract_id', __('The selected contract is not active on the factoring date.'));
            }

            $amounts = FactoringTransaction::calculateAmounts(
                (float) $invoice->getNetInvoiceAmount(),
                (float) $this->input('factoring_percentage'),
                (float) $contract->borrowing_rate,
                (float) $contract->margin_rate,
                (float) unformat_number($this->input('other_charges')),
                $factoringDate->format('Y-m-d'),
                $invoice->getInvoiceDueDate()
            );

            $factoringInterestAmount = (float) unformat_number($this->input('factoring_interest_amount'));
            $receivedAmount = (float) unformat_number($this->input('received_amount'));
            $otherCharges = (float) unformat_number($this->input('other_charges'));
            $factoringAmount = $amounts['factoring_amount'];
            $sum = round($factoringInterestAmount + $receivedAmount + $otherCharges, 2);

            if (abs($sum - $factoringAmount) > 0.02) {
                $validator->errors()->add(
                    'received_amount',
                    __('Received amount, factoring interest amount, and other charges must equal factoring amount.')
                );
            }

            if ($receivedAmount < 0) {
                $validator->errors()->add('received_amount', __('Received amount cannot be negative.'));
            }

            if ($factoringInterestAmount < 0) {
                $validator->errors()->add('factoring_interest_amount', __('Factoring interest amount cannot be negative.'));
            }

            if ($receivedAmount > $contract->getRemainingLimit($exceptTransactionId)) {
                $validator->errors()->add(
                    'received_amount',
                    __('Factoring amount cannot exceed the remaining contract limit.')
                );
            }
        });
    }

    protected function exceptFactoringTransactionId(): ?int
    {
        $transaction = $this->route('factoringTransaction');

        return $transaction?->id;
    }

    protected function isEditingCurrentInvoice(CustomerInvoice $invoice): bool
    {
        $transaction = $this->route('factoringTransaction');

        return $transaction
            && (int) $transaction->customer_invoice_id === (int) $invoice->id;
    }
}
