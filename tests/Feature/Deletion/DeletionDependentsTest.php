<?php

namespace Tests\Feature\Deletion;

use App\Support\Deletion\ReferencedRecordGuard;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Reads the REAL schema back and checks the guard's dependent list
 * against it.
 *
 * This is the half that cannot be written as a fixture: the whole point
 * is that a table added months from now must not quietly reopen the
 * hole. A column that references one of the guarded master records and
 * is not declared means a delete can still strand rows.
 *
 * Runs against the development schema (SMOKE_DB, default 'cashvero'),
 * like the other smoke-style suites, and skips when it is not there.
 *
 * @see \App\Support\Deletion\ReferencedRecordGuard
 */
class DeletionDependentsTest extends TestCase
{
    /**
     * Columns that reference each guarded parent, by naming convention.
     * information_schema only knows about the ones with a real foreign
     * key — the schema has 86 of those for hundreds of references — so
     * the column names have to be matched by hand too.
     */
    private const REFERENCE_COLUMNS = [
        'partners' => ['partner_id', 'customer_id', 'supplier_id', 'shareholder_partner_id'],
        'contracts' => ['contract_id'],
        'financial_institutions' => [
            'financial_institution_id', 'from_bank_id', 'to_bank_id',
            'drawl_bank_id', 'delivery_bank_id', 'receiving_bank_id',
        ],
        'financial_institution_accounts' => [
            'financial_institution_account_id',
            'cash_cover_deducted_from_account_id',
            'lg_fees_and_commission_account_id',
        ],
    ];

    /**
     * Declared on purpose as NOT blocking, with the reason. Anything
     * else missing from the guard is a finding, not a decision.
     */
    private const DELIBERATELY_NOT_BLOCKING = [
        // A bank's own accounts are deleted along with it by
        // FinancialInstitutionController::destroy() — what blocks is
        // money having moved on one of them, which the guard checks
        // through the bridge instead.
        'financial_institutions' => [
            ['financial_institution_accounts', 'financial_institution_id'],
        ],
        // An account's interest rates are its own configuration and go
        // with it; they are not transactions.
        'financial_institution_accounts' => [
            ['account_interests', 'financial_institution_account_id'],
        ],
        // A contract's purchase / sales orders are sub-items of the
        // contract form — no screen deletes one on its own, so they go
        // with the contract. What hangs off them blocks instead, via
        // the guard's through-bridge entries.
        'contracts' => [
            ['purchase_orders', 'contract_id'],
            ['sales_orders', 'contract_id'],
        ],
        'partners' => [],
    ];

    private string $database;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = env('SMOKE_DB', 'cashvero');

        $exists = DB::select(
            'select schema_name from information_schema.schemata where schema_name = ?',
            [$this->database]
        );

