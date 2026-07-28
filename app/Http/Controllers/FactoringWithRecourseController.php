<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFactoringWithRecourseRequest;
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
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * FactoringWithRecourseController
 * ------------------------------------------------------------------
 * "Factoring With Recourse" — the company sells a customer invoice to
 * a factoring company for immediate cash, but stays on the hook: if
 * the customer never pays, the invoice comes back to the company
 * (Reject) and must be repaid to the factoring company. If the
 * customer does pay (Collect), any shortfall between the amount
 * disbursed and the invoice's real value is recorded too. Both
 * outcomes are independently revertible. No Odoo write, no DB
 * triggers — the model itself already documents a real, confirmed,
 * already-fixed Carbon 3 sign bug in calculateAmounts() (diffInDays).
 *
 * ── Frontend migration status ───────────────────────────────────
 *   index()          → Inertia::render, Pages/FactoringWithRecourse/Index.vue
 *   create()/edit()  → Inertia::render, Pages/FactoringWithRecourse/Form.vue
 *                       (shared with Without Recourse via a `recourseType` prop —
 *                       the original create/edit blade forms are byte-for-byte
 *                       identical apart from labels/routes)
 *   store()/update() → real Laravel redirects (Inertia-compatible), was raw
 *                       JSON ({redirectTo}) for the old jQuery/AJAX form.
 *   destroy()/markCollected()/revertCollected()/markRejected()/revertRejected()
 *                     → unchanged, already redirect()->back(), Inertia-compatible
 *                       as-is (triggered via router.post/delete from Index.vue).
 *   getContracts()/getInvoiceCurrencies()/getInvoices()/calculate()
 *                     → unchanged, pure JSON AJAX endpoints called from the
 *                       Vue form's cascading dropdowns — not Inertia visits.
 */
