<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * FindOrphanMoneyRowsCommand
 * ------------------------------------------------------------------
 * Finds — and optionally deletes — child rows (bank statements, partner
 * statements, cheques, transfers, settlements …) whose owning
 * money_payment / money_received / cash_expense row no longer exists.
 *
 * WHY THESE ROWS EXIST
 * The cleanup of a money row's children lives in
 * MoneyPayment::deleteRelations() (and the MoneyReceived / CashExpense
 * equivalents), which the controllers call explicitly *before*
 * ->delete(). There is no `deleting` model event doing it, so every
 * delete path that does not go through those controllers leaves the
 * children behind — e.g.:
 *
 *   - SupplierOpeningBalancesController: a query-builder mass delete
 *     ($openingBalance->moneyModel()->where(...)->delete()), which skips
 *     both deleteRelations() and the model events entirely
 *   - DeletingClass@truncate / @multipleRowsDeleting (routes
 *     Truncate/{model} and DeleteMultipleRows/{model}), which call
 *     ->delete() on the model without deleteRelations()
 *
 * The result is exactly the reported symptom: the money payment is gone
 * from its screen, but its row is still sitting in the bank statement.
 *
 * WHY A COMMAND AND NOT A MIGRATION
 * CurrentAccountBankStatement::deleting() zeroes debit/credit and saves,
 * which fires updateNextRows() and recomputes beginning_balance /
 * end_balance for every later row on that account. A raw SQL DELETE in a
 * migration would remove the row but leave every following balance
 * wrong. So the fix has to go through Eloquent, row by row — and it has
 * to be re-runnable and reportable, which a migration is not.
 *
 * USAGE
 *   php artisan money:orphan-rows                  # report only (safe)
 *   php artisan money:orphan-rows --company=92     # limit to one company
 *   php artisan money:orphan-rows --table=current_account_bank_statements
 *   php artisan money:orphan-rows --fix            # actually delete
 */
class FindOrphanMoneyRowsCommand extends Command
{
    protected $signature = 'money:orphan-rows
        {--fix : Delete the orphan rows instead of only reporting them}
        {--company= : Restrict to a single company id}
        {--table= : Restrict to a single child table}
        {--samples=5 : How many sample rows to print per finding}
        {--ids : List every orphan id in the summary instead of the first 25}';

    protected $description = 'Report (and optionally delete) child rows whose money payment / money received / cash expense owner no longer exists';

    /**
     * Owner column => owner table.
     */
    private const OWNERS = [
        'money_payment_id' => 'money_payments',
        'money_received_id' => 'money_received',
        'cash_expense_id' => 'cash_expenses',
    ];

    /**
     * Child table => Eloquent model. Deleting through the model is what
     * keeps the running balances correct, so a table without a model is
     * reported but never auto-deleted.
     *
     * @var array<string, class-string<Model>>
     */
    private const MODELS = [
        'current_account_bank_statements' => \App\Models\CurrentAccountBankStatement::class,
        'cash_in_safe_statements' => \App\Models\CashInSafeStatement::class,
        'clean_overdraft_bank_statements' => \App\Models\CleanOverdraftBankStatement::class,
        'fully_secured_overdraft_bank_statements' => \App\Models\FullySecuredOverdraftBankStatement::class,
        'overdraft_against_assignment_of_contract_bank_statements' => \App\Models\OverdraftAgainstAssignmentOfContractBankStatement::class,
        'overdraft_against_commercial_paper_bank_statements' => \App\Models\OverdraftAgainstCommercialPaperBankStatement::class,
        'employee_statements' => \App\Models\EmployeeStatement::class,
        'other_partner_statements' => \App\Models\OtherPartnerStatement::class,
        'shareholder_statements' => \App\Models\ShareholderStatement::class,
        'subsidiary_company_statements' => \App\Models\SubsidiaryCompanyStatement::class,
        'tax_statements' => \App\Models\TaxStatement::class,
        'outgoing_transfers' => \App\Models\OutgoingTransfer::class,
        'payable_cheques' => \App\Models\PayableCheque::class,
        'cash_payments' => \App\Models\CashPayment::class,
        'incoming_transfers' => \App\Models\IncomingTransfer::class,
        'cash_in_banks' => \App\Models\CashInBank::class,
        'cash_in_safes' => \App\Models\CashInSafe::class,
        'cheques' => \App\Models\Cheque::class,
        'settlements' => \App\Models\Settlement::class,
        'payment_settlements' => \App\Models\PaymentSettlement::class,
        'settlement_allocations' => \App\Models\SettlementAllocation::class,
        'down_payment_settlements' => \App\Models\DownPaymentSettlement::class,
        'down_payment_money_payment_settlements' => \App\Models\DownPaymentMoneyPaymentSettlement::class,
    ];

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');
        $companyId = $this->option('company');
        $onlyTable = $this->option('table');
        $samples = (int) $this->option('samples');

        $this->line('');
        $this->info($fix ? 'MODE: FIX (rows will be deleted)' : 'MODE: REPORT ONLY (nothing will be deleted)');
        if ($companyId) {
            $this->line("Company filter: {$companyId}");
        }
        $this->line('');

        $findings = [];
        $grandTotal = 0;
        $skipped = [];

        foreach ($this->childTables($onlyTable) as $table => $columns) {
            /**
             * A few child tables carry no company_id, and an orphan row's
             * company can no longer be looked up through its (deleted)
             * owner. Rather than silently ignoring the filter and deleting
             * across every company, skip them and say so.
             */
            if ($companyId && ! Schema::hasColumn($table, 'company_id')) {
                $skipped[] = $table;

                continue;
            }

            $ids = $this->orphanIds($table, $columns, $companyId);

            if ($ids === []) {
                continue;
            }

            $grandTotal += count($ids);
            $findings[] = [$table, implode(' + ', $columns), count($ids), $this->formatIds($ids)];
            $this->reportFinding($table, $columns, $ids, $samples);

            if ($fix) {
                $this->deleteOrphans($table, $ids);
            }
        }

