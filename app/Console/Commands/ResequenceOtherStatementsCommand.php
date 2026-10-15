<?php

namespace App\Console\Commands;

use App\Models\EmployeeStatement;
use App\Models\LeasingContractBankStatement;
use App\Models\LetterOfCreditCashCoverStatement;
use App\Models\LetterOfCreditStatement;
use App\Models\LetterOfGuaranteeCashCoverStatement;
use App\Models\LetterOfGuaranteeStatement;
use App\Models\LoanStatement;
use App\Models\MediumTermLoanBankStatement;
use App\Models\OtherPartnerStatement;
use App\Models\ShareholderStatement;
use App\Models\SubsidiaryCompanyStatement;
use App\Models\TaxStatement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Re-orders EXISTING rows of the other statements inside each date, by the real creation order:
 * letters of guarantee / credit (+ cash cover), loans, medium term loans, leasing,
 * employee, tax, shareholder, subsidiary company and other-partner statements.
 *
 *   php artisan other-statements:resequence                 -> DRY RUN (changes nothing)
 *   php artisan other-statements:resequence --company=92    -> dry run for one company
 *   php artisan other-statements:resequence --apply         -> really changes the data
 *
 * After re-ordering, the balances of every affected chain are recalculated.
 * Day-end balances do not change (same rows, same amounts).
 * BACK UP THE DATABASE BEFORE USING --apply.
 */
class ResequenceOtherStatementsCommand extends Command
{
    protected $signature = 'other-statements:resequence {--company= : Only this company id} {--apply : Really change the data (default is a dry run)} {--force : Skip the backup confirmation}';

    protected $description = 'Re-order LG / LC / loan / leasing / employee / tax / shareholder / subsidiary / other-partner statement rows inside each date (dry run by default)';

    private const MODELS = [
        LetterOfGuaranteeStatement::class,
        LetterOfGuaranteeCashCoverStatement::class,
        LetterOfCreditStatement::class,
        LetterOfCreditCashCoverStatement::class,
        LoanStatement::class,
        MediumTermLoanBankStatement::class,
        LeasingContractBankStatement::class,
        EmployeeStatement::class,
        TaxStatement::class,
        ShareholderStatement::class,
        SubsidiaryCompanyStatement::class,
        OtherPartnerStatement::class,
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $companyId = $this->option('company');

        $plan = []; // [modelClass => [dayKey => [groupValues, date, rows]]]
        $totalDays = 0;
        $examples = 0;

        foreach (self::MODELS as $modelClass) {
            $table = (new $modelClass)->getTable();
            $columns = $modelClass::sameDayGroupColumns();
            $rows = DB::table($table)->when($companyId, fn ($q) => $q->where('company_id', $companyId))->get();
            $days = [];
            foreach ($rows->groupBy(fn ($r) => implode('|', array_map(fn ($c) => (string) ($r->{$c} ?? 'NULL'), $columns)).'|'.$r->date) as $key => $dayRows) {
                if ($dayRows->count() < 2) {
                    continue;
                }
                $desired = $modelClass::sortRowsOfOneDayAppendOrder($dayRows)->pluck('id')->map(fn ($i) => (int) $i)->all();
                $current = $dayRows->sortBy(fn ($r) => [(string) $r->full_date, (int) $r->id])->pluck('id')->map(fn ($i) => (int) $i)->all();
                if ($desired !== $current) {
                    $first = $dayRows->first();
                    $days[$key] = [$modelClass::sameDayGroupValuesOf($first), $first->date, $dayRows];
                }
            }
            $this->line(sprintf('%-42s rows %-6d days to re-order: %d', class_basename($modelClass), $rows->count(), count($days)));
            if ($days !== []) {
                $plan[$modelClass] = $days;
                $totalDays += count($days);
            }
        }

        $this->line('');
        $this->info('Total days to re-order: '.$totalDays);
        if ($totalDays === 0) {
            $this->info('Nothing to change.');

            return self::SUCCESS;
        }

        foreach ($plan as $modelClass => $days) {
            foreach ($days as $key => [$groupValues, $date, $dayRows]) {
                if ($examples++ >= 4) {
                    break 2;
                }
                $this->line('');
                $this->line(class_basename($modelClass).' | '.json_encode($groupValues).' | date '.$date);
                $desiredOrder = $modelClass::sortRowsOfOneDayAppendOrder($dayRows);
                $currentOrder = $dayRows->sortBy(fn ($r) => [(string) $r->full_date, (int) $r->id])->values();
                $table = [];
                foreach ($currentOrder as $i => $r) {
                    $table[] = [
                        $i + 1,
                        $desiredOrder->search(fn ($d) => $d->id == $r->id) + 1,
                        'id '.$r->id,
                        (float) ($r->debit ?? 0) > 0 ? 'DEBIT' : 'CREDIT',
                        number_format((float) ($r->debit ?? 0) + (float) ($r->credit ?? 0), 2),
                        $r->created_at,
                    ];
                }
                $this->table(['Now', 'New', 'Row', 'Side', 'Amount', 'Created at (DB time)'], $table);
            }
        }
        if ($totalDays > 4) {
            $this->line('... and more day(s) not shown.');
        }

        if (! $apply) {
            $this->warn('DRY RUN - nothing was changed. Run again with --apply to change the data.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Have you BACKED UP the database? Continue?')) {
            $this->warn('Cancelled.');

            return self::FAILURE;
        }

        $chains = 0;
        foreach ($plan as $modelClass => $days) {
            $minPerChain = [];
            foreach ($days as $key => [$groupValues, $date, $dayRows]) {
                $touched = $modelClass::resequenceOneDayAppendOrder($groupValues, $date);
                if ($touched) {
                    $chainKey = json_encode($groupValues);
                    $minPerChain[$chainKey] = [$groupValues, isset($minPerChain[$chainKey]) ? min($minPerChain[$chainKey][1], $touched) : $touched];
                }
            }
            foreach ($minPerChain as [$groupValues, $minFullDate]) {
                $modelClass::cascadeFromAppendOrder($groupValues, $minFullDate);
                $chains++;
            }
        }

        $this->info('Done. Re-ordered '.$totalDays.' day(s) and recalculated '.$chains.' balance chain(s).');

        return self::SUCCESS;
    }
}
