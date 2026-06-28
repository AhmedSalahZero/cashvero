<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFactoringWithoutRecourseRequest;
use App\Models\AccountType;
use App\Models\Company;
use App\Models\CustomerInvoice;
use App\Models\FactoringCompany;
use App\Models\FactoringContract;
use App\Models\FactoringTransaction;
use App\Models\FinancialInstitution;
use App\Models\Partner;
use App\Models\Settlement;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FactoringWithoutRecourseController
{
    use GeneralFunctions;

    protected function applyFilter(Request $request, Collection $collection): Collection
    {
        if (!count($collection)) {
            return $collection;
        }

        $searchFieldName = $request->get('field');
        $dateFieldName = $searchFieldName === 'factoring_date' ? 'factoring_date' : 'created_at';
        $from = $request->get('from');
        $to = $request->get('to');
        $value = $request->query('value');

        return $collection
            ->when($request->has('value'), function ($collection) use ($value, $searchFieldName) {
                return $collection->filter(function ($item) use ($value, $searchFieldName) {
                    if ($searchFieldName === 'customer_id') {
                        return false !== stristr($item->customer?->getName() ?? '', (string) $value);
                    }
                    if ($searchFieldName === 'factoring_company_id') {
                        return false !== stristr($item->factoringCompany?->getName() ?? '', (string) $value);
                    }

                    return false !== stristr((string) $item->{$searchFieldName}, (string) $value);
                });
            })
            ->when($request->get('from'), function ($collection) use ($dateFieldName, $from) {
                return $collection->where($dateFieldName, '>=', $from);
            })
            ->when($request->get('to'), function ($collection) use ($dateFieldName, $to) {
                return $collection->where($dateFieldName, '<=', $to);
            })
            ->sortByDesc('id')
            ->values();
    }

    public function index(Company $company, Request $request)
    {
        $transactions = $company->factoringTransactions()
            ->with(['factoringCompany', 'factoringContract', 'customer', 'customerInvoice', 'financialInstitution'])
            ->where('recourse_type', FactoringTransaction::WITHOUT_RECOURSE)
            ->get();

        $transactions = $this->applyFilter($request, $transactions);

        $searchFields = [
            'factoring_date' => __('Factoring Date'),
            'customer_id' => __('Customer'),
            'factoring_company_id' => __('Factoring Company'),
            'invoice_currency' => __('Invoice Currency'),
            'received_amount' => __('Received Amount'),
        ];

        return view('factoring.without-recourse.index', compact('company', 'transactions', 'searchFields'));
    }

    public function create(Company $company)
    {
        return view('factoring.without-recourse.form', $this->formViewData($company));
    }

    public function edit(Company $company, FactoringTransaction $factoringTransaction)
    {
        $this->ensureWithoutRecourseTransaction($company, $factoringTransaction);

        $factoringTransaction->load(['customer', 'customerInvoice', 'factoringCompany', 'factoringContract']);

        return view('factoring.without-recourse.form', array_merge(
            $this->formViewData($company),
            [
                'factoringTransaction' => $factoringTransaction,
                'contracts' => $this->contractsForCompany(
                    $company,
                    (int) $factoringTransaction->factoring_company_id,
                    $factoringTransaction->factoring_date,
                    (int) $factoringTransaction->factoring_contract_id
                ),
            ]
        ));
    }

    public function store(Company $company, StoreFactoringWithoutRecourseRequest $request)
    {
        $invoice = CustomerInvoice::findOrFail($request->input('customer_invoice_id'));
        $contract = FactoringContract::findOrFail($request->input('factoring_contract_id'));
        $accountType = AccountType::findOrFail($request->input('account_type_id'));
        $factoringDate = parseDatePickerValue($request->input('factoring_date')) ?? now()->format('Y-m-d');
        $otherCharges = (float) unformat_number($request->input('other_charges'));

        $amounts = FactoringTransaction::calculateAmounts(
            (float) $invoice->getNetInvoiceAmount(),
            (float) $request->input('factoring_percentage'),
            (float) $contract->borrowing_rate,
            (float) $contract->margin_rate,
            $otherCharges,
            $factoringDate,
            $invoice->getInvoiceDueDate()
        );

        $factoringInterestAmount = (float) unformat_number($request->input('factoring_interest_amount'));
        $receivedAmount = (float) unformat_number($request->input('received_amount'));

        $transaction = DB::transaction(function () use ($company, $request, $invoice, $contract, $accountType, $factoringDate, $otherCharges, $amounts, $factoringInterestAmount, $receivedAmount) {
            /** @var FactoringTransaction $transaction */
            $transaction = FactoringTransaction::create([
                'company_id' => $company->id,
                'recourse_type' => FactoringTransaction::WITHOUT_RECOURSE,
                'factoring_date' => $factoringDate,
                'factoring_company_id' => $request->input('factoring_company_id'),
                'factoring_contract_id' => $contract->id,
                'customer_id' => $request->input('customer_id'),
                'customer_invoice_id' => $invoice->id,
                'invoice_currency' => $request->input('invoice_currency'),
                'invoice_amount' => $invoice->getNetInvoiceAmount(),
                'factoring_percentage' => $request->input('factoring_percentage'),
                'factoring_amount' => $amounts['factoring_amount'],
                'contract_interest_rate' => $amounts['contract_interest_rate'],
                'diff_in_days' => $amounts['diff_in_days'],
                'factoring_interest_amount' => $factoringInterestAmount,
                'other_charges' => $otherCharges,
                'received_amount' => $receivedAmount,
                'financial_institution_id' => $request->input('financial_institution_id'),
                'account_type_id' => $accountType->id,
                'account_number' => $request->input('account_number'),
                'created_by' => auth()->id(),
            ]);

            $commentEn = __('Factoring Amount From Account Number #:accountNumber', ['accountNumber' => $transaction->account_number]);
            $commentAr = __('Factoring Amount From Account Number #:accountNumber', ['accountNumber' => $transaction->account_number], 'ar');

            $transaction->storeBankDebitStatement(
                $company->id,
                (int) $request->input('financial_institution_id'),
                $accountType,
                $request->input('account_number'),
                $factoringDate,
                $receivedAmount,
                $commentEn,
                $commentAr
            );

            $settlement = Settlement::create([
                'invoice_id' => $invoice->id,
                'partner_id' => $request->input('customer_id'),
                'settlement_amount' => $invoice->getNetInvoiceAmount(),
                'withhold_amount' => 0,
                'company_id' => $company->id,
                'factoring_transaction_id' => $transaction->id,
                'is_from_down_payment' => 0,
            ]);

            $transaction->update(['settlement_id' => $settlement->id]);

            return $transaction;
        });

        return response()->json([
            'redirectTo' => route('factoring.without-recourse.index', ['company' => $company->id]),
        ]);
    }

    public function update(Company $company, FactoringTransaction $factoringTransaction, StoreFactoringWithoutRecourseRequest $request)
    {
        $this->ensureWithoutRecourseTransaction($company, $factoringTransaction);

        $invoice = CustomerInvoice::findOrFail($request->input('customer_invoice_id'));
        $contract = FactoringContract::findOrFail($request->input('factoring_contract_id'));
        $accountType = AccountType::findOrFail($request->input('account_type_id'));
        $factoringDate = parseDatePickerValue($request->input('factoring_date')) ?? now()->format('Y-m-d');
        $otherCharges = (float) unformat_number($request->input('other_charges'));

        $amounts = FactoringTransaction::calculateAmounts(
            (float) $invoice->getNetInvoiceAmount(),
            (float) $request->input('factoring_percentage'),
            (float) $contract->borrowing_rate,
            (float) $contract->margin_rate,
            $otherCharges,
            $factoringDate,
            $invoice->getInvoiceDueDate()
        );

        $factoringInterestAmount = (float) unformat_number($request->input('factoring_interest_amount'));
        $receivedAmount = (float) unformat_number($request->input('received_amount'));

        DB::transaction(function () use ($company, $request, $invoice, $contract, $accountType, $factoringDate, $otherCharges, $amounts, $factoringInterestAmount, $receivedAmount, $factoringTransaction) {
            $factoringTransaction->deleteBankDebitStatements();

            $factoringTransaction->update([
                'factoring_date' => $factoringDate,
                'factoring_company_id' => $request->input('factoring_company_id'),
                'factoring_contract_id' => $contract->id,
                'customer_id' => $request->input('customer_id'),
                'customer_invoice_id' => $invoice->id,
                'invoice_currency' => $request->input('invoice_currency'),
                'invoice_amount' => $invoice->getNetInvoiceAmount(),
                'factoring_percentage' => $request->input('factoring_percentage'),
                'factoring_amount' => $amounts['factoring_amount'],
                'contract_interest_rate' => $amounts['contract_interest_rate'],
                'diff_in_days' => $amounts['diff_in_days'],
                'factoring_interest_amount' => $factoringInterestAmount,
                'other_charges' => $otherCharges,
                'received_amount' => $receivedAmount,
                'financial_institution_id' => $request->input('financial_institution_id'),
                'account_type_id' => $accountType->id,
                'account_number' => $request->input('account_number'),
                'updated_by' => auth()->id(),
            ]);

            $commentEn = __('Factoring Amount From Account Number #:accountNumber', ['accountNumber' => $factoringTransaction->account_number]);
            $commentAr = __('Factoring Amount From Account Number #:accountNumber', ['accountNumber' => $factoringTransaction->account_number], 'ar');

            $factoringTransaction->storeBankDebitStatement(
                $company->id,
                (int) $request->input('financial_institution_id'),
                $accountType,
                $request->input('account_number'),
                $factoringDate,
                $receivedAmount,
                $commentEn,
                $commentAr
            );

            if ($factoringTransaction->settlement) {
                $factoringTransaction->settlement->update([
                    'settlement_amount' => $invoice->getNetInvoiceAmount(),
                    'partner_id' => $request->input('customer_id'),
                ]);
            }
        });

        return response()->json([
            'redirectTo' => route('factoring.without-recourse.index', ['company' => $company->id]),
        ]);
    }

    public function destroy(Company $company, FactoringTransaction $factoringTransaction)
    {
        $this->ensureWithoutRecourseTransaction($company, $factoringTransaction);

        $factoringTransaction->deleteRelations();
        $factoringTransaction->delete();

        return redirect()->back()->with('success', __('Item Has Been Delete Successfully'));
    }

    public function getContracts(Company $company, FactoringCompany $factoringCompany, Request $request)
    {
        abort_unless($factoringCompany->company_id === $company->id, 404);

        $date = parseDatePickerValue($request->get('factoring_date')) ?? now()->format('Y-m-d');
        $includeContractId = $request->integer('except_factoring_transaction_id')
            ? FactoringTransaction::find($request->integer('except_factoring_transaction_id'))?->factoring_contract_id
            : null;

        $contracts = $this->contractsForCompany($company, $factoringCompany->id, $date, $includeContractId)
            ->map(fn (FactoringContract $contract) => [
                'id' => $contract->id,
                'label' => $contract->getContractStartDateFormatted() . ' — ' . $contract->getContractEndDateFormatted()
                    . ' | ' . strtoupper($contract->getCurrency() ?? '')
                    . ' | ' . $contract->getLimitFormatted(),
                'borrowing_rate' => (float) $contract->borrowing_rate,
                'margin_rate' => (float) $contract->margin_rate,
                'contract_interest_rate' => $contract->getContractInterestRate(),
                'currency' => $contract->getCurrency(),
            ]);

        return response()->json(['status' => true, 'contracts' => $contracts]);
    }

    public function getInvoiceCurrencies(Company $company, int $customerId, Request $request)
    {
        $exceptTransactionId = $request->integer('except_factoring_transaction_id') ?: null;

        $currencies = $this->availableInvoicesQuery($company, $customerId, null, $exceptTransactionId)
            ->pluck('currency')
            ->unique()
            ->filter()
            ->mapWithKeys(function ($currency) {
                $allCurrencies = getCurrencies();

                return [$currency => $allCurrencies[$currency] ?? strtoupper($currency)];
            });

        if ($exceptTransactionId) {
            $transaction = FactoringTransaction::query()
                ->where('company_id', $company->id)
                ->where('customer_id', $customerId)
                ->find($exceptTransactionId);

            if ($transaction && $transaction->invoice_currency) {
                $allCurrencies = getCurrencies();
                $currency = $transaction->invoice_currency;
                $currencies[$currency] = $allCurrencies[$currency] ?? strtoupper($currency);
            }
        }

        return response()->json(['status' => true, 'currencies' => $currencies]);
    }

    public function getInvoices(Company $company, int $customerId, ?string $currency = null)
    {
        $exceptTransactionId = request()->integer('except_factoring_transaction_id') ?: null;

        $invoices = $this->availableInvoicesQuery($company, $customerId, $currency, $exceptTransactionId)
            ->orderBy('invoice_date')
            ->get(['id', 'invoice_number', 'invoice_due_date', 'net_invoice_amount', 'currency'])
            ->map(fn (CustomerInvoice $invoice) => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'invoice_due_date' => $invoice->getInvoiceDueDate()
                    ? Carbon::make($invoice->getInvoiceDueDate())->format('Y-m-d')
                    : '',
                'invoice_amount' => (float) $invoice->getNetInvoiceAmount(),
                'invoice_amount_formatted' => number_format((float) $invoice->getNetInvoiceAmount(), 2),
                'currency' => $invoice->getCurrency(),
            ]);

        return response()->json(['status' => true, 'invoices' => $invoices]);
    }

    public function calculate(Request $request, Company $company)
    {
        $invoice = CustomerInvoice::where('company_id', $company->id)->find($request->input('customer_invoice_id'));
        $contract = FactoringContract::where('company_id', $company->id)->find($request->input('factoring_contract_id'));

        if (!$invoice || !$contract) {
            return response()->json(['status' => false], 422);
        }

        $amounts = FactoringTransaction::calculateAmounts(
            (float) $invoice->getNetInvoiceAmount(),
            (float) $request->input('factoring_percentage', 0),
            (float) $contract->borrowing_rate,
            (float) $contract->margin_rate,
            (float) unformat_number($request->input('other_charges', 0)),
            parseDatePickerValue($request->input('factoring_date', now()->format('Y-m-d'))) ?? now()->format('Y-m-d'),
            $invoice->getInvoiceDueDate()
        );

        $invoiceDueDate = $invoice->getInvoiceDueDate()
            ? Carbon::make($invoice->getInvoiceDueDate())->format('Y-m-d')
            : '';

        return response()->json([
            'status' => true,
            'invoice_amount' => (float) $invoice->getNetInvoiceAmount(),
            'invoice_due_date' => $invoiceDueDate,
            ...$amounts,
        ]);
    }

    protected function availableInvoicesQuery(Company $company, int $customerId, ?string $currency = null, ?int $exceptFactoringTransactionId = null)
    {
        $usedInvoiceIds = FactoringTransaction::query()
            ->when($exceptFactoringTransactionId, fn ($query) => $query->where('id', '!=', $exceptFactoringTransactionId))
            ->pluck('customer_invoice_id');

        $currentInvoiceId = $exceptFactoringTransactionId
            ? FactoringTransaction::query()->where('company_id', $company->id)->find($exceptFactoringTransactionId)?->customer_invoice_id
            : null;

        return CustomerInvoice::query()
            ->where('company_id', $company->id)
            ->where('customer_id', $customerId)
            ->where('net_invoice_amount', '>', 0)
            ->where(function ($query) use ($currentInvoiceId) {
                $query->where(function ($availableQuery) {
                    $availableQuery->where('collected_amount', 0)->where('net_balance', '>', 0);
                });

                if ($currentInvoiceId) {
                    $query->orWhere('id', $currentInvoiceId);
                }
            })
            ->whereNotIn('id', $usedInvoiceIds)
            ->when($currency, fn ($q) => $q->where('currency', $currency));
    }

    protected function contractsForCompany(Company $company, int $factoringCompanyId, string $date, ?int $includeContractId = null): Collection
    {
        $contracts = $company->factoringContracts()
            ->where('factoring_company_id', $factoringCompanyId)
            ->where('recourse_type', FactoringContract::WITHOUT_RECOURSE)
            ->activeOnDate($date)
            ->get();

        if ($includeContractId && !$contracts->contains('id', $includeContractId)) {
            $currentContract = $company->factoringContracts()->find($includeContractId);
            if ($currentContract) {
                $contracts->push($currentContract);
            }
        }

        return $contracts;
    }

    protected function ensureWithoutRecourseTransaction(Company $company, FactoringTransaction $factoringTransaction): void
    {
        abort_unless(
            $factoringTransaction->company_id === $company->id
                && $factoringTransaction->recourse_type === FactoringTransaction::WITHOUT_RECOURSE,
            404
        );
    }

    protected function formViewData(Company $company): array
    {
        return [
            'company' => $company,
            'factoringCompanies' => $company->factoringCompanies()->orderBy('name')->pluck('name', 'id'),
            'customers' => Partner::onlyCustomers()->where('company_id', $company->id)->orderBy('name')->pluck('name', 'id'),
            'financialInstitutionBanks' => FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get(),
            'accountTypes' => AccountType::onlyCashAccounts()->get(),
        ];
    }
}
