<?php

namespace App\Console\Commands;

use App\Models\CurrentAccountBankStatement;
use App\Models\FinancialInstitutionAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * ResyncEndOfMonthInterestCommand
 * ==================================================================
 * Fills in the month-end interest rows an account should have and
 * doesn't.
 *
 * WHY ANY ARE MISSING
 * Moving an account's Balance Date backwards opens up the months
 * between the new date and the old one — they now have a balance to
 * accrue on. Nothing added their rows, because
 * `synced_end_of_month_years` marked the year done and blocked every
 * re-run. It had to: the row's own date cannot say which month it
 * stands for (a user can edit it, and rows on record sit mid-month),
 * so the generator had no reliable way to avoid duplicating a month it
 * had already covered.
 *
 * `end_of_month_period` fixed that, and the generator is idempotent
 * again — this command is for the accounts that lost rows before it
 * existed.
 *
 * WHY A COMMAND AND NOT A MIGRATION
 * Creating a statement row fires the model's own hooks and the
 * database triggers that recompute every following balance. A raw
 * INSERT in a migration would add the row and leave the running
 * balances around it untouched. It also has to be re-runnable and
 * reportable, which a migration is not.
 *
 * USAGE
 *   php artisan statements:resync-month-end                 # report only (safe)
 *   php artisan statements:resync-month-end --company=92
 *   php artisan statements:resync-month-end --account=315
 *   php artisan statements:resync-month-end --fix           # actually create
 *
 * @see \App\Models\CurrentAccountBankStatement::resyncEndOfMonthInterestForAllYears()
 */
class ResyncEndOfMonthInterestCommand extends Command
{
    protected $signature = 'statements:resync-month-end
        {--fix : Create the missing rows instead of only reporting them}
        {--company= : Restrict to a single company id}
        {--account= : Restrict to a single bank account id}';

    protected $description = 'Report (and optionally create) the month-end interest rows an account is missing';

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');

        $this->line('');
        $this->info($fix ? 'MODE: FIX (missing rows will be created)' : 'MODE: REPORT ONLY (nothing will be created)');
        $this->line('');

        $accounts = FinancialInstitutionAccount::query()
            ->when($this->option('company'), fn ($query, $company) => $query->where('company_id', $company))
            ->when($this->option('account'), fn ($query, $account) => $query->where('id', $account))
            ->orderBy('id')
            ->get();

        $findings = [];
        $created = 0;
        $failed = 0;

        foreach ($accounts as $account) {
            $missing = $this->missingPeriodsFor($account);

            if ($missing === []) {
                continue;
            }

            $findings[] = [$account->id, $account->company_id, $account->balance_date, implode(', ', $missing)];

            if (! $fix) {
                continue;
            }

            try {
                $added = $this->resync($account);
                $created += $added;
                $this->info("    account {$account->id}: created {$added} row(s)");
            } catch (Throwable $e) {
                $failed++;
                $this->error("    account {$account->id} failed: ".$e->getMessage());
            }
        }

        $this->line('');

        if ($findings === []) {
            $this->info('No account is missing a month-end interest row.');

            return self::SUCCESS;
        }

        $this->table(['account', 'company', 'balance date', 'missing months'], $findings);

        if (! $fix) {
            $this->warn('Nothing was changed. Re-run with --fix to create these rows.');

            return self::SUCCESS;
        }

        $this->line("Created: {$created}".($failed ? ", failed: {$failed}" : ''));

        /*
         * Re-asking the same question afterwards is what proves the job
         * is done — and, because every row went through the model, that
         * the balance cascades ran too.
         */
        $remaining = 0;
        foreach ($accounts as $account) {
            $remaining += count($this->missingPeriodsFor($account->fresh()));
        }

        if ($remaining > 0) {
            $this->error("{$remaining} month(s) still missing — see the errors above.");

            return self::FAILURE;
        }

        $this->info('Verified: no month-end rows remain missing.');

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * The months this account has been synced for that still have no
     * row, skipping every month that ends on or before the balance
     * date — those have no balance to accrue on, which is the same rule
     * the generator applies.
     *
     * @return list<string>
     */
    private function missingPeriodsFor(FinancialInstitutionAccount $account): array
    {
        $years = array_filter((array) $account->synced_end_of_month_years);

        if ($years === [] || ! $account->balance_date) {
            return [];
        }

        $hasBeginningBalance = DB::table('current_account_bank_statements')
            ->where('financial_institution_account_id', $account->id)
            ->where('is_beginning_balance', 1)
            ->exists();

        if (! $hasBeginningBalance) {
            return [];
        }

        $present = DB::table('current_account_bank_statements')
            ->where('financial_institution_account_id', $account->id)
            ->whereIn('interest_type', ['end_of_month', 'end_of_month_final'])
            ->whereNotNull('end_of_month_period')
            ->pluck('end_of_month_period')
            ->all();
        $present = array_flip($present);

        $missing = [];

        foreach ($years as $year) {
            for ($month = 1; $month <= 12; $month++) {
                $period = sprintf('%s-%02d', $year, $month);
                $monthEnd = \Carbon\Carbon::create((int) $year, $month, 1)->endOfMonth()->format('Y-m-d');

                if ($monthEnd <= $account->balance_date) {
                    continue;
                }

                if (! isset($present[$period])) {
                    $missing[] = $period;
                }
            }
        }

        return $missing;
    }

    private function resync(FinancialInstitutionAccount $account): int
    {
        $beginningBalance = CurrentAccountBankStatement::withoutGlobalScopes()
            ->where('financial_institution_account_id', $account->id)
            ->where('is_beginning_balance', 1)
            ->first();

        if (! $beginningBalance) {
            return 0;
        }

        return $beginningBalance->resyncEndOfMonthInterestForAllYears($account->company_id);
    }
}
