<?php
namespace App\Http\Controllers;

use App\Helpers\HArr;
use App\Http\Requests\ApplyCollectionToChequeRequest;
use App\Http\Requests\BackToUnderCollectionChequeRequest;
use App\Http\Requests\DeleteMoneyReceivedRequest;
use App\Http\Requests\SendToUnderCollectionChequeRequest;
use App\Http\Requests\StoreMoneyReceivedRequest;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\Cheque;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Currency;
use App\Models\CustomerInvoice;
use App\Models\FactoringTransaction;
use App\Models\FinancialInstitution;
use App\Models\ForeignExchangeRate;
use App\Models\MoneyReceived;
use App\Models\Partner;
use App\Models\SalesOrder;
use App\Services\Api\OdooPayment;
use App\Services\Api\OdooSync;
use App\Traits\GeneralFunctions;
use App\Traits\Models\HasBasicFilter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

/**
 * MoneyReceivedController
 * ------------------------------------------------------------------
 * Treasury Operations → "Money Received". Every way cash physically
 * or virtually arrives into the company: cheques received (in safe /
 * under collection / collected / rejected), incoming bank transfers,
 * bank deposits, and cash into a safe/branch. Also handles "Down
 * Payment" money received (an advance not yet tied to a specific
 * invoice) via the same underlying `money_received` table and
 * `MoneyReceived` model, distinguished by `money_type`.
 *
 * This is one of the most business-critical, most intertwined parts
 * of CashVero: every save here can create/adjust real bank & partner
 * statements (several via database triggers — see
 * CashVero_Roadmap.md §1), and can create/void a real Odoo payment
 * when the company has Odoo integration configured. Money Payment
 * (the supplier-side mirror) shares a large amount of this same
 * shape and several of the same underlying helpers (IsMoney trait).
 *
 * ── Frontend migration status (as of this file's last update) ──────
 * - index() → ✅ Inertia/Vue (`Pages/MoneyReceived/Index.vue`). All 7 tabs.
 * - create()/store()/edit()/update() → ✅ Inertia/Vue
 *   (`Pages/MoneyReceived/Form.vue`) for the plain "Money Received"
 *   form, and (`Pages/MoneyReceived/DownPaymentForm.vue`) for the
 *   dedicated Down Payment form (`?type=down-payment` on create, or
 *   editing a record where `isDownPayment()` is true) — routed via
 *   createDownPayment()/editDownPayment(). Cheque Under Collection
 *   still keeps its own dedicated Blade edit view, matching the
 *   original (not a gap this migration introduced).
 * - store()/update()'s final response was changed from a raw JSON body
 *   (`['redirectTo'=>...]`, correct for the old jQuery-AJAX form) to a
 *   real redirect — required for Inertia, same fix already documented
 *   elsewhere in this codebase (bug #19/#22 in the Roadmap). The
 *   `$returnModel` early-return path `update()` relies on internally
 *   is untouched.
 * - destroy() → already Inertia-compatible (real redirect), untouched.
 * - Both Vue Form pages re-use the exact same real AJAX endpoints the
 *   original jQuery form called (getInvoiceNumber, getContractsFor-
 *   Customer, getSalesOrdersForContract, account-number/balance
 *   lookups) rather than duplicating their logic server-side — so
 *   there remains exactly one source of truth for invoice/settlement
 *   row data, in both create and edit mode.
 */
class MoneyReceivedController
{
    use GeneralFunctions, HasBasicFilter;

    /**
     * The 7 tabs on the Money Received index page, in the exact order
     * the original Blade page displayed them, each mapped to:
     *   - the (unchanged) Company:: query-builder method that supplies its rows
     *   - the pagination page-name Laravel uses for that tab (so all 7
     *     tabs can be paginated independently on one URL, matching the original)
     *   - the search fields the "Filter" feature supports for that tab
     *     (these field names are read directly from Request() deep inside
     *     the query builder methods themselves — see Company.php — so they
     *     are passed through here unchanged, not reimplemented)
     */
    protected function tabDefinitions(): array
    {
        return [
            MoneyReceived::CHEQUE => [
                'label' => __('Cheques In Safe'),
                'query' => 'getReceivedChequesInSafe',
                'page' => 'cheques-in-safe-page',
                'searchFields' => [
                    'partner_name' => __('Customer Name'),
                    'receiving_date' => __('Receiving Date'),
                    'cheque_number' => __('Cheque Number'),
                    'currency' => __('Currency'),
                    'receiving_currency' => __('Receiving Currency'),
                    'drawee_bank_name' => __('Drawee Bank'),
                    'due_date' => __('Due Date'),
                ],
            ],
            MoneyReceived::CHEQUE_REJECTED => [
                'label' => __('Rejected Cheques'),
                'query' => 'getReceivedRejectedChequesInSafe',
                'page' => 'rejected-cheques-in-safe-page',
                'searchFields' => [
                    'partner_name' => __('Customer Name'),
                    'receiving_date' => __('Receiving Date'),
                    'cheque_number' => __('Cheque Number'),
                    'currency' => __('Currency'),
                    'drawee_bank_name' => __('Drawee Bank'),
                    'due_date' => __('Due Date'),
                ],
            ],
            MoneyReceived::CHEQUE_UNDER_COLLECTION => [
                'label' => __('Cheques Under Collection'),
                'query' => 'getReceivedChequesUnderCollection',
                'page' => 'cheques-under-collection-page',
                'searchFields' => [
                    'partner_name' => __('Customer Name'),
                    'cheque_number' => __('Cheque Number'),
                    'received_amount' => __('Cheque Amount'),
                    'deposit_date' => __('Deposit Date'),
                    'drawl_bank_name' => __('Drawl Bank'),
                    'clearance_days' => __('Clearance Days'),
                ],
            ],
            MoneyReceived::CHEQUE_COLLECTED => [
                'label' => __('Collected Cheques'),
                'query' => 'getCollectedCheques',
                'page' => 'collected-cheques-page',
                'searchFields' => [
                    'partner_name' => __('Customer Name'),
                    'cheque_number' => __('Cheque Number'),
                    'drawee_bank_name' => __('Drawee Bank'),
                    'due_date' => __('Due Date'),
                    'currency' => __('Currency'),
                    'receiving_currency' => __('Receiving Currency'),
                    'account_number' => __('Account Number'),
                ],
            ],
            MoneyReceived::INCOMING_TRANSFER => [
                'label' => __('Incoming Transfer'),
                'query' => 'getReceivedTransfer',
                'page' => 'incoming-transfer-page',
                'searchFields' => [
                    'partner_name' => __('Customer Name'),
                    'receiving_date' => __('Receiving Date'),
                    'receiving_bank_name' => __('Receiving Bank'),
                    'received_amount' => __('Transfer Amount'),
                    'currency' => __('Currency'),
                    'receiving_currency' => __('Receiving Currency'),
                    'account_number' => __('Account Number'),
                ],
            ],
            MoneyReceived::CASH_IN_SAFE => [
                'label' => __('Cash In Safe'),
                'query' => 'getReceivedCashesInSafe',
                'page' => 'cash-in-safe-page',
                'searchFields' => [
                    'partner_name' => __('Customer Name'),
                    'receiving_date' => __('Receiving Date'),
                    'receiving_branch_name' => __('Branch'),
                    'received_amount' => __('Received Amount'),
                    'currency' => __('Currency'),
                    'receiving_currency' => __('Receiving Currency'),
                    'receipt_number' => __('Receipt Number'),
                ],
            ],
            MoneyReceived::CASH_IN_BANK => [
                'label' => __('Bank Deposit'),
                'query' => 'getReceivedCashesInBank',
                'page' => 'cash-in-bank-page',
                'searchFields' => [
                    'partner_name' => __('Customer Name'),
                    'receiving_date' => __('Receiving Date'),
                    'receiving_bank_name' => __('Receiving Bank'),
                    'received_amount' => __('Deposit Amount'),
                    'currency' => __('Currency'),
                    'receiving_currency' => __('Receiving Currency'),
                    'account_number' => __('Account Number'),
                ],
            ],
        ];
    }