class FactoringWithRecourseController
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
            ->where('recourse_type', FactoringTransaction::WITH_RECOURSE);

        $transactions = $this->applyFactoringFilter($request, $query)
            ->paginate(self::ROWS_PER_PAGE)
            ->withQueryString();

        $searchFields = $this->factoringSearchFields();

        return Inertia::render('FactoringWithRecourse/Index', [
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
            'canCreate' => hasAuthFor('create supplier payment'),
            'canUpdate' => hasAuthFor('update supplier payment'),
            'canDelete' => hasAuthFor('delete supplier payment'),
            'transactions' => $transactions->through(fn (FactoringTransaction $t) => [
                'id' => $t->id,
                'factoring_date_formatted' => $t->getFactoringDateFormatted(),
                'factoring_company_name' => $t->factoringCompany?->getName(),
                'customer_name' => $t->customer?->getName(),
                'invoice_number' => $t->customerInvoice?->invoice_number,
                'invoice_currency' => $t->invoice_currency,
                'factoring_amount' => (float) $t->factoring_amount,
                'received_amount' => (float) $t->received_amount,
                'is_collected' => (bool) $t->is_collected,
                'is_rejected' => (bool) $t->is_rejected,
                'is_pending' => $t->isPendingWithRecourse(),
                'collection_date' => $t->collection_date,
                'rejection_date' => $t->rejection_date,
                'uncollected_invoice_charges' => (float) $t->uncollected_invoice_charges,
                'difference_amount' => $t->getCollectionDifferenceAmount(),
                'financial_institution_name' => $t->financialInstitution?->getName(),
                'financial_institution_id' => $t->financial_institution_id,
                'account_type_id' => $t->account_type_id,
                'account_number' => $t->account_number,
                'edit_url' => route('factoring.with-recourse.edit', ['company' => $company->id, 'factoringTransaction' => $t->id]),
                'mark_collected_url' => route('factoring.with-recourse.mark-collected', ['company' => $company->id, 'factoringTransaction' => $t->id]),
                'revert_collected_url' => route('factoring.with-recourse.revert-collected', ['company' => $company->id, 'factoringTransaction' => $t->id]),
                'mark_rejected_url' => route('factoring.with-recourse.mark-rejected', ['company' => $company->id, 'factoringTransaction' => $t->id]),
                'revert_rejected_url' => route('factoring.with-recourse.revert-rejected', ['company' => $company->id, 'factoringTransaction' => $t->id]),
                'delete_url' => route('factoring.with-recourse.destroy', ['company' => $company->id, 'factoringTransaction' => $t->id]),
            ])->toArray(),
            'urls' => [
                'create' => route('factoring.with-recourse.create', ['company' => $company->id]),
                'index' => route('factoring.with-recourse.index', ['company' => $company->id]),
            ],
        ]);
    }

    public function create(Company $company)
    {
        return Inertia::render('Factoring/Form', $this->formViewData($company));
    }

    public function edit(Company $company, FactoringTransaction $factoringTransaction)
    {
        $this->ensureWithRecourseTransaction($company, $factoringTransaction);

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
            'is_pending' => $factoringTransaction->isPendingWithRecourse(),
        ];
        $viewData['contracts'] = $contracts->map(fn (FactoringContract $contract) => [
            'id' => $contract->id,
            'label' => $contract->getContractStartDateFormatted() . ' — ' . $contract->getContractEndDateFormatted()
                . ' | ' . strtoupper($contract->getCurrency() ?? '')
                . ' | ' . $contract->getLimitFormatted(),
        ])->values();
        $viewData['urls']['update'] = route('factoring.with-recourse.update', ['company' => $company->id, 'factoringTransaction' => $factoringTransaction->id]);

        return Inertia::render('Factoring/Form', $viewData);
    }

    public function store(Company $company, StoreFactoringWithRecourseRequest $request)
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

        DB::transaction(function () use ($company, $request, $invoice, $contract, $accountType, $factoringDate, $otherCharges, $amounts, $factoringInterestAmount, $receivedAmount) {
            /** @var FactoringTransaction $transaction */
            $transaction = FactoringTransaction::create([
                'company_id' => $company->id,
                'recourse_type' => FactoringTransaction::WITH_RECOURSE,
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

            $this->syncFactoringDisbursementStatement($transaction, $company, $factoringDate, $receivedAmount, $invoice);
        });

        return redirect()->route('factoring.with-recourse.index', ['company' => $company->id])
            ->with('success', __('Data Store Successfully'));
    }

    public function update(Company $company, FactoringTransaction $factoringTransaction, StoreFactoringWithRecourseRequest $request)
    {
        $this->ensureWithRecourseTransaction($company, $factoringTransaction);
        abort_if(!$factoringTransaction->isPendingWithRecourse(), 422, __('Collected or rejected factoring transactions cannot be edited.'));

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

            $this->syncFactoringDisbursementStatement($factoringTransaction, $company, $factoringDate, $receivedAmount, $invoice);
        });

        return redirect()->route('factoring.with-recourse.index', ['company' => $company->id])
            ->with('success', __('Item Has Been Updated Successfully'));
    }

    public function destroy(Company $company, FactoringTransaction $factoringTransaction)
    {
        $this->ensureWithRecourseTransaction($company, $factoringTransaction);

        $factoringTransaction->deleteRelations();
        $factoringTransaction->delete();

        return redirect()->back()->with('success', __('Item Has Been Delete Successfully'));
    }

    public function markCollected(Company $company, FactoringTransaction $factoringTransaction, Request $request)
    {
        $this->ensureWithRecourseTransaction($company, $factoringTransaction);

        if (!$factoringTransaction->isPendingWithRecourse()) {
            return redirect()->back()->with('fail', __('This transaction cannot be collected.'));
        }

        $differenceAmount = $factoringTransaction->getCollectionDifferenceAmount();

        $request->validate([
            'collection_date' => 'required|date|before_or_equal:today',
            'financial_institution_id' => [
                'required',
                Rule::exists('financial_institutions', 'id')->where('company_id', $company->id),
            ],
            'account_type_id' => 'required|exists:account_types,id',
            'account_number' => 'required|string',
        ]);

        $collectionDate = Carbon::make(
            parseDatePickerValue($request->input('collection_date')) ?? $request->input('collection_date')
        )->format('Y-m-d');

        $accountType = AccountType::findOrFail($request->input('account_type_id'));
        $invoice = $factoringTransaction->customerInvoice;
        $factoringCompanyName = $factoringTransaction->factoringCompany?->getName() ?? '';
        $invoiceNumber = $invoice?->invoice_number ?? '';

        $bankCommentEn = __('Due From Factoring Company [:companyName] On [Invoice #:invoiceNumber]', [
            'companyName' => $factoringCompanyName,
            'invoiceNumber' => $invoiceNumber,
        ]);
        $bankCommentAr = __('Due From Factoring Company [:companyName] On [Invoice #:invoiceNumber]', [
            'companyName' => $factoringCompanyName,
            'invoiceNumber' => $invoiceNumber,
        ], 'ar');

        $settlementCommentEn = __('Factoring With Recourse Collection For Invoice #:invoiceNumber', ['invoiceNumber' => $invoiceNumber]);
        $settlementCommentAr = __('Factoring With Recourse Collection For Invoice #:invoiceNumber', ['invoiceNumber' => $invoiceNumber], 'ar');

        DB::transaction(function () use ($company, $factoringTransaction, $request, $collectionDate, $accountType, $differenceAmount, $invoice, $bankCommentEn, $bankCommentAr, $settlementCommentEn, $settlementCommentAr) {
            if ($differenceAmount > 0) {
                $factoringTransaction->storeBankCreditStatementForCollection(
                    $company->id,
                    (int) $request->input('financial_institution_id'),
                    $accountType,
                    $request->input('account_number'),
                    $collectionDate,
                    $differenceAmount,
                    $bankCommentEn,
                    $bankCommentAr
                );
            }

            $settlement = Settlement::create([
                'invoice_id' => $invoice->id,
                'partner_id' => $factoringTransaction->customer_id,
                'settlement_amount' => $invoice->getNetInvoiceAmount(),
                'withhold_amount' => 0,
                'company_id' => $company->id,
                'factoring_transaction_id' => $factoringTransaction->id,
                'is_from_down_payment' => 0,
            ]);

            $factoringTransaction->storeFactoringSettlementStatement(
                $company->id,
                (int) $factoringTransaction->factoring_company_id,
                (int) $factoringTransaction->factoring_contract_id,
                $collectionDate,
                (float) $factoringTransaction->received_amount,
                (string) $factoringTransaction->invoice_currency,
                $settlementCommentEn,
                $settlementCommentAr
            );

            $factoringTransaction->update([
                'is_collected' => true,
                'collected_at' => $collectionDate,
                'collection_date' => $collectionDate,
                'collection_difference_amount' => $differenceAmount > 0 ? $differenceAmount : null,
                'collection_financial_institution_id' => $request->input('financial_institution_id'),
                'collection_account_type_id' => $accountType->id,
                'collection_account_number' => $request->input('account_number'),
                'settlement_id' => $settlement->id,
                'updated_by' => auth()->id(),
            ]);
        });

        return redirect()->back()->with('success', __('Item Has Been Updated Successfully'));
    }

    public function revertCollected(Company $company, FactoringTransaction $factoringTransaction)
    {
        $this->ensureWithRecourseTransaction($company, $factoringTransaction);

        if (!$factoringTransaction->isCollected()) {
            return redirect()->back()->with('fail', __('This transaction has not been collected.'));
        }

        DB::transaction(function () use ($factoringTransaction) {
            $factoringTransaction->deleteCollectionBankStatements();
            $factoringTransaction->deleteFactoringSettlementStatements();
            if ($factoringTransaction->settlement) {
                $factoringTransaction->settlement->delete();
            }

            $factoringTransaction->update([
                'is_collected' => false,
                'collected_at' => null,
                'collection_date' => null,
                'collection_difference_amount' => null,
                'collection_financial_institution_id' => null,
                'collection_account_type_id' => null,
                'collection_account_number' => null,
                'settlement_id' => null,
                'updated_by' => auth()->id(),
            ]);
        });

        return redirect()->back()->with('success', __('Item Has Been Updated Successfully'));
    }

    public function markRejected(Company $company, FactoringTransaction $factoringTransaction, Request $request)
    {
        $this->ensureWithRecourseTransaction($company, $factoringTransaction);

        if (!$factoringTransaction->isPendingWithRecourse()) {
            return redirect()->back()->with('fail', __('This transaction cannot be rejected.'));
        }

        $request->validate([
            'rejection_date' => 'required|date|before_or_equal:today',
            'uncollected_invoice_charges' => 'required|numeric|min:0',
            'financial_institution_id' => [
                'required',
                Rule::exists('financial_institutions', 'id')->where('company_id', $company->id),
            ],
            'account_type_id' => 'required|exists:account_types,id',
            'account_number' => 'required|string',
        ]);

        $rejectionDate = Carbon::make(
            parseDatePickerValue($request->input('rejection_date')) ?? $request->input('rejection_date')
        )->format('Y-m-d');

        $accountType = AccountType::findOrFail($request->input('account_type_id'));
        $invoice = $factoringTransaction->customerInvoice;
        $factoringCompanyName = $factoringTransaction->factoringCompany?->getName() ?? '';
        $invoiceNumber = $invoice?->invoice_number ?? '';
        $paymentAmount = (float) $factoringTransaction->factoring_amount;
        $uncollectedInvoiceCharges = (float) unformat_number($request->input('uncollected_invoice_charges'));

        $bankCommentEn = __('Payment To Factoring Company [:companyName] For [Invoice #:invoiceNumber]', [
            'companyName' => $factoringCompanyName,
            'invoiceNumber' => $invoiceNumber,
        ]);
        $bankCommentAr = __('Payment To Factoring Company [:companyName] For [Invoice #:invoiceNumber]', [
            'companyName' => $factoringCompanyName,
            'invoiceNumber' => $invoiceNumber,
        ], 'ar');

        $statementCommentEn = __('Factoring With Recourse Rejection For Invoice #:invoiceNumber', ['invoiceNumber' => $invoiceNumber]);
        $statementCommentAr = __('Factoring With Recourse Rejection For Invoice #:invoiceNumber', ['invoiceNumber' => $invoiceNumber], 'ar');

        DB::transaction(function () use ($company, $factoringTransaction, $request, $rejectionDate, $accountType, $paymentAmount, $uncollectedInvoiceCharges, $bankCommentEn, $bankCommentAr, $statementCommentEn, $statementCommentAr) {
            $factoringTransaction->storeBankDebitStatementForRejection(
                $company->id,
                (int) $request->input('financial_institution_id'),
                $accountType,
                $request->input('account_number'),
                $rejectionDate,
                $paymentAmount,
                $bankCommentEn,
                $bankCommentAr
            );

            $factoringTransaction->storeFactoringRejectionStatement(
                $company->id,
                (int) $factoringTransaction->factoring_company_id,
                (int) $factoringTransaction->factoring_contract_id,
                $rejectionDate,
                $paymentAmount,
                (string) $factoringTransaction->invoice_currency,
                $statementCommentEn,
                $statementCommentAr
            );

            $factoringTransaction->update([
                'is_rejected' => true,
                'rejected_at' => $rejectionDate,
                'rejection_date' => $rejectionDate,
                'rejection_financial_institution_id' => $request->input('financial_institution_id'),
                'rejection_account_type_id' => $accountType->id,
                'rejection_account_number' => $request->input('account_number'),
                'uncollected_invoice_charges' => $uncollectedInvoiceCharges,
                'updated_by' => auth()->id(),
            ]);
        });

        return redirect()->back()->with('success', __('Item Has Been Updated Successfully'));
    }

    public function revertRejected(Company $company, FactoringTransaction $factoringTransaction)
    {
        $this->ensureWithRecourseTransaction($company, $factoringTransaction);

        if (!$factoringTransaction->isRejected()) {
            return redirect()->back()->with('fail', __('This transaction has not been rejected.'));
        }

        DB::transaction(function () use ($factoringTransaction) {
            $factoringTransaction->deleteRejectionBankStatements();
            $factoringTransaction->deleteFactoringRejectionStatements();
            $factoringTransaction->update([
                'is_rejected' => false,
                'rejected_at' => null,
                'rejection_date' => null,
                'rejection_financial_institution_id' => null,
                'rejection_account_type_id' => null,
                'rejection_account_number' => null,
                'uncollected_invoice_charges' => null,
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
            ->where('recourse_type', FactoringContract::WITH_RECOURSE)
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

    protected function ensureWithRecourseTransaction(Company $company, FactoringTransaction $factoringTransaction): void
    {
        abort_unless(
            $factoringTransaction->company_id === $company->id
                && $factoringTransaction->recourse_type === FactoringTransaction::WITH_RECOURSE,
            404
        );
    }

    protected function formViewData(Company $company): array
    {
        return [
            'mode' => 'create',
            'model' => null,
            'contracts' => [],
            'recourseType' => FactoringTransaction::WITH_RECOURSE,
            'pageTitle' => 'Factoring With Recourse',
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
                'store' => route('factoring.with-recourse.store', ['company' => $company->id]),
                'back' => route('factoring.with-recourse.index', ['company' => $company->id]),
                'getContracts' => $this->companyScopedUrl($company, 'factoring/with-recourse/contracts'),
                'getInvoiceCurrencies' => $this->companyScopedUrl($company, 'factoring/with-recourse/currencies'),
                'getInvoices' => $this->companyScopedUrl($company, 'factoring/with-recourse/invoices'),
                'calculate' => route('factoring.with-recourse.calculate', ['company' => $company->id]),
                'getAccountNumbersForType' => $this->companyScopedUrl($company, 'money-received/get-account-numbers-based-on-account-type'),
            ],
        ];
    }

    protected function companyScopedUrl(Company $company, string $path): string
    {
        return url('/'.app()->getLocale().'/'.$company->id.'/'.ltrim($path, '/'));
    }
}
