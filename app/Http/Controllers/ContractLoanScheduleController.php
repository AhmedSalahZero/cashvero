<?php

namespace App\Http\Controllers;

use App\Models\AccountType;
use App\Models\Company;
use App\Models\ContractLoanSchedule;
use App\Models\ContractLoanScheduleSettlement;
use App\Models\FinancialInstitutionAccount;
use App\Rules\DateMustBeLessThanOrEqualDate;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * ContractLoanScheduleController
 * ------------------------------------------------------------------
 * Mirrors MediumTermLoanController's settlement methods almost
 * exactly, for Leasing Contract installments instead of Medium Term
 * Loan ones — with one real difference: each Contract Loan Schedule
 * row carries its own CHEQUE (cheque number, drawee bank, account
 * number), since leasing installments are paid via post-dated
 * cheques. The original's shared settlement Blade view even swaps
 * the "Schedule Payment" label for "Cheque Amount" for this model —
 * carried over faithfully here.
 *
 * getAccountNumbersForDraweeBank() is a separate AJAX helper used by
 * the (already-migrated) schedule upload/edit-row flow, not by the
 * settlement screen — UNCHANGED, deliberately.
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   ✅ viewSettlement() / editSettlement() → MIGRATED to Vue +
 *      Inertia. Renders resources/js/Pages/LeasingContractSettlement/Index.vue.
 *   ✅ storeSettlement() / updateSettlement() / deleteSettlement() /
 *      getAccountNumbersForDraweeBank() → UNCHANGED, deliberately. All
 *      already redirect or JSON-appropriately.
 */
class ContractLoanScheduleController extends Controller
{
    public function getAccountNumbersForDraweeBank(Company $company, Request $request)
    {
        $draweeBankName = trim((string) $request->get('drawee_bank', ''));
        $accountNumbers = $draweeBankName !== ''
            ? getAccountNumbersForDraweeBankName($company->id, $draweeBankName)
            : [];

        return response()->json([
            'data' => $accountNumbers,
        ]);
    }

    /**
     * Shows the payment-settlement screen for one contract schedule
     * (cheque) installment.
     *
     * ✅ MIGRATED to Vue + Inertia. Renders
     * resources/js/Pages/LeasingContractSettlement/Index.vue.
     */
    public function viewSettlement(Company $company, ContractLoanSchedule $contractLoanSchedule)
    {
        $contractLoanSchedule->load('leasingContract.contractLoanSchedules', 'draweeBank');

        if (! $contractLoanSchedule->canSettle()) {
            return redirect()
                ->back()
                ->with('warning', __('This contract schedule installment is not linked to an active leasing contract with a drawee bank.'));
        }

        return \Inertia\Inertia::render('LeasingContractSettlement/Index', $this->getSettlementPageVars($company, $contractLoanSchedule, null));
    }

    /**
     * Shows the edit form for an existing settlement, pre-filled.
     *
     * ✅ MIGRATED to Vue + Inertia, same page component as
     * viewSettlement(), distinguished by a non-null
     * `editingSettlement` prop.
     */
    public function editSettlement(Company $company, Request $request, ContractLoanScheduleSettlement $contractLoanScheduleSettlement)
    {
        $contractLoanSchedule = $contractLoanScheduleSettlement->contractLoanSchedule;

        if (! $contractLoanSchedule || ! $contractLoanSchedule->canSettle()) {
            return redirect()
                ->back()
                ->with('warning', __('This contract schedule installment is not linked to an active leasing contract with a drawee bank.'));
        }

        return \Inertia\Inertia::render('LeasingContractSettlement/Index', $this->getSettlementPageVars($company, $contractLoanSchedule, $contractLoanScheduleSettlement));
    }

