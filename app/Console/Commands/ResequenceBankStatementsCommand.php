<?php

namespace App\Console\Commands;

use App\Models\CleanOverdraftBankStatement;
use App\Models\CurrentAccountBankStatement;
use App\Models\FullySecuredOverdraftBankStatement;
use App\Models\LcOverdraftBankStatement;
use App\Models\OverdraftAgainstAssignmentOfContractBankStatement;
use App\Models\OverdraftAgainstCommercialPaperBankStatement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Re-orders EXISTING bank statement rows inside each date.
 *
 *   php artisan bank-statements:resequence                 -> DRY RUN (changes nothing)
 *   php artisan bank-statements:resequence --company=92    -> dry run for one company
 *   php artisan bank-statements:resequence --apply         -> really changes the data
 *
 * BANK CURRENT ACCOUNTS : inside each date -> beginning balance first, then the rows in the real
 *   creation order, then end-of-month interest last. Balances are recalculated afterwards.
 *   Day-end balances do not change (same rows, same amounts).
 *
 * OVERDRAFTS (clean / fully secured / against contracts / against commercial paper / LC) :
 *   the screen order is aligned with the order the balances already use (limit row first, rows by
 *   id, end-of-month interest last). NO balance changes at all, and the settlement priority
 *   ("interest before principal") is not touched.
 *
 * BACK UP THE DATABASE BEFORE USING --apply.
 */
class ResequenceBankStatementsCommand extends Command
{
    protected $signature = 'bank-statements:resequence {--company= : Only this company id} {--apply : Really change the data (default is a dry run)} {--force : Skip the backup confirmation}';

    protected $description = 'Re-order bank current account + overdraft statement rows inside each date (dry run by default)';

    /** @var array<int, array{0:class-string,1:string}> model, facility key column */
    private const OVERDRAFTS = [
        [CleanOverdraftBankStatement::class, 'clean_overdraft_id'],
        [FullySecuredOverdraftBankStatement::class, 'fully_secured_overdraft_id'],
        [OverdraftAgainstAssignmentOfContractBankStatement::class, 'overdraft_against_assignment_of_contract_id'],
        [OverdraftAgainstCommercialPaperBankStatement::class, 'overdraft_against_commercial_paper_id'],
        [LcOverdraftBankStatement::class, 'lc_facility_id'],
    ];

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $companyId = $this->option('company');

        // ---------- find what needs fixing ----------
        $currentRows = DB::table('current_account_bank_statements')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
            ->get(['id', 'company_id', 'financial_institution_account_id', 'date', 'is_beginning_balance', 'interest_type', 'created_at', 'full_date', 'debit', 'credit']);

        $currentToFix = [];
        foreach ($currentRows->groupBy(fn ($r) => $r->company_id.'|'.$r->financial_institution_account_id.'|'.$r->date) as $key => $rows) {
            if ($rows->count() < 2) {
                continue;
            }
            $desired = CurrentAccountBankStatement::sortRowsOfOneDay($rows)->pluck('id')->map(fn ($i) => (int) $i)->all();
            $current = $rows->sortBy(fn ($r) => [(string) $r->full_date, (int) $r->id])->pluck('id')->map(fn ($i) => (int) $i)->all();
            if ($desired !== $current) {
                $currentToFix[$key] = $rows;
            }
        }

        $overdraftToFix = [];
        $overdraftRowsChecked = 0;
        foreach (self::OVERDRAFTS as [$modelClass, $fk]) {
            $table = (new $modelClass)->getTable();
            $rows = DB::table($table)
                ->when($companyId, fn ($q) => $q->where('company_id', $companyId))
                ->get(['id', $fk.' as facility_id', 'date', 'type', 'interest_type', 'full_date']);
            $overdraftRowsChecked += $rows->count();
            foreach ($rows->groupBy(fn ($r) => $r->facility_id.'|'.$r->date) as $key => $dayRows) {
                if ($dayRows->count() < 2) {
                    continue;
                }
                $desired = $modelClass::sortOverdraftRowsOfOneDay($dayRows)->pluck('id')->map(fn ($i) => (int) $i)->all();
                $current = $dayRows->sortBy(fn ($r) => [(string) $r->full_date, (int) $r->id])->pluck('id')->map(fn ($i) => (int) $i)->all();
                if ($desired !== $current) {
                    $overdraftToFix[] = [$modelClass, $fk, $key];
                }
            }
        }

