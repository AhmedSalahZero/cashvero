<?php

namespace App\Http\Controllers;

use App\Models\AccountType;
use App\Models\Company;
use App\Models\ContractLoanSchedule;
use App\Models\ContractLoanScheduleSettlement;
use App\Rules\DateMustBeGreaterThanOrEqualDate;
use App\Rules\DateMustBeLessThanOrEqualDate;
use App\Support\ShareholderAccounts\AccountNumberLabel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
 * ── Leasing cheques follow the supplier payable-cheque lifecycle ────
 * (2026-10-19) Each installment cheque is posted to its drawee current
 * account as soon as the schedule row exists, dated at the due date
 * (ContractLoanSchedule::syncChequeStatement()). Paying it is then ONE
 * step, exactly like MoneyPaymentController::markChequesAsPaid():
 * markAsPaid() moves that same row to the actual payment date, and
 * unmarkAsPaid() moves it back. Partial settlements were removed: each
 * one used to post its own bank row, which on top of the pre-posted
 * cheque row would count the money twice. Settlements recorded the old
 * way are still listed and their last one can still be deleted.
 *
 * Renders resources/js/Pages/LeasingContractSettlement/Index.vue.
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
     * The cheque screen for one installment: its details, and either the
     * Mark As Paid form or, once paid, the Unmark action.
     */
    public function viewSettlement(Company $company, ContractLoanSchedule $contractLoanSchedule)
    {
        $contractLoanSchedule->load('leasingContract.contractLoanSchedules', 'draweeBank', 'settlements');

        if (! $contractLoanSchedule->canSettle()) {
            return redirect()
                ->back()
                ->with('warning', __('This contract schedule installment is not linked to an active leasing contract with a drawee bank.'));
        }

        return \Inertia\Inertia::render('LeasingContractSettlement/Index', $this->getSettlementPageVars($company, $contractLoanSchedule));
    }

    /**
     * Builds every prop LeasingContractSettlement/Index.vue needs.
     */
    protected function getSettlementPageVars(Company $company, ContractLoanSchedule $contractLoanSchedule): array
    {
        $account = $contractLoanSchedule->getChequeAccount();
        $financialInstitutionId = $contractLoanSchedule->getFinancialInstitutionId();
        $legacySettlements = $contractLoanSchedule->settlements->reject(fn (ContractLoanScheduleSettlement $s) => $s->isChequePayment());
        $lastLegacySettlementId = $legacySettlements->sortBy('id')->last()?->id;

        return [
            'company' => ['id' => $company->id],
            'contractLoanSchedule' => [
                'id' => $contractLoanSchedule->id,
                'leasing_contract_name' => $contractLoanSchedule->getLeasingContractName(),
                'date' => isValidScheduleDate($contractLoanSchedule->getDate()) ? Carbon::make($contractLoanSchedule->getDate())->format('Y-m-d') : null,
                'date_formatted' => $contractLoanSchedule->getDateFormatted(),
                'currency' => $contractLoanSchedule->getCurrency(),
                'beginning_balance_formatted' => $contractLoanSchedule->getBeginningBalanceFormatted(),
                'cheque_amount_formatted' => $contractLoanSchedule->getChequeAmountFormatted(),
                'amount_to_pay_formatted' => number_format($contractLoanSchedule->getChequeStatementAmount()),
                'cheque_number' => $contractLoanSchedule->getChequeNumber(),
                'drawee_bank_name' => $contractLoanSchedule->hasDraweeBank() ? $contractLoanSchedule->getDraweeBankName() : null,
                'account_number' => $account
                    ? AccountNumberLabel::forCurrentAccount($company->id, $financialInstitutionId, $account->account_number)
                    : null,
                'interest_amount_formatted' => $contractLoanSchedule->getInterestAmountFormatted(),
                'principle_amount_formatted' => $contractLoanSchedule->getPrincipleAmountFormatted(),
                'end_balance_formatted' => $contractLoanSchedule->getEndBalanceFormatted(),
                'settlement_default_date' => $contractLoanSchedule->getSettlementDefaultDate(),
                'is_paid' => $contractLoanSchedule->isChequePaid(),
                'actual_payment_date_formatted' => $contractLoanSchedule->getActualPaymentDateFormatted(),
            ],
            // Partial settlements recorded before cheques were pre-posted.
            'settlements' => $legacySettlements->map(function (ContractLoanScheduleSettlement $s) use ($company, $financialInstitutionId, $lastLegacySettlementId, $contractLoanSchedule) {
                return [
                    'id' => $s->id,
                    'date_formatted' => $s->getDateFormatted(),
                    'account_number' => AccountNumberLabel::forCurrentAccount($company->id, $financialInstitutionId, $s->getAccountNumber()),
                    'amount_formatted' => $s->getAmountFormatted(),
                    'delete_url' => $s->id === $lastLegacySettlementId && ! $contractLoanSchedule->isChequePaid()
                        ? route('delete.contract.loan.schedule.settlements', ['company' => $company->id, 'contractLoanScheduleSettlement' => $s->id])
                        : null,
                ];
            })->values(),
            'markAsPaidUrl' => route('contract.loan.schedule.mark.as.paid', ['company' => $company->id, 'contractLoanSchedule' => $contractLoanSchedule->id]),
            'unmarkAsPaidUrl' => route('contract.loan.schedule.unmark.as.paid', ['company' => $company->id, 'contractLoanSchedule' => $contractLoanSchedule->id]),
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
     * Marks the installment cheque as paid on its actual payment date.
     * Mirrors MoneyPaymentController::markChequesAsPaid(): same date
     * rules, same balance check, and the already-posted cheque row is
     * MOVED to the payment date rather than a second row being added.
     */
    public function markAsPaid(Company $company, Request $request, ContractLoanSchedule $contractLoanSchedule)
    {
        abort_unless((int) $contractLoanSchedule->company_id === (int) $company->id, 404);

        if (! $contractLoanSchedule->canSettle()) {
            return redirect()
                ->back()
                ->with('warning', __('This contract schedule installment is not linked to an active leasing contract with a drawee bank.'));
        }
        if ($contractLoanSchedule->isChequePaid()) {
            return redirect()->back()->with('fail', __('This cheque is already marked as paid.'));
        }

        $request->validate([
            'actual_payment_date' => [
                'required',
                new DateMustBeLessThanOrEqualDate(null, now(), __('Payment Date Must Be Less Than Or Equal Today')),
                new DateMustBeGreaterThanOrEqualDate(null, $contractLoanSchedule->getDate(), __('Payment Date Must Be Greater Than Or Equal Cheque Due Date')),
            ],
        ]);

        $account = $contractLoanSchedule->getChequeAccount();
        if (! $account) {
            return redirect()->back()->with('fail', __('This cheque is not linked to a current account. Edit the installment and choose its account first.'));
        }

        $amount = $contractLoanSchedule->getChequeStatementAmount();
        if ($amount <= 0) {
            return redirect()->back()->with('fail', __('This installment has nothing left to pay.'));
        }

        $paymentDate = Carbon::make($request->get('actual_payment_date'))->format('Y-m-d');

        if ($this->balanceBeforeChequeAt($company, $contractLoanSchedule, $account->account_number, $paymentDate) < $amount) {
            return redirect()->back()->with('fail', __('Net Balance Less Than Paid Amount'))->withInput();
        }

        DB::transaction(function () use ($company, $contractLoanSchedule, $account, $amount, $paymentDate) {
            $settlement = $contractLoanSchedule->settlements()->create([
                'current_account_number' => $account->account_number,
                'amount' => $amount,
                'date' => $paymentDate,
                'is_cheque_payment' => 1,
                'company_id' => $company->id,
            ]);

            // Moves the cheque row from the due date to the payment date.
            $contractLoanSchedule->refresh()->update(['actual_payment_date' => $paymentDate]);

            $commentEn = $contractLoanSchedule->getChequeStatementComment('en');
            $commentAr = $contractLoanSchedule->getChequeStatementComment('ar');
            $settlement->handleLoanStatement($company->id, $contractLoanSchedule->getFinancialInstitutionId(), $account->account_number, $paymentDate, $amount, $commentEn, $commentAr);

            /**
             * * لو العقد ده اتدفعت منه فواتير موردين من خلال كاش فيرو (نوع الدفع
             * * Through Leasing) يبقي ليه كشف حساب سحب/سداد خاص بيه .. والقسط
             * * اللي اتسدد دلوقتي بيرجّع جزء ال principle بتاعه للمتاح فيه.
             */
            $settlement->handleLeasingContractRepayment($company->id, $commentEn, $commentAr);
        });

        return redirect()->route('view.uploading', getUploadingRouteParams(
            $company->id,
            'ContractLoanSchedule',
            (string) $contractLoanSchedule->getLeasingContractId()
        ))->with('success', __('Cheque Marked As Paid Successfully'));
    }

    /**
     * Returns a paid cheque to unpaid: removes the Mark As Paid settlement
     * (and the loan / contract ledger rows it posted) and moves the cheque
     * row back to its due date. Mirrors IsMoneyOut::revertPayableChequeToUnpaid().
     */
    public function unmarkAsPaid(Company $company, ContractLoanSchedule $contractLoanSchedule)
    {
        abort_unless((int) $contractLoanSchedule->company_id === (int) $company->id, 404);

        if (! $contractLoanSchedule->isChequePaid()) {
            return redirect()->back()->with('fail', __('Cheque is not paid'));
        }

        DB::transaction(function () use ($contractLoanSchedule) {
            $contractLoanSchedule->settlements()->where('is_cheque_payment', 1)->get()
                ->each(function (ContractLoanScheduleSettlement $settlement) {
                    $settlement->deleteAllRelations();
                    $settlement->delete();
                });

            $contractLoanSchedule->refresh()->update(['actual_payment_date' => null]);
        });

        return redirect()->route('view.contract.loan.schedule.settlements', [
            'company' => $company->id,
            'contractLoanSchedule' => $contractLoanSchedule->id,
        ])->with('success', __('Cheque Returned To Unpaid Successfully'));
    }

    /**
     * Deletes a settlement recorded the old (partial) way and reverses
     * everything it posted. The installment's cheque row grows back by the
     * same amount through ContractLoanSchedule's `remaining` hook.
     */
    public function deleteSettlement(Company $company, Request $request, ContractLoanScheduleSettlement $contractLoanScheduleSettlement)
    {
        $schedule = $contractLoanScheduleSettlement->contractLoanSchedule;

        if ($contractLoanScheduleSettlement->isChequePayment() || $schedule?->isChequePaid()) {
            return back()->with('fail', __('Unmark the cheque as paid first.'));
        }

        $contractLoanScheduleSettlement->deleteAllRelations();
        $contractLoanScheduleSettlement->delete();

        return back();
    }

    /**
     * The account balance on $date as it was before this cheque left it.
     * If the cheque row already sits on or before $date (paid on its due
     * date, or later) it has already been taken out of that balance, so
     * it is added back — same idea as markChequesAsPaid().
     */
    protected function balanceBeforeChequeAt(Company $company, ContractLoanSchedule $contractLoanSchedule, string $accountNumber, string $date): float
    {
        $accountType = AccountType::onlyCurrentAccount()->first();
        $balanceRequest = Request::create('/', 'GET', [
            'accountType' => $accountType?->id,
            'accountNumber' => $accountNumber,
            'financialInstitutionId' => $contractLoanSchedule->getFinancialInstitutionId(),
            'balanceDate' => $date,
        ]);
        $balance = (float) ((new MoneyReceivedController())->updateNetBalanceBasedOnAccountNumber($balanceRequest, $company)->getData()->balance ?? 0);

        $chequeRow = $contractLoanSchedule->chequeStatement()->first();
        if ($chequeRow && Carbon::make($chequeRow->date)->format('Y-m-d') <= $date) {
            $balance += (float) $chequeRow->credit;
        }

        return $balance;
    }
}
