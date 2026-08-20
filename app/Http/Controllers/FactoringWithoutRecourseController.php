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
use App\Traits\FiltersFactoringTransactions;
use App\Traits\GeneralFunctions;
use App\Support\ShareholderAccounts\AccountNumberLabel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * FactoringWithoutRecourseController
 * ------------------------------------------------------------------
 * "Factoring Without Recourse" — the company sells a customer invoice
 * to a factoring company and the risk transfers immediately: unlike
 * With Recourse, the invoice is settled right away at creation (see
 * store()'s Settlement::create() call), and if the customer never
 * pays, that's the factoring company's problem, not ours. What's
 * still tracked here afterward: Mark As Settled (an administrative
 * confirmation step, separate from the automatic invoice settlement
 * done at creation) and Record Difference Received (any shortfall
 * between what was disbursed and the invoice's real value, paid back
 * separately by the factoring company) — both independently
 * revertible. No Odoo write, no DB triggers.
 *
 * ⚠️ Naming note: `is_settled`/`markAsSettled()` here refers to this
 * feature's own administrative settlement flag — NOT the same concept
 * as the `Settlement` model row created in store(), which settles the
 * underlying customer invoice itself. Both are real and both matter,
 * they're just two different "settled"s. Left exactly as the
 * original named them.
 *
 * ── Frontend migration status ───────────────────────────────────
 *   index()          → Inertia::render, Pages/FactoringWithoutRecourse/Index.vue
 *   create()/edit()  → Inertia::render, Pages/Factoring/Form.vue — the SAME shared
 *                       component used by Factoring With Recourse, distinguished
 *                       via the `recourseType`/`pageTitle` props (confirmed
 *                       byte-for-byte identical blade forms apart from labels/routes).
 *   store()/update() → real Laravel redirects (Inertia-compatible), was raw
 *                       JSON ({redirectTo}) for the old jQuery/AJAX form.
 *   destroy()/markAsSettled()/revertSettlement()/markDifferenceReceived()/
 *   revertDifferenceReceived() → unchanged, already redirect()->back(),
 *                       Inertia-compatible as-is.
 *   getContracts()/getInvoiceCurrencies()/getInvoices()/calculate()
 *                     → unchanged, pure JSON AJAX endpoints for the Vue form's
 *                       cascading dropdowns — not Inertia visits.
 */
class FactoringWithoutRecourseController
{
    use FiltersFactoringTransactions;
    use GeneralFunctions;

    private const ROWS_PER_PAGE = 20;

    /**
     * Listing page. This used to load every factoring transaction the
     * company had ever recorded — with five eager-loaded relations and no
     * date window — and then filter and sort that collection in PHP. The
     * filter runs as SQL now (see FiltersFactoringTransactions), so the
     * database returns one page at a time.
     */
    public function index(Company $company, Request $request)
    {
        $query = $company->factoringTransactions()
            ->with(['factoringCompany', 'factoringContract', 'customer', 'customerInvoice', 'financialInstitution'])
            ->where('recourse_type', FactoringTransaction::WITHOUT_RECOURSE);

        $transactions = $this->applyFactoringFilter($request, $query)
            ->paginate(self::ROWS_PER_PAGE)
            ->withQueryString();

        $searchFields = $this->factoringSearchFields();

        return Inertia::render('FactoringWithoutRecourse/Index', [
            'company' => ['id' => $company->id, 'name' => $company->getName()],
            'searchFields' => $searchFields,
            // Echoed back so the search inputs keep what the user typed.
            // The paginator links carry the same values, so page 2 stays
            // inside the same filtered set.
            'filters' => [
                'field' => $request->get('field', ''),
                'value' => $request->query('value', ''),
                'from' => $request->get('from', ''),
                'to' => $request->get('to', ''),
            ],
            'financialInstitutionBanks' => FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get()
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->getName()])->values(),
            'accountTypes' => AccountType::onlyCashAccounts()->get()
                ->map(fn ($a) => ['id' => $a->id, 'name' => $a->getName()])->values(),
            'canCreate' => hasAuthFor('factoring_without_recourse.create'),
            'canUpdate' => hasAuthFor('factoring_without_recourse.update'),
            'canDelete' => hasAuthFor('factoring_without_recourse.delete'),
            'transactions' => $transactions->through(fn (FactoringTransaction $t) => [
                'id' => $t->id,
                'factoring_date_formatted' => $t->getFactoringDateFormatted(),
                'factoring_company_name' => $t->factoringCompany?->getName(),
                'customer_name' => $t->customer?->getName(),
                'invoice_number' => $t->customerInvoice?->invoice_number,
                'invoice_currency' => $t->invoice_currency,
                'factoring_amount' => (float) $t->factoring_amount,
                'received_amount' => (float) $t->received_amount,
                'is_settled' => (bool) $t->is_settled,
                'settled_at' => $t->settled_at,
                'is_difference_received' => (bool) $t->is_difference_received,
                'difference_received_amount' => (float) $t->difference_received_amount,
                'difference_amount' => $t->getDifferenceAmount(),
                'financial_institution_name' => $t->financialInstitution?->getName(),
                'financial_institution_id' => $t->financial_institution_id,
                'account_type_id' => $t->account_type_id,
                'account_number' => $t->account_number,
                'account_number_label' => AccountNumberLabel::forCurrentAccount($company->id, $t->financial_institution_id, $t->account_number),
                'edit_url' => route('factoring.without-recourse.edit', ['company' => $company->id, 'factoringTransaction' => $t->id]),
                'mark_as_settled_url' => route('factoring.without-recourse.mark-as-settled', ['company' => $company->id, 'factoringTransaction' => $t->id]),
                'revert_settlement_url' => route('factoring.without-recourse.revert-settlement', ['company' => $company->id, 'factoringTransaction' => $t->id]),
                'mark_difference_received_url' => route('factoring.without-recourse.mark-difference-received', ['company' => $company->id, 'factoringTransaction' => $t->id]),
                'revert_difference_received_url' => route('factoring.without-recourse.revert-difference-received', ['company' => $company->id, 'factoringTransaction' => $t->id]),
                'delete_url' => route('factoring.without-recourse.destroy', ['company' => $company->id, 'factoringTransaction' => $t->id]),
            ])->toArray(),
            'urls' => [
                'create' => route('factoring.without-recourse.create', ['company' => $company->id]),
                'index' => route('factoring.without-recourse.index', ['company' => $company->id]),
                // See FactoringWithRecourseController::index() for the full
                // explanation — same missing-key bug, same fix.
                'getAccountNumbersForType' => $this->companyScopedUrl($company, 'money-received/get-account-numbers-based-on-account-type'),
            ],
        ]);
    }

    public function create(Company $company)
    {
        return Inertia::render('Factoring/Form', $this->formViewData($company));
    }

    public function edit(Company $company, FactoringTransaction $factoringTransaction)
    {
        $this->ensureWithoutRecourseTransaction($company, $factoringTransaction);

        $factoringTransaction->load(['customer', 'customerInvoice', 'factoringCompany', 'factoringContract']);
        $invoice = $factoringTransaction->customerInvoice;
        $contracts = $this->contractsForCompany(
            $company,
            (int) $factoringTransaction->factoring_company_id,
            $factoringTransaction->factoring_date,
            (int) $factoringTransaction->factoring_contract_id
        );

        $viewData = $this->formViewData($company);
        $viewData['mode'] = 'edit';
        $viewData['model'] = [
            'id' => $factoringTransaction->id,
            'factoring_date' => $factoringTransaction->factoring_date,
            'factoring_company_id' => $factoringTransaction->factoring_company_id,
            'factoring_contract_id' => $factoringTransaction->factoring_contract_id,
            'customer_id' => $factoringTransaction->customer_id,
            'customer_name' => $factoringTransaction->customer?->getName(),
            'invoice_currency' => $factoringTransaction->invoice_currency,
            'customer_invoice_id' => $factoringTransaction->customer_invoice_id,
            'invoice_number' => $invoice?->invoice_number,
            'invoice_due_date' => $invoice?->getInvoiceDueDate(),
            'invoice_amount' => (float) $factoringTransaction->invoice_amount,
            'factoring_percentage' => (float) $factoringTransaction->factoring_percentage,
            'factoring_amount' => (float) $factoringTransaction->factoring_amount,
            'remaining_limit' => $factoringTransaction->factoringContract?->getRemainingLimit($factoringTransaction->id) ?? 0,
            'contract_interest_rate' => (float) $factoringTransaction->contract_interest_rate,
            'diff_in_days' => $factoringTransaction->diff_in_days,
            'factoring_interest_amount' => (float) $factoringTransaction->factoring_interest_amount,
            'other_charges' => (float) $factoringTransaction->other_charges,
            'received_amount' => (float) $factoringTransaction->received_amount,
            'financial_institution_id' => $factoringTransaction->financial_institution_id,
            'account_type_id' => $factoringTransaction->account_type_id,
            'account_number' => $factoringTransaction->account_number,
        ];
        $viewData['contracts'] = $contracts->map(fn (FactoringContract $contract) => [
            'id' => $contract->id,
            'label' => $contract->getContractStartDateFormatted() . ' — ' . $contract->getContractEndDateFormatted()
                . ' | ' . strtoupper($contract->getCurrency() ?? '')
                . ' | ' . $contract->getLimitFormatted(),
        ])->values();
        $viewData['urls']['update'] = route('factoring.without-recourse.update', ['company' => $company->id, 'factoringTransaction' => $factoringTransaction->id]);

        return Inertia::render('Factoring/Form', $viewData);
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

            $commentEn = __('Factoring Without Recourse Amount For Invoice #:invoiceNumber', ['invoiceNumber' => $invoice->getInvoiceNumber()]);
            $commentAr = __('Factoring Without Recourse Amount For Invoice #:invoiceNumber', ['invoiceNumber' => $invoice->getInvoiceNumber()], 'ar');

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

            $this->syncFactoringDisbursementStatement($transaction, $company, $factoringDate, $receivedAmount, $invoice);

            return $transaction;
        });

        return redirect()->route('factoring.without-recourse.index', ['company' => $company->id])
            ->with('success', __('Data Store Successfully'));
    }

    public function update(Company $company, FactoringTransaction $factoringTransaction, StoreFactoringWithoutRecourseRequest $request)
    {
        $this->ensureWithoutRecourseTransaction($company, $factoringTransaction);
        abort_if($factoringTransaction->isSettled(), 422, __('Settled factoring transactions cannot be edited.'));

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

            $commentEn = __('Factoring Without Recourse Amount For Invoice #:invoiceNumber', ['invoiceNumber' => $invoice->getInvoiceNumber()]);
            $commentAr = __('Factoring Without Recourse Amount For Invoice #:invoiceNumber', ['invoiceNumber' => $invoice->getInvoiceNumber()], 'ar');

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

            $this->syncFactoringDisbursementStatement($factoringTransaction, $company, $factoringDate, $receivedAmount, $invoice);
        });

        return redirect()->route('factoring.without-recourse.index', ['company' => $company->id])
            ->with('success', __('Item Has Been Updated Successfully'));
    }

    public function destroy(Company $company, FactoringTransaction $factoringTransaction)
    {
        $this->ensureWithoutRecourseTransaction($company, $factoringTransaction);

        $factoringTransaction->deleteRelations();
        $factoringTransaction->delete();

        return redirect()->back()->with('success', __('Item Has Been Delete Successfully'));
    }

    public function markAsSettled(Company $company, FactoringTransaction $factoringTransaction, Request $request)
    {
        $this->ensureWithoutRecourseTransaction($company, $factoringTransaction);

        if ($factoringTransaction->isSettled()) {
            return redirect()->back()->with('fail', __('This transaction is already settled.'));
        }

        $request->validate([
            'settlement_date' => 'required|date|before_or_equal:today',
        ]);

        $settledDate = Carbon::make(
            parseDatePickerValue($request->input('settlement_date')) ?? $request->input('settlement_date')
        )->format('Y-m-d');

        $invoice = $factoringTransaction->customerInvoice;
        $commentEn = __('Mark As Settled For Invoice #:invoiceNumber', ['invoiceNumber' => $invoice?->invoice_number ?? '']);
        $commentAr = __('Mark As Settled For Invoice #:invoiceNumber', ['invoiceNumber' => $invoice?->invoice_number ?? ''], 'ar');

        DB::transaction(function () use ($company, $factoringTransaction, $settledDate, $commentEn, $commentAr) {
            $factoringTransaction->storeFactoringSettlementStatement(
                $company->id,
                (int) $factoringTransaction->factoring_company_id,
                (int) $factoringTransaction->factoring_contract_id,
                $settledDate,
                (float) $factoringTransaction->received_amount,
                (string) $factoringTransaction->invoice_currency,
                $commentEn,
                $commentAr
            );

            $factoringTransaction->update([
                'is_settled' => true,
                'settled_at' => $settledDate,
                'updated_by' => auth()->id(),
            ]);
        });

        return redirect()->back()->with('success', __('Item Has Been Updated Successfully'));
    }

    public function revertSettlement(Company $company, FactoringTransaction $factoringTransaction)
    {
        $this->ensureWithoutRecourseTransaction($company, $factoringTransaction);

        if (!$factoringTransaction->isSettled()) {
            return redirect()->back()->with('fail', __('This transaction is not settled.'));
        }

        DB::transaction(function () use ($factoringTransaction) {
            $factoringTransaction->deleteFactoringSettlementStatements();
            $factoringTransaction->update([
                'is_settled' => false,
                'settled_at' => null,
                'updated_by' => auth()->id(),
            ]);
        });

        return redirect()->back()->with('success', __('Item Has Been Updated Successfully'));
    }

    public function markDifferenceReceived(Company $company, FactoringTransaction $factoringTransaction, Request $request)
    {
        $this->ensureWithoutRecourseTransaction($company, $factoringTransaction);

        if ($factoringTransaction->isDifferenceReceived()) {
            return redirect()->back()->with('fail', __('The difference amount has already been recorded.'));
        }

        $differenceAmount = $factoringTransaction->getDifferenceAmount();
        if ($differenceAmount <= 0) {
            return redirect()->back()->with('fail', __('There is no difference amount to record.'));
        }

        $request->validate([
            'difference_received_date' => 'required|date|before_or_equal:today',
            'financial_institution_id' => [
                'required',
                Rule::exists('financial_institutions', 'id')->where('company_id', $company->id),
            ],
            'account_type_id' => 'required|exists:account_types,id',
            'account_number' => 'required|string',
        ]);

        $receivedDate = Carbon::make(
            parseDatePickerValue($request->input('difference_received_date')) ?? $request->input('difference_received_date')
        )->format('Y-m-d');

        $accountType = AccountType::findOrFail($request->input('account_type_id'));
        $invoice = $factoringTransaction->customerInvoice;
        $factoringCompanyName = $factoringTransaction->factoringCompany?->getName() ?? '';
        $invoiceNumber = $invoice?->invoice_number ?? '';
        $commentEn = __('Due From Factoring Company [:companyName] On [Invoice #:invoiceNumber]', [
            'companyName' => $factoringCompanyName,
            'invoiceNumber' => $invoiceNumber,
        ]);
        $commentAr = __('Due From Factoring Company [:companyName] On [Invoice #:invoiceNumber]', [
            'companyName' => $factoringCompanyName,
            'invoiceNumber' => $invoiceNumber,
        ], 'ar');

        DB::transaction(function () use ($company, $factoringTransaction, $request, $receivedDate, $accountType, $differenceAmount, $commentEn, $commentAr) {
            $factoringTransaction->storeBankCreditStatementForDifference(
                $company->id,
                (int) $request->input('financial_institution_id'),
                $accountType,
                $request->input('account_number'),
                $receivedDate,
                $differenceAmount,
                $commentEn,
                $commentAr
            );

            $factoringTransaction->update([
                'is_difference_received' => true,
                'difference_received_date' => $receivedDate,
                'difference_received_amount' => $differenceAmount,
                'difference_financial_institution_id' => $request->input('financial_institution_id'),
                'difference_account_type_id' => $accountType->id,
                'difference_account_number' => $request->input('account_number'),
                'updated_by' => auth()->id(),
            ]);
        });

        return redirect()->back()->with('success', __('Item Has Been Updated Successfully'));
    }

    public function revertDifferenceReceived(Company $company, FactoringTransaction $factoringTransaction)
    {
        $this->ensureWithoutRecourseTransaction($company, $factoringTransaction);

        if (!$factoringTransaction->isDifferenceReceived()) {
            return redirect()->back()->with('fail', __('The difference amount has not been recorded.'));
        }

        DB::transaction(function () use ($factoringTransaction) {
            $factoringTransaction->deleteDifferenceReceivedBankStatements();
            $factoringTransaction->update([
                'is_difference_received' => false,
                'difference_received_date' => null,
                'difference_received_amount' => null,
                'difference_financial_institution_id' => null,
                'difference_account_type_id' => null,
                'difference_account_number' => null,
                'updated_by' => auth()->id(),
            ]);
        });

        return redirect()->back()->with('success', __('Item Has Been Updated Successfully'));
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
                'remaining_limit' => $contract->getRemainingLimit($request->integer('except_factoring_transaction_id') ?: null),
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

        $exceptTransactionId = $request->integer('except_factoring_transaction_id') ?: null;
        $remainingLimit = $contract->getRemainingLimit($exceptTransactionId);

        return response()->json([
            'status' => true,
            'invoice_amount' => (float) $invoice->getNetInvoiceAmount(),
            'invoice_due_date' => $invoiceDueDate,
            'remaining_limit' => $remainingLimit,
            'remaining_limit_formatted' => number_format($remainingLimit, 2),
            ...$amounts,
        ]);
    }

    protected function syncFactoringDisbursementStatement(
        FactoringTransaction $transaction,
        Company $company,
        string $date,
        float $creditAmount,
        CustomerInvoice $invoice
    ): void {
        $transaction->deleteFactoringStatements();

        $commentEn = __('Factoring Disbursement For Invoice #:invoiceNumber', ['invoiceNumber' => $invoice->getInvoiceNumber()]);
        $commentAr = __('Factoring Disbursement For Invoice #:invoiceNumber', ['invoiceNumber' => $invoice->getInvoiceNumber()], 'ar');

        $transaction->storeFactoringDisbursementStatement(
            $company->id,
            (int) $transaction->factoring_company_id,
            (int) $transaction->factoring_contract_id,
            $date,
            $creditAmount,
            (string) $transaction->invoice_currency,
            $commentEn,
            $commentAr
        );
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
            'mode' => 'create',
            'model' => null,
            'contracts' => [],
            'recourseType' => FactoringTransaction::WITHOUT_RECOURSE,
            'pageTitle' => 'Factoring Without Recourse',
            'company' => ['id' => $company->id, 'name' => $company->getName()],
            'factoringCompanies' => collect($company->factoringCompanies()->orderBy('name')->pluck('name', 'id'))
                ->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
            'customers' => collect(Partner::onlyCustomers()->where('company_id', $company->id)->orderBy('name')->pluck('name', 'id'))
                ->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
            'financialInstitutionBanks' => FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get()
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->getName()])->values(),
            'accountTypes' => AccountType::onlyCashAccounts()->get()
                ->map(fn ($a) => ['id' => $a->id, 'name' => $a->getName()])->values(),
            'urls' => [
                'store' => route('factoring.without-recourse.store', ['company' => $company->id]),
                'back' => route('factoring.without-recourse.index', ['company' => $company->id]),
                'getContracts' => $this->companyScopedUrl($company, 'factoring/without-recourse/contracts'),
                'getInvoiceCurrencies' => $this->companyScopedUrl($company, 'factoring/without-recourse/currencies'),
                'getInvoices' => $this->companyScopedUrl($company, 'factoring/without-recourse/invoices'),
                'calculate' => route('factoring.without-recourse.calculate', ['company' => $company->id]),
                'getAccountNumbersForType' => $this->companyScopedUrl($company, 'money-received/get-account-numbers-based-on-account-type'),
            ],
        ];
    }

    protected function companyScopedUrl(Company $company, string $path): string
    {
        return url('/'.app()->getLocale().'/'.$company->id.'/'.ltrim($path, '/'));
    }
}
