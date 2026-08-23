<?php

namespace Tests\Feature\InvoiceUpload;

use App\Support\Invoices\InvoiceMoneyLinks;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Which uploaded invoices "Delete All" has to keep back.
 *
 * The trap this guards is a naming one: the child tables all call the
 * column `invoice_id` and none of them says whether it means a customer
 * or a supplier invoice. Customer invoice #7073 and supplier invoice
 * #7073 both exist, so reading the wrong table would keep back a
 * perfectly deletable row — or, worse, delete one that has money on it
 * because the check looked somewhere it never appears.
 *
 * The side is decided by the TABLE:
 *   settlements            → money_received  → customer
 *   payment_settlements    → money_payment   → supplier
 *   settlement_allocations → money_payment   → supplier
 *
 * @see \App\Support\Invoices\InvoiceMoneyLinks
 */
class InvoiceMoneyLinksTest extends TestCase
{
    /** @var list<string> */
    private array $tables = [
        'settlements',
        'payment_settlements',
        'settlement_allocations',
        'invoice_deductions',
        'factoring_transactions',
        'letter_of_credit_issuances',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $database = DB::connection()->getDatabaseName();
        $this->assertStringContainsString('test', $database, "Refusing to run against '{$database}'.");

        $this->dropTables();

        foreach (['settlements', 'payment_settlements', 'settlement_allocations'] as $table) {
            Schema::create($table, function ($blueprint) {
                $blueprint->bigIncrements('id');
                $blueprint->unsignedBigInteger('invoice_id')->nullable();
                $blueprint->decimal('settlement_amount', 18, 2)->default(0);
            });
        }

        Schema::create('invoice_deductions', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('invoice_id')->nullable();
            $table->string('invoice_type')->nullable();
        });

