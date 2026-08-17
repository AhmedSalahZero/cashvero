<?php
namespace App\Http\Controllers;

use App\Enums\LcTypes;
use App\Http\Requests\StoreLetterOfCreditIssuanceRequest;
use App\Http\Requests\StoreNewSettlementWithLcIssuanceRequest;
use App\Http\Requests\UpdateLetterOfCreditIssuanceRequest;
use App\Models\AccountType;
use App\Models\CertificatesOfDeposit;
use App\Models\Company;
use App\Models\Contract;
use App\Models\CurrentAccountBankStatement;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionAccount;
use App\Models\LcIssuanceExpense;
use App\Models\LcOverdraftBankStatement;
use App\Models\LcSettlementInternalMoneyTransfer;
use App\Models\LetterOfCreditCashCoverStatement;
use App\Models\LetterOfCreditFacility;
use App\Models\LetterOfCreditIssuance;
use App\Models\LetterOfCreditStatement;
use App\Models\Partner;
use App\Models\PaymentSettlement;
use App\Models\PurchaseOrder;
use App\Models\SupplierInvoice;
use App\Models\TimeOfDeposit;
use App\Services\Api\OdooSync;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * LetterOfCreditIssuanceController
 * ------------------------------------------------------------------
 * ! No Odoo Service Yet — confirmed, not an oversight. Unlike LG
 * Issuance, nothing here touches Odoo.
 *
 * Manages individual Letters Of Credit issued against one of 2 LIVE
 * funding sources: LC Facility, and 100% Cash Cover. "Against CD" and
 * "Against TD" still exist as full source values and model/controller
 * branches used to display or compute against any pre-existing
 * record — but as of this pass, their create()/edit() Blade views
 * (against-cd-form.blade.php / against-td-form.blade.php) have been
 * deleted, confirmed permanently dead by the project owner: an LC
 * Facility itself now carries the secured/unsecured distinction (see
 * LetterOfCreditFacility's `type` field), so a separate "issue
 * directly against a CD/TD" path became redundant, and the original
 * index.blade.php never even had a "New" button routing to either.
 * create() now 404s for these 2 sources; edit() redirects back with a
 * message instead (see each method's own docblock for why they're
 * treated differently). The routes themselves are left registered,
 * matching this project's established pattern for old unlinked
 * routes.
 *
 * Tabs are by LC TYPE (Sight LC, Deferred, Cash Against Document —
 * see App\Enums\LcTypes), not by source — same convention as LG
 * Issuance.
 *
 * ⚠️ update() does NOT actually update in place — it deletes the
 * issuance and all its relations, then calls store() fresh. Confirmed
 * deliberate original behavior (same pattern as LG Issuance), not a
 * bug. Left completely untouched.
 *
 * Unlike LG Issuance, this has only 2 real statuses (Running, Paid —
 * no Cancelled/Expired) and no Advanced Payment feature. Instead it
 * has: an Expenses sub-feature (LcIssuanceExpense — extra costs
 * booked against the issuance), and "Mark As Paid" (markAsPaid()) —
 * the terminal action that settles the LC against a chosen Supplier
 * Invoice, with its own financed-by-bank-vs-self / interest logic.
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   ✅ index() → MIGRATED to Vue + Inertia. Renders
 *      resources/js/Pages/LetterOfCreditIssuance/Index.vue.
 *      Unlike LG Issuance's index(), the ORIGINAL here has no
 *      pagination at all — every row for all 3 LC types is already
 *      queried and returned on every request, filtering only the
 *      active tab server-side. Matched exactly: no separate on-demand
 *      tab-data endpoint was added, since nothing needed deferring.
 *   ✅ create() / edit() → MIGRATED to Vue + Inertia, for the 2 live
 *      sources (LC_FACILITY, HUNDRED_PERCENTAGE_CASH_COVER). Renders
 *      resources/js/Pages/LetterOfCreditIssuance/LcFacilityForm and
 *      .../HundredPercentageCashCoverForm respectively.
 *   ✅ CONFIRMED DEAD & REMOVED (project owner, this pass): AGAINST_CD
 *      / AGAINST_TD as LC Issuance funding sources. The LC Facility
 *      contract was restructured to absorb what these used to cover,
 *      and the original Blade UI never had a button routing to either
 *      — they were unreachable dead code even before this migration,
 *      not just unmigrated. Their Blade views
 *      (against-cd-form.blade.php, against-td-form.blade.php) have
 *      been deleted; create()/edit() now abort(404) for these 2
 *      sources instead of falling through to a view that no longer
 *      exists.
 *   ⚠️ The AGAINST_CD/AGAINST_TD *constants* themselves, and the
 *      branches that read them in commonViewVars()/getTdOrCdCurrency()
 *      below and in LetterOfCreditFacilityController's balance/limit
 *      calculations, are UNCHANGED, deliberately — per §3.4, financial
 *      calculation logic is never touched blind. Those branches exist
 *      to correctly display/compute any LC Issuance record that may
 *      already exist in the live database with one of these sources
 *      (e.g. from data predating this dead-end, or an Odoo import) —
 *      only the *creation/editing entry point* was confirmed dead,
 *      not historical data.
 *   ✅ store() / update() / backToRunningStatus() / markAsPaid() /
 *      destroy() / applyExpense() / deleteExpense() /
 *      getRemainingBalance() → UNCHANGED, deliberately. All already
 *      redirect or JSON-appropriately.
 *   ⚠️ updateExpense() → response converted from a raw JSON body to a
 *      redirect, for Inertia compatibility (same fix already applied
 *      elsewhere in this project, e.g. LG Issuance's
 *      editAmountToBeDecreased()). Financial logic UNCHANGED.
 *   ℹ️ Index.vue's "Mark As Paid" modal deliberately does NOT include
 *      the original's nested "Allocate Payment To Customer Contract"
 *      repeater — a genuinely separate sub-feature for manually
 *      splitting a payment across multiple customer contracts, scoped
 *      as its own follow-up pass. Submitting without it still settles
 *      correctly against the chosen Supplier Invoice (automatic
 *      server-side); it only skips the manual-split override, sending
 *      an empty allocations array — the same safe default
 *      markAsPaid() already falls back to when none is submitted.
 */
class LetterOfCreditIssuanceController
{
    use GeneralFunctions;

    /**
     * * التعديل معمول كـ حذف ثم إنشاء ، فالـ update محتاج يوصل للسجل الجديد
     * * اللي storeWithinTransaction() عملته عشان يرجّع عليه المصاريف
     * * الإضافية بتاعت السجل القديم
     */
    protected ?LetterOfCreditIssuance $lastStoredIssuance = null;

    /**
     * No longer called from index() (see that method's docblock —
     * replaced with a real DB query since this operated on an
     * already-fully-loaded Collection). Left in place, unused, rather
     * than deleted, since nothing else in this class references it and
     * removing it isn't necessary for this fix.
     */
    protected function applyFilter(Request $request, Collection $collection, ?string $filterStartDate = null, ?string $filterEndDate = null): Collection
    {
        if (!count($collection)) {
            return $collection;
        }
        $searchFieldName = $request->get('field');
        $dateFieldName = 'issuance_date'; // change it
        $from = $request->get('from');
        $to = $request->get('to');
        $value = $request->query('value');
        $collection = $collection
        ->when($request->has('value'), function ($collection) use ($value, $searchFieldName) {
            return $collection->filter(function ($letterOfCreditIssuance) use ($value, $searchFieldName) {
                $currentValue = $letterOfCreditIssuance->{$searchFieldName};
                return false !== stristr($currentValue, $value);
            });
        })
        ->when($request->get('from'), function ($collection) use ($dateFieldName, $from) {
            return $collection->where($dateFieldName, '>=', $from);
        })
        ->when($request->get('to'), function ($collection) use ($dateFieldName, $to) {
            return $collection->where($dateFieldName, '<=', $to);
        })
        ->when($filterStartDate, function ($collection) use ($filterStartDate, $filterEndDate) {
            return $collection->filterByIssuanceDate($filterStartDate, $filterEndDate);
        })
        ->sortByDesc('id')->values();

        return $collection;
    }

    /**
     * Builds one row for Index.vue — including everything the Mark As
     * Paid / Expenses modals need inline (no separate AJAX endpoint
     * exists for these in the original, so the same eagerly-loaded
     * approach is used here, matching the original's own inline
     * per-row Blade queries in cancel-issuance-modal.blade.php).
     */
    private function buildRow(LetterOfCreditIssuance $lc, Company $company, string $source = null): array
    {
        $source = $source ?? $lc->getSource();
        $lcAmount = $lc->getLcAmount();
        /**
         * ⚠️ REAL BUG FIXED HERE (client-flagged, 2026-08-11): LC
         * currency is stored lowercase ('usd', 'egp' — confirmed from
         * the LC form's own convention), but supplier_invoices.currency
         * is stored uppercase ('EGP' — confirmed against real data).
         * onlyCurrency() does an exact string match, so this silently
         * excluded every genuinely matching invoice — the dropdown
         * looked empty even when the right invoice existed. Fixed by
         * normalizing to uppercase here, at the call site, rather than
         * changing the shared onlyCurrency() scope itself (used by
         * other invoice types too, where callers may already pass the
         * correct case).
         */
        $supplierInvoices = SupplierInvoice::onlyCompany($company->id)->onlyForPartner($lc->getBeneficiaryId())
            ->where(function ($q) use ($lcAmount) {
                $q->orHas('letterOfCreditIssuancePaymentSettlements')
                    ->orWhere('net_balance', '>=', $lcAmount);
            })
            ->onlyCurrency(strtoupper($lc->getLcCurrency()))
            ->get();

        $financialInstitutionId = $lc->getFinancialInstitutionBankId();
        $currentAccountsForBank = FinancialInstitutionAccount::where('financial_institution_id', $financialInstitutionId)
            ->where('company_id', $company->id)->get()
            ->map(fn ($a) => ['id' => $a->id, 'account_number' => $a->getAccountNumber()])->values();

        return [
            'id' => $lc->id,
            'lc_type' => $lc->getLcType(),
            'transaction_name' => $lc->getTransactionName(),
            'beneficiary_name' => $lc->getSupplierName(),
            'source_formatted' => $lc->getSourceFormatted(),
            'status' => $lc->getStatus(),
            'status_formatted' => $lc->getStatusFormatted(),
            'is_running' => $lc->isRunning(),
            'is_paid' => $lc->isPaid(),
            'bank_name' => $lc->getFinancialInstitutionBankName(),
            'financial_institution_id' => $financialInstitutionId,
            'lc_code' => $lc->getLcCode(),
            'lc_amount' => $lc->getLcAmount(),
            'lc_amount_formatted' => $lc->getLcAmountFormatted(),
            'lc_currency' => $lc->getLcCurrency(),
            'due_date' => $lc->getDueDate(),
            'due_date_formatted' => $lc->getDueDateFormatted(),
            'issuance_date_formatted' => $lc->getIssuanceDateFormatted(),
            'has_comment' => $lc->hasComment(),
            'user_comment' => $lc->getUserComment(),
            'edit_url' => route('edit.letter.of.credit.issuance', ['company' => $company->id, 'letterOfCreditIssuance' => $lc->id, 'source' => $source]),
            'delete_url' => route('delete.letter.of.credit.issuance', ['company' => $company->id, 'letterOfCreditIssuance' => $lc->id, 'source' => $source]),
            'back_to_running_url' => route('back.to.running.letter.of.credit.issuance', ['company' => $company->id, 'letterOfCreditIssuance' => $lc->id, 'source' => $source]),
            'mark_as_paid_url' => route('make.letter.of.credit.issuance.as.paid', ['company' => $company->id, 'letterOfCreditIssuance' => $lc->id, 'source' => $source]),
            'apply_expense_url' => route('apply.lc.issuance.expense', ['company' => $company->id, 'letterOfCreditIssuance' => $lc->id]),
            // Mark As Paid modal data
            'supplier_invoice_id' => $lc->getSupplierInvoiceId(),
            /**
             * Client-flagged (2026-08-11): the original popup shows each
             * invoice's own currency/net balance/exchange rate so the
             * person can see exactly what they're settling against —
             * dropped during the Vue migration along with everything
             * else below. Restored to match cancel-issuance-modal.blade.php.
             */
            'supplier_invoices' => $supplierInvoices->map(fn ($inv) => [
                'id' => $inv->id,
                'invoice_number' => $inv->getInvoiceNumber(),
                'currency' => $inv->getCurrency(),
                'net_balance' => $inv->getNetBalance(),
                'exchange_rate' => $inv->getExchangeRate(),
                'net_balance_in_main_currency' => $inv->getNetBalanceInMainCurrency(),
            ])->values(),
            'lc_exchange_rate' => $lc->getExchangeRate(),
            'lc_amount_in_main_currency' => $lc->getLcAmountInMainCurrency(),
            'lc_amount_in_main_currency_formatted' => $lc->getAmountInMainCurrencyFormatted(),
            'cash_cover_amount' => $lc->getCashCoverAmount(),
            'cash_cover_amount_formatted' => $lc->getCashCoverAmountFormatted(),
            'lc_cash_cover_currency' => $lc->getLcCashCoverCurrency(),
            /**
             * Matches the original's own logic exactly (see
             * cancel-issuance-modal.blade.php line 77): cash cover's own
             * exchange rate is 1 when its currency already IS the
             * company's main currency, otherwise it reuses the LC's own
             * exchange rate.
             */
            'cash_cover_exchange_rate' => $lc->getLcCashCoverCurrency() === $company->getMainFunctionalCurrency() ? 1 : $lc->getExchangeRate(),
            'cash_cover_rate' => $lc->getCashCoverRate(),
            'account_number_lookup_url' => route('lc.get.account.numbers.for.account.type', ['company' => $company->id, 'accountType' => '__TYPE__', 'currency' => '__CURRENCY__', 'financialInstitutionId' => $financialInstitutionId]),
            'is_financed_by_self' => $lc->isFinancedBySelf(),
            'company_main_currency' => $company->getMainFunctionalCurrency(),
            'payment_currency' => $lc->getPaymentCurrency(),
            'payment_account_type_id' => $lc->getPaymentAccountTypeId(),
            'payment_account_number_id' => $lc->getPaymentAccountNumberId(),
            'interest_currency' => $lc->getInterestCurrency(),
            'interest_amount' => $lc->getInterestAmount(),
            'current_account_types' => AccountType::onlyCurrentAccount()->get()->map(fn ($t) => ['id' => $t->id, 'name' => $t->getName()])->values(),
            'payment_accounts' => $currentAccountsForBank,
            // Expenses modal data
            'bank_currencies' => getBanksCurrencies(),
            'expenses' => $lc->expenses->sortBy('date')->values()->map(fn ($e) => [
                'id' => $e->id,
                'name' => $e->getName(),
                'date' => $e->getDate(),
                'date_formatted' => $e->getDateFormatted(),
                'amount' => $e->getAmount(),
                'amount_formatted' => $e->getAmountFormatted(),
                'currency' => $e->getCurrency(),
                'exchange_rate' => $e->getExchangeRate(),
                'update_url' => route('update.lc.issuance.expense', ['company' => $company->id, 'expense' => $e->id]),
                'delete_url' => route('delete.lc.issuance.expense', ['company' => $company->id, 'expense' => $e->id]),
            ])->values(),
        ];
    }

    /**
     * The main "LC Issuance" list — 3 tabs by LC type.
     *
     * FIX (per audit, 2026-08-13): the ORIGINAL genuinely had no
     * pagination at all here — confirmed deliberate (see the removed
     * comment this docblock used to have), not a migration mistake —
     * but it means every LC record the company has ever issued, for
     * ALL 3 types, was loaded into memory and fully built into a row
     * (including a couple of real per-row queries inside buildRow(),
     * still true after this fix — see that method) on every single
     * visit to this page, and that only gets worse as LC history
     * grows. Same underlying problem as Cash Expense / Internal Money
     * Transfer / Buy-Sell Currencies (already fixed), just more severe
     * here since there was no pagination boundary of any kind.
     *
     * Now uses real database pagination, one tab per Inertia prop
     * (only the active tab's page of rows is ever built), same
     * pattern as those other three pages. Switching tabs client-side
     * still works instantly on the first page load, since all three
     * tabs' first page still arrive together — only page-turning
     * within a tab now scopes down to just that tab (see
     * goToPage()/applyFilters() in Index.vue).
     *
     * NOT changed in this pass: buildRow() itself still runs a couple
     * of real per-row queries (a SupplierInvoice lookup and a
     * FinancialInstitutionAccount lookup, both needed for that row's
     * "Mark As Paid" modal). Real pagination bounds this to ~15-20
     * rows per request instead of a company's entire LC history, which
     * is the large majority of the win — but those two queries per
     * visible row are a smaller, separate opportunity left for a
     * follow-up if it's still worth chasing after this.
     *
     * ✅ MIGRATED to Vue + Inertia. Renders
     * resources/js/Pages/LetterOfCreditIssuance/Index.vue.
     */
    public function index(Company $company, Request $request)
    {
        $paginationPerPage = GeneralFunctions::getPaginationLimit();
        $numberOfMonthsBetweenEndDateAndStartDate = 18;
        $activeLcType = $request->get('active', LcTypes::SIGHT_LC);
        $filterDates = [];
        foreach (getLcTypes() as $type => $typeNameFormatted) {
            $startDate = $request->has('startDate') ? $request->input('startDate.'.$type) : now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d');
            $endDate = $request->has('endDate') ? $request->input('endDate.'.$type) : now()->format('Y-m-d');
            $filterDates[$type] = ['startDate' => $startDate, 'endDate' => $endDate];
        }

        $pageParamByType = [
            LcTypes::SIGHT_LC => 'sightLcPage',
            LcTypes::DEFERRED => 'deferredPage',
            LcTypes::CASH_AGAINST_DOCUMENT => 'cashAgainstDocumentPage',
        ];

        $buildTab = function (string $type) use ($company, $request, $activeLcType, $filterDates, $paginationPerPage, $pageParamByType) {
            $startDate = $filterDates[$type]['startDate'];
            $endDate = $filterDates[$type]['endDate'];

            $query = $company->letterOfCreditIssuances()
                ->with(['financialInstitutionBank', 'beneficiary', 'expenses'])
                ->where('lc_type', $type)
                ->whereDate('issuance_date', '>=', $startDate)
                ->whereDate('issuance_date', '<=', $endDate);

            // Search only ever applies to whichever tab is actually
            // active — matches the original's own guard exactly
            // (searchField is one of only 2 real columns offered by
            // Index.vue's dropdown: transaction_name, lc_code).
            if ($type === $activeLcType) {
                $searchFieldName = $request->get('field');
                $value = $request->get('value');
                if ($request->has('value') && $searchFieldName) {
                    $query->where($searchFieldName, 'like', '%'.$value.'%');
                }
            }

            $paginator = $query->orderByDesc('id')
                ->paginate($paginationPerPage, ['*'], $pageParamByType[$type])
                ->withQueryString();

            return [
                'rows' => $paginator->through(fn (LetterOfCreditIssuance $lc) => $this->buildRow($lc, $company)),
            ];
        };

        return \Inertia\Inertia::render('LetterOfCreditIssuance/Index', [
            'company' => ['id' => $company->id],
            'activeLcType' => $activeLcType,
            'filterDates' => $filterDates,
            'lcTypes' => getLcTypes(),
            'createUrls' => [
                LetterOfCreditIssuance::LC_FACILITY => route('create.letter.of.credit.issuance', ['company' => $company->id, 'source' => LetterOfCreditIssuance::LC_FACILITY]),
                LetterOfCreditIssuance::HUNDRED_PERCENTAGE_CASH_COVER => route('create.letter.of.credit.issuance', ['company' => $company->id, 'source' => LetterOfCreditIssuance::HUNDRED_PERCENTAGE_CASH_COVER]),
            ],
            /**
             * Previously ungated — see the LG issuance note; the same
             * four permissions existed and were never checked.
             */
            'permissions' => [
                'canCreate' => hasAuthFor('lc_issuance.create'),
                'canUpdate' => hasAuthFor('lc_issuance.update'),
                'canDelete' => hasAuthFor('lc_issuance.delete'),
                'canSettle' => hasAuthFor('lc_issuance.settle'),
            ],
            'sightLcTab' => fn () => $buildTab(LcTypes::SIGHT_LC),
            'deferredTab' => fn () => $buildTab(LcTypes::DEFERRED),
            'cashAgainstDocumentTab' => fn () => $buildTab(LcTypes::CASH_AGAINST_DOCUMENT),
            /**
             * Facility Renewal work uncovered this: the "Allocate
             * Payment To Customer Contract" table (client-flagged,
             * 2026-08-11) existed in the original Blade flow but was
             * dropped entirely during the Vue migration — the current
             * modal even had a comment admitting it was a known gap.
             * The backend logic to SAVE these allocations was already
             * intact and untouched; only this data source and the UI
             * itself were missing. Matches the original's own query
             * (Partner::onlyCustomers()->onlyThatHaveContracts()) —
             * eagerly loaded with each contract's own code/amount so
             * the modal never needs a live lookup while typing.
             */
            'customersWithContracts' => \App\Models\Partner::onlyCompany($company->id)
                ->onlyCustomers()->onlyThatHaveContracts()->with('contracts')->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->getName(),
                    'contracts' => $p->contracts->map(fn ($c) => [
                        'id' => $c->id,
                        'name' => $c->getName(),
                        'code' => $c->getCode(),
                        'amount' => $c->getAmount(),
                    ])->values(),
                ])->values(),
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
     * Shared data-fetching for all 4 source forms. UNCHANGED,
     * deliberately — still used as-is by the 2 dead, unmigrated
     * sources (AGAINST_CD, AGAINST_TD) via their original Blade views.
     */
    public function commonViewVars(Company $company, string $source, ?LetterOfCreditIssuance $letterOfCreditIssuance = null): array
    {
        $cdOrTdAccountTypes = [];
        $tdOrCdCurrencyName = null;
        if ($source == LetterOfCreditIssuance::AGAINST_CD) {
            $cdOrTdAccountTypes = AccountType::onlyCdAccounts()->get();
            if ($letterOfCreditIssuance) {
                $currentCertificateOfDeposit = CertificatesOfDeposit::find($letterOfCreditIssuance->cd_or_td_id);
                $tdOrCdCurrencyName = $currentCertificateOfDeposit->getCurrency();
            }
        } elseif ($source == LetterOfCreditIssuance::AGAINST_TD) {
            $cdOrTdAccountTypes = AccountType::onlyTdAccounts()->get();
            if ($letterOfCreditIssuance) {
                $currentTimeOfDeposit = TimeOfDeposit::find($letterOfCreditIssuance->cd_or_td_id);
                $tdOrCdCurrencyName = $currentTimeOfDeposit->getCurrency();
            }
        }
        return [
            'financialInstitutionBanks' => FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->onlyForSource($source)->get(),
            'beneficiaries' => Partner::onlySuppliers()->onlyForCompany($company->id)->get(),
            'contracts' => Contract::onlyForCompany($company->id)->get(),
            'purchaseOrders' => PurchaseOrder::onlyForCompany($company->id)->get(),
            'cashCoverAccountTypes' => AccountType::onlyCashCoverAccounts()->get(),
            'accountTypes' => AccountType::onlyCurrentAccount()->get(),
            'source' => $source,
            'cdOrTdAccountTypes' => $cdOrTdAccountTypes,
            'tdOrCdCurrencyName' => $tdOrCdCurrencyName,
        ];
    }

    /**
     * Preloads current accounts + running CD/TD accounts for a set of
     * financial institutions, each tagged with its real
     * account_type_id, so the Vue form can filter the account-number
     * dropdown by type + bank + currency client-side — same proven
     * pattern already used for Fully Secured Overdraft and LG Facility
     * / LG Issuance, since the original relied on client-side AJAX
     * endpoints with no traceable server route in this codebase.
     */
    protected function buildAccountsForBanks(Company $company, Collection $financialInstitutionIds): array
    {
        $currentAccountType = AccountType::onlyCurrentAccount()->first();
        $latestBalances = CurrentAccountBankStatement::whereIn('financial_institution_account_id', FinancialInstitutionAccount::whereIn('financial_institution_id', $financialInstitutionIds)->where('company_id', $company->id)->pluck('id'))
            ->orderByDesc('date')->orderByDesc('id')->get()
            ->groupBy('financial_institution_account_id')
            ->map(fn ($rows) => $rows->first()->getEndBalance());
        $accounts = FinancialInstitutionAccount::whereIn('financial_institution_id', $financialInstitutionIds)
            ->where('company_id', $company->id)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'account_type_id' => $currentAccountType?->id,
                'financial_institution_id' => $a->financial_institution_id,
                'account_number' => $a->getAccountNumber(),
                'currency' => $a->getCurrency(),
                'amount' => $latestBalances->get($a->id, 0),
            ])->values()->all();

        foreach (AccountType::onlyCashCoverAccounts()->get() as $accountType) {
            if ($accountType->id === $currentAccountType?->id) {
                continue; // already covered above
            }
            $modelClass = '\\App\\Models\\'.$accountType->getModelName();
            $records = $modelClass::where('company_id', $company->id)
                ->whereIn('financial_institution_id', $financialInstitutionIds)
                ->where('status', $modelClass::RUNNING)
                ->get();
            foreach ($records as $record) {
                $accounts[] = [
                    'id' => $record->id,
                    'account_type_id' => $accountType->id,
                    'financial_institution_id' => $record->financial_institution_id,
                    'account_number' => $record->getAccountNumber(),
                    'currency' => $record->getCurrency(),
                    'amount' => $record->getAmount(),
                ];
            }
        }
        return $accounts;
    }

    /**
     * Builds every prop LcFacilityForm.vue needs, on top of
     * commonViewVars().
     */
    protected function lcFacilityFormVars(Company $company, array $commonVars, ?LetterOfCreditIssuance $model): array
    {
        $financialInstitutionIds = collect($commonVars['financialInstitutionBanks'])->pluck('id');
        return [
            'mode' => $model ? 'edit' : 'create',
            'company' => ['id' => $company->id, 'mainFunctionalCurrency' => $company->getMainFunctionalCurrency()],
            'currencies' => getCurrencies(),
            'lcTypes' => getLcTypes(),
            'lcCategories' => LetterOfCreditIssuance::getCategories(),
            'financialInstitutionBanks' => collect($commonVars['financialInstitutionBanks'])->map(fn ($fi) => [
                'id' => $fi->id,
                'name' => $fi->getName(),
                'lc_facilities' => $fi->letterOfCreditFacilities->map(fn ($f) => ['id' => $f->id, 'name' => $f->getName()])->values(),
            ])->values(),
            'beneficiaries' => collect($commonVars['beneficiaries'])->map(fn ($p) => ['id' => $p->getId(), 'name' => $p->getName()])->values(),
            'accounts' => $this->buildAccountsForBanks($company, $financialInstitutionIds),
            'cashCoverAccountTypes' => AccountType::onlyCashCoverAccounts()->get()->map(fn ($t) => ['id' => $t->id, 'name' => $t->getName()])->values(),
            'feesAccountTypes' => AccountType::onlyCurrentAccount()->get()->map(fn ($t) => ['id' => $t->id, 'name' => $t->getName()])->values(),
            'contracts' => collect($commonVars['contracts'])->map(fn ($c) => ['id' => $c->id, 'partner_id' => $c->partner_id, 'name' => $c->getName()])->values(),
            'purchaseOrders' => collect($commonVars['purchaseOrders'])->map(fn ($po) => ['id' => $po->id, 'contract_id' => $po->contract_id, 'po_number' => $po->po_number])->values(),
            'model' => $model ? [
                'id' => $model->id,
                'category_name' => $model->getCategoryName(),
                'transaction_name' => $model->getTransactionName(),
                'financial_institution_id' => $model->getFinancialInstitutionBankId(),
                'lc_facility_id' => $model->getLcFacilityId(),
                'lc_type' => $model->getLcType(),
                'lc_code' => $model->getLcCode(),
                'partner_id' => $model->getBeneficiaryId(),
                'transaction_reference' => $model->getTransactionReference(),
                'contract_id' => $model->getContractType() === 'no-po' ? -1 : ($model->getContractType() === 'existing-po' ? -2 : $model->getContractId()),
                'new_purchase_order_number' => $model->getNewPoNumber(),
                'purchase_order_id' => $model->getPurchaseOrderId(),
                'purchase_order_date' => $model->getPurchaseOrderDate(),
                'transaction_date' => $model->getTransactionDate(),
                'issuance_date' => $model->getIssuanceDate(),
                'lc_duration_days' => $model->getLcDurationDays(),
                'due_date' => $model->getDueDate(),
                'lc_amount' => $model->getLcAmount(),
                'lc_currency' => $model->getLcCurrency(),
                'exchange_rate' => $model->getExchangeRate(),
                'amount_in_main_currency' => $model->getLcAmountInMainCurrency(),
                'cash_cover_rate' => $model->getCashCoverRate(),
                'cash_cover_amount' => $model->getCashCoverAmount(),
                'lc_cash_cover_currency' => $model->getLcCashCoverCurrency(),
                'lc_commission_rate' => $model->getLcCommissionRate(),
                'lc_commission_amount' => $model->getLcCommissionAmount(),
                'min_lc_commission_fees' => $model->min_lc_commission_fees,
                'issuance_fees' => $model->getIssuanceFees(),
                'cash_cover_deducted_from_account_type' => $model->getCashCoverDeductedFromAccountTypeId(),
                'cash_cover_deducted_from_account_id' => $model->getCashCoverDeductedFromAccountId(),
                'lc_fees_and_commission_account_type' => $model->getFeesAndCommissionAccountTypeId(),
                'lc_fees_and_commission_account_id' => $model->getFeesAndCommissionAccountId(),
                'financed_by_bank_or_self' => $model->getFinancedBy(),
                'financing_duration' => $model->financing_duration,
                'user_comment' => $model->getUserComment(),
            ] : null,
            'lookupUrl' => route('update.letter.of.credit.outstanding.balance.and.limit', ['company' => $company->id]),
            'exchangeRateLookupUrl' => route('get.exchange.rate.for.date.and.currencies', ['company' => $company->id]),
            'submitUrl' => $model
                ? route('update.letter.of.credit.issuance', ['company' => $company->id, 'letterOfCreditIssuance' => $model->id, 'source' => LetterOfCreditIssuance::LC_FACILITY])
                : route('store.letter.of.credit.issuance', ['company' => $company->id, 'source' => LetterOfCreditIssuance::LC_FACILITY]),
            'backUrl' => route('view.letter.of.credit.issuance', ['company' => $company->id]),
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
     * Builds every prop HundredPercentageCashCoverForm.vue needs, on
     * top of commonViewVars(). Only one shared account (current
     * accounts only) for both cash cover and fees/commission —
     * confirmed by tracing the original: no separate Cash Cover
     * account field exists for this source.
     */
    protected function hundredPercentageCashCoverFormVars(Company $company, array $commonVars, ?LetterOfCreditIssuance $model): array
    {
        $financialInstitutionIds = collect($commonVars['financialInstitutionBanks'])->pluck('id');
        $currentAccountType = AccountType::onlyCurrentAccount()->first();
        $feesAccounts = FinancialInstitutionAccount::whereIn('financial_institution_id', $financialInstitutionIds)
            ->where('company_id', $company->id)->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'account_type_id' => $currentAccountType?->id,
                'financial_institution_id' => $a->financial_institution_id,
                'account_number' => $a->getAccountNumber(),
                'currency' => $a->getCurrency(),
            ])->values();

        return [
            'mode' => $model ? 'edit' : 'create',
            'company' => ['id' => $company->id, 'mainFunctionalCurrency' => $company->getMainFunctionalCurrency()],
            'source' => LetterOfCreditIssuance::HUNDRED_PERCENTAGE_CASH_COVER,
            'feesAccountTypes' => AccountType::onlyCurrentAccount()->get()->map(fn ($t) => ['id' => $t->id, 'name' => $t->getName()])->values(),
            // Placeholder-based URL template (same technique the original
            // Blade JS used) — Vue substitutes __ACCOUNT_TYPE__,
            // __ACCOUNT_ID__, __FI_ID__ before fetching, since these are
            // real route path segments, not query params.
            'balanceLookupUrlTemplate' => route('update.balance.and.net.balance.based.on.account.id.ajax', ['company' => $company->id, 'accountType' => '__ACCOUNT_TYPE__', 'accountId' => '__ACCOUNT_ID__', 'financialInstitutionId' => '__FI_ID__']),
            'currencies' => getCurrencies(),
            'lcTypes' => getLcTypes(),
            'lcCategories' => LetterOfCreditIssuance::getCategories(),
            'financialInstitutionBanks' => collect($commonVars['financialInstitutionBanks'])->map(fn ($fi) => ['id' => $fi->id, 'name' => $fi->getName()])->values(),
            'beneficiaries' => collect($commonVars['beneficiaries'])->map(fn ($p) => ['id' => $p->getId(), 'name' => $p->getName()])->values(),
            'feesAccounts' => $feesAccounts,
            'contracts' => collect($commonVars['contracts'])->map(fn ($c) => ['id' => $c->id, 'partner_id' => $c->partner_id, 'name' => $c->getName()])->values(),
            'purchaseOrders' => collect($commonVars['purchaseOrders'])->map(fn ($po) => ['id' => $po->id, 'contract_id' => $po->contract_id, 'po_number' => $po->po_number])->values(),
            'model' => $model ? [
                'id' => $model->id,
                'category_name' => $model->getCategoryName(),
                'transaction_name' => $model->getTransactionName(),
                'financial_institution_id' => $model->getFinancialInstitutionBankId(),
                'lc_type' => $model->getLcType(),
                'lc_code' => $model->getLcCode(),
                'partner_id' => $model->getBeneficiaryId(),
                'transaction_reference' => $model->getTransactionReference(),
                'contract_id' => $model->getContractType() === 'no-po' ? -1 : ($model->getContractType() === 'existing-po' ? -2 : $model->getContractId()),
                'new_purchase_order_number' => $model->getNewPoNumber(),
                'purchase_order_id' => $model->getPurchaseOrderId(),
                'purchase_order_date' => $model->getPurchaseOrderDate(),
                'transaction_date' => $model->getTransactionDate(),
                'issuance_date' => $model->getIssuanceDate(),
                'lc_duration_days' => $model->getLcDurationDays(),
                'due_date' => $model->getDueDate(),
                'lc_amount' => $model->getLcAmount(),
                'lc_currency' => $model->getLcCurrency(),
                'exchange_rate' => $model->getExchangeRate(),
                'amount_in_main_currency' => $model->getLcAmountInMainCurrency(),
                'cash_cover_rate' => $model->getCashCoverRate(),
                'cash_cover_amount' => $model->getCashCoverAmount(),
                'lc_cash_cover_currency' => $model->getLcCashCoverCurrency(),
                'lc_commission_rate' => $model->getLcCommissionRate(),
                'lc_commission_amount' => $model->getLcCommissionAmount(),
                'min_lc_commission_fees' => $model->min_lc_commission_fees,
                'issuance_fees' => $model->getIssuanceFees(),
                'cash_cover_deducted_from_account_type' => $model->getCashCoverDeductedFromAccountTypeId(),
                'lc_fees_and_commission_account_id' => $model->getFeesAndCommissionAccountId(),
                'user_comment' => $model->getUserComment(),
            ] : null,
            'lookupUrl' => route('update.letter.of.credit.outstanding.balance.and.limit', ['company' => $company->id]),
            'exchangeRateLookupUrl' => route('get.exchange.rate.for.date.and.currencies', ['company' => $company->id]),
            'submitUrl' => $model
                ? route('update.letter.of.credit.issuance', ['company' => $company->id, 'letterOfCreditIssuance' => $model->id, 'source' => LetterOfCreditIssuance::HUNDRED_PERCENTAGE_CASH_COVER])
                : route('store.letter.of.credit.issuance', ['company' => $company->id, 'source' => LetterOfCreditIssuance::HUNDRED_PERCENTAGE_CASH_COVER]),
            'backUrl' => route('view.letter.of.credit.issuance', ['company' => $company->id]),
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
     * Shows the "Add LC Issuance" form for one of 4 funding sources.
     *
     * ✅ PARTIALLY MIGRATED: LC_FACILITY and
     * HUNDRED_PERCENTAGE_CASH_COVER now render Vue + Inertia. The 2
     * dead sources (AGAINST_CD, AGAINST_TD — unreachable from the UI,
     * confirmed with the project owner) still render their original,
     * untouched Blade views.
     */
    public function create(Company $company, string $source)
    {
        $commonVars = $this->commonViewVars($company, $source);
        if ($source === LetterOfCreditIssuance::LC_FACILITY) {
            return \Inertia\Inertia::render('LetterOfCreditIssuance/LcFacilityForm', $this->lcFacilityFormVars($company, $commonVars, null));
        }
        if ($source === LetterOfCreditIssuance::HUNDRED_PERCENTAGE_CASH_COVER) {
            return \Inertia\Inertia::render('LetterOfCreditIssuance/HundredPercentageCashCoverForm', $this->hundredPercentageCashCoverFormVars($company, $commonVars, null));
        }
        // AGAINST_CD / AGAINST_TD — confirmed dead with the project
        // owner: the LC Facility contract was restructured to cover
        // this, and the original Blade UI never had a button routing
        // here. No Vue page for these; the old Blade views were
        // deleted with this pass. 404 rather than silently rendering
        // a missing view.
        abort(404);
    }

    /**
     * Stores a new LC Issuance. UNCHANGED, deliberately.
     */
    public function store(Company $company, StoreLetterOfCreditIssuanceRequest $request, string $source)
    {
        /**
         * * الحفظ كله جوه ترانزاكشن واحدة
         * * وأي اتصال بأودو بيتنفذ بعد ما الترانزاكشن تكومِت (شوف OdooSync)
         */
        return OdooSync::transaction(function () use ($company, $request, $source) {
            return $this->storeWithinTransaction($company, $request, $source);
        });
    }

    protected function storeWithinTransaction(Company $company, StoreLetterOfCreditIssuanceRequest $request, string $source)
    {
        $financialInstitutionId = $request->get('financial_institution_id');
        $letterOfCreditFacilityId = $request->get('lc_facility_id');
        $letterOfCreditFacility = $source == LetterOfCreditIssuance::LC_FACILITY ? LetterOfCreditFacility::find($letterOfCreditFacilityId) : null;
        $letterOfCreditFacilityId = 0;
        $contractId = $request->get('contract_id');
        $purchaseOrderId = $request->get('purchase_order_id');
        $lcCashCoverCurrency = $request->get('lc_cash_cover_currency');
        $contractType = null;
        $newPurchaseOrderNumber = $request->get('new_purchase_order_number');

        if ($contractId == -1) {
            $contractId = null;
            $contractType = 'no-po';
            $existingPo = PurchaseOrder::where([
                'po_number' => $newPurchaseOrderNumber,
                'company_id' => $company->id,
            ])->first();

            if ($newPurchaseOrderNumber && !$existingPo) {
                $po = PurchaseOrder::create([
                    'contract_id' => null,
                    'po_number' => $newPurchaseOrderNumber,
                    'company_id' => $company->id,
                    'created_by' => auth()->user()->id
                ]);
                $purchaseOrderId = $po->id;
            }
            /**
             * * لو امر الشراء موجود اصلا (حالة التعديل) لازم نمسك الـ id بتاعه
             * * من غير كده كان بيفضل ماخود من الريكوست ، والحقل ده مخفي في وضع
             * * "PO جديد" فكان بيرجع فاضي والاعتماد يتحفظ من غير امر شراء
             */
            elseif ($existingPo) {
                $purchaseOrderId = $existingPo->id;
            }
        } elseif ($contractId == -2) {
            $contractType = 'existing-po';
            $contractId = null;
        }

        $request->merge([
            'contract_type' => $contractType,
            'contract_id' => $contractId,
            'purchase_order_id' => $purchaseOrderId
        ]);

        if ($source == LetterOfCreditIssuance::LC_FACILITY && is_null($letterOfCreditFacility)) {
            return redirect()->back()->with('fail', __('No Available Letter Of Credit Facility Found !'));
        }
        if ($letterOfCreditFacility instanceof LetterOfCreditFacility) {
            $letterOfCreditFacilityId = $letterOfCreditFacility->id;
        }
        $model = new LetterOfCreditIssuance();
        /**
         * ⚠️ REAL BUG FIXED HERE (client-flagged, 2026-08-11): company_id
         * was never explicitly set on this model — storeBasicForm() only
         * sets a column if the submitted request happens to include a
         * field with that exact name, and the LC Issuance form doesn't
         * resubmit company_id (it's already known from the URL). That
         * left $model->company_id null all the way through to
         * storeCurrentAccountCreditBankStatement(), which reads it
         * directly — causing the crash the moment a current-account
         * statement row tried to save with no company_id at all.
         */
        $model->company_id = $company->id;
        // ⚠️ Confirmed bug fix: relying on $request->merge() + storeBasicForm()'s
        // generic re-read of the request to null out contract_id (for the -1
        // "New PO" / -2 "Existing PO" sentinel values) was hitting a foreign
        // key violation on insert — contract_id was landing as something
        // other than a real PHP null by the time storeBasicForm() saved the
        // model. Fixed by setting it directly here, with the already-
        // resolved, unambiguous $contractId/$contractType PHP values, and
        // excluding both fields from storeBasicForm()'s own processing so it
        // can't re-read the raw, unresolved request value and overwrite this.
        $model->contract_id = $contractId;
        $model->contract_type = $contractType;
        $lcCommissionAmount = $request->get('lc_commission_amount', 0);
        $minLcCommissionAmount = $request->get('min_lc_commission_fees', 0);
        $model->storeBasicForm($request, ['_token', 'save', '_method', 'contract_id', 'contract_type']);
        // * السجل الجديد عشان update() يقدر يرجّع عليه مصاريف السجل القديم
        $this->lastStoredIssuance = $model;
        $transactionName = $request->get('transaction_name');
        $lcType = $request->get('lc_type');
        $issuanceDate = $request->get('issuance_date');
        $lcAmount = $request->get('lc_amount', 0);
        $currency = $request->get('lc_currency', 0);
        $cdOrTdId = $request->get('cd_or_td_id');

        $cdOrTdAccountTypeId = $request->get('cd_or_td_account_type_id');
        $accountType = AccountType::find($cdOrTdAccountTypeId);
        $cdOrTdAccount = null;
        if ($accountType && $accountType->isCertificateOfDeposit()) {
            $cdOrTdAccount = CertificatesOfDeposit::find($cdOrTdId);
            $cdOrTdId = $cdOrTdAccount->id;
        } elseif ($accountType && $accountType->isTimeOfDeposit()) {
            $cdOrTdAccount = TimeOfDeposit::find($cdOrTdId);
            $cdOrTdId = $cdOrTdAccount->id;
        }
        /**
         * * في lc-facility و hundred-percentage-cash-cover مفيش شهادة/وديعة ،
         * * فـ $cdOrTdAccount بيفضل null. من غير الفحص ده كان بيضرب
         * * "getCurrency() on null" لو عملة التغطية اتسابت فاضية
         */
        $lcCashCoverOrCdOrTdCurrency = $model->getLcCashCoverCurrency()
            ?: ($cdOrTdAccount ? $cdOrTdAccount->getCurrency() : $request->get('lc_currency', ''));
        $isOpeningBalance = $request->get('category_name') == LetterOfCreditIssuance::OPENING_BALANCE;
        $cashCoverAmount = $request->get('cash_cover_amount', 0);
        $issuanceFees = $request->get('issuance_fees', 0);
        $lcAmountInMainCurrency = $model->getLcAmountInMainCurrency();
        $maxLcCommissionAmount = max($minLcCommissionAmount, $lcCommissionAmount);
        $lcFeesAndCommissionAccountId = $request->get('lc_fees_and_commission_account_id');

        $financialInstitutionAccountForFeesAndCommission = FinancialInstitutionAccount::find($lcFeesAndCommissionAccountId);
        $financialInstitutionAccountForCashCover = FinancialInstitutionAccount::find($request->get('cash_cover_deducted_from_account_id', $lcFeesAndCommissionAccountId));

        if (is_null($financialInstitutionAccountForFeesAndCommission)) {
            return redirect()->back()->withInput()->with('fail', __('Please Select A Valid Fees And Commission Account'));
        }

        $financialInstitutionAccountIdForFeesAndCommission = $financialInstitutionAccountForFeesAndCommission->id;
        $openingBalanceDateOfCurrentAccount = $financialInstitutionAccountForFeesAndCommission->getOpeningBalanceDate();

        $financialInstitutionAccountIdForCashCover = $financialInstitutionAccountForCashCover->id ?? 0;

        /**
         * * الفحص ده معناه: التغطية جاية من شهادة استثمار أو وديعة ، يبقى
         * * ما ينفعش نخصمها كمان من الحساب الجاري.
         * *
         * * قبل كده كان بيقارن cash_cover_deducted_from_account_id (وده id
         * * لحساب في financial_institution_accounts) بالرقمين 28 و 29 وهما
         * * ids لأنواع الحسابات في account_types — نوعين مختلفين تماما ،
         * * فالشرط كان دايما false والتغطية كانت بتتخصم مرتين في
         * * against-cd و against-td
         */
        $cashCoverAccountType = AccountType::find($request->get('cash_cover_deducted_from_account_type'));
        $isCdOrTdCashCoverAccount = $cashCoverAccountType
            && ($cashCoverAccountType->isCertificateOfDeposit() || $cashCoverAccountType->isTimeOfDeposit());
        $customerName = $model->getBeneficiaryName();
        if (!$isOpeningBalance && !$isCdOrTdCashCoverAccount) {
            $model->storeCurrentAccountCreditBankStatement($issuanceDate, $cashCoverAmount, $financialInstitutionAccountIdForCashCover, 0, 1, __('Cash Cover [ :customerName ] [ :lgType ] Transaction Name [ :transactionName ]', ['lgType' => __($lcType, [], 'en'), 'customerName' => $customerName, 'transactionName' => $transactionName], 'en'), __('Cash Cover [ :customerName ] [ :lgType ] Transaction Name [ :transactionName ]', ['lgType' => __($lcType, [], 'ar'), 'customerName' => $customerName, 'transactionName' => $transactionName], 'ar'));
        }
        if (!$isOpeningBalance) {
            $model->storeCurrentAccountCreditBankStatement($issuanceDate, $issuanceFees, $financialInstitutionAccountIdForFeesAndCommission, 0, 1, __('Issuance Fees [ :customerName ] [ :lgType ] Transaction Name [ :transactionName ]', ['lgType' => __($lcType, [], 'en'), 'customerName' => $customerName, 'transactionName' => $transactionName], 'en'), __('Issuance Fees [ :customerName ] [ :lgType ] Transaction Name [ :transactionName ]', ['lgType' => __($lcType, [], 'ar'), 'customerName' => $customerName, 'transactionName' => $transactionName], 'ar'), false, false, null, true);
        }
        $commentEn = __('LC Issuance [:lcType] [:transactionName]', ['lcType' => $lcType, 'transactionName' => $transactionName], 'en');
        $commentAr = __('LC Issuance [:lcType] [:transactionName]', ['lcType' => $lcType, 'transactionName' => $transactionName], 'ar');
        $model->handleLetterOfCreditStatement($financialInstitutionId, $source, $letterOfCreditFacilityId, $lcType, $company->id, $issuanceDate, 0, 0, $lcAmountInMainCurrency, $lcCashCoverOrCdOrTdCurrency, 0, $cdOrTdId, 'credit-lc-amount', $commentEn, $commentAr);
        $commentEn = __('LC Issuance Cash Cover [:lcType] [:transactionName]', ['lcType' => $lcType, 'transactionName' => $transactionName], 'en');
        $commentAr = __('LC Issuance Cash Cover [:lcType] [:transactionName]', ['lcType' => $lcType, 'transactionName' => $transactionName], 'ar');
        $model->handleLetterOfCreditStatement($financialInstitutionId, $source, $letterOfCreditFacilityId, $lcType, $company->id, $issuanceDate, 0, $cashCoverAmount, 0, $lcCashCoverOrCdOrTdCurrency, 0, $cdOrTdId, 'credit-lc-amount', $commentEn, $commentAr);
        $model->handleLetterOfCreditCashCoverStatement($financialInstitutionId, $source, $letterOfCreditFacilityId, $lcType, $company->id, $issuanceDate, 0, $cashCoverAmount, 0, $lcCashCoverCurrency, 0, 'credit-lc-amount');

        /**
         * * قرار العميل (12-08-2026): عمولة الاعتماد بتتاخد **مرة واحدة** يوم
         * * الإصدار ، مش دورية زي خطاب الضمان.
         * *
         * * عشان كده بنبعت عدد مرات = 1 و interval غير 'quarterly' ، فالدالة
         * * المشتركة بتنزل صف عمولة واحد بس. حقل LC Duration بيأثر على
         * * تاريخ الاستحقاق بس ومالوش أي علاقة بالعمولة
         */
        $numberOfCommissionRows = 1;
        $lcCommissionInterval = 'once';
        $model->storeCommissionAmountCreditBankStatement($lcCommissionInterval, $numberOfCommissionRows, $issuanceDate, $openingBalanceDateOfCurrentAccount, $maxLcCommissionAmount, $financialInstitutionAccountIdForFeesAndCommission, $transactionName, $lcType, $isOpeningBalance);

        return redirect()->route('view.letter.of.credit.issuance', ['company' => $company->id, 'active' => $request->get('lc_type')])->with('success', __('Data Store Successfully'));
    }

    /**
     * Shows the "Edit LC Issuance" form for one of the live funding
     * sources.
     *
     * ✅ MIGRATED to Vue + Inertia for LC_FACILITY and
     * HUNDRED_PERCENTAGE_CASH_COVER.
     *
     * ⚠️ AGAINST_CD/AGAINST_TD, confirmed dead (see class docblock):
     * unlike create(), this method receives an EXISTING
     * $letterOfCreditIssuance — if a legacy row with one of these
     * sources somehow still exists in the live database (nothing in
     * this codebase can rule that out with certainty), a hard 404
     * would stand between the project owner and that record with no
     * way out. Redirecting back to the index with a clear message is
     * the safer failure mode than create()'s abort(404) — deliberately
     * not the same treatment, per §3.4 (never touch financial data
     * paths blind).
     */
    public function edit(Company $company, Request $request, LetterOfCreditIssuance $letterOfCreditIssuance, string $source)
    {
        $commonVars = $this->commonViewVars($company, $source, $letterOfCreditIssuance);
        if ($source === LetterOfCreditIssuance::LC_FACILITY) {
            return \Inertia\Inertia::render('LetterOfCreditIssuance/LcFacilityForm', $this->lcFacilityFormVars($company, $commonVars, $letterOfCreditIssuance));
        }
        if ($source === LetterOfCreditIssuance::HUNDRED_PERCENTAGE_CASH_COVER) {
            return \Inertia\Inertia::render('LetterOfCreditIssuance/HundredPercentageCashCoverForm', $this->hundredPercentageCashCoverFormVars($company, $commonVars, $letterOfCreditIssuance));
        }
        return redirect()
            ->route('view.letter.of.credit.issuance', ['company' => $company->id])
            ->with('fail', __('This LC Issuance type is no longer supported for editing. Please contact an administrator.'));
    }

    /**
     * Updates an LC Issuance by deleting it and all its relations,
     * then calling store() fresh. Confirmed deliberate original
     * behavior (same pattern as LG Issuance), not a bug. UNCHANGED.
     */
    public function update(Company $company, UpdateLetterOfCreditIssuanceRequest $request, LetterOfCreditIssuance $letterOfCreditIssuance, string $source)
    {
        /**
         * * الحقل في الفورم اسمه contract_id بـ underscore. الكود كان بيقرا
         * * 'contract-id' بشرطة فالقيمة كانت دايما null ، يعني الشرط الأول
         * * صح دايما (امر الشراء بيتمسح في كل تعديل) والتاني غلط دايما
         * * (رقم الـ PO عمره ما بيتحدث)
         */
        $requestContractId = $request->get('contract_id');
        if ($letterOfCreditIssuance->getContractType() == 'no-po' && $requestContractId != -1) {
            $letterOfCreditIssuance->purchaseOrder ? $letterOfCreditIssuance->purchaseOrder->delete() : null;
        }
        if ($letterOfCreditIssuance->getContractType() == 'no-po' && $requestContractId == -1) {
            if ($letterOfCreditIssuance->purchaseOrder) {
                $letterOfCreditIssuance->purchaseOrder->update([
                    'po_number' => $request->get('new_purchase_order_number')
                ]);
            }
        }

        /**
         * * التعديل معمول كـ حذف ثم إنشاء
         * * فلازم يكون كله في ترانزاكشن واحدة
         * * قبل كده لو أي حاجة ضربت في النص كان الاعتماد القديم بيروح والجديد بيتعمل ناقص
         */
        /**
         * Wrapped so the delete+create below records as one edit and
         * this issuance's history follows it onto the new row.
         * See App\Support\Activity\ActivityLogger::asUpdate().
         */
        \App\Support\Activity\ActivityLogger::asUpdate($letterOfCreditIssuance, function () use ($company, $request, $letterOfCreditIssuance, $source) {
            OdooSync::transaction(function () use ($company, $request, $letterOfCreditIssuance, $source) {
                /**
                 * * المصاريف الإضافية مش بتتعاد مع الاعتماد الجديد ، فبنمسك الـ ids
                 * * بتاعتها ونرجّعها على السجل الجديد بعد ما يتعمل. من غير كده كانت
                 * * بتفضل مربوطة بـ id اتمسح (مصاريف يتيمة ما بتظهرش في أي شاشة)
                 */
                $expenseIds = $letterOfCreditIssuance->expenses()->pluck('id')->toArray();

                $letterOfCreditIssuance->deleteAllRelations(false);
                $letterOfCreditIssuance->delete();

                $this->storeWithinTransaction($company, $request, $source);

                if (count($expenseIds) && $this->lastStoredIssuance) {
                    LcIssuanceExpense::whereIn('id', $expenseIds)->update([
                        'lc_issuance_id' => $this->lastStoredIssuance->id
                    ]);
                }
            });
        });
        return redirect()->route('view.letter.of.credit.issuance', ['company' => $company->id, 'active' => $request->get('lc_type')])->with('success', __('Data Store Successfully'));
    }

    /**
     * Reverses "Mark As Paid" — as if it had been marked paid by
     * mistake. Deletes the related LC/cash-cover/bank statement
     * entries and the settlement. UNCHANGED, deliberately.
     */
    public function backToRunningStatus(Company $company, Request $request, LetterOfCreditIssuance $letterOfCreditIssuance, string $source)
    {
        return OdooSync::transaction(function () use ($company, $request, $letterOfCreditIssuance, $source) {
            return $this->backToRunningStatusWithinTransaction($company, $request, $letterOfCreditIssuance, $source);
        });
    }

    protected function backToRunningStatusWithinTransaction(Company $company, Request $request, LetterOfCreditIssuance $letterOfCreditIssuance, string $source)
    {
        $letterOfCreditIssuanceStatus = LetterOfCreditIssuance::RUNNING;

        $letterOfCreditIssuance->update([
            'status' => $letterOfCreditIssuanceStatus,
            'payment_date' => null,
            'supplier_invoice_id' => null,
            'payment_currency' => null,
            'payment_account_type_id' => null,
            'payment_account_number_id' => null,
            /**
             * * الفوايد بتتسجل في خطوة الدفع ، فلازم تترجع صفر مع التراجع.
             * * من غير كده صف الفايدة في الحساب الجاري بيتمسح بس القيمة
             * * بتفضل على الاعتماد وبتترجع في المودال ، فلو اتدفع تاني
             * * بتتحسب من جديد
             */
            'interest_amount' => 0,
            'interest_currency' => null,
        ]);

        PaymentSettlement::deleteButTriggerChangeOnLastElement($letterOfCreditIssuance->settlements);
        CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($letterOfCreditIssuance->currentAccountPaymentCreditBankStatements);
        CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($letterOfCreditIssuance->currentAccountLcInterestCreditBankStatements);
        LetterOfCreditStatement::deleteButTriggerChangeOnLastElement($letterOfCreditIssuance->letterOfCreditStatements->where('type', LetterOfCreditIssuance::FOR_PAID));
        /**
         * * صفوف الـ for-paid بس هي اللي بتتشال ، لأنها هي اللي خطوة الدفع
         * * عملتها. صف الـ credit-lc-amount اتعمل وقت **الإصدار** ومفيش
         * * علاقة ليه بالدفع — وشيله كان بيخلي الاعتماد يرجع running
         * * من غير تغطيته النقدية أصلا (كشف التغطيات بيفضل فاضي)
         */
        LetterOfCreditCashCoverStatement::deleteButTriggerChangeOnLastElement($letterOfCreditIssuance->letterOfCreditCashCoverStatements->where('type', LetterOfCreditIssuance::FOR_PAID));
        /**
         * * نفس الفلتر اللي في markAsPaid بالظبط (is_credit = 1) ، عشان
         * * التراجع يكون معكوس تماما للدفع. من غير الفلتر كان بيمسح أي صف
         * * مدين على نفس الاعتماد وده مش من صنع خطوة الدفع
         */
        LcOverdraftBankStatement::deleteButTriggerChangeOnLastElement($letterOfCreditIssuance->lcOverdraftBankStatements->where('source', $source)->where('is_credit', 1));

        return redirect()->route('view.letter.of.credit.issuance', ['company' => $company->id, 'active' => $request->get('lc_type')])->with('success', __('Data Store Successfully'));
    }

    /**
     * The terminal action: marks the LC as Paid — settles it against
     * a chosen Supplier Invoice, posts the payment (and, if financed
     * by the bank, the LC-facility-credit side instead of a bank
     * debit), and books interest if financed by self. UNCHANGED,
     * deliberately — including the nested settlement/allocation
     * calls at the bottom, which Index.vue's simplified Mark As Paid
     * modal feeds an empty allocations array by default (the same
     * safe fallback this method already has built in).
     */
    /**
     * Client-flagged (2026-08-11): the "Do you want to pay this LC?"
     * popup showed every current account for the bank regardless of
     * currency — original Cashvero filters by BOTH the selected Payment
     * Currency and Account Type. Matches the exact working pattern
     * already used by Money Payment / Money Received / Cash Expense
     * for the same live lookup, rather than inventing a new one.
     */
    /**
     * ⚠️ REAL BUG FIXED HERE (client-flagged, 2026-08-11): the shared
     * getAllAccountNumberForCurrency() defaults to keying its results
     * by account_number (a string), not the real database id — that's
     * fine for the other callers of this same pattern (Money Payment
     * etc.), which don't need the real id back. LC payment DOES need
     * it — payment_account_number_id gets stored as a genuine foreign
     * key on the resulting statement row — so submitting an account
     * NUMBER where an ID was expected meant the later lookup for that
     * account silently found nothing, crashing the moment a statement
     * row tried to save. Fixed by explicitly requesting 'id' as the
     * key, only for this endpoint — the shared method and every other
     * caller of it are untouched.
     */
    public function getAccountNumbersForAccountType(Company $company, Request $request, string $accountType, ?string $selectedCurrency = null, ?int $financialInstitutionId = 0)
    {
        $accountType = AccountType::find($accountType);
        $accountNumberModel = ('\App\Models\\'.$accountType->getModelName())::getAllAccountNumberForCurrency($company->id, $selectedCurrency, $financialInstitutionId, 'id');
        return response()->json([
            'status' => true,
            'data' => $accountNumberModel,
        ]);
    }

    public function markAsPaid(Company $company, StoreNewSettlementWithLcIssuanceRequest $request, LetterOfCreditIssuance $letterOfCreditIssuance, string $source)
    {
        return OdooSync::transaction(function () use ($company, $request, $letterOfCreditIssuance, $source) {
            return $this->markAsPaidWithinTransaction($company, $request, $letterOfCreditIssuance, $source);
        });
    }

    protected function markAsPaidWithinTransaction(Company $company, StoreNewSettlementWithLcIssuanceRequest $request, LetterOfCreditIssuance $letterOfCreditIssuance, string $source)
    {
        $supplierInvoiceId = $request->get('supplier_invoice_id');
        $supplierInvoice = SupplierInvoice::find($supplierInvoiceId);
        $letterOfCreditIssuanceStatus = LetterOfCreditIssuance::PAID;
        $lcType = $request->get('lc_type');
        $financedByBank = $letterOfCreditIssuance->isFinancedByBank();
        $financedBySelf = $letterOfCreditIssuance->isFinancedBySelf();
        $request->merge([
            'payment_currency' => $financedBySelf ? $request->get('payment_currency') : null,
            'payment_account_type_id' => $financedBySelf ? $request->get('payment_account_type_id') : null,
            'payment_account_number_id' => $financedBySelf ? $request->get('payment_account_number_id') : null,
            'lc_remaining_amount' => number_unformat($request->get('lc_remaining_amount', 0)),
        ]);
        $paymentCurrency = $request->get('payment_currency');
        $paymentAccountTypeId = $request->get('payment_account_type_id');
        $paymentAccountNumberId = $request->get('payment_account_number_id');
        $lcRemainingAmount = $request->get('lc_remaining_amount');
        $interestAmount = number_unformat($request->get('interest_amount', 0));
        $interestCurrency = $request->get('interest_currency');
        $financialInstitutionId = $letterOfCreditIssuance->financial_institution_id;
        $financialDuration = $letterOfCreditIssuance->getFinancialDuration();
        $supplierName = $letterOfCreditIssuance->getSupplierName();
        $transactionName = $letterOfCreditIssuance->getTransactionName();
        $lcFacilityLimit = $letterOfCreditIssuance->letterOfCreditFacility ? $letterOfCreditIssuance->letterOfCreditFacility->getLimit() : 0;
        $paymentDate = Carbon::make($request->get('payment_date', now()->format('Y-m-d')))->format('Y-m-d');
        $letterOfCreditIssuance->update([
            'status' => $letterOfCreditIssuanceStatus,
            'payment_date' => $paymentDate,
            'supplier_invoice_id' => $supplierInvoiceId,
            'payment_currency' => $paymentCurrency,
            'payment_account_type_id' => $paymentAccountTypeId,
            'payment_account_number_id' => $paymentAccountNumberId,
            'interest_amount' => $interestAmount,
            'interest_currency' => $interestCurrency
        ]);

        $letterOfCreditFacility = $letterOfCreditIssuance->letterOfCreditFacility;
        $lcType = $letterOfCreditIssuance->getLcType();
        $lcAmount = $letterOfCreditIssuance->getLcAmount();
        $lcAmountInMainCurrency = $letterOfCreditIssuance->getLcAmountInMainCurrency();

        $cashCoverAmount = $letterOfCreditIssuance->getCashCoverAmount();
        $diffBetweenLcAmountAndCashCover = ($lcAmountInMainCurrency - $cashCoverAmount);

        LetterOfCreditStatement::deleteButTriggerChangeOnLastElement($letterOfCreditIssuance->letterOfCreditStatements->where('type', LetterOfCreditIssuance::FOR_PAID));
        CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($letterOfCreditIssuance->currentAccountPaymentCreditBankStatements);
        CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($letterOfCreditIssuance->currentAccountLcInterestCreditBankStatements);
        LetterOfCreditCashCoverStatement::deleteButTriggerChangeOnLastElement($letterOfCreditIssuance->letterOfCreditCashCoverStatements->where('type', LetterOfCreditIssuance::FOR_PAID));
        LcOverdraftBankStatement::deleteButTriggerChangeOnLastElement($letterOfCreditIssuance->lcOverdraftBankStatements->where('source', $source)->where('is_credit', 1));

        $letterOfCreditFacilityId = $letterOfCreditFacility ? $letterOfCreditFacility->id : 0;
        $letterOfCreditCurrency = $source == LetterOfCreditIssuance::AGAINST_TD || $source == LetterOfCreditIssuance::AGAINST_CD ? $letterOfCreditIssuance->getTdOrCdCurrency($source, $company->id) : $letterOfCreditIssuance->getLcCashCoverCurrency();
        $commentEn = __('LC Payment [:lcType] [:transactionName]', ['lcType' => $lcType, 'transactionName' => $transactionName], 'en');
        $commentAr = __('LC Payment [:lcType] [:transactionName]', ['lcType' => $lcType, 'transactionName' => $transactionName], 'ar');
        $letterOfCreditIssuance->handleLetterOfCreditStatement($financialInstitutionId, $source, $letterOfCreditFacilityId, $lcType, $company->id, $paymentDate, 0, $lcRemainingAmount, 0, $letterOfCreditCurrency, 0, $letterOfCreditIssuance->getCdOrTdId(), LetterOfCreditIssuance::FOR_PAID, $commentEn, $commentAr);
        $commentEn = __('LC Cash Cover Payment [:lcType] [:transactionName]', ['lcType' => $lcType, 'transactionName' => $transactionName], 'en');
        $commentAr = __('LC Cash Cover Payment [:lcType] [:transactionName]', ['lcType' => $lcType, 'transactionName' => $transactionName], 'ar');
        $letterOfCreditIssuance->handleLetterOfCreditCashCoverStatement($financialInstitutionId, $source, $letterOfCreditFacilityId, $lcType, $company->id, $paymentDate, 0, 0, $cashCoverAmount, $letterOfCreditIssuance->getLcCashCoverCurrency(), 0, LetterOfCreditIssuance::FOR_PAID);
        /**
         * * الفوايد مصروف بنكي زي العمولة ومصاريف الإصدار ، فبتتخصم من نفس
         * * حساب المصاريف والعمولات بتاع الاعتماد.
         * *
         * * ودي الحالة الوحيدة اللي فيها حساب أصلا: لما التمويل من البنك
         * * الـ merge اللي فوق بيصفّر payment_account_number_id ، والدالة
         * * بتاخد int مش nullable فكان بيحصل TypeError (صفحة 500)
         */
        $interestAccountId = $letterOfCreditIssuance->getFeesAndCommissionAccountId() ?: $paymentAccountNumberId;
        if ($interestAmount > 0 && $interestAccountId) {
            $letterOfCreditIssuance->storeCurrentAccountLcInterestPaymentCreditBankStatement($paymentDate, $interestAmount, $interestAccountId, 0, 1, __('LC Interest Payment [ :supplierName ] [ :lcType ] Transaction Name [ :transactionName ]', ['lcType' => __($lcType, [], 'en'), 'supplierName' => $supplierName, 'transactionName' => $transactionName], 'en'), __('LC Payment [ :supplierName ] [ :lcType ] Transaction Name [ :transactionName ]', ['lcType' => __($lcType, [], 'ar'), 'supplierName' => $supplierName, 'transactionName' => $transactionName], 'ar'));
        }
        if ($source != LetterOfCreditIssuance::HUNDRED_PERCENTAGE_CASH_COVER) {
            $commentEn = __('Post Finance [ :noDays ] Days [ :supplierName ] [ :lcType ] Transaction Name [ :transactionName ]', ['noDays' => $financialDuration, 'supplierName' => $supplierName, 'lcType' => $lcType, 'transactionName' => $transactionName], 'en');
            $commentAr = __('Post Finance [ :noDays ] Days [ :supplierName ] [ :lcType ] Transaction Name [ :transactionName ]', ['noDays' => $financialDuration, 'supplierName' => $supplierName, 'lcType' => $lcType, 'transactionName' => $transactionName], 'ar');
            if ($financedByBank) {
                $letterOfCreditIssuance->handleLcCreditBankStatement($letterOfCreditFacilityId, 'credit', $lcFacilityLimit, $paymentDate, $diffBetweenLcAmountAndCashCover, $source, $commentEn, $commentAr);
            } else {
                $letterOfCreditIssuance->storeCurrentAccountPaymentCreditBankStatement($paymentDate, $lcRemainingAmount, $paymentAccountNumberId, 0, 1, __('LC Payment [ :supplierName ] [ :lcType ] Transaction Name [ :transactionName ]', ['lcType' => __($lcType, [], 'en'), 'supplierName' => $supplierName, 'transactionName' => $transactionName], 'en'), __('LC Payment [ :supplierName ] [ :lcType ] Transaction Name [ :transactionName ]', ['lcType' => __($lcType, [], 'ar'), 'supplierName' => $supplierName, 'transactionName' => $transactionName], 'ar'));
            }
        }
        if ($supplierInvoice) {
            $letterOfCreditIssuance->storeNewSettlementAfterDeleteOldOne($supplierInvoice, $company);
            $letterOfCreditIssuance->storeNewAllocationAfterDeleteOldOne($request->get('allocations', []));
        }
        return redirect()->route('view.letter.of.credit.issuance', ['company' => $company->id, 'active' => $lcType])->with('success', __('Data Store Successfully'));
    }

    /**
     * Deletes an LC Issuance and all its relations. UNCHANGED.
     */
    public function destroy(Company $company, LetterOfCreditIssuance $letterOfCreditIssuance)
    {
        $lcType = $letterOfCreditIssuance->getLcType();
        OdooSync::transaction(function () use ($letterOfCreditIssuance) {
            $letterOfCreditIssuance->deleteAllRelations();
            $letterOfCreditIssuance->delete();
        });
        return redirect()->route('view.letter.of.credit.issuance', ['company' => $company->id, 'active' => $lcType]);
    }

    public function getLcIssuanceExpenseData(Request $request, Company $company, $type): array
    {
        return [
            'expense_name' => $request->input('expense_name.'.$type),
            'date' => Carbon::make($request->input('date.'.$type))->format('Y-m-d'),
            'amount' => $request->input('amount.'.$type),
            'exchange_rate' => $request->input('exchange_rate.'.$type),
            'currency' => $request->input('currency.'.$type),
            'amount_in_main_currency' => $request->input('amount_in_main_currency.'.$type),
            'company_id' => $company->id
        ];
    }

    /**
     * Books a new expense against the LC Issuance. UNCHANGED,
     * deliberately — already redirects correctly.
     */
    public function applyExpense(Company $company, Request $request, LetterOfCreditIssuance $letterOfCreditIssuance, $type = 'create')
    {
        return OdooSync::transaction(function () use ($company, $request, $letterOfCreditIssuance, $type) {
            return $this->applyExpenseWithinTransaction($company, $request, $letterOfCreditIssuance, $type);
        });
    }

    protected function applyExpenseWithinTransaction(Company $company, Request $request, LetterOfCreditIssuance $letterOfCreditIssuance, $type = 'create')
    {
        $date = Carbon::make($request->input('date.'.$type))->format('Y-m-d');
        $amount = $request->input('amount.'.$type, 0);

        $accountId = $letterOfCreditIssuance->getCashCoverDeductedFromAccountId();
        $financialInstitutionAccount = FinancialInstitutionAccount::find($accountId);
        $financialInstitutionAccountId = $financialInstitutionAccount->id;
        $expenseData = $this->getLcIssuanceExpenseData($request, $company, $type);
        $expenseName = $expenseData['expense_name'] ?? null;
        $amount = $expenseData['amount'] ?? 0;
        if (is_null($expenseName)) {
            return redirect()->back()->with(['fail' => __('Please Enter Expense Name')]);
        }
        if ($amount == 0) {
            return redirect()->back()->with(['fail' => __('Please Enter Expense Amount')]);
        }
        /**
         * @var LcIssuanceExpense $lcIssuanceExpense
         */
        $lcIssuanceExpense = $letterOfCreditIssuance->expenses()->create($expenseData);
        $supplierName = $letterOfCreditIssuance->getSupplierName();
        $expenseName = $lcIssuanceExpense->getName();
        $lcType = $letterOfCreditIssuance->getLcType();
        $transactionName = $letterOfCreditIssuance->getTransactionName();

        $expenseCommentEn = __('Expense [ :expenseName ] [ :supplierName ] [ :lcType ] Transaction Name [ :transactionName ]', ['expenseName' => $expenseName, 'supplierName' => $supplierName, 'lcType' => $lcType, 'transactionName' => $transactionName], 'en');
        $expenseCommentAr = __('Expense [ :expenseName ] [ :supplierName ] [ :lcType ] Transaction Name [ :transactionName ]', ['expenseName' => $expenseName, 'supplierName' => $supplierName, 'lcType' => $lcType, 'transactionName' => $transactionName], 'ar');
        $lcIssuanceExpense->storeCurrentAccountCreditBankStatement($date, $amount, $financialInstitutionAccountId, 0, 1, $expenseCommentEn, $expenseCommentAr);
        return redirect()->route('view.letter.of.credit.issuance', ['company' => $company->id])->with('success', __('Expense Credit Successfully'));
    }

    /**
     * ⚠️ Fixed here: the original returned a raw JSON body
     * (`{'reloadCurrentPage'=>true}`), which only worked because the
     * original Blade form did a normal full-page POST and something
     * else handled the reload — under Inertia, router.post() expects
     * an Inertia response (redirect or Inertia::render), and a raw
     * JSON body breaks it (same category of fix already applied
     * elsewhere in this project, e.g. editRate()/
     * editLendingInformation()/editAmountToBeDecreased()). Changed to
     * a redirect; financial logic (delete-then-reapply) UNCHANGED.
     */
    public function updateExpense(Company $company, Request $request, LcIssuanceExpense $expense)
    {
        /**
         * * التعديل معمول كـ حذف ثم إنشاء
         * * فلازم يكون كله في ترانزاكشن واحدة
         */
        OdooSync::transaction(function () use ($company, $request, $expense) {
            $expense->delete();
            $letterOfCreditIssuance = $expense->letterOfCreditIssuance;
            $this->applyExpenseWithinTransaction($company, $request, $letterOfCreditIssuance, 'update');
        });
        return redirect()->route('view.letter.of.credit.issuance', ['company' => $company->id])->with('success', __('Expense Updated Successfully'));
    }

    /**
     * Deletes an expense. UNCHANGED, deliberately — already redirects
     * correctly.
     */
    public function deleteExpense(Company $company, Request $request, LcIssuanceExpense $expense)
    {
        $expense->delete();
        return redirect()->back()->with('success', __('Expense Deleted Successfully'));
    }

    /**
     * Pure AJAX data endpoint — used by the Internal Money Transfer
     * feature, not by anything migrated in this pass. UNCHANGED,
     * deliberately.
     */
    public function getRemainingBalance(Company $company, Request $request)
    {
        $letterOfCreditIssuance = LetterOfCreditIssuance::find($request->get('letterOfCreditIssuanceId'));
        $lcSettlementInternalTransfer = LcSettlementInternalMoneyTransfer::find($request->get('internalMoneyTransferId'));
        $currentLcAmountInEditMode = $lcSettlementInternalTransfer ? $lcSettlementInternalTransfer->getAmount() : 0;
        $remainingBalance = $letterOfCreditIssuance ? $letterOfCreditIssuance->getRemainingBalance($currentLcAmountInEditMode) : 0;
        return response()->json([
            'status' => true,
            'remaining_balance' => $remainingBalance
        ]);
    }
}
