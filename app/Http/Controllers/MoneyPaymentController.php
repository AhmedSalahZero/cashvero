<?php
namespace App\Http\Controllers;

use App\Helpers\HArr;
use App\Http\Requests\DeleteMoneyPaymentRequest;
use App\Http\Requests\MarkChequeAsPaidRequest;
use App\Http\Requests\StoreMoneyPaymentRequest;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\CashVeroBranch;
use App\Models\Company;
use App\Models\Contract;
use App\Models\CustomerInvoice;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionAccount;
use App\Models\ForeignExchangeRate;
use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use App\Models\Partner;
use App\Models\PayableCheque;
use App\Models\PurchaseOrder;
use App\Models\SupplierInvoice;
use App\Services\Api\OdooPayment;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

/**
 * MoneyPaymentController
 * ------------------------------------------------------------------
 * Treasury Operations → "Money Payment" — the supplier-side mirror of
 * Money Received (MoneyReceivedController). Every way cash physically
 * or virtually LEAVES the company: cheques issued (payable), outgoing
 * bank transfers, and cash paid out. Also handles "Down Payment"
 * money paid (an advance not yet tied to a specific supplier invoice)
 * via the same underlying `money_payments` table, distinguished by
 * `money_type` — same shape as Money Received's own down payment.
 *
 * ── Real differences from Money Received (confirmed against this
 *    codebase, not assumed) — worth knowing before touching this file:
 * - Only 3 index tabs, not 7: Payable Cheques, Outgoing Transfer, Cash
 *   Payment. There is no cheque-collection sub-lifecycle (Under
 *   Collection/Collected/Rejected) — we aren't depositing these
 *   cheques for clearance, the recipient is. The old markup for those
 *   4 extra tabs exists in the original Blade but is commented out —
 *   genuinely dead, not a gap.
 * - The payable-cheque lifecycle is ONE step ("Mark As Paid"), not
 *   two — no separate "send to collection" stage, since the bank/
 *   account is already fixed on the cheque at creation time.
 *   markChequesAsPaid() also does a real balance check before
 *   allowing it (via AmountCanNotBeGreaterThanEndBalanceAtPaymentDate)
 *   and reuses MoneyReceivedController::updateNetBalanceBasedOnAccountNumber
 *   directly — a genuine cross-controller call, not a mistake.
 * - The Outgoing Transfer tab's batch "Mark As Paid" is non-functional
 *   in the ORIGINAL app: its route (outgoing.transfer.mark.as.paid)
 *   points at MoneyPaymentController::markOutgoingTransfersAsPaid,
 *   which does not exist on this controller (only CashExpenseController
 *   has a method by that name — a different feature). The original
 *   Blade's own checkbox column for this tab is ALSO commented out,
 *   confirming this was already disabled, not merely broken. This
 *   migration matches that: no batch selection/action is rendered for
 *   Outgoing Transfer. Flagged to the project owner rather than
 *   silently inventing real behavior for a route that's never worked.
 * - "Opening balance" payable cheques are edited through a separate,
 *   still-Blade flow (`_edit_opening_balance_cheque.blade.php` +
 *   updateOpeningPayableCheque()) — not migrated in this pass.
 * - A supplier payment can additionally be "allocated" against a
 *   *customer's* contract (`storeNewAllocation()`) — a feature with no
 *   equivalent on the Money Received side. Scoped for the Form page,
 *   not the Index page.
 * - Review permission gate: unlike Money Received (which uses a real,
 *   seeded `review money received` permission via getReviewPermissionName()),
 *   the review modal here is gated directly on `update supplier payment`
 *   in the original Blade — getReviewPermissionName('MoneyPayment')
 *   returns 'review supplier payments' (plural), which is NOT a seeded
 *   permission in HAuth.php, so that helper is unusable here. Matched
 *   the Blade's actual gate, not the unused helper.
 * - "Resend To Odoo" is ALSO broken in the original for this model:
 *   the shared `_user_odoo_modal.blade.php` partial hardcodes its
 *   form action as `route('resend.with.odoo', ['moneyReceived'=>...])`
 *   regardless of which model included it, and that route is hard-
 *   bound to `MoneyReceivedController::resendToOdoo(MoneyReceived
 *   $moneyReceived)` — passing a MoneyPayment id 404s (different
 *   table, unrelated id sequence). The Odoo-error DISPLAY still works
 *   (plain attribute reads); the "Resend" action does not, and isn't
 *   wired here for that reason — see mapMoneyPaymentRow().
 *
 * ── Frontend migration status (as of this file's last update) ──────
 * - index() → ✅ Inertia/Vue (`Pages/MoneyPayment/Index.vue`).
 * - create()/store()/edit()/update()/destroy() → 🔲 still Blade, next.
 * - store()/update()/markChequesAsPaid()'s responses were changed from
 *   a raw JSON body (correct for the old jQuery-AJAX page) to real
 *   redirects — required for Inertia, same fix already applied twice
 *   on the Money Received side (bug #19/#22 in the Roadmap). Fixed
 *   proactively here since markChequesAsPaid() is already called from
 *   the new Vue Index page's "Mark As Paid" action.
 */
class MoneyPaymentController
{
    use GeneralFunctions;

// 	protected function applyFilter(Request $request, $query)
// {
//     $searchFieldName = $request->get('field');
//     $from = $request->get('from');
//     $to = $request->get('to');
//     $value = $request->query('value');

//     // تحديد اسم الحقل للتاريخ
//     $dateFieldName = ($searchFieldName === 'due_date') ? 'due_date' : 'delivery_date';
//     $query->when($value, function ($q) use ($value, $searchFieldName) {
//         $q->where(function($subQuery) use ($value, $searchFieldName) {
//             $subQuery->where($searchFieldName, 'LIKE', "%{$value}%")
//                      ->orWhereHas('payableCheque', function($relationQ) use ($value, $searchFieldName) {
//                          $relationQ->where($searchFieldName, 'LIKE', "%{$value}%");
//                      });
//         });
//     });

//     $query->when($from, function ($q) use ($dateFieldName, $from) {
//         $q->where($dateFieldName, '>=', $from);
//     });

//     $query->when($to, function ($q) use ($dateFieldName, $to) {
//         $q->where($dateFieldName, '<=', $to);
//     });

//     return $query->orderByDesc('delivery_date');
// }

