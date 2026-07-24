<?php
namespace App\Http\Controllers;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\Partner;
use App\Services\Api\OdooService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * OdooSettingController
 * ------------------------------------------------------------------
 * "Other Odoo Integration Settings" — a single, always-present GL
 * account mapping form per company (one OdooSetting row per company,
 * not a list of records — Route::resource() is used but only
 * index()/store() are actually implemented; the rest are dead,
 * unlinked resource routes, matching this project's established
 * pattern of leaving unused routes registered).
 *
 * Every field here is a "Chart Of Account Number" (an Odoo GL account
 * code, plain text) grouped into 6 sections matching the original
 * exactly: Liquidity/Treasury Accounts, LG & LC Cash Cover Accounts,
 * Taxes & Social Insurance, Bank Charges & Fees, Bank Facilities
 * Interest Expense, and a repeater for Interest Revenues Accounts
 * (each row: a Bank + its own GL code, since interest revenue is
 * tracked per bank).
 *
 * store() does something genuinely important on every submit: for
 * every submitted `..._code` field, it makes a LIVE Odoo API call
 * (`OdooService::fetchData('account.account', ...)`) to resolve that
 * code to a real Odoo account ID — only fields that successfully
 * resolve get saved at all (both the code and the resolved ID). A
 * code that doesn't exist in Odoo is silently dropped, not an error.
 * Tax-named fields additionally get pushed onto the matching Partner
 * tax record's odoo_id. UNCHANGED — this genuinely requires a live
 * Odoo connection and cannot be simulated; test this against a real
 * company with Odoo configured.
 *
 * ⚠️ Confirmed omission in the original, preserved exactly: the model
 * has both `insurance_from_account_code` and `insurance_to_account_code`,
 * but the form only ever exposes "Insurance From Account" — "Insurance
 * To Account" has no field in the original form at all. Not added
 * here either, since introducing a field the original never had would
 * be a scope change, not a like-for-like migration.
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   ✅ index() → MIGRATED to Vue + Inertia. Renders
 *      resources/js/Pages/OdooSettings/Form.vue.
 *   ✅ store() → UNCHANGED, deliberately — already returns a proper
 *      redirect (unlike several other controllers in this project
 *      that needed a JSON→redirect fix). The live Odoo resolution
 *      logic, tax-record syncing, and interest-revenue-account
 *      rebuild are all untouched.
 */
