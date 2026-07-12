<?php

namespace App\Services;

use App\Helpers\HDate;
use App\Models\AccountType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\ForeignExchangeRate;
use App\Models\MediumTermLoan;
use App\Support\CashDashboard\DepositCashDashboardHelper;
use App\Support\CashDashboard\LatestStatementQuery;
use App\Support\CashDashboard\OverdraftCashDashboardHelper;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CashDashboardService
{
    public function build(Company $company, Request $request): array
    {
        $companyId = $company->id;
        $currentDate = now()->format('Y-m-d');
        $dateInput = $request->get('date');
        $date = $dateInput ? HDate::formatDateFromDatePicker($dateInput) : $currentDate;
        $date = Carbon::make($date)->format('Y-m-d');
        $year = (int) explode('-', $date)[0];

        $allCurrencies = getCurrenciesForSuppliersAndCustomers($companyId);
        $selectedCurrencies = array_values(array_filter(
            $request->get('currencies', $allCurrencies) ?: [],
            fn ($currency) => (bool) $currency
        ));

        $mainFunctionalCurrency = $company->getMainFunctionalCurrency();
        $foreignExchangeRates = ForeignExchangeRate::where('company_id', $companyId)->get();

        $financialInstitutionBanks = FinancialInstitution::onlyForCompany($companyId)
            ->onlyBanks()
            ->with('bank')
            ->get();
        $banksById = $financialInstitutionBanks->keyBy('id');
        $financialInstitutionBankIds = $financialInstitutionBanks->pluck('id')->all();
        $selectedFinancialInstitutionBankIds = $request->has('financial_institution_ids')
            ? array_map('intval', (array) $request->get('financial_institution_ids'))
            : $financialInstitutionBankIds;

        $branches = Branch::getBranchesForCurrentCompany($companyId);
        $branchIds = array_map('intval', array_keys($branches));

        $cdAccountTypeId = (int) AccountType::onlyCdAccounts()->value('id');
        $tdAccountTypeId = (int) AccountType::onlyTdAccounts()->value('id');

        $allFullySecuredOverdraftBanks = FinancialInstitution::onlyForCompany($companyId)->onlyBanks()->onlyHasFullySecuredOverdrafts()->get();
        $allCleanOverdraftBanks = FinancialInstitution::onlyForCompany($companyId)->onlyBanks()->onlyHasCleanOverdrafts()->get();
        $allOverdraftAgainstCommercialPaperBanks = FinancialInstitution::onlyForCompany($companyId)->onlyBanks()->onlyHasOverdraftAgainstCommercialPapers()->get();
        $allOverdraftAgainstAssignmentOfContractBanks = FinancialInstitution::onlyForCompany($companyId)->onlyBanks()->onlyHasOverdraftAgainstAssignmentOfContracts()->get();

        $fullySecuredOverdraftAccountTypes = AccountType::onlyFullySecuredOverdraft()->get();
        $cleanOverdraftAccountTypes = AccountType::onlyCleanOverdraft()->get();
        $overdraftAgainstCommercialPaperAccountTypes = AccountType::onlyOverdraftAgainstCommercialPaper()->get();
        $overdraftAgainstAssignmentOfContractAccountTypes = AccountType::onlyOverdraftAgainstAssignmentOfContract()->get();

        $hasFullySecuredOverdraftMap = OverdraftCashDashboardHelper::currenciesWithRecords('fully_secured_overdrafts', $companyId, $selectedCurrencies);
        $hasCleanOverdraftMap = OverdraftCashDashboardHelper::currenciesWithRecords('clean_overdrafts', $companyId, $selectedCurrencies);
        $hasOverdraftAgainstCommercialPaperMap = OverdraftCashDashboardHelper::currenciesWithRecords('overdraft_against_commercial_papers', $companyId, $selectedCurrencies);
        $hasOverdraftAgainstAssignmentOfContractMap = OverdraftCashDashboardHelper::currenciesWithRecords('overdraft_against_assignment_of_contracts', $companyId, $selectedCurrencies);

        $mediumTermLoansByCurrency = MediumTermLoan::query()
            ->where('company_id', $companyId)
            ->whereIn('currency', $selectedCurrencies)
            ->with('loanSchedules')
            ->get()
            ->groupBy('currency');

        $exchangeRates = [];
        foreach ($selectedCurrencies as $currencyName) {
            if ($mainFunctionalCurrency !== $currencyName) {
                $exchangeRates[$currencyName] = ForeignExchangeRate::getExchangeRateForCurrencyAndClosestDate(
                    $currencyName,
                    $mainFunctionalCurrency,
                    $date,
                    $companyId,
                    $foreignExchangeRates
                );
            }
        }

        $fullySecuredOverdraftIdsByCurrency = OverdraftCashDashboardHelper::overdraftIdsByCurrency('fully_secured_overdrafts', $companyId, $selectedCurrencies, $date);
        $cleanOverdraftIdsByCurrency = OverdraftCashDashboardHelper::overdraftIdsByCurrency('clean_overdrafts', $companyId, $selectedCurrencies, $date);
        $overdraftAgainstCommercialPaperIdsByCurrency = OverdraftCashDashboardHelper::overdraftIdsByCurrency('overdraft_against_commercial_papers', $companyId, $selectedCurrencies, $date);
        $overdraftAgainstAssignmentOfContractIdsByCurrency = OverdraftCashDashboardHelper::overdraftIdsByCurrency('overdraft_against_assignment_of_contracts', $companyId, $selectedCurrencies, $date);

        $cashInSafeByCurrency = LatestStatementQuery::latestCashInSafeByBranch(
            $companyId,
            $date,
            $branchIds,
            $selectedCurrencies
        );
        $currentAccountsByCurrency = LatestStatementQuery::latestCurrentAccountBalances(
            $companyId,
            $date,
            $selectedFinancialInstitutionBankIds,
            $selectedCurrencies
        );

        $fullySecuredOverdraftCardData = [];
        $cleanOverdraftCardData = [];
        $overdraftAgainstCommercialPaperCardData = [];
        $overdraftAgainstAssignmentOfContractCardData = [];
        $totalRoomForEachFullySecuredOverdraftId = [];
        $totalRoomForEachCleanOverdraftId = [];
        $totalRoomForEachOverdraftAgainstCommercialPaperId = [];
        $totalRoomForEachOverdraftAgainstAssignmentOfContractId = [];

        $details = [];
        $reports = [];
        $totalCard = [];

        $bankNameResolver = function (int $bankId) use ($banksById): string {
            /** @var FinancialInstitution|null $institution */
            $institution = $banksById->get($bankId);

            return $institution ? $institution->getName() : __('N/A');
        };

        foreach ($selectedCurrencies as $currencyName) {
            $cashInSafeStatementAmountForCurrency = 0.0;
            $currentAccountInBanks = 0.0;

            foreach ($branches as $branchId => $branchName) {
                $statement = $cashInSafeByCurrency[$currencyName][$branchId] ?? null;
                $amount = $statement ? (float) $statement->end_balance : 0.0;
                $details[$currencyName]['cash_in_safe'][] = [
                    'amount' => $amount,
                    'branch_name' => $branchName,
                ];
                $cashInSafeStatementAmountForCurrency += $amount;
            }

            $fullySecuredOverdraftIds = $fullySecuredOverdraftIdsByCurrency[$currencyName] ?? [];
            $cleanOverdraftIds = $cleanOverdraftIdsByCurrency[$currencyName] ?? [];
            $overdraftAgainstCommercialPaperIds = $overdraftAgainstCommercialPaperIdsByCurrency[$currencyName] ?? [];
            $overdraftAgainstAssignmentOfContractIds = $overdraftAgainstAssignmentOfContractIdsByCurrency[$currencyName] ?? [];

            $fullySecuredLatest = OverdraftCashDashboardHelper::latestStatementsForOverdrafts(
                'fully_secured_overdraft_bank_statements',
                'fully_secured_overdrafts',
                'fully_secured_overdraft_id',
                $companyId,
                $currencyName,
                $date,
                $fullySecuredOverdraftIds
            );
            $cleanLatest = OverdraftCashDashboardHelper::latestStatementsForOverdrafts(
                'clean_overdraft_bank_statements',
                'clean_overdrafts',
                'clean_overdraft_id',
                $companyId,
                $currencyName,
                $date,
                $cleanOverdraftIds
            );
            $commercialLatest = OverdraftCashDashboardHelper::latestStatementsForOverdrafts(
                'overdraft_against_commercial_paper_bank_statements',
                'overdraft_against_commercial_papers',
                'overdraft_against_commercial_paper_id',
                $companyId,
                $currencyName,
                $date,
                $overdraftAgainstCommercialPaperIds
            );
            $assignmentLatest = OverdraftCashDashboardHelper::latestStatementsForOverdrafts(
                'overdraft_against_assignment_of_contract_bank_statements',
                'overdraft_against_assignment_of_contracts',
                'overdraft_against_assignment_of_contract_id',
                $companyId,
                $currencyName,
                $date,
                $overdraftAgainstAssignmentOfContractIds
            );

            $fullySecuredMeta = OverdraftCashDashboardHelper::overdraftMetaById('fully_secured_overdrafts', $fullySecuredOverdraftIds);
            $cleanMeta = OverdraftCashDashboardHelper::overdraftMetaById('clean_overdrafts', $cleanOverdraftIds);
            $commercialMeta = OverdraftCashDashboardHelper::overdraftMetaById('overdraft_against_commercial_papers', $overdraftAgainstCommercialPaperIds);
            $assignmentMeta = OverdraftCashDashboardHelper::overdraftMetaById('overdraft_against_assignment_of_contracts', $overdraftAgainstAssignmentOfContractIds);

            $accountsForCurrency = collect($currentAccountsByCurrency[$currencyName] ?? []);

            foreach ($selectedFinancialInstitutionBankIds as $financialInstitutionBankId) {
                $institution = $banksById->get($financialInstitutionBankId);
                if (! $institution) {
                    continue;
                }

                $institutionName = $institution->getName();
                $unusedRoom = 0.0;

                OverdraftCashDashboardHelper::applyFinancialInstitutionRoomData(
                    $totalRoomForEachCleanOverdraftId,
                    $currencyName,
                    $cleanOverdraftIds,
                    $cleanMeta,
                    $cleanLatest,
                    $financialInstitutionBankId,
                    $institutionName,
                    $unusedRoom
                );
                OverdraftCashDashboardHelper::applyFinancialInstitutionRoomData(
                    $totalRoomForEachFullySecuredOverdraftId,
                    $currencyName,
                    $fullySecuredOverdraftIds,
                    $fullySecuredMeta,
                    $fullySecuredLatest,
                    $financialInstitutionBankId,
                    $institutionName,
                    $unusedRoom
                );
                OverdraftCashDashboardHelper::applyFinancialInstitutionRoomData(
                    $totalRoomForEachOverdraftAgainstCommercialPaperId,
                    $currencyName,
                    $overdraftAgainstCommercialPaperIds,
                    $commercialMeta,
                    $commercialLatest,
                    $financialInstitutionBankId,
                    $institutionName,
                    $unusedRoom
                );
                OverdraftCashDashboardHelper::applyFinancialInstitutionRoomData(
                    $totalRoomForEachOverdraftAgainstAssignmentOfContractId,
                    $currencyName,
                    $overdraftAgainstAssignmentOfContractIds,
                    $assignmentMeta,
                    $assignmentLatest,
                    $financialInstitutionBankId,
                    $institutionName,
                    $unusedRoom
                );

                foreach ($accountsForCurrency as $accountRow) {
                    if ((int) $accountRow->financial_institution_id !== (int) $financialInstitutionBankId) {
                        continue;
                    }

                    $amount = (float) ($accountRow->end_balance ?? 0);
                    $details[$currencyName]['current_account'][] = [
                        'amount' => $amount,
                        'account_number' => $accountRow->account_number,
                        'financial_institution_name' => $institutionName,
                    ];
                    $currentAccountInBanks += $amount;
                }
            }

            $certificateRows = DepositCashDashboardHelper::certificatesForCurrency(
                $companyId,
                $currencyName,
                $selectedFinancialInstitutionBankIds,
                $cdAccountTypeId,
                $bankNameResolver
            );
            foreach ($certificateRows as $certificateRow) {
                $details[$currencyName]['certificate_of_deposits'][] = (array) $certificateRow;
            }

            $timeDepositRows = DepositCashDashboardHelper::timeDepositsForCurrency(
                $companyId,
                $currencyName,
                $selectedFinancialInstitutionBankIds,
                $tdAccountTypeId,
                $bankNameResolver
            );
            foreach ($timeDepositRows as $timeDepositRow) {
                $details[$currencyName]['time_of_deposits'][] = (array) $timeDepositRow;
            }

            $cleanOverdraftCardData[$currencyName] = OverdraftCashDashboardHelper::yearCardData(
                'clean_overdraft_bank_statements',
                'clean_overdrafts',
                'clean_overdraft_id',
                DB::table('clean_overdrafts')->where('currency', $currencyName)->where('company_id', $companyId)->where('contract_start_date', '<=', $date),
                $companyId,
                $currencyName,
                $date,
                $year,
                $cleanOverdraftIds,
                $cleanLatest
            );
            $fullySecuredOverdraftCardData[$currencyName] = OverdraftCashDashboardHelper::yearCardData(
                'fully_secured_overdraft_bank_statements',
                'fully_secured_overdrafts',
                'fully_secured_overdraft_id',
                DB::table('fully_secured_overdrafts')->where('currency', $currencyName)->where('company_id', $companyId)->where('contract_start_date', '<=', $date),
                $companyId,
                $currencyName,
                $date,
                $year,
                $fullySecuredOverdraftIds,
                $fullySecuredLatest
            );
            $overdraftAgainstCommercialPaperCardData[$currencyName] = OverdraftCashDashboardHelper::yearCardData(
                'overdraft_against_commercial_paper_bank_statements',
                'overdraft_against_commercial_papers',
                'overdraft_against_commercial_paper_id',
                DB::table('overdraft_against_commercial_papers')->where('currency', $currencyName)->where('company_id', $companyId)->where('contract_start_date', '<=', $date),
                $companyId,
                $currencyName,
                $date,
                $year,
                $overdraftAgainstCommercialPaperIds,
                $commercialLatest
            );
            $overdraftAgainstAssignmentOfContractCardData[$currencyName] = OverdraftCashDashboardHelper::yearCardData(
                'overdraft_against_assignment_of_contract_bank_statements',
                'overdraft_against_assignment_of_contracts',
                'overdraft_against_assignment_of_contract_id',
                DB::table('overdraft_against_assignment_of_contracts')->where('currency', $currencyName)->where('company_id', $companyId)->where('contract_start_date', '<=', $date),
                $companyId,
                $currencyName,
                $date,
                $year,
                $overdraftAgainstAssignmentOfContractIds,
                $assignmentLatest
            );

            $reports['cash_and_banks'][$currencyName] = $cashInSafeStatementAmountForCurrency + $currentAccountInBanks;
            $reports['certificate_of_deposits'][$currencyName] = (float) $certificateRows->sum('amount');
            $reports['time_deposits'][$currencyName] = (float) $timeDepositRows->sum('amount');

            $currentTotal = $reports['cash_and_banks'][$currencyName]
                + $reports['time_deposits'][$currencyName]
                + $reports['certificate_of_deposits'][$currencyName];
            $reports['total'][$currencyName] = ($reports['total'][$currencyName] ?? 0) + $currentTotal;

            $totalCard[$currencyName] = $this->sumForTotalCard($totalCard[$currencyName] ?? [], [
                $cleanOverdraftCardData[$currencyName] ?? 0,
                $fullySecuredOverdraftCardData[$currencyName] ?? 0,
                $overdraftAgainstCommercialPaperCardData[$currencyName] ?? 0,
                $overdraftAgainstAssignmentOfContractCardData[$currencyName] ?? 0,
            ]);
        }

        $mediumTermLoansArr = [];
        $hasFullySecuredOverdraft = [];
        $hasCleanOverdraft = [];
        $hasOverdraftAgainstCommercialPaper = [];
        $hasOverdraftAgainstAssignmentOfContract = [];

        foreach ($selectedCurrencies as $currencyName) {
            $mediumTermLoansArr[$currencyName] = $mediumTermLoansByCurrency->get($currencyName, collect());
            $hasFullySecuredOverdraft[$currencyName] = isset($hasFullySecuredOverdraftMap[$currencyName]);
            $hasCleanOverdraft[$currencyName] = isset($hasCleanOverdraftMap[$currencyName]);
            $hasOverdraftAgainstCommercialPaper[$currencyName] = isset($hasOverdraftAgainstCommercialPaperMap[$currencyName]);
            $hasOverdraftAgainstAssignmentOfContract[$currencyName] = isset($hasOverdraftAgainstAssignmentOfContractMap[$currencyName]);
        }

        return array_merge(compact(
            'mediumTermLoansArr',
            'exchangeRates',
            'mainFunctionalCurrency',
            'company',
            'financialInstitutionBanks',
            'reports',
            'selectedCurrencies',
            'allCurrencies',
            'selectedFinancialInstitutionBankIds',
            'totalCard',
            'details',
            'date',
            'cleanOverdraftCardData',
            'totalRoomForEachCleanOverdraftId',
            'cleanOverdraftAccountTypes',
            'allCleanOverdraftBanks',
            'hasCleanOverdraft',
            'fullySecuredOverdraftCardData',
            'totalRoomForEachFullySecuredOverdraftId',
            'fullySecuredOverdraftAccountTypes',
            'allFullySecuredOverdraftBanks',
            'hasFullySecuredOverdraft',
            'overdraftAgainstCommercialPaperCardData',
            'totalRoomForEachOverdraftAgainstCommercialPaperId',
            'overdraftAgainstCommercialPaperAccountTypes',
            'allOverdraftAgainstCommercialPaperBanks',
            'hasOverdraftAgainstCommercialPaper',
            'overdraftAgainstAssignmentOfContractCardData',
            'totalRoomForEachOverdraftAgainstAssignmentOfContractId',
            'overdraftAgainstAssignmentOfContractAccountTypes',
            'allOverdraftAgainstAssignmentOfContractBanks',
            'hasOverdraftAgainstAssignmentOfContract'
        ), [
            'selectedFinancialInstitutionsIds' => $selectedFinancialInstitutionBankIds,
        ]);
    }

    private function sumForTotalCard(array $oldArr, array $newItems): array
    {
        foreach ($newItems as $oldItems) {
            foreach ($oldItems as $key => $value) {
                $oldArr[$key] = isset($oldArr[$key]) ? $oldArr[$key] + $value : $value;
            }
        }

        return $oldArr;
    }
}