    // protected function applyFilter2(Request $request,  $collection):Collection
    // {
    //     if (!count($collection)) {
    //         return $collection;
    //     }
    //     $searchFieldName = $request->get('field');
    //     $dateFieldName = $searchFieldName === 'due_date' ? 'due_date' : 'delivery_date';
    //     if ($searchFieldName =='delivery_date') {
    //         $dateFieldName = 'delivery_date';
    //     }
    //     $from = $request->get('from');
    //     $to = $request->get('to');
    //     $value = $request->query('value');
    //     $collection = $collection
    //     ->when($request->has('value'), function ($collection) use ($request, $value, $searchFieldName) {
    //         return $collection->filter(function ($moneyPayment) use ($value, $searchFieldName) {
    //             /**
    //              * @var MoneyPayment $moneyPayment
    //              */
    //             $currentValue = $moneyPayment->{$searchFieldName} ;
    //             // $moneyPaymentRelationName cash-in-safe -> cashInSafe relation ship name
    //             $moneyPaymentRelationName = dashesToCamelCase(Request('active')) ;
    //             $relationRecord = $moneyPayment->$moneyPaymentRelationName ;
    //             /**
    //              * * بمعني لو مالقناش القيمة في جدول ال
    //              * * moneyPayment
    //              * * هندور عليها في العلاقه
    //              */
    //             $currentValue = is_null($currentValue) && $relationRecord ? $relationRecord->{$searchFieldName}  :$currentValue ;
    //             if ($searchFieldName == 'delivery_branch_id') {
    //                 $currentValue = $moneyPayment->getCashPaymentBranchName() ;
    //             }
    //             if ($searchFieldName == 'delivery_bank_id') {
    //                 $currentValue = $moneyPayment->payableCheque ? $moneyPayment->payableCheque->getDeliveryBankName() :0 ;
    //             }
    //             return false !== stristr($currentValue, $value);
    //         });
    //     })
    //     ->when($request->get('from'), function ($collection) use ($dateFieldName, $from) {
    //         return $collection->where($dateFieldName, '>=', $from);
    //     })
    //     ->when($request->get('to'), function ($collection) use ($dateFieldName, $to) {
    //         return $collection->where($dateFieldName, '<=', $to);
    //     })
    //     ->sortByDesc('delivery_date')->values();
    //     return $collection;
    // }
    /**
     * The 3 tabs on the Money Payment index page, in the original's
     * nav-tabs order. See class docblock for why there are only 3
     * (no cheque-collection sub-lifecycle on the payment side).
     */
    protected function tabDefinitions(): array
    {
        return [
            MoneyPayment::PAYABLE_CHEQUE => [
                'label' => __('Payable Cheques'),
                'query' => 'getMoneyPaymentPayableCheques',
                'page' => 'payableChequesPage',
                'searchFields' => [
                    'partner_name' => __('Supplier Name'),
                    'delivery_date' => __('Payment Date'),
                    'cheque_number' => __('Cheque Number'),
                    'currency' => __('Currency'),
                    'payment_currency' => __('Payment Currency'),
                    'payment_bank_name' => __('Payment Bank'),
                    'due_date' => __('Due Date'),
                ],
            ],
            MoneyPayment::OUTGOING_TRANSFER => [
                'label' => __('Outgoing Transfer'),
                'query' => 'getMoneyPaymentOutgoingTransfer',
                'page' => 'outgoingTransferPage',
                'searchFields' => [
                    'partner_name' => __('Supplier Name'),
                    'delivery_date' => __('Payment Date'),
                    'payment_bank_name' => __('Payment Bank'),
                    'currency' => __('Currency'),
                    'payment_currency' => __('Payment Currency'),
                    'account_number' => __('Account Number'),
                ],
            ],
            MoneyPayment::CASH_PAYMENT => [
                'label' => __('Cash Payment'),
                'query' => 'getMoneyPaymentCashPayments',
                'page' => 'cashPaymentsPage',
                'searchFields' => [
                    'partner_name' => __('Supplier Name'),
                    'delivery_date' => __('Payment Date'),
                    'delivery_branch_name' => __('Branch'),
                    'currency' => __('Currency'),
                    'payment_currency' => __('Payment Currency'),
                    'receipt_number' => __('Receipt Number'),
                ],
            ],
        ];
    }

    /**
     * Money Payment index — the Treasury Operations "Money Payment"
     * list. Same shape as MoneyReceivedController::index() (see that
     * file for the fuller rationale) — each tab keeps its own real,
     * server-side-paginated, server-side-searchable query, unchanged.
     */
    public function index(Company $company, Request $request)
    {
        $paginationPerPage = GeneralFunctions::getPaginationLimit();
        $numberOfMonthsBetweenEndDateAndStartDate = 18;
        $activeTab = $request->get('active', MoneyPayment::PAYABLE_CHEQUE);
        $tabs = $this->tabDefinitions();

        $filterDates = [];
        foreach (MoneyPayment::getAllTypes() as $type) {
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

            $query = $company->{$definition['query']}($startDate, $endDate, $activeTab);

            $totalCount = (clone $query)->count();
            $totalAmount = (clone $query)->sum('paid_amount');

            $paginator = $query->paginate($paginationPerPage, ['*'], $definition['page']);
            $paginator->appends(array_merge($request->except('page'), ['active' => $type]));

            $paginatorArray = $paginator->toArray();
            $paginatorArray['data'] = $activeTab === $type
                ? $paginator->getCollection()->map(fn (MoneyPayment $moneyPayment) => $this->mapMoneyPaymentRow($moneyPayment, $type, $company))->all()
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

        return Inertia::render('MoneyPayment/Index', [
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
                'canCreate' => $user->can('create supplier payment'),
                'canUpdate' => $user->can('update supplier payment'),
                'canDelete' => $user->can('delete supplier payment'),
            ],
            'companyHasOdoo' => $company->hasOdooIntegrationCredentials(),
            'urls' => [
                'index' => route('view.money.payment', ['company' => $company->id]),
                'createMoneyPayment' => route('create.money.payment', ['company' => $company->id]),
                'createDownPayment' => route('create.money.payment', ['company' => $company->id, 'type' => 'down-payment']),
                'markChequesAsPaid' => route('payable.cheque.mark.as.paid', ['company' => $company->id]),
                'balanceForAccountNumber' => route('update.balance.and.net.balance.based.on.account.number', ['company' => $company->id]),
            ],
        ]);
    }

    /**
     * Builds one Money Payment index-table row as a plain array, every
     * value pre-formatted and every URL pre-resolved — same reasoning
     * as MoneyReceivedController::mapMoneyReceivedRow().
     */
    protected function mapMoneyPaymentRow(MoneyPayment $moneyPayment, string $type, Company $company): array
    {
        $common = [
            'id' => $moneyPayment->id,
            'type_formatted' => $moneyPayment->getMoneyTypeFormatted(),
            'partner_name' => $moneyPayment->getSupplierName(),
            'delivery_date' => $moneyPayment->getDeliveryDate(),
            'delivery_date_formatted' => $moneyPayment->getDeliveryDateFormatted(),
            'paid_amount_formatted' => $moneyPayment->getPaidAmountFormatted(),
            'currency' => $moneyPayment->getPaymentCurrency(),
            'currency_formatted' => $moneyPayment->getCurrencyToPaymentCurrencyFormatted(),
            'is_open_balance' => $moneyPayment->isOpenBalance(),
            'is_reviewed' => $moneyPayment->isReviewed(),
            'has_comment' => $moneyPayment->hasComment(),
            'user_comment' => $moneyPayment->hasComment() ? $moneyPayment->getUserComment() : null,
            'has_odoo_error' => $company->hasOdooIntegrationCredentials() && $moneyPayment->hasOdooError(),
            'odoo_error' => $moneyPayment->hasOdooError() ? $moneyPayment->getOdooError() : null,
            'is_fully_integrated_with_odoo' => $company->hasOdooIntegrationCredentials() && $moneyPayment->fullyIntegratedWithOdoo(),
            'odoo_reference_names' => $company->hasOdooIntegrationCredentials() && $moneyPayment->fullyIntegratedWithOdoo() ? $moneyPayment->getOdooReferenceNames() : [],
            'edit_url' => route('edit.money.payment', ['company' => $company->id, 'moneyPayment' => $moneyPayment->id]),
            'delete_url' => route('delete.money.payment', ['company' => $company->id, 'moneyPayment' => $moneyPayment->id]),
            'review_url' => route('confirmed.review', ['company' => $company->id, 'model' => $moneyPayment->id]),
            // ⚠️ No resend_odoo_url here — see class docblock. The
            // shared _user_odoo_modal partial's "Resend" button posts
            // to a route hard-bound to MoneyReceivedController's
            // MoneyReceived $moneyReceived type-hint; passing a
            // MoneyPayment id 404s (different table, unrelated id
            // sequence). Genuinely broken in the original, not
            // something to wire up here without a real fix decision.
        ];

        return match ($type) {
            MoneyPayment::PAYABLE_CHEQUE => array_merge($common, [
                'status_formatted' => $moneyPayment->payableCheque?->getStatusFormatted(),
                'is_paid' => $moneyPayment->payableCheque?->getStatus() === 'paid',
                'cheque_number' => $moneyPayment->payableCheque?->getChequeNumber(),
                'payment_bank_name' => $moneyPayment->payableCheque?->getDeliveryBankName(),
                'account_type_name' => $moneyPayment->payableCheque?->getAccountTypeName(),
                'account_number' => $moneyPayment->payableCheque?->getAccountNumber(),
                'due_date' => $moneyPayment->payableCheque?->getDueDate(),
                'due_date_formatted' => $moneyPayment->payableCheque?->getDueDateFormatted(),
                'due_after_days' => $moneyPayment->payableCheque?->getDueAfterDays(),
                'due_status' => $moneyPayment->payableCheque?->getDueStatusFormatted(),
                'is_due' => $moneyPayment->getIsPayableChequeDue(),
            ]),
            MoneyPayment::OUTGOING_TRANSFER => array_merge($common, [
                'payment_bank_name' => $moneyPayment->getOutgoingTransferDeliveryBankName(),
                'account_type_name' => $moneyPayment->getOutgoingTransferAccountTypeName(),
                'account_number' => $moneyPayment->getOutgoingTransferAccountNumber(),
            ]),
            MoneyPayment::CASH_PAYMENT => array_merge($common, [
                'branch_name' => $moneyPayment->getCashPaymentBranchName(),
                'receipt_number' => $moneyPayment->getCashPaymentReceiptNumber(),
            ]),
            default => $common,
        };
    }