class OdooSettingController
{
    public function index(Company $company, Request $request)
    {
        $financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get();
        $setting = $company->odooSetting;

        return \Inertia\Inertia::render('OdooSettings/Form', [
            'company' => ['id' => $company->id],
            'model' => $setting ? [
                'liquidity_transfer_account_code' => $setting->liquidity_transfer_account_code,
                'custody_account_code' => $setting->custody_account_code,
                'employee_loans_account_code' => $setting->employee_loans_account_code,
                'cheques_receivable_code' => $setting->cheques_receivable_code,
                'cheques_payable_code' => $setting->cheques_payable_code,
                'shareholder_account_code' => $setting->shareholder_account_code,
                'dividend_payable_account_code' => $setting->dividend_payable_account_code,
                'insurance_from_account_code' => $setting->insurance_from_account_code,
                'advances_to_suppliers_code' => $setting->advances_to_suppliers_code,
                'advances_from_customers_code' => $setting->advances_from_customers_code,

                'bid_lg_cash_cover_code' => $setting->bid_lg_cash_cover_code,
                'final_lg_cash_cover_code' => $setting->final_lg_cash_cover_code,
                'advanced_lg_cash_cover_code' => $setting->advanced_lg_cash_cover_code,
                'performance_lg_cash_cover_code' => $setting->performance_lg_cash_cover_code,
                'sight_lc_cash_cover_code' => $setting->sight_lc_cash_cover_code,
                'deferred_lc_cash_cover_code' => $setting->deferred_lc_cash_cover_code,

                'vat_taxes_code' => $setting->vat_taxes_code,
                'credit_withhold_taxes_code' => $setting->credit_withhold_taxes_code,
                'salary_taxes_code' => $setting->salary_taxes_code,
                'social_insurance_code' => $setting->social_insurance_code,
                'income_taxes_code' => $setting->income_taxes_code,
                'takaful_code' => $setting->takaful_code,
                'tax_for_victims_code' => $setting->tax_for_victims_code,
                'real_estate_taxes_code' => $setting->real_estate_taxes_code,
                'stamp_duty_taxes_code' => $setting->stamp_duty_taxes_code,
                'other_taxes_code' => $setting->other_taxes_code,

                'letter_of_guarantee_commission_fees_code' => $setting->letter_of_guarantee_commission_fees_code,
                'letter_of_guarantee_issuance_fees_code' => $setting->letter_of_guarantee_issuance_fees_code,
                'letter_of_credit_commission_fees_code' => $setting->letter_of_credit_commission_fees_code,
                'letter_of_credit_other_fees_code' => $setting->letter_of_credit_other_fees_code,

                'fully_secured_overdraft_interest_expense_code' => $setting->fully_secured_overdraft_interest_expense_code,
                'clean_overdraft_interest_expense_code' => $setting->clean_overdraft_interest_expense_code,
                'overdraft_against_commercial_paper_interest_expense_code' => $setting->overdraft_against_commercial_paper_interest_expense_code,
                'overdraft_against_contract_assignment_interest_expense_code' => $setting->overdraft_against_contract_assignment_interest_expense_code,
                'medium_term_loan_interest_expense_code' => $setting->medium_term_loan_interest_expense_code,
            ] : null,
            'financialInstitutionBanks' => $financialInstitutionBanks->map(fn ($b) => ['id' => $b->id, 'name' => $b->getName()])->values(),
            'interestRevenueAccounts' => $company->interestRevenuesAccounts->map(fn ($a) => [
                'financial_institution_id' => $a->getFinancialInstitutionId(),
                'odoo_code' => $a->getOdooCode(),
            ])->values(),
            'submitUrl' => route('odoo-settings.store', ['company' => $company->id]),
            'navUrls' => [
                'home' => route('home', ['company' => $company->id]),
                'bank_accounts' => route('view.financial.institutions', ['company' => $company->id, 'active' => 'bank']),
                'customers' => route('partners.index', ['company' => $company->id, 'type' => 'customers']),
                'suppliers' => route('partners.index', ['company' => $company->id, 'type' => 'suppliers']),
                'notifications' => route('view.notifications', ['company' => $company->id, 'type' => 'all']),
            ],
        ]);
    }

    /**
     * Resolves and saves every submitted GL account code. UNCHANGED,
     * deliberately — live Odoo API resolution, tax-record syncing,
     * and interest-revenue-account rebuild all untouched.
     */
    public function store(Request $request, Company $company)
    {
        $setting = $company->odooSetting;
        $result = [];
        $odooService = new OdooService($company);
        $taxesColumns = Partner::getTaxesNames();
        $revenueResults = [];
        foreach ($request->get('revenues') as $revenueArr) {
            $code = $revenueArr['odoo_code'];
            $bankId = isset($revenueArr['bank']) && is_numeric($revenueArr['bank']) ? $revenueArr['bank'] : null;
            $journal = $odooService->fetchData('account.account', ['code', 'name'], [[['code', '=', $code]]]);
            $odooId = $journal[0]['id'] ?? null;
            if ($odooId) {
                $revenueResults[] = [
                    'odoo_id' => $odooId,
                    'odoo_code' => $code,
                    'financial_institution_id' => $bankId,
                    'company_id' => $company->id,
                ];
            }
        }
        $company->interestRevenuesAccounts()->delete();
        if (count($revenueResults)) {
            DB::table('interest_revenue_accounts')->insert($revenueResults);
        }
        foreach ($request->except(array_merge(['_token', 'revenues'])) as $key => $value) {
            $journal = $odooService->fetchData('account.account', ['code', 'name'], [[['code', '=', $value]]]);
            if ($journal) {
                $dbKeyName = str_replace('_code', '_id', $key);
                $result[$dbKeyName] = $journal[0]['id'];
                $result[$key] = $value;
                if (in_array($key, array_keys($taxesColumns))) {
                    Partner::where('company_id', $company->id)->where('name', $taxesColumns[$key])->where('is_tax', 1)->update([
                        'odoo_id' => $result[$dbKeyName],
                    ]);
                }
            }
        }

        $setting ? $setting->update($result) : $company->odooSetting()->create($result);

        return redirect()->route('odoo-settings.index', ['company' => $company->id]);
    }
}
