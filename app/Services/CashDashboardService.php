<?php

namespace App\Services;

use App\Helpers\HDate;
use App\Models\AccountType;
use App\Models\Branch;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\ForeignExchangeRate;
use App\Models\MediumTermLoan;
use App\Models\LeasingContract;
use App\Support\CashDashboard\DepositCashDashboardHelper;
use App\Support\CashDashboard\LatestStatementQuery;
use App\Support\CashDashboard\OverdraftCashDashboardHelper;
use App\Support\ShareholderAccounts\AccountNumberLabel;
use App\Support\ShareholderAccounts\ShareholderAccountAccess;
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

        /**
         * * فلتر ملكية الحسابات : كل الحسابات / حسابات الشركة / حسابات الشركاء
         * * الافتراضي حسابات الشركة (القرار D2) و اللي ماعندوش صلاحية
         * * shareholder_account.view
         * * بيتثبت على حسابات الشركة مهما بعت في الريكوست (القرار D6)
         * * الملف : docs/shareholder-accounts.md
         */
        $canManageShareholderAccounts = ShareholderAccountAccess::canView();
        $accountOwnerFilter = ShareholderAccountAccess::filterFromRequest($request);

        /**
         * * التسهيلات دي (كل انواع الاوفر درافت) بنكيا ما بتتعملش لفرد
         * * فا هي دايما بتاعة الشركة .. ولما المستخدم يختار حسابات الشركاء بس
         * * ما ينفعش نفضل نعرضله ارقام تسهيلات الشركة كأنها بتاعة الشريك
         */
        $includeCompanyOnlyInstruments = ! $accountOwnerFilter->isShareholdersOnly();

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

        $hasFullySecuredOverdraftMap = $includeCompanyOnlyInstruments ? OverdraftCashDashboardHelper::currenciesWithRecords('fully_secured_overdrafts', $companyId, $selectedCurrencies) : [];
        $hasCleanOverdraftMap = $includeCompanyOnlyInstruments ? OverdraftCashDashboardHelper::currenciesWithRecords('clean_overdrafts', $companyId, $selectedCurrencies) : [];
        $hasOverdraftAgainstCommercialPaperMap = $includeCompanyOnlyInstruments ? OverdraftCashDashboardHelper::currenciesWithRecords('overdraft_against_commercial_papers', $companyId, $selectedCurrencies) : [];
        $hasOverdraftAgainstAssignmentOfContractMap = $includeCompanyOnlyInstruments ? OverdraftCashDashboardHelper::currenciesWithRecords('overdraft_against_assignment_of_contracts', $companyId, $selectedCurrencies) : [];

        /**
         * * القرار D1 : الفلتر بالملكية فقط ومتساوي على كل الأنواع
         * * فا قرض الشريك بيتفلتر زي الحساب الجاري والـ TD/CD بالظبط
         */
        $mediumTermLoansByCurrency = MediumTermLoan::query()
            ->where('company_id', $companyId)
            ->whereIn('currency', $selectedCurrencies)
            ->ownedAccordingTo($accountOwnerFilter)
            ->with('loanSchedules')
            ->get()
            ->groupBy('currency');

        /**
         * * التأجير التمويلي زي الاوفر درافت والخزن : ما بيتعملش لفرد
         * * فا دايما بتاع الشركة وما يظهرش في عرض حسابات الشركاء
         */
        $leasingContractsByCurrency = $includeCompanyOnlyInstruments
            ? LeasingContract::query()
                ->where('company_id', $companyId)
                ->whereIn('currency', $selectedCurrencies)
                ->with(['contractLoanSchedules', 'leasingCompany'])
                ->get()
                ->groupBy('currency')
            : collect();

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

        $fullySecuredOverdraftIdsByCurrency = $includeCompanyOnlyInstruments ? OverdraftCashDashboardHelper::overdraftIdsByCurrency('fully_secured_overdrafts', $companyId, $selectedCurrencies, $date) : [];
        $cleanOverdraftIdsByCurrency = $includeCompanyOnlyInstruments ? OverdraftCashDashboardHelper::overdraftIdsByCurrency('clean_overdrafts', $companyId, $selectedCurrencies, $date) : [];
        $overdraftAgainstCommercialPaperIdsByCurrency = $includeCompanyOnlyInstruments ? OverdraftCashDashboardHelper::overdraftIdsByCurrency('overdraft_against_commercial_papers', $companyId, $selectedCurrencies, $date) : [];
        $overdraftAgainstAssignmentOfContractIdsByCurrency = $includeCompanyOnlyInstruments ? OverdraftCashDashboardHelper::overdraftIdsByCurrency('overdraft_against_assignment_of_contracts', $companyId, $selectedCurrencies, $date) : [];

        /**
         * * الخزن دايما بتاعة الشركة .. مفيش خزنة شخصية لشريك (القسم 1 في الملف)
         * * فا لما الفلتر يبقى "حسابات الشركاء" الخزن ما تدخلش في الحساب
         */
        $cashInSafeByCurrency = $accountOwnerFilter->isShareholdersOnly() ? [] : LatestStatementQuery::latestCashInSafeByBranch(
            $companyId,
            $date,
            $branchIds,
            $selectedCurrencies
        );
        $currentAccountsByCurrency = LatestStatementQuery::latestCurrentAccountBalances(
            $companyId,
            $date,
            $selectedFinancialInstitutionBankIds,
            $selectedCurrencies,
            $accountOwnerFilter
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
                // Only list this branch under this currency if it genuinely
                // has a recorded Cash In Safe statement in that currency —
                // previously every branch was listed under every currency
                // (at a hardcoded 0 when no record existed), which made the
                // "Cash & Banks" detail panel show every safe/branch on
                // every currency tab instead of only the ones that actually
                // hold that currency. The per-currency SUM below is
                // unaffected either way, since a skipped row would only
                // ever have contributed 0.
                if (! $statement) {
                    continue;
                }
                $amount = (float) $statement->end_balance;
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

            $fullySecuredInterest = OverdraftCashDashboardHelper::interestByOverdraftId(
                'fully_secured_overdraft_bank_statements',
                'fully_secured_overdrafts',
                'fully_secured_overdraft_id',
                $companyId,
                $currencyName,
                $date,
                $year,
                $fullySecuredOverdraftIds
            );
            $cleanInterest = OverdraftCashDashboardHelper::interestByOverdraftId(
                'clean_overdraft_bank_statements',
                'clean_overdrafts',
                'clean_overdraft_id',
                $companyId,
                $currencyName,
                $date,
                $year,
                $cleanOverdraftIds
            );
            $commercialInterest = OverdraftCashDashboardHelper::interestByOverdraftId(
                'overdraft_against_commercial_paper_bank_statements',
                'overdraft_against_commercial_papers',
                'overdraft_against_commercial_paper_id',
                $companyId,
                $currencyName,
                $date,
                $year,
                $overdraftAgainstCommercialPaperIds
            );
            $assignmentInterest = OverdraftCashDashboardHelper::interestByOverdraftId(
                'overdraft_against_assignment_of_contract_bank_statements',
                'overdraft_against_assignment_of_contracts',
                'overdraft_against_assignment_of_contract_id',
                $companyId,
                $currencyName,
                $date,
                $year,
                $overdraftAgainstAssignmentOfContractIds
            );

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
                    $unusedRoom,
                    $cleanInterest
                );
                OverdraftCashDashboardHelper::applyFinancialInstitutionRoomData(
                    $totalRoomForEachFullySecuredOverdraftId,
                    $currencyName,
                    $fullySecuredOverdraftIds,
                    $fullySecuredMeta,
                    $fullySecuredLatest,
                    $financialInstitutionBankId,
                    $institutionName,
                    $unusedRoom,
                    $fullySecuredInterest
                );
                OverdraftCashDashboardHelper::applyFinancialInstitutionRoomData(
                    $totalRoomForEachOverdraftAgainstCommercialPaperId,
                    $currencyName,
                    $overdraftAgainstCommercialPaperIds,
                    $commercialMeta,
                    $commercialLatest,
                    $financialInstitutionBankId,
                    $institutionName,
                    $unusedRoom,
                    $commercialInterest
                );
                OverdraftCashDashboardHelper::applyFinancialInstitutionRoomData(
                    $totalRoomForEachOverdraftAgainstAssignmentOfContractId,
                    $currencyName,
                    $overdraftAgainstAssignmentOfContractIds,
                    $assignmentMeta,
                    $assignmentLatest,
                    $financialInstitutionBankId,
                    $institutionName,
                    $unusedRoom,
                    $assignmentInterest
                );

                foreach ($accountsForCurrency as $accountRow) {
                    if ((int) $accountRow->financial_institution_id !== (int) $financialInstitutionBankId) {
                        continue;
                    }

                    $amount = (float) ($accountRow->end_balance ?? 0);
                    $details[$currencyName]['current_account'][] = [
                        'amount' => $amount,
                        'account_number' => AccountNumberLabel::format($accountRow->account_number, $accountRow->shareholder_name ?? null),
                        'financial_institution_name' => $institutionName,
                    ];
                    $currentAccountInBanks += $amount;
                }
            }

            // "Cash & Banks" detail panel — combines Current Account (bank)
            // rows and Cash In Safe (branch) rows into one list, sorted by
            // largest balance first (per explicit product decision), with
            // each row's equivalent in the company's main functional
            // currency exposed alongside it. $exchangeRates[$currencyName]
            // is already the same single FX rate used everywhere else on
            // this dashboard for this currency (computed above, before this
            // per-currency loop), so every row here shares one rate — there
            // is no per-row rate, only a per-currency one.
            $cashAndBankRows = array_merge(
                array_map(fn (array $row) => $row + ['source' => __('Current Account')], $details[$currencyName]['current_account'] ?? []),
                array_map(fn (array $row) => [
                    'amount' => $row['amount'],
                    'account_number' => '-',
                    'financial_institution_name' => $row['branch_name'],
                    'source' => __('Cash In Safe'),
                ], $details[$currencyName]['cash_in_safe'] ?? [])
            );
            usort($cashAndBankRows, fn (array $a, array $b) => $b['amount'] <=> $a['amount']);
            $cashAndBankExchangeRate = $exchangeRates[$currencyName] ?? 1.0;
            $details[$currencyName]['cash_and_banks'] = array_map(fn (array $row) => $row + [
                'exchange_rate' => $cashAndBankExchangeRate,
                'amount_in_main_currency' => $row['amount'] * $cashAndBankExchangeRate,
            ], $cashAndBankRows);

            $certificateRows = DepositCashDashboardHelper::certificatesForCurrency(
                $companyId,
                $currencyName,
                $selectedFinancialInstitutionBankIds,
                $cdAccountTypeId,
                $bankNameResolver,
                $accountOwnerFilter
            );
            foreach ($certificateRows as $certificateRow) {
                $details[$currencyName]['certificate_of_deposits'][] = (array) $certificateRow;
            }

            $timeDepositRows = DepositCashDashboardHelper::timeDepositsForCurrency(
                $companyId,
                $currencyName,
                $selectedFinancialInstitutionBankIds,
                $tdAccountTypeId,
                $bankNameResolver,
                $accountOwnerFilter
            );
            foreach ($timeDepositRows as $timeDepositRow) {
                $details[$currencyName]['time_of_deposits'][] = (array) $timeDepositRow;
            }

            $cleanOverdraftCardData[$currencyName] = ! $includeCompanyOnlyInstruments ? [] : OverdraftCashDashboardHelper::yearCardData(
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
            $fullySecuredOverdraftCardData[$currencyName] = ! $includeCompanyOnlyInstruments ? [] : OverdraftCashDashboardHelper::yearCardData(
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
            $overdraftAgainstCommercialPaperCardData[$currencyName] = ! $includeCompanyOnlyInstruments ? [] : OverdraftCashDashboardHelper::yearCardData(
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
            $overdraftAgainstAssignmentOfContractCardData[$currencyName] = ! $includeCompanyOnlyInstruments ? [] : OverdraftCashDashboardHelper::yearCardData(
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
        $leasingContractsArr = [];
        $hasFullySecuredOverdraft = [];
        $hasCleanOverdraft = [];
        $hasOverdraftAgainstCommercialPaper = [];
        $hasOverdraftAgainstAssignmentOfContract = [];

        foreach ($selectedCurrencies as $currencyName) {
            $mediumTermLoansArr[$currencyName] = $mediumTermLoansByCurrency->get($currencyName, collect());
            $leasingContractsArr[$currencyName] = $leasingContractsByCurrency->get($currencyName, collect());
            $hasFullySecuredOverdraft[$currencyName] = isset($hasFullySecuredOverdraftMap[$currencyName]);
            $hasCleanOverdraft[$currencyName] = isset($hasCleanOverdraftMap[$currencyName]);
            $hasOverdraftAgainstCommercialPaper[$currencyName] = isset($hasOverdraftAgainstCommercialPaperMap[$currencyName]);
            $hasOverdraftAgainstAssignmentOfContract[$currencyName] = isset($hasOverdraftAgainstAssignmentOfContractMap[$currencyName]);
        }

        return array_merge(compact(
            'mediumTermLoansArr',
            'leasingContractsArr',
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
            // Owner filter state + options for the Vue controls.
            'canManageShareholderAccounts' => $canManageShareholderAccounts,
            'accountOwner' => $accountOwnerFilter->owner,
            'accountOwnerShareholderId' => $accountOwnerFilter->shareholderPartnerId,
            'shareholders' => $canManageShareholderAccounts
                ? ShareholderAccountAccess::shareholdersForSelect($companyId)
                : [],
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