    /**
     * Same helper as MoneyReceivedController::companyScopedUrl() — see
     * that file's docblock for the full "why". money-payment's own
     * unnamed AJAX endpoints (getInvoiceNumber, getAccountNumbersFor-
     * AccountType) need this exact same fix when the Form page is
     * built next.
     */
    protected function companyScopedUrl(Company $company, string $path): string
    {
        return url('/'.app()->getLocale().'/'.$company->id.'/'.ltrim($path, '/'));
    }

    /**
     * The 3 Money Payment "Money Type" options — matches the original
     * Blade `<select id="type">`'s static option list exactly.
     */
    protected function moneyTypeOptions(): array
    {
        return [
            ['value' => MoneyPayment::CASH_PAYMENT, 'label' => __('Cash Payment')],
            ['value' => MoneyPayment::PAYABLE_CHEQUE, 'label' => __('Payable Cheques')],
            ['value' => MoneyPayment::OUTGOING_TRANSFER, 'label' => __('Outgoing Transfer')],
        ];
    }

    public function create(Company $company, $supplierInvoiceId = null)
    {
        $isDownPayment = Request()->has('type');
        $currencies = SupplierInvoice::getCurrencies();

        if ($isDownPayment) {
            return $this->createDownPayment($company);
        }

        $accountTypes = AccountType::onlyCashAccounts()->get(['id', 'name_en', 'name_ar']);
        $selectedBranches = Branch::getBranchesForCurrentCompany($company->id);
        $financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->with('bank:id,view_name')->get(['id', 'type', 'name', 'bank_id']);
        $selectedCurrency = $supplierInvoiceId ? SupplierInvoice::where('id', $supplierInvoiceId)->first()->getCurrency() : null;
        $invoiceNumber = $supplierInvoiceId ? SupplierInvoice::where('id', $supplierInvoiceId)->first()->getInvoiceNumber() : null;

        $suppliers = $supplierInvoiceId
            ? Partner::orderBy('name')->where('id', SupplierInvoice::find($supplierInvoiceId)->supplier_id)->where('company_id', $company->id)->pluck('name', 'id')
            : Partner::orderBy('name')->where('is_supplier', 1)->where('company_id', $company->id)->pluck('name', 'id');

        return Inertia::render('MoneyPayment/Form', [
            'company' => ['id' => $company->id, 'name' => $company->getName(), 'mainFunctionalCurrency' => $company->getMainFunctionalCurrency()],
            'model' => null,
            'singleModel' => $supplierInvoiceId,
            'invoiceNumber' => $invoiceNumber,
            'warningMessage' => null,
            'suppliers' => collect($suppliers)->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
            'partnerTypes' => collect(getAllPartnerTypesForSuppliers())->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'moneyTypes' => $this->moneyTypeOptions(),
            'currencies' => collect($selectedCurrency ? [$selectedCurrency => $selectedCurrency] : getBanksCurrencies())->map(fn ($label, $code) => ['code' => $code, 'label' => strtoupper($label)])->values(),
            'selectedBranches' => collect($selectedBranches)->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
            'financialInstitutionBanks' => $financialInstitutionBanks->map(fn ($b) => ['id' => $b->id, 'name' => $b->getName()]),
            'accountTypes' => $accountTypes->map(fn ($a) => ['id' => $a->id, 'name' => $a->getName()]),
            'urls' => $this->formUrls($company),
        ]);
    }

    /**
     * Down Payment create — renders `Pages/MoneyPayment/DownPaymentForm.vue`.
     * Initial supplier list matches the default Down Payment Type
     * ('over_contract'), i.e. suppliers who have at least one contract;
     * the Vue page refreshes this itself via getSuppliersWithOpeningBalance
     * whenever Down Payment Type changes — same pattern as Money
     * Received's own Down Payment form.
     */
    protected function createDownPayment(Company $company)
    {
        $selectedBranches = Branch::getBranchesForCurrentCompany($company->id);
        $financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->with('bank:id,view_name')->get(['id', 'type', 'name', 'bank_id']);
        $accountTypes = AccountType::onlyCashAccounts()->get(['id', 'name_en', 'name_ar']);
        $suppliers = Partner::orderBy('name')->where('is_supplier', 1)->where('company_id', $company->id)->onlyThatHaveSupplierContracts()->pluck('name', 'id');

        return Inertia::render('MoneyPayment/DownPaymentForm', [
            'company' => ['id' => $company->id, 'name' => $company->getName(), 'mainFunctionalCurrency' => $company->getMainFunctionalCurrency()],
            'model' => null,
            'suppliers' => collect($suppliers)->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
            'moneyTypes' => $this->moneyTypeOptions(),
            'currencies' => collect(getBanksCurrencies())->map(fn ($label, $code) => ['code' => $code, 'label' => strtoupper($label)])->values(),
            'selectedBranches' => collect($selectedBranches)->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
            'financialInstitutionBanks' => $financialInstitutionBanks->map(fn ($b) => ['id' => $b->id, 'name' => $b->getName()]),
            'accountTypes' => $accountTypes->map(fn ($a) => ['id' => $a->id, 'name' => $a->getName()]),
            'urls' => $this->downPaymentFormUrls($company),
        ]);
    }

