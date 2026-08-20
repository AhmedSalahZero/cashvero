<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * FindOrphanLgRowsCommand
 * ------------------------------------------------------------------
 * Finds — and optionally deletes — statement rows whose owning
 * letter_of_guarantee_issuances row no longer exists.
 *
 * WHY THESE ROWS EXIST
 * Cleanup of an LG issuance's children used to live only in
 * LetterOfGuaranteeIssuance::deleteAllRelations(), which the controller
 * called explicitly before ->delete(). Any delete path that did not go
 * through that call left the children behind. Only two child tables
 * have a real FK CASCADE in the schema
 * (lg_renewal_date_histories, lg_issuance_advanced_payment_histories)
 * and those two are the only ones that came out clean — the three
 * tables below, guarded by application code alone, are where rows
 * leaked.
 *
 * The symptom is a dashboard that disagrees with its own list page:
 * the LG Issuance screen is empty, while the LG/LC dashboard still
 * reports an Outstanding Balance, because
 * LetterOfGuaranteeStatement::getTotalOutstandingBalanceForAllTypes()
 * reads the statements table directly with no check that the parent
 * issuance is still there. The same leak also leaves LG commission
 * fees sitting in a bank account's statement.
 *
 * The leak itself is now closed by LetterOfGuaranteeIssuance::deleting()
 * — this command is for the rows that leaked before that existed.
 *
 * WHY A COMMAND AND NOT A MIGRATION
 * Each of these models' deleting() hook zeroes debit/credit and saves,
 * which fires updateNextRows() and recomputes beginning_balance /
 * end_balance for every later row in the same series. A raw SQL DELETE
 * would remove the row but leave every following balance wrong. So the
 * fix has to go through Eloquent, row by row — and it has to be
 * re-runnable and reportable, which a migration is not.
 *
 * Mirrors money:orphan-rows, which does the same job for money
 * payment / received / cash expense owners.
 *
 * USAGE
 *   php artisan lg:orphan-rows                  # report only (safe)
 *   php artisan lg:orphan-rows --company=146    # limit to one company
 *   php artisan lg:orphan-rows --table=letter_of_guarantee_statements
 *   php artisan lg:orphan-rows --fix            # actually delete
 */
class FindOrphanLgRowsCommand extends Command
{
    protected $signature = 'lg:orphan-rows
        {--fix : Delete the orphan rows instead of only reporting them}
        {--company= : Restrict to a single company id}
        {--table= : Restrict to a single child table}
        {--samples=5 : How many sample rows to print per finding}
        {--ids : List every orphan id in the summary instead of the first 25}';

    protected $description = 'Report (and optionally delete) statement rows whose letter of guarantee issuance no longer exists';

    private const OWNER_COLUMN = 'letter_of_guarantee_issuance_id';

    private const OWNER_TABLE = 'letter_of_guarantee_issuances';

    /**
     * Child table => Eloquent model. Deleting through the model is what
     * keeps the running balances correct, so a table without a model is
     * reported but never auto-deleted.
     *
     * @var array<string, class-string<Model>>
     */
    private const MODELS = [
        'letter_of_guarantee_statements' => \App\Models\LetterOfGuaranteeStatement::class,
        'letter_of_guarantee_cash_cover_statements' => \App\Models\LetterOfGuaranteeCashCoverStatement::class,
        'current_account_bank_statements' => \App\Models\CurrentAccountBankStatement::class,
    ];

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');
        $companyId = $this->option('company');
        $onlyTable = $this->option('table');
        $samples = (int) $this->option('samples');

        if ($onlyTable && ! isset(self::MODELS[$onlyTable])) {
            $this->error("Unknown --table={$onlyTable}. Expected one of: ".implode(', ', array_keys(self::MODELS)));

            return self::FAILURE;
        }

        $this->line('');
        $this->info($fix ? 'MODE: FIX (rows will be deleted)' : 'MODE: REPORT ONLY (nothing will be deleted)');
        if ($companyId) {
            $this->line("Company filter: {$companyId}");
        }
        $this->line('');

        $findings = [];
        $grandTotal = 0;
        $deletedTotal = 0;
        $failedTotal = 0;