        if ($exists === []) {
            $this->markTestSkipped("Development schema '{$this->database}' is not available.");
        }
    }

    /**
     * @return list<array{0:string,1:string}>
     */
    private function referencingColumns(string $parent): array
    {
        $names = self::REFERENCE_COLUMNS[$parent];
        $placeholders = implode(',', array_fill(0, count($names), '?'));

        $rows = DB::select(
            "select table_name as tbl, column_name as col
               from information_schema.columns
              where table_schema = ? and column_name in ({$placeholders}) and table_name <> ?
              order by table_name, column_name",
            array_merge([$this->database], $names, [$parent])
        );

        return array_map(fn ($r) => [$r->tbl, $r->col], $rows);
    }

    /**
     * Schema::hasColumn() would ask the TEST connection, which has none
     * of these tables — the whole point is to check the real schema.
     */
    private function columnExists(string $table, string $column): bool
    {
        return DB::select(
            'select 1 from information_schema.columns where table_schema = ? and table_name = ? and column_name = ? limit 1',
            [$this->database, $table, $column]
        ) !== [];
    }

    /**
     * @dataProvider guardedParentProvider
     */
    public function test_every_referencing_column_in_the_schema_is_accounted_for(string $parent): void
    {
        $declared = array_map(
            fn ($d) => [$d['table'], $d['column']],
            ReferencedRecordGuard::dependentsOf($parent)
        );
        $excused = self::DELIBERATELY_NOT_BLOCKING[$parent] ?? [];
        $known = array_merge($declared, $excused);

        $unaccounted = [];
        foreach ($this->referencingColumns($parent) as $reference) {
            if (! in_array($reference, $known, true)) {
                $unaccounted[] = implode('.', $reference);
            }
        }

        $this->assertSame([], $unaccounted, sprintf(
            "These columns reference %s but the delete guard does not know about them, so deleting a %s would strand them:\n  %s",
            $parent, $parent, implode("\n  ", $unaccounted)
        ));
    }

    /**
     * A typo in the dependent list is invisible at runtime — the guard
     * simply never blocks on it.
     *
     * @dataProvider guardedParentProvider
     */
    public function test_every_declared_dependent_really_exists(string $parent): void
    {
        $broken = [];

        foreach (ReferencedRecordGuard::dependentsOf($parent) as $dependent) {
            if (! $this->columnExists($dependent['table'], $dependent['column'])) {
                $broken[] = "missing {$dependent['table']}.{$dependent['column']}";
            }
            if (isset($dependent['through'])) {
                [$bridgeTable, $bridgeColumn] = $dependent['through'];
                if (! $this->columnExists($bridgeTable, $bridgeColumn)) {
                    $broken[] = "bad bridge {$bridgeTable}.{$bridgeColumn}";
                }
            }
        }

        $this->assertSame([], $broken, "Dependent list for {$parent} is out of step with the schema.");
    }

    /**
     * Every cascading foreign key is the dangerous subset — those
     * deletes happen inside MySQL, where no model event can intervene.
     *
     * @dataProvider guardedParentProvider
     */
    public function test_every_cascading_foreign_key_is_guarded(string $parent): void
    {
        $cascading = DB::select(
            'select k.table_name as tbl, k.column_name as col
               from information_schema.key_column_usage k
               join information_schema.referential_constraints r
                 on r.constraint_schema = k.constraint_schema and r.constraint_name = k.constraint_name
              where k.constraint_schema = ? and k.referenced_table_name = ? and r.delete_rule = ?',
            [$this->database, $parent, 'CASCADE']
        );

        if ($cascading === []) {
            $this->markTestSkipped("No cascading foreign keys into {$parent}.");
        }

        $declared = array_map(fn ($d) => [$d['table'], $d['column']], ReferencedRecordGuard::dependentsOf($parent));
        $excused = self::DELIBERATELY_NOT_BLOCKING[$parent] ?? [];

        $unguarded = [];
        foreach ($cascading as $fk) {
            $pair = [$fk->tbl, $fk->col];
            if (! in_array($pair, $declared, true) && ! in_array($pair, $excused, true)) {
                $unguarded[] = "{$fk->tbl}.{$fk->col}";
            }
        }

        $this->assertSame([], $unguarded, sprintf(
            "MySQL would cascade-delete these when a %s is removed, with no model event to clean up after it:\n  %s",
            $parent, implode("\n  ", $unguarded)
        ));
    }

    /**
     * Deleting a contract cascades its purchase / sales orders away
     * too, so anything referencing an ORDER is one step further down
     * the same chain and has to be accounted for as well — either as a
     * through-dependent of contracts, or as an excused sub-item.
     *
     * @dataProvider orderBridgeProvider
     */
    public function test_everything_hanging_off_a_contracts_orders_is_accounted_for(string $orderTable): void
    {
        $rows = DB::select(
            "select table_name as tbl, column_name as col
               from information_schema.columns
              where table_schema = ? and column_name in (?, ?, ?) and table_name <> ?
              order by table_name, column_name",
            [$this->database, 'sales_order_id', 'purchase_order_id', 'po_id', $orderTable]
        );

        $declared = [];
        foreach (ReferencedRecordGuard::dependentsOf('contracts') as $dependent) {
            if (($dependent['through'][0] ?? null) === $orderTable) {
                $declared[] = [$dependent['table'], $dependent['column']];
            }
        }

        $unaccounted = [];
        foreach ($rows as $row) {
            // Only rows that actually point at THIS order table matter.
            $reaches = DB::select(
                "select 1 from `{$this->database}`.`{$row->tbl}` t
                   join `{$this->database}`.`{$orderTable}` o on o.id = t.`{$row->col}` limit 1"
            );

            if ($reaches === []) {
                continue;   // nothing in this installation points here
            }

            if (! in_array([$row->tbl, $row->col], $declared, true)) {
                $unaccounted[] = "{$row->tbl}.{$row->col}";
            }
        }

        $this->assertSame([], $unaccounted, sprintf(
            "These rows hang off a contract's %s and would be stranded when the contract is deleted:\n  %s",
            $orderTable, implode("\n  ", $unaccounted)
        ));
    }

    public static function orderBridgeProvider(): array
    {
        return [
            'sales orders' => ['sales_orders'],
            'purchase orders' => ['purchase_orders'],
        ];
    }

    public static function guardedParentProvider(): array
    {
        return [
            'partners' => ['partners'],
            'contracts' => ['contracts'],
            'financial institutions' => ['financial_institutions'],
            'bank accounts' => ['financial_institution_accounts'],
        ];
    }

    /**
     * The four master records the guard is meant to cover.
     */
    public function test_the_guard_covers_the_four_master_records(): void
    {
        $this->assertSame(
            ['partners', 'contracts', 'financial_institutions', 'financial_institution_accounts'],
            ReferencedRecordGuard::guardedTables()
        );
    }

    /**
     * @dataProvider guardedControllerProvider
     */
    public function test_the_delete_action_asks_the_guard_first(string $controller, string $variable): void
    {
        $source = file_get_contents(app_path("Http/Controllers/{$controller}.php"));

        $guardAt = strpos($source, "\${$variable}->deletionBlockedMessage()");
        $this->assertNotFalse($guardAt, "{$controller} deletes without asking the guard.");

        $deleteAt = strpos($source, "\${$variable}->delete()", $guardAt);
        $accountsAt = strpos($source, '->accounts->each', $guardAt);
        $firstDestructive = min(array_filter([$deleteAt, $accountsAt], fn ($p) => $p !== false) ?: [PHP_INT_MAX]);

        $this->assertLessThan($firstDestructive, $guardAt, "{$controller} runs the guard after it has already started deleting.");
        $this->assertStringContainsString("with('fail'", substr($source, $guardAt, $firstDestructive - $guardAt),
            "{$controller} does not return early when the guard blocks.");
    }

    public static function guardedControllerProvider(): array
    {
        return [
            'partners' => ['PartnersController', 'partner'],
            'contracts' => ['ContractsController', 'contract'],
            'financial institutions' => ['FinancialInstitutionController', 'financialInstitution'],
            'bank accounts' => ['FinancialInstitutionAccountController', 'financialInstitutionAccount'],
        ];
    }
}
