<?php

namespace App\Http\Controllers;

use App\Models\AccountType;
use App\Models\Company;
use App\Models\ContractLoanSchedule;
use App\Models\ContractLoanScheduleSettlement;
use App\Models\FinancialInstitutionAccount;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ContractLoanScheduleController extends Controller
{
    public function viewSettlement(Company $company, ContractLoanSchedule $contractLoanSchedule)
    {
        $contractLoanSchedule->load('leasingContract.contractLoanSchedules', 'draweeBank');

        if (! $contractLoanSchedule->canSettle()) {
            return redirect()
                ->back()
                ->with('warning', __('This contract schedule installment is not linked to an active leasing contract with a drawee bank.'));
        }

        return view('admin.loan-schedule-settlements.index', $this->getCommonSettlementVars($company, $contractLoanSchedule));
    }

    public function storeSettlement(Company $company, Request $request, ContractLoanSchedule $contractLoanSchedule)
    {
        if (! $contractLoanSchedule->canSettle()) {
            return redirect()
                ->back()
                ->with('warning', __('This contract schedule installment is not linked to an active leasing contract with a drawee bank.'));
        }

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

        return back();
    }

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

    public function editSettlement(Company $company, Request $request, ContractLoanScheduleSettlement $contractLoanScheduleSettlement)
    {
        $contractLoanSchedule = $contractLoanScheduleSettlement->contractLoanSchedule;

        if (! $contractLoanSchedule || ! $contractLoanSchedule->canSettle()) {
            return redirect()
                ->back()
                ->with('warning', __('This contract schedule installment is not linked to an active leasing contract with a drawee bank.'));
        }

        return view('admin.loan-schedule-settlements.index', $this->getCommonSettlementVars($company, $contractLoanSchedule, $contractLoanScheduleSettlement));
    }

    public function deleteSettlement(Company $company, Request $request, ContractLoanScheduleSettlement $contractLoanScheduleSettlement)
    {
        $contractLoanScheduleSettlement->deleteAllRelations();
        $contractLoanScheduleSettlement->delete();

        return back();
    }

    protected function getCommonSettlementVars(
        Company $company,
        ContractLoanSchedule $contractLoanSchedule,
        ?ContractLoanScheduleSettlement $contractLoanScheduleSettlement = null
    ): array {
        $currentAccountType = AccountType::onlyCurrentAccount()->first();
        $currentAccounts = FinancialInstitutionAccount::getAllAccountNumberForCurrency(
            $company->id,
            $contractLoanSchedule->getCurrency(),
            $contractLoanSchedule->getFinancialInstitutionId()
        );

        return [
            'loanSchedule' => $contractLoanSchedule,
            'company' => $company,
            'settlements' => $contractLoanSchedule->settlements,
            'currentAccounts' => $currentAccounts,
            'model' => $contractLoanScheduleSettlement,
            'currentAccountTypeId' => $currentAccountType?->id,
            'financialInstitutionId' => $contractLoanSchedule->getFinancialInstitutionId(),
            'isContractLoanSchedule' => true,
            'settlementRoutes' => [
                'store' => route('store.contract.loan.schedule.settlements', ['company' => $company->id, 'contractLoanSchedule' => $contractLoanSchedule->id]),
                'update' => isset($contractLoanScheduleSettlement)
                    ? route('update.contract.loan.schedule.settlements', ['company' => $company->id, 'contractLoanScheduleSettlement' => $contractLoanScheduleSettlement->id])
                    : null,
                'edit' => 'edit.contract.loan.schedule.settlements',
                'delete' => 'delete.contract.loan.schedule.settlements',
                'back' => route('view.uploading', [
                    'company' => $company->id,
                    'loanId' => $contractLoanSchedule->getLeasingContractId(),
                    'model' => 'ContractLoanSchedule',
                ]),
            ],
            'settlementModelType' => 'ContractLoanScheduleSettlement',
            'settlementRouteParameter' => 'contractLoanScheduleSettlement',
        ];
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