    /**
     * Same idea as MoneyReceivedController::formUrls(), for the
     * dedicated Down Payment form. getSuppliersWithOpeningBalance is a
     * real, existing, NAMED route despite its historical name — it's
     * the endpoint the Down Payment Type change handler always calls,
     * returning either "suppliers who have a contract" (over_contract)
     * or "every supplier" (general).
     */
    protected function downPaymentFormUrls(Company $company): array
    {
        return [
            'index' => route('view.money.payment', ['company' => $company->id]),
            'store' => route('store.money.payment', ['company' => $company->id]),
            'getContractsForSupplier' => route('get.contracts.for.supplier', ['company' => $company->id]),
            'getContractsForCustomer' => route('get.contracts.for.customer', ['company' => $company->id]),
            'getPurchaseOrdersForContract' => $this->companyScopedUrl($company, 'down-payments/get-purchases-orders-for-contract'),
            'getSuppliersWithOpeningBalance' => route('get.suppliers.of.opening-balance', ['company' => $company->id]),
            'getAccountNumbersForType' => $this->companyScopedUrl($company, 'money-payment/get-account-numbers-based-on-account-type'),
            'balanceForAccountNumber' => route('update.balance.and.net.balance.based.on.account.number', ['company' => $company->id]),
            'getBranchBasedOnCurrency' => route('get.branch.based.on.currency', ['company' => $company->id]),
            'getCashInSafeEndBalance' => route('get.current.end.balance.of.cash.in.safe.statement', ['company' => $company->id]),
        ];
    }

    /**
     * Every URL the Vue Form page needs, pre-resolved (no Ziggy). Uses
     * companyScopedUrl() for every route that was never given a
     * ->name(...) in the original app — same root cause, same fix, as
     * MoneyReceivedController::formUrls().
     */
    protected function formUrls(Company $company): array
    {
        return [
            'index' => route('view.money.payment', ['company' => $company->id]),
            'store' => route('store.money.payment', ['company' => $company->id]),
            'getInvoiceNumbers' => $this->companyScopedUrl($company, 'money-payment/get-invoice-numbers'),
            'getAccountNumbersForType' => $this->companyScopedUrl($company, 'money-payment/get-account-numbers-based-on-account-type'),
            'balanceForAccountNumber' => route('update.balance.and.net.balance.based.on.account.number', ['company' => $company->id]),
            'getContractsForSupplier' => route('get.contracts.for.supplier', ['company' => $company->id]),
            'getContractsForCustomer' => route('get.contracts.for.customer', ['company' => $company->id]),
            'getPurchaseOrdersForContract' => $this->companyScopedUrl($company, 'down-payments/get-purchases-orders-for-contract'),
            'getSuppliersBasedOnCurrency' => $this->companyScopedUrl($company, 'get-suppliers-based-on-currency'),
            'getBranchBasedOnCurrency' => route('get.branch.based.on.currency', ['company' => $company->id]),
            'getCashInSafeEndBalance' => route('get.current.end.balance.of.cash.in.safe.statement', ['company' => $company->id]),
        ];
    }

    /**
     * Same idea as MoneyReceivedController::serializeMoneyReceivedForForm()
     * — the Vue Form page re-fetches invoices/allocations itself from
     * the same real AJAX endpoint (with inEditMode=1, money_payment_id
     * set), so this only needs the record's own scalar fields.
     */
    protected function serializeMoneyPaymentForForm(MoneyPayment $moneyPayment): array
    {
        $type = $moneyPayment->getType();

        return [
            'id' => $moneyPayment->id,
            'delivery_date' => $moneyPayment->getDeliveryDate(),
            'partner_type' => $moneyPayment->getPartnerType(),
            'supplier_id' => $moneyPayment->getPartnerId(),
            'supplier_name' => $moneyPayment->getPartnerName(),
            'currency' => $moneyPayment->getCurrency(),
            'payment_currency' => $moneyPayment->getPaymentCurrency(),
            'type' => $type,
            'transaction_type' => $moneyPayment->getTransactionType(),
            'user_comment' => $moneyPayment->getUserComment(),
            'paid_amount' => $moneyPayment->getPaidAmount(),
            'exchange_rate' => $moneyPayment->getExchangeRate(),
            'amount_in_invoice_currency' => $moneyPayment->getAmountInInvoiceCurrency(),
            'has_unapplied_or_down_payment' => (bool) $moneyPayment->has_unapplied_or_down_payment,
            'contract_id' => $moneyPayment->getContractId(),
            'contract_name' => $moneyPayment->getContractName(),
            'cash_payment' => $moneyPayment->isCashPayment() ? [
                'delivery_branch_id' => $moneyPayment->getCashPaymentBranchId(),
                'receipt_number' => $moneyPayment->getCashPaymentReceiptNumber(),
            ] : null,
            'outgoing_transfer' => $moneyPayment->isOutgoingTransfer() ? [
                'delivery_bank_id' => $moneyPayment->getOutgoingTransferDeliveryBankId(),
                'account_type_id' => $moneyPayment->getOutgoingTransferAccountTypeId(),
                'account_number' => $moneyPayment->getOutgoingTransferAccountNumber(),
            ] : null,
            'payable_cheque' => $moneyPayment->isPayableCheque() ? [
                'delivery_bank_id' => $moneyPayment->getPayableChequePaymentBankId(),
                'account_type_id' => $moneyPayment->getPayableChequeAccountTypeId(),
                'account_number' => $moneyPayment->getPayableChequeAccountNumber(),
                'due_date' => $moneyPayment->getPayableChequeDueDate(),
                'cheque_number' => $moneyPayment->payableCheque?->getChequeNumber(),
            ] : null,
        ];
    }

