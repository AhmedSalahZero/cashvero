<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\FactoringContract;
use App\Models\FinancialInstitutionAccount;
use App\Models\LeasingContract;
use App\Models\LetterOfCreditFacility;
use App\Models\LetterOfGuaranteeFacility;
use App\Models\MediumTermLoan;
use App\Models\CleanOverdraft;
use App\Models\FullySecuredOverdraft;
use App\Models\OverdraftAgainstAssignmentOfContract;
use App\Models\OverdraftAgainstCommercialPaper;
use App\Models\LetterOfCreditStatement;
use App\Models\LetterOfGuaranteeIssuance;
use App\Models\LetterOfGuaranteeStatement;
use App\Support\CashDashboard\LatestStatementQuery;
use Inertia\Inertia;

/**
 * FinancialInstitutionFacilitiesController
 * ------------------------------------------------------------------
 * NEW (2026-07-25, project-owner requested) — 5 read-only, company-wide
 * roll-up index pages living in the "Financial Institutions & Cash
 * Account" sidebar section, right after "Financial Institutions"
 * itself. Each one flattens data that otherwise only exists spread
 * across many individual bank/company detail pages into a single
 * table, so the user doesn't have to click into every bank one at a
 * time to see, e.g., every overdraft facility across all banks.
 *
 * Purely presentational — every query here is read-only (no create/
 * update/delete), so there is zero calculation or Odoo-sync risk:
 * nothing here writes to any table, fires any model hook, or touches
 * any DB trigger.
 *
 * ⚠️ Two real data-shape notes worth flagging explicitly rather than
 * silently working around:
 *   1. `FinancialInstitutionAccount` (Bank Accounts, page 1) has no
 *      "account type" column of its own — this page only ever lists
 *      current accounts, so "Account Type" is shown as the fixed
 *      label "Current Account" for every row, matching the
 *      AccountType::CURRENT_ACCOUNT concept already used elsewhere in
 *      this codebase as a display label, not a real foreign key here.
 *   2. `FactoringContract` (page 5) has NO name/title field at all in
 *      the schema — confirmed against both the model and its existing
 *      controller, which already identifies each contract by its
 *      Recourse Type label for the same reason. "Contract Name" for
 *      this page reuses that same label (e.g. "With Recourse") since
 *      there is no other identifying text to show. Flagging this
 *      because it's a real data gap, not a guess dressed up as a
 *      field.
 */
class FinancialInstitutionFacilitiesController
{
    /**
     * 1) Bank Accounts — every FinancialInstitutionAccount (current
     * account) across every bank for this company.
     */
    public function bankAccounts(Company $company)
    {
        // Shareholder-owned accounts are hidden from anyone without
        // shareholder_account.view — docs/shareholder-accounts.md (D6).
        $canViewShareholderAccounts = \App\Support\ShareholderAccounts\ShareholderAccountAccess::canView();

        $accounts = FinancialInstitutionAccount::with(['financialInstitution.bank', 'shareholderPartner'])
            ->where('company_id', $company->id)
            ->when(! $canViewShareholderAccounts, fn ($query) => $query->onlyCompanyOwned())
            ->get();

        $endBalances = FinancialInstitutionAccount::endBalancesAt($company->id, $accounts->pluck('id')->all(), now()->format('Y-m-d'));

        $rows = $accounts->map(fn ($account) => [
            'id' => $account->id,
            'bank_name' => $account->financialInstitution?->getName() ?? __('N/A'),
            'account_type' => __('Current Account'),
            'account_number' => $account->account_number,
            'owner_name' => $account->getShareholderName() ?? __('Company'),
            // In the account's own currency.
            'end_balance' => $endBalances[$account->id] ?? 0.0,
            'currency' => $account->currency,
        ])->sortBy('bank_name')->values();

        return Inertia::render('FinancialInstitutionFacilities/BankAccounts', [
            'company' => ['id' => $company->id, 'name' => $company->getName()],
            'rows' => $rows,
            'canViewShareholderAccounts' => $canViewShareholderAccounts,
        ]);
    }

