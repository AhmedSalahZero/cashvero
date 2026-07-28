<?php

namespace App\Services\CompanyData;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Copies one company's CashVero rows from the local mysql_source DB
 * (system.veroanalysis.com / veroanalysis) into the default DB (cash-vero),
 * preserving primary keys when safe.
 */
class CompanyImportService
{
    public const SOURCE = 'mysql_source';

    /** Transient / cache tables: wipe on target, do not copy from source. */
    private const SKIP_COPY = [
        'active_jobs',
        'logs',
        'temp_deleted_statements',
    ];

    /** Prefer these tables early when ordering inserts (roots first). */
    private const BOOTSTRAP_ORDER = [
        'companies',
        'company_systems',
        'notification_settings',
        'odoo_settings',
        'branch',
        'cash_vero_business_units',
        'cash_vero_business_sectors',
        'cash_vero_sales_channels',
        'cash_vero_sales_persons',
        'deductions',
        'cash_expense_categories',
        'cash_expense_category_names',
        'partners',
        'financial_institutions',
        'financial_institution_accounts',
        'contracts',
        'opening_balances',
        'customer_opening_balances',
        'supplier_opening_balances',
    ];

    /** Indirect children (no company_id) keyed by parent lookup. */
    private const INDIRECT = [
        'account_interests' => [
            'parent_table' => 'financial_institution_accounts',
            'parent_fk' => 'financial_institution_account_id',
        ],
        'outgoing_transfers' => [
            'parent_tables' => [
                'money_payments' => 'money_payment_id',
                'cash_expenses' => 'cash_expense_id',
            ],
        ],
        'settlement_allocations' => [
            'parent_tables' => [
                'money_payments' => 'money_payment_id',
                'customer_invoices' => 'invoice_id',
                'contracts' => 'contract_id',
                'partners' => 'partner_id',
                'letter_of_credit_issuances' => 'letter_of_credit_issuance_id',
            ],
        ],
        'po_allocations' => [
            'parent_tables' => [
                'contracts' => 'contract_id',
                'purchase_orders' => 'purchase_order_id',
                'partners' => 'partner_id',
            ],
        ],
        'cash_expense_contract' => [
            'parent_tables' => [
                'cash_expenses' => 'cash_expense_id',
                'contracts' => 'contract_id',
            ],
        ],
    ];

    private string $target;

    public function __construct(?string $targetConnection = null)
    {
        $this->target = $targetConnection ?? (string) config('database.default');
    }

    public function sourceConnection(): string
    {
        return self::SOURCE;
    }

    public function targetConnection(): string
    {
        return $this->target;
    }

    /**
     * @return array{
     *   company_id: int,
     *   source_db: string,
     *   target_db: string,
     *   intersection: array<int, string>,
     *   source_only: array<int, string>,
     *   target_only: array<int, string>,
     *   source_counts: array<string, int>,
     *   target_counts: array<string, int>,
     *   collisions: array<int, array{table: string, id: int|string, other_company_id: mixed}>,
     *   source_company: object|null,
     *   target_company: object|null
     * }
     */
    public function analyze(int $companyId): array
    {
        $sourceTables = $this->companyIdTables(self::SOURCE);
        $targetTables = $this->companyIdTables($this->target);

        $intersection = array_values(array_intersect($sourceTables, $targetTables));
        sort($intersection);

        $sourceOnly = array_values(array_diff($sourceTables, $targetTables));
        sort($sourceOnly);
        $targetOnly = array_values(array_diff($targetTables, $sourceTables));
        sort($targetOnly);

        $sourceCounts = [];
        $targetCounts = [];
        foreach ($intersection as $table) {
            if (in_array($table, self::SKIP_COPY, true)) {
                continue;
            }
            $sourceCounts[$table] = (int) DB::connection(self::SOURCE)->table($table)->where('company_id', $companyId)->count();
            $targetCounts[$table] = (int) DB::connection($this->target)->table($table)->where('company_id', $companyId)->count();
        }

        return [
            'company_id' => $companyId,
            'source_db' => (string) DB::connection(self::SOURCE)->getDatabaseName(),
            'target_db' => (string) DB::connection($this->target)->getDatabaseName(),
            'intersection' => $intersection,
            'source_only' => $sourceOnly,
            'target_only' => $targetOnly,
            'source_counts' => $sourceCounts,
            'target_counts' => $targetCounts,
            'collisions' => $this->findCollisions($companyId, $intersection),
            'source_company' => DB::connection(self::SOURCE)->table('companies')->where('id', $companyId)->first(),
            'target_company' => DB::connection($this->target)->table('companies')->where('id', $companyId)->first(),
        ];
    }

