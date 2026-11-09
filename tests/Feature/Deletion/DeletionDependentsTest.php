<?php

namespace Tests\Feature\Deletion;

use App\Support\Deletion\ReferencedRecordGuard;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
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
 * Reads the schema of the test database the suite is connected to
 * (cashvero_test), which is built from this project's migrations — the
 * same tables and foreign keys as production, with none of its rows.
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

    /** The company contractWithOrders() made; the rows pointAt() plants belong to it. */
    private ?int $ordersCompanyId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = DB::connection()->getDatabaseName();
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

    /** Straight from information_schema, like the queries above. */
    private function columnExists(string $table, string $column): bool
    {
        return DB::select(
            'select 1 from information_schema.columns where table_schema = ? and table_name = ? and column_name = ? limit 1',
            [$this->database, $table, $column]
        ) !== [];
    }

    #[DataProvider('guardedParentProvider')]
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
     */
    #[DataProvider('guardedParentProvider')]
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
     */
    #[DataProvider('guardedParentProvider')]
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

        // No cascading key into this parent at all is a pass: nothing can be deleted behind the guard's back.
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
     * Which order table each order-referencing column really holds an id
     * of, as the app writes it — the name does not say (an LG's
     * `purchase_order_id` holds a SALES order):
     *
     *   down_payment_settlements.sales_order_id                → FK to sales_orders
     *   down_payment_money_payment_settlements.purchase_order_id → FK to purchase_orders
     *   letter_of_guarantee_issuances.purchase_order_id         → FK to sales_orders
     *   letter_of_credit_issuances.purchase_order_id            → LetterOfCreditIssuance::purchaseOrder(),
     *                                                             and the LC form lists PurchaseOrder rows
     *   po_allocations.purchase_order_id                        → PurchaseOrder::allocations()
     */
    private const ORDER_LINKS = [
        ['down_payment_settlements', 'sales_order_id', 'sales_orders'],
        ['down_payment_money_payment_settlements', 'purchase_order_id', 'purchase_orders'],
        ['letter_of_guarantee_issuances', 'purchase_order_id', 'sales_orders'],
        ['letter_of_credit_issuances', 'purchase_order_id', 'purchase_orders'],
        ['po_allocations', 'purchase_order_id', 'purchase_orders'],
    ];

    /**
     * Deleting a contract cascades its purchase / sales orders away
     * too, so anything referencing an ORDER is one step further down
     * the same chain and has to be accounted for as well — either as a
     * through-dependent of contracts, or as an excused sub-item.
     *
     * Which order table a column points at is read from the rows. A
     * contract of this test's own gets one sales and one purchase order,
     * and every order-referencing table a row pointing at the order the
     * app links it to (ORDER_LINKS).
     */
    #[DataProvider('orderBridgeProvider')]
    public function test_everything_hanging_off_a_contracts_orders_is_accounted_for(string $orderTable): void
    {
        $orders = $this->contractWithOrders();

        foreach (self::ORDER_LINKS as [$table, $column, $linkedOrderTable]) {
            $this->pointAt($table, $column, $orders[$linkedOrderTable]);
        }

        $unaccounted = $this->unaccountedOrderReferences($orderTable, $orders[$orderTable]);

        $this->assertSame([], $unaccounted, sprintf(
            "These rows hang off a contract's %s and would be stranded when the contract is deleted:\n  %s",
            $orderTable, implode("\n  ", $unaccounted)
        ));
    }

    /** Every order-referencing column of the schema is listed in ORDER_LINKS, so none goes unchecked. */
    public function test_every_order_referencing_column_has_a_known_link(): void
    {
        $columns = array_map(fn ($r) => [$r->tbl, $r->col], DB::select(
            "select table_name as tbl, column_name as col
               from information_schema.columns
              where table_schema = ? and column_name in (?, ?, ?)
              order by table_name, column_name",
            [$this->database, 'sales_order_id', 'purchase_order_id', 'po_id']
        ));

        $known = array_map(fn ($link) => [$link[0], $link[1]], self::ORDER_LINKS);
        sort($known);

        $this->assertSame($known, $columns, 'An order-referencing column was added or removed — say in ORDER_LINKS which order it holds.');
    }

    /**
     * The check above must catch a row the guard does not know about: a
     * row pointing at a SALES order through a column the guard only
     * follows through purchase orders. po_allocations.purchase_order_id
     * has no foreign key, so nothing in MySQL stops such a row.
     */
    public function test_a_row_hanging_off_an_undeclared_order_link_is_caught(): void
    {
        $orders = $this->contractWithOrders();

        $this->pointAt('po_allocations', 'purchase_order_id', $orders['sales_orders']);

        $this->assertSame(
            ['po_allocations.purchase_order_id'],
            $this->unaccountedOrderReferences('sales_orders', $orders['sales_orders'])
        );
    }

    /**
     * The behaviour itself: a supplier contract whose purchase order
     * carries a Letter of Credit must refuse to be deleted — the LC's
     * purchase_order_id holds the purchase order, which goes with the
     * contract.
     */
    public function test_a_contract_whose_purchase_order_carries_a_letter_of_credit_cannot_be_deleted(): void
    {
        $orders = $this->contractWithOrders();
        $contract = \App\Models\Contract::findOrFail(DB::table('purchase_orders')->where('id', $orders['purchase_orders'])->value('contract_id'));

        $this->assertNull($contract->deletionBlockedMessage(), 'A contract with nothing hanging off it may be deleted.');

        $this->pointAt('letter_of_credit_issuances', 'purchase_order_id', $orders['purchase_orders']);

        $message = $contract->fresh()->deletionBlockedMessage();

        $this->assertNotNull($message, 'The Letter of Credit on its purchase order must block deleting the contract.');
        $this->assertStringContainsString('Letters of Credit', $message);
    }

    /** @return list<string> "table.column" of every row pointing at $orderId that the guard does not declare */
    private function unaccountedOrderReferences(string $orderTable, int $orderId): array
    {
        $rows = DB::select(
            "select table_name as tbl, column_name as col
               from information_schema.columns
              where table_schema = ? and column_name in (?, ?, ?) and table_name <> ?
              order by table_name, column_name",
            [$this->database, 'sales_order_id', 'purchase_order_id', 'po_id', $orderTable]
        );

        $this->assertNotSame([], $rows, 'The schema has no order-referencing columns to check.');

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
                   join `{$this->database}`.`{$orderTable}` o on o.id = t.`{$row->col}`
                  where o.id = ? limit 1",
                [$orderId]
            );

            if ($reaches === []) {
                continue;   // nothing points here
            }

            if (! in_array([$row->tbl, $row->col], $declared, true)) {
                $unaccounted[] = "{$row->tbl}.{$row->col}";
            }
        }

        return $unaccounted;
    }

    /**
     * A customer contract with one sales order and one purchase order whose ids differ, so a row
     * pointing at one can never be mistaken for a row pointing at the other.
     *
     * @return array{sales_orders: int, purchase_orders: int}
     */
    private function contractWithOrders(): array
    {
        $company = $this->makeCompany();
        $this->ordersCompanyId = $company->id;
        $contractId = DB::table('contracts')->insertGetId([
            'company_id' => $company->id,
            'name' => 'Guarded Contract',
            'model_type' => 'Customer',
            'currency' => 'EGP',
            'exchange_rate' => 1,
            'amount' => 1000,
        ]);

        $order = fn (string $table) => (int) DB::table($table)->insertGetId([
            'company_id' => $company->id,
            'contract_id' => $contractId,
            'amount' => 1000,
        ]);

        $purchaseOrderId = $order('purchase_orders');
        do {
            $salesOrderId = $order('sales_orders');
        } while ($salesOrderId === $purchaseOrderId);

        return ['sales_orders' => $salesOrderId, 'purchase_orders' => $purchaseOrderId];
    }

    /** One row in $table whose $column holds $orderId (the other columns take their defaults). */
    private function pointAt(string $table, string $column, int $orderId): void
    {
        $row = [$column => $orderId];

        if ($this->columnExists($table, 'company_id')) {
            $row['company_id'] = $this->ordersCompanyId;
        }

        DB::table($table)->insert($row);
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
    /**
     * * الاختبارات اللي فوق بتثبت إن الحارس *يعرف* العمود . ده بيثبت إنه
     * * *بيمنع* الحذف فعلا — و ده اللي بيحمي الداتا .
     *
     * * الحالة دي حصلت بجد : جدول internal_settlements اتضاف مع ميزة
     * * التسويات الداخلية و اتنسي يتسجّل في الحارس ، فحذف الشريك كان
     * * بيسيب صف تسوية بيشاور على شريك مش موجود — و شاشة الأرصدة بتقرا
     * * التسويات بالشريك و بتنادي getName() على null .
     */
    public function test_a_partner_with_an_internal_settlement_cannot_be_deleted(): void
    {
        $company = $this->makeCompany();
        $user = $this->makeUser([], $company);

        $partner = new \App\Models\Partner;
        $partner->forceFill([
            'company_id' => $company->id,
            'name' => 'Guard Test '.uniqid(),
            'is_customer' => 1,
            'is_supplier' => 1,
        ])->save();

        // من غير أي حركة الحذف مسموح — الحارس مش بيمنع على طول
        $this->assertNull($partner->deletionBlockedMessage(),
            'شريك من غير حركات المفروض ينفع يتحذف');

        DB::table('internal_settlements')->insert([
            'company_id' => $company->id,
            'partner_id' => $partner->id,
            'currency' => 'EGP',
            'settlement_date' => '2026-01-01',
            'amount' => 1000,
            'exchange_rate' => 1,
            'amount_in_main_currency' => 1000,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $message = $partner->fresh()->deletionBlockedMessage();

        $this->assertNotNull($message, 'التسوية الداخلية المفروض تمنع الحذف');
        $this->assertStringContainsString('Internal Settlements', $message,
            'الرسالة لازم تقول للمستخدم إيه اللي ماسك الشريك');

        // و الأكشن نفسه بيرفض و الشريك بيفضل موجود
        $request = \Illuminate\Http\Request::create('/x', 'DELETE');
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => $user);
        $this->app->instance('request', $request);

        $response = app(\App\Http\Controllers\PartnersController::class)
            ->destroy($company, $partner->fresh());

        $this->assertNotNull($response->getSession()->get('fail'),
            'الكونترولر لازم يرفض الحذف');
        $this->assertNotNull(\App\Models\Partner::find($partner->id),
            'الشريك اتحذف رغم إن عليه تسوية');
    }

    public function test_the_guard_covers_the_four_master_records(): void
    {
        $this->assertSame(
            ['partners', 'contracts', 'financial_institutions', 'financial_institution_accounts'],
            ReferencedRecordGuard::guardedTables()
        );
    }

    #[DataProvider('guardedControllerProvider')]
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