    /**
     * 2) ODA & MTL Facilities — every overdraft (all 4 types) and
     * every Medium Term Loan across every bank for this company.
     */
    public function odaAndMtlFacilities(Company $company)
    {
        $rows = collect();

        $today = now()->format('Y-m-d');

        /**
         * Outstanding and room come from each facility's own statement, as
         * of today — the same latest-row lookup the Cash Status dashboard
         * uses. Overdraft statements carry future month-end interest rows,
         * hence the date cut-off. A negative end balance is the drawn amount.
         *
         * Room is the statement's own figure, not Limit − Outstanding: on the
         * commercial-paper and assignment-of-contract overdrafts it follows
         * the drawing power behind the facility, which can be below its limit.
         */
        $overdraftTypes = [
            CleanOverdraft::class => [__('Clean Overdraft'), 'clean_overdraft_bank_statements', 'clean_overdraft_id'],
            FullySecuredOverdraft::class => [__('Fully Secured Overdraft'), 'fully_secured_overdraft_bank_statements', 'fully_secured_overdraft_id'],
            OverdraftAgainstCommercialPaper::class => [__('Overdraft Against Commercial Paper'), 'overdraft_against_commercial_paper_bank_statements', 'overdraft_against_commercial_paper_id'],
            OverdraftAgainstAssignmentOfContract::class => [__('Overdraft Against Assignment Of Contract'), 'overdraft_against_assignment_of_contract_bank_statements', 'overdraft_against_assignment_of_contract_id'],
        ];

        foreach ($overdraftTypes as $modelClass => [$typeLabel, $statementTable, $foreignKey]) {
            $facilities = $modelClass::with('financialInstitution.bank')
                ->where('company_id', $company->id)
                ->get();
            $statements = LatestStatementQuery::latestStatementsByForeignKey($statementTable, $foreignKey, $company->id, $today, $facilities->pluck('id')->all());

            $facilities->each(function ($row) use (&$rows, $typeLabel, $statements) {
                $statement = $statements[$row->id] ?? null;
                $rows->push([
                    'id' => $typeLabel.'-'.$row->id,
                    'bank_name' => $row->financialInstitution?->getName() ?? __('N/A'),
                    'account_type' => $typeLabel,
                    'account_number' => $row->account_number,
                    'limit_amount' => (float) $row->limit,
                    'outstanding' => $statement ? -1 * (float) $statement->end_balance : 0.0,
                    'room' => $statement ? (float) $statement->room : (float) $row->limit,
                    'currency' => $row->currency,
                ]);
            });
        }

        MediumTermLoan::with('financialInstitution.bank')
            ->where('company_id', $company->id)
            ->get()
            ->each(function ($row) use (&$rows, $today) {
                /** @var MediumTermLoan $row */
                $rows->push([
                    'id' => 'mtl-'.$row->id,
                    'bank_name' => $row->financialInstitution?->getName() ?? __('N/A'),
                    'account_type' => __('Medium Term Loan'),
                    'account_number' => $row->account_number,
                    'limit_amount' => (float) $row->limit,
                    // Principle still to repay, from the installment schedule —
                    // works for loans drawn before CashVero too, which have no ledger.
                    'outstanding' => (float) $row->getRepaymentBreakdown()['totals']['principle_remaining'],
                    // Only a loan that pays suppliers from CashVero has room to draw.
                    'room' => $row->isNotConsumedYet() ? (float) $row->getAvailableRoomAt($today) : null,
                    'currency' => $row->currency,
                ]);
            });

        return Inertia::render('FinancialInstitutionFacilities/OdaAndMtlFacilities', [
            'company' => ['id' => $company->id, 'name' => $company->getName()],
            'rows' => $rows->sortBy('bank_name')->values(),
        ]);
    }