    /**
     * @return array{ok: bool, report: array<string, mixed>}
     */
    public function import(int $companyId, bool $dryRun = false): array
    {
        $analysis = $this->analyze($companyId);
        $report = [
            'analysis' => $analysis,
            'dry_run' => $dryRun,
            'copied' => [],
            'wiped' => [],
            'indirect' => [],
            'users' => [],
            'verification' => null,
            'errors' => [],
        ];

        if (! $analysis['source_company']) {
            $report['errors'][] = "Company {$companyId} not found on source DB {$analysis['source_db']}.";
            $report['ok'] = false;

            return ['ok' => false, 'report' => $report];
        }

        if (count($analysis['collisions']) > 0) {
            $report['errors'][] = 'PK collisions with other companies on target — aborting.';
            $report['ok'] = false;

            return ['ok' => false, 'report' => $report];
        }

        if ($dryRun) {
            $report['verification'] = $this->buildExpectedVerification($companyId, $analysis['intersection']);
            $report['ok'] = true;

            return ['ok' => true, 'report' => $report];
        }

        $tables = $this->orderedTables($analysis['intersection']);

        try {
            DB::connection($this->target)->statement('SET FOREIGN_KEY_CHECKS=0');

            $this->wipeTargetCompany($companyId, $tables, $report);
            $this->upsertCompanyRow($companyId, $report);
            $this->copyCompanyIdTables($companyId, $tables, $report);
            $this->copyUsersAndMembership($companyId, $report);
            $this->copyIndirectTables($companyId, $report);
            $this->copyCompanyMedia($companyId, $report);

            DB::connection($this->target)->statement('SET FOREIGN_KEY_CHECKS=1');
        } catch (Throwable $e) {
            try {
                DB::connection($this->target)->statement('SET FOREIGN_KEY_CHECKS=1');
            } catch (Throwable) {
            }
            $report['errors'][] = $e->getMessage();
            $report['ok'] = false;

            return ['ok' => false, 'report' => $report];
        }

        $verification = $this->verify($companyId, $analysis['intersection']);
        $report['verification'] = $verification;
        $report['ok'] = $verification['ok'];

        if (! $verification['ok']) {
            $report['errors'][] = 'Post-import verification failed.';
        }

        return ['ok' => $report['ok'], 'report' => $report];
    }