    /**
     * Money Received index — the Treasury Operations "Money Received"
     * list. Migrated to Inertia/Vue; renders `Pages/MoneyReceived/Index.vue`.
     *
     * Every one of the 7 tabs keeps its own real, server-side pagination
     * and its own real, server-side search (the search itself is
     * UNCHANGED — it's implemented deep inside each `Company::getReceivedXxx()`
     * query builder, reading `Request('field'|'value'|'from'|'to')`, gated
     * to only apply to whichever tab is currently active — see
     * app/Models/Company.php). Only the currently-active tab's data is
     * fully built into row arrays; the other 6 tabs still run their
     * queries (for accurate counts/totals) but are not rendered until
     * the person switches to them, at which point a fresh request re-runs
     * this method with `active` changed — this mirrors the original
     * Blade page's own behaviour (it also ran all 7 queries on every
     * load) rather than introducing a new lazy-tab pattern that would
     * behave differently from what the business already relies on.
     */
    public function index(Company $company, Request $request)
    {
        $paginationPerPage = GeneralFunctions::getPaginationLimit();
        $numberOfMonthsBetweenEndDateAndStartDate = 18;
        $activeTab = $request->get('active', MoneyReceived::CHEQUE);
        $tabs = $this->tabDefinitions();

        // Date-range filter — UNCHANGED default window logic (18 months
        // back to today unless the person picked an explicit range).
        $filterDates = [];
        foreach (MoneyReceived::getAllTypes() as $type) {
            $startDate = $request->has('startDate') ? $request->input('startDate.'.$type) : now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d');
            $endDate = $request->has('endDate') ? $request->input('endDate.'.$type) : now()->format('Y-m-d');
            $filterDates[$type] = [
                'startDate' => $startDate,
                'endDate' => $endDate,
            ];
        }

        $tabsOut = [];
        foreach ($tabs as $type => $definition) {
            $startDate = $filterDates[$type]['startDate'] ?? null;
            $endDate = $filterDates[$type]['endDate'] ?? null;

            // Real, unchanged query builder from Company.php — the exact
            // same method the original Blade page called.
            $query = $company->{$definition['query']}($startDate, $endDate, $activeTab);

            $totalCount = (clone $query)->count();
            $totalAmount = (clone $query)->sum('received_amount');

            $paginator = $query->paginate($paginationPerPage, ['*'], $definition['page']);
            $paginator->appends(array_merge($request->except('page'), ['active' => $type]));

            // Only build the (potentially heavy) row arrays for the tab
            // actually being viewed — the other 6 only need the count/sum
            // above for their tab-pill badges, matching how the original
            // Blade page ran all 7 queries but only rendered one table.
            $paginatorArray = $paginator->toArray();
            $paginatorArray['data'] = $activeTab === $type
                ? $paginator->getCollection()->map(fn (MoneyReceived $moneyReceived) => $this->mapMoneyReceivedRow($moneyReceived, $type, $company))->all()
                : [];

            $tabsOut[$type] = [
                'label' => $definition['label'],
                'searchFields' => $definition['searchFields'],
                'totalCount' => $totalCount,
                'totalAmount' => round($totalAmount, 2),
                'paginator' => $paginatorArray,
            ];
        }

        $financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->with('bank:id,view_name')->get(['id', 'type', 'name', 'bank_id']);
        $accountTypes = AccountType::onlyCashAccounts()->get(['id', 'name_en', 'name_ar']);
        $user = auth()->user();

        return Inertia::render('MoneyReceived/Index', [
            'company' => ['id' => $company->id, 'name' => $company->getName()],
            'activeTab' => $activeTab,
            'tabs' => $tabsOut,
            'filterDates' => $filterDates,
            'search' => [
                'field' => $request->get('field'),
                'value' => $request->get('value'),
                'from' => $request->get('from'),
                'to' => $request->get('to'),
            ],
            'financialInstitutionBanks' => $financialInstitutionBanks->map(fn ($b) => ['id' => $b->id, 'name' => $b->getName()]),
            'accountTypes' => $accountTypes->map(fn ($a) => ['id' => $a->id, 'name' => $a->getName()]),
            'permissions' => [
                'canCreate' => $user->can('create money received'),
                'canUpdate' => $user->can('update money received'),
                'canDelete' => $user->can('delete money received'),
                'canReview' => $user->can(getReviewPermissionName('MoneyReceived')),
            ],
            'companyHasOdoo' => $company->hasOdooIntegrationCredentials(),
            'urls' => [
                'index' => route('view.money.receive', ['company' => $company->id]),
                'createMoneyReceived' => route('create.money.receive', ['company' => $company->id]),
                'createDownPayment' => route('create.money.receive', ['company' => $company->id, 'type' => 'down-payment']),
                'sendToCollection' => route('cheque.send.to.collection', ['company' => $company->id]),
                'accountNumbersForType' => $this->companyScopedUrl($company, 'money-received/get-account-numbers-based-on-account-type'),
                'balanceForAccountNumber' => route('update.balance.and.net.balance.based.on.account.number', ['company' => $company->id]),
            ],
        ]);
    }

    /**
     * Builds one Money Received index-table row as a plain array, with
     * every URL pre-resolved (this app has no Ziggy — see Style Guide
     * §8) and every value pre-formatted exactly as the original Blade
     * table cell displayed it. Kept as one shared mapper (rather than
     * 7 near-duplicate ones) because ~80% of the fields are identical
     * across tabs; `$type`-specific fields are merged in per tab below.
     */
    protected function mapMoneyReceivedRow(MoneyReceived $moneyReceived, string $type, Company $company): array
    {
        $company = $company ?: $moneyReceived->company;

        $common = [
            'id' => $moneyReceived->id,
            'type_formatted' => $moneyReceived->getMoneyTypeFormatted(),
            'customer_name' => $moneyReceived->getCustomerName(),
            'receiving_date' => $moneyReceived->getReceivingDate(),
            'receiving_date_formatted' => $moneyReceived->getReceivingDateFormatted(),
            'received_amount_formatted' => $moneyReceived->getReceivedAmountFormatted(),
            'currency' => $moneyReceived->getReceivingCurrency(),
            'currency_formatted' => $moneyReceived->getCurrencyToReceivingCurrencyFormatted(),
            'is_open_balance' => $moneyReceived->isOpenBalance(),
            'is_reviewed' => $moneyReceived->isReviewed(),
            'has_comment' => $moneyReceived->hasComment(),
            'user_comment' => $moneyReceived->hasComment() ? $moneyReceived->getUserComment() : null,
            'has_odoo_error' => $company->hasOdooIntegrationCredentials() && $moneyReceived->hasOdooError(),
            'odoo_error' => $moneyReceived->hasOdooError() ? $moneyReceived->getOdooError() : null,
            'is_fully_integrated_with_odoo' => $company->hasOdooIntegrationCredentials() && $moneyReceived->fullyIntegratedWithOdoo(),
            'odoo_reference_names' => $company->hasOdooIntegrationCredentials() && $moneyReceived->fullyIntegratedWithOdoo() ? $moneyReceived->getOdooReferenceNames() : [],
            'edit_url' => route('edit.money.receive', ['company' => $company->id, 'moneyReceived' => $moneyReceived->id]),
            'delete_url' => route('delete.money.receive', ['company' => $company->id, 'moneyReceived' => $moneyReceived->id]),
            'review_url' => route('confirmed.review', ['company' => $company->id, 'model' => $moneyReceived->id]),
            'resend_odoo_url' => route('resend.with.odoo', ['company' => $company->id, 'moneyReceived' => $moneyReceived->id]),
        ];

        return match ($type) {
            MoneyReceived::CHEQUE => array_merge($common, [
                'cheque_number' => $moneyReceived->cheque?->getChequeNumber(),
                'drawee_bank_name' => $moneyReceived->cheque?->getDraweeBankName(),
                'due_date_formatted' => $moneyReceived->cheque?->getDueDateFormatted(),
                'due_after_days' => $moneyReceived->cheque?->getDueAfterDays(),
                'due_status' => $moneyReceived->cheque?->getDueStatusFormatted(),
                'due_status_bool' => (bool) $moneyReceived->cheque?->getDueStatus(),
            ]),
            MoneyReceived::CHEQUE_REJECTED => array_merge($common, [
                'cheque_number' => $moneyReceived->cheque?->getChequeNumber(),
                'drawee_bank_name' => $moneyReceived->cheque?->getDraweeBankName(),
                'due_date_formatted' => $moneyReceived->cheque?->getDueDateFormatted(),
                'status_formatted' => $moneyReceived->cheque?->getStatusFormatted(),
            ]),
            MoneyReceived::CHEQUE_UNDER_COLLECTION => array_merge($common, [
                'cheque_number' => $moneyReceived->cheque?->getChequeNumber(),
                'deposit_date' => $moneyReceived->cheque?->deposit_date,
                'deposit_date_formatted' => $moneyReceived->cheque?->getDepositDateFormatted(),
                'drawl_bank_id' => $moneyReceived->cheque?->drawl_bank_id,
                'drawl_bank_name' => $moneyReceived->cheque?->getDrawlBankName(),
                'account_type' => $moneyReceived->cheque?->account_type,
                'account_type_name' => $moneyReceived->cheque?->getAccountTypeName(),
                'account_number' => $moneyReceived->cheque?->getAccountNumber(),
                'due_date_formatted' => $moneyReceived->cheque?->getDueDateFormatted(),
                'clearance_days' => $moneyReceived->cheque?->getClearanceDays(),
                'expected_collection_date_formatted' => $moneyReceived->cheque?->chequeExpectedCollectionDateFormatted(),
                'due_status' => $moneyReceived->cheque?->getDueStatusFormatted(),
                'due_status_bool' => (bool) $moneyReceived->cheque?->getDueStatus(),
                'apply_collection_url' => route('cheque.apply.collection', ['company' => $company->id, 'moneyReceived' => $moneyReceived->id]),
                'send_to_safe_url' => route('cheque.send.to.safe', ['company' => $company->id, 'moneyReceived' => $moneyReceived->id]),
                'send_to_rejected_safe_url' => route('cheque.send.to.rejected.safe', ['company' => $company->id, 'moneyReceived' => $moneyReceived->id]),
            ]),
            MoneyReceived::CHEQUE_COLLECTED => array_merge($common, [
                'cheque_number' => $moneyReceived->cheque?->getChequeNumber(),
                'due_date_formatted' => $moneyReceived->cheque?->getDueDateFormatted(),
                'deposit_date_formatted' => $moneyReceived->cheque?->getDepositDateFormatted(),
                'drawl_bank_name' => $moneyReceived->cheque?->getDrawlBankName(),
                'account_type_name' => $moneyReceived->cheque?->getAccountTypeName(),
                'account_number' => $moneyReceived->cheque?->getAccountNumber(),
                'actual_collection_date_formatted' => $moneyReceived->cheque?->chequeActualCollectionDateFormatted(),
                'send_to_under_collection_url' => route('cheque.send.to.under.collection', ['company' => $company->id, 'moneyReceived' => $moneyReceived->id]),
            ]),
            MoneyReceived::INCOMING_TRANSFER => array_merge($common, [
                'receiving_bank_name' => $moneyReceived->getIncomingTransferReceivingBankName(),
                'account_type_name' => $moneyReceived->getIncomingTransferAccountTypeName(),
                'account_number' => $moneyReceived->getIncomingTransferAccountNumber(),
            ]),
            MoneyReceived::CASH_IN_SAFE => array_merge($common, [
                'branch_name' => $moneyReceived->getCashInSafeBranchName(),
                'receipt_number' => $moneyReceived->getCashInSafeReceiptNumber(),
            ]),
            MoneyReceived::CASH_IN_BANK => array_merge($common, [
                'receiving_bank_name' => $moneyReceived->getCashInBankReceivingBankName(),
                'account_type_name' => $moneyReceived->getCashInBankAccountTypeName(),
                'account_number' => $moneyReceived->getCashInBankAccountNumber(),
            ]),
            default => $common,
        };
    }

    
    /**
     * The 4 Money Received "Money Type" options — hardcoded here to
     * match the original Blade `<select id="type">`'s static option
     * list exactly (Cash In Safe / Bank Deposit / Cheque / Incoming
     * Transfer). Cheque-related sibling types (Under Collection/
     * Collected/Rejected) are index-only states, never chosen here.
     */
    protected function moneyTypeOptions(): array
    {
        return [
            ['value' => MoneyReceived::CASH_IN_SAFE, 'label' => __('Cash In Safe')],
            ['value' => MoneyReceived::CASH_IN_BANK, 'label' => __('Bank Deposit')],
            ['value' => MoneyReceived::CHEQUE, 'label' => __('Cheque')],
            ['value' => MoneyReceived::INCOMING_TRANSFER, 'label' => __('Incoming Transfer')],
        ];
    }

