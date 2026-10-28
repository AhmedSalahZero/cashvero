<?php
namespace App\Http\Controllers;

use App\Support\Instructions\PageInstructions;
use App\Http\Controllers\MoneyReceivedController;
use App\Http\Requests\StoreMediumTermLoanRequest;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionAccount;
use App\Models\LoanSchedule;
use App\Models\LoanScheduleSettlement;
use App\Models\MediumTermLoan;
use App\Models\MediumTermLoanEarlySettlement;
use App\Rules\DateMustBeLessThanOrEqualDate;
use App\Support\ShareholderAccounts\AccountNumberLabel;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * MediumTermLoanController
 * ------------------------------------------------------------------
 * Manages Medium Term Loan — unlike LG/LC, this is a single
 * self-contained entity (no separate Facility + Issuance split): one
 * record per loan, with an attached amortization schedule
 * (loan_schedules) and per-installment payment settlements
 * (LoanScheduleSettlement).
 *
 * ⚠️ update() does NOT actually update in place — it deletes the loan
 * and its schedule relations, then calls store() fresh. Same
 * confirmed, deliberate pattern already seen on LG/LC Issuance. Left
 * completely untouched.
 *
 * The "Upload Loan Schedule & Apply Payments" action on the list page
 * links to a GENERIC, already-migrated upload/import system
 * (SalesGatheringController + SalesGatheringTestController → renders
 * resources/js/Pages/InvoiceUpload/*.vue), shared across several
 * unrelated features (Customer/Supplier Invoice, Contract Loan
 * Schedule, etc.) — confirmed already Inertia-migrated from a prior
 * session, with LoanSchedule already wired in. Nothing new needed
 * here beyond linking to it correctly.
 *
 * The Loan Schedule Settlement screen (viewLoanScheduleSettlement /
 * editLoanScheduleSettlement) is rendered by a Blade view SHARED with
 * a different, unrelated feature (Contract Loan Schedule, via its own
 * separate ContractLoanScheduleController). Only the
 * MediumTermLoan-owned path is migrated here — this controller now
 * renders its own dedicated Vue page
 * (LoanScheduleSettlement/Index.vue) instead of the shared Blade
 * view. ContractLoanScheduleController is UNCHANGED and keeps
 * rendering the original shared Blade view for its own path —
 * deliberately out of scope for this pass.
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   ✅ index() / create() / edit() → MIGRATED to Vue + Inertia.
 *      Renders resources/js/Pages/MediumTermLoan/Index.vue and
 *      .../Form.vue.
 *   ✅ viewLoanScheduleSettlement() / editLoanScheduleSettlement() →
 *      MIGRATED to Vue + Inertia. Renders
 *      resources/js/Pages/LoanScheduleSettlement/Index.vue.
 *   ✅ store() / update() / destroy() / storeLoanScheduleSettlement() /
 *      updateLoanScheduleSettlement() / deleteLoanScheduleSettlement()
 *      / refreshReport() / getMediumTermLoanForFinancialInstitution()
 *      → UNCHANGED, deliberately. All already redirect or JSON-
 *      appropriately.
 *   ⚠️ Confirmed real bug, not fixed here without a decision first:
 *      Account Number on the loan itself is a free-text field, not a
 *      linked FinancialInstitutionAccount — matches the original
 *      exactly (account_number is a plain varchar column, not a
 *      foreign key). The Settlement screen's "Current Account" field
 *      is a genuinely different, separate concept: an actual bank
 *      account you pay the installment FROM.
 */
class MediumTermLoanController
{
    use GeneralFunctions;
    protected function applyFilter(Request $request, Collection $collection): Collection
    {
        if (!count($collection)) {
            return $collection;
        }
        $searchFieldName = $request->get('field');
        $dateFieldName = 'created_at'; // change it
        $from = $request->get('from');
        $to = $request->get('to');
        $value = $request->query('value');
        $collection = $collection
        ->when($request->has('value'), function ($collection) use ($value, $searchFieldName) {
            return $collection->filter(function ($moneyReceived) use ($value, $searchFieldName) {
                $currentValue = $moneyReceived->{$searchFieldName};
                return false !== stristr($currentValue, $value);
            });
        })
        ->when($request->get('from'), function ($collection) use ($dateFieldName, $from) {
            return $collection->where($dateFieldName, '>=', $from);
        })
        ->when($request->get('to'), function ($collection) use ($dateFieldName, $to) {
            return $collection->where($dateFieldName, '<=', $to);
        })
        ->sortByDesc('id')->values();
        return $collection;
    }

    /**
     * The main "Medium Term Loan" list — one flat list per financial
     * institution (only one status, "Running", exists for this
     * model — no tabs needed, matches the original exactly).
     *
     * ✅ MIGRATED to Vue + Inertia. Renders
     * resources/js/Pages/MediumTermLoan/Index.vue.
     */
    public function index(Company $company, Request $request, FinancialInstitution $financialInstitution)
    {
        $currentType = $request->get('active', MediumTermLoan::RUNNING);
        $runningEndDate = $request->has('endDate') ? $request->input('endDate.'.$currentType) : now()->format('Y-m-d');

        $mediumTermLoans = $company->mediumTermLoans->where('financial_institution_id', $financialInstitution->id);
        $mediumTermLoans = $mediumTermLoans->filterByLoanEndDate($runningEndDate);
        $mediumTermLoans = $this->applyFilter($request, $mediumTermLoans);

        return \Inertia\Inertia::render('MediumTermLoan/Index', [
			'instructionsUrl' => route('view.instructions', ['company' => $company->id, 'page' => PageInstructions::MEDIUM_TERM_LOAN, 'financialInstitution' => $financialInstitution->id]),
            'company' => ['id' => $company->id],
            'financialInstitution' => ['id' => $financialInstitution->id, 'name' => $financialInstitution->getName()],
            'canCreate' => hasAuthFor('medium_term_loan.create'),
            'createUrl' => route('loans.create', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
            'rows' => $mediumTermLoans->map(function (MediumTermLoan $loan) use ($company, $financialInstitution) {
                return [
                    'id' => $loan->id,
                    'name' => $loan->getName(),
                    'start_date_formatted' => $loan->getStartDateFormatted(),
                    'end_date_formatted' => $loan->getEndDateFormatted(),
                    'currency_formatted' => $loan->getCurrencyFormatted(),
                    'limit_formatted' => $loan->getLimitFormatted(),
                    'account_number' => AccountNumberLabel::forOwnedInstrument($loan),
                    'borrowing_rate_formatted' => $loan->getBorrowingRateFormatted(),
                    'margin_rate_formatted' => $loan->getMarginRateFormatted(),
                    'duration_formatted' => $loan->getDurationFormatted(),
                    'installment_interval_formatted' => $loan->getPaymentInstallmentIntervalFormatted(),
                    'upload_schedule_url' => route('view.uploading', ['company' => $company->id, 'model' => 'LoanSchedule', 'loanId' => $loan->id]),
                    'statement_url' => route('loans.statement', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'mediumTermLoan' => $loan->id]),
                    'edit_url' => route('loans.edit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'mediumTermLoan' => $loan->id]),
                    'delete_url' => route('loans.destroy', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'mediumTermLoan' => $loan->id]),
                    'currency' => $loan->getCurrency(),
                    'is_early_settled' => $loan->isEarlySettled(),
                    'status_formatted' => $loan->getStatusFormatted(),
                    'early_settlement_preview' => $loan->isEarlySettled() ? null : $this->getEarlySettlementPreviewForView($loan),
                    'early_settlement' => $this->getEarlySettlementForView($company, $loan),
                    'early_settlement_url' => route('loans.early.settlement.store', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'mediumTermLoan' => $loan->id]),
                ];
            })->values(),
            'earlySettlementAccountTypes' => MediumTermLoanEarlySettlement::getAllowedAccountTypes()
                ->map(fn (AccountType $accountType) => ['id' => $accountType->id, 'name' => $accountType->getName()])
                ->values(),
            'earlySettlementAccounts' => $this->getEarlySettlementAccounts($company, $financialInstitution, $mediumTermLoans->map->getCurrency()->unique()->values()->all()),
            'balanceLookupUrl' => route('update.balance.and.net.balance.based.on.account.number', ['company' => $company->id]),
            'canUpload' => hasAuthFor('medium_term_loan.create'),
            'canUpdate' => hasAuthFor('medium_term_loan.update'),
            'canDelete' => hasAuthFor('medium_term_loan.delete'),
            'backUrl' => route('view.financial.institutions', ['company' => $company->id, 'active' => 'bank']),
            'navUrls' => [
                'home' => route('home', ['company' => $company->id]),
                'bank_accounts' => route('view.financial.institutions', ['company' => $company->id, 'active' => 'bank']),
                'customers' => route('partners.index', ['company' => $company->id, 'type' => 'customers']),
                'suppliers' => route('partners.index', ['company' => $company->id, 'type' => 'suppliers']),
                'notifications' => route('view.notifications', ['company' => $company->id, 'type' => 'all']),
            ],
        ]);
    }

    /**
     * What the Early Settlement popup shows before saving: the first open
     * installment, how many are open, and the remaining principle (the
     * amount's default) and interest.
     */
    protected function getEarlySettlementPreviewForView(MediumTermLoan $loan): ?array
    {
        $preview = $loan->getEarlySettlementPreview();
        if (! $preview['open_installments_count']) {
            return null;
        }

        return [
            'first_installment_number' => $preview['first_installment_number'],
            'first_installment_date_formatted' => $preview['first_installment_date_formatted'],
            'open_installments_count' => $preview['open_installments_count'],
            'remaining_principle' => $preview['remaining_principle'],
            'remaining_interest' => $preview['remaining_interest'],
        ];
    }

    protected function getEarlySettlementForView(Company $company, MediumTermLoan $loan): ?array
    {
        $earlySettlement = $loan->earlySettlement;
        if (! $earlySettlement) {
            return null;
        }

        return [
            'id' => $earlySettlement->id,
            'date' => Carbon::make($earlySettlement->getDate())->format('Y-m-d'),
            'date_formatted' => $earlySettlement->getDateFormatted(),
            'amount' => (float) $earlySettlement->getAmount(),
            'fee_amount' => (float) $earlySettlement->getFeeAmount(),
            'account_type_id' => (int) $earlySettlement->getAccountTypeId(),
            'account_type_name' => $earlySettlement->accountType?->getName(),
            'account_number' => $earlySettlement->getAccountNumber(),
            'principle_settled' => (float) $earlySettlement->getPrincipleSettled(),
            'interest_waived' => (float) $earlySettlement->getInterestWaived(),
            'update_url' => route('loans.early.settlement.update', ['company' => $company->id, 'financialInstitution' => $loan->getFinancialInstitutionId(), 'mediumTermLoan' => $loan->id]),
            'delete_url' => route('loans.early.settlement.destroy', ['company' => $company->id, 'financialInstitution' => $loan->getFinancialInstitutionId(), 'mediumTermLoan' => $loan->id]),
        ];
    }

    /**
     * Accounts the early settlement can be paid from, keyed
     * [currency][accountTypeId] => [{value, label}] — same lookup as
     * CashExpenseController::getAccountNumbersForAccountType(), done once
     * per currency on the page instead of over AJAX.
     */
    protected function getEarlySettlementAccounts(Company $company, FinancialInstitution $financialInstitution, array $currencies): array
    {
        $accountTypes = MediumTermLoanEarlySettlement::getAllowedAccountTypes();
        $result = [];
        foreach ($currencies as $currency) {
            foreach ($accountTypes as $accountType) {
                $accounts = ('\App\Models\\'.$accountType->getModelName())::getAllAccountNumberForCurrency($company->id, $currency, $financialInstitution->id);
                $result[$currency][$accountType->id] = collect($accounts)
                    ->map(fn ($label, $accountNumber) => ['value' => (string) $accountNumber, 'label' => (string) $label])
                    ->values()
                    ->all();
            }
        }

        return $result;
    }

    /**
     * MTL Statement — the loan's own statement, in two halves.
     *
     * Requested by the project owner (2026-08-16): "لازم نفصل بين الفايدة
     * اللي انا كاتبها والفايدة اللي انا دافعها ... نشوف اصل ال interest كام
     * واندفع منها كام واصل القسط كام واندفع منه كام."
     *
     *   1. Repayment breakdown — per installment and in total: interest
     *      DUE vs interest PAID vs remaining, and the same for principle.
     *      Computed straight off loan_schedules + settlements, so it works
     *      for every loan, including "existing" ones that were never drawn
     *      through CashVero and therefore own no facility ledger at all.
     *
     *   2. Facility ledger — only for a loan that IS drawn from here:
     *      drawdowns (credit), installment repayments (principle in debit,
     *      interest recorded alongside but deliberately outside the balance
     *      formula), running balance and room.
     *
     * Read-only. No new financial logic lives here — the allocation rule is
     * the single one in LoanScheduleSettlement, reused by both halves so
     * the ledger and the breakdown can never disagree.
     */
    public function statement(Company $company, FinancialInstitution $financialInstitution, MediumTermLoan $mediumTermLoan)
    {
        $breakdown = $mediumTermLoan->getRepaymentBreakdown();
        $isPayableFacility = $mediumTermLoan->isNotConsumedYet();

        $ledger = $isPayableFacility
            ? $mediumTermLoan->bankStatements()->orderByRaw('full_date asc , id asc')->get()
            : collect();

        return \Inertia\Inertia::render('MediumTermLoan/Statement', [
			'instructionsUrl' => route('view.instructions', ['company' => $company->id, 'page' => PageInstructions::MTL_STATEMENT, 'financialInstitution' => $financialInstitution->id]),
            'company' => ['id' => $company->id],
            'financialInstitution' => ['id' => $financialInstitution->id, 'name' => $financialInstitution->getName()],
            'loan' => [
                'id' => $mediumTermLoan->id,
                'name' => $mediumTermLoan->getName(),
                'currency_formatted' => $mediumTermLoan->getCurrencyFormatted(),
                'limit' => (float) $mediumTermLoan->getLimit(),
                'account_number' => AccountNumberLabel::forOwnedInstrument($mediumTermLoan),
                'start_date_formatted' => $mediumTermLoan->getStartDateFormatted(),
                'end_date_formatted' => $mediumTermLoan->getEndDateFormatted(),
                'interest_rate_formatted' => number_format($mediumTermLoan->getInterestRate(), 2).' %',
                'consumption_status' => $mediumTermLoan->getConsumptionStatus(),
                'is_payable_facility' => $isPayableFacility,
                // Drawn is derived from room so it can never disagree with the
                // ledger's own trigger-computed figure.
                'available_room' => $isPayableFacility ? $mediumTermLoan->getAvailableRoomAt() : 0.0,
                'drawn' => $isPayableFacility
                    ? (float) $mediumTermLoan->getLimit() - $mediumTermLoan->getAvailableRoomAt()
                    : 0.0,
            ],
            'breakdown' => $breakdown['rows'],
            'totals' => $breakdown['totals'],
            'earlySettlement' => $this->getEarlySettlementForView($company, $mediumTermLoan),
            'ledger' => $ledger->map(function (\App\Models\MediumTermLoanBankStatement $row) use ($company) {
                return [
                    'id' => $row->id,
                    'date_formatted' => $row->date ? \Carbon\Carbon::make($row->date)->format('d-m-Y') : __('N/A'),
                    'type' => $row->type,
                    'is_repayment' => in_array($row->type, [\App\Models\MediumTermLoanBankStatement::INSTALLMENT_REPAYMENT, \App\Models\MediumTermLoanBankStatement::EARLY_SETTLEMENT], true),
                    'beginning_balance' => (float) $row->beginning_balance,
                    'debit' => (float) $row->debit,
                    'credit' => (float) $row->credit,
                    'interest_amount' => (float) $row->interest_amount,
                    'end_balance' => (float) $row->end_balance,
                    'room' => (float) $row->room,
                    'comment' => AccountNumberLabel::decorateText(
                        (int) $company->id,
                        $row->{'comment_'.app()->getLocale()} ?: $row->comment_en
                    ),
                ];
            })->values(),
            'backUrl' => route('loans.index', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
            'navUrls' => [
                'home' => route('home', ['company' => $company->id]),
                'bank_accounts' => route('view.financial.institutions', ['company' => $company->id, 'active' => 'bank']),
                'customers' => route('partners.index', ['company' => $company->id, 'type' => 'customers']),
                'suppliers' => route('partners.index', ['company' => $company->id, 'type' => 'suppliers']),
                'notifications' => route('view.notifications', ['company' => $company->id, 'type' => 'all']),
            ],
        ]);
    }

    /**
     * Shows the "Add Medium Term Loan" form.
     *
     * ✅ MIGRATED to Vue + Inertia — shares the same page component as
     * edit() (resources/js/Pages/MediumTermLoan/Form.vue),
     * distinguished by the `mode: 'create'` prop.
     */
    public function create(Company $company, FinancialInstitution $financialInstitution)
    {
        return \Inertia\Inertia::render('MediumTermLoan/Form', [
			'instructionsUrl' => route('view.instructions', ['company' => $company->id, 'page' => PageInstructions::MEDIUM_TERM_LOAN_FORM, 'financialInstitution' => $financialInstitution->id]),
            'mode' => 'create',
            'company' => ['id' => $company->id],
            'financialInstitution' => ['id' => $financialInstitution->id, 'name' => $financialInstitution->getName()],
            'currencies' => getCurrencies(),
            'installmentIntervals' => \App\Helpers\HVero::getDurationIntervalTypesForSelect(),
            'consumptionStatuses' => MediumTermLoan::getConsumptionStatusesForSelect(),
            'hasOdoo' => $company->hasOdooCredentials(),
            'isConsumptionLocked' => false,
            'isLocked' => false,
            // Shareholder ownership control — docs/shareholder-accounts.md.
            // A loan flagged as an owner's is filtered exactly like their
            // other accounts (decision D1): ownership, nothing else.
            ...\App\Support\ShareholderAccounts\ShareholderAccountAccess::formProps($company->id),
            'model' => null,
            'submitUrl' => route('loans.store', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
            'backUrl' => route('loans.index', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
            'navUrls' => [
                'home' => route('home', ['company' => $company->id]),
                'bank_accounts' => route('view.financial.institutions', ['company' => $company->id, 'active' => 'bank']),
                'customers' => route('partners.index', ['company' => $company->id, 'type' => 'customers']),
                'suppliers' => route('partners.index', ['company' => $company->id, 'type' => 'suppliers']),
                'notifications' => route('view.notifications', ['company' => $company->id, 'type' => 'all']),
            ],
        ]);
    }

    /**
     * Stores a new Medium Term Loan.
     *
     * The Odoo sync at the end is the only addition — see
     * syncLoanWithOdoo() for why it is now required.
     */
    public function store(Company $company, StoreMediumTermLoanRequest $request, FinancialInstitution $financialInstitution)
    {
        $type = MediumTermLoan::RUNNING;
        $mediumTermLoan = new MediumTermLoan;
        $mediumTermLoan->status = MediumTermLoan::RUNNING;

        $ownership = \App\Support\ShareholderAccounts\ShareholderAccountAccess::ownershipFromRequest($request);
        if ($ownership === []) {
            $ownership = [
                'is_shareholder_account' => false,
                'shareholder_partner_id' => null,
            ];
        }
        $mediumTermLoan->forceFill($ownership);
        $mediumTermLoan->storeBasicForm($request, ['_token', 'save', '_method', 'is_shareholder_account', 'shareholder_partner_id']);
        $activeTab = $type;

        $odooSyncFailureMessage = $this->syncLoanWithOdoo($company, $mediumTermLoan);

        $redirect = redirect()->route('loans.index', ['company' => $company->id, 'active' => $activeTab, 'financialInstitution' => $financialInstitution->id]);

        if ($odooSyncFailureMessage) {
            return $redirect->with('fail', $odooSyncFailureMessage);
        }

        return $redirect->with('success', __('Data Store Successfully'));
    }

    /**
     * Resolves the loan's Odoo Code against Odoo's chart of accounts and
     * writes back journal_id / odoo_id / the payment-method ids.
     *
     * Required because the loan is now a paying account: when a settlement
     * syncs, OdooPayment::createPayment() asks the paying account for its
     * journal via MoneyPayment::getBankAccountJournalId(), which is typed
     * `: int`. A loan with no journal_id therefore doesn't just sync
     * badly — it throws a TypeError and the payment fails. Bank accounts
     * never hit this because their Odoo Code has always been synced on
     * save; this gives the loan the identical treatment.
     *
     * Mirrors FinancialInstitutionAccountController::syncAccountWithOdoo()
     * deliberately, including its messages: the loan itself is already
     * saved by this point, so a sync failure is reported, never fatal.
     *
     * Note update() re-enters store(), so this covers editing too.
     *
     * @return string|null error message, or null when everything is fine
     */
    private function syncLoanWithOdoo(Company $company, MediumTermLoan $mediumTermLoan): ?string
    {
        if (! $company->hasOdooCredentials()) {
            return null;
        }

        if (! $company->hasOdooIntegrationCredentials()) {
            return __('The Account Has Been Saved But It Was Not Synced With Odoo Because The Current User Has No Odoo Username Or Password');
        }

        $odooCode = $mediumTermLoan->getOdooCode();
        if (! $odooCode) {
            return null;
        }

        try {
            $isSynced = (new \App\Services\Api\OdooService($company))->syncFinancialInstitutions($mediumTermLoan);
        } catch (\Throwable $exception) {
            report($exception);

            return __('The Account Has Been Saved But Syncing With Odoo Failed').' : '.$exception->getMessage();
        }

        if (! $isSynced) {
            return __('The Account Has Been Saved But The Odoo Code Was Not Found In Odoo Chart Of Accounts').' : '.$odooCode;
        }

        return null;
    }

    /**
     * Shows the "Edit Medium Term Loan" form.
     *
     * ✅ MIGRATED to Vue + Inertia — shares the same page component as
     * create() (resources/js/Pages/MediumTermLoan/Form.vue),
     * distinguished by the `mode: 'edit'` prop.
     */
    public function edit(Company $company, FinancialInstitution $financialInstitution, MediumTermLoan $mediumTermLoan)
    {
        // ⚠️ Confirmed, deliberate lock: update() (below) works by
        // deleting the loan and its schedule relations, then recreating
        // from scratch. If a schedule has real settlements against it
        // already, that would silently destroy settlement/bank-statement
        // history. Per the project owner's decision, once a schedule
        // exists the facility is locked — the user must explicitly
        // delete the schedule (destroySchedule(), a new, separate
        // action) before editing is allowed again.
        $isLocked = $mediumTermLoan->loanSchedules()->exists();

        // Separate, narrower lock than $isLocked above: once a supplier has
        // actually been paid out of this loan, flipping it back to
        // "existing" would strand those payments against an account the
        // system no longer considers payable. The rest of the form stays
        // editable (subject to $isLocked); only this one field freezes.
        $isConsumptionLocked = $mediumTermLoan->hasDrawdowns();

        return \Inertia\Inertia::render('MediumTermLoan/Form', [
			'instructionsUrl' => route('view.instructions', ['company' => $company->id, 'page' => PageInstructions::MEDIUM_TERM_LOAN_FORM, 'financialInstitution' => $financialInstitution->id]),
            'mode' => 'edit',
            'company' => ['id' => $company->id],
            'financialInstitution' => ['id' => $financialInstitution->id, 'name' => $financialInstitution->getName()],
            'currencies' => getCurrencies(),
            'installmentIntervals' => \App\Helpers\HVero::getDurationIntervalTypesForSelect(),
            'consumptionStatuses' => MediumTermLoan::getConsumptionStatusesForSelect(),
            'hasOdoo' => $company->hasOdooCredentials(),
            'isConsumptionLocked' => $isConsumptionLocked,
            'isLocked' => $isLocked,
            // Shareholder ownership control — docs/shareholder-accounts.md
            ...\App\Support\ShareholderAccounts\ShareholderAccountAccess::formProps($company->id),
            'model' => [
                'id' => $mediumTermLoan->id,
                'name' => $mediumTermLoan->getName(),
                'start_date' => $mediumTermLoan->getStartDate(),
                'end_date' => $mediumTermLoan->getEndDate(),
                'currency' => $mediumTermLoan->getCurrency(),
                'limit' => $mediumTermLoan->getLimit(),
                'account_number' => $mediumTermLoan->getAccountNumber(),
                'borrowing_rate' => $mediumTermLoan->getBorrowingRate(),
                'margin_rate' => $mediumTermLoan->getMarginRate(),
                'duration' => $mediumTermLoan->getDuration(),
                'installment_payment_interval' => $mediumTermLoan->getPaymentInstallmentInterval(),
                'already_paid_amount' => (float) $mediumTermLoan->already_paid_amount,
                'first_installment_date' => $mediumTermLoan->first_installment_date,
                'remaining_installment_count' => $mediumTermLoan->remaining_installment_count,
                'consumption_status' => $mediumTermLoan->getConsumptionStatus(),
                'odoo_code' => $mediumTermLoan->getOdooCode(),
                'available_room_formatted' => $mediumTermLoan->getAvailableRoomAtFormatted(),
            ] + \App\Support\ShareholderAccounts\ShareholderAccountAccess::modelProps($mediumTermLoan),
            'submitUrl' => route('loans.update', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'mediumTermLoan' => $mediumTermLoan->id]),
            'deleteScheduleUrl' => route('loans.schedule.destroy', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'mediumTermLoan' => $mediumTermLoan->id]),
            'backUrl' => route('loans.index', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
            'navUrls' => [
                'home' => route('home', ['company' => $company->id]),
                'bank_accounts' => route('view.financial.institutions', ['company' => $company->id, 'active' => 'bank']),
                'customers' => route('partners.index', ['company' => $company->id, 'type' => 'customers']),
                'suppliers' => route('partners.index', ['company' => $company->id, 'type' => 'suppliers']),
                'notifications' => route('view.notifications', ['company' => $company->id, 'type' => 'all']),
            ],
        ]);
    }

    /**
     * Deletes the loan and calls store() fresh, in-place — same
     * delete-then-recreate pattern as before. UPDATED: now blocked
     * once a schedule exists (see destroySchedule() below for why).
     */
    public function update(Company $company, StoreMediumTermLoanRequest $request, FinancialInstitution $financialInstitution, MediumTermLoan $mediumTermLoan)
    {
        // Server-side enforcement of the same lock shown in edit() — a
        // disabled form in the UI isn't a guarantee, this is.
        if ($mediumTermLoan->loanSchedules()->exists()) {
            return redirect()
                ->back()
                ->with('fail', __('This loan has an uploaded schedule and can\'t be edited. Delete the schedule first if you need to make changes.'));
        }

        // update() works by DELETING this loan and re-creating it (see the
        // method docblock). That is survivable for a loan nobody has drawn
        // from, but a loan that has already paid suppliers owns bank
        // statement rows and Money Payment records keyed to its id —
        // deleting it would strand every one of them. Blocked outright
        // rather than silently corrupting them.
        if ($mediumTermLoan->hasDrawdowns()) {
            return redirect()
                ->back()
                ->with('fail', __('Supplier payments have already been made from this loan, so it can\'t be edited. Delete those payments first if you need to make changes.'));
        }

        $type = MediumTermLoan::RUNNING;

        /**
         * The edit deletes this loan and re-creates it, so without this
         * wrapper the audit trail shows an unrelated delete and create
         * and the loan's history stops at the old row.
         * See App\Support\Activity\ActivityLogger::asUpdate().
         */
        \App\Support\Activity\ActivityLogger::asUpdate($mediumTermLoan, function () use ($company, $request, $financialInstitution, $mediumTermLoan) {
            $mediumTermLoan->deleteRelations();
            $mediumTermLoan->delete();
            $this->store($company, $request, $financialInstitution);
        });
        $activeTab = $type;
        return redirect()->route('loans.index', ['company' => $company->id, 'active' => $activeTab, 'financialInstitution' => $financialInstitution->id])->with('success', __('Item Has Been Updated Successfully'));
    }

    /**
     * Deletes every installment on this loan's schedule, and for each
     * one, every payment settlement recorded against it — reversing
     * each settlement's effect on the bank statement / loan statement
     * exactly as a normal single-settlement delete already does
     * (reuses the existing, already-proven LoanScheduleSettlement::
     * deleteAllRelations() + delete(), just applied to every row).
     * Unlocks the facility for editing again once complete.
     */
    public function destroySchedule(Company $company, FinancialInstitution $financialInstitution, MediumTermLoan $mediumTermLoan)
    {
        if ($response = $this->failIfEarlySettled($mediumTermLoan)) {
            return $response;
        }
        DB::transaction(function () use ($mediumTermLoan) {
            foreach ($mediumTermLoan->loanSchedules as $schedule) {
                foreach ($schedule->settlements as $settlement) {
                    $settlement->deleteAllRelations();
                    $settlement->delete();
                }
                $schedule->delete();
            }
        });

        return redirect()
            ->back()
            ->with('success', __('Schedule deleted. You can now edit this loan or upload a corrected schedule.'));
    }

    /**
     * Deletes a Medium Term Loan and its schedule. UNCHANGED.
     */
    public function destroy(Company $company, FinancialInstitution $financialInstitution, MediumTermLoan $mediumTermLoan)
    {
        // Same reasoning as update(): a loan that has paid suppliers owns
        // statement rows and Money Payment records keyed to its id. Deleting
        // it would leave them pointing at nothing.
        if ($mediumTermLoan->hasDrawdowns()) {
            return redirect()
                ->back()
                ->with('fail', __('Supplier payments have already been made from this loan, so it can\'t be deleted. Delete those payments first.'));
        }
        if ($response = $this->failIfEarlySettled($mediumTermLoan)) {
            return $response;
        }

        $mediumTermLoan->deleteRelations();
        $mediumTermLoan->delete();
        return redirect()->back()->with('success', __('Item Has Been Delete Successfully'));
    }

    /**
     * Shows the payment-settlement screen for one installment row.
     *
     * ✅ MIGRATED to Vue + Inertia for the MediumTermLoan-owned path
     * ONLY — renders resources/js/Pages/LoanScheduleSettlement/Index.vue.
     * ContractLoanScheduleController's own equivalent method is
     * UNCHANGED and keeps rendering the original shared Blade view —
     * deliberately out of scope for this pass.
     */
    public function viewLoanScheduleSettlement(Company $company, LoanSchedule $loanSchedule)
    {
        if (!$loanSchedule->hasMediumTermLoan()) {
            return redirect()
                ->back()
                ->with('warning', __('This loan schedule installment is not linked to an active medium term loan.'));
        }

        return \Inertia\Inertia::render('LoanScheduleSettlement/Index', $this->getSettlementPageVars($company, $loanSchedule, null));
    }

    /**
     * Shows the edit form for an existing settlement, pre-filled.
     *
     * ✅ MIGRATED to Vue + Inertia, same page component as
     * viewLoanScheduleSettlement(), distinguished by a non-null
     * `editingSettlement` prop.
     */
    public function editLoanScheduleSettlement(Company $company, Request $request, LoanScheduleSettlement $loanScheduleSettlement)
    {
        $loanSchedule = $loanScheduleSettlement->loanSchedule;

        if (!$loanSchedule || !$loanSchedule->hasMediumTermLoan()) {
            return redirect()
                ->back()
                ->with('warning', __('This loan schedule installment is not linked to an active medium term loan.'));
        }

        return \Inertia\Inertia::render('LoanScheduleSettlement/Index', $this->getSettlementPageVars($company, $loanSchedule, $loanScheduleSettlement));
    }

    /**
     * Builds every prop LoanScheduleSettlement/Index.vue needs.
     * Financial data (loan schedule header fields, existing
     * settlements) is read-only display data — UNCHANGED source
     * queries, just reshaped for Vue.
     */
    protected function getSettlementPageVars(Company $company, LoanSchedule $loanSchedule, ?LoanScheduleSettlement $editingSettlement): array
    {
        $currentAccountType = AccountType::onlyCurrentAccount()->first();
        $currentAccounts = FinancialInstitutionAccount::getAllAccountNumberForCurrency($company->id, $loanSchedule->getCurrency(), $loanSchedule->getFinancialInstitutionId());

        return [
            'company' => ['id' => $company->id],
            'loanSchedule' => [
                'id' => $loanSchedule->id,
                'medium_term_loan_name' => $loanSchedule->getMediumTermLoanName(),
                'date_formatted' => $loanSchedule->getDateFormatted(),
                'currency' => $loanSchedule->getCurrency(),
                'beginning_balance_formatted' => $loanSchedule->getBeginningBalanceFormatted(),
                'schedule_payment_formatted' => $loanSchedule->getSchedulePaymentFormatted(),
                'interest_amount_formatted' => $loanSchedule->getInterestAmountFormatted(),
                'principle_amount_formatted' => $loanSchedule->getPrincipleAmountFormatted(),
                'end_balance_formatted' => $loanSchedule->getEndBalanceFormatted(),
                'settlement_default_date' => $loanSchedule->getSettlementDefaultDate(),
                'remaining' => $loanSchedule->getRemaining(),
            ],
            // {value: stored account number, label: what the user reads}.
            // The two differ for a shareholder-owned account (D7,
            // docs/shareholder-accounts.md) — sending only the labels here
            // would store the owner's name in current_account_number.
            'currentAccounts' => collect($currentAccounts)
                ->map(fn ($label, $accountNumber) => ['value' => (string) $accountNumber, 'label' => (string) $label])
                ->values(),
            'currentAccountTypeId' => $currentAccountType?->id ?? 0,
            'financialInstitutionId' => $loanSchedule->getFinancialInstitutionId(),
            'balanceLookupUrl' => route('update.balance.and.net.balance.based.on.account.number', ['company' => $company->id]),
            'settlements' => $loanSchedule->settlements->map(function (LoanScheduleSettlement $s) use ($company, $editingSettlement, $loanSchedule) {
                return [
                    'id' => $s->id,
                    'date_formatted' => $s->getDateFormatted(),
                    'account_number' => AccountNumberLabel::forCurrentAccount($company->id, $loanSchedule->getFinancialInstitutionId(), $s->getAccountNumber()),
                    'amount_formatted' => $s->getAmountFormatted(),
                    'edit_url' => route('edit.loan.schedule.settlements', ['company' => $company->id, 'loanScheduleSettlement' => $s->id]),
                    'delete_url' => route('delete.loan.schedule.settlements', ['company' => $company->id, 'loanScheduleSettlement' => $s->id]),
                    'is_being_edited' => $editingSettlement && $editingSettlement->id === $s->id,
                ];
            })->values(),
            // Only the LAST settlement is editable/deletable — confirmed
            // from the original's `@if($loop->last)` guard around the
            // Edit/Delete buttons. Vue enforces the same restriction.
            'lastSettlementId' => $loanSchedule->settlements->sortBy('date')->last()?->id,
            'editingSettlement' => $editingSettlement ? [
                'id' => $editingSettlement->id,
                'date' => $editingSettlement->getDate(),
                'amount' => $editingSettlement->getAmount(),
                'current_account_number' => $editingSettlement->getCurrentAccountNumber(),
            ] : null,
            'submitUrl' => $editingSettlement
                ? route('update.loan.schedule.settlements', ['company' => $company->id, 'loanScheduleSettlement' => $editingSettlement->id])
                : route('store.loan.schedule.settlements', ['company' => $company->id, 'loanSchedule' => $loanSchedule->id]),
            'backUrl' => route('view.uploading', ['company' => $company->id, 'loanId' => $loanSchedule->getMediumTermLoanId(), 'model' => 'LoanSchedule']),
            'navUrls' => [
                'home' => route('home', ['company' => $company->id]),
                'bank_accounts' => route('view.financial.institutions', ['company' => $company->id, 'active' => 'bank']),
                'customers' => route('partners.index', ['company' => $company->id, 'type' => 'customers']),
                'suppliers' => route('partners.index', ['company' => $company->id, 'type' => 'suppliers']),
                'notifications' => route('view.notifications', ['company' => $company->id, 'type' => 'all']),
            ],
        ];
    }

    /**
     * Records a payment settlement against a schedule installment.
     * UNCHANGED, deliberately — balance validation and statement
     * posting logic untouched.
     */
    public function storeLoanScheduleSettlement(Company $company, Request $request, LoanSchedule $loanSchedule)
    {
        if (!$loanSchedule->hasMediumTermLoan()) {
            return redirect()
                ->back()
                ->with('warning', __('This loan schedule installment is not linked to an active medium term loan.'));
        }
        if ($response = $this->failIfEarlySettled($loanSchedule->mediumTermLoan)) {
            return $response;
        }

        /**
         * * تسوية القسط بتصرف فلوس فعليًا من الحساب
         * * فما ينفعش تتسجّل بتاريخ بعد النهاردة
         */
        $request->validate([
            'date' => ['required', new DateMustBeLessThanOrEqualDate(null, now(), __('Settlement Date Must Be Less Than Or Equal Today'))],
        ]);

        $currentAccountNumber = $request->get('current_account_number');
        $amount = number_unformat($request->get('amount'));
        $date = $request->get('date');

        $balanceValidationResponse = $this->validateCurrentAccountBalanceForSettlement(
            $company,
            $loanSchedule,
            $currentAccountNumber,
            $date,
            $amount
        );

        if ($balanceValidationResponse) {
            return $balanceValidationResponse;
        }

        /**
         * @var LoanScheduleSettlement $loanScheduleSettlement
         */
        $loanScheduleSettlement = $loanSchedule->settlements()->create([
            'current_account_number' => $currentAccountNumber,
            'amount' => $amount,
            'date' => $date,
            'company_id' => $company->id
        ]);
        $financialInstitutionId = $loanSchedule->getFinancialInstitutionId();
        $accountType = AccountType::onlyCurrentAccount()->first();
        $commentEn = __('Settlement For Loan '.$loanSchedule->getMediumTermLoanName().' Installment No. '.$loanSchedule->getInstallmentNumber(), [], 'en');
        $commentAr = __('Settlement For Loan '.$loanSchedule->getMediumTermLoanName().' Installment No. '.$loanSchedule->getInstallmentNumber(), [], 'ar');
        $loanScheduleSettlement->handleCreditStatement($company->id, $financialInstitutionId, $accountType, $currentAccountNumber, null, $date, $amount, null, null, $commentEn, $commentAr);
        $loanScheduleSettlement->handleLoanStatement($company->id, $financialInstitutionId, $currentAccountNumber, $date, $amount, $commentEn, $commentAr);
        /**
         * * لو القرض دا كان بيتدفع منه فواتير (يعني
         * * consumption_status = new
         * * واتسحب منه فعلا) يبقي الجزء الخاص بال
         * * principle
         * * من القسط دا بينزل
         * * debit
         * * على حساب القرض نفسه .. فا المسحوب بيقل وال
         * * room
         * * بيرجع يزيد. الفايدة عمرها ما بتلمس الحساب دا.
         * * والجزء بتاع الفايدة بيتسجل في نفس الحركة في عمود
         * * interest_amount
         * * — تسجيل بس عشان الكشف يفرّق بين الفايدة المستحقة والمدفوعة،
         * * وعمره ما بيحرك الرصيد.
         * @see LoanScheduleSettlement::handleMediumTermLoanRepayment()
         */
        $loanScheduleSettlement->handleMediumTermLoanRepayment($company->id, $commentEn, $commentAr);
        // ⚠️ Same confirmed fix as ContractLoanScheduleController's
        // equivalent method: this was back(), leaving the user on the
        // same settlement page after paying instead of the schedule
        // table.
        return redirect()->route('view.uploading', getUploadingRouteParams(
            $company->id,
            'LoanSchedule',
            (string) $loanSchedule->getMediumTermLoanId()
        ));
    }

    /**
     * Deletes then re-creates the settlement fresh. UNCHANGED,
     * deliberately — same delete-then-reapply pattern as elsewhere.
     */
    public function updateLoanScheduleSettlement(Company $company, Request $request, LoanScheduleSettlement $loanScheduleSettlement)
    {
        $loanSchedule = $loanScheduleSettlement->loanSchedule;
        if ($response = $this->failIfEarlySettled($loanSchedule?->mediumTermLoan)) {
            return $response;
        }
        $this->deleteLoanScheduleSettlement($company, $request, $loanScheduleSettlement);
        $this->storeLoanScheduleSettlement($company, $request, $loanSchedule);
        return redirect()->route('view.loan.schedule.settlements', ['loanSchedule' => $loanSchedule->id, 'company' => $company->id]);
    }

    /**
     * Deletes a settlement and reverses everything it posted.
     * UNCHANGED.
     */
    public function deleteLoanScheduleSettlement(Company $company, Request $request, LoanScheduleSettlement $loanScheduleSettlement)
    {
        if ($response = $this->failIfEarlySettled($loanScheduleSettlement->loanSchedule?->mediumTermLoan)) {
            return $response;
        }
        $loanScheduleSettlement->deleteAllRelations();
        $loanScheduleSettlement->delete();
        return back();
    }

    /**
     * Early Settlement — the customer pays off the rest of the loan in one
     * go. The bank sums the remaining principle (the agreed amount) and adds
     * an early-settlement fee. Money moves once, from the chosen account;
     * every open installment is closed without a cash movement of its own.
     * @see MediumTermLoanEarlySettlement
     */
    public function storeEarlySettlement(Company $company, Request $request, FinancialInstitution $financialInstitution, MediumTermLoan $mediumTermLoan)
    {
        if ($response = $this->failIfEarlySettled($mediumTermLoan)) {
            return $response;
        }
        if (! count($mediumTermLoan->getEarlySettlementPreview()['loan_schedule_ids'])) {
            return back()->with('fail', __('This loan has no open installments to settle.'));
        }

        $data = $this->validateEarlySettlementRequest($company, $request, $mediumTermLoan, null);
        if ($data instanceof \Illuminate\Http\RedirectResponse) {
            return $data;
        }

        DB::transaction(function () use ($mediumTermLoan, $data) {
            MediumTermLoanEarlySettlement::settle($mediumTermLoan, ...$data);
        });

        return redirect()
            ->route('loans.index', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id])
            ->with('success', __('Loan Has Been Early Settled Successfully'));
    }

    /**
     * Same delete-then-reapply pattern as the rest of this controller, but
     * the balance is validated BEFORE anything is reversed, and both steps
     * share one transaction — so a failed edit leaves the original intact.
     */
    public function updateEarlySettlement(Company $company, Request $request, FinancialInstitution $financialInstitution, MediumTermLoan $mediumTermLoan)
    {
        $earlySettlement = $mediumTermLoan->earlySettlement;
        if (! $earlySettlement) {
            return back()->with('fail', __('This loan has not been early settled.'));
        }

        $data = $this->validateEarlySettlementRequest($company, $request, $mediumTermLoan, $earlySettlement);
        if ($data instanceof \Illuminate\Http\RedirectResponse) {
            return $data;
        }

        DB::transaction(function () use ($mediumTermLoan, $earlySettlement, $data) {
            $earlySettlement->reverse();
            MediumTermLoanEarlySettlement::settle($mediumTermLoan->refresh(), ...$data);
        });

        return redirect()
            ->route('loans.index', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id])
            ->with('success', __('Item Has Been Updated Successfully'));
    }

    /**
     * Reverses the early settlement completely: removes its statement rows,
     * re-opens every installment it closed (remaining recomputed from the
     * normal settlements on it) and puts the loan back to running.
     */
    public function destroyEarlySettlement(Company $company, FinancialInstitution $financialInstitution, MediumTermLoan $mediumTermLoan)
    {
        $earlySettlement = $mediumTermLoan->earlySettlement;
        if (! $earlySettlement) {
            return back()->with('fail', __('This loan has not been early settled.'));
        }

        DB::transaction(function () use ($earlySettlement) {
            $earlySettlement->reverse();
        });

        return redirect()
            ->route('loans.index', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id])
            ->with('success', __('Item Has Been Delete Successfully'));
    }

    /**
     * @return array{0: string, 1: float, 2: float, 3: AccountType, 4: string}|\Illuminate\Http\RedirectResponse
     *         the positional arguments MediumTermLoanEarlySettlement::settle() takes after the loan
     */
    protected function validateEarlySettlementRequest(Company $company, Request $request, MediumTermLoan $mediumTermLoan, ?MediumTermLoanEarlySettlement $editingEarlySettlement)
    {
        /**
         * * السداد المعجل بيصرف فلوس فعلا من الحساب .. فا ما ينفعش يتسجل
         * * بتاريخ بعد النهاردة
         */
        $request->validate([
            'date' => ['required', new DateMustBeLessThanOrEqualDate(null, now()->format('Y-m-d'), __('Early Settlement Date Must Be Less Than Or Equal Today'))],
            'amount' => ['required'],
            'account_type_id' => ['required'],
            'account_number' => ['required'],
        ]);

        $date = Carbon::make($request->get('date'))->format('Y-m-d');
        $amount = (float) number_unformat($request->get('amount'));
        $feeAmount = (float) number_unformat($request->get('fee_amount') ?: 0);
        $accountNumber = (string) $request->get('account_number');

        if ($amount <= 0) {
            return back()->with('fail', __('Early Settlement Amount Must Be Greater Than Zero'))->withInput();
        }
        if ($feeAmount < 0) {
            return back()->with('fail', __('Early Settlement Fees Can Not Be Negative'))->withInput();
        }

        /**
         * * السداد المعجل بيبدأ بعد اخر قسط اتدفع .. فا ما ينفعش يكون قبل
         * * تاريخ اخر سداد عادي على القرض
         */
        $lastSettlementDate = LoanScheduleSettlement::whereIn('loan_schedule_id', $mediumTermLoan->loanSchedules()->select('id'))->max('date');
        if ($lastSettlementDate && $date < Carbon::make($lastSettlementDate)->format('Y-m-d')) {
            return back()->with('fail', __('Early Settlement Date Must Be On Or After The Last Installment Payment Date :date', ['date' => Carbon::make($lastSettlementDate)->format('d-m-Y')]))->withInput();
        }

        /**
         * * حساب جاري او اوفردرافت .. في بنك القرض نفسه وبعملته
         */
        $accountType = MediumTermLoanEarlySettlement::getAllowedAccountTypes()->firstWhere('id', (int) $request->get('account_type_id'));
        $availableAccounts = $accountType
            ? ('\App\Models\\'.$accountType->getModelName())::getAllAccountNumberForCurrency($company->id, $mediumTermLoan->getCurrency(), $mediumTermLoan->getFinancialInstitutionId())
            : [];
        if (! $accountType || ! array_key_exists($accountNumber, $availableAccounts)) {
            return back()->with('fail', __('Please Select A Valid Account'))->withInput();
        }

        if ($response = $this->validateAccountBalanceForEarlySettlement($company, $mediumTermLoan, $accountType, $accountNumber, $date, $amount + $feeAmount, $editingEarlySettlement)) {
            return $response;
        }

        return [$date, $amount, $feeAmount, $accountType, $accountNumber];
    }

    /**
     * Same balance check as validateCurrentAccountBalanceForSettlement(),
     * for any cash account type (an overdraft is checked against its room).
     * In edit mode the endpoint adds the old amount back when the account
     * is unchanged.
     */
    protected function validateAccountBalanceForEarlySettlement(
        Company $company,
        MediumTermLoan $mediumTermLoan,
        AccountType $accountType,
        string $accountNumber,
        string $date,
        float $amount,
        ?MediumTermLoanEarlySettlement $editingEarlySettlement
    ): ?\Illuminate\Http\RedirectResponse {
        $balanceRequest = Request::create('/', 'GET', array_filter([
            'accountType' => $accountType->id,
            'accountNumber' => $accountNumber,
            'financialInstitutionId' => $mediumTermLoan->getFinancialInstitutionId(),
            'balanceDate' => $date,
            'modelId' => $editingEarlySettlement?->id,
            'modelType' => $editingEarlySettlement ? 'MediumTermLoanEarlySettlement' : null,
        ]));

        $balanceResponse = (new MoneyReceivedController())->updateNetBalanceBasedOnAccountNumber($balanceRequest, $company);
        $balance = (float) ($balanceResponse->getData()->balance ?? 0);

        if ($amount > $balance) {
            return back()->with('fail', __('Net Balance Less Than Paid Amount'))->withInput();
        }

        return null;
    }

    /**
     * * القرض اللي اتسدد سداد معجل اتقفل .. اي تغيير في اقساطه او سداداتها
     * * لازم يستنى لحد ما السداد المعجل نفسه يتحذف
     */
    protected function failIfEarlySettled(?MediumTermLoan $mediumTermLoan): ?\Illuminate\Http\RedirectResponse
    {
        if ($mediumTermLoan && $mediumTermLoan->isEarlySettled()) {
            return back()->with('fail', __('This loan has been early settled. Delete the early settlement first.'));
        }

        return null;
    }

    protected function validateCurrentAccountBalanceForSettlement(
        Company $company,
        LoanSchedule $loanSchedule,
        string $accountNumber,
        string $date,
        float $amount,
        ?LoanScheduleSettlement $editingSettlement = null
    ): ?\Illuminate\Http\RedirectResponse {
        $accountType = AccountType::onlyCurrentAccount()->first();

        if (!$accountType) {
            return back()->with('fail', __('Current account type is not configured.'))->withInput();
        }

        $balanceRequest = Request::create('/', 'GET', [
            'accountType' => $accountType->id,
            'accountNumber' => $accountNumber,
            'financialInstitutionId' => $loanSchedule->getFinancialInstitutionId(),
            'balanceDate' => Carbon::make($date)->format('Y-m-d'),
            'modelId' => $editingSettlement?->id,
            'modelType' => 'LoanScheduleSettlement',
        ]);

        $balanceResponse = (new MoneyReceivedController())->updateNetBalanceBasedOnAccountNumber($balanceRequest, $company);
        $balance = (float) ($balanceResponse->getData()->balance ?? 0);

        if ($amount > $balance) {
            return back()->with('fail', __('Net Balance Less Than Paid Amount'))->withInput();
        }

        return null;
    }

    /**
     * Pure AJAX data endpoint — used elsewhere (cash flow / dashboard
     * reports), not by anything migrated in this pass. UNCHANGED,
     * deliberately.
     */
    public function refreshReport(Company $company, Request $request) // ajax
    {
        $financialInstitutionId = (int) $request->get('financialInstitutionId');
        $loanId = (int) $request->get('mediumTermLoanId');
        $currencyName = $request->get('currencyName');
        $startDate = $request->get('loanStartDate');
        $endDate = $request->get('loanEndDate');
        $result = DB::table('medium_term_loans')
        ->where('medium_term_loans.company_id', $company->id)
        ->where('medium_term_loans.currency', $currencyName)
        ->join('loan_schedules', 'loan_schedules.medium_term_loan_id', '=', 'medium_term_loans.id')
        ->when($loanId !== 0, function ($builder) use ($loanId) {
            $builder->where('medium_term_loans.id', '=', $loanId);
        })
        ->when($financialInstitutionId !== 0, function ($builder) use ($financialInstitutionId) {
            $builder->where('medium_term_loans.financial_institution_id', '=', $financialInstitutionId);
        })
        ->whereBetween('loan_schedules.date', [$startDate, $endDate])
        ->orderBy('loan_schedules.date')
        ->select([
            'loan_schedules.date',
            'loan_schedules.schedule_payment',
        ])
        ->get();

        return response()->json([
            'status' => true,
            'data' => $result
        ]);
    }

    /**
     * Pure AJAX data endpoint — used elsewhere, not by anything
     * migrated in this pass. UNCHANGED, deliberately.
     */
    public function getMediumTermLoanForFinancialInstitution(Company $company, Request $request)
    {
        $currency = $request->get('currency');
        $financialInstitutionId = (int) $request->get('financialInstitutionId');

        if ($financialInstitutionId === 0) {
            $loans = $company->mediumTermLoans()->where('currency', $currency)->get();
        } else {
            $financialInstitution = FinancialInstitution::find($financialInstitutionId);
            $loans = $financialInstitution
                ? $financialInstitution->loans()->where('currency', $currency)->get()
                : collect();
        }

        return response()->json([
            'status' => true,
            'loans' => $loans
        ]);
    }
}
