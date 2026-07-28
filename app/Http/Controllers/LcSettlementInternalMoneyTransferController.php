<?php
namespace App\Http\Controllers;

use App\Models\AccountType;
use App\Models\Bank;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\LcSettlementInternalMoneyTransfer;
use App\Models\LetterOfCreditIssuance;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;
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
 * ── Frontend migration status ───────────────────────────────────
 *   index()          → Inertia::render, Pages/LcSettlementInternalMoneyTransfer/Index.vue
 *   create()/edit()  → Inertia::render, Pages/LcSettlementInternalMoneyTransfer/Form.vue
 *   store()/update()/destroy() → real Laravel redirects (Inertia-compatible),
 *                                 business logic untouched.
 */
class LcSettlementInternalMoneyTransferController
{
    use GeneralFunctions;

    /**
     * Read-only list page. Renders Pages/LcSettlementInternalMoneyTransfer/Index.vue.
     */
    public function index(Company $company, Request $request)
    {
        $paginationPerPage = GeneralFunctions::getPaginationLimit();
        $numberOfMonthsBetweenEndDateAndStartDate = 18;
        $currentType = LcSettlementInternalMoneyTransfer::BANK_TO_LETTER_OF_CREDIT;

        $startDate = $request->input('startDate') ?: now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d');
        $endDate = $request->input('endDate') ?: now()->format('Y-m-d');

        $transfers = $company->getBankToLcSettlementInternalMoneyTransfers($startDate, $endDate, $currentType)
            ->with(['fromBank', 'fromAccountType', 'letterOfCreditIssuance'])
            ->orderByDesc('id')
            ->paginate($paginationPerPage)
            ->withQueryString()
            ->through(fn (LcSettlementInternalMoneyTransfer $transfer) => [
                'id' => $transfer->id,
                'transfer_date_formatted' => $transfer->getTransferDateFormatted(),
                'amount_formatted' => $transfer->getAmountFormatted(),
                'currency' => $transfer->getCurrencyFormatted(),
                'from_bank_name' => $transfer->getFromBankName(),
                'from_account_type_name' => $transfer->getFromAccountTypeName(),
                'from_account_number' => $transfer->getFromAccountNumber(),
                'to_lc_issuance_name' => $transfer->getLetterOfCreditIssuanceTransactionName(),
                'user_comment' => $transfer->user_comment,
                'edit_url' => route('lc-settlement-internal-money-transfers.edit', ['company' => $company->id, 'lc_settlement_internal_transfer' => $transfer->id]),
                'delete_url' => route('lc-settlement-internal-money-transfers.destroy', ['company' => $company->id, 'lc_settlement_internal_transfer' => $transfer->id]),
            ]);

        return Inertia::render('LcSettlementInternalMoneyTransfer/Index', [
            'company' => ['id' => $company->id, 'name' => $company->getName()],
            'transfers' => $transfers,
            'filterDates' => ['startDate' => $startDate, 'endDate' => $endDate],
            'canCreate' => auth()->user()->can('create lc settlement internal transfer'),
            'canUpdate' => auth()->user()->can('update lc settlement internal transfer'),
            'canDelete' => auth()->user()->can('delete lc settlement internal transfer'),
            'urls' => [
                'create' => route('lc-settlement-internal-money-transfers.create', ['company' => $company->id]),
                'index' => route('lc-settlement-internal-money-transfers.index', ['company' => $company->id]),
            ],
        ]);
    }

    /**
     * Create form. Renders Pages/LcSettlementInternalMoneyTransfer/Form.vue.
     */
    public function create(Company $company)
    {
        return Inertia::render('LcSettlementInternalMoneyTransfer/Form', $this->formViewData($company));
    }

    /**
     * Edit form — same Vue page as create(), with `model` populated.
     */
    public function edit(Company $company, LcSettlementInternalMoneyTransfer $lcSettlementInternalTransfer)
    {
        $viewData = $this->formViewData($company);
        $viewData['urls']['update'] = route('lc-settlement-internal-money-transfers.update', ['company' => $company->id, 'lc_settlement_internal_transfer' => $lcSettlementInternalTransfer->id]);
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
            'user_comment' => $lcSettlementInternalTransfer->user_comment,
        ];

        return Inertia::render('LcSettlementInternalMoneyTransfer/Form', $viewData);
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

    public function store(Company $company, Request $request)
    {
        $internalMoneyTransfer = new LcSettlementInternalMoneyTransfer;
        $companyId = $company->id;
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

        return redirect()->route('lc-settlement-internal-money-transfers.index', ['company' => $company->id])
            ->with('success', __('Data Store Successfully'));
    }

    public function update(Company $company, Request $request, LcSettlementInternalMoneyTransfer $lcSettlementInternalTransfer)
    {
        $lcSettlementInternalTransfer->deleteRelations();
        $lcSettlementInternalTransfer->delete();
        $this->store($company, $request);

        return redirect()->route('lc-settlement-internal-money-transfers.index', ['company' => $company->id])
            ->with('success', __('Item Has Been Updated Successfully'));
    }

    public function destroy(Company $company, LcSettlementInternalMoneyTransfer $lcSettlementInternalTransfer)
    {
        $lcSettlementInternalTransfer->deleteRelations();
        $lcSettlementInternalTransfer->delete();

        return redirect()->back()->with('success', __('Item Has Been Delete Successfully'));
    }
}