    public function getContractsForSupplier(Company $company, Request $request)
    {
        $contracts = Contract::where('partner_id', $request->get('supplierId'))
        ->where('model_type', 'Supplier')
        ->where('currency', $request->get('currency'))->pluck('name', 'id')->toArray();
        return response()->json([
            'status'=>true ,
            'contracts'=>$contracts
        ]);
    }
    public function getSalesOrdersForContract(Company $company, Request $request, $contractId  = 0, ?string $selectedCurrency=null)
    {
        $downPaymentId = $request->get('down_payment_id');
        $moneyPayment = MoneyPayment::find($downPaymentId);
        $purchaseOrders = PurchaseOrder::where('contract_id', $contractId)->get();
        $formattedSalesOrders = [];
        foreach ($purchaseOrders as $index=>$purchaseOrder) {
            $paidAmount = $moneyPayment ? $moneyPayment->downPaymentSettlements->where('purchase_order_id', $purchaseOrder->id)->first() : null ;
            $formattedSalesOrders[$index]['paid_amount'] = $paidAmount && $paidAmount->down_payment_amount ? $paidAmount->down_payment_amount : 0;
            $formattedSalesOrders[$index]['po_number'] = $purchaseOrder->po_number;
            $formattedSalesOrders[$index]['amount'] = $purchaseOrder->getAmount();
            $formattedSalesOrders[$index]['id'] = $purchaseOrder->id;
        }
        if (!count($purchaseOrders)) {
            $index = 0;
            $downPaymentSettlement = $moneyPayment ? $moneyPayment->downPaymentSettlements->where('contract_id', null)->first() : null ;
            $formattedSalesOrders[$index]['paid_amount'] = $downPaymentSettlement && $downPaymentSettlement->down_payment_amount ? $downPaymentSettlement->down_payment_amount : 0;
            $formattedSalesOrders[$index]['po_number'] = 'General';
            $formattedSalesOrders[$index]['amount'] =0;
            $formattedSalesOrders[$index]['id'] = -1;
        }
        return response()->json([
            'status'=>true ,
            'purchases_orders'=>$formattedSalesOrders,
            'selectedCurrency'=>$selectedCurrency
        ]);

    }
    public function getInvoiceNumber(Company $company, Request $request, int $supplierInvoiceId, ?string $selectedCurrency=null)
    {
        $inEditMode = $request->get('inEditMode');
        $moneyPaymentId = $request->get('money_payment_id');
        $moneyPayment = MoneyPayment::find($moneyPaymentId);
        $partner = Partner::find($supplierInvoiceId);
        $downPaymentContract = Contract::find($request->get('downPaymentContractId'));
        if (!$partner) {
            return response()->json([
                'status'=>true ,
                'invoices'=>[],
                'currencies'=>[],
                'selectedCurrency'=>[],
                'clientsWithContracts'=>[]
            ]);
        }
        $partnerId = $partner->id ;
        
        $invoices = SupplierInvoice::where('supplier_id', $partnerId)->where('company_id', $company->id)
        ->where('net_invoice_amount', '>', 0)
        // ->whereNull('opening_balance_id')
        ->when($downPaymentContract, function ($q) use ($downPaymentContract) {
            $q->where('contract_code', $downPaymentContract->getCode());
        });
        if (!$inEditMode) {
            $invoices->where('net_balance', '>', 0);
        }
        $allCurrencies =$invoices->where('company_id', $company->id)->pluck('currency', 'currency')->mapWithKeys(function ($value, $key) {
            return [
                $key=>$value
            ];
        });
        if ($selectedCurrency) {
            $invoices = $invoices->where('currency', '=', $selectedCurrency);
        }
        $invoices = $invoices->orderBy('invoice_date', 'asc')
        ->get(['id','invoice_number','invoice_date','invoice_due_date','net_invoice_amount','total_paid_amount','net_balance','currency'])
        ->toArray();


        foreach ($invoices as $index=>$invoiceArr) {
            $invoices[$index]['settlement_amount'] = $moneyPayment ? $moneyPayment->sumSettlementsForInvoice($invoiceArr['id'], $partnerId, 0) : 0;
            $invoices[$index]['withhold_amount'] = $moneyPayment ? $moneyPayment->sumWithholdAmountForInvoice($invoiceArr['id'], $partnerId, 0) : 0;
        }

        $invoices = $this->formatInvoices($invoices, $inEditMode, $moneyPayment);
        $clientsWithContracts = Partner::onlyCompany($company->id)	->onlyCustomers()->onlyThatHaveCustomerContracts()->pluck('name', 'id')->toArray();
        
        return response()->json([
            'status'=>true ,
            'invoices'=>$invoices,
            'currencies'=>$allCurrencies,
            'selectedCurrency'=>$selectedCurrency,
            'clientsWithContracts'=>$clientsWithContracts
        ]);

    }
    protected function formatInvoices(array $invoices, int $inEditMode, $moneyPayment)
    {
        return SupplierInvoice::formatInvoices($invoices, $inEditMode, $moneyPayment);
    }