        Schema::create('factoring_transactions', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('customer_invoice_id')->nullable();
        });

        Schema::create('letter_of_credit_issuances', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('supplier_invoice_id')->nullable();
        });
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

    // ---------------------------------------------------------------
    // nothing attached
    // ---------------------------------------------------------------

    public function test_invoices_with_no_money_are_all_deletable(): void
    {
        $this->assertSame([], InvoiceMoneyLinks::idsWithMoney('CustomerInvoice', [1, 2, 3]));
        $this->assertSame([], InvoiceMoneyLinks::idsWithMoney('SupplierInvoice', [1, 2, 3]));
        $this->assertSame([], InvoiceMoneyLinks::reasons('CustomerInvoice', []));
    }

    public function test_an_empty_id_list_asks_the_database_nothing(): void
    {
        DB::table('settlements')->insert(['invoice_id' => 1]);

        $this->assertSame([], InvoiceMoneyLinks::idsWithMoney('CustomerInvoice', []));
    }

    /**
     * A model with no money links declared must not be guarded at all —
     * "Delete All" on Sales Gathering or Loan Schedule keeps working.
     */
    public function test_an_unguarded_model_blocks_nothing(): void
    {
        DB::table('settlements')->insert(['invoice_id' => 1]);

        $this->assertFalse(InvoiceMoneyLinks::isGuarded('SalesGathering'));
        $this->assertSame([], InvoiceMoneyLinks::idsWithMoney('SalesGathering', [1, 2, 3]));
    }

    // ---------------------------------------------------------------
    // customer invoices
    // ---------------------------------------------------------------

    public function test_a_customer_invoice_with_a_collection_is_kept_back(): void
    {
        DB::table('settlements')->insert(['invoice_id' => 10, 'settlement_amount' => 500]);

        $this->assertSame([10], InvoiceMoneyLinks::idsWithMoney('CustomerInvoice', [10, 11, 12]));
        $this->assertSame(['Collections' => 1], InvoiceMoneyLinks::reasons('CustomerInvoice', [10]));
    }

    public function test_a_customer_invoice_that_was_factored_is_kept_back(): void
    {
        DB::table('factoring_transactions')->insert(['customer_invoice_id' => 10]);

        $this->assertSame([10], InvoiceMoneyLinks::idsWithMoney('CustomerInvoice', [10, 11]));
    }

    // ---------------------------------------------------------------
    // supplier invoices
    // ---------------------------------------------------------------

    public function test_a_supplier_invoice_with_a_payment_is_kept_back(): void
    {
        DB::table('payment_settlements')->insert(['invoice_id' => 20, 'settlement_amount' => 900]);

        $this->assertSame([20], InvoiceMoneyLinks::idsWithMoney('SupplierInvoice', [20, 21]));
        $this->assertSame(['Payments' => 1], InvoiceMoneyLinks::reasons('SupplierInvoice', [20]));
    }

    public function test_a_supplier_invoice_with_only_an_allocation_is_kept_back(): void
    {
        DB::table('settlement_allocations')->insert(['invoice_id' => 20]);

        $this->assertSame([20], InvoiceMoneyLinks::idsWithMoney('SupplierInvoice', [20, 21]));
    }

    public function test_a_supplier_invoice_behind_a_letter_of_credit_is_kept_back(): void
    {
        DB::table('letter_of_credit_issuances')->insert(['supplier_invoice_id' => 20]);

        $this->assertSame([20], InvoiceMoneyLinks::idsWithMoney('SupplierInvoice', [20, 21]));
    }

    // ---------------------------------------------------------------
    // the id-collision trap
    // ---------------------------------------------------------------

    /**
     * The same id number exists on both sides. A collection on customer
     * #30 must not keep supplier #30 back, and a payment on supplier #30
     * must not keep customer #30 back.
     */
    public function test_the_two_sides_never_read_each_others_tables(): void
    {
        DB::table('settlements')->insert(['invoice_id' => 30]);          // customer collection
        DB::table('payment_settlements')->insert(['invoice_id' => 31]);  // supplier payment

        $this->assertSame([30], InvoiceMoneyLinks::idsWithMoney('CustomerInvoice', [30, 31]),
            'A supplier payment must not keep a customer invoice back.');
        $this->assertSame([31], InvoiceMoneyLinks::idsWithMoney('SupplierInvoice', [30, 31]),
            'A customer collection must not keep a supplier invoice back.');
    }

    /**
     * invoice_deductions DOES carry invoice_type, so it has to be
     * filtered on it — it is the one table both sides read.
     */
    public function test_deductions_are_filtered_by_invoice_type(): void
    {
        DB::table('invoice_deductions')->insert([
            ['invoice_id' => 40, 'invoice_type' => 'CustomerInvoice'],
            ['invoice_id' => 41, 'invoice_type' => 'SupplierInvoice'],
        ]);

        $this->assertSame([40], InvoiceMoneyLinks::idsWithMoney('CustomerInvoice', [40, 41]));
        $this->assertSame([41], InvoiceMoneyLinks::idsWithMoney('SupplierInvoice', [40, 41]));
    }

    // ---------------------------------------------------------------
    // counting and reporting
    // ---------------------------------------------------------------

    public function test_an_invoice_held_by_two_things_is_counted_once(): void
    {
        DB::table('payment_settlements')->insert(['invoice_id' => 50]);
        DB::table('settlement_allocations')->insert(['invoice_id' => 50]);

        $this->assertSame([50], InvoiceMoneyLinks::idsWithMoney('SupplierInvoice', [50]),
            'One invoice, not two.');
        $this->assertSame(
            ['Payments' => 1, 'Payment Allocations' => 1],
            InvoiceMoneyLinks::reasons('SupplierInvoice', [50]),
            'but both reasons are named'
        );
    }

    public function test_several_settlements_on_one_invoice_count_as_one_invoice(): void
    {
        DB::table('payment_settlements')->insert([
            ['invoice_id' => 60], ['invoice_id' => 60], ['invoice_id' => 60],
        ]);

        $this->assertSame([60], InvoiceMoneyLinks::idsWithMoney('SupplierInvoice', [60]));
        $this->assertSame(['Payments' => 1], InvoiceMoneyLinks::reasons('SupplierInvoice', [60]),
            'Three payments against one invoice is still one invoice.');
    }

    public function test_the_reasons_are_ordered_with_the_biggest_first(): void
    {
        DB::table('payment_settlements')->insert([['invoice_id' => 70], ['invoice_id' => 71]]);
        DB::table('settlement_allocations')->insert(['invoice_id' => 70]);

        $this->assertSame(
            ['Payments' => 2, 'Payment Allocations' => 1],
            InvoiceMoneyLinks::reasons('SupplierInvoice', [70, 71])
        );
    }

    /**
     * A bulk delete hands over every id in the dataset, and MySQL will
     * not take an unbounded IN () list — the lookup chunks.
     */
    public function test_it_handles_more_ids_than_one_query_can_hold(): void
    {
        $ids = range(1, 2500);
        DB::table('payment_settlements')->insert([['invoice_id' => 1], ['invoice_id' => 2499]]);

        $this->assertSame([1, 2499], InvoiceMoneyLinks::idsWithMoney('SupplierInvoice', $ids));
    }

    /**
     * A table this installation does not have must be skipped, not blow
     * the delete up.
     */
    public function test_a_missing_table_is_skipped_not_fatal(): void
    {
        Schema::dropIfExists('factoring_transactions');
        DB::table('settlements')->insert(['invoice_id' => 80]);

        $this->assertSame([80], InvoiceMoneyLinks::idsWithMoney('CustomerInvoice', [80]));
    }
}