    /**
     * Re-runnable parity check against source (no writes).
     *
     * @return array{ok: bool, mismatches: array<int, array<string, mixed>>, checks: int}
     */
    public function verify(int $companyId, ?array $intersection = null): array
    {
        $intersection = $intersection ?? array_values(array_intersect(
            $this->companyIdTables(self::SOURCE),
            $this->companyIdTables($this->target)
        ));

        $mismatches = [];
        $checks = 0;

        foreach ($intersection as $table) {
            if (in_array($table, self::SKIP_COPY, true)) {
                continue;
            }
            if (! Schema::connection(self::SOURCE)->hasTable($table) || ! Schema::connection($this->target)->hasTable($table)) {
                continue;
            }

            $checks++;
            $sourceCount = (int) DB::connection(self::SOURCE)->table($table)->where('company_id', $companyId)->count();
            $targetCount = (int) DB::connection($this->target)->table($table)->where('company_id', $companyId)->count();
            if ($sourceCount !== $targetCount) {
                $mismatches[] = [
                    'table' => $table,
                    'type' => 'count',
                    'source' => $sourceCount,
                    'target' => $targetCount,
                ];
            }

            foreach ($this->moneyColumns($table) as $column) {
                $checks++;
                $sourceSum = $this->moneySum(self::SOURCE, $table, $column, $companyId);
                $targetSum = $this->moneySum($this->target, $table, $column, $companyId);
                if ($sourceSum !== $targetSum) {
                    $mismatches[] = [
                        'table' => $table,
                        'type' => 'sum',
                        'column' => $column,
                        'source' => $sourceSum,
                        'target' => $targetSum,
                    ];
                }
            }
        }

        foreach (self::INDIRECT as $table => $config) {
            if (! Schema::connection(self::SOURCE)->hasTable($table) || ! Schema::connection($this->target)->hasTable($table)) {
                continue;
            }
            $checks++;
            $pk = $this->primaryKeyColumn($table, self::SOURCE) ?? 'id';
            $sourcePkIds = $this->selectIndirectRows(self::SOURCE, $companyId, $table, $config)->pluck($pk)->all();
            $targetPkIds = $this->selectIndirectRows($this->target, $companyId, $table, $config)->pluck($pk)->all();
            sort($sourcePkIds);
            sort($targetPkIds);
            if ($sourcePkIds !== $targetPkIds) {
                $mismatches[] = [
                    'table' => $table,
                    'type' => 'indirect_ids',
                    'source' => count($sourcePkIds),
                    'target' => count($targetPkIds),
                ];
            }
        }

        return [
            'ok' => count($mismatches) === 0,
            'mismatches' => $mismatches,
            'checks' => $checks,
        ];
    }

    /**
     * @param  array<int, string>  $tables
     * @return array<int, array{table: string, id: int|string, other_company_id: mixed}>
     */
    private function findCollisions(int $companyId, array $tables): array
    {
        $collisions = [];

        foreach ($tables as $table) {
            if (in_array($table, self::SKIP_COPY, true)) {
                continue;
            }
            if ($table === 'companies') {
                continue;
            }
            if (! Schema::connection($this->target)->hasColumn($table, 'id')) {
                continue;
            }

            $sourceIds = DB::connection(self::SOURCE)->table($table)
                ->where('company_id', $companyId)
                ->orderBy('id')
                ->pluck('id')
                ->all();

            if (! count($sourceIds)) {
                continue;
            }

            foreach (array_chunk($sourceIds, 500) as $chunk) {
                $rows = DB::connection($this->target)->table($table)
                    ->whereIn('id', $chunk)
                    ->where('company_id', '!=', $companyId)
                    ->get(['id', 'company_id']);

                foreach ($rows as $row) {
                    $collisions[] = [
                        'table' => $table,
                        'id' => $row->id,
                        'other_company_id' => $row->company_id,
                    ];
                    if (count($collisions) >= 50) {
                        return $collisions;
                    }
                }
            }
        }

        return $collisions;
    }