    /**
     * 3) LG & LC Facilities — every Letter of Guarantee facility and
     * every Letter of Credit facility across every bank for this
     * company, combined into one table (with a Type column added so
     * the two remain distinguishable once merged).
     */
    public function lgLcFacilities(Company $company)
    {
        $rows = collect();

        LetterOfGuaranteeFacility::with('financialInstitution.bank')
            ->where('company_id', $company->id)
            ->get()
            ->each(function ($row) use (&$rows, $company) {
                /**
                 * Same figures the LG issuance form checks a new LG against:
                 * the statements' outstanding, plus the amount the bank holds
                 * back for advance-payment LGs not activated yet.
                 */
                $outstanding = LetterOfGuaranteeStatement::getTotalOutstandingBalanceForAllTypes($row->id, $company->id, (int) $row->financial_institution_id, (string) $row->currency)
                    + LetterOfGuaranteeIssuance::reservedForUnactivated($company->id, $row->currency, (int) $row->financial_institution_id, LetterOfGuaranteeIssuance::LG_FACILITY, $row->id);
                $rows->push([
                    'id' => 'lg-'.$row->id,
                    'kind' => 'lg',
                    'facility_type' => __('Letter Of Guarantee'),
                    'bank_name' => $row->financialInstitution?->getName() ?? __('N/A'),
                    'contract_name' => $row->name ?: __('N/A'),
                    'start_date' => $row->contract_start_date,
                    'end_date' => $row->contract_end_date,
                    'limit_amount' => (float) $row->limit,
                    'outstanding' => $outstanding,
                    'room' => (float) $row->limit - $outstanding,
                    'currency' => $row->currency,
                ]);
            });

        LetterOfCreditFacility::with('financialInstitution.bank')
            ->where('company_id', $company->id)
            ->get()
            ->each(function ($row) use (&$rows, $company) {
                // Same figure the LC issuance form checks a new LC against.
                $outstanding = LetterOfCreditStatement::getFacilityOutstandingAcrossCurrencies($row->id, $company->id, (int) $row->financial_institution_id);
                $rows->push([
                    'id' => 'lc-'.$row->id,
                    'kind' => 'lc',
                    'outstanding' => $outstanding,
                    'room' => (float) $row->limit - $outstanding,
                    'facility_type' => __('Letter Of Credit'),
                    'bank_name' => $row->financialInstitution?->getName() ?? __('N/A'),
                    'contract_name' => $row->name ?: __('N/A'),
                    'start_date' => $row->contract_start_date,
                    'end_date' => $row->contract_end_date,
                    'limit_amount' => (float) $row->limit,
                    'currency' => $row->currency,
                ]);
            });

        return Inertia::render('FinancialInstitutionFacilities/LgLcFacilities', [
            'company' => ['id' => $company->id, 'name' => $company->getName()],
            'rows' => $rows->sortBy('bank_name')->values(),
        ]);
    }

    /**
     * 4) Leasing Facilities — every LeasingContract across every
     * Leasing company for this company.
     */
    public function leasingFacilities(Company $company)
    {
        $rows = LeasingContract::with('leasingCompany')
            ->where('company_id', $company->id)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'leasing_company_name' => $row->leasingCompany?->getName() ?? __('N/A'),
                'contract_name' => $row->name ?: __('N/A'),
                'start_date' => $row->start_date,
                'end_date' => $row->end_date,
                'limit_amount' => (float) $row->limit,
                'currency' => $row->currency,
            ])
            ->sortBy('leasing_company_name')
            ->values();

        return Inertia::render('FinancialInstitutionFacilities/LeasingFacilities', [
            'company' => ['id' => $company->id, 'name' => $company->getName()],
            'rows' => $rows,
        ]);
    }

    /**
     * 5) Factoring Facilities — every FactoringContract across every
     * Factoring company for this company. See class docblock note #2
     * about the "Contract Name" column for this one.
     */
    public function factoringFacilities(Company $company)
    {
        $rows = FactoringContract::with('factoringCompany')
            ->where('company_id', $company->id)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'factoring_company_name' => $row->factoringCompany?->getName() ?? __('N/A'),
                'contract_name' => FactoringContract::recourseTypes()[$row->recourse_type] ?? __('N/A'),
                'start_date' => $row->contract_start_date,
                'end_date' => $row->contract_end_date,
                'limit_amount' => (float) $row->limit,
                'currency' => $row->currency,
            ])
            ->sortBy('factoring_company_name')
            ->values();

        return Inertia::render('FinancialInstitutionFacilities/FactoringFacilities', [
            'company' => ['id' => $company->id, 'name' => $company->getName()],
            'rows' => $rows,
        ]);
    }
}