    public function create(Company $company, $customerInvoiceId = null)
    {
        $isDownPayment = Request()->has('type');
        $customerInvoiceCurrencies = CustomerInvoice::getCurrencies($customerInvoiceId);

        if ($isDownPayment) {
            return $this->createDownPayment($company);
        }

        $banks = Bank::pluck('view_name', 'id');
        $accountTypes = AccountType::onlyCashAccounts()->get(['id', 'name_en', 'name_ar']);
        $selectedBranches = Branch::getBranchesForCurrentCompany($company->id);
        $selectedBanks = MoneyReceived::getDrawlBanksForCurrentCompany($company->id);
        $financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->with('bank:id,view_name')->get(['id', 'type', 'name', 'bank_id']);
        $invoiceNumber = $customerInvoiceId ? CustomerInvoice::where('id', $customerInvoiceId)->first()->getInvoiceNumber() : null;

        $customers = $customerInvoiceId
            ? Partner::orderBy('name')->where('id', CustomerInvoice::find($customerInvoiceId)->customer_id)->where('company_id', $company->id)->pluck('name', 'id')->toArray()
            : Partner::orderBy('name')->where('is_customer', 1)->where('company_id', $company->id)->pluck('name', 'id')->toArray();

        return Inertia::render('MoneyReceived/Form', [
            'company' => ['id' => $company->id, 'name' => $company->getName(), 'mainFunctionalCurrency' => $company->getMainFunctionalCurrency()],
            'model' => null,
            'singleModel' => $customerInvoiceId,
            'invoiceNumber' => $invoiceNumber,
            'warningMessage' => null,
            'customers' => collect($customers)->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
            'partnerTypes' => collect(getAllPartnerTypesForCustomers())->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'moneyTypes' => $this->moneyTypeOptions(),
            'currencies' => collect($customerInvoiceCurrencies ?: getBanksCurrencies())->map(fn ($label, $code) => ['code' => $code, 'label' => strtoupper($label)])->values(),
            'selectedBranches' => collect($selectedBranches)->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
            'selectedBanks' => collect($banks)->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
            'financialInstitutionBanks' => $financialInstitutionBanks->map(fn ($b) => ['id' => $b->id, 'name' => $b->getName()]),
            'accountTypes' => $accountTypes->map(fn ($a) => ['id' => $a->id, 'name' => $a->getName()]),
            'urls' => $this->formUrls($company),
        ]);
    }

    /**
     * Down Payment create — renders `Pages/MoneyReceived/DownPaymentForm.vue`.
     * Initial customer list matches the default Down Payment Type
     * ('over_contract' — the select's first, pre-selected option), i.e.
     * customers who have at least one contract; the Vue page refreshes
     * this list itself via `getCustomersOfOpeningBalance` whenever Down
     * Payment Type changes, exactly like the original's own on-change
     * (and on-load) AJAX refresh.
     */
    protected function createDownPayment(Company $company)
    {
        $selectedBranches = Branch::getBranchesForCurrentCompany($company->id);
        $selectedBanks = Bank::pluck('view_name', 'id');
        $financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->with('bank:id,view_name')->get(['id', 'type', 'name', 'bank_id']);
        $accountTypes = AccountType::onlyCashAccounts()->get(['id', 'name_en', 'name_ar']);
        $customers = Partner::orderBy('name')->where('is_customer', 1)->where('company_id', $company->id)->onlyThatHaveCustomerContracts()->pluck('name', 'id');

        return Inertia::render('MoneyReceived/DownPaymentForm', [
            'company' => ['id' => $company->id, 'name' => $company->getName(), 'mainFunctionalCurrency' => $company->getMainFunctionalCurrency()],
            'model' => null,
            'customers' => collect($customers)->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
            'moneyTypes' => $this->moneyTypeOptions(),
            'currencies' => collect(getBanksCurrencies())->map(fn ($label, $code) => ['code' => $code, 'label' => strtoupper($label)])->values(),
            'selectedBranches' => collect($selectedBranches)->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
            'selectedBanks' => collect($selectedBanks)->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
            'financialInstitutionBanks' => $financialInstitutionBanks->map(fn ($b) => ['id' => $b->id, 'name' => $b->getName()]),
            'accountTypes' => $accountTypes->map(fn ($a) => ['id' => $a->id, 'name' => $a->getName()]),
            'urls' => $this->downPaymentFormUrls($company),
        ]);
    }

    /**
     * Every URL the Vue Form page needs, pre-resolved (no Ziggy — see
     * Style Guide §8). Shared between create() and edit().
     */
    protected function formUrls(Company $company): array
    {
        return [
            'index' => route('view.money.receive', ['company' => $company->id]),
            'store' => route('store.money.receive', ['company' => $company->id]),
            'getInvoiceNumbers' => $this->companyScopedUrl($company, 'money-received/get-invoice-numbers'),
            'getAccountNumbersForType' => $this->companyScopedUrl($company, 'money-received/get-account-numbers-based-on-account-type'),
            'balanceForAccountNumber' => route('update.balance.and.net.balance.based.on.account.number', ['company' => $company->id]),
            'getContractsForCustomer' => route('get.contracts.for.customer', ['company' => $company->id]),
            'getSalesOrdersForContract' => $this->companyScopedUrl($company, 'down-payments/get-sales-orders-for-contract'),
            'getPartnersBasedOnCurrency' => $this->companyScopedUrl($company, 'get-partners-based-on-type'),
            'getBranchBasedOnCurrency' => route('get.branch.based.on.currency', ['company' => $company->id]),
            'getCashInSafeEndBalance' => route('get.current.end.balance.of.cash.in.safe.statement', ['company' => $company->id]),
        ];
    }

    /**
     * Same idea as formUrls(), for the dedicated Down Payment form.
     * `getCustomersOfOpeningBalance` is a real, existing, NAMED route
     * (no locale/company-prefix issue) despite its historical name —
     * it's the same endpoint the original's Down Payment Type change
     * handler always calls, returning either "customers who have a
     * contract" (over_contract) or "every customer" (general).
     */
    protected function downPaymentFormUrls(Company $company): array
    {
        return [
            'index' => route('view.money.receive', ['company' => $company->id]),
            'store' => route('store.money.receive', ['company' => $company->id]),
            'getContractsForCustomer' => route('get.contracts.for.customer', ['company' => $company->id]),
            'getSalesOrdersForContract' => $this->companyScopedUrl($company, 'down-payments/get-sales-orders-for-contract'),
            'getCustomersOfOpeningBalance' => route('get.customers.of.opening-balance', ['company' => $company->id]),
            'getAccountNumbersForType' => $this->companyScopedUrl($company, 'money-received/get-account-numbers-based-on-account-type'),
            'balanceForAccountNumber' => route('update.balance.and.net.balance.based.on.account.number', ['company' => $company->id]),
            'getBranchBasedOnCurrency' => route('get.branch.based.on.currency', ['company' => $company->id]),
            'getCashInSafeEndBalance' => route('get.current.end.balance.of.cash.in.safe.statement', ['company' => $company->id]),
        ];
    }