    /**
     * Builds every prop LeasingContractSettlement/Index.vue needs.
     * Read-only display data — UNCHANGED source queries, just
     * reshaped for Vue.
     */
    protected function getSettlementPageVars(Company $company, ContractLoanSchedule $contractLoanSchedule, ?ContractLoanScheduleSettlement $editingSettlement): array
    {
        $currentAccountType = AccountType::onlyCurrentAccount()->first();
        $currentAccounts = FinancialInstitutionAccount::getAllAccountNumberForCurrency($company->id, $contractLoanSchedule->getCurrency(), $contractLoanSchedule->getFinancialInstitutionId());

        return [
            'company' => ['id' => $company->id],
            'contractLoanSchedule' => [
                'id' => $contractLoanSchedule->id,
                'leasing_contract_name' => $contractLoanSchedule->getLeasingContractName(),
                'date_formatted' => $contractLoanSchedule->getDateFormatted(),
                'currency' => $contractLoanSchedule->getCurrency(),
                'beginning_balance_formatted' => $contractLoanSchedule->getBeginningBalanceFormatted(),
                'cheque_amount_formatted' => $contractLoanSchedule->getChequeAmountFormatted(),
                'cheque_number' => $contractLoanSchedule->getChequeNumber(),
                'drawee_bank_name' => $contractLoanSchedule->hasDraweeBank() ? $contractLoanSchedule->getDraweeBankName() : null,
                'interest_amount_formatted' => $contractLoanSchedule->getInterestAmountFormatted(),
                'principle_amount_formatted' => $contractLoanSchedule->getPrincipleAmountFormatted(),
                'end_balance_formatted' => $contractLoanSchedule->getEndBalanceFormatted(),
                'settlement_default_date' => $contractLoanSchedule->getSettlementDefaultDate(),
                'remaining' => $contractLoanSchedule->getRemaining(),
            ],
            // {value: stored account number, label: what the user reads}.
            // The two differ for a shareholder-owned account (D7,
            // docs/shareholder-accounts.md) — sending only the labels here
            // would store the owner's name in current_account_number.
            'currentAccounts' => collect($currentAccounts)
                ->map(fn ($label, $accountNumber) => ['value' => (string) $accountNumber, 'label' => (string) $label])
                ->values(),
            'currentAccountTypeId' => $currentAccountType?->id ?? 0,
            'financialInstitutionId' => $contractLoanSchedule->getFinancialInstitutionId(),
            'balanceLookupUrl' => route('update.balance.and.net.balance.based.on.account.number', ['company' => $company->id]),
            'settlements' => $contractLoanSchedule->settlements->map(function (ContractLoanScheduleSettlement $s) use ($company, $editingSettlement) {
                return [
                    'id' => $s->id,
                    'date_formatted' => $s->getDateFormatted(),
                    'account_number' => $s->getAccountNumber(),
                    'amount_formatted' => $s->getAmountFormatted(),
                    'edit_url' => route('edit.contract.loan.schedule.settlements', ['company' => $company->id, 'contractLoanScheduleSettlement' => $s->id]),
                    'delete_url' => route('delete.contract.loan.schedule.settlements', ['company' => $company->id, 'contractLoanScheduleSettlement' => $s->id]),
                    'is_being_edited' => $editingSettlement && $editingSettlement->id === $s->id,
                ];
            })->values(),
            // Only the LAST settlement is editable/deletable — same
            // rule as Loan Schedule Settlement, confirmed from the
            // same shared original Blade view.
            'lastSettlementId' => $contractLoanSchedule->settlements->sortBy('date')->last()?->id,
            'editingSettlement' => $editingSettlement ? [
                'id' => $editingSettlement->id,
                'date' => $editingSettlement->getDate(),
                'amount' => $editingSettlement->getAmount(),
                'current_account_number' => $editingSettlement->getCurrentAccountNumber(),
            ] : null,
            'submitUrl' => $editingSettlement
                ? route('update.contract.loan.schedule.settlements', ['company' => $company->id, 'contractLoanScheduleSettlement' => $editingSettlement->id])
                : route('store.contract.loan.schedule.settlements', ['company' => $company->id, 'contractLoanSchedule' => $contractLoanSchedule->id]),
            'backUrl' => route('view.uploading', ['company' => $company->id, 'loanId' => $contractLoanSchedule->getLeasingContractId(), 'model' => 'ContractLoanSchedule']),
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
     * Records a payment settlement against a contract schedule
     * (cheque) installment. UNCHANGED, deliberately.
     */
    public function storeSettlement(Company $company, Request $request, ContractLoanSchedule $contractLoanSchedule)
    {
        if (! $contractLoanSchedule->canSettle()) {
            return redirect()
                ->back()
                ->with('warning', __('This contract schedule installment is not linked to an active leasing contract with a drawee bank.'));
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
            $contractLoanSchedule,
            $currentAccountNumber,
            $date,
            $amount
        );

        if ($balanceValidationResponse) {
            return $balanceValidationResponse;
        }

        $settlement = $contractLoanSchedule->settlements()->create([
            'current_account_number' => $currentAccountNumber,
            'amount' => $amount,
            'date' => $date,
            'company_id' => $company->id,
        ]);

        $financialInstitutionId = $contractLoanSchedule->getFinancialInstitutionId();
        $accountType = AccountType::onlyCurrentAccount()->first();
        $commentEn = __('Settlement For Contract :contract Installment No. :number', [
            'contract' => $contractLoanSchedule->getLeasingContractName(),
            'number' => $contractLoanSchedule->getInstallmentNumber(),
        ], 'en');
        $commentAr = __('Settlement For Contract :contract Installment No. :number', [
            'contract' => $contractLoanSchedule->getLeasingContractName(),
            'number' => $contractLoanSchedule->getInstallmentNumber(),
        ], 'ar');

        $settlement->handleCreditStatement($company->id, $financialInstitutionId, $accountType, $currentAccountNumber, null, $date, $amount, null, null, $commentEn, $commentAr);
        $settlement->handleLoanStatement($company->id, $financialInstitutionId, $currentAccountNumber, $date, $amount, $commentEn, $commentAr);

        /**
         * * لو العقد ده اتدفعت منه فواتير موردين من خلال كاش فيرو (نوع الدفع
         * * Through Leasing) يبقي ليه كشف حساب سحب/سداد خاص بيه .. والقسط
         * * اللي اتسدد دلوقتي بيرجّع جزء ال principle بتاعه للمتاح فيه.
         */
        $settlement->handleLeasingContractRepayment($company->id, $commentEn, $commentAr);

        // ⚠️ Confirmed fix: this was back(), which leaves the user on
        // the same settlement page after paying — the natural next
        // step is seeing the updated schedule table, not re-settling
        // the same installment. Same destination helper already used
        // for the upload flow.
        return redirect()->route('view.uploading', getUploadingRouteParams(
            $company->id,
            'ContractLoanSchedule',
            (string) $contractLoanSchedule->getLeasingContractId()
        ));
    }

    /**
     * Deletes then re-creates the settlement fresh. UNCHANGED.
     */
    public function updateSettlement(Company $company, Request $request, ContractLoanScheduleSettlement $contractLoanScheduleSettlement)
    {
        $contractLoanSchedule = $contractLoanScheduleSettlement->contractLoanSchedule;
        $this->deleteSettlement($company, $request, $contractLoanScheduleSettlement);
        $this->storeSettlement($company, $request, $contractLoanSchedule);

        return redirect()->route('view.contract.loan.schedule.settlements', [
            'contractLoanSchedule' => $contractLoanSchedule->id,
            'company' => $company->id,
        ]);
    }

    /**
     * Deletes a settlement and reverses everything it posted.
     * UNCHANGED.
     */
    public function deleteSettlement(Company $company, Request $request, ContractLoanScheduleSettlement $contractLoanScheduleSettlement)
    {
        $contractLoanScheduleSettlement->deleteAllRelations();
        $contractLoanScheduleSettlement->delete();

        return back();
    }

    protected function validateCurrentAccountBalanceForSettlement(
        Company $company,
        ContractLoanSchedule $contractLoanSchedule,
        string $accountNumber,
        string $date,
        float $amount,
        ?ContractLoanScheduleSettlement $editingSettlement = null
    ): ?\Illuminate\Http\RedirectResponse {
        $accountType = AccountType::onlyCurrentAccount()->first();

        if (! $accountType) {
            return back()->with('fail', __('Current account type is not configured.'))->withInput();
        }

        $balanceRequest = Request::create('/', 'GET', [
            'accountType' => $accountType->id,
            'accountNumber' => $accountNumber,
            'financialInstitutionId' => $contractLoanSchedule->getFinancialInstitutionId(),
            'balanceDate' => Carbon::make($date)->format('Y-m-d'),
            'modelId' => $editingSettlement?->id,
            'modelType' => 'ContractLoanScheduleSettlement',
        ]);

        $balanceResponse = (new MoneyReceivedController())->updateNetBalanceBasedOnAccountNumber($balanceRequest, $company);
        $balance = (float) ($balanceResponse->getData()->balance ?? 0);

        if ($amount > $balance) {
            return back()->with('fail', __('Net Balance Less Than Paid Amount'))->withInput();
        }

        return null;
    }
}