    public function store(
        Company $company,
        StoreMoneyPaymentRequest $request,
        $returnModel = false
        // ,$accountNumberHasChanged = false
    ) {
        $hasUnappliedAmount = (bool)$request->get('unapplied_amount');
        $partnerType = $request->get('partner_type', 'is_supplier');
        $moneyType = $request->get('type');
		
        $isGeneralDownPaymentOrSettlementOpening = $request->get('down_payment_type') == MoneyPayment::DOWN_PAYMENT_GENERAL || $request->get('down_payment_type') == MoneyPayment::SETTLEMENT_OF_OPENING_BALANCE;
		$isDownPaymentOverContract = $request->get('down_payment_type') == MoneyPayment::DOWN_PAYMENT_OVER_CONTRACT;
        $financialInstitutionId = null;
        $contractId = $request->get('contract_id');
        $contractId = is_numeric($contractId) ? $contractId : null;
        $partnerId = $request->get('supplier_id');
        $supplier = Partner::find($partnerId);
        $supplierId = $supplier->id;
        $paymentBranchName = $request->get('delivery_branch_id') ;
        $data = $request->only(['type','delivery_date','currency','payment_currency','down_payment_type','partner_type','user_comment','transaction_type','account_bank_statement_line_id','journal_entry_id']);
        // $isSupplier = $partnerType == 'is_supplier';
        $data['currency'] = $isGeneralDownPaymentOrSettlementOpening   ? $data['payment_currency'] : $data['currency']??null;
        $paymentCurrency = $data['payment_currency'];
        $data['currency'] = is_null($data['currency']) ?  $paymentCurrency : $data['currency'];
        $currencyName = $data['currency'];
        
        $data['partner_id'] = $supplierId;
        $data['user_id'] = auth()->user()->id ;
        $data['company_id'] = $company->id ;
        $isDownPayment =  $request->get('is_down_payment') && $request->has('purchases_orders_amounts');
        $isDownPaymentFromMoneyPayment = $request->get('unapplied_amount', 0) > 0 && !$request->get('is_down_payment') && $moneyType == 'is_supplier'  ;
        $data['money_type'] =  !$isDownPayment ? 'money-payment' : 'down-payment';
        $data['money_type'] = $isDownPaymentFromMoneyPayment ? MoneyPayment::INVOICE_SETTLEMENT_WITH_DOWN_PAYMENT : $data['money_type'];
        $currency = $data['currency'] ;
        $hasUnappliedOrIsDownPayment = $hasUnappliedAmount || $isDownPayment;
        $data['has_unapplied_or_down_payment'] = $hasUnappliedOrIsDownPayment;
        $relationData = [];
        $relationName = null ;
        $isTheSameCurrency = $currency == $paymentCurrency ;
		$date = $data['delivery_date'];
        $date = Carbon::make($date)->format('Y-m-d');
		
		
      
		$mainFunctionalCurrency= $company->getMainFunctionalCurrency();
       
        $amountInPaymentCurrency = $request->input('paid_amount.'.$moneyType, 0) ;
        $amountInPaymentCurrency = unformat_number($amountInPaymentCurrency);
        
        $invoiceCurrencyAmount =  $isTheSameCurrency ? $amountInPaymentCurrency  : HArr::sumFormattedArr(array_column($request->get('settlements', []), 'settlement_amount'))  ;
		if(!$isTheSameCurrency && !$request->has('settlements') && $request->has('amount_in_invoice_currency')){
			$invoiceCurrencyAmount = $request->input('amount_in_invoice_currency.'.$moneyType);
		}
        if ($moneyType == MoneyPayment::CASH_PAYMENT) {
            $relationData = $request->only(['receipt_number']) ;
            $relationData['delivery_branch_id'] = $this->generateBranchId($paymentBranchName, $company->id) ;
            $relationData['company_id'] = $company->id ;
            $relationName = 'cashPayment';
        } elseif ($moneyType ==MoneyPayment::OUTGOING_TRANSFER) {
            $relationName = 'outgoingTransfer';
            $financialInstitutionId = $request->input('delivery_bank_id.'.MoneyPayment::OUTGOING_TRANSFER) ;
            $relationData = [
                'delivery_bank_id'=>$financialInstitutionId,
                'actual_payment_date'=>$data['delivery_date'],
                'account_number'=>$request->input('account_number.'.MoneyPayment::OUTGOING_TRANSFER),
                'account_type'=>$request->input('account_type.'.MoneyPayment::OUTGOING_TRANSFER)
            ];
        } elseif ($moneyType ==MoneyPayment::PAYABLE_CHEQUE) {
            $relationName = 'payableCheque';
            $financialInstitutionId = $request->input('delivery_bank_id.'.MoneyPayment::PAYABLE_CHEQUE) ;
            $dueDate = $request->input('due_date') ;
			$date = Carbon::make($dueDate)->format('Y-m-d');
            $relationData = [
                'due_date'=>$dueDate ,
                'actual_payment_date'=>$dueDate,
                'cheque_number'=>$request->input('cheque_number'),
                'delivery_bank_id'=>$financialInstitutionId,
                'account_number'=>$request->input('account_number.'.MoneyPayment::PAYABLE_CHEQUE),
                'account_type'=>$request->input('account_type.'.MoneyPayment::PAYABLE_CHEQUE),
                'company_id'=>$company->id
            ];
        }
    
        if ($partnerType && $partnerType != 'is_supplier') {
            $data['paid_amount'] = $request->input('paid_amount.'.$moneyType, 0);
        }
        $deliveryBank = FinancialInstitution::find($financialInstitutionId);
        $deliveryBankName = $deliveryBank ? $deliveryBank->getName() : null;
        $bankNameOrBranchName =  $moneyType == MoneyPayment::CASH_PAYMENT ? Branch::find($relationData['delivery_branch_id'])->getName() : $deliveryBankName ;
        $data['paid_amount'] =$amountInPaymentCurrency ;
        $data['amount_in_invoice_currency'] = $invoiceCurrencyAmount ;
		 $exchangeRate = $currencyName == $paymentCurrency ? 1 : number_unformat($request->input('exchange_rate.'.$moneyType, 1)) ;
		 $exchangeRate = $isGeneralDownPaymentOrSettlementOpening  ? ForeignExchangeRate::getExchangeRateForCurrencyAndClosestDate($currency, $mainFunctionalCurrency, $date, $company->id) :$exchangeRate;
		 if($isDownPaymentOverContract && $paymentCurrency == $mainFunctionalCurrency){
				$exchangeRate = $request->input('exchange_rate.'.$moneyType, 1);
		 }
		 if($isDownPaymentOverContract && $paymentCurrency != $mainFunctionalCurrency){
				$exchangeRate = ForeignExchangeRate::getExchangeRateForCurrencyAndClosestDate($paymentCurrency, $mainFunctionalCurrency, $date, $company->id);
		 }

		 
        $data['exchange_rate'] =$exchangeRate ;
        
        //	$data['money_type'] = $isDownPayment ? 'down-payment' : 'money-payment' ;
        $data['contract_id'] = $contractId ;
        // $data['money_payment_id'] = $moneyPaymentId;

    
        if (!$isDownPayment && !$isDownPaymentFromMoneyPayment) {
            unset($data['contract_id']);
        }
		/**
		 * @var MoneyPayment $moneyPayment
		 */
        $moneyPayment = MoneyPayment::create($data);

        $relationData['company_id'] = $company->id ;
	
        $moneyPayment->$relationName()->create($relationData);
        $moneyPayment = $moneyPayment->refresh();
         
        $statementDate = $moneyPayment->getStatementDate();
        $accountType = AccountType::find($request->input('account_type.'.$moneyType));
        $accountNumber = $request->input('account_number.'.$moneyType) ;
        $deliveryBranchId = $relationData['delivery_branch_id'] ?? null ;
        $moneyPayment->handleCreditStatement($company->id, $financialInstitutionId, $accountType, $accountNumber, $moneyType, $statementDate, $amountInPaymentCurrency, $deliveryBranchId, $paymentCurrency);
        
        /**
         * * دي علشان لو كان مثلا
         * * employee
         * * بس في التعديل غيرها ل
         * * supplier
         * * يبقي لازم تحذف ال employee
         */
        // if($partnerType == 'is_supplier' && $moneyPayment->journal_entry_id && $moneyPayment->account_bank_statement_line_id){
        // 	$moneyPayment->unlinkNonCustomerOrSupplierOdooExpense();
        // }
        /**
         * * For Money Payment Only
         */
        $totalWithholdAmountAndSettlements = $moneyPayment->storeNewSettlement(
            $request->get('settlements', []),
            $partnerId,
            $company
        );
		$totalWithholdAmount = $totalWithholdAmountAndSettlements['total_withhold_amount'];
        $moneyPayment->update([
            'total_withhold_amount'=>$totalWithholdAmount
        ]);
        /**
         * * For Contract Only
         */
        
        $moneyPayment->storeNewAllocation($request->get('allocations', []));
        
        if ($hasUnappliedOrIsDownPayment) {
            $moneyPayment->storeNewPurchaseOrders($request->get('purchases_orders_amounts', []), $contractId, $supplierId, $company->id, $amountInPaymentCurrency);
            // if ($company->hasOdooIntegrationCredentials() && $partnerType == 'is_supplier') {
            //     $odooPaymentService = new OdooPayment($company);
            //     $odooPaymentService->createDownPayment($moneyPayment);
            // }
        }
		if (($partnerType && $partnerType != 'is_supplier') || ($isDownPayment || $isDownPaymentFromMoneyPayment )) {
            $moneyPayment->handlePartnerDebitStatement($partnerType, $partnerId, $moneyPayment->id, $company->id, $statementDate, $invoiceCurrencyAmount, $paymentCurrency, $bankNameOrBranchName, $accountType, $accountNumber);
            $moneyPayment->storeNonCustomerOrSupplierOdooExpense(($isDownPayment || $isDownPaymentFromMoneyPayment  ));
        }
	
        $activeTab = $moneyType;
        if ($returnModel) {
            return $moneyPayment;
        }
        return redirect()->route('view.money.payment', ['company'=>$company->id,'active'=>$activeTab])->with('success', __('Data Store Successfully'));

    }
  
	
    public function edit(Company $company, Request $request, moneyPayment $moneyPayment, $supplierInvoiceId = null)
    {
        $isDownPayment = $moneyPayment->isDownPayment();
        $partnerType = $moneyPayment->partner->getSupplierType();
        $currencies = SupplierInvoice::getCurrencies();
        $selectedCurrency = $supplierInvoiceId ? SupplierInvoice::where('id', $supplierInvoiceId)->first()->getCurrency() : null;

        if ($isDownPayment) {
            return $this->editDownPayment($company, $moneyPayment);
        }

        $accountTypes = AccountType::onlyCashAccounts()->get(['id', 'name_en', 'name_ar']);
        $selectedBranches = Branch::getBranchesForCurrentCompany($company->id);
        $financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->with('bank:id,view_name')->get(['id', 'type', 'name', 'bank_id']);
        $suppliers = Partner::orderBy('name')->where($partnerType, 1)->where('company_id', $company->id)->pluck('name', 'id');
        $warningMessage = count($moneyPayment->settlementsForDownPaymentThatComeFromMoneyModel) ? __('Warning, please take care incase you changed the paid amount, the invoices settled using this down payment will be deleted') : null;

        return Inertia::render('MoneyPayment/Form', [
            'company' => ['id' => $company->id, 'name' => $company->getName(), 'mainFunctionalCurrency' => $company->getMainFunctionalCurrency()],
            'model' => $this->serializeMoneyPaymentForForm($moneyPayment),
            'singleModel' => $supplierInvoiceId,
            'invoiceNumber' => null,
            'warningMessage' => $warningMessage,
            'suppliers' => collect($suppliers)->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
            'partnerTypes' => collect(getAllPartnerTypesForSuppliers())->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'moneyTypes' => $this->moneyTypeOptions(),
            'currencies' => collect($selectedCurrency ? [$selectedCurrency => $selectedCurrency] : getBanksCurrencies())->map(fn ($label, $code) => ['code' => $code, 'label' => strtoupper($label)])->values(),
            'selectedBranches' => collect($selectedBranches)->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
            'financialInstitutionBanks' => $financialInstitutionBanks->map(fn ($b) => ['id' => $b->id, 'name' => $b->getName()]),
            'accountTypes' => $accountTypes->map(fn ($a) => ['id' => $a->id, 'name' => $a->getName()]),
            'urls' => array_merge($this->formUrls($company), [
                'update' => route('update.money.payment', ['company' => $company->id, 'moneyPayment' => $moneyPayment->id]),
            ]),
        ]);
    }