    /**
     * ⚠️ Real bug fix — root cause of "Settlement Information shows no
     * invoices no matter which customer is picked": several of this
     * page's AJAX endpoints (getInvoiceNumber, getAccountNumbersFor-
     * AccountType, getSalesOrdersForContract, getPartnersBasedOn-
     * Currency) were never given a route `->name(...)` in the original
     * app — every route in this section actually lives under TWO
     * nested prefixes (`LaravelLocalization::setLocale()` for the
     * locale segment, then `Route::prefix('{company}')`), which is
     * exactly what the original jQuery built by hand:
     * `'/' + lang + '/' + companyId + '/money-received/...'`
     * (see custom/money-receive.js). Laravel's plain `url()` helper
     * has no way to know about either prefix for an unnamed route, so
     * `url('money-received/get-invoice-numbers')` silently built a
     * URL missing BOTH segments — the fetch calls were hitting a
     * non-existent path and failing quietly (no invoices, no sales
     * orders, no account numbers, no currency-filtered partners,
     * anywhere on this page). This helper reproduces the original's
     * exact, working mechanism instead of guessing at a `url()` fix.
     */
    protected function companyScopedUrl(Company $company, string $path): string
    {
        return url('/'.app()->getLocale().'/'.$company->id.'/'.ltrim($path, '/'));
    }

    
    public function getContractsForCustomer(Company $company, Request $request)
    {
        $contracts = Contract::where('partner_id', $request->get('customerId'))
        ->where('model_type', 'Customer')
        ->where('currency', $request->get('currency'))->pluck('name', 'id')->toArray();
        return response()->json([
            'status'=>true ,
            'contracts'=>$contracts
        ]);
    }
    public function getContractsForCustomerWithStartAndEndDate(Company $company, Request $request)
    {

        $contracts = Contract::where('partner_id', $request->get('customerId'))
        ->whereDoesntHave('lendingInformationForAgainstAssignmentContract')
        ->where('currency', $request->get('currency'))
        ->where('model_type', 'Customer')
        ->get();
        return response()->json([
            'status'=>true ,
            'contracts'=>$contracts
        ]);
    }
    public function getSalesOrdersForContract(Company $company, Request $request, $contractId = 0, ?string $selectedCurrency=null)
    {
        $downPaymentId = $request->get('down_payment_id');
        $moneyReceived = MoneyReceived::find($downPaymentId);
        $salesOrders = SalesOrder::where('contract_id', $contractId)->get();
        $formattedSalesOrders = [];
        foreach ($salesOrders as $index=>$salesOrder) {
            /**
             * @var SalesOrder $salesOrder
             */
            $receivedAmount = $moneyReceived ? $moneyReceived->downPaymentSettlements->where('sales_order_id', $salesOrder->id)->first() : null ;
            $formattedSalesOrders[$index]['received_amount'] = $receivedAmount && $receivedAmount->down_payment_amount ? $receivedAmount->down_payment_amount : 0;
            $formattedSalesOrders[$index]['so_number'] = $salesOrder->so_number;
            $formattedSalesOrders[$index]['amount'] = $salesOrder->getAmount();
            $formattedSalesOrders[$index]['id'] = $salesOrder->id;
        }
        if (!count($salesOrders)) {
            $index = 0;
            $receivedAmount = $moneyReceived ? $moneyReceived->downPaymentSettlements->where('contract_id', null)->first() : null ;
            $formattedSalesOrders[$index]['received_amount'] = $receivedAmount && $receivedAmount->down_payment_amount ? $receivedAmount->down_payment_amount : 0;
            $formattedSalesOrders[$index]['so_number'] = 'General';
            $formattedSalesOrders[$index]['amount'] =0;
            $formattedSalesOrders[$index]['id'] = -1;
        }
        return response()->json([
            'status'=>true ,
            'sales_orders'=>$formattedSalesOrders,
            'selectedCurrency'=>$selectedCurrency
        ]);
        
    }
    public function getInvoiceNumber(Company $company, Request $request, int $customerId, ?string $selectedCurrency=null)
    {
        $inEditMode = $request->get('inEditMode');
        $moneyReceivedId = $request->get('money_received_id');
        
        $moneyReceived = MoneyReceived::find($moneyReceivedId);
        $partner = Partner::find($customerId);
        if (!$partner) {
            return response()->json([
                'status'=>true ,
                'invoices'=>[],
                'currencies'=>[],
                'selectedCurrency'=>[]
            ]);
        }
        $downPaymentContract = Contract::find($request->get('downPaymentContractId'));
        $partnerId = $partner->id;
        $invoices = CustomerInvoice::where('customer_id', $partnerId)
        ->where('company_id', $company->id)
    //	->whereNull('opening_balance_id')
        ->where('net_invoice_amount', '>', 0)
        ->when($downPaymentContract, function ($q) use ($downPaymentContract) {
            $q->where('contract_code', $downPaymentContract->getCode());
        });
        
        if (!$inEditMode) {
            $invoices->where('net_balance', '>', 0);
        }
        $contractsWithDownPaymentsCurrencies =$invoices->pluck('currency', 'currency')->mapWithKeys(function ($value, $key) {
            return [
                $key=>$value
            ];
        });

        if ($selectedCurrency) {
            $invoices = $invoices->where('currency', '=', $selectedCurrency);
        }

        $blockedByWithRecourse = FactoringTransaction::blockedInvoiceIdsForMoneyReceived($company->id);
        if ($blockedByWithRecourse->isNotEmpty()) {
            $invoices->whereNotIn('id', $blockedByWithRecourse);
        }

        $invoices = $invoices->orderBy('invoice_date', 'asc')
        ->get(['id','invoice_number','project_name','invoice_date','invoice_due_date','net_invoice_amount','total_collected_amount','net_balance','currency'])
        ->toArray();
        
        
        foreach ($invoices as $index=>$invoiceArr) {
            $invoices[$index]['settlement_amount'] = $moneyReceived ? $moneyReceived->sumSettlementsForInvoice($invoiceArr['id'], $partnerId, 0) : 0;
            $invoices[$index]['withhold_amount'] = $moneyReceived ? $moneyReceived->sumWithholdAmountForInvoice($invoiceArr['id'], $partnerId, 0) : 0;
        }

        $invoices = $this->formatInvoices($invoices, (int) $inEditMode);
        return response()->json([
            'status'=>true ,
            'invoices'=>$invoices,
            'currencies'=>$contractsWithDownPaymentsCurrencies,
            'selectedCurrency'=>$selectedCurrency
        ]);
        
    }
    protected function formatInvoices(array $invoices, int $inEditMode)
    {
        return CustomerInvoice::formatInvoices($invoices, $inEditMode);
    }
    
    public function store(Company $company, StoreMoneyReceivedRequest $request, $returnModel = false, $accountNumberHasChanged=false)
    {
        /**
         * * الحفظ كله جوه ترانزاكشن واحدة
         * * وأي اتصال بأودو بيتنفذ بعد ما الترانزاكشن تكومِت (شوف OdooSync)
         */
        return OdooSync::transaction(function () use ($company, $request, $returnModel, $accountNumberHasChanged) {
            return $this->storeWithinTransaction($company, $request, $returnModel, $accountNumberHasChanged);
        });
    }

