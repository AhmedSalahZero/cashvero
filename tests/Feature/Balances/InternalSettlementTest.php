<?php

namespace Tests\Feature\Balances;

use App\Http\Controllers\BalancesController;
use App\Models\Company;
use App\Models\InternalSettlement;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Internal settlements, allocated across real invoices.
 *
 * An internal settlement offsets a partner who is both a customer and
 * a supplier against themselves: money comes off their customer
 * invoices and lands on their supplier invoices, same amount, same
 * moment.
 *
 * These tests run against the app's REAL invoice and settlement
 * triggers (loaded from app/Triggers/Cashvero) rather than a stand-in,
 * because the whole design rests on them: the controller never writes
 * a balance, it writes a settlement row and the trigger recomputes
 * net_balance. A test without the triggers would prove nothing about
 * the number that ends up on screen.
 *
 * @see \App\Models\InternalSettlement
 * @see \App\Http\Controllers\BalancesController::storeInternalSettlement()
 */
class InternalSettlementTest extends TestCase
{
    private const COMPANY = 700;

    private const DUAL = 70;           // customer AND supplier

    private const CUSTOMER_ONLY = 71;

    /** @var list<string> */
    private array $tables = [
        'settlements', 'payment_settlements', 'internal_settlements',
        'customer_invoices', 'supplier_invoices', 'down_payment_settlements',
        'invoice_deductions', 'money_received', 'money_payments',
        'down_payment_money_payment_settlements',
        'factoring_transactions', 'letter_of_credit_issuances',
        'partners', 'companies',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $database = DB::connection()->getDatabaseName();
        $this->assertStringContainsString('test', $database, "Refusing to run against '{$database}'.");

        $this->dropTables();
        $this->createSchema();
        $this->loadRealTriggers();

        DB::table('companies')->insert(['id' => self::COMPANY, 'main_functional_currency' => 'EGP']);
        DB::table('partners')->insert([
            ['id' => self::DUAL, 'company_id' => self::COMPANY, 'name' => 'Ahmed', 'is_customer' => 1, 'is_supplier' => 1],
            ['id' => self::CUSTOMER_ONLY, 'company_id' => self::COMPANY, 'name' => 'Mona', 'is_customer' => 1, 'is_supplier' => 0],
        ]);

        $this->app->instance('request', Request::create('/en/'.self::COMPANY.'/customer-balances/CustomerInvoice', 'GET'));
    }

    protected function tearDown(): void
    {
        $this->dropTables();

        parent::tearDown();
    }

