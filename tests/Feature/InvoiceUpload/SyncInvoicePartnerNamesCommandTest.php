<?php

namespace Tests\Feature\InvoiceUpload;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Copies partners.name onto the name stored on customer and supplier invoices.
 *
 * @see \App\Console\Commands\SyncInvoicePartnerNamesCommand
 */
class SyncInvoicePartnerNamesCommandTest extends TestCase
{
    private const COMPANY = 940;

    private const OTHER_COMPANY = 941;

    private const CUSTOMER = 950;

    private const OTHER_CUSTOMER = 951;

    private const NAMELESS_CUSTOMER = 952;

    private const SUPPLIER = 953;

    /** @var list<string> */
    private array $tables = ['customer_invoices', 'supplier_invoices', 'partners'];

    protected function setUp(): void
    {
        parent::setUp();

        $database = DB::connection()->getDatabaseName();
        $this->assertStringContainsString('test', $database, "Refusing to run against '{$database}'.");

        $this->dropTables();

        Schema::create('partners', function ($table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name')->nullable();
        });
        foreach ([
            'customer_invoices' => ['customer_id', 'customer_name'],
            'supplier_invoices' => ['supplier_id', 'supplier_name'],
        ] as $invoiceTable => [$partnerColumn, $nameColumn]) {
            Schema::create($invoiceTable, function ($table) use ($partnerColumn, $nameColumn) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('company_id');
                $table->unsignedBigInteger($partnerColumn)->nullable();
                $table->string($nameColumn)->nullable();
                $table->dateTime('updated_at')->nullable();
            });
        }

        DB::table('partners')->insert([
            ['id' => self::CUSTOMER, 'company_id' => self::COMPANY, 'name' => 'قطر الوطني الأهلي'],
            ['id' => self::OTHER_CUSTOMER, 'company_id' => self::OTHER_COMPANY, 'name' => 'Elsewhere Ltd'],
            ['id' => self::NAMELESS_CUSTOMER, 'company_id' => self::COMPANY, 'name' => null],
            ['id' => self::SUPPLIER, 'company_id' => self::COMPANY, 'name' => 'مورد الحديد'],
        ]);
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

    private function invoice(string $table, array $attributes): int
    {
        $nameColumn = $table === 'customer_invoices' ? 'customer_name' : 'supplier_name';

        return DB::table($table)->insertGetId($attributes + [
            'company_id' => self::COMPANY,
            $nameColumn => 'Old Name',
            'updated_at' => '2026-01-01 00:00:00',
        ]);
    }

    private function nameOf(string $table, int $id): ?string
    {
        $column = $table === 'customer_invoices' ? 'customer_name' : 'supplier_name';

        return DB::table($table)->where('id', $id)->value($column);
    }

    public function test_it_copies_the_partner_name_onto_customer_invoices(): void
    {
        $id = $this->invoice('customer_invoices', ['customer_id' => self::CUSTOMER]);

        $this->artisan('invoices:sync-partner-names --fix')->assertSuccessful();

        $this->assertSame('قطر الوطني الأهلي', $this->nameOf('customer_invoices', $id));
    }

    public function test_it_copies_the_partner_name_onto_supplier_invoices(): void
    {
        $id = $this->invoice('supplier_invoices', ['supplier_id' => self::SUPPLIER]);

        $this->artisan('invoices:sync-partner-names --fix')->assertSuccessful();

        $this->assertSame('مورد الحديد', $this->nameOf('supplier_invoices', $id));
    }

    public function test_without_the_fix_flag_nothing_is_written(): void
    {
        $id = $this->invoice('customer_invoices', ['customer_id' => self::CUSTOMER]);

        $this->artisan('invoices:sync-partner-names')->assertSuccessful();

        $this->assertSame('Old Name', $this->nameOf('customer_invoices', $id));
        $this->assertSame(
            '2026-01-01 00:00:00',
            DB::table('customer_invoices')->where('id', $id)->value('updated_at')
        );
    }

    public function test_an_invoice_whose_partner_is_missing_is_left_alone(): void
    {
        $orphaned = $this->invoice('customer_invoices', ['customer_id' => 999999]);
        $good = $this->invoice('customer_invoices', ['customer_id' => self::CUSTOMER]);

        Artisan::call('invoices:sync-partner-names', ['--fix' => true]);
        $output = Artisan::output();

        $this->assertStringContainsString('1 invoice(s) have no usable partner name', $output);
        $this->assertStringContainsString('updated 1 invoice name(s)', $output);
        $this->assertSame('Old Name', $this->nameOf('customer_invoices', $orphaned));
        $this->assertSame('قطر الوطني الأهلي', $this->nameOf('customer_invoices', $good));
    }

    public function test_a_partner_with_no_name_does_not_blank_the_invoice(): void
    {
        $id = $this->invoice('customer_invoices', ['customer_id' => self::NAMELESS_CUSTOMER]);

        Artisan::call('invoices:sync-partner-names', ['--fix' => true]);

        $this->assertStringContainsString('1 invoice(s) have no usable partner name', Artisan::output());
        $this->assertSame('Old Name', $this->nameOf('customer_invoices', $id));
    }

    public function test_a_matching_name_is_not_rewritten(): void
    {
        $id = $this->invoice('customer_invoices', [
            'customer_id' => self::CUSTOMER,
            'customer_name' => 'قطر الوطني الأهلي',
        ]);

        $this->artisan('invoices:sync-partner-names --fix')->assertSuccessful();

        $this->assertSame(
            '2026-01-01 00:00:00',
            DB::table('customer_invoices')->where('id', $id)->value('updated_at')
        );
    }

    public function test_a_null_invoice_name_is_filled_from_the_partner(): void
    {
        $id = $this->invoice('customer_invoices', [
            'customer_id' => self::CUSTOMER,
            'customer_name' => null,
        ]);

        $this->artisan('invoices:sync-partner-names --fix')->assertSuccessful();

        $this->assertSame('قطر الوطني الأهلي', $this->nameOf('customer_invoices', $id));
    }

    public function test_the_company_option_limits_what_gets_renamed(): void
    {
        $mine = $this->invoice('customer_invoices', ['customer_id' => self::CUSTOMER]);
        $theirs = $this->invoice('customer_invoices', [
            'company_id' => self::OTHER_COMPANY,
            'customer_id' => self::OTHER_CUSTOMER,
        ]);

        $this->artisan('invoices:sync-partner-names --fix --company='.self::COMPANY)->assertSuccessful();

        $this->assertSame('قطر الوطني الأهلي', $this->nameOf('customer_invoices', $mine));
        $this->assertSame('Old Name', $this->nameOf('customer_invoices', $theirs));
    }

    public function test_each_invoice_gets_its_own_partner_name(): void
    {
        $customerInvoice = $this->invoice('customer_invoices', ['customer_id' => self::CUSTOMER]);
        $otherInvoice = $this->invoice('customer_invoices', [
            'company_id' => self::OTHER_COMPANY,
            'customer_id' => self::OTHER_CUSTOMER,
        ]);

        $this->artisan('invoices:sync-partner-names --fix')->assertSuccessful();

        $this->assertSame('قطر الوطني الأهلي', $this->nameOf('customer_invoices', $customerInvoice));
        $this->assertSame('Elsewhere Ltd', $this->nameOf('customer_invoices', $otherInvoice));
    }
}
