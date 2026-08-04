<?php

namespace App\Services\CompanyData;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Copies one company's CashVero rows from the local mysql_source DB
 * (system.veroanalysis.com / veroanalysis) into the default DB (cash-vero).
 *
 * Primary keys (except companies.id) are reassigned via auto-increment;
 * foreign keys are rewritten so relationships stay intact. company_id stays
 * the same on both sides.
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

    /**
     * Explicit FK column → referenced table when heuristic would guess wrong.
     *
     * @var array<string, string>
     */
    private const FK_COLUMN_MAP = [
        'invoice_id' => 'customer_invoices',
        'branch_id' => 'branch',
        'user_id' => 'users',
        'creator_id' => 'users',
        'created_by' => 'users',
        'updated_by' => 'users',
        'approved_by' => 'users',
    ];

    /**
     * Morph class → table for media.model_id remapping.
     *
     * @var array<string, string>
     */
    private const MORPH_TABLES = [
        'App\\Models\\Company' => 'companies',
        'App\\Models\\Partner' => 'partners',
        'App\\Models\\Contract' => 'contracts',
        'App\\Models\\MoneyReceived' => 'money_received',
        'App\\Models\\MoneyPayment' => 'money_payments',
        'App\\Models\\CashExpense' => 'cash_expenses',
        'App\\Models\\FinancialInstitution' => 'financial_institutions',
        'App\\Models\\FinancialInstitutionAccount' => 'financial_institution_accounts',
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

    /** @var array<string, array<int|string, int|string>> */
    private array $idMaps = [];

    /** @var array<string, array<string, string>> column => referenced table, keyed by table */
    private array $fkCache = [];

    /** @var array<string, bool> */
    private array $knownTables = [];

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
        $this->idMaps = [];
        $this->fkCache = [];
        $this->knownTables = [];

        $analysis = $this->analyze($companyId);
        $report = [
            'analysis' => $analysis,
            'dry_run' => $dryRun,
            'copied' => [],
            'wiped' => [],
            'indirect' => [],
            'users' => [],
            'id_maps' => [],
            'verification' => null,
            'errors' => [],
        ];

        if (! $analysis['source_company']) {
            $report['errors'][] = "Company {$companyId} not found on source DB {$analysis['source_db']}.";
            $report['ok'] = false;

            return ['ok' => false, 'report' => $report];
        }

        // Collisions are informational only — PKs are remapped on insert.
        if ($dryRun) {
            $report['verification'] = $this->buildExpectedVerification($companyId, $analysis['intersection']);
            $report['ok'] = true;

            return ['ok' => true, 'report' => $report];
        }

        $tables = $this->orderedTables($analysis['intersection']);
        $this->primeKnownTables($tables);

        // company_id itself is identity — map companies.id → same id.
        $this->idMaps['companies'][$companyId] = $companyId;

        $suspendedTriggers = [];
        $importException = null;
        try {
            DB::connection($this->target)->statement('SET FOREIGN_KEY_CHECKS=0');
            // Statement BEFORE INSERT/UPDATE triggers recalculate beginning/end balances
            // from prior rows. During bulk remapped import that corrupts Cash & Banks totals —
            // suspend them and restore source balances as-is.
            $suspendedTriggers = $this->suspendBalanceCalculationTriggers();
            $report['triggers_suspended'] = count($suspendedTriggers);

            $this->wipeTargetCompany($companyId, $tables, $report);
            $this->upsertCompanyRow($companyId, $report);
            $this->copyCompanyIdTables($companyId, $tables, $report);
            $this->copyUsersAndMembership($companyId, $report);
            $this->copyIndirectTables($companyId, $report);
            $this->copyCompanyMedia($companyId, $report);

            DB::connection($this->target)->statement('SET FOREIGN_KEY_CHECKS=1');
        } catch (Throwable $e) {
            $importException = $e;
            try {
                DB::connection($this->target)->statement('SET FOREIGN_KEY_CHECKS=1');
            } catch (Throwable) {
            }
            $report['errors'][] = $e->getMessage();
        } finally {
            try {
                $this->restoreBalanceCalculationTriggers($suspendedTriggers);
            } catch (Throwable $restoreError) {
                $report['errors'][] = 'Failed to restore statement triggers: '.$restoreError->getMessage();
            }
        }

        if ($importException) {
            $report['ok'] = false;

            return ['ok' => false, 'report' => $report];
        }

        $report['id_maps'] = $this->idMapSizes();

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
            $sourceCount = $this->selectIndirectRows(self::SOURCE, $companyId, $table, $config)->count();
            // Target parents use remapped IDs — resolve via idMaps when available, else target company parents.
            $targetCount = $this->countIndirectOnTarget($companyId, $table, $config);
            if ($sourceCount !== $targetCount) {
                $mismatches[] = [
                    'table' => $table,
                    'type' => 'indirect_count',
                    'source' => $sourceCount,
                    'target' => $targetCount,
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
        // Wipe indirect children using TARGET parent IDs (may differ from source after prior remaps).
        foreach (self::INDIRECT as $table => $config) {
            if (! Schema::connection($this->target)->hasTable($table)) {
                continue;
            }
            $deleted = $this->deleteIndirectOnTarget($companyId, $table, $config);
            $report['wiped'][$table] = $deleted;
        }

        if (Schema::connection($this->target)->hasTable('media')) {
            $report['wiped']['media'] = DB::connection($this->target)->table('media')
                ->where('model_type', 'App\\Models\\Company')
                ->where('model_id', $companyId)
                ->delete();
        }

        $pending = $tables;
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

        $this->idMaps['companies'][$companyId] = $companyId;
    }

    /**
     * @param  array<int, string>  $tables
     * @param  array<string, mixed>  $report
     */
    private function copyCompanyIdTables(int $companyId, array $tables, array &$report): void
    {
        foreach ($tables as $table) {
            if ($table === 'companies' || $table === 'companies_users' || in_array($table, self::SKIP_COPY, true)) {
                continue;
            }
            if (! Schema::connection($this->target)->hasColumn($table, 'company_id')) {
                continue;
            }

            $inserted = 0;
            $orderCol = $this->primaryKeyColumn($table, self::SOURCE) ?? 'id';
            $query = DB::connection(self::SOURCE)->table($table)->where('company_id', $companyId);
            // Statement "latest" KPIs use date/id (not full_date). full_date can disagree with id
            // on the same calendar day; inserting in full_date order remaps ids so the wrong
            // row becomes latest and Cash & Banks balances diverge. Always follow source id.
            if (Schema::connection(self::SOURCE)->hasColumn($table, $orderCol)) {
                $query->orderBy($orderCol);
            }

            $query->chunk(200, function ($rows) use ($table, $orderCol, &$inserted) {
                foreach ($rows as $row) {
                    $this->insertRemappedRow($table, (array) $row, $orderCol);
                    $inserted++;
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

            if (isset($this->idMaps['users'][$sourceUserId])) {
                $targetUserId = (int) $this->idMaps['users'][$sourceUserId];
            } else {
                $byEmail = null;
                if (! empty($sourceUser->email)) {
                    $byEmail = DB::connection($this->target)->table('users')
                        ->where('email', $sourceUser->email)
                        ->first();
                }
                if ($byEmail) {
                    $targetUserId = (int) $byEmail->id;
                    $this->idMaps['users'][$sourceUserId] = $targetUserId;
                    $remappedByEmail++;
                } else {
                    $payload = $this->filterColumns('users', (array) $sourceUser);
                    unset($payload['id']);
                    $targetUserId = (int) DB::connection($this->target)->table('users')->insertGetId($payload);
                    $this->idMaps['users'][$sourceUserId] = $targetUserId;
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
            $pk = $this->primaryKeyColumn($table, self::SOURCE) ?? 'id';
            $inserted = 0;

            foreach ($rows as $row) {
                $this->insertRemappedRow($table, (array) $row, $pk, remapCompanyId: false);
                $inserted++;
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

        $pk = $this->primaryKeyColumn('media', $this->target) ?? 'id';
        $inserted = 0;

        foreach ($rows as $row) {
            $payload = $this->filterColumns('media', (array) $row);
            $oldId = $payload[$pk] ?? null;
            unset($payload[$pk]);

            // Company morph keeps company id.
            $payload['model_id'] = $companyId;

            $newId = (int) DB::connection($this->target)->table('media')->insertGetId($payload);
            if ($oldId !== null) {
                $this->idMaps['media'][$oldId] = $newId;
            }
            $inserted++;
        }
        $report['indirect']['media'] = $inserted;
    }

    /**
     * Insert one row with remapped FKs and a new auto-increment PK.
     *
     * @param  array<string, mixed>  $row
     */
    private function insertRemappedRow(string $table, array $row, string $pk, bool $remapCompanyId = true): int|string
    {
        $payload = $this->filterColumns($table, $row);
        $oldId = $payload[$pk] ?? null;
        unset($payload[$pk]);

        $payload = $this->remapForeignKeys($table, $payload);

        if ($remapCompanyId && array_key_exists('company_id', $payload) && isset($this->idMaps['companies'][$payload['company_id']])) {
            // company_id identity — already same value; no-op kept for clarity
            $payload['company_id'] = $this->idMaps['companies'][$payload['company_id']];
        }

        if ($pk === 'id' && Schema::connection($this->target)->hasColumn($table, 'id')) {
            $newId = DB::connection($this->target)->table($table)->insertGetId($payload);
        } else {
            DB::connection($this->target)->table($table)->insert($payload);
            $newId = $oldId;
        }

        if ($oldId !== null) {
            $this->idMaps[$table][$oldId] = $newId;
        }

        return $newId;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function remapForeignKeys(string $table, array $payload): array
    {
        foreach ($this->fkColumnsFor($table) as $column => $refTable) {
            if (! array_key_exists($column, $payload) || $payload[$column] === null || $payload[$column] === '') {
                continue;
            }
            $old = $payload[$column];
            if (isset($this->idMaps[$refTable][$old])) {
                $payload[$column] = $this->idMaps[$refTable][$old];
            }
        }

        // Morph pairs: model_type + model_id (and similar)
        foreach (['model', 'statementable', 'commentable', 'taggable'] as $morph) {
            $typeCol = $morph.'_type';
            $idCol = $morph.'_id';
            if (! isset($payload[$typeCol], $payload[$idCol]) || $payload[$idCol] === null) {
                continue;
            }
            $type = (string) $payload[$typeCol];
            if ($type === 'App\\Models\\Company') {
                continue;
            }
            $refTable = self::MORPH_TABLES[$type] ?? null;
            if ($refTable && isset($this->idMaps[$refTable][$payload[$idCol]])) {
                $payload[$idCol] = $this->idMaps[$refTable][$payload[$idCol]];
            }
        }

        return $payload;
    }

    /**
     * @return array<string, string> column => referenced table
     */
    private function fkColumnsFor(string $table): array
    {
        if (isset($this->fkCache[$table])) {
            return $this->fkCache[$table];
        }

        $map = [];

        // Formal FKs from information_schema (source DB).
        $database = DB::connection(self::SOURCE)->getDatabaseName();
        $fks = DB::connection(self::SOURCE)->table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', $database)
            ->where('TABLE_NAME', $table)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->get(['COLUMN_NAME', 'REFERENCED_TABLE_NAME']);

        foreach ($fks as $fk) {
            $col = $fk->COLUMN_NAME ?? $fk->column_name ?? null;
            $ref = $fk->REFERENCED_TABLE_NAME ?? $fk->referenced_table_name ?? null;
            if ($col && $ref && $col !== 'company_id') {
                $map[$col] = $ref;
            }
        }

        // Heuristic + explicit overrides for columns ending in _id.
        if (Schema::connection($this->target)->hasTable($table)) {
            foreach (Schema::connection($this->target)->getColumnListing($table) as $column) {
                if ($column === 'id' || $column === 'company_id' || ! str_ends_with($column, '_id')) {
                    continue;
                }
                if (isset($map[$column])) {
                    continue;
                }
                $ref = $this->guessReferencedTable($column);
                if ($ref) {
                    $map[$column] = $ref;
                }
            }
        }

        return $this->fkCache[$table] = $map;
    }

    private function guessReferencedTable(string $column): ?string
    {
        if (isset(self::FK_COLUMN_MAP[$column])) {
            $mapped = self::FK_COLUMN_MAP[$column];

            return $this->tableExists($mapped) ? $mapped : null;
        }

        $base = substr($column, 0, -3); // strip _id
        $candidates = [
            $base,
            Str::plural($base),
            Str::snake(Str::plural(Str::studly($base))),
        ];

        // cash_vero_* style
        if (! str_starts_with($base, 'cash_vero_')) {
            $candidates[] = 'cash_vero_'.Str::plural($base);
        }

        foreach (array_unique($candidates) as $candidate) {
            if ($this->tableExists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function tableExists(string $table): bool
    {
        if (isset($this->knownTables[$table])) {
            return $this->knownTables[$table];
        }

        return $this->knownTables[$table] = Schema::connection($this->target)->hasTable($table);
    }

    /**
     * @param  array<int, string>  $tables
     */
    private function primeKnownTables(array $tables): void
    {
        foreach ($tables as $table) {
            $this->knownTables[$table] = true;
        }
        $this->knownTables['companies'] = true;
        $this->knownTables['users'] = true;
        $this->knownTables['media'] = true;
        foreach (array_keys(self::INDIRECT) as $table) {
            $this->knownTables[$table] = Schema::connection($this->target)->hasTable($table);
        }
    }

    /**
     * @return array<string, int>
     */
    private function idMapSizes(): array
    {
        $sizes = [];
        foreach ($this->idMaps as $table => $map) {
            $sizes[$table] = count($map);
        }
        ksort($sizes);

        return $sizes;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function deleteIndirectOnTarget(int $companyId, string $table, array $config): int
    {
        $query = DB::connection($this->target)->table($table);
        $this->applyIndirectConstraintsFromConnection($query, $companyId, $config, $this->target);

        return $query->delete();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function countIndirectOnTarget(int $companyId, string $table, array $config): int
    {
        $query = DB::connection($this->target)->table($table);
        $this->applyIndirectConstraintsFromConnection($query, $companyId, $config, $this->target);

        return $query->count();
    }

    /**
     * @param  array<string, mixed>  $config
     * @return \Illuminate\Support\Collection<int, \stdClass>
     */
    private function selectIndirectRows(string $connection, int $companyId, string $table, array $config)
    {
        $query = DB::connection($connection)->table($table);
        $this->applyIndirectConstraintsFromConnection($query, $companyId, $config, $connection);

        return $query->get();
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array<string, mixed>  $config
     */
    private function applyIndirectConstraintsFromConnection($query, int $companyId, array $config, string $parentConnection): void
    {
        if (isset($config['parent_table'], $config['parent_fk'])) {
            $parentIds = DB::connection($parentConnection)
                ->table($config['parent_table'])
                ->where('company_id', $companyId)
                ->pluck('id')
                ->all();

            // After remap, source select still uses source parents; for target wipe/count use target.
            // When copying, we select from SOURCE with SOURCE parents — correct.
            // When parentConnection is SOURCE but we already remapped and need target FKs for verification,
            // countIndirectOnTarget passes target connection.
            if (! count($parentIds)) {
                $query->whereRaw('1 = 0');

                return;
            }
            $query->whereIn($config['parent_fk'], $parentIds);

            return;
        }

        if (isset($config['parent_tables']) && is_array($config['parent_tables'])) {
            $query->where(function ($q) use ($config, $companyId, $parentConnection) {
                $any = false;
                foreach ($config['parent_tables'] as $parentTable => $fk) {
                    if (! Schema::connection($parentConnection)->hasTable($parentTable)) {
                        continue;
                    }
                    if (! Schema::connection($parentConnection)->hasColumn($parentTable, 'company_id')) {
                        continue;
                    }
                    $ids = DB::connection($parentConnection)->table($parentTable)
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

    private function isStatementTable(string $table): bool
    {
        return str_ends_with($table, '_statements') || str_contains($table, '_statements');
    }

    /**
     * Drop BEFORE INSERT/UPDATE triggers on statement tables so imported
     * beginning_balance / end_balance values are preserved.
     *
     * @return array<int, array{name: string, sql: string}>
     */
    private function suspendBalanceCalculationTriggers(): array
    {
        $saved = [];
        $triggers = DB::connection($this->target)->select('SHOW TRIGGERS');

        foreach ($triggers as $trigger) {
            $table = (string) ($trigger->Table ?? '');
            $timing = (string) ($trigger->Timing ?? '');
            $event = (string) ($trigger->Event ?? '');
            $name = (string) ($trigger->Trigger ?? '');

            if ($timing !== 'BEFORE' || ! in_array($event, ['INSERT', 'UPDATE'], true)) {
                continue;
            }
            if (! $this->isStatementTable($table)) {
                continue;
            }
            if ($name === '') {
                continue;
            }

            $createRows = DB::connection($this->target)->select('SHOW CREATE TRIGGER `'.str_replace('`', '``', $name).'`');
            if (! count($createRows)) {
                continue;
            }
            $createRow = (array) $createRows[0];
            $sql = $createRow['SQL Original Statement']
                ?? $createRow['sql_original_statement']
                ?? null;
            if (! is_string($sql) || $sql === '') {
                // Fallback: some drivers expose differently cased keys
                foreach ($createRow as $value) {
                    if (is_string($value) && str_starts_with(strtoupper(ltrim($value)), 'CREATE')) {
                        $sql = $value;
                        break;
                    }
                }
            }
            if (! is_string($sql) || $sql === '') {
                throw new \RuntimeException("Could not capture definition for trigger {$name}");
            }

            // App DB users often cannot recreate triggers with the original DEFINER.
            $sql = preg_replace('/DEFINER=`[^`]+`@`[^`]+`\s*/i', '', $sql) ?? $sql;

            $saved[] = ['name' => $name, 'sql' => $sql];
            DB::connection($this->target)->unprepared('DROP TRIGGER IF EXISTS `'.str_replace('`', '``', $name).'`');
        }

        return $saved;
    }

    /**
     * @param  array<int, array{name: string, sql: string}>  $saved
     */
    private function restoreBalanceCalculationTriggers(array $saved): void
    {
        foreach ($saved as $item) {
            $name = $item['name'];
            $sql = $item['sql'];
            DB::connection($this->target)->unprepared('DROP TRIGGER IF EXISTS `'.str_replace('`', '``', $name).'`');
            DB::connection($this->target)->unprepared($sql);
        }
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
            'note' => 'dry-run: verification skipped (no writes); PKs will be remapped on --force',
            'company_id' => $companyId,
            'tables' => count($intersection),
        ];
    }
}