        foreach (array_keys(self::MODELS) as $table) {
            if ($onlyTable && $table !== $onlyTable) {
                continue;
            }
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, self::OWNER_COLUMN)) {
                continue;
            }

            $ids = $this->orphanIds($table, $companyId);

            if ($ids === []) {
                continue;
            }

            $grandTotal += count($ids);
            $findings[] = [$table, count($ids), $this->formatIds($ids)];
            $this->reportFinding($table, $ids, $samples);

            if ($fix) {
                [$deleted, $failed] = $this->deleteOrphans($table, $ids);
                $deletedTotal += $deleted;
                $failedTotal += $failed;
            }
        }

        $this->line('');

        if ($findings === []) {
            $this->info('No orphan rows found.');

            return self::SUCCESS;
        }

        $this->table(['child table', 'orphan rows', 'ids'], $findings);
        $this->line("TOTAL orphan rows: {$grandTotal}");

        if (! $fix) {
            $this->line('');
            $this->warn('Nothing was changed. Re-run with --fix to delete these rows.');

            return self::SUCCESS;
        }

        $this->line("Deleted: {$deletedTotal}".($failedTotal ? ", failed: {$failedTotal}" : ''));

        /**
         * Re-running the same detection after the fix is what proves the
         * job is done — and, because every delete went through Eloquent,
         * that the balance cascades ran too. A non-empty result here
         * means something refused to delete, not that the report lied.
         */
        $remaining = 0;
        foreach (array_keys(self::MODELS) as $table) {
            if ($onlyTable && $table !== $onlyTable) {
                continue;
            }
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, self::OWNER_COLUMN)) {
                continue;
            }
            $remaining += count($this->orphanIds($table, $companyId));
        }

        if ($remaining > 0) {
            $this->error("{$remaining} orphan row(s) still present after the fix — see the errors above.");

            return self::FAILURE;
        }

        $this->info('Verified: no orphan rows remain.');

        return $failedTotal > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * A row is an orphan when it names an issuance (> 0, so the
     * facility-level beginning-balance rows that legitimately carry 0
     * are never touched) and that issuance is gone.
     *
     * Runs on the query builder, not the model, so
     * CurrentAccountBankStatement's `only_active` global scope cannot
     * hide the inactive future-commission rows — they are orphans too.
     *
     * @return list<int>
     */
    private function orphanIds(string $table, ?string $companyId): array
    {
        $query = DB::table($table)
            ->whereNotNull($table.'.'.self::OWNER_COLUMN)
            ->where($table.'.'.self::OWNER_COLUMN, '>', 0)
            ->whereNotExists(function ($sub) use ($table) {
                $sub->select(DB::raw(1))
                    ->from(self::OWNER_TABLE)
                    ->whereColumn(self::OWNER_TABLE.'.id', $table.'.'.self::OWNER_COLUMN);
            });

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
     * @param  list<int>  $ids
     */
    private function reportFinding(string $table, array $ids, int $samples): void
    {
        $this->warn(sprintf('%s : %d orphan row(s)', $table, count($ids)));

        if ($samples <= 0) {
            return;
        }

        $preview = array_slice($ids, 0, $samples);
        $show = array_values(array_intersect(
            ['id', 'company_id', self::OWNER_COLUMN, 'date', 'debit', 'credit', 'end_balance', 'comment_en'],
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
     * Deletes through the Eloquent model (never a mass SQL delete) so
     * each model's deleting/updated hooks re-run and the running
     * balances after every removed row stay correct.
     *
     * withoutGlobalScopes() matters here: an inactive
     * current_account_bank_statements row (a not-yet-due commission) is
     * invisible to the model's `only_active` scope, and find() would
     * return null — silently leaving the orphan behind.
     *
     * @param  list<int>  $ids
     * @return array{0: int, 1: int} deleted, failed
     */
    private function deleteOrphans(string $table, array $ids): array
    {
        $modelClass = self::MODELS[$table] ?? null;

        if (! $modelClass) {
            $this->error("    no model mapped for {$table} — skipped, delete it manually");

            return [0, count($ids)];
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

        return [$deleted, $failed];
    }
}