    /**
     * @param  array<int, string>  $tables
     * @param  array<string, mixed>  $report
     */
    private function wipeTargetCompany(int $companyId, array $tables, array &$report): void
    {
        // Collect parent IDs from SOURCE before wipe (PKs preserved).
        $indirectParents = [];
        foreach (self::INDIRECT as $table => $config) {
            $indirectParents[$table] = $config;
        }

        // Wipe indirect children first using source parent ID sets.
        foreach (self::INDIRECT as $table => $config) {
            if (! Schema::connection($this->target)->hasTable($table)) {
                continue;
            }
            $deleted = $this->deleteIndirectOnTarget($companyId, $table, $config);
            $report['wiped'][$table] = $deleted;
        }

        // Wipe company media for Company morph.
        if (Schema::connection($this->target)->hasTable('media')) {
            $report['wiped']['media'] = DB::connection($this->target)->table('media')
                ->where('model_type', 'App\\Models\\Company')
                ->where('model_id', $companyId)
                ->delete();
        }

        $pending = $tables;
        // Also wipe skip-copy tables if present on target.
        foreach (self::SKIP_COPY as $skip) {
            if (Schema::connection($this->target)->hasTable($skip)
                && Schema::connection($this->target)->hasColumn($skip, 'company_id')
                && ! in_array($skip, $pending, true)
            ) {
                $pending[] = $skip;
            }
        }

        $attempt = 0;
        while ($attempt < 15 && count($pending)) {
            $failed = [];
            foreach ($pending as $table) {
                if ($table === 'companies') {
                    continue;
                }
                try {
                    if (! Schema::connection($this->target)->hasColumn($table, 'company_id')) {
                        continue;
                    }
                    $n = DB::connection($this->target)->table($table)->where('company_id', $companyId)->delete();
                    $report['wiped'][$table] = ($report['wiped'][$table] ?? 0) + $n;
                } catch (Throwable) {
                    $failed[] = $table;
                }
            }
            $pending = $failed;
            $attempt++;
        }

        if (count($pending)) {
            throw new \RuntimeException('Could not wipe tables after retries: '.implode(', ', $pending));
        }

        unset($indirectParents);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function upsertCompanyRow(int $companyId, array &$report): void
    {
        $row = DB::connection(self::SOURCE)->table('companies')->where('id', $companyId)->first();
        if (! $row) {
            throw new \RuntimeException("Source company {$companyId} missing.");
        }

        $payload = $this->filterColumns('companies', (array) $row);
        $exists = DB::connection($this->target)->table('companies')->where('id', $companyId)->exists();
        if ($exists) {
            $update = $payload;
            unset($update['id']);
            DB::connection($this->target)->table('companies')->where('id', $companyId)->update($update);
            $report['copied']['companies'] = 'updated';
        } else {
            DB::connection($this->target)->table('companies')->insert($payload);
            $report['copied']['companies'] = 'inserted';
        }
    }

    /**
     * @param  array<int, string>  $tables
     * @param  array<string, mixed>  $report
     */
    private function copyCompanyIdTables(int $companyId, array $tables, array &$report): void
    {
        foreach ($tables as $table) {
            // companies: upserted separately. companies_users: after users exist.
            if ($table === 'companies' || $table === 'companies_users' || in_array($table, self::SKIP_COPY, true)) {
                continue;
            }
            if (! Schema::connection($this->target)->hasColumn($table, 'company_id')) {
                continue;
            }

            $inserted = 0;
            $orderCol = $this->primaryKeyColumn($table, self::SOURCE);
            $query = DB::connection(self::SOURCE)->table($table)->where('company_id', $companyId);
            if ($orderCol) {
                $query->orderBy($orderCol);
            } else {
                $query->orderByRaw('1');
            }
            $query->chunk(200, function ($rows) use ($table, &$inserted) {
                    $batch = [];
                    foreach ($rows as $row) {
                        $batch[] = $this->filterColumns($table, (array) $row);
                    }
                    if (count($batch)) {
                        DB::connection($this->target)->table($table)->insert($batch);
                        $inserted += count($batch);
                    }
                });

            $report['copied'][$table] = $inserted;
        }
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function copyUsersAndMembership(int $companyId, array &$report): void
    {
        if (! Schema::connection(self::SOURCE)->hasTable('companies_users')) {
            return;
        }

        $memberships = DB::connection(self::SOURCE)->table('companies_users')
            ->where('company_id', $companyId)
            ->get();

        $createdUsers = 0;
        $linked = 0;
        $remappedByEmail = 0;
        $skipped = 0;

        foreach ($memberships as $membership) {
            $sourceUserId = (int) $membership->user_id;
            $sourceUser = DB::connection(self::SOURCE)->table('users')->where('id', $sourceUserId)->first();
            if (! $sourceUser) {
                $skipped++;

                continue;
            }

            $targetUserId = null;
            $byId = DB::connection($this->target)->table('users')->where('id', $sourceUserId)->first();
            if ($byId) {
                $targetUserId = $sourceUserId;
            } else {
                $byEmail = null;
                if (! empty($sourceUser->email)) {
                    $byEmail = DB::connection($this->target)->table('users')
                        ->where('email', $sourceUser->email)
                        ->first();
                }
                if ($byEmail) {
                    // Same person, different PK — link the existing target user.
                    $targetUserId = (int) $byEmail->id;
                    $remappedByEmail++;
                } else {
                    $payload = $this->filterColumns('users', (array) $sourceUser);
                    DB::connection($this->target)->table('users')->insert($payload);
                    $targetUserId = $sourceUserId;
                    $createdUsers++;
                }
            }

            $exists = DB::connection($this->target)->table('companies_users')
                ->where('company_id', $companyId)
                ->where('user_id', $targetUserId)
                ->exists();

            if (! $exists) {
                DB::connection($this->target)->table('companies_users')->insert([
                    'company_id' => $companyId,
                    'user_id' => $targetUserId,
                ]);
                $linked++;
            }
        }

        $report['users'] = [
            'memberships_source' => $memberships->count(),
            'users_created' => $createdUsers,
            'memberships_inserted' => $linked,
            'remapped_by_email' => $remappedByEmail,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function copyIndirectTables(int $companyId, array &$report): void
    {
        foreach (self::INDIRECT as $table => $config) {
            if (! Schema::connection(self::SOURCE)->hasTable($table) || ! Schema::connection($this->target)->hasTable($table)) {
                continue;
            }

            $rows = $this->selectIndirectRows(self::SOURCE, $companyId, $table, $config);
            $inserted = 0;
            foreach ($rows->chunk(200) as $chunk) {
                $batch = [];
                foreach ($chunk as $row) {
                    $batch[] = $this->filterColumns($table, (array) $row);
                }
                if (count($batch)) {
                    // Avoid duplicate PK if residual rows remain
                    foreach ($batch as $item) {
                        $pk = $this->primaryKeyColumn($table, $this->target) ?? 'id';
                        if (isset($item[$pk]) && DB::connection($this->target)->table($table)->where($pk, $item[$pk])->exists()) {
                            continue;
                        }
                        DB::connection($this->target)->table($table)->insert($item);
                        $inserted++;
                    }
                }
            }
            $report['indirect'][$table] = $inserted;
        }
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function copyCompanyMedia(int $companyId, array &$report): void
    {
        if (! Schema::connection(self::SOURCE)->hasTable('media') || ! Schema::connection($this->target)->hasTable('media')) {
            return;
        }

        $rows = DB::connection(self::SOURCE)->table('media')
            ->where('model_type', 'App\\Models\\Company')
            ->where('model_id', $companyId)
            ->get();

        $inserted = 0;
        foreach ($rows as $row) {
            $payload = $this->filterColumns('media', (array) $row);
            $pk = $this->primaryKeyColumn('media', $this->target) ?? 'id';
            if (isset($payload[$pk]) && DB::connection($this->target)->table('media')->where($pk, $payload[$pk])->exists()) {
                // Collision on media id belonging to something else — skip rather than abort cutover of data.
                continue;
            }
            DB::connection($this->target)->table('media')->insert($payload);
            $inserted++;
        }
        $report['indirect']['media'] = $inserted;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function deleteIndirectOnTarget(int $companyId, string $table, array $config): int
    {
        $query = DB::connection($this->target)->table($table);
        $this->applyIndirectConstraints($query, $companyId, $config, $this->target);

        return $query->delete();
    }

    /**
     * @param  array<string, mixed>  $config
     * @return \Illuminate\Support\Collection<int, \stdClass>
     */
    private function selectIndirectRows(string $connection, int $companyId, string $table, array $config)
    {
        $query = DB::connection($connection)->table($table);
        $this->applyIndirectConstraints($query, $companyId, $config, $connection);

        return $query->get();
    }

    /**
     * Parent IDs always come from SOURCE so wipe and copy stay aligned after the target wipe.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array<string, mixed>  $config
     */
    private function applyIndirectConstraints($query, int $companyId, array $config, string $parentConnection): void
    {
        unset($parentConnection);

        if (isset($config['parent_table'], $config['parent_fk'])) {
            $parentIds = DB::connection(self::SOURCE)
                ->table($config['parent_table'])
                ->where('company_id', $companyId)
                ->pluck('id')
                ->all();

            if (! count($parentIds)) {
                $query->whereRaw('1 = 0');

                return;
            }
            $query->whereIn($config['parent_fk'], $parentIds);

            return;
        }

        if (isset($config['parent_tables']) && is_array($config['parent_tables'])) {
            $query->where(function ($q) use ($config, $companyId) {
                $any = false;
                foreach ($config['parent_tables'] as $parentTable => $fk) {
                    if (! Schema::connection(self::SOURCE)->hasTable($parentTable)) {
                        continue;
                    }
                    $ids = DB::connection(self::SOURCE)->table($parentTable)
                        ->where('company_id', $companyId)
                        ->pluck('id')
                        ->all();
                    if (! count($ids)) {
                        continue;
                    }
                    $any = true;
                    $q->orWhereIn($fk, $ids);
                }
                if (! $any) {
                    $q->whereRaw('1 = 0');
                }
            });
        }
    }

    /**
     * @return array<int, string>
     */
    private function companyIdTables(string $connection): array
    {
        return getTableNamesThatHasColumn('company_id', $connection);
    }

    /**
     * @param  array<int, string>  $tables
     * @return array<int, string>
     */
    private function orderedTables(array $tables): array
    {
        $set = array_fill_keys($tables, true);
        $ordered = [];

        foreach (self::BOOTSTRAP_ORDER as $table) {
            if (isset($set[$table])) {
                $ordered[] = $table;
                unset($set[$table]);
            }
        }

        $rest = array_keys($set);
        sort($rest);

        return array_merge($ordered, $rest);
    }

    /**
     * Keep only columns that exist on the target table; encode arrays/objects as JSON.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function filterColumns(string $table, array $row): array
    {
        $targetColumns = Schema::connection($this->target)->getColumnListing($table);
        $allowed = array_flip($targetColumns);
        $out = [];

        foreach ($row as $key => $value) {
            if (! isset($allowed[$key])) {
                continue;
            }
            if (is_array($value) || is_object($value)) {
                $out[$key] = json_encode($value);
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    private function primaryKeyColumn(string $table, string $connection): ?string
    {
        $database = DB::connection($connection)->getDatabaseName();
        $row = DB::connection($connection)->table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', 'PRIMARY')
            ->orderBy('ORDINAL_POSITION')
            ->first();

        return $row->COLUMN_NAME ?? ($row->column_name ?? null);
    }

    /**
     * @return array<int, string>
     */
    private function moneyColumns(string $table): array
    {
        $candidates = ['debit', 'credit', 'amount', 'received_amount', 'paid_amount', 'invoice_amount_in_main_currency', 'net_invoice_amount_in_main_currency'];
        $cols = [];
        foreach ($candidates as $column) {
            if (Schema::connection($this->target)->hasColumn($table, $column)
                && Schema::connection(self::SOURCE)->hasColumn($table, $column)
            ) {
                $cols[] = $column;
            }
        }

        return $cols;
    }

    private function moneySum(string $connection, string $table, string $column, int $companyId): string
    {
        $value = DB::connection($connection)->table($table)
            ->where('company_id', $companyId)
            ->sum($column);

        return number_format((float) $value, 2, '.', '');
    }

    /**
     * @param  array<int, string>  $intersection
     * @return array{ok: bool, mismatches: array<int, mixed>, checks: int, note: string}
     */
    private function buildExpectedVerification(int $companyId, array $intersection): array
    {
        return [
            'ok' => true,
            'mismatches' => [],
            'checks' => 0,
            'note' => 'dry-run: verification skipped (no writes)',
            'company_id' => $companyId,
            'tables' => count($intersection),
        ];
    }
}
