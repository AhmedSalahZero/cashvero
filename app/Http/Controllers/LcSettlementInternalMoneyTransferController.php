<?php
namespace App\Http\Controllers;

use App\Support\Instructions\PageInstructions;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\LcSettlementInternalMoneyTransfer;
use App\Models\LetterOfCreditIssuance;
use App\Rules\DateMustBeLessThanOrEqualDate;
use App\Services\Api\OdooSync;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * LcSettlementInternalMoneyTransferController
 * ------------------------------------------------------------------
 * "LC Settlement Internal Transfer" — moves money from a real bank
 * account into a Letter of Credit facility's overdraft balance (a
 * pure internal transfer, no Odoo write, no DB triggers). Only one
 * transfer type exists today (Bank → Letter of Credit) — the
 * `type`/`getAllTypes()` scaffolding on the model mirrors Internal
 * Money Transfer's 4-type pattern in case more types are ever added,
 * but only this one is real right now.
 *
 * ── Reworked (client-requested, 2026-08-18) ─────────────────────
 * This screen used to be a plain CRUD list of individual transfer
 * records, created manually one at a time. It's now the "Pending LC
 * Settlements" screen instead: one row per bank-financed LC Issuance
 * that has been paid to the supplier but not yet fully settled with
 * the bank. Rows appear automatically (any bank-financed LC with
 * getRemainingBankSettlementAmount() > 0 shows up — see
 * LetterOfCreditIssuance::isPendingBankSettlement()), so Create/Delete
 * no longer make sense as actions: Create is gone entirely (there's
 * nothing to manually create — paying the LC to the supplier via
 * LetterOfCreditIssuanceController@markAsPaid is what puts a row here),
 * and Delete is replaced by Reset, which wipes every settlement made
 * so far for that LC and returns it to fully unpaid. To remove a row
 * from this list entirely, the user reverts the LC back to Running
 * from the LC Issuance screen instead.
 *
 * Each partial (or full) settlement the user records via "Mark As
 * Settle" still creates one real LcSettlementInternalMoneyTransfer row
 * underneath, exactly like the old manual flow did — that collection
 * of rows, read back via LetterOfCreditIssuance::lcSettlementInternalMoneyTransfers(),
 * IS the settlement history. No separate history table was needed.
 * "Edit" only ever targets the MOST RECENT settlement for a given LC
 * (client-confirmed) — since each settlement's interest is calculated
 * from the gap since the previous one, editing an older entry would
 * ripple through everything after it. The Index page only ever links
 * to the latest transfer's edit route, so this is enforced simply by
 * never exposing an edit link to any other one.
 *
 * ── Frontend migration status ───────────────────────────────────
 *   index()                → Inertia::render, Pages/LcSettlementInternalMoneyTransfer/Index.vue
 *   edit()                 → Inertia::render, Pages/LcSettlementInternalMoneyTransfer/Form.vue
 *                             (still used for the "Edit latest settlement" action)
 *   create()/store()       → UNCHANGED, deliberately, but no longer linked from the
 *                             UI — see class docblock above. Left in place rather
 *                             than deleted in case anything else still depends on
 *                             the route existing.
 *   update()/destroy()     → UNCHANGED, deliberately — destroy() is simply no
 *                             longer linked from the UI (superseded by reset()).
 *   settle()/reset()/getSettleData() → NEW.
 */
class LcSettlementInternalMoneyTransferController
{
    use GeneralFunctions;