    /**
     * Down Payment edit — mirrors editDownPayment()'s counterpart on
     * MoneyReceivedController. Purchase orders (and their pre-filled
     * amounts) are fetched client-side, same "one source of truth"
     * reasoning as the plain form's invoices.
     */
    protected function editDownPayment(Company $company, MoneyPayment $moneyPayment)
    {
        $selectedBranches = Branch::getBranchesForCurrentCompany($company->id);
        $financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->with('bank:id,view_name')->get(['id', 'type', 'name', 'bank_id']);
        $accountTypes = AccountType::onlyCashAccounts()->get(['id', 'name_en', 'name_ar']);
        $suppliers = Partner::orderBy('name')->where('is_supplier', 1)->where('company_id', $company->id)
            ->when($moneyPayment->isDownPaymentOverContract(), fn ($q) => $q->onlyThatHaveSupplierContracts())
            ->pluck('name', 'id');
        $warningMessage = count($moneyPayment->settlementsForDownPaymentThatComeFromMoneyModel) ? __('Warning, please take care incase you changed the paid amount, the invoices settled using this down payment will be deleted') : null;

        return Inertia::render('MoneyPayment/DownPaymentForm', [
            'company' => ['id' => $company->id, 'name' => $company->getName(), 'mainFunctionalCurrency' => $company->getMainFunctionalCurrency()],
            'model' => $this->serializeDownPaymentForForm($moneyPayment),
            'warningMessage' => $warningMessage,
            'suppliers' => collect($suppliers)->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
            'moneyTypes' => $this->moneyTypeOptions(),
            'currencies' => collect(getBanksCurrencies())->map(fn ($label, $code) => ['code' => $code, 'label' => strtoupper($label)])->values(),
            'selectedBranches' => collect($selectedBranches)->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
            'financialInstitutionBanks' => $financialInstitutionBanks->map(fn ($b) => ['id' => $b->id, 'name' => $b->getName()]),
            'accountTypes' => $accountTypes->map(fn ($a) => ['id' => $a->id, 'name' => $a->getName()]),
            'urls' => array_merge($this->downPaymentFormUrls($company), [
                'update' => route('update.money.payment', ['company' => $company->id, 'moneyPayment' => $moneyPayment->id]),
            ]),
        ]);
    }

    protected function serializeDownPaymentForForm(MoneyPayment $moneyPayment): array
    {
        $type = $moneyPayment->getType();

        return [
            'id' => $moneyPayment->id,
            'delivery_date' => $moneyPayment->getDeliveryDate(),
            'down_payment_type' => $moneyPayment->getDownPaymentType(),
            'currency' => $moneyPayment->getCurrency(),
            'supplier_id' => $moneyPayment->getPartnerId(),
            'supplier_name' => $moneyPayment->getPartnerName(),
            'payment_currency' => $moneyPayment->getPaymentCurrency(),
            'type' => $type,
            'user_comment' => $moneyPayment->getUserComment(),
            'paid_amount' => $moneyPayment->getPaidAmount(),
            'exchange_rate' => $moneyPayment->getExchangeRate(),
            'amount_in_invoice_currency' => $moneyPayment->getAmountInInvoiceCurrency(),
            'contract_id' => $moneyPayment->getContractId(),
            'contract_name' => $moneyPayment->getContractName(),
            'cash_payment' => $moneyPayment->isCashPayment() ? [
                'delivery_branch_id' => $moneyPayment->getCashPaymentBranchId(),
                'receipt_number' => $moneyPayment->getCashPaymentReceiptNumber(),
            ] : null,
            'outgoing_transfer' => $moneyPayment->isOutgoingTransfer() ? [
                'delivery_bank_id' => $moneyPayment->getOutgoingTransferDeliveryBankId(),
                'account_type_id' => $moneyPayment->getOutgoingTransferAccountTypeId(),
                'account_number' => $moneyPayment->getOutgoingTransferAccountNumber(),
            ] : null,
            'payable_cheque' => $moneyPayment->isPayableCheque() ? [
                'delivery_bank_id' => $moneyPayment->getPayableChequePaymentBankId(),
                'account_type_id' => $moneyPayment->getPayableChequeAccountTypeId(),
                'account_number' => $moneyPayment->getPayableChequeAccountNumber(),
                'due_date' => $moneyPayment->getPayableChequeDueDate(),
                'cheque_number' => $moneyPayment->payableCheque?->getChequeNumber(),
            ] : null,
        ];
    }


    public function update(Company $company, StoreMoneyPaymentRequest $request, moneyPayment $moneyPayment)
    {
	
        $oldSettlementsForMoneyReceivedWithDownPayment  = $moneyPayment->settlementsForDownPaymentThatComeFromMoneyModel ;
        $newType = $request->get('type');
        $request->merge([
            'journal_entry_id'=>$moneyPayment->journal_entry_id,
            'account_bank_statement_line_id'=>$moneyPayment->account_bank_statement_line_id,
        ]);
        $moneyPayment->deleteRelations();
        $moneyPaidAmountHasChanged = $moneyPayment->getAmount() != $request->input('paid_amount.'.$newType);
        $moneyPayment->delete();
        $newMoneyPayment = $this->store($company, $request, true);
        if (!$moneyPaidAmountHasChanged) {
            $newMoneyPayment->storeNewSettlement(
                $oldSettlementsForMoneyReceivedWithDownPayment->toArray(),
                $newMoneyPayment->getPartnerId(),
                $company,
                1
            );
        }
        $activeTab = $newType;
        return redirect()->route('view.money.payment', ['company'=>$company->id,'active'=>$activeTab])->with('success', __('Money Payment Has Been Updated Successfully'));
    }

