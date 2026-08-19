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

        $rows = $accounts->map(fn ($account) => [
            'id' => $account->id,
            'bank_name' => $account->financialInstitution?->getName() ?? __('N/A'),
            'account_type' => __('Current Account'),
            'account_number' => $account->account_number,
            'owner_name' => $account->getShareholderName() ?? __('Company'),
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

        $overdraftTypes = [
            CleanOverdraft::class => __('Clean Overdraft'),
            FullySecuredOverdraft::class => __('Fully Secured Overdraft'),
            OverdraftAgainstCommercialPaper::class => __('Overdraft Against Commercial Paper'),
            OverdraftAgainstAssignmentOfContract::class => __('Overdraft Against Assignment Of Contract'),
        ];

        foreach ($overdraftTypes as $modelClass => $typeLabel) {
            $modelClass::with('financialInstitution.bank')
                ->where('company_id', $company->id)
                ->get()
                ->each(function ($row) use (&$rows, $typeLabel) {
                    $rows->push([
                        'id' => $typeLabel.'-'.$row->id,
                        'bank_name' => $row->financialInstitution?->getName() ?? __('N/A'),
                        'account_type' => $typeLabel,
                        'account_number' => $row->account_number,
                        'limit_amount' => (float) $row->limit,
                        'currency' => $row->currency,
                    ]);
                });
        }

        MediumTermLoan::with('financialInstitution.bank')
            ->where('company_id', $company->id)
            ->get()
            ->each(function ($row) use (&$rows) {
                $rows->push([
                    'id' => 'mtl-'.$row->id,
                    'bank_name' => $row->financialInstitution?->getName() ?? __('N/A'),
                    'account_type' => __('Medium Term Loan'),
                    'account_number' => $row->account_number,
                    'limit_amount' => (float) $row->limit,
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
            ->each(function ($row) use (&$rows) {
                $rows->push([
                    'id' => 'lg-'.$row->id,
                    'facility_type' => __('Letter Of Guarantee'),
                    'bank_name' => $row->financialInstitution?->getName() ?? __('N/A'),
                    'contract_name' => $row->name ?: __('N/A'),
                    'start_date' => $row->contract_start_date,
                    'end_date' => $row->contract_end_date,
                    'limit_amount' => (float) $row->limit,
                    'currency' => $row->currency,
                ]);
            });

        LetterOfCreditFacility::with('financialInstitution.bank')
            ->where('company_id', $company->id)
            ->get()
            ->each(function ($row) use (&$rows) {
                $rows->push([
                    'id' => 'lc-'.$row->id,
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