    /**
     * Read-only list page. Renders Pages/LcSettlementInternalMoneyTransfer/Index.vue.
     * One row per bank-financed, paid, not-yet-fully-settled LC Issuance.
     */
    public function index(Company $company, Request $request)
    {
        $paginationPerPage = GeneralFunctions::getPaginationLimit();

        /**
         * ⚠️ REAL BUG FIXED HERE (client-flagged, 2026-08-18): this used to
         * filter down to isPendingBankSettlement() only, which meant the
         * moment a settlement brought the remaining balance to zero, the
         * row vanished from the list entirely — no way to see it again,
         * no way to fix a mistake, no settlement history at all. Every
         * bank-financed, paid LC now stays on this screen permanently —
         * Pending or Settled — distinguished by the new `is_settled` flag
         * below rather than by disappearing.
         */
        $allIssuances = LetterOfCreditIssuance::where('company_id', $company->id)
            ->where('status', LetterOfCreditIssuance::PAID)
            ->where('financed_by_bank_or_self', 'bank')
            ->with(['lcOverdraftBankStatements', 'lcSettlementInternalMoneyTransfers'])
            ->get()
            // Pending ones first (what the user needs to act on), settled
            // ones after — each group most-recent-first.
            ->sortBy([
                [fn (LetterOfCreditIssuance $lc) => $lc->isPendingBankSettlement() ? 0 : 1, 'asc'],
                ['payment_date', 'desc'],
            ])
            ->values();

        // Paginated in PHP rather than SQL — isPendingBankSettlement() needs
        // each row's own bank-statement rows loaded to evaluate, so the
        // sorting above can't happen inside the query itself. Lists like
        // this are never huge in practice (bank-financed, paid LCs only).
        $currentPage = (int) ($request->get('page', 1));
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            $allIssuances->forPage($currentPage, $paginationPerPage)->values(),
            $allIssuances->count(),
            $paginationPerPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $rows = $paginator->getCollection()->map(function (LetterOfCreditIssuance $lc) use ($company) {
            $latestSettlement = $lc->getLatestLcSettlementInternalMoneyTransfer();
            $isSettled = ! $lc->isPendingBankSettlement();

            return [
                'letter_of_credit_issuance_id' => $lc->id,
                'transaction_name' => $lc->getTransactionName(),
                'supplier_name' => $lc->getSupplierName(),
                'lc_code' => $lc->getNumber(),
                'lc_type' => $lc->getLcType(),
                'currency' => strtoupper((string) $lc->getLcCashCoverCurrency()),
                'payment_date_formatted' => $lc->getReceivingOrPaymentMoneyDateFormatted(),
                'remaining_amount_formatted' => number_format($lc->getRemainingBankSettlementAmount()),
                'is_settled' => $isSettled,
                'status_label' => $isSettled ? __('Settled') : __('Pending'),
                'settlements_count' => $lc->lcSettlementInternalMoneyTransfers->count(),
                'last_settlement_date_formatted' => $latestSettlement ? $latestSettlement->getTransferDateFormatted() : null,
                'settle_data_url' => route('lc-settlement-internal-money-transfers.settle-data', ['company' => $company->id, 'letterOfCreditIssuance' => $lc->id]),
                'settle_url' => route('lc-settlement-internal-money-transfers.settle', ['company' => $company->id, 'letterOfCreditIssuance' => $lc->id]),
                'reset_url' => route('lc-settlement-internal-money-transfers.reset', ['company' => $company->id, 'letterOfCreditIssuance' => $lc->id]),
                'edit_url' => $latestSettlement ? route('lc-settlement-internal-money-transfers.edit', ['company' => $company->id, 'lc_settlement_internal_transfer' => $latestSettlement->id]) : null,
                /**
                 * Client-requested (2026-08-18): the paying bank on a
                 * settlement is always the same bank the LC was issued
                 * with — never a choice. Exposed here so the popup can
                 * show it read-only instead of offering a picker.
                 */
                'bank_id' => $lc->getFinancialInstitutionId(),
                'bank_name' => $lc->getFinancialInstitutionBankName(),
            ];
        })->values();

        return Inertia::render('LcSettlementInternalMoneyTransfer/Index', [
            'instructionsUrl' => route('view.instructions', ['company' => $company->id, 'page' => PageInstructions::LC_SETTLEMENT]),
            'company' => ['id' => $company->id, 'name' => $company->getName()],
            'rows' => $rows,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'links' => $paginator->linkCollection()->toArray(),
                'total' => $paginator->total(),
            ],
            'canSettle' => auth()->user()->can('lc_settlement_transfer.create'),
            'canUpdate' => auth()->user()->can('lc_settlement_transfer.update'),
            'canReset' => auth()->user()->can('lc_settlement_transfer.delete'),
            'accountTypes' => AccountType::onlyCurrentAccount()->get()->map(fn ($a) => ['id' => $a->id, 'name' => $a->getName()])->values(),
            'interestDestinations' => LcSettlementInternalMoneyTransfer::getInterestDestinationsForSelect(),
            'urls' => [
                'index' => route('lc-settlement-internal-money-transfers.index', ['company' => $company->id]),
                'getAccountNumbersForType' => $this->companyScopedUrl($company, 'money-received/get-account-numbers-based-on-account-type'),
            ],
        ]);
    }

    /**
     * Create form. UNCHANGED, deliberately — no longer linked from the UI
     * (see class docblock), left in place rather than deleted.
     */
    public function create(Company $company)
    {
        return Inertia::render('LcSettlementInternalMoneyTransfer/Form', array_merge($this->formViewData($company), ['instructionsUrl' => route('view.instructions', ['company' => $company->id, 'page' => PageInstructions::LC_SETTLEMENT_FORM])]));
    }

    /**
     * Edit form — same Vue page as create(), with `model` populated. Only
     * ever reached, per the Index page, via the MOST RECENT settlement's
     * own id for a given LC (client-confirmed scope — see class docblock).
     */
    public function edit(Company $company, LcSettlementInternalMoneyTransfer $lcSettlementInternalTransfer)
    {
        $viewData = $this->formViewData($company);
        $viewData['urls']['update'] = route('lc-settlement-internal-money-transfers.update', ['company' => $company->id, 'lc_settlement_internal_transfer' => $lcSettlementInternalTransfer->id]);
        $viewData['urls']['back'] = route('lc-settlement-internal-money-transfers.index', ['company' => $company->id]);
        $viewData['interestDestinations'] = LcSettlementInternalMoneyTransfer::getInterestDestinationsForSelect();
        $viewData['model'] = [
            'id' => $lcSettlementInternalTransfer->id,
            'transfer_date' => $lcSettlementInternalTransfer->getTransferDate(),
            'from_bank_id' => $lcSettlementInternalTransfer->getFromBankId(),
            'currency' => $lcSettlementInternalTransfer->getCurrency(),
            'from_account_type_id' => $lcSettlementInternalTransfer->getFromAccountTypeId(),
            'from_account_number' => $lcSettlementInternalTransfer->getFromAccountNumber(),
            'to_letter_of_credit_issuance_id' => $lcSettlementInternalTransfer->to_letter_of_credit_issuance_id,
            'to_lc_issuance_name' => $lcSettlementInternalTransfer->getLetterOfCreditIssuanceTransactionName(),
            'amount' => (float) $lcSettlementInternalTransfer->getAmount(),
            'interest_amount' => (float) $lcSettlementInternalTransfer->getInterestAmount(),
            'interest_destination' => $lcSettlementInternalTransfer->getInterestDestination(),
            'user_comment' => $lcSettlementInternalTransfer->user_comment,
        ];

        return Inertia::render('LcSettlementInternalMoneyTransfer/Form', array_merge($viewData, ['instructionsUrl' => route('view.instructions', ['company' => $company->id, 'page' => PageInstructions::LC_SETTLEMENT_FORM])]));
    }

    /**
     * Shared prop-building for create()/edit() — banks, account types.
     */
    protected function formViewData(Company $company): array
    {
        return [
            'company' => ['id' => $company->id, 'name' => $company->getName()],
            'model' => null,
            'currencies' => collect(getCurrencies())->map(fn ($label, $code) => ['code' => $code, 'label' => $label])->values(),
            'financialInstitutionBanks' => FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get()
                ->map(fn ($b) => ['id' => $b->id, 'name' => $b->getName()])->values(),
            'accountTypes' => AccountType::onlyCurrentAccount()->get()
                ->map(fn ($a) => ['id' => $a->id, 'name' => $a->getName()])->values(),
            'urls' => [
                'store' => route('lc-settlement-internal-money-transfers.store', ['company' => $company->id]),
                'back' => route('lc-settlement-internal-money-transfers.index', ['company' => $company->id]),
                'getLcIssuancesForBank' => route('update.lc.issuance.based.on.financial.institution', ['company' => $company->id]),
                'getRemainingBalance' => route('get.remaining.balance.lc.issuance', ['company' => $company->id]),
                'getAccountNumbersForType' => $this->companyScopedUrl($company, 'money-received/get-account-numbers-based-on-account-type'),
            ],
        ];
    }

    protected function companyScopedUrl(Company $company, string $path): string
    {
        return url('/'.app()->getLocale().'/'.$company->id.'/'.ltrim($path, '/'));
    }

    /**
     * * الكنترولر ده مالوش Form Request، فالتحقق بيتعمل هنا
     * * التحويل بيحرّك فلوس فعليًا فما ينفعش يتسجّل بتاريخ بعد النهاردة
     * * لازم يتنفّذ قبل ما الترانزاكشن تبدأ
     */
    protected function validateTransferDate(Request $request): void
    {
        $request->validate([
            'transfer_date' => ['required', new DateMustBeLessThanOrEqualDate(null, now(), __('Transaction Date Can Not Be Greater Than Today'))],
        ]);
    }

    public function store(Company $company, Request $request)
    {
        $this->validateTransferDate($request);
        /**
         * * الحفظ كله جوه ترانزاكشن واحدة
         */
        return OdooSync::transaction(function () use ($company, $request) {
            return $this->storeWithinTransaction($company, $request);
        });
    }

    protected function storeWithinTransaction(Company $company, Request $request)
    {
        $internalMoneyTransfer = new LcSettlementInternalMoneyTransfer;
        $companyId = $company->id;
        /**
         * ⚠️ REAL BUG FIXED HERE (client-flagged, 2026-08-18): storeBasicForm()
         * (see App\Traits\HasBasicStoreRequest) only ever sets columns that
         * are actually present as keys in the request — company_id was
         * never one of them (neither the old manual-create form nor the
         * new Settle popup ever send it), so every insert here left
         * company_id unset and failed its foreign key constraint the
         * moment this code path was actually exercised. Setting it
         * directly on the model, before storeBasicForm() runs, survives
         * that loop untouched (it only touches columns the request
         * mentions) and gets persisted by storeBasicForm()'s own save().
         */
        $internalMoneyTransfer->company_id = $companyId;
        $letterOfCreditIssuance = LetterOfCreditIssuance::find($request->get('to_letter_of_credit_issuance_id'));
        if (!$letterOfCreditIssuance) {
            return redirect()->back()->withErrors(['to_letter_of_credit_issuance_id' => __('Letter of Credit Issue not found')])->withInput();
        }
        $letterOfCreditFacilityId = $letterOfCreditIssuance->getLcFacilityId();
        $lcFacilityLimit = $letterOfCreditIssuance->getLcFacilityLimit();
        $supplierName = $letterOfCreditIssuance->getSupplierName();
        $lcType = $letterOfCreditIssuance->getLcType();
        $transactionName = $letterOfCreditIssuance->getTransactionName();
        $transferDate = $request->get('transfer_date');
        $transferAmount = $request->get('amount');
        $internalMoneyTransfer->type = LcSettlementInternalMoneyTransfer::BANK_TO_LETTER_OF_CREDIT;
        $internalMoneyTransfer->storeBasicForm($request);
        $fromFinancialInstitutionId = $request->get('from_bank_id');
        $fromAccountTypeId = $request->get('from_account_type_id');
        $fromAccountNumber = $request->get('from_account_number');

        $fromAccountType = AccountType::find($fromAccountTypeId);

        $commentEn = __('Internal Transfer [ :supplierName ] [ :lcType ] Transaction Name [ :transactionName ]', ['supplierName' => $supplierName, 'lcType' => $lcType, 'transactionName' => $transactionName], 'en');
        $commentAr = __('Internal Transfer [ :supplierName ] [ :lcType ] Transaction Name [ :transactionName ]', ['supplierName' => $supplierName, 'lcType' => $lcType, 'transactionName' => $transactionName], 'ar');
        $internalMoneyTransfer->handleBankToLetterOfCreditTransfer($companyId, $letterOfCreditFacilityId, $lcFacilityLimit, $fromAccountType, $fromAccountNumber, $fromFinancialInstitutionId, $letterOfCreditIssuance, $transferDate, $transferAmount, $commentEn, $commentAr);

        /**
         * * الفايدة بقت جزء من التسوية نفسها (client-requested, 2026-08-18) —
         * * مش بتتدفع وقت "Mark as Paid" للمورد. لو مفيش فايدة في التسوية دي
         * * (interest_amount == 0) الميثود دي مش بتعمل حاجة خالص.
         */
        $interestAmount = (float) number_unformat($request->get('interest_amount', 0));
        $interestDestination = $request->get('interest_destination');
        $interestCommentEn = __('LC Interest Settlement [ :supplierName ] [ :lcType ] Transaction Name [ :transactionName ]', ['supplierName' => $supplierName, 'lcType' => $lcType, 'transactionName' => $transactionName], 'en');
        $interestCommentAr = __('LC Interest Settlement [ :supplierName ] [ :lcType ] Transaction Name [ :transactionName ]', ['supplierName' => $supplierName, 'lcType' => $lcType, 'transactionName' => $transactionName], 'ar');
        $internalMoneyTransfer->handleInterestSettlement($companyId, $letterOfCreditFacilityId, $lcFacilityLimit, $fromAccountType, $fromAccountNumber, $fromFinancialInstitutionId, $letterOfCreditIssuance, $transferDate, $interestAmount, $interestDestination, $interestCommentEn, $interestCommentAr);

        return redirect()->route('lc-settlement-internal-money-transfers.index', ['company' => $company->id])
            ->with('success', __('Data Store Successfully'));
    }

    public function update(Company $company, Request $request, LcSettlementInternalMoneyTransfer $lcSettlementInternalTransfer)
    {
        $this->validateTransferDate($request);
        $this->validateSettleRequest($request, $lcSettlementInternalTransfer->letterOfCreditIssuance, $lcSettlementInternalTransfer);
        /**
         * * التعديل معمول كـ حذف ثم إنشاء
         * * فلازم يكون كله في ترانزاكشن واحدة
         */
        /**
         * Wrapped so the delete+create above records as the single edit it
         * is, and this record's history follows it onto the new row.
         * See App\Support\Activity\ActivityLogger::asUpdate().
         */
        \App\Support\Activity\ActivityLogger::asUpdate($lcSettlementInternalTransfer, function () use ($company, $request, $lcSettlementInternalTransfer) {
	        OdooSync::transaction(function () use ($company, $request, $lcSettlementInternalTransfer) {
	            $lcSettlementInternalTransfer->deleteRelations();
	            $lcSettlementInternalTransfer->delete();

	            $this->storeWithinTransaction($company, $request);
	        });
        });

        return redirect()->route('lc-settlement-internal-money-transfers.index', ['company' => $company->id])
            ->with('success', __('Item Has Been Updated Successfully'));
    }

    public function destroy(Company $company, LcSettlementInternalMoneyTransfer $lcSettlementInternalTransfer)
    {
        OdooSync::transaction(function () use ($lcSettlementInternalTransfer) {
            $lcSettlementInternalTransfer->deleteRelations();
            $lcSettlementInternalTransfer->delete();
        });

        return redirect()->back()->with('success', __('Item Has Been Delete Successfully'));
    }

    /**
     * AJAX endpoint the "Mark As Settle" popup calls every time the
     * settlement date changes, to re-price the interest estimate.
     * Remaining principal comes straight off the LC's own running
     * balance (LetterOfCreditIssuance::getRemainingBankSettlementAmount(),
     * which already nets out every settlement made so far). Interest is
     * calculated from whichever is more recent — the previous settlement's
     * own date, or the LC's original payment/draw date if this is the
     * first settlement — up to the chosen settlement date, using the LC
     * Facility's own interest rate in effect on that date.
     *
     * Client-specified formula (2026-08-18):
     *   Interest = Remaining LC Amount × interest_rate% × (days ÷ 360)
     */
    public function getSettleData(Company $company, LetterOfCreditIssuance $letterOfCreditIssuance, Request $request)
    {
        $settlementDate = $request->get('settlement_date') ?: now()->format('Y-m-d');
        $remainingAmount = $letterOfCreditIssuance->getRemainingBankSettlementAmount();

        $lastSettlement = $letterOfCreditIssuance->getLatestLcSettlementInternalMoneyTransfer();
        $fromDate = $lastSettlement ? $lastSettlement->getTransferDate() : $letterOfCreditIssuance->getPaymentDate();
        $days = max(0, Carbon::make($fromDate)->diffInDays(Carbon::make($settlementDate), false));

        $facility = $letterOfCreditIssuance->letterOfCreditFacility;
        $terms = $facility ? $facility->getTermsAsOfDate($settlementDate) : null;
        $interestRate = $terms ? (float) $terms->interest_rate : 0;

        $calculatedInterest = round($remainingAmount * ($interestRate / 100) * ($days / 360), 2);

        return response()->json([
            'status' => true,
            'remaining_amount' => $remainingAmount,
            'days' => $days,
            'interest_rate' => $interestRate,
            'calculated_interest' => $calculatedInterest,
            'from_date' => $fromDate,
        ]);
    }

    protected function validateSettleRequest(Request $request, ?LetterOfCreditIssuance $letterOfCreditIssuance, ?LcSettlementInternalMoneyTransfer $editingTransfer = null): void
    {
        // Editing the latest settlement should be able to re-submit its own
        // amount without that amount being counted against itself as
        // "already settled" — same compensation idea used throughout this
        // app's other room/balance rules.
        $alreadySettled = 0;
        if ($letterOfCreditIssuance) {
            $alreadySettled = $letterOfCreditIssuance->lcSettlementInternalMoneyTransfers
                ->when($editingTransfer, fn ($collection) => $collection->reject(fn ($t) => $t->id === $editingTransfer->id))
                ->sum('amount');
        }
        $originalFinancedAmount = $letterOfCreditIssuance ? $letterOfCreditIssuance->getRemainingBankSettlementAmount() + $letterOfCreditIssuance->lcSettlementInternalMoneyTransfers->sum('amount') : 0;
        $maxSettleable = max(0, $originalFinancedAmount - $alreadySettled);

        $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'lte:'.($maxSettleable ?: 0.01)],
            'transfer_date' => ['required'],
            'from_bank_id' => ['required'],
            'from_account_type_id' => ['required'],
            'from_account_number' => ['required'],
            'interest_amount' => ['nullable', 'numeric', 'gte:0'],
            'interest_destination' => ['nullable', Rule::in(array_column(LcSettlementInternalMoneyTransfer::getInterestDestinationsForSelect(), 'value'))],
        ], [
            'amount.lte' => __('This exceeds what is left to settle on this LC.'),
        ]);
    }

    /**
     * "Mark As Settle" — records one settlement (full or partial) of a
     * bank-financed LC's outstanding balance with the bank. Reuses
     * storeWithinTransaction() so a settlement posts through the exact
     * same principal + interest logic as the legacy manual-transfer flow
     * and Edit both do — one code path, three entry points.
     */
    public function settle(Company $company, Request $request, LetterOfCreditIssuance $letterOfCreditIssuance)
    {
        $this->validateTransferDate($request);
        /**
         * Client-requested (2026-08-18): the paying bank is never a user
         * choice here — it's always the same bank the LC itself was
         * issued with. Forced server-side regardless of whatever the
         * client actually submits, so this holds even if the UI's own
         * removal of the picker is ever bypassed.
         */
        $request->merge(['from_bank_id' => $letterOfCreditIssuance->getFinancialInstitutionId()]);
        $this->validateSettleRequest($request, $letterOfCreditIssuance);

        $request->merge(['to_letter_of_credit_issuance_id' => $letterOfCreditIssuance->id]);

        return OdooSync::transaction(function () use ($company, $request) {
            return $this->storeWithinTransaction($company, $request);
        });
    }

    /**
     * Wipes every settlement made so far for this LC — principal AND
     * interest, whichever destination each one used — returning it to
     * fully unpaid/pending with the bank. This is how a row gets removed
     * from "Pending LC Settlements" without going through Delete (there is
     * no Delete on this screen anymore — see class docblock): once every
     * settlement is reset, the LC is still status=PAID (still owed to the
     * bank in full), it just shows its full original amount as remaining
     * again.
     */
    public function reset(Company $company, LetterOfCreditIssuance $letterOfCreditIssuance)
    {
        OdooSync::transaction(function () use ($letterOfCreditIssuance) {
            $letterOfCreditIssuance->lcSettlementInternalMoneyTransfers->each(function (LcSettlementInternalMoneyTransfer $transfer) {
                $transfer->deleteRelations();
                $transfer->delete();
            });
        });

        return redirect()->back()->with('success', __('Settlement Has Been Reset Successfully'));
    }
}