        $this->line('');
        if ($skipped !== []) {
            $this->warn('Skipped (no company_id column, cannot honour --company): '.implode(', ', $skipped));
            $this->line('Run them without --company, or one at a time with --table=<name>.');
            $this->line('');
        }

        if ($findings === []) {
            $this->info('No orphan rows found.');

            return self::SUCCESS;
        }

        $this->table(['child table', 'owner columns checked', 'orphan rows', 'ids'], $findings);
        $this->line("TOTAL orphan rows: {$grandTotal}");

        if (! $fix) {
            $this->line('');
            $this->warn('Nothing was changed. Re-run with --fix to delete these rows.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, list<string>>
     */
    private function childTables(?string $onlyTable): array
    {
        $result = [];

        foreach (array_keys(self::MODELS) as $table) {
            if ($onlyTable && $table !== $onlyTable) {
                continue;
            }
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach (array_keys(self::OWNERS) as $column) {
                if (Schema::hasColumn($table, $column)) {
                    $result[$table][] = $column;
                }
            }
        }

        return $result;
    }

    /**
     * A row counts as an orphan ONLY when every owner id it carries is
     * dead.
     *
     * This is deliberately stricter than "this one column points at a
     * missing row". MoneyPayment's partner-statement relations use the
     * wrong foreign key (money_received_id instead of money_payment_id,
     * see MoneyPayment::employeeStatement() and friends), so those rows
     * end up carrying BOTH money_payment_id and money_received_id set to
     * the same number. Judging such a row by money_received_id alone
     * marks a perfectly live payment statement as an orphan — and
     * deleting it would destroy real data. As long as one owner is still
     * alive, the row has an owner and is left alone.
     *
     * @param  list<string>  $columns
     * @return list<int>
     */
    private function orphanIds(string $table, array $columns, ?string $companyId): array
    {
        $query = DB::table($table)
            // at least one owner id present, otherwise the row is simply unowned
            ->where(function ($any) use ($table, $columns) {
                foreach ($columns as $column) {
                    $any->orWhere(function ($set) use ($table, $column) {
                        $set->whereNotNull($table.'.'.$column)->where($table.'.'.$column, '!=', 0);
                    });
                }
            });

        // ... and every owner id that IS present must be dead
        foreach ($columns as $column) {
            $ownerTable = self::OWNERS[$column];
            $query->where(function ($dead) use ($table, $column, $ownerTable) {
                $dead->whereNull($table.'.'.$column)
                    ->orWhere($table.'.'.$column, 0)
                    ->orWhereNotExists(function ($sub) use ($ownerTable, $table, $column) {
                        $sub->select(DB::raw(1))
                            ->from($ownerTable)
                            ->whereColumn($ownerTable.'.id', $table.'.'.$column);
                    });
            });
        }

        if ($companyId && Schema::hasColumn($table, 'company_id')) {
            $query->where($table.'.company_id', $companyId);
        }

        return $query->orderBy($table.'.id')->pluck($table.'.id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * The ids themselves, so a finding can be checked straight against
     * the table without re-running anything. Long lists are cut short —
     * pass --ids to get every one of them.
     *
     * @param  list<int>  $ids
     */
    private function formatIds(array $ids): string
    {
        $limit = $this->option('ids') ? count($ids) : 25;

        if (count($ids) <= $limit) {
            return implode(', ', $ids);
        }

        return implode(', ', array_slice($ids, 0, $limit)).' … +'.(count($ids) - $limit).' more';
    }

    /**
     * @param  list<string>  $columns
     * @param  list<int>  $ids
     */
    private function reportFinding(string $table, array $columns, array $ids, int $samples): void
    {
        $this->warn(sprintf('%s (%s all dead) : %d row(s)', $table, implode(' + ', $columns), count($ids)));

        if ($samples <= 0) {
            return;
        }

        $preview = array_slice($ids, 0, $samples);
        $show = array_values(array_intersect(
            array_merge(['id', 'company_id', 'date', 'debit', 'credit', 'comment_en'], $columns),
            Schema::getColumnListing($table)
        ));

        foreach (DB::table($table)->whereIn('id', $preview)->get($show) as $row) {
            $this->line('    '.json_encode($row, JSON_UNESCAPED_UNICODE));
        }

        if (count($ids) > $samples) {
            $this->line('    ... +'.(count($ids) - $samples).' more');
        }
    }

    /**
     * Deletes through the Eloquent model (never a mass SQL delete) so the
     * statement models' deleting/updated hooks re-run and the running
     * balances after each removed row stay correct.
     *
     * @param  list<int>  $ids
     */
    private function deleteOrphans(string $table, array $ids): void
    {
        $modelClass = self::MODELS[$table] ?? null;

        if (! $modelClass) {
            $this->error("    no model mapped for {$table} — skipped, delete it manually");

            return;
        }

        $deleted = 0;
        $failed = 0;

        foreach ($ids as $id) {
            try {
                /** @var Model|null $row */
                $row = $modelClass::withoutGlobalScopes()->find($id);
                if (! $row) {
                    continue;
                }
                $row->delete();
                $deleted++;
            } catch (Throwable $e) {
                $failed++;
                $this->error("    failed to delete {$table}#{$id}: ".$e->getMessage());
            }
        }

        $this->info("    deleted {$deleted} row(s)".($failed ? ", {$failed} failed" : ''));
    }
}