        // ---------- report ----------
        $this->info('BANK CURRENT ACCOUNTS');
        $this->info('  rows checked                : '.$currentRows->count());
        $this->info('  days that need re-ordering  : '.count($currentToFix));
        $this->info('OVERDRAFT STATEMENTS (all 5 types)');
        $this->info('  rows checked                : '.$overdraftRowsChecked);
        $this->info('  days that need re-ordering  : '.count($overdraftToFix));

        $shown = 0;
        foreach ($currentToFix as $key => $rows) {
            if ($shown++ >= 5) {
                break;
            }
            [$company, $account, $date] = explode('|', $key);
            $this->line('');
            $this->line("Current account #$account | company $company | date $date");
            $desiredOrder = CurrentAccountBankStatement::sortRowsOfOneDay($rows);
            $currentOrder = $rows->sortBy(fn ($r) => [(string) $r->full_date, (int) $r->id])->values();
            $table = [];
            foreach ($currentOrder as $i => $r) {
                $table[] = [
                    $i + 1,
                    $desiredOrder->search(fn ($d) => $d->id == $r->id) + 1,
                    'id '.$r->id,
                    CurrentAccountBankStatement::rankOf($r) === 0 ? 'BEGINNING' : (CurrentAccountBankStatement::rankOf($r) === 2 ? 'MONTH-END INTEREST' : ((float) $r->debit > 0 ? 'IN' : 'OUT')),
                    number_format((float) $r->debit + (float) $r->credit, 2),
                    $r->created_at,
                ];
            }
            $this->table(['Now', 'New', 'Row', 'Type', 'Amount', 'Created at (DB time)'], $table);
        }
        if (count($currentToFix) > 5) {
            $this->line('... and '.(count($currentToFix) - 5).' more current-account day(s).');
        }

        if ($currentToFix === [] && $overdraftToFix === []) {
            $this->info('Nothing to change.');

            return self::SUCCESS;
        }

        if (! $apply) {
            $this->warn('DRY RUN - nothing was changed. Run again with --apply to change the data.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Have you BACKED UP the database? Continue?')) {
            $this->warn('Cancelled.');

            return self::FAILURE;
        }

        // ---------- apply ----------
        $minPerAccount = [];
        foreach ($currentToFix as $key => $rows) {
            [$company, $account, $date] = explode('|', $key);
            $touched = CurrentAccountBankStatement::resequenceDay((int) $company, (int) $account, $date);
            if ($touched) {
                $k = $company.'|'.$account;
                $minPerAccount[$k] = isset($minPerAccount[$k]) ? min($minPerAccount[$k], $touched) : $touched;
            }
        }
        foreach ($minPerAccount as $k => $minFullDate) {
            [$company, $account] = explode('|', $k);
            CurrentAccountBankStatement::cascadeFrom((int) $company, (int) $account, $minFullDate);
        }

        foreach ($overdraftToFix as [$modelClass, $fk, $key]) {
            [$facilityId, $date] = explode('|', $key);
            $modelClass::resequenceOverdraftDay((int) $facilityId, $fk, $date);
        }

        $this->info('Done. Current accounts: '.count($currentToFix).' day(s) re-ordered, '.count($minPerAccount).' account(s) recalculated. Overdrafts: '.count($overdraftToFix).' day(s) re-ordered (no balance changes).');

        return self::SUCCESS;
    }
}
