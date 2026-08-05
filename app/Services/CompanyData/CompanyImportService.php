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
     * Do NOT put ambiguous names (invoice_id) here — use FK_COLUMN_MAP_BY_TABLE.
     *
     * @var array<string, string>
     */
    private const FK_COLUMN_MAP = [
        'customer_id' => 'partners',
        'supplier_id' => 'partners',
        'branch_id' => 'branch',
        'delivery_branch_id' => 'branch',
        'receiving_branch_id' => 'branch',
        'from_branch_id' => 'branch',
        'to_branch_id' => 'branch',
        'user_id' => 'users',
        'creator_id' => 'users',
        'created_by' => 'users',
        'updated_by' => 'users',
        'approved_by' => 'users',
        // Financial institutions (not banks.name rows) — except drawee_bank_id → banks.
        'receiving_bank_id' => 'financial_institutions',
        'delivery_bank_id' => 'financial_institutions',
        'from_bank_id' => 'financial_institutions',
        'to_bank_id' => 'financial_institutions',
        'drawl_bank_id' => 'financial_institutions',
        'drawee_bank_id' => 'banks',
        // FinancialInstitutionAccount columns whose names don't pluralize cleanly.
        'deducted_from_account_id' => 'financial_institution_accounts',
        'maturity_amount_added_to_account_id' => 'financial_institution_accounts',
        'lg_fees_and_commission_account_id' => 'financial_institution_accounts',
        'lc_fees_and_commission_account_id' => 'financial_institution_accounts',
        'payment_account_number_id' => 'financial_institution_accounts',
        'certificate_of_deposit_id' => 'certificates_of_deposits',
        'lg_facility_id' => 'letter_of_guarantee_facilities',
        'lc_facility_id' => 'letter_of_credit_facilities',
        'lc_issuance_id' => 'letter_of_credit_issuances',
        'lg_advanced_payment_history_id' => 'lg_issuance_advanced_payment_histories',
        'letter_of_guarantee_issuance_advanced_payment_history_id' => 'lg_issuance_advanced_payment_histories',
    ];

    /**
     * Per-table FK overrides when the same column name points at different parents.
     *
     * @var array<string, array<string, string>>
     */
    private const FK_COLUMN_MAP_BY_TABLE = [
        'supplier_invoices' => [
            'opening_balance_id' => 'supplier_opening_balances',
            'supplier_id' => 'partners',
        ],
        'customer_invoices' => [
            'opening_balance_id' => 'customer_opening_balances',
            'customer_id' => 'partners',
        ],
        'money_payments' => [
            'advanced_opening_balance_id' => 'supplier_opening_balances',
            'opening_balance_id' => 'opening_balances',
        ],
        'money_received' => [
            'advanced_opening_balance_id' => 'customer_opening_balances',
            'opening_balance_id' => 'opening_balances',
        ],
        'settlements' => [
            'invoice_id' => 'customer_invoices',
        ],
        'payment_settlements' => [
            'invoice_id' => 'supplier_invoices',
        ],
        'settlement_allocations' => [
            'invoice_id' => 'supplier_invoices',
        ],
        'contracts' => [
            'parent_id' => 'contracts',
            'overdraft_against_assignment_of_contract_id' => 'overdraft_against_assignment_of_contracts',
        ],
        'down_payment_settlements' => [
            'customer_id' => 'partners',
        ],
        'down_payment_money_payment_settlements' => [
            'supplier_id' => 'partners',
        ],
        'factoring_transactions' => [
            'customer_id' => 'partners',
        ],
        'lending_information_against_assignment_of_contracts' => [
            'customer_id' => 'partners',
        ],
        'letter_of_guarantee_statements' => [
            'lg_facility_id' => 'letter_of_guarantee_facilities',
            'lg_advanced_payment_history_id' => 'lg_issuance_advanced_payment_histories',
        ],
        'letter_of_guarantee_cash_cover_statements' => [
            'lg_facility_id' => 'letter_of_guarantee_facilities',
            'lg_advanced_payment_history_id' => 'lg_issuance_advanced_payment_histories',
        ],
        'letter_of_credit_statements' => [
            'lc_facility_id' => 'letter_of_credit_facilities',
        ],
        'letter_of_credit_cash_cover_statements' => [
            'lc_facility_id' => 'letter_of_credit_facilities',
        ],
        'lc_issuance_expenses' => [
            'lc_issuance_id' => 'letter_of_credit_issuances',
        ],
    ];

    /**
     * Account-type polymorphic FKs: id column → type column (AccountType.id).
     * The AccountType.model_name decides which table the id points at.
     *
     * @var array<string, string>
     */
    private const POLYMORPHIC_ACCOUNT_FK = [
        'cash_cover_deducted_from_account_id' => 'cash_cover_deducted_from_account_type',
        'cd_or_td_account_id' => 'cd_or_td_account_type_id',
        'cd_or_td_id' => 'cd_or_td_account_type_id',
    ];

    /**
     * Morph class → table for model_type + model_id remapping.
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
        'App\\Models\\TimeOfDeposit' => 'time_of_deposits',
        'App\\Models\\CertificatesOfDeposit' => 'certificates_of_deposits',
        'App\\Models\\CustomerInvoice' => 'customer_invoices',
        'App\\Models\\SupplierInvoice' => 'supplier_invoices',
        'Company' => 'companies',
        'Partner' => 'partners',
        'Contract' => 'contracts',
        'MoneyReceived' => 'money_received',
        'MoneyPayment' => 'money_payments',
        'CashExpense' => 'cash_expenses',
        'FinancialInstitutionAccount' => 'financial_institution_accounts',
        'TimeOfDeposit' => 'time_of_deposits',
        'CertificatesOfDeposit' => 'certificates_of_deposits',
        'CustomerInvoice' => 'customer_invoices',
        'SupplierInvoice' => 'supplier_invoices',
        'FullySecuredOverdraft' => 'fully_secured_overdrafts',
        'CleanOverdraft' => 'clean_overdrafts',
        'OverdraftAgainstCommercialPaper' => 'overdraft_against_commercial_papers',
        'OverdraftAgainstAssignmentOfContract' => 'overdraft_against_assignment_of_contracts',
    ];

    /**
     * Columns that store external (Odoo / remote) IDs — never remapped as local FKs.
     *
     * @var array<int, string>
     */
    private const EXTERNAL_ID_COLUMNS = [
        'odoo_id',
        'odoo_move_id',
        'journal_id',
        'journal_entry_id',
        'inbound_journal_entry_id',
        'outbound_journal_entry_id',
        'cancel_journal_entry_id',
        'issuance_fees_journal_entry_id',
        'commission_fees_journal_entry_id',
        'interest_journal_entry_id',
        'lg_commission_fees_journal_entry_id',
        'renewal_fees_journal_entry_id',
        'store_journal_entry_id',
        'store_break_journal_entry_id',
        'inbound_break_journal_entry_id',
        'outbound_break_journal_entry_id',
        'break_journal_entry_id',
        'renewal_journal_entry_id',
        'maturity_journal_entry_id',
        'account_bank_statement_line_id',
        'break_account_bank_statement_line_id',
        'renewal_account_bank_statement_line_id',
        'interest_account_bank_statement_line_id',
        'maturity_account_bank_statement_line_id',
        'store_account_bank_statement_line_id',
        'project_account_id', // Odoo analytic / chart id, not FI account
        'cd_or_td_account_type_id', // global account_types.id (not remapped)
        'cash_cover_deducted_from_account_type', // global account_types.id
        'deleted_id',
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
                'supplier_invoices' => 'invoice_id',
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

    /** Global reference tables that must share stable IDs between source and target. */
    private const GLOBAL_ID_TABLES = [
        'account_types',
        'banks',
    ];

    private string $target;

    /** @var array<string, array<int|string, int|string>> */
    private array $idMaps = [];

    /** @var array<string, array<string, string>> column => referenced table, keyed by table */
    private array $fkCache = [];

    /** @var array<string, bool> */
    private array $knownTables = [];

    /** @var array<int, string> Tables that had cyclic FK edges broken during ordering */
    private array $orderCycleBreaks = [];

    /** @var array<int|string, string>|null AccountType.id → model_name */
    private ?array $accountTypeModelNames = null;

    /**
     * Deferred self-FK updates: table => list of [pk, column, oldParentId].
     *
     * @var array<string, array<int, array{pk: int|string, column: string, old: int|string}>>
     */
    private array $deferredSelfFks = [];

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
        $this->orderCycleBreaks = [];
        $this->accountTypeModelNames = null;
        $this->deferredSelfFks = [];

        $analysis = $this->analyze($companyId);
        $report = [
            'analysis' => $analysis,
            'dry_run' => $dryRun,
            'copied' => [],
            'wiped' => [],
            'indirect' => [],
            'users' => [],
            'id_maps' => [],
            'order_cycle_breaks' => [],
            'import_order' => [],
            'target_only_untouched' => $analysis['target_only'],
            'verification' => null,
            'errors' => [],
            'preflight' => null,
        ];

        if (! $analysis['source_company']) {
            $report['errors'][] = "Company {$companyId} not found on source DB {$analysis['source_db']}.";
            $report['ok'] = false;

            return ['ok' => false, 'report' => $report];
        }

        // Known tables must be primed before orderedTables() — FK heuristics call tableExists().
        $this->primeKnownTables($analysis['intersection']);
        $preflight = $this->preflight($companyId, $analysis['intersection']);
        $report['preflight'] = $preflight;
        $tables = $preflight['ordered_tables'];
        $report['import_order'] = $tables;
        $report['order_cycle_breaks'] = $this->orderCycleBreaks;

        if (! $preflight['ok']) {
            $report['errors'] = array_merge($report['errors'], $preflight['errors']);
            $report['ok'] = false;

            return ['ok' => false, 'report' => $report];
        }

        if ($dryRun) {
            $report['verification'] = $this->buildExpectedVerification($companyId, $analysis['intersection']);
            $report['ok'] = true;

            return ['ok' => true, 'report' => $report];
        }

        // company_id itself is identity — map companies.id → same id.
        $this->idMaps['companies'][$companyId] = $companyId;

        $suspendedTriggers = [];
        $importException = null;
        $triggerRestoreFailed = false;
        try {
            DB::connection($this->target)->statement('SET FOREIGN_KEY_CHECKS=0');
            // Statement BEFORE INSERT/UPDATE triggers recalculate beginning/end balances
            // from prior rows. AFTER INSERT can duplicate withdrawals. BEFORE DELETE may
            // rewrite balances — suspend all of them during bulk remapped import.
            $suspendedTriggers = $this->suspendBalanceCalculationTriggers();
            $report['triggers_suspended'] = count($suspendedTriggers);

            DB::connection($this->target)->beginTransaction();
            try {
                $this->wipeTargetCompany($companyId, $tables, $report);
                $this->upsertCompanyRow($companyId, $report);
                // Users first so user_id FKs on company tables remap correctly.
                $this->copyUsersAndMembership($companyId, $report);
                $this->copyCompanyIdTables($companyId, $tables, $report);
                $this->applyDeferredSelfForeignKeys();
                $this->copyIndirectTables($companyId, $report);
                $this->copyCompanyMedia($companyId, $report);
                DB::connection($this->target)->commit();
            } catch (Throwable $e) {
                try {
                    DB::connection($this->target)->rollBack();
                } catch (Throwable) {
                }
                throw $e;
            }

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
                $triggerRestoreFailed = true;
                $report['errors'][] = 'Failed to restore statement triggers: '.$restoreError->getMessage();
            }
        }

        if ($importException || $triggerRestoreFailed) {
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
     * Full preflight: ordering, global ID parity, unmapped local IDs, cycles.
     *
     * @param  array<int, string>  $tables
     * @return array{
     *   ok: bool,
     *   errors: array<int, string>,
     *   ordered_tables: array<int, string>,
     *   order_violations: array<int, array<string, mixed>>,
     *   unmapped_local_ids: array<int, array<string, mixed>>,
     *   global_id_mismatches: array<int, array<string, mixed>>,
     *   cycle_breaks: array<int, string>
     * }
     */
    public function preflight(int $companyId, array $tables): array
    {
        $this->primeKnownTables($tables);
        $this->fkCache = [];
        $ordered = $this->orderedTables($tables);
        $errors = [];

        $violations = $this->findOrderViolations($ordered);
        if (count($violations)) {
            $errors[] = 'Import order has parent-after-child violations: '.count($violations);
        }
        if (count($this->orderCycleBreaks)) {
            $errors[] = 'Unresolved cyclic FK tables: '.implode(', ', $this->orderCycleBreaks);
        }

        $unmapped = $this->findUnmappedLocalIdColumns($tables, $companyId);
        if (count($unmapped)) {
            $errors[] = 'Unmapped local *_id columns with data: '.count($unmapped);
        }

        $globalMismatches = $this->checkGlobalIdTablesParity();
        if (count($globalMismatches)) {
            $errors[] = 'Global reference tables diverge between source and target.';
        }

        return [
            'ok' => count($errors) === 0,
            'errors' => $errors,
            'ordered_tables' => $ordered,
            'order_violations' => $violations,
            'unmapped_local_ids' => $unmapped,
            'global_id_mismatches' => $globalMismatches,
            'cycle_breaks' => $this->orderCycleBreaks,
        ];
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

        $this->primeKnownTables($intersection);
        $this->fkCache = [];

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

        $fkIntegrity = $this->checkForeignKeyIntegrity($companyId, $intersection);
        $checks += $fkIntegrity['checks'];
        foreach ($fkIntegrity['orphans'] as $orphan) {
            $targetOrphans = (int) ($orphan['target_orphans'] ?? 0);
            $sourceOrphans = (int) ($orphan['source_orphans'] ?? 0);
            $targetCross = (int) ($orphan['target_cross_company'] ?? 0);
            $sourceCross = (int) ($orphan['source_cross_company'] ?? 0);
            // Cross-company refs on source become plain orphans on target after the other
            // company's PKs were remapped — compare combined broken counts.
            $targetBroken = $targetOrphans + $targetCross;
            $sourceBroken = $sourceOrphans + $sourceCross;
            if ($targetBroken > $sourceBroken) {
                $mismatches[] = array_merge(['type' => 'fk_orphan'], $orphan);
            }
        }

        $ordered = $this->orderedTables($intersection);
        $orderViolations = $this->findOrderViolations($ordered);
        $checks++;
        if (count($orderViolations) || count($this->orderCycleBreaks)) {
            $mismatches[] = [
                'type' => 'import_order',
                'order_violations' => $orderViolations,
                'cycle_breaks' => $this->orderCycleBreaks,
            ];
        }

        $unmapped = $this->findUnmappedLocalIdColumns($intersection, $companyId);
        $checks++;
        if (count($unmapped)) {
            $mismatches[] = [
                'type' => 'unmapped_local_ids',
                'columns' => $unmapped,
            ];
        }

        $globalMismatches = $this->checkGlobalIdTablesParity();
        $checks++;
        if (count($globalMismatches)) {
            $mismatches[] = [
                'type' => 'global_id_parity',
                'mismatches' => $globalMismatches,
            ];
        }

        return [
            'ok' => count($mismatches) === 0,
            'mismatches' => $mismatches,
            'checks' => $checks,
            'fk_integrity' => $fkIntegrity,
        ];
    }

    /**
     * Count orphaned FK rows on target (and source for comparison) for one company.
     *
     * @param  array<int, string>|null  $tables
     * @return array{
     *   ok: bool,
     *   checks: int,
     *   orphans: array<int, array{
     *     table: string,
     *     column: string,
     *     parent: string,
     *     total: int,
     *     target_orphans: int,
     *     source_orphans: int,
     *     target_cross_company: int
     *   }>
     * }
     */
    public function checkForeignKeyIntegrity(int $companyId, ?array $tables = null): array
    {
        $tables = $tables ?? array_values(array_intersect(
            $this->companyIdTables(self::SOURCE),
            $this->companyIdTables($this->target)
        ));

        $this->primeKnownTables($tables);
        $this->fkCache = [];

        $orphans = [];
        $checks = 0;

        foreach ($tables as $table) {
            if (in_array($table, self::SKIP_COPY, true) || $table === 'companies') {
                continue;
            }
            if (! Schema::connection($this->target)->hasTable($table)
                || ! Schema::connection($this->target)->hasColumn($table, 'company_id')) {
                continue;
            }

            foreach ($this->fkColumnsFor($table) as $column => $parent) {
                if (! Schema::connection($this->target)->hasTable($parent)
                    || ! Schema::connection($this->target)->hasColumn($parent, 'id')) {
                    continue;
                }
                if (! Schema::connection($this->target)->hasColumn($table, $column)) {
                    continue;
                }

                $checks++;
                $targetStats = $this->countOrphanFkStats($this->target, $table, $column, $parent, $companyId);
                $sourceStats = ['orphans' => 0, 'cross_company' => 0];
                if (Schema::connection(self::SOURCE)->hasTable($table)
                    && Schema::connection(self::SOURCE)->hasColumn($table, $column)
                    && Schema::connection(self::SOURCE)->hasTable($parent)) {
                    $sourceStats = $this->countOrphanFkStats(self::SOURCE, $table, $column, $parent, $companyId);
                }

                if ($targetStats['orphans'] > 0 || $sourceStats['orphans'] > 0
                    || $targetStats['cross_company'] > 0 || $sourceStats['cross_company'] > 0) {
                    $orphans[] = [
                        'table' => $table,
                        'column' => $column,
                        'parent' => $parent,
                        'total' => $targetStats['total'],
                        'target_orphans' => $targetStats['orphans'],
                        'source_orphans' => $sourceStats['orphans'],
                        'target_cross_company' => $targetStats['cross_company'],
                        'source_cross_company' => $sourceStats['cross_company'],
                    ];
                }
            }

            // Polymorphic account FKs (not covered by fkColumnsFor when type decides parent).
            foreach (self::POLYMORPHIC_ACCOUNT_FK as $idCol => $typeCol) {
                if (! Schema::connection($this->target)->hasColumn($table, $idCol)) {
                    continue;
                }
                $hasTypeCol = Schema::connection($this->target)->hasColumn($table, $typeCol);
                $checks++;
                if ($hasTypeCol) {
                    $poly = $this->countPolymorphicOrphans($this->target, $table, $idCol, $typeCol, $companyId);
                    $sourcePoly = 0;
                    if (Schema::connection(self::SOURCE)->hasTable($table)
                        && Schema::connection(self::SOURCE)->hasColumn($table, $idCol)) {
                        $sourcePoly = $this->countPolymorphicOrphans(self::SOURCE, $table, $idCol, $typeCol, $companyId)['orphans'];
                    }
                } elseif ($idCol === 'cd_or_td_id') {
                    $poly = $this->countCdOrTdOrphans($this->target, $table, $companyId);
                    $sourcePoly = Schema::connection(self::SOURCE)->hasTable($table)
                        && Schema::connection(self::SOURCE)->hasColumn($table, $idCol)
                        ? $this->countCdOrTdOrphans(self::SOURCE, $table, $companyId)['orphans']
                        : 0;
                } else {
                    continue;
                }
                if ($poly['orphans'] > 0 || $sourcePoly > 0) {
                    $orphans[] = [
                        'table' => $table,
                        'column' => $idCol,
                        'parent' => $hasTypeCol ? 'polymorphic:'.$typeCol : 'polymorphic:cd_or_td',
                        'total' => $poly['total'],
                        'target_orphans' => $poly['orphans'],
                        'source_orphans' => $sourcePoly,
                        'target_cross_company' => 0,
                    ];
                }
            }

            // invoice_deductions uses invoice_type + invoice_id
            if ($table === 'invoice_deductions'
                && Schema::connection($this->target)->hasColumn($table, 'invoice_id')
                && Schema::connection($this->target)->hasColumn($table, 'invoice_type')) {
                $checks++;
                $poly = $this->countInvoiceTypeOrphans($this->target, $companyId);
                $sourcePoly = Schema::connection(self::SOURCE)->hasTable($table)
                    ? $this->countInvoiceTypeOrphans(self::SOURCE, $companyId)['orphans']
                    : 0;
                if ($poly['orphans'] > 0 || $sourcePoly > 0) {
                    $orphans[] = [
                        'table' => $table,
                        'column' => 'invoice_id',
                        'parent' => 'polymorphic:invoice_type',
                        'total' => $poly['total'],
                        'target_orphans' => $poly['orphans'],
                        'source_orphans' => $sourcePoly,
                        'target_cross_company' => 0,
                    ];
                }
            }
        }

        return [
            'ok' => collect($orphans)->every(function ($o) {
                $targetBroken = ((int) ($o['target_orphans'] ?? 0)) + ((int) ($o['target_cross_company'] ?? 0));
                $sourceBroken = ((int) ($o['source_orphans'] ?? 0)) + ((int) ($o['source_cross_company'] ?? 0));

                return $targetBroken <= $sourceBroken;
            }),
            'checks' => $checks,
            'orphans' => $orphans,
        ];
    }

    /**
     * @return array{total: int, orphans: int, cross_company: int}
     */
    private function countOrphanFkStats(string $connection, string $table, string $column, string $parent, int $companyId): array
    {
        $base = DB::connection($connection)->table($table.' as c')
            ->where('c.company_id', $companyId)
            ->whereNotNull('c.'.$column)
            ->where('c.'.$column, '!=', 0)
            ->where('c.'.$column, '!=', '');

        $total = (int) (clone $base)->count();

        $orphans = (int) (clone $base)
            ->leftJoin($parent.' as p', 'p.id', '=', 'c.'.$column)
            ->whereNull('p.id')
            ->count();

        $crossCompany = 0;
        if (Schema::connection($connection)->hasColumn($parent, 'company_id')) {
            $crossCompany = (int) (clone $base)
                ->join($parent.' as p', 'p.id', '=', 'c.'.$column)
                ->whereColumn('p.company_id', '!=', 'c.company_id')
                ->count();
        }

        return [
            'total' => $total,
            'orphans' => $orphans,
            'cross_company' => $crossCompany,
        ];
    }

    private function countOrphanFk(string $connection, string $table, string $column, string $parent, int $companyId): int
    {
        return $this->countOrphanFkStats($connection, $table, $column, $parent, $companyId)['orphans'];
    }

    /**
     * @return array{total: int, orphans: int}
     */
    private function countPolymorphicOrphans(string $connection, string $table, string $idCol, string $typeCol, int $companyId): array
    {
        $rows = DB::connection($connection)->table($table)
            ->where('company_id', $companyId)
            ->whereNotNull($idCol)
            ->where($idCol, '!=', 0)
            ->where($idCol, '!=', '')
            ->get([$idCol, $typeCol]);

        $total = $rows->count();
        $orphans = 0;
        $typeMap = $this->accountTypeModelNames();

        foreach ($rows as $row) {
            $typeId = $row->{$typeCol};
            $id = $row->{$idCol};
            if ($this->isEmptyFk($id)) {
                continue;
            }
            $modelName = $typeMap[$typeId] ?? null;
            $parent = $modelName ? $this->tableForAccountTypeModel($modelName) : null;
            if (! $parent || ! Schema::connection($connection)->hasTable($parent)) {
                $orphans++;

                continue;
            }
            $exists = DB::connection($connection)->table($parent)->where('id', $id)->exists();
            if (! $exists) {
                $orphans++;
            }
        }

        return ['total' => $total, 'orphans' => $orphans];
    }

    /**
     * @return array{total: int, orphans: int}
     */
    private function countInvoiceTypeOrphans(string $connection, int $companyId): array
    {
        $rows = DB::connection($connection)->table('invoice_deductions')
            ->where('company_id', $companyId)
            ->whereNotNull('invoice_id')
            ->where('invoice_id', '!=', 0)
            ->get(['invoice_id', 'invoice_type']);

        $total = $rows->count();
        $orphans = 0;
        foreach ($rows as $row) {
            $parent = self::MORPH_TABLES[(string) $row->invoice_type]
                ?? self::MORPH_TABLES['App\\Models\\'.(string) $row->invoice_type]
                ?? null;
            if (! $parent || ! Schema::connection($connection)->hasTable($parent)) {
                $orphans++;

                continue;
            }
            if (! DB::connection($connection)->table($parent)->where('id', $row->invoice_id)->exists()) {
                $orphans++;
            }
        }

        return ['total' => $total, 'orphans' => $orphans];
    }

    /**
     * Resolve cd_or_td_id when no type column exists — orphan if missing from CD/TD/FIA.
     *
     * @return array{total: int, orphans: int}
     */
    private function countCdOrTdOrphans(string $connection, string $table, int $companyId): array
    {
        $rows = DB::connection($connection)->table($table)
            ->where('company_id', $companyId)
            ->whereNotNull('cd_or_td_id')
            ->where('cd_or_td_id', '!=', 0)
            ->where('cd_or_td_id', '!=', '')
            ->pluck('cd_or_td_id');

        $total = $rows->count();
        $orphans = 0;
        $parents = ['time_of_deposits', 'certificates_of_deposits', 'financial_institution_accounts'];
        foreach ($rows as $id) {
            if ($this->isEmptyFk($id)) {
                continue;
            }
            $found = false;
            foreach ($parents as $parent) {
                if (Schema::connection($connection)->hasTable($parent)
                    && DB::connection($connection)->table($parent)->where('id', $id)->exists()) {
                    $found = true;
                    break;
                }
            }
            if (! $found) {
                $orphans++;
            }
        }

        return ['total' => $total, 'orphans' => $orphans];
    }

    private function isEmptyFk(mixed $value): bool
    {
        return $value === null || $value === '' || $value === 0 || $value === '0';
    }

    /**
     * @return array<int|string, string>
     */
    private function accountTypeModelNames(): array
    {
        if ($this->accountTypeModelNames !== null) {
            return $this->accountTypeModelNames;
        }

        $connection = Schema::connection($this->target)->hasTable('account_types')
            ? $this->target
            : self::SOURCE;

        $this->accountTypeModelNames = [];
        if (Schema::connection($connection)->hasTable('account_types')) {
            foreach (DB::connection($connection)->table('account_types')->get(['id', 'model_name']) as $row) {
                $this->accountTypeModelNames[$row->id] = (string) $row->model_name;
            }
        }

        return $this->accountTypeModelNames;
    }

    private function tableForAccountTypeModel(string $modelName): ?string
    {
        $modelName = ltrim($modelName, '\\');
        if (isset(self::MORPH_TABLES[$modelName])) {
            return self::MORPH_TABLES[$modelName];
        }
        $short = class_basename($modelName);
        if (isset(self::MORPH_TABLES[$short])) {
            return self::MORPH_TABLES[$short];
        }
        $candidate = Str::snake(Str::pluralStudly($short));

        return $this->tableExists($candidate) ? $candidate : null;
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

        // Defer self-FKs (e.g. contracts.parent_id) until the whole table is copied.
        $deferred = [];
        foreach ($this->fkColumnsFor($table) as $column => $refTable) {
            if ($refTable !== $table || ! array_key_exists($column, $payload)) {
                continue;
            }
            if (! $this->isEmptyFk($payload[$column])) {
                $deferred[$column] = $payload[$column];
                $payload[$column] = null;
            }
        }

        $payload = $this->remapForeignKeys($table, $payload);

        if ($remapCompanyId && array_key_exists('company_id', $payload) && isset($this->idMaps['companies'][$payload['company_id']])) {
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

        foreach ($deferred as $column => $oldParentId) {
            $this->deferredSelfFks[$table][] = [
                'pk' => $newId,
                'column' => $column,
                'old' => $oldParentId,
            ];
        }

        return $newId;
    }

    private function applyDeferredSelfForeignKeys(): void
    {
        foreach ($this->deferredSelfFks as $table => $items) {
            $pk = $this->primaryKeyColumn($table, $this->target) ?? 'id';
            foreach ($items as $item) {
                $newParent = $this->idMaps[$table][$item['old']] ?? null;
                if ($newParent === null) {
                    continue;
                }
                DB::connection($this->target)->table($table)
                    ->where($pk, $item['pk'])
                    ->update([$item['column'] => $newParent]);
            }
        }
        $this->deferredSelfFks = [];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function remapForeignKeys(string $table, array $payload): array
    {
        foreach ($this->fkColumnsFor($table) as $column => $refTable) {
            if (! array_key_exists($column, $payload) || $this->isEmptyFk($payload[$column])) {
                continue;
            }
            if ($refTable === $table) {
                continue; // handled by deferred self-FK pass
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
            if (! isset($payload[$typeCol], $payload[$idCol]) || $this->isEmptyFk($payload[$idCol])) {
                continue;
            }
            $type = (string) $payload[$typeCol];
            if ($type === 'App\\Models\\Company' || $type === 'Company') {
                continue;
            }
            $refTable = self::MORPH_TABLES[$type] ?? self::MORPH_TABLES[class_basename($type)] ?? null;
            if ($refTable && isset($this->idMaps[$refTable][$payload[$idCol]])) {
                $payload[$idCol] = $this->idMaps[$refTable][$payload[$idCol]];
            }
        }

        // invoice_deductions.invoice_type + invoice_id
        if ($table === 'invoice_deductions'
            && isset($payload['invoice_type'], $payload['invoice_id'])
            && ! $this->isEmptyFk($payload['invoice_id'])) {
            $type = (string) $payload['invoice_type'];
            $refTable = self::MORPH_TABLES[$type] ?? self::MORPH_TABLES['App\\Models\\'.$type] ?? null;
            if ($refTable && isset($this->idMaps[$refTable][$payload['invoice_id']])) {
                $payload['invoice_id'] = $this->idMaps[$refTable][$payload['invoice_id']];
            }
        }

        // Account-type polymorphic FKs (CD/TD/current account pointed by AccountType.model_name).
        foreach (self::POLYMORPHIC_ACCOUNT_FK as $idCol => $typeCol) {
            if (! array_key_exists($idCol, $payload) || $this->isEmptyFk($payload[$idCol])) {
                continue;
            }
            if (array_key_exists($typeCol, $payload) && ! $this->isEmptyFk($payload[$typeCol])) {
                $modelName = $this->accountTypeModelNames()[$payload[$typeCol]] ?? null;
                if (! $modelName) {
                    continue;
                }
                $refTable = $this->tableForAccountTypeModel($modelName);
                if ($refTable && isset($this->idMaps[$refTable][$payload[$idCol]])) {
                    $payload[$idCol] = $this->idMaps[$refTable][$payload[$idCol]];
                }

                continue;
            }

            // Statements often store cd_or_td_id without a type column — resolve via idMaps.
            if ($idCol === 'cd_or_td_id') {
                foreach (['time_of_deposits', 'certificates_of_deposits', 'financial_institution_accounts'] as $refTable) {
                    if (isset($this->idMaps[$refTable][$payload[$idCol]])) {
                        $payload[$idCol] = $this->idMaps[$refTable][$payload[$idCol]];
                        break;
                    }
                }
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
            if ($col && $ref && $col !== 'company_id' && ! $this->isExternalIdColumn($col)) {
                $map[$col] = $ref;
            }
        }

        // Heuristic + explicit overrides for columns ending in _id.
        if (Schema::connection($this->target)->hasTable($table)) {
            foreach (Schema::connection($this->target)->getColumnListing($table) as $column) {
                if ($column === 'id' || $column === 'company_id' || ! str_ends_with($column, '_id')) {
                    continue;
                }
                if ($this->isExternalIdColumn($column)) {
                    continue;
                }
                // Polymorphic id columns are remapped separately.
                if (isset(self::POLYMORPHIC_ACCOUNT_FK[$column]) || $this->isPolymorphicIdColumn($table, $column)) {
                    continue;
                }
                $ref = $this->guessReferencedTable($column, $table);
                if ($ref) {
                    // Per-table / explicit map wins over formal FK when both exist.
                    if (isset(self::FK_COLUMN_MAP_BY_TABLE[$table][$column]) || isset(self::FK_COLUMN_MAP[$column])) {
                        $map[$column] = $ref;
                    } elseif (! isset($map[$column])) {
                        $map[$column] = $ref;
                    }
                }
            }
        }

        return $this->fkCache[$table] = $map;
    }

    private function isExternalIdColumn(string $column): bool
    {
        if (in_array($column, self::EXTERNAL_ID_COLUMNS, true)) {
            return true;
        }

        return str_contains($column, 'odoo')
            || str_ends_with($column, '_odoo_id')
            || str_ends_with($column, '_payment_method_id')
            || (str_ends_with($column, '_journal_entry_id') && ! in_array($column, ['journal_entry_id'], true))
            || str_ends_with($column, 'account_bank_statement_line_id')
            || str_ends_with($column, 'account_bank_statement_odoo_id');
    }

    /**
     * True when column is a morph/poly id paired with a sibling *_type column on the table.
     */
    private function isPolymorphicIdColumn(string $table, string $column): bool
    {
        if (! str_ends_with($column, '_id')) {
            return false;
        }
        $prefix = substr($column, 0, -3);
        $typeCol = $prefix.'_type';
        if (! Schema::connection(self::SOURCE)->hasColumn($table, $typeCol)
            && ! Schema::connection($this->target)->hasColumn($table, $typeCol)) {
            return false;
        }

        return in_array($prefix, ['model', 'statementable', 'commentable', 'taggable', 'invoice'], true)
            || $column === 'invoice_id';
    }

    private function guessReferencedTable(string $column, ?string $table = null): ?string
    {
        if ($table !== null && isset(self::FK_COLUMN_MAP_BY_TABLE[$table][$column])) {
            $mapped = self::FK_COLUMN_MAP_BY_TABLE[$table][$column];

            return $this->tableExists($mapped) ? $mapped : null;
        }

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
        $this->applyIndirectConstraintsFromConnection($query, $companyId, $table, $config, $this->target);

        return $query->delete();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function countIndirectOnTarget(int $companyId, string $table, array $config): int
    {
        $query = DB::connection($this->target)->table($table);
        $this->applyIndirectConstraintsFromConnection($query, $companyId, $table, $config, $this->target);

        return $query->count();
    }

    /**
     * @param  array<string, mixed>  $config
     * @return \Illuminate\Support\Collection<int, \stdClass>
     */
    private function selectIndirectRows(string $connection, int $companyId, string $table, array $config)
    {
        $query = DB::connection($connection)->table($table);
        $this->applyIndirectConstraintsFromConnection($query, $companyId, $table, $config, $connection);

        return $query->get();
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  array<string, mixed>  $config
     */
    private function applyIndirectConstraintsFromConnection($query, int $companyId, string $childTable, array $config, string $parentConnection): void
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
            $query->where(function ($q) use ($config, $companyId, $parentConnection, $childTable) {
                $any = false;
                foreach ($config['parent_tables'] as $parentTable => $fk) {
                    if (! Schema::connection($this->target)->hasColumn($childTable, $fk)
                        && ! Schema::connection(self::SOURCE)->hasColumn($childTable, $fk)) {
                        continue;
                    }
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
     * Parent-before-child order across ALL company tables.
     * BOOTSTRAP_ORDER is only a priority tie-break among ready nodes — it never
     * overrides a real FK dependency. Self-FKs are deferred (not edges).
     * Unresolved cycles fail preflight via $this->orderCycleBreaks.
     *
     * @param  array<int, string>  $tables
     * @return array<int, string>
     */
    private function orderedTables(array $tables): array
    {
        $this->orderCycleBreaks = [];
        $nodes = array_values(array_unique($tables));
        sort($nodes);
        $nodeSet = array_fill_keys($nodes, true);
        $bootstrapPriority = array_flip(self::BOOTSTRAP_ORDER);

        $adj = [];
        $inDegree = array_fill_keys($nodes, 0);
        foreach ($nodes as $table) {
            $adj[$table] = [];
        }

        foreach ($nodes as $table) {
            $parents = [];
            foreach ($this->fkColumnsFor($table) as $column => $parent) {
                if ($parent === $table) {
                    continue; // self-reference — deferred remap
                }
                if (! isset($nodeSet[$parent])) {
                    continue; // external / global / not in company copy set
                }
                $parents[$parent] = true;
            }

            foreach (array_keys(self::POLYMORPHIC_ACCOUNT_FK) as $idCol) {
                if (! Schema::connection($this->target)->hasColumn($table, $idCol)) {
                    continue;
                }
                foreach (['financial_institution_accounts', 'time_of_deposits', 'certificates_of_deposits'] as $parent) {
                    if (isset($nodeSet[$parent]) && $parent !== $table) {
                        $parents[$parent] = true;
                    }
                }
            }

            foreach (array_keys($parents) as $parent) {
                $adj[$parent][] = $table;
                $inDegree[$table]++;
            }
        }

        $ready = [];
        foreach ($nodes as $table) {
            if ($inDegree[$table] === 0) {
                $ready[] = $table;
            }
        }
        usort($ready, function ($a, $b) use ($bootstrapPriority) {
            $pa = $bootstrapPriority[$a] ?? 10000;
            $pb = $bootstrapPriority[$b] ?? 10000;
            if ($pa !== $pb) {
                return $pa <=> $pb;
            }

            return strcmp($a, $b);
        });

        $topo = [];
        while (count($ready)) {
            $node = array_shift($ready);
            $topo[] = $node;
            foreach ($adj[$node] as $child) {
                $inDegree[$child]--;
                if ($inDegree[$child] === 0) {
                    $ready[] = $child;
                    usort($ready, function ($a, $b) use ($bootstrapPriority) {
                        $pa = $bootstrapPriority[$a] ?? 10000;
                        $pb = $bootstrapPriority[$b] ?? 10000;
                        if ($pa !== $pb) {
                            return $pa <=> $pb;
                        }

                        return strcmp($a, $b);
                    });
                }
            }
        }

        $remaining = array_values(array_diff($nodes, $topo));
        if (count($remaining)) {
            sort($remaining);
            $this->orderCycleBreaks = $remaining;
            // Do NOT silently append — preflight will fail. Still return a full list
            // so callers can inspect, with remaining at end.
            $topo = array_merge($topo, $remaining);
        }

        return $topo;
    }

    /**
     * @param  array<int, string>  $ordered
     * @return array<int, array{child: string, column: string, parent: string, child_pos: int, parent_pos: int}>
     */
    private function findOrderViolations(array $ordered): array
    {
        $pos = array_flip($ordered);
        $violations = [];
        foreach ($ordered as $table) {
            foreach ($this->fkColumnsFor($table) as $column => $parent) {
                if ($parent === $table || ! isset($pos[$parent])) {
                    continue;
                }
                if ($pos[$parent] > $pos[$table]) {
                    $violations[] = [
                        'child' => $table,
                        'column' => $column,
                        'parent' => $parent,
                        'child_pos' => $pos[$table],
                        'parent_pos' => $pos[$parent],
                    ];
                }
            }
        }

        return $violations;
    }

    /**
     * @param  array<int, string>  $tables
     * @return array<int, array{table: string, column: string, rows: int}>
     */
    private function findUnmappedLocalIdColumns(array $tables, int $companyId): array
    {
        $unmapped = [];
        foreach ($tables as $table) {
            if (in_array($table, self::SKIP_COPY, true)) {
                continue;
            }
            if (! Schema::connection(self::SOURCE)->hasTable($table)
                || ! Schema::connection(self::SOURCE)->hasColumn($table, 'company_id')) {
                continue;
            }
            $map = $this->fkColumnsFor($table);
            foreach (Schema::connection(self::SOURCE)->getColumnListing($table) as $column) {
                if ($column === 'id' || $column === 'company_id' || ! str_ends_with($column, '_id')) {
                    continue;
                }
                if ($this->isExternalIdColumn($column) || isset($map[$column])) {
                    continue;
                }
                if (isset(self::POLYMORPHIC_ACCOUNT_FK[$column])) {
                    continue;
                }
                // Polymorphic morph pairs (model_id + model_type, invoice_id + invoice_type, …)
                if ($this->isPolymorphicIdColumn($table, $column)) {
                    continue;
                }
                // odoo_settings chart-of-account style ids
                if ($table === 'odoo_settings') {
                    continue;
                }
                $count = (int) DB::connection(self::SOURCE)->table($table)
                    ->where('company_id', $companyId)
                    ->whereNotNull($column)
                    ->where($column, '!=', 0)
                    ->where($column, '!=', '')
                    ->count();
                if ($count > 0) {
                    $unmapped[] = [
                        'table' => $table,
                        'column' => $column,
                        'rows' => $count,
                    ];
                }
            }
        }

        return $unmapped;
    }

    /**
     * @return array<int, array{table: string, detail: string}>
     */
    private function checkGlobalIdTablesParity(): array
    {
        $mismatches = [];
        foreach (self::GLOBAL_ID_TABLES as $table) {
            if (! Schema::connection(self::SOURCE)->hasTable($table)
                || ! Schema::connection($this->target)->hasTable($table)) {
                $mismatches[] = [
                    'table' => $table,
                    'detail' => 'missing on source or target',
                ];

                continue;
            }
            $sourceIds = DB::connection(self::SOURCE)->table($table)->orderBy('id')->pluck('id')->all();
            $targetIds = DB::connection($this->target)->table($table)->orderBy('id')->pluck('id')->all();
            if ($sourceIds !== $targetIds) {
                $mismatches[] = [
                    'table' => $table,
                    'detail' => sprintf(
                        'id sets differ (source=%d target=%d)',
                        count($sourceIds),
                        count($targetIds)
                    ),
                ];
            }
        }

        return $mismatches;
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
     * Drop BEFORE INSERT/UPDATE balance triggers and AFTER INSERT side-effect
     * triggers on statement tables so imported balances stay intact and child rows
     * (e.g. *_withdrawals) are not double-created by triggers + table copy.
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

            if (! $this->isStatementTable($table)) {
                continue;
            }
            $suspendBefore = $timing === 'BEFORE' && in_array($event, ['INSERT', 'UPDATE', 'DELETE'], true);
            $suspendAfterInsert = $timing === 'AFTER' && $event === 'INSERT';
            if (! $suspendBefore && ! $suspendAfterInsert) {
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