    public function destroy(Company $company, MoneyPayment $moneyPayment, DeleteMoneyPaymentRequest $request)
    {
        
        $moneyPayment->deleteRelations();
        $activeTab = $moneyPayment->getType();
        $moneyPayment->delete();
        return redirect()->route('view.money.payment', ['company'=>$company->id,'active'=>$activeTab])->with('success', __('Money Payment Has Been Updated Successfully'));
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
    public function markChequesAsPaid(Company $company, MarkChequeAsPaidRequest $request)
    {
    
        
        $hasOdooIntegration = $company->hasOdooIntegrationCredentials();
        $OdooPaymentService = null ;
        if ($hasOdooIntegration) {
            $OdooPaymentService = new OdooPayment($company);
        }
            
        $moneyPaymentIds = $request->get('cheques') ;
        $moneyPaymentIds = is_array($moneyPaymentIds) ? $moneyPaymentIds :  explode(',', $moneyPaymentIds);
        $data = $request->only(['actual_payment_date']);
        $actualPaymentDate = Carbon::make($request->get('actual_payment_date'))->format('Y-m-d');
        $data['status'] = PayableCheque::PAID;
        
        foreach ($moneyPaymentIds as $moneyPaymentId) {
            /**
             * @var MoneyPayment $moneyPayment
             */
            $moneyPayment = MoneyPayment::find($moneyPaymentId) ;
            $currentPaidAmount = $moneyPayment->getAmount();
            $balancesResultJsonResponse = ((new MoneyReceivedController())->updateNetBalanceBasedOnAccountNumber($request, $company, $moneyPayment->getPayableChequeAccountType(), $moneyPayment->getPayableChequeAccountNumber(), $moneyPayment->getPayableChequePaymentBankId(), $actualPaymentDate));
            $balance = $balancesResultJsonResponse->getData()->balance;
			$balance = $balance + $currentPaidAmount;
            $errMessage = __('Net Balance Less Than Paid Amount');
    
            if ($balance < $currentPaidAmount) {
                return redirect()->back()->with('fail', $errMessage);
            }
            // $chequeDueDate = $moneyPayment->payableCheque->due_date;
            $moneyPayment->payableCheque->update($data);
            $currentStatement = $moneyPayment->getCurrentStatement();
        
        
            if ($hasOdooIntegration && $company->withinIntegrationDate($actualPaymentDate)) {
				
				if($moneyPayment->isOpenBalance()){
					$moneyPayment->markOpeningPayableChequeAsPaidInOdoo(false);
				}else{
					$moneyPayment->markPayableChequeAsPaidInOdoo();
				}
            }
        
            if ($currentStatement) {
                $currentStatement->handleFullDateAfterDateEdit(Carbon::make($data['actual_payment_date'])->format('Y-m-d'), $currentStatement->debit, $currentStatement->credit);
            }

        }
        return redirect()->route('view.money.payment', ['company'=>$company->id,'active'=>MoneyPayment::PAYABLE_CHEQUE])
            ->with('success', __('Cheques Marked As Paid Successfully'));

    }

    public function getAccountNumbersForAccountType(Company $company, Request $request, string $accountType, ?string $selectedCurrency=null, ?int $financialInstitutionId = 0)
    {
        $accountType = AccountType::find($accountType);
        $accountNumberModel =  ('\App\Models\\'.$accountType->getModelName())::getAllAccountNumberForCurrency($company->id, $selectedCurrency, $financialInstitutionId);
        return response()->json([
            'status'=>true ,
            'data'=>$accountNumberModel
        ]);
    }
    
    public function getSuppliersBasedOnCurrency(Request $request, Company $company, string $currencyName)
    {
        return response()->json([
            'supplierInvoices'=>SupplierInvoice::orderBy('supplier_name')->where('currency', $currencyName)->where('company_id', $company->id)->pluck('supplier_id', 'supplier_name')
        ]);
    }
    public function getSuppliersWithOpeningBalance(Request $request, Company $company)
    {
        
        $type =$request->get('type') ;
        $partners = [];
        if ($type == 'over_contract') {
            $partners=  Partner::onlyThatHaveSupplierContracts()->where('is_supplier', 1)->orderBy('name')
                                    ->where('company_id', $company->id)->pluck('id', 'name');
        } elseif ($type == 'general') {
            $partners =  Partner::where('is_supplier', 1)->orderBy('name')
                                    ->where('company_id', $company->id)->pluck('id', 'name');
        } elseif ($type == 'settlement-of-opening-balance') {
            $partners = SupplierInvoice::orderBy('supplier_name')
            ->whereNotNull('opening_balance_id')
            ->where('company_id', $company->id)->pluck('supplier_id', 'supplier_name');
        }
        return response()->json([
            'invoices' => $partners
        ]);
    }
    public function getCashInSafeStatementEndBalance(Request $request, Company $company, ?int $branchId = null, ?string $currencyName = null, ?string $deliveryDate = null)
    {
        
        $branchId = $request->get('branchId', $branchId);
        if (is_null($deliveryDate) && $request->has('balanceDate')) {
            $deliveryDate = $request->get('balanceDate');
            if (is_null($deliveryDate)) {
                return response()->json([
                    'end_balance'=>0
                ]);
            }
            $deliveryDate = Carbon::make($deliveryDate)->format('Y-m-d');
        }
        $currencyName = $request->get('currencyName', $currencyName);
        $currencyName = is_null($currencyName) ? $request->get('currency') :$currencyName  ;
        $additionalAmountInEditMode = 0 ;
        // $additionalAmountInEditMode = number_unformat($request->get('additionalBalanceInEditMode',0));
        /**
         * @var Branch|null $branch
         */
        $branch = Branch::find($branchId);
        $model = null ;
        if ($request->has('modelId')) {
            $modelId = $request->get('modelId')  ;
            $modelType = $request->get('modelType');
            $model = ('App\Models\\'.$modelType)::find($modelId);
            $oldBranchId = null ;
            if ($model && method_exists($model, 'getBranchId')) {
                $oldBranchId = $model->getBranchId();
            } elseif ($model && method_exists($model, 'getFromBranchId')) {
                $oldBranchId = $model->getFromBranchId();
            }
            if ($branch && $oldBranchId && $oldBranchId == $branch->id) {
                $additionalAmountInEditMode =  $model->getAmount();
            }
        }
        $branches  = CashVeroBranch::where('company_id', $company->id)->where('currency', $currencyName)->orderBy('name')->pluck('id', 'name')->toArray();
        $endBalance = $branch ? $branch->getCurrentEndBalance($company->id, $currencyName, $deliveryDate) : 0;
        if (isset($model) && $model instanceof MoneyReceived) {
            $endBalance = $endBalance-$additionalAmountInEditMode ;
        } else {
            $endBalance = $endBalance+$additionalAmountInEditMode ;
        }
        return response()->json([
            'end_balance'=>$endBalance,
            'branches'=>$branches
        ]);
        
    }

	public function updateOpeningPayableCheque(Request $request , Company $company,MoneyPayment $moneyPayment  , PayableCheque $payableCheque)
	{
		$moneyPayment->update([
			'currency'=>$paymentCurrency = $request->get('currency'),
			'paid_amount'=>$amountInPaymentCurrency = number_unformat($request->get('paid_amount',0)),
		]);
		$payableCheque->update([
			'status'=>PayableCheque::PENDING,
			'supplier_id'=>$request->get('supplier_id'),
			'exchange_rate'=>$request->get('exchange_rate'),
			'due_date'=>$statementDate = Carbon::make($request->get('due_date'))->format('Y-m-d'),
			'cheque_number'=>$request->get('cheque_number'),
			'delivery_bank_id'=>$financialInstitutionId = $request->get('drawl_bank_id'),
			'account_type'=>$accountType = $request->get('account_type'),
			'account_number'=>$accountNumber = $request->get('account_number')
		]);
		$moneyType = $moneyPayment->getMoneyType();
		$deliveryBranchId = null;
		$currentStatement = $moneyPayment->getCurrentStatement();
		   if ($currentStatement) {
                /**
                 * ! Need To Change To Work With All Other Account Types
                 */
                $financialInstitutionAccount = FinancialInstitutionAccount::findByAccountNumber($accountNumber, $company->id, $financialInstitutionId);
                $currentStatement->handleFullDateAfterDateEdit($statementDate, 0, $amountInPaymentCurrency, [
                    'financial_institution_account_id' =>  $financialInstitutionAccount->id
                ]);
            } else {
                $moneyPayment->handleCreditStatement($company->id, $financialInstitutionId, $accountType, $accountNumber, $moneyType, $statementDate, $amountInPaymentCurrency, $deliveryBranchId, $paymentCurrency);
            }
			
			
			 if ($moneyPayment->account_bank_statement_line_id) {
            $OdooPaymentService = new OdooPayment($moneyPayment->company);
            $OdooPaymentService->unlinkBankCollection($moneyPayment->account_bank_statement_line_id);
        }
		
		return redirect()->back()->with('success',__('Done'));
	
	}
}