    protected function storeWithinTransaction(Company $company, StoreMoneyReceivedRequest $request, $returnModel = false, $accountNumberHasChanged=false)
    {

        $syncWithOdoo = !$request->has('stop-sync-with-odoo')  ;
        $hasUnappliedAmount = (bool)$request->get('unapplied_amount');
        $isGeneralDownPaymentOrSettlementOpening = $request->get('down_payment_type') == MoneyReceived::DOWN_PAYMENT_GENERAL || $request->get('down_payment_type') == MoneyReceived::SETTLEMENT_OF_OPENING_BALANCE;
		
        $partnerType = $request->get('partner_type', 'is_customer');
        $moneyType = $request->get('type');
        $financialInstitutionId = null;
        $contractId = $request->get('contract_id');
        $contractId = is_numeric($contractId) ? $contractId : null;
        $partnerId = $request->get('customer_id');
        $customer = Partner::find($partnerId);
        // ⚠️ REAL BUG FIXED HERE (2026-07-24 audit, Stage 5): Partner::find
        // can return null (stale dropdown / deleted partner / bad input);
        // previously crashed with "property id on null".
        if (! $customer) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'customer_id' => [__('Selected partner was not found.')],
            ]);
        }
        $customerId = $customer->id;
		$isDownPaymentOverContract = $request->get('down_payment_type') == MoneyReceived::DOWN_PAYMENT_OVER_CONTRACT;
        $receivedBankName = $request->get('receiving_branch_id') ;
        $data = $request->only(['type','receiving_date','currency','receiving_currency','customer_id','down_payment_type','partner_type','user_comment','transaction_type','journal_entry_id','account_bank_statement_line_id']);
        $data['currency'] = $isGeneralDownPaymentOrSettlementOpening ? $data['receiving_currency'] : $data['currency']??null;
        $receivingCurrency = $data['receiving_currency'];
        $data['currency'] = is_null($data['currency']) ?  $receivingCurrency : $data['currency'];
        $receivingDate = $data['receiving_date'];
		$receivingDate = Carbon::make($receivingDate)->format('Y-m-d');
		$date = $receivingDate;
        $currency = $data['currency'] ;
        $companyId = $company->id;
        $receivingCurrency = $data['receiving_currency'] ;
        $isDownPayment = $request->get('is_down_payment') && $request->has('sales_orders_amounts');
        $isDownPaymentFromMoneyReceived = $request->get('unapplied_amount', 0) > 0 && !$request->get('is_down_payment') && $moneyType =='is_customer';
        $data['money_type'] =  !$isDownPayment ? 'money-received' : 'down-payment';
        $data['money_type'] = $isDownPaymentFromMoneyReceived ? MoneyReceived::INVOICE_SETTLEMENT_WITH_DOWN_PAYMENT : $data['money_type'];
        $data['partner_id'] = $partnerId;
        $hasUnappliedOrIsDownPayment = $hasUnappliedAmount || $isDownPayment ;
        $data['user_id'] = auth()->user()->id ;
        $data['company_id'] = $company->id ;
        $data['has_unapplied_or_down_payment'] =$hasUnappliedOrIsDownPayment ;
        $draweeBankName = null;
        $relationData = [];
        $relationName = null ;
        $isTheSameCurrency = $currency == $receivingCurrency ;
        
    
        $amountInReceivingCurrency = $request->input('received_amount.'.$moneyType, 0) ;
        
        $amountInReceivingCurrency = unformat_number($amountInReceivingCurrency);
        $invoiceCurrencyAmount =  $isTheSameCurrency ? $amountInReceivingCurrency  : HArr::sumFormattedArr(array_column($request->get('settlements', []), 'settlement_amount'))  ;
		if(!$isTheSameCurrency && !$request->has('settlements') && $request->has('amount_in_invoice_currency')){
			$invoiceCurrencyAmount = $request->input('amount_in_invoice_currency.'.$moneyType);
		}
        if ($moneyType == MoneyReceived::CASH_IN_SAFE) {
            $relationData = $request->only(['receipt_number']) ;
            $relationData['receiving_branch_id'] = $this->generateBranchId($receivedBankName, $company->id) ;
            $relationName = 'cashInSafe';
        } elseif ($moneyType ==MoneyReceived::INCOMING_TRANSFER) {
            $relationName = 'incomingTransfer';
            $financialInstitutionId = $request->input('receiving_bank_id.'.MoneyReceived::INCOMING_TRANSFER);
            $relationData = [
                'receiving_bank_id'=>$financialInstitutionId,
                'account_number'=>$request->input('account_number.'.MoneyReceived::INCOMING_TRANSFER),
                'account_type'=>$request->input('account_type.'.MoneyReceived::INCOMING_TRANSFER)
            ];
        } elseif ($moneyType ==MoneyReceived::CASH_IN_BANK) {
            $relationName = 'cashInBank';
            $financialInstitutionId = $request->input('receiving_bank_id.'.MoneyReceived::CASH_IN_BANK) ;
            $relationData = [
                'receiving_bank_id'=>$financialInstitutionId,
                'account_number'=>$request->input('account_number.'.MoneyReceived::CASH_IN_BANK),
                'account_type'=>$request->input('account_type.'.MoneyReceived::CASH_IN_BANK)
            ];
        } elseif ($moneyType ==MoneyReceived::CHEQUE) {
            $relationName = 'cheque';
            $draweeBankId = $request->input('drawee_bank_id');
            $draweeBankName = Bank::find($draweeBankId)->getName();
			$dueDate = $request->input('due_date');
			$date= Carbon::make($dueDate);
            $relationData = [
                'due_date'=>$dueDate,
                'cheque_number'=>$request->input('cheque_number'),
                'drawee_bank_id'=>$draweeBankId,
                'branch_id'=>$request->input('cheque_branch_id')
            ];
        }
        $receivedBank = FinancialInstitution::find($financialInstitutionId);
        $receivedBankName = $receivedBank ? $receivedBank->getName() : $draweeBankName;
        $bankNameOrBranchName =  $moneyType == MoneyReceived::CASH_IN_SAFE ? Branch::find($relationData['receiving_branch_id'])->getName() : $receivedBankName ;
        $data['received_amount'] =$amountInReceivingCurrency ;
        $data['amount_in_invoice_currency'] = $invoiceCurrencyAmount ;
		$exchangeRate = $isTheSameCurrency ? 1 : number_unformat($request->input('exchange_rate.'.$moneyType, 1)) ;
		$mainFunctionalCurrency = $company->getMainFunctionalCurrency();
		 $exchangeRate = $isGeneralDownPaymentOrSettlementOpening ? ForeignExchangeRate::getExchangeRateForCurrencyAndClosestDate($currency, $mainFunctionalCurrency, $date, $company->id) :$exchangeRate;
		 if($isDownPaymentOverContract && $receivingCurrency == $mainFunctionalCurrency){
				$exchangeRate = $request->input('exchange_rate.'.$moneyType, 1);
		 }
		 if($isDownPaymentOverContract && $receivingCurrency != $mainFunctionalCurrency){
				$exchangeRate = ForeignExchangeRate::getExchangeRateForCurrencyAndClosestDate($receivingCurrency, $mainFunctionalCurrency, $date, $company->id);
		 }
		 
        $data['exchange_rate'] =$exchangeRate ;
        $data['contract_id'] = $contractId ;
        /**
         * @var AccountType $accountType ;
         */
        $accountType = AccountType::find($request->input('account_type.'.$moneyType));
        $accountNumber = $request->input('account_number.'.$moneyType);
        $receivingDate = Carbon::make($receivingDate)->format('Y-m-d');
        if (!$isDownPayment && !$isDownPaymentFromMoneyReceived) {
            unset($data['contract_id']);
        }
        $moneyReceived = MoneyReceived::create($data);
        

    
        
        $currency = $data['currency'] ?? null ;
        $receivingBranchId = $relationData['receiving_branch_id'] ?? null ;
        $relationData['company_id'] = $company->id ;
        $moneyReceived->$relationName()->create($relationData);
        /**
         * @var MoneyReceived $moneyReceived
         */
        $moneyReceived = $moneyReceived->refresh();
        $statementDate = $moneyReceived->getStatementDate();
        $moneyReceived->handleDebitStatement($financialInstitutionId, $accountType, $accountNumber, $moneyType, $statementDate, $amountInReceivingCurrency, $receivingCurrency, $receivingBranchId);
      
        
        /**
         * * For Money Received Only
         */
        
        $totalWithholdAmountAndSettlements = $moneyReceived->storeNewSettlement($request->get('settlements', []), $partnerId, $company, false, $syncWithOdoo);
        $totalWithholdAmount = $totalWithholdAmountAndSettlements['total_withhold_amount'];
        $moneyReceived->update([
            'total_withhold_amount'=>$totalWithholdAmount
        ]);
        
        /**
         * * For Contract Only
         */
        
     
        if ($hasUnappliedOrIsDownPayment) {
            $moneyReceived->storeNewSalesOrdersAmounts($request->get('sales_orders_amounts', []), $contractId, $customerId, $companyId, $amountInReceivingCurrency);
            // if ($company->hasOdooIntegrationCredentials() &&  $partnerType == 'is_customer') {
            //     $odooPaymentService = new OdooPayment($company);
            //     $odooPaymentService->createDownPayment($moneyReceived);
            // }
        }
		  if (($partnerType && $partnerType != 'is_customer') || ($isDownPayment || $isDownPaymentFromMoneyReceived)) {
            $moneyReceived->handlePartnerCreditStatement($partnerType, $partnerId, $moneyReceived->id, $company->id, $statementDate, $amountInReceivingCurrency, $receivingCurrency, $bankNameOrBranchName, $accountType, $accountNumber);
            $moneyReceived->storeNonCustomerOrSupplierOdooExpense(($isDownPayment || $isDownPaymentFromMoneyReceived));
        }
        

        $activeTab = $moneyType;
        if ($returnModel) {
            return $moneyReceived;
        }

        return redirect()->route('view.money.receive', ['company' => $company->id, 'active' => $activeTab])
            ->with('success', __('Money Received Has Been Saved Successfully'));
    }
 
    public function edit(Company $company, Request $request, MoneyReceived $moneyReceived, $customerInvoiceId = null)
    {
        $isDownPayment = $moneyReceived->isDownPayment();
        $partnerType = $moneyReceived->partner->getCustomerType();
        $customerInvoiceCurrencies = CustomerInvoice::getCurrencies($customerInvoiceId);
        $banks = Bank::pluck('view_name', 'id');
        $selectedBanks = MoneyReceived::getDrawlBanksForCurrentCompany($company->id);
        $selectedBranches = Branch::getBranchesForCurrentCompany($company->id);
        $financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get();

        // Cheque Under Collection deposit edits use the Index.vue collection
        // modal (same sendToCollection endpoint) — not a separate form page.
        if ($moneyReceived->isChequeUnderCollection()) {
            return redirect()->route('view.money.receive', [
                'company' => $company->id,
                'active' => MoneyReceived::CHEQUE_UNDER_COLLECTION,
            ]);
        }

        // Down Payment edit → Pages/MoneyReceived/DownPaymentForm.vue.
        if ($isDownPayment) {
            return $this->editDownPayment($company, $moneyReceived);
        }

        $accountTypes = AccountType::onlyCashAccounts()->get(['id', 'name_en', 'name_ar']);
        $financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->with('bank:id,view_name')->get(['id', 'type', 'name', 'bank_id']);
        $customers = Partner::orderBy('name')->where($partnerType, 1)->where('company_id', $company->id)->pluck('name', 'id')->toArray();
        $warningMessage = count($moneyReceived->settlementsForDownPaymentThatComeFromMoneyModel) ? __('Warning, please take care incase you changed the received amount, the invoices settled using this down payment will be deleted') : null;

        return Inertia::render('MoneyReceived/Form', [
            'company' => ['id' => $company->id, 'name' => $company->getName(), 'mainFunctionalCurrency' => $company->getMainFunctionalCurrency()],
            'model' => $this->serializeMoneyReceivedForForm($moneyReceived),
            'singleModel' => $customerInvoiceId,
            'invoiceNumber' => null,
            'warningMessage' => $warningMessage,
            'customers' => collect($customers)->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
            'partnerTypes' => collect(getAllPartnerTypesForCustomers())->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'moneyTypes' => $this->moneyTypeOptions(),
            'currencies' => collect($customerInvoiceCurrencies ?: getBanksCurrencies())->map(fn ($label, $code) => ['code' => $code, 'label' => strtoupper($label)])->values(),
            'selectedBranches' => collect($selectedBranches)->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
            'selectedBanks' => collect($banks)->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
            'financialInstitutionBanks' => $financialInstitutionBanks->map(fn ($b) => ['id' => $b->id, 'name' => $b->getName()]),
            'accountTypes' => $accountTypes->map(fn ($a) => ['id' => $a->id, 'name' => $a->getName()]),
            'urls' => array_merge($this->formUrls($company), [
                'update' => route('update.money.receive', ['company' => $company->id, 'moneyReceived' => $moneyReceived->id]),
            ]),
        ]);
    }

    /**
     * Serializes a MoneyReceived record into the exact plain-array shape
     * `Pages/MoneyReceived/Form.vue` expects to prefill the form. Invoice
     * settlement rows and down-payment sales-order rows are deliberately
     * NOT pre-built here — the Vue page fetches them itself from the
     * same real, unchanged AJAX endpoints the original jQuery form used
     * (`getInvoiceNumber` with `inEditMode=1`, `getSalesOrdersForContract`
     * with `down_payment_id`), so there is exactly one source of truth
     * for "what does this invoice/SO row look like", not two.
     */
    protected function serializeMoneyReceivedForForm(MoneyReceived $moneyReceived): array
    {
        $type = $moneyReceived->getType();

        return [
            'id' => $moneyReceived->id,
            'receiving_date' => $moneyReceived->getReceivingDate(),
            'partner_type' => $moneyReceived->getPartnerType(),
            'customer_id' => $moneyReceived->getPartnerId(),
            'customer_name' => $moneyReceived->getPartnerName(),
            'currency' => $moneyReceived->getCurrency(),
            'receiving_currency' => $moneyReceived->getReceivingCurrency(),
            'type' => $type,
            'transaction_type' => $moneyReceived->getTransactionType(),
            'user_comment' => $moneyReceived->getUserComment(),
            'received_amount' => $moneyReceived->getReceivedAmount(),
            'exchange_rate' => $moneyReceived->getExchangeRate(),
            'amount_in_invoice_currency' => $moneyReceived->getAmountInInvoiceCurrency(),
            'has_unapplied_or_down_payment' => (bool) $moneyReceived->has_unapplied_or_down_payment,
            'contract_id' => $moneyReceived->getContractId(),
            'contract_name' => $moneyReceived->getContractName(),
            'cash_in_safe' => $moneyReceived->isCashInSafe() ? [
                'receiving_branch_id' => $moneyReceived->getCashInSafeReceivingBranchId(),
                'receipt_number' => $moneyReceived->getCashInSafeReceiptNumber(),
            ] : null,
            'cash_in_bank' => $moneyReceived->isCashInBank() ? [
                'receiving_bank_id' => $moneyReceived->getCashInBankReceivingBankId(),
                'account_type_id' => $moneyReceived->getCashInBankAccountTypeId(),
                'account_number' => $moneyReceived->getCashInBankAccountNumber(),
            ] : null,
            'incoming_transfer' => $moneyReceived->isIncomingTransfer() ? [
                'receiving_bank_id' => $moneyReceived->getIncomingTransferReceivingBankId(),
                'account_type_id' => $moneyReceived->getIncomingTransferAccountTypeId(),
                'account_number' => $moneyReceived->getIncomingTransferAccountNumber(),
            ] : null,
            'cheque' => $moneyReceived->isCheque() ? [
                'drawee_bank_id' => $moneyReceived->getChequeDraweeBankId(),
                'due_date' => $moneyReceived->getChequeDueDate(),
                'cheque_number' => $moneyReceived->getChequeNumber(),
                'branch_id' => $moneyReceived->getCashInSafeReceivingBranchId(),
            ] : null,
        ];
    }

    /**
     * Down Payment edit — mirrors editDownPayment()'s counterpart for the
     * plain form. Sales orders (and their pre-filled amounts) are fetched
     * client-side from the same getSalesOrdersForContract endpoint,
     * passing this record's id as down_payment_id — same "one source of
     * truth" reasoning as the plain form's invoices.
     */
    protected function editDownPayment(Company $company, MoneyReceived $moneyReceived)
    {
        $selectedBranches = Branch::getBranchesForCurrentCompany($company->id);
        $selectedBanks = Bank::pluck('view_name', 'id');
        $financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->with('bank:id,view_name')->get(['id', 'type', 'name', 'bank_id']);
        $accountTypes = AccountType::onlyCashAccounts()->get(['id', 'name_en', 'name_ar']);
        $customers = Partner::orderBy('name')->where('is_customer', 1)->where('company_id', $company->id)
            ->when($moneyReceived->isDownPaymentOverContract(), fn ($q) => $q->onlyThatHaveCustomerContracts())
            ->pluck('name', 'id');
        $warningMessage = count($moneyReceived->settlementsForDownPaymentThatComeFromMoneyModel) ? __('Warning, please take care incase you changed the received amount, the invoices settled using this down payment will be deleted') : null;

        return Inertia::render('MoneyReceived/DownPaymentForm', [
            'company' => ['id' => $company->id, 'name' => $company->getName(), 'mainFunctionalCurrency' => $company->getMainFunctionalCurrency()],
            'model' => $this->serializeDownPaymentForForm($moneyReceived),
            'warningMessage' => $warningMessage,
            'customers' => collect($customers)->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
            'moneyTypes' => $this->moneyTypeOptions(),
            'currencies' => collect(getBanksCurrencies())->map(fn ($label, $code) => ['code' => $code, 'label' => strtoupper($label)])->values(),
            'selectedBranches' => collect($selectedBranches)->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
            'selectedBanks' => collect($selectedBanks)->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
            'financialInstitutionBanks' => $financialInstitutionBanks->map(fn ($b) => ['id' => $b->id, 'name' => $b->getName()]),
            'accountTypes' => $accountTypes->map(fn ($a) => ['id' => $a->id, 'name' => $a->getName()]),
            'urls' => array_merge($this->downPaymentFormUrls($company), [
                'update' => route('update.money.receive', ['company' => $company->id, 'moneyReceived' => $moneyReceived->id]),
            ]),
        ]);
    }

    protected function serializeDownPaymentForForm(MoneyReceived $moneyReceived): array
    {
        $type = $moneyReceived->getType();

        return [
            'id' => $moneyReceived->id,
            'receiving_date' => $moneyReceived->getReceivingDate(),
            'down_payment_type' => $moneyReceived->getDownPaymentType(),
            'currency' => $moneyReceived->getCurrency(),
            'customer_id' => $moneyReceived->getPartnerId(),
            'customer_name' => $moneyReceived->getPartnerName(),
            'receiving_currency' => $moneyReceived->getReceivingCurrency(),
            'type' => $type,
            'user_comment' => $moneyReceived->getUserComment(),
            'received_amount' => $moneyReceived->getReceivedAmount(),
            'exchange_rate' => $moneyReceived->getExchangeRate(),
            'amount_in_invoice_currency' => $moneyReceived->getAmountInInvoiceCurrency(),
            'contract_id' => $moneyReceived->getContractId(),
            'contract_name' => $moneyReceived->getContractName(),
            'cash_in_safe' => $moneyReceived->isCashInSafe() ? [
                'receiving_branch_id' => $moneyReceived->getCashInSafeReceivingBranchId(),
                'receipt_number' => $moneyReceived->getCashInSafeReceiptNumber(),
            ] : null,
            'cash_in_bank' => $moneyReceived->isCashInBank() ? [
                'receiving_bank_id' => $moneyReceived->getCashInBankReceivingBankId(),
                'account_type_id' => $moneyReceived->getCashInBankAccountTypeId(),
                'account_number' => $moneyReceived->getCashInBankAccountNumber(),
            ] : null,
            'incoming_transfer' => $moneyReceived->isIncomingTransfer() ? [
                'receiving_bank_id' => $moneyReceived->getIncomingTransferReceivingBankId(),
                'account_type_id' => $moneyReceived->getIncomingTransferAccountTypeId(),
                'account_number' => $moneyReceived->getIncomingTransferAccountNumber(),
            ] : null,
            'cheque' => $moneyReceived->isCheque() ? [
                'drawee_bank_id' => $moneyReceived->getChequeDraweeBankId(),
                'due_date' => $moneyReceived->getChequeDueDate(),
                'cheque_number' => $moneyReceived->getChequeNumber(),
                'branch_id' => $moneyReceived->getCashInSafeReceivingBranchId(),
            ] : null,
        ];
    }

    public function update(Company $company, StoreMoneyReceivedRequest $request, moneyReceived $moneyReceived)
    {
        //	$companyId = $company->id ;
        $newType = $request->get('type');
        /**
         * * التعديل معمول كـ حذف ثم إنشاء
         * * فلازم يكون كله في ترانزاكشن واحدة
         * * قبل كده لو أي حاجة ضربت في النص كان السجل القديم بيروح والجديد بيتعمل ناقص
         */
        OdooSync::transaction(function () use ($company, $request, $moneyReceived, $newType) {
            $oldSettlementsForMoneyReceivedWithDownPayment  = $moneyReceived->settlementsForDownPaymentThatComeFromMoneyModel ;
            $moneyReceivedAmountHasChanged = $moneyReceived->getAmount() != $request->input('received_amount.'.$newType);

            $moneyReceived->deleteRelations();
            $moneyReceived->delete();

            $newMoneyReceived = $this->storeWithinTransaction($company, $request, true);

            if (!$moneyReceivedAmountHasChanged) {
                $newMoneyReceived->storeNewSettlement(
                    $oldSettlementsForMoneyReceivedWithDownPayment->toArray(),
                    $newMoneyReceived->getPartnerId(),
                    $company,
                    1
                );
            }
        });
        $activeTab = $newType;

        return redirect()->route('view.money.receive', ['company' => $company->id, 'active' => $activeTab])
            ->with('success', __('Money Received Has Been Updated Successfully'));
    }
    
    public function destroy(Company $company, MoneyReceived $moneyReceived, DeleteMoneyReceivedRequest $request)
    {
        $activeTab = $moneyReceived->getType();
        OdooSync::transaction(function () use ($moneyReceived) {
            $moneyReceived->deleteRelations();
            $moneyReceived->delete();
        });
        return redirect()->route('view.money.receive', ['company'=>$company->id,'active'=>$activeTab])->with('success', __('Money Received Has Been Updated Successfully'));
    }
    protected function generateBranchId($nameOrId, $companyId)
    {
        $branch = Branch::where('id', $nameOrId)->first();
        if (!$branch) {
            $branch = Branch::create([
                'name'=>$nameOrId,
                'company_id'=>$companyId ,
                'created_by'=>auth()->user()->id
            ]);
        }
        return $branch->id ;
    }
    public function sendToCollection(Company $company, SendToUnderCollectionChequeRequest $request)
    {
        $hasOdooIntegration = $company->hasOdooIntegrationCredentials();
        $OdooPaymentService = null ;
        if ($hasOdooIntegration) {
            $OdooPaymentService = new OdooPayment($company);
        }
        $moneyReceivedIds = $request->get('cheques') ;
        $moneyReceivedIds = is_array($moneyReceivedIds) ? $moneyReceivedIds :  explode(',', $moneyReceivedIds);
        $data = $request->only(['deposit_date','drawl_bank_id','account_type','account_number','account_balance','clearance_days']);
        $data['account_type'] =  $request->input('account_type.'.MoneyReceived::CHEQUE_UNDER_COLLECTION);
        $data['account_number'] = $request->input('account_number.'.MoneyReceived::CHEQUE_UNDER_COLLECTION);
        $data['account_type'] = is_null($data['account_type']) ? $request->get('account_type') : $data['account_type'] ;
        $data['drawl_bank_id'] = $request->input('receiving_bank_id.'.MoneyReceived::CHEQUE_UNDER_COLLECTION, $request->get('drawl_bank_id'));
       
    
        $data['account_number'] = is_null($data['account_number']) ? $request->get('account_number') : $data['account_number'] ;
        $data['status'] = Cheque::UNDER_COLLECTION;
        
        foreach ($moneyReceivedIds as $moneyReceivedId) {
            /**
             * @var MoneyReceived $moneyReceived
             */
            $moneyReceived = MoneyReceived::find($moneyReceivedId) ;
            $isOpening = $moneyReceived->isOpenBalance();
            /**
             * Confirmed business rule (project owner, 2026-07-24): moving an already
             * under-collection cheque to a different account is an administrative correction,
             * not a real-world event — treated the same as a voluntary return to safe. Blocked
             * if the OLD facility's available room is less than the limit this cheque itself
             * contributed there, since that gap means real transactions already rely on the
             * room this cheque provided on that facility.
             */
            $oldCheque = $moneyReceived->cheque;
            $isAccountActuallyChanging = $oldCheque->status === Cheque::UNDER_COLLECTION
                && ((string) $oldCheque->account_type !== (string) $data['account_type'] || (string) $oldCheque->account_number !== (string) $data['account_number']);
            if ($isAccountActuallyChanging) {
                $oldAccountTypeModel = AccountType::find($oldCheque->account_type);
                if ($oldAccountTypeModel && $oldAccountTypeModel->isOverdraftAgainstCommercialPaperAccount()) {
                    $collateralContribution = $oldCheque->getActiveOverdraftAgainstCommercialPaperLimitContribution();
                    if ($collateralContribution) {
                        $collateralRule = new \App\Rules\OverdraftCollateralRemovalRule(
                            'overdraft_against_commercial_paper_bank_statements',
                            'overdraft_against_commercial_paper_id',
                            $collateralContribution['facility_id'],
                            $company->id,
                            $collateralContribution['amount']
                        );
                        if (!$collateralRule->passes('account_number', null)) {
                            return redirect()->back()->with('fail', $collateralRule->message());
                        }
                    }
                }
            }
            $data['expected_collection_date'] = $moneyReceived->cheque->calculateChequeExpectedCollectionDate($data['deposit_date'], $data['clearance_days']);
            $moneyReceived->cheque->update(array_merge($data, ['updated_at'=>now()]));
            if (!$isOpening) {
                if ($hasOdooIntegration) {
                    foreach ($moneyReceived->settlements as $settlement) {
                        $OdooPaymentService->reCreatePayment($settlement);
                    }
                    if ($moneyReceived->isInvoiceSettlementWithDownPayment()) {
                        $odooPaymentService = new OdooPayment($company);
                        $odooPaymentService->recreateDownPayment($moneyReceived);
                    }
                }
                $moneyReceived->handleOdooDownPayments($OdooPaymentService, $hasOdooIntegration);
                
            }
        }
        return redirect()->route('view.money.receive', ['company'=>$company->id,'active'=>MoneyReceived::CHEQUE_UNDER_COLLECTION])
            ->with('success', __('Cheques Sent To Collection Successfully'));
        
    }
    /**
     * * تحديد ان الشيك دا تم بالفعل صرفة من البنك ونزل في حسابك
     */
    public function applyCollection(Company $company, ApplyCollectionToChequeRequest $request, MoneyReceived $moneyReceived)
    {
        /**
         *
         * @var MoneyReceived $moneyReceived
         */
        // $collectionFeesAmount = $request->get('collection_fees',0) ;
        $actualCollectionDate = Carbon::make($request->get('actual_collection_date'))->format('Y-m-d')  ;
        $moneyReceived->cheque->update([
            'status'=>Cheque::COLLECTED,
            // 'collection_fees'=>$collectionFeesAmount,
            'actual_collection_date'=>$actualCollectionDate
        ]);
        $chequeNumber = $moneyReceived->cheque->getChequeNumber();
        $accountType = AccountType::find($moneyReceived->cheque->account_type) ;
        $currency = $moneyReceived->getReceivingCurrency();
        $receivedAmount = $moneyReceived->getReceivedAmount();
        // $receivingDate = $moneyReceived->getReceivingDate();
        $moneyType = MoneyReceived::CHEQUE;
        $accountNumber = $moneyReceived->cheque->account_number ;
        $financialInstitutionId = $moneyReceived->cheque->getDrawlBankId();
        $financialInstitution = $moneyReceived->cheque->getDrawlBank();
        /**
         * @var AccountType $accountType ;
         */
        $moneyReceived->handleDebitStatement($financialInstitutionId, $accountType, $accountNumber, $moneyType, $actualCollectionDate, $receivedAmount, $currency, null);
        // $moneyReceived->handleCreditStatement($company->id , $financialInstitutionId , $accountType,$accountNumber,'fees',$actualCollectionDate,$collectionFeesAmount,null,$currency,__('Cheque Collection Fees - Cheque [ :number ]' ,['number'=>$chequeNumber],'en' ),__('Cheque Collection Fees - Cheque [ :number ]' ,['number'=>$chequeNumber],'ar' ));
        
        $hasOdooIntegration = $company->hasOdooIntegrationCredentials();
        $OdooPaymentService = null ;
        if ($hasOdooIntegration) {
            $OdooPaymentService = new OdooPayment($company);
        }
        
        
        if ($hasOdooIntegration && $company->withinIntegrationDate($actualCollectionDate)) {
            $odooSetting = $company->odooSetting;
            $hasSettlements = $moneyReceived->settlements->count();
            $items = $hasSettlements ? $moneyReceived->settlements : [$moneyReceived];
            $items = $hasSettlements ? $moneyReceived->settlements : [$moneyReceived];
       
            if ($moneyReceived->isInvoiceSettlementWithDownPayment()) {
                $items->push($moneyReceived);
            }
            foreach ($items as $settlementOrMoneyModel) {
                $odooId = $settlementOrMoneyModel->odoo_id ;
				$isMoneyReceived = $settlementOrMoneyModel instanceof MoneyReceived ;
                $isOpeningAndMoneyReceivedBalance = $isMoneyReceived && $settlementOrMoneyModel->isOpenBalance() ;
                /**
                 * * التحصيل بيبعت عملة التحصيل مع مبلغ بنفس العملة، يعني القيد
                 * * متسق أصلاً — وعلشان كده مفيش شكوى منه زي ما في جهة الصرف
                 * * (الصرف بيبعت عملة الفاتورة مع مبلغ بالجنيه فبيحصل التعارض)
                 * * ما بنبعتش amount_currency هنا عمدًا: القيد ده بيتسوّى مع
                 * * الـ account.payment المتعمول بعملة التحصيل، فلو خلّيناه بعملة
                 * * الفاتورة كنا هنخلي سطرين بعملتين مختلفتين يتسووا مع بعض
                 */
                $odooCurrencyId =Currency::getOdooId($currency);
                $accountTypeId=$moneyReceived->cheque->getAccountTypeId();
                $accountNumber = $moneyReceived->cheque->getAccountNumber();
                $journalId = $financialInstitution->getJournalIdForAccount($accountTypeId, $accountNumber);
                $debitAccountOdooId = $financialInstitution->getOdooIdForAccount($accountTypeId, $accountNumber);
                $creditOdooAccountId = $odooSetting->getChequesReceivableId();
                $odooPartnerId = $moneyReceived->getPartnerOdooId();
                $amountInMainFunctionalCurrency= $settlementOrMoneyModel->getAmountInReceivingCurrency();
				if($isMoneyReceived && $moneyReceived->isInvoiceSettlementWithDownPayment() ){
					$amountInMainFunctionalCurrency = $moneyReceived->downPaymentSettlements->sum('down_payment_amount') * $moneyReceived->getExchangeRate() ;
				}
                $ref = 'Cheque Collection ' . $settlementOrMoneyModel->getInvoiceNumber();
                if ($isOpeningAndMoneyReceivedBalance) {
                    $settlementOrMoneyModel->markOpeningPayableChequeAsPaidInOdoo(true);
                } else {
                    $res =$OdooPaymentService->chequeCollection($odooId, $amountInMainFunctionalCurrency, $actualCollectionDate, $odooCurrencyId, $journalId, $debitAccountOdooId, $creditOdooAccountId, $odooPartnerId, $ref);
                    $settlementOrMoneyModel->update([
                        'account_bank_statement_line_id'=>$res['statement_entry_id']??null,
                        'odoo_reference'=>$res['bank_reference']??null
                    ]);
                    
                }
            }
        }
        return redirect()->route('view.money.receive', ['company'=>$company->id,'active'=>MoneyReceived::CHEQUE_COLLECTED])->with('success', __('Cheque Is Returned To Safe'));
    }
    public function sendToUnderCollection(Company $company, BackToUnderCollectionChequeRequest $request, MoneyReceived $moneyReceived)
    {
        $isOpenBalance=  $moneyReceived->isOpenBalance();
        $updateChequeData = [
            'status'=>Cheque::UNDER_COLLECTION,
            // 'collection_fees'=>null,
            'actual_collection_date'=>null
        ] ;

    
        $moneyReceived->cheque->update($updateChequeData);

        while ($currentStatement = $moneyReceived->getCurrentStatement()) {
            $currentStatement->delete();
            $moneyReceived = $moneyReceived->refresh();
        }
        $hasOdooIntegration = $company->hasOdooIntegrationCredentials();
        $OdooPaymentService = null ;
        if ($hasOdooIntegration && !$isOpenBalance) {
            $OdooPaymentService = new OdooPayment($company);
            $hasSettlements = $moneyReceived->settlements->count();
            $items = $hasSettlements ? $moneyReceived->settlements : [$moneyReceived];
            if ($moneyReceived->isInvoiceSettlementWithDownPayment()) {
                $items->push($moneyReceived);
            }
            foreach ($items as $settlementOrMoneyModel) {
                if ($settlementOrMoneyModel->account_bank_statement_line_id) {
                    $OdooPaymentService->unlinkBankCollection($settlementOrMoneyModel->account_bank_statement_line_id);
                }
            }
        }

        if ($hasOdooIntegration && $isOpenBalance) {
            $moneyReceived->unlinkNonCustomerOrSupplierOdooExpense();
            $moneyReceived->update([
            'odoo_reference'=>null,
            'journal_entry_id'=>null ,
            'account_bank_statement_line_id'=>null
            ]);
        }

        
        $moneyReceived->handleOdooDownPayments($OdooPaymentService, $hasOdooIntegration);
        
        return redirect()->route('view.money.receive', ['company'=>$company->id,'active'=>MoneyReceived::CHEQUE_UNDER_COLLECTION])->with('success', __('Cheque Is Under Collection'));
        
    }
    public function sendToSafe(Company $company, Request $request, MoneyReceived $moneyReceived)
    {
        
        $hasOdooIntegration = $company->hasOdooIntegrationCredentials();
        $OdooPaymentService = null ;
        if ($hasOdooIntegration) {
            $OdooPaymentService = new OdooPayment($company);
        }
        $isOpeningBalance = $moneyReceived->isOpenBalance();
        /**
         * Confirmed business rule (project owner, 2026-07-24): a cheque returning to the safe
         * (a VOLUNTARY action, not a rejection) must be blocked if the facility's available room
         * is less than the limit this cheque itself contributed — that gap means real
         * transactions already rely on the room this cheque provided.
         */
        $collateralContribution = $moneyReceived->cheque->getActiveOverdraftAgainstCommercialPaperLimitContribution();
        if ($collateralContribution) {
            $collateralRule = new \App\Rules\OverdraftCollateralRemovalRule(
                'overdraft_against_commercial_paper_bank_statements',
                'overdraft_against_commercial_paper_id',
                $collateralContribution['facility_id'],
                $company->id,
                $collateralContribution['amount']
            );
            if (!$collateralRule->passes('status', null)) {
                return redirect()->back()->with('fail', $collateralRule->message());
            }
        }
        $moneyReceived->cheque->update([
            'status'=>Cheque::IN_SAFE,
            'deposit_date'=>null ,
            'drawl_bank_id'=>null ,
            'account_type'=>null ,
            'account_number'=>null ,
            'account_balance'=>null ,
            'expected_collection_date'=>null ,
            'clearance_days'=>null
        ]);
        
        if ($hasOdooIntegration && !$isOpeningBalance) {
            foreach ($moneyReceived->settlements as $settlement) {
                $OdooPaymentService->reCreatePayment($settlement);
            }
            if ($moneyReceived->isInvoiceSettlementWithDownPayment()) {
                $odooPaymentService = new OdooPayment($company);
                $odooPaymentService->recreateDownPayment($moneyReceived);
            }
                    
        }
        return redirect()->route('view.money.receive', ['company'=>$company->id,'active'=>MoneyReceived::CHEQUE])->with('success', __('Cheque Is Returned To Safe'));
    }
    public function sendToSafeAsRejected(Company $company, Request $request, MoneyReceived $moneyReceived)
    {
        
        $moneyReceived->cheque->update([
            'status'=>Cheque::REJECTED,
            'deposit_date'=>null ,
            'drawl_bank_id'=>null ,
            'account_type'=>null ,
            'account_number'=>null ,
            'account_balance'=>null ,
            'expected_collection_date'=>null ,
            'clearance_days'=>null
        ]);
        
        return redirect()->route('view.money.receive', ['company'=>$company->id,'active'=>MoneyReceived::CHEQUE_REJECTED])->with('success', __('Cheque Is Returned To Safe'));
        
    }

    public function getAccountNumbersForAccountType(Company $company, Request $request, string $accountType, ?string $selectedCurrency=null, ?int $financialInstitutionId = 0)
    {
        $accountType = AccountType::find($accountType);
        $modelName = $accountType->getModelName() ;
        $accountNumberModel =  ('\App\Models\\'.$modelName)::getAllAccountNumberForCurrency($company->id, $selectedCurrency, $financialInstitutionId);
        return response()->json([
            'status'=>true ,
            'data'=>$accountNumberModel
            
        ]);
    }
    public function getAccountIdsForAccountType(Company $company, Request $request, string $accountType, ?string $selectedCurrency=null, ?int $financialInstitutionId = 0)
    {
        $accountType = AccountType::find($accountType);
        $modelName = $accountType->getModelName() ;
        $accountNumberModel =  ('\App\Models\\'.$modelName)::getAllAccountNumberForCurrency($company->id, $selectedCurrency, $financialInstitutionId, 'id');
        return response()->json([
            'status'=>true ,
            'data'=>$accountNumberModel
            
        ]);
    }
    public function getAccountAmountForAccountId(Company $company, Request $request, string $accountTypeId, int $accountId, int $financialInstitutionId)
    {
    
        
        $accountType = AccountType::find($accountTypeId);
        $accountNumberModel =  ('\App\Models\\'.$accountType->getModelName())::find($accountId);
        $accountNumber = $accountNumberModel ? $accountNumberModel->account_number : '';
        $currencyName = $accountNumberModel ? $accountNumberModel->currency : '';
    
        return response()->json([
            'status'=>true ,
            'amount'=>$accountNumberModel ? $accountNumberModel->getAmount($currencyName, $accountNumber, $financialInstitutionId, $company->id) : 0 ,
       //     'interest_rate'=>$accountNumberModel ? $accountNumberModel->getInterestRate() : 0,
            'currencyName'=>$currencyName
        ]);
    }
    public function updateNetBalanceBasedOnAccountNumber(Request $request, Company $company, $accountTypeId = null, $accountNumber = null, $financialInstitutionId = null, $statementDate = null)
    {
        $additionalAmountInEditMode=  0 ;
        // $additionalAmountInEditMode = number_unformat($request->get('additionalBalanceInEditMode',0));
        $model = null ;
 
        $netBalanceDate = '' ;
        $accountTypeId = $request->get('accountType', $accountTypeId);
        $accountType = AccountType::find($accountTypeId);
        $statementDate = $statementDate ?: $request->get('balanceDate');
        $statementDate = $statementDate ?: now()->format('Y-m-d');
        $statementDate = Carbon::make($statementDate)->format('Y-m-d');
        
        $accountNumber = $request->get('accountNumber', $accountNumber);
        
        $financialInstitutionId = $request->get('financialInstitutionId', $financialInstitutionId);
        if (!$accountType) {
            return response()->json([
                'status'=>true ,
                'balance'=>0,
                'net_balance'=>0 ,
            ]);
        }
   
        $accountNumberModel =  ('\App\Models\\'.$accountType->getModelName())::findByAccountNumber($accountNumber, $company->id, $financialInstitutionId);
        
        if (!$accountNumberModel) {
            
                return response()->json(
                    [
                        'status'=>true ,
                        'balance'=>0,
                        'net_balance'=>0 ,
                    ]
                );
          
        }
        
        if ($request->has('modelId')) {
            $modelId = $request->get('modelId')  ;
            $modelType = $request->get('modelType');
            $model = ('App\Models\\'.$modelType)::find($modelId);
            $oldAccountNumber = $model ? $model->getAccountNumber() : null;
            $oldAccountTypeId = $model ? $model->getAccountTypeId() : null;
            $statementDate = $model && $model->payableCheque ? $model->payableCheque->due_date : $statementDate ;
            // $oldFinancialInstitution = $model ? $model->getAccountTypeId() : null;
            if ($oldAccountNumber && $oldAccountNumber == $accountNumber
            && $oldAccountTypeId && $oldAccountTypeId == $accountTypeId
            ) {
                $additionalAmountInEditMode =  $model->getPaidAmount();
            }
        }
		
	
        
        $statementTableName = $accountNumberModel->getStatementTableName() ;
        $foreignKeyName = $accountNumberModel->getForeignKeyInStatementTable();
        $balanceRow = DB::table($statementTableName)->where($foreignKeyName, $accountNumberModel->id)->where('date', '<=', $statementDate)->orderByRaw('date desc , id desc')->first();
        $NetBalanceRow = DB::table($statementTableName)->where($foreignKeyName, $accountNumberModel->id)->orderByRaw('date desc , id desc')->first();
		
        $column = $accountType->isOverdraftAccount() ? 'room' : 'end_balance';
        $balance = 0;
        $balanceDate = '';

        $netBalance = 0;
        if ($balanceRow) {
            $balance = $balanceRow->{$column} ;
            $balanceDate = Carbon::make($balanceRow->date)->format('d-m-Y') ;
        }
        if ($NetBalanceRow) {
            $netBalance =$NetBalanceRow->{$column} ;
            $netBalanceDate =Carbon::make($NetBalanceRow->date)->format('d-m-Y') ;
        }
        return response()->json([
            'status'=>true ,
            'balance'=>$balance+$additionalAmountInEditMode,
            'net_balance'=>$netBalance+$additionalAmountInEditMode ,
            'balance_date'=>$balanceDate,
            'net_balance_date'=>$netBalanceDate ,
        ]);

    }
    
    public function updateNetBalanceBasedOnAccountIdByAjax(Request $request, Company $company, $accountType, $accountId, $financialInstitutionId)
    {
        $accountTypeId = $accountType ;
        $account = AccountType::find($accountTypeId);
        $fullModelName = 'App\Models\\'.$account->getModelName() ;
        $accountNumber = $fullModelName::find($accountId)->account_number;
        
        return $this->updateNetBalanceBasedOnAccountNumber((new Request())->replace([
            'accountType'=>$accountTypeId,
            'accountNumber'=>$accountNumber ,
            'financialInstitutionId'=>$financialInstitutionId
        ]), $company);
    }
    
    public function getCustomersWithOpeningBalance(Request $request, Company $company)
    {
        $type =$request->get('type') ;
        $partners = [];
        if ($type == 'over_contract') {
            $partners=  Partner::onlyThatHaveCustomerContracts()->where('is_customer', 1)->orderBy('name')
                                    ->where('company_id', $company->id)->pluck('id', 'name');
        } elseif ($type == 'general') {
            $partners =  Partner::where('is_customer', 1)->orderBy('name')
                                    ->where('company_id', $company->id)->pluck('id', 'name');
        } elseif ($type == 'settlement-of-opening-balance') {
            $partners = CustomerInvoice::orderBy('customer_name')
            ->whereNotNull('opening_balance_id')
            ->where('company_id', $company->id)->pluck('customer_id', 'customer_name');
        }
        return response()->json([
            'invoices' => $partners
        ]);
        
    }
    public function getCustomersBasedOnCurrency(Request $request, Company $company, string $currencyName)
    {
        return response()->json([
            'customerInvoices' => CustomerInvoice::orderBy('customer_name')->
            where('currency', $currencyName)
            ->where('company_id', $company->id)->pluck('customer_id', 'customer_name')
            
        ]);
    }
    public function getPartnersBasedOnCurrency(Request $request, Company $company, string $currencyName)
    {
        $partnerColumnName = $request->get('partnerColumnName');

        if ($partnerColumnName == 'is_customer') {
            $partners = CustomerInvoice::orderBy('customer_name')->where('currency', $currencyName)->where('company_id', $company->id)->pluck('customer_id', 'customer_name');
        } else {
            $partners = Partner::orderBy('name')->where('company_id', $company->id)->where($partnerColumnName, 1)->pluck('id', 'name')->toArray();
        }
        return response()->json([
            'partners'=>$partners
        ]);
    }
    public function markAsConfirmed(Company $company, Request $request, int $modelId)
    {
        $tableName = $request->get('table_name');
        DB::table($tableName)->where('id', $modelId)->update([
            'is_reviewed'=>1,
            'reviewed_by'=>auth()->user()->id
        ]);
        return redirect()->back();
    }
    public function resendToOdoo(Company $company, Request $request, MoneyReceived $moneyReceived)
    {
        $OdooPaymentService = new OdooPayment($company);
        foreach ($moneyReceived->settlements as $payment) {
            $OdooPaymentService->reCreatePayment($payment);
        }
        if (!session()->has('fail') && $moneyReceived->hasUnappliedOrDownPayment()) {
            $OdooPaymentService->RecreateDownPayment($moneyReceived);
        }
        return back();
    }
    
    

}
