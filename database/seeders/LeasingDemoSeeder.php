<?php

namespace Database\Seeders;

use App\Models\ContractLoanSchedule;
use App\Models\FinancialInstitutionAccount;
use App\Models\LeasingCompany;
use App\Models\LeasingContract;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Demo data for the "Through Leasing" money type.
 *
 * Gives a leasing company something to pay from, so the Money Payment
 * screen's Contract Name dropdown is not empty. Safe to re-run: every
 * contract is matched on (company, leasing company, name) and updated
 * rather than duplicated.
 *
 * Usage:
 *   php artisan db:seed --class=LeasingDemoSeeder
 *   php artisan db:seed --class=LeasingDemoSeeder   # COMPANY_ID=... LEASING_COMPANY=...
 *
 * Override the targets with environment variables:
 *   COMPANY_ID       the company to seed into        (default: 146)
 *   LEASING_COMPANY  the leasing company's name      (default: the
 *                    company's first leasing company)
 */
class LeasingDemoSeeder extends Seeder
{
    public function run(): void
    {
        $companyId = (int) (env('COMPANY_ID') ?: 146);

        $leasingCompany = LeasingCompany::where('company_id', $companyId)
            ->when(env('LEASING_COMPANY'), fn ($q) => $q->where('name', env('LEASING_COMPANY')))
            ->orderBy('id')
            ->first();

        if (! $leasingCompany) {
            $this->command?->error("No leasing company found for company {$companyId}. Create one first.");

            return;
        }

        $this->command?->info("Seeding leasing contracts for [{$leasingCompany->getName()}] in company {$companyId}.");

        /**
         * The one with a schedule: drawn from, repaid, and therefore the
         * one whose contract statement has something to show.
         */
        $main = $this->contract($companyId, $leasingCompany->id, [
            'name' => 'LC-2026-001',
            'currency' => 'EGP',
            'limit' => 1000000,
            'duration' => 10,
        ]);

        $this->schedule($companyId, $main);

        /**
         * A fresh contract with no schedule yet — its whole limit is
         * available, which is the state most new contracts start in.
         */
        $this->contract($companyId, $leasingCompany->id, [
            'name' => 'LC-2026-002',
            'currency' => 'EGP',
            'limit' => 500000,
            'duration' => 12,
        ]);

        /**
         * A USD contract. It must NOT appear when paying in EGP — the
         * dropdown filters on the payment currency, and this is what
         * proves that on screen rather than only in a test.
         */
        $this->contract($companyId, $leasingCompany->id, [
            'name' => 'LC-2026-003',
            'currency' => 'USD',
            'limit' => 200000,
            'duration' => 24,
        ]);

        $this->command?->info('Done. Money Payment → Money Type → Through Leasing now has contracts to pick.');
    }

    private function contract(int $companyId, int $leasingCompanyId, array $attributes): LeasingContract
    {
        $contract = LeasingContract::firstOrNew([
            'company_id' => $companyId,
            'leasing_company_id' => $leasingCompanyId,
            'name' => $attributes['name'],
        ]);

        $contract->fill([
            'status' => LeasingContract::RUNNING,
            'currency' => $attributes['currency'],
            'limit' => $attributes['limit'],
            'start_date' => Carbon::now()->subMonths(2)->format('Y-m-d'),
            'end_date' => Carbon::now()->addMonths($attributes['duration'])->format('Y-m-d'),
            'borrowing_rate' => 12,
            'margin_rate' => 2,
            'duration' => $attributes['duration'],
            'installment_payment_interval' => 'monthly',
            'first_installment_date' => Carbon::now()->addMonth()->startOfMonth()->format('Y-m-d'),
            'remaining_installment_count' => $attributes['duration'],
        ])->save();

        $this->command?->line("  · {$attributes['name']} — {$attributes['currency']} ".number_format($attributes['limit']));

        return $contract;
    }

    /**
     * Ten monthly installments of 110,000 — 100,000 principle plus
     * 10,000 interest each, so the interest/principle split on the
     * contract statement is obvious at a glance and the numbers stay
     * round when an installment is settled.
     */
    private function schedule(int $companyId, LeasingContract $contract): void
    {
        if ($contract->contractLoanSchedules()->exists()) {
            $this->command?->line('    (schedule already present — left alone)');

            return;
        }

        /**
         * Settling an installment really moves money out of a current
         * account, so the schedule points at a funded one. Picking the
         * best-funded EGP account keeps the demo settleable instead of
         * failing on "Net Balance Less Than Paid Amount".
         */
        $account = $this->bestFundedEgpAccount($companyId);

        if (! $account) {
            $this->command?->warn('    No funded EGP current account found — schedule created without a drawee bank, so its installments cannot be settled.');
        }

        $installment = 110000.0;
        $principle = 100000.0;
        $interest = 10000.0;
        $balance = (float) $contract->getLimit();

        for ($i = 1; $i <= 10; $i++) {
            $date = Carbon::now()->addMonths($i)->startOfMonth();
            $endBalance = $balance - $principle;

            ContractLoanSchedule::create([
                'leasing_contract_id' => $contract->id,
                'company_id' => $companyId,
                'date' => $date->format('Y-m-d'),
                'beginning_balance' => $balance,
                'cheque_amount' => $installment,
                'interest_amount' => $interest,
                'principle_amount' => $principle,
                'end_balance' => $endBalance,
                'remaining' => $installment,
                // Same helper the real Excel import uses, so a seeded row
                // is indistinguishable from an imported one.
                'status' => resolveLoanScheduleStatus($installment, $installment, $date->format('Y-m-d')),
                'cheque_number' => 'CHQ-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT),
                'drawee_bank_id' => $account?->financial_institution_id,
                'financial_institution_account_id' => $account?->id,
                'account_number' => $account?->account_number,
            ]);

            $balance = $endBalance;
        }

        $this->command?->line('    + 10 monthly installments of 110,000 (100,000 principle + 10,000 interest)');
    }

    private function bestFundedEgpAccount(int $companyId): ?FinancialInstitutionAccount
    {
        $accountId = \Illuminate\Support\Facades\DB::table('current_account_bank_statements as s')
            ->join('financial_institution_accounts as a', 'a.id', '=', 's.financial_institution_account_id')
            ->where('s.company_id', $companyId)
            ->where('a.currency', 'EGP')
            ->where('a.is_active', 1)
            ->orderByRaw('s.full_date desc , s.id desc')
            ->get(['a.id as account_id', 's.end_balance'])
            ->unique('account_id')
            ->sortByDesc('end_balance')
            ->value('account_id');

        return $accountId ? FinancialInstitutionAccount::find($accountId) : null;
    }
}