    private function dropTables(): void
    {
        foreach ($this->tables as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function createSchema(): void
    {
        Schema::create('companies', function ($table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('main_functional_currency')->nullable();
        });
        Schema::create('partners', function ($table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name')->nullable();
            $table->boolean('is_customer')->default(0);
            $table->boolean('is_supplier')->default(0);
        });

        // Both invoice tables carry every column their trigger touches.
        foreach (['customer_invoices' => 'customer', 'supplier_invoices' => 'supplier'] as $tableName => $side) {
            $money = $side === 'customer' ? 'collected' : 'paid';
            Schema::create($tableName, function ($table) use ($side, $money) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('company_id')->nullable();
                $table->unsignedBigInteger($side.'_id')->nullable();
                $table->string($side.'_name')->nullable();
                $table->string('invoice_number')->nullable();
                $table->date('invoice_date')->nullable();
                $table->date('invoice_due_date')->nullable();
                $table->string('currency')->nullable();
                $table->string('invoice_status')->nullable();
                $table->string('invoice_month')->nullable();
                $table->string('invoice_year')->nullable();
                $table->decimal('exchange_rate', 18, 6)->default(1);
                foreach ([
                    'invoice_amount', 'vat_amount', 'discount_amount', 'net_invoice_amount',
                    'withhold_amount', 'odoo_withhold_amount', 'total_withhold_amount',
                    'total_deductions', 'net_balance',
                    $money.'_amount', 'odoo_'.$money.'_amount', 'excel_'.$money.'_amount', 'total_'.$money.'_amount',
                ] as $column) {
                    $table->decimal($column, 18, 2)->default(0);
                    $table->decimal($column.'_in_main_currency', 18, 2)->default(0);
                }
            });
        }

        foreach (['settlements' => 'money_received_id', 'payment_settlements' => 'money_payment_id'] as $tableName => $moneyColumn) {
            Schema::create($tableName, function ($table) use ($tableName, $moneyColumn) {
                $table->increments('id');
                $table->unsignedBigInteger('invoice_id')->nullable();
                $table->unsignedBigInteger('partner_id')->default(0);
                $table->string('withhold_amount')->nullable();
                $table->string('settlement_amount')->nullable();
                $table->integer($moneyColumn)->nullable();
                $table->unsignedBigInteger('company_id')->nullable();
                $table->unsignedBigInteger('internal_settlement_id')->nullable();
                if ($tableName === 'payment_settlements') {
                    $table->unsignedBigInteger('letter_of_credit_issuance_id')->default(0);
                    $table->unsignedBigInteger('cash_expense_id')->nullable();
                }
                $table->timestamps();
            });
        }

        /**
         * The supplier-side settlement trigger keeps its own down-payment
         * table in step; the customer side uses down_payment_settlements.
         * Both stay empty here — they exist so the triggers can run.
         */
        Schema::create('down_payment_money_payment_settlements', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('money_payment_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('currency')->nullable();
            $table->decimal('down_payment_amount', 18, 2)->default(0);
            $table->decimal('total_down_payment_settlement', 18, 2)->default(0);
            $table->decimal('down_payment_balance', 18, 2)->default(0);
        });
        // Referenced by the settlement triggers; empty is fine.
        Schema::create('down_payment_settlements', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('money_received_id')->nullable();
            $table->unsignedBigInteger('money_payment_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('currency')->nullable();
            $table->decimal('down_payment_balance', 18, 2)->default(0);
        });
        foreach (['money_received' => 'receiving_date', 'money_payments' => 'delivery_date'] as $tableName => $dateColumn) {
            Schema::create($tableName, function ($table) use ($dateColumn) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('company_id')->nullable();
                $table->unsignedBigInteger('partner_id')->nullable();
                $table->string('partner_type')->nullable();
                $table->date($dateColumn)->nullable();
                $table->decimal('amount_in_invoice_currency', 18, 2)->default(0);
            });
        }
        Schema::create('invoice_deductions', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->string('invoice_type')->nullable();
            $table->date('date')->nullable();
        });
        Schema::create('factoring_transactions', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->string('recourse_type')->nullable();
            $table->date('factoring_date')->nullable();
        });
        Schema::create('letter_of_credit_issuances', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->date('payment_date')->nullable();
        });

        require_once database_path('migrations/2026_08_25_100000_create_internal_settlements_table.php');
        (require database_path('migrations/2026_08_25_100000_create_internal_settlements_table.php'))->up();
    }

    /**
     * Loads the app's own trigger definitions into the test schema.
     *
     * The .sql files are written for the mysql client, so they use
     * DELIMITER to let a trigger body contain semicolons. PDO has no
     * idea what DELIMITER means and takes one statement at a time, so
     * the directive is honoured here instead: track the current
     * delimiter and cut the file on it.
     *
     * If these files stop matching production the tests below start
     * failing — which is the point of loading the real ones.
     */
    private function loadRealTriggers(): void
    {
        foreach ([
            'customer_invoices_triggers.sql',
            'supplier_invoices_triggers.sql',
            'settlements.sql',
            'payment_settlements.sql',
        ] as $file) {
            foreach ($this->statementsIn(file_get_contents(app_path('Triggers/Cashvero/'.$file))) as $statement) {
                DB::unprepared($statement);
            }
        }

        $loaded = DB::table('information_schema.TRIGGERS')
            ->where('TRIGGER_SCHEMA', DB::connection()->getDatabaseName())
            ->count();

        $this->assertGreaterThanOrEqual(8, $loaded,
            'The invoice and settlement triggers did not load — every balance assertion below would be meaningless.');
    }

    /**
     * Splits a mysql-client script into statements, honouring DELIMITER.
     *
     * @return list<string>
     */
    private function statementsIn(string $sql): array
    {
        $delimiter = ';';
        $buffer = '';
        $statements = [];

        foreach (preg_split('/\R/', $sql) as $line) {
            if (preg_match('/^\s*delimiter\s+(\S+)\s*$/i', $line, $m)) {
                $delimiter = $m[1];

                continue;
            }

            $buffer .= $line."\n";

            while (($at = strpos($buffer, $delimiter)) !== false) {
                $statement = trim(substr($buffer, 0, $at));
                $buffer = substr($buffer, $at + strlen($delimiter));

                if ($statement !== '') {
                    $statements[] = $statement;
                }
            }
        }

        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }

        return $statements;
    }

    // ---------------------------------------------------------------
    // fixtures
    // ---------------------------------------------------------------

    private function customerInvoice(string $number, float $amount, int $partnerId = self::DUAL): int
    {
        return $this->invoice('customer_invoices', 'customer_id', $number, $amount, $partnerId);
    }

    private function supplierInvoice(string $number, float $amount, int $partnerId = self::DUAL): int
    {
        return $this->invoice('supplier_invoices', 'supplier_id', $number, $amount, $partnerId);
    }

    private function invoice(string $table, string $partnerColumn, string $number, float $amount, int $partnerId): int
    {
        return (int) DB::table($table)->insertGetId([
            'company_id' => self::COMPANY,
            $partnerColumn => $partnerId,
            'invoice_number' => $number,
            'invoice_date' => Carbon::today()->subMonth()->format('Y-m-d'),
            'invoice_due_date' => Carbon::today()->format('Y-m-d'),
            'currency' => 'EGP',
            'exchange_rate' => 1,
            'invoice_amount' => $amount,
        ]);
    }

    private function openBalance(string $table, string $partnerColumn, int $partnerId = self::DUAL): float
    {
        return round((float) DB::table($table)->where($partnerColumn, $partnerId)->sum('net_balance'), 2);
    }

    private function customerOpen(int $partnerId = self::DUAL): float
    {
        return $this->openBalance('customer_invoices', 'customer_id', $partnerId);
    }

    private function supplierOpen(int $partnerId = self::DUAL): float
    {
        return $this->openBalance('supplier_invoices', 'supplier_id', $partnerId);
    }

    private function controller(): BalancesController
    {
        return app(BalancesController::class);
    }

    private function submit(array $payload, ?InternalSettlement $editing = null)
    {
        $request = Request::create('/en/'.self::COMPANY.'/customer-balances/internal-settlement', $editing ? 'PUT' : 'POST', $payload + [
            'partner_id' => self::DUAL,
            'currency' => 'EGP',
            'settlement_date' => Carbon::today()->format('Y-m-d'),
        ]);
        $request->setLaravelSession(app('session.store'));
        $this->app->instance('request', $request);

        $company = Company::findOrFail(self::COMPANY);

        return $editing
            ? $this->controller()->updateInternalSettlement($request, $company, $editing)
            : $this->controller()->storeInternalSettlement($request, $company);
    }

    private function failure($response): ?string
    {
        return $response->getSession()->get('fail');
    }

    // ---------------------------------------------------------------
    // the money actually moves
    // ---------------------------------------------------------------

    /** The whole point: one settlement, both balances down by it. */
    public function test_it_takes_the_amount_off_the_customer_and_the_supplier_together(): void
    {
        $customer = $this->customerInvoice('C-1', 1000);
        $supplier = $this->supplierInvoice('S-1', 800);

        $this->assertSame(1000.0, $this->customerOpen());
        $this->assertSame(800.0, $this->supplierOpen());

        $this->submit([
            'customer_allocations' => [$customer => 300],
            'supplier_allocations' => [$supplier => 300],
        ]);

        $this->assertSame(700.0, $this->customerOpen(), '1,000 − 300 settled.');
        $this->assertSame(500.0, $this->supplierOpen(), '800 − 300 settled.');
        $this->assertSame(300.0, InternalSettlement::firstOrFail()->getAmount());
    }

    /** One settlement can be spread over several invoices per side. */
    public function test_one_settlement_can_span_several_invoices_on_each_side(): void
    {
        $c1 = $this->customerInvoice('C-1', 400);
        $c2 = $this->customerInvoice('C-2', 600);
        $s1 = $this->supplierInvoice('S-1', 250);
        $s2 = $this->supplierInvoice('S-2', 900);

        $this->submit([
            'customer_allocations' => [$c1 => 400, $c2 => 100],
            'supplier_allocations' => [$s1 => 250, $s2 => 250],
        ]);

        $this->assertSame(500.0, $this->customerOpen(), '1,000 − 500.');
        $this->assertSame(650.0, $this->supplierOpen(), '1,150 − 500.');
        $this->assertSame(0.0, (float) DB::table('customer_invoices')->where('id', $c1)->value('net_balance'),
            'C-1 was allocated its whole balance, so it is fully settled.');
        $this->assertSame(500.0, (float) DB::table('customer_invoices')->where('id', $c2)->value('net_balance'));
    }

    /** An invoice paid in full by a settlement reads as collected. */
    public function test_an_invoice_settled_in_full_is_marked_collected(): void
    {
        $customer = $this->customerInvoice('C-1', 500);
        $supplier = $this->supplierInvoice('S-1', 500);

        $this->submit([
            'customer_allocations' => [$customer => 500],
            'supplier_allocations' => [$supplier => 500],
        ]);

        $this->assertSame('collected', DB::table('customer_invoices')->where('id', $customer)->value('invoice_status'));
    }

    // ---------------------------------------------------------------
    // what it refuses
    // ---------------------------------------------------------------

    public function test_it_refuses_more_than_an_invoice_has_open(): void
    {
        $customer = $this->customerInvoice('C-1', 1000);
        $supplier = $this->supplierInvoice('S-1', 800);

        $response = $this->submit([
            'customer_allocations' => [$customer => 900],
            'supplier_allocations' => [$supplier => 900],
        ]);

        $this->assertStringContainsString('S-1', (string) $this->failure($response));
        $this->assertSame(1000.0, $this->customerOpen(), 'Nothing may move when the allocation is refused.');
        $this->assertSame(800.0, $this->supplierOpen());
        $this->assertSame(0, InternalSettlement::query()->count());
    }

    /** Two sides that do not agree are two adjustments, not an offset. */
    public function test_it_refuses_sides_that_do_not_match(): void
    {
        $customer = $this->customerInvoice('C-1', 1000);
        $supplier = $this->supplierInvoice('S-1', 800);

        $response = $this->submit([
            'customer_allocations' => [$customer => 300],
            'supplier_allocations' => [$supplier => 200],
        ]);

        $this->assertStringContainsString('must match', (string) $this->failure($response));
        $this->assertSame(1000.0, $this->customerOpen());
        $this->assertSame(800.0, $this->supplierOpen());
    }

    public function test_it_refuses_a_partner_who_is_not_also_a_supplier(): void
    {
        $customer = $this->customerInvoice('C-1', 1000, self::CUSTOMER_ONLY);

        $request = Request::create('/x', 'POST', [
            'partner_id' => self::CUSTOMER_ONLY, 'currency' => 'EGP',
            'settlement_date' => Carbon::today()->format('Y-m-d'),
            'customer_allocations' => [$customer => 100], 'supplier_allocations' => [],
        ]);
        $request->setLaravelSession(app('session.store'));
        $response = $this->controller()->storeInternalSettlement($request, Company::findOrFail(self::COMPANY));

        $this->assertStringContainsString('both a customer and a supplier', (string) $this->failure($response));
        $this->assertSame(1000.0, $this->customerOpen(self::CUSTOMER_ONLY));
    }

    public function test_it_refuses_the_main_currency_roll_up(): void
    {
        $customer = $this->customerInvoice('C-1', 1000);
        $supplier = $this->supplierInvoice('S-1', 800);

        $response = $this->submit([
            'currency' => 'main_currency',
            'customer_allocations' => [$customer => 100],
            'supplier_allocations' => [$supplier => 100],
        ]);

        $this->assertNotNull($this->failure($response));
        $this->assertSame(0, InternalSettlement::query()->count());
    }

    /** A settlement with nothing allocated is not a settlement. */
    public function test_it_refuses_an_empty_allocation(): void
    {
        $this->customerInvoice('C-1', 1000);
        $this->supplierInvoice('S-1', 800);

        $response = $this->submit(['customer_allocations' => [], 'supplier_allocations' => []]);

        $this->assertStringContainsString('at least one', (string) $this->failure($response));
    }

    // ---------------------------------------------------------------
    // editing
    // ---------------------------------------------------------------

    /**
     * The edit ceiling must add this settlement's own effect back
     * before measuring. Raising 300 → 900 is legitimate on a 1,000
     * invoice, but the 300 already took the balance to 700 — judged
     * against that, a correct edit would be refused.
     */
    public function test_an_edit_can_raise_its_own_allocation_above_the_current_balance(): void
    {
        $customer = $this->customerInvoice('C-1', 1000);
        $supplier = $this->supplierInvoice('S-1', 1000);

        $this->submit([
            'customer_allocations' => [$customer => 300],
            'supplier_allocations' => [$supplier => 300],
        ]);
        $settlement = InternalSettlement::firstOrFail();
        $this->assertSame(700.0, $this->customerOpen());

        $response = $this->submit([
            'customer_allocations' => [$customer => 900],
            'supplier_allocations' => [$supplier => 900],
        ], $settlement);

        $this->assertNull($this->failure($response), 'Raising the settlement inside the invoice total must be allowed.');
        $this->assertSame(100.0, $this->customerOpen(), '1,000 − 900.');
        $this->assertSame(100.0, $this->supplierOpen());
        $this->assertSame(900.0, $settlement->fresh()->getAmount());
    }

    /** Lowering it gives the difference back to the invoices. */
    public function test_lowering_an_edit_returns_the_difference(): void
    {
        $customer = $this->customerInvoice('C-1', 1000);
        $supplier = $this->supplierInvoice('S-1', 1000);

        $this->submit([
            'customer_allocations' => [$customer => 800],
            'supplier_allocations' => [$supplier => 800],
        ]);
        $settlement = InternalSettlement::firstOrFail();

        $this->submit([
            'customer_allocations' => [$customer => 250],
            'supplier_allocations' => [$supplier => 250],
        ], $settlement);

        $this->assertSame(750.0, $this->customerOpen());
        $this->assertSame(750.0, $this->supplierOpen());
    }

    /** An edit may move the money to entirely different invoices. */
    public function test_an_edit_can_move_the_allocation_to_other_invoices(): void
    {
        $c1 = $this->customerInvoice('C-1', 500);
        $c2 = $this->customerInvoice('C-2', 500);
        $s1 = $this->supplierInvoice('S-1', 500);
        $s2 = $this->supplierInvoice('S-2', 500);

        $this->submit([
            'customer_allocations' => [$c1 => 200],
            'supplier_allocations' => [$s1 => 200],
        ]);
        $settlement = InternalSettlement::firstOrFail();

        $this->submit([
            'customer_allocations' => [$c2 => 200],
            'supplier_allocations' => [$s2 => 200],
        ], $settlement);

        $this->assertSame(500.0, (float) DB::table('customer_invoices')->where('id', $c1)->value('net_balance'),
            'C-1 must be given its money back.');
        $this->assertSame(300.0, (float) DB::table('customer_invoices')->where('id', $c2)->value('net_balance'));
        $this->assertSame(500.0, (float) DB::table('supplier_invoices')->where('id', $s1)->value('net_balance'));
        $this->assertSame(300.0, (float) DB::table('supplier_invoices')->where('id', $s2)->value('net_balance'));
    }

    /** A refused edit must leave the original settlement standing. */
    public function test_a_refused_edit_leaves_the_original_untouched(): void
    {
        $customer = $this->customerInvoice('C-1', 1000);
        $supplier = $this->supplierInvoice('S-1', 1000);

        $this->submit([
            'customer_allocations' => [$customer => 400],
            'supplier_allocations' => [$supplier => 400],
        ]);
        $settlement = InternalSettlement::firstOrFail();

        $response = $this->submit([
            'customer_allocations' => [$customer => 500],
            'supplier_allocations' => [$supplier => 300],
        ], $settlement);

        $this->assertStringContainsString('must match', (string) $this->failure($response));
        $this->assertSame(400.0, $settlement->fresh()->getAmount());
        $this->assertSame(600.0, $this->customerOpen(), 'The original 400 is still applied.');
        $this->assertSame(600.0, $this->supplierOpen());
    }

    // ---------------------------------------------------------------
    // deleting
    // ---------------------------------------------------------------

    public function test_deleting_a_settlement_puts_both_sides_back_exactly(): void
    {
        $customer = $this->customerInvoice('C-1', 1000);
        $supplier = $this->supplierInvoice('S-1', 800);

        $this->submit([
            'customer_allocations' => [$customer => 275.55],
            'supplier_allocations' => [$supplier => 275.55],
        ]);
        $settlement = InternalSettlement::firstOrFail();

        $this->controller()->destroyInternalSettlement(Company::findOrFail(self::COMPANY), $settlement);

        $this->assertSame(1000.0, $this->customerOpen());
        $this->assertSame(800.0, $this->supplierOpen());
        $this->assertSame(0, InternalSettlement::query()->count());
        $this->assertSame(0, DB::table('settlements')->count(), 'Its allocation rows go with it.');
        $this->assertSame(0, DB::table('payment_settlements')->count());
    }

    /** Deleting one settlement must not disturb another. */
    public function test_deleting_one_settlement_leaves_the_others_applied(): void
    {
        $customer = $this->customerInvoice('C-1', 1000);
        $supplier = $this->supplierInvoice('S-1', 1000);

        $this->submit(['customer_allocations' => [$customer => 100], 'supplier_allocations' => [$supplier => 100]]);
        $first = InternalSettlement::orderBy('id')->firstOrFail();
        $this->submit(['customer_allocations' => [$customer => 250], 'supplier_allocations' => [$supplier => 250]]);

        $this->assertSame(650.0, $this->customerOpen(), '1,000 − 100 − 250.');

        $this->controller()->destroyInternalSettlement(Company::findOrFail(self::COMPANY), $first);

        $this->assertSame(750.0, $this->customerOpen(), 'Only the deleted 100 comes back.');
        $this->assertSame(750.0, $this->supplierOpen());
    }

    // ---------------------------------------------------------------
    // both statements
    // ---------------------------------------------------------------

    /** Credit on the customer's statement, debit on the supplier's. */
    public function test_the_settlement_reads_opposite_ways_on_the_two_statements(): void
    {
        $customer = $this->customerInvoice('C-1', 1000);
        $supplier = $this->supplierInvoice('S-1', 1000);

        $this->submit([
            'customer_allocations' => [$customer => 400],
            'supplier_allocations' => [$supplier => 400],
        ]);

        $customerRow = $this->statementRow('CustomerInvoice');
        $this->assertSame(0, $customerRow['debit']);
        $this->assertSame(400.0, $customerRow['credit']);

        $supplierRow = $this->statementRow('SupplierInvoice');
        $this->assertSame(400.0, $supplierRow['debit']);
        $this->assertSame(0, $supplierRow['credit']);
    }

    /** Each statement names the invoices on the OTHER side. */
    public function test_each_statement_names_the_invoices_the_money_reached(): void
    {
        $customer = $this->customerInvoice('C-1', 1000);
        $supplier = $this->supplierInvoice('S-1', 1000);

        $this->submit([
            'customer_allocations' => [$customer => 400],
            'supplier_allocations' => [$supplier => 400],
        ]);

        $this->assertStringContainsString('S-1', $this->statementRow('CustomerInvoice')['comment'],
            "The customer statement should say which supplier invoice it paid.");
        $this->assertStringContainsString('C-1', $this->statementRow('SupplierInvoice')['comment'],
            "The supplier statement should say which customer invoice it came from.");
    }

    // ---------------------------------------------------------------
    // routing
    // ---------------------------------------------------------------

    /**
     * The dialog's invoice endpoint must not be swallowed by the
     * single-segment `customer-balances/{modelType}` route above it.
     *
     * It was: registered after the wildcard, the URL matched with
     * modelType = 'internal-settlement-invoices', which
     * BalancesController@index turns into a model class name and dies
     * on — a 500 the dialog could only read as "this partner has no
     * invoices". Route order is invisible at the call site, so it is
     * pinned here.
     */
    public function test_the_invoice_endpoint_is_not_captured_by_the_balances_wildcard(): void
    {
        $matched = app('router')->getRoutes()->match(
            Request::create('/'.self::COMPANY.'/customer-balances/internal-settlement-invoices', 'GET')
        );

        $this->assertSame('internal.settlement.invoices', $matched->getName());
    }

    /** modelType becomes a class name, so only the two real ones may reach it. */
    public function test_the_balances_route_only_accepts_a_real_model_type(): void
    {
        foreach (['CustomerInvoice', 'SupplierInvoice'] as $modelType) {
            $matched = app('router')->getRoutes()->match(
                Request::create('/'.self::COMPANY.'/customer-balances/'.$modelType, 'GET')
            );
            $this->assertSame('view.balances', $matched->getName());
        }

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        app('router')->getRoutes()->match(
            Request::create('/'.self::COMPANY.'/customer-balances/NotAModel', 'GET')
        );
    }

    /**
     * The dialog reads its endpoint from a prop, and an Inertia prop
     * that is not DECLARED in defineProps is simply absent at runtime —
     * `props.internalSettlementInvoicesUrl` came back undefined, the
     * fetch resolved 'undefined' against the current path, 404'd, and
     * the dialog reported the partner had no invoices.
     *
     * Nothing in a build catches that, so the two halves are pinned
     * together here: what the controller sends and what the component
     * declares.
     */
    public function test_the_page_declares_every_internal_settlement_prop_it_is_sent(): void
    {
        $component = file_get_contents(resource_path('js/Pages/Balances/Index.vue'));
        preg_match('/defineProps\(\s*\{(.*?)\n\}\)/s', $component, $matches);
        $declared = $matches[1] ?? '';

        foreach (['canSettleInternally', 'storeInternalSettlementUrl', 'internalSettlementInvoicesUrl'] as $prop) {
            $this->assertMatchesRegularExpression('/^\s*'.$prop.'\s*:/m', $declared,
                "Balances/Index.vue is sent `{$prop}` but does not declare it, so it is undefined in the component.");
        }
    }

    /** @return array<string, mixed> */
    private function statementRow(string $modelType): array
    {
        $rows = [];
        $index = 0;

        \App\Http\Controllers\CustomerInvoiceDashboardController::appendBalances(
            false, 'EGP', collect(), $index, $rows, self::DUAL,
            Carbon::today()->subYear()->format('Y-m-d'), Carbon::today()->format('Y-m-d'),
            [], $modelType, true
        );

        $settlementRows = array_values(array_filter(
            $rows,
            fn ($row) => ($row['document_type'] ?? '') === InternalSettlement::documentType()
        ));

        $this->assertCount(1, $settlementRows, 'Exactly one internal settlement row was expected.');

        return $settlementRows[0];
    }
}
