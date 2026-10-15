<?php

namespace App\Console\Commands;

use App\Models\CashInSafeStatement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Re-orders EXISTING safe statement rows so that, inside each date, rows follow
 * the real creation order (opening-balance rows first), then recalculates the
 * running balances.
 *
 *   php artisan safe-statements:resequence                 -> DRY RUN (changes nothing)
 *   php artisan safe-statements:resequence --company=92    -> dry run for one company
 *   php artisan safe-statements:resequence --apply         -> really changes the data
 *
 * Day-end balances never change (same rows, same amounts) - only the order inside
 * a day and therefore the running balance between rows of the same day.
 * BACK UP THE DATABASE BEFORE USING --apply.
 */
class ResequenceSafeStatementsCommand extends Command
{
    protected $signature = 'safe-statements:resequence {--company= : Only this company id} {--apply : Really change the data (default is a dry run)} {--force : Skip the backup confirmation}';

    protected $description = 'Re-order safe statement rows inside each date by creation order (dry run by default)';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $companyId = $this->option('company');

        $rows = DB::table('cash_in_safe_statements')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->get(['id', 'company_id', 'branch_id', 'currency', 'date', 'type', 'opening_balance_id', 'created_at', 'full_date', 'debit', 'credit', 'beginning_balance', 'end_balance']);

        $groups = $rows->groupBy(fn ($r) => $r->company_id.'|'.$r->branch_id.'|'.$r->currency.'|'.$r->date);

        $toFix = [];
        foreach ($groups as $key => $groupRows) {
            if ($groupRows->count() < 2) {
                continue;
            }
            $desired = CashInSafeStatement::sortRowsOfOneDay($groupRows)->pluck('id')->map(fn ($id) => (int) $id)->all();
            $current = $groupRows->sortBy(fn ($r) => [(string) $r->full_date, (int) $r->id])->pluck('id')->map(fn ($id) => (int) $id)->all();
            if ($desired !== $current) {
                $toFix[$key] = $groupRows;
            }
        }

        $this->info('Safe statement rows checked : '.$rows->count());
        $this->info('Days (per safe/currency)    : '.$groups->count());
        $this->info('Days that need re-ordering  : '.count($toFix));

        if ($toFix === []) {
            $this->info('Nothing to change.');

            return self::SUCCESS;
        }

        // Show a few examples so the change can be reviewed before applying it.
        $shown = 0;
        foreach ($toFix as $key => $groupRows) {
            if ($shown++ >= 5) {
                break;
            }
            [$company, $branch, $currency, $date] = explode('|', $key);
            $this->line('');
            $this->line("Company $company | safe #$branch | $currency | date $date");
            $table = [];
            $desiredOrder = CashInSafeStatement::sortRowsOfOneDay($groupRows);
            $currentOrder = $groupRows->sortBy(fn ($r) => [(string) $r->full_date, (int) $r->id])->values();
            foreach ($currentOrder as $i => $r) {
                $new = $desiredOrder->search(fn ($d) => $d->id == $r->id) + 1;
                $table[] = [
                    $i + 1,
                    $new,
                    'id '.$r->id,
                    (float) $r->debit > 0 ? 'RECEIPT' : 'PAYMENT',
                    number_format((float) $r->debit + (float) $r->credit, 2),
                    $r->created_at,
                ];
            }
            $this->table(['Now', 'New', 'Row', 'Type', 'Amount', 'Created at (DB time)'], $table);
        }
        if (count($toFix) > 5) {
            $this->line('... and '.(count($toFix) - 5).' more day(s).');
        }

        if (! $apply) {
            $this->warn('DRY RUN - nothing was changed. Run again with --apply to change the data.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Have you BACKED UP the database? Continue?')) {
            $this->warn('Cancelled.');

            return self::FAILURE;
        }

        $minPerBranch = [];
        foreach ($toFix as $key => $groupRows) {
            [$company, $branch, $currency, $date] = explode('|', $key);
            $touched = CashInSafeStatement::resequenceDay((int) $company, (int) $branch, $currency === '' ? null : $currency, $date);
            if ($touched) {
                $minPerBranch[$branch] = isset($minPerBranch[$branch]) ? min($minPerBranch[$branch], $touched) : $touched;
            }
        }
        foreach ($minPerBranch as $branchId => $minFullDate) {
            CashInSafeStatement::cascadeFrom((int) $branchId, $minFullDate);
        }

        $this->info('Done. Re-ordered '.count($toFix).' day(s) and recalculated the balances of '.count($minPerBranch).' safe(s).');

        return self::SUCCESS;
    }
}
