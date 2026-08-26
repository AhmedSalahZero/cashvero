<?php

namespace Tests\Feature\InvoiceUpload;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The production repair for invoices saved without a company.
 *
 * The controller fix stops new ones; this command is what makes the
 * rows already in production reachable again. What matters is that it
 * repairs only what it can prove, and proves it from the partner —
 * never from a guess.
 *
 * @see \App\Console\Commands\FixInvoicesMissingCompanyCommand
 */
class FixInvoicesMissingCompanyCommandTest extends TestCase
{
    private const COMPANY = 920;

    private const OTHER_COMPANY = 921;

    private const CUSTOMER = 930;          // belongs to COMPANY

    private const OTHER_CUSTOMER = 931;    // belongs to OTHER_COMPANY

    private const HOMELESS_CUSTOMER = 932; // a partner with no company of its own

    private const SUPPLIER = 933;

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
        foreach (['customer_invoices' => 'customer_id', 'supplier_invoices' => 'supplier_id'] as $invoiceTable => $partnerColumn) {
            Schema::create($invoiceTable, function ($table) use ($partnerColumn) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('company_id')->default(0);
                $table->unsignedBigInteger($partnerColumn)->nullable();
                $table->string('invoice_number')->nullable();
                $table->string('currency')->nullable();
                $table->decimal('net_invoice_amount', 18, 5)->default(0);
            });
        }

        DB::table('partners')->insert([
            ['id' => self::CUSTOMER, 'company_id' => self::COMPANY, 'name' => '3S Software'],
            ['id' => self::OTHER_CUSTOMER, 'company_id' => self::OTHER_COMPANY, 'name' => 'Elsewhere Ltd'],
            ['id' => self::HOMELESS_CUSTOMER, 'company_id' => null, 'name' => 'No Company'],
            ['id' => self::SUPPLIER, 'company_id' => self::COMPANY, 'name' => 'A Supplier'],
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
        return DB::table($table)->insertGetId($attributes + [
            'company_id' => 0, 'currency' => 'EGP', 'net_invoice_amount' => 1000,
        ]);
    }

    private function companyOf(string $table, int $id): int
    {
        return (int) DB::table($table)->where('id', $id)->value('company_id');
    }

    // ---------------------------------------------------------------

    public function test_it_backfills_the_company_from_the_invoices_partner(): void
    {
        $id = $this->invoice('customer_invoices', ['customer_id' => self::CUSTOMER, 'invoice_number' => 'INV-1']);

        $this->artisan('invoices:fix-missing-company --fix')->assertSuccessful();

        $this->assertSame(self::COMPANY, $this->companyOf('customer_invoices', $id));
    }

    public function test_it_repairs_supplier_invoices_the_same_way(): void
    {
        $id = $this->invoice('supplier_invoices', ['supplier_id' => self::SUPPLIER, 'invoice_number' => 'SINV-1']);

        $this->artisan('invoices:fix-missing-company --fix')->assertSuccessful();

        $this->assertSame(self::COMPANY, $this->companyOf('supplier_invoices', $id));
    }

    /** Report mode is the default, and it must be genuinely read-only. */
    public function test_without_the_fix_flag_nothing_is_written(): void
    {
        $id = $this->invoice('customer_invoices', ['customer_id' => self::CUSTOMER, 'invoice_number' => 'INV-1']);

        $this->artisan('invoices:fix-missing-company')->assertSuccessful();

        $this->assertSame(0, $this->companyOf('customer_invoices', $id), 'Report mode must not change anything.');
    }

    /**
     * An invoice whose partner cannot say where it belongs is left
     * exactly as it is — guessing a company for a financial record is
     * worse than leaving it visibly broken.
     *
     * Asserted on the command's own report, not only on the stored
     * value: the column is NOT NULL DEFAULT 0, so a partner with a NULL
     * company would be written back as 0 and look untouched. The report
     * is the only place the difference between "skipped" and "repaired
     * to nothing" is visible.
     */
    public function test_an_invoice_whose_partner_cannot_place_it_is_left_alone(): void
    {
        $homeless = $this->invoice('customer_invoices', ['customer_id' => self::HOMELESS_CUSTOMER, 'invoice_number' => 'INV-HOMELESS']);
        $orphaned = $this->invoice('customer_invoices', ['customer_id' => 999999, 'invoice_number' => 'INV-NO-PARTNER']);
        $good = $this->invoice('customer_invoices', ['customer_id' => self::CUSTOMER, 'invoice_number' => 'INV-GOOD']);

        \Illuminate\Support\Facades\Artisan::call('invoices:fix-missing-company', ['--fix' => true]);
        $output = \Illuminate\Support\Facades\Artisan::output();

        $this->assertStringContainsString('2 invoice(s) have no usable partner', $output,
            'Both unplaceable invoices must be reported for review, not silently repaired.');
        $this->assertStringContainsString('1 invoice(s) to backfill', $output);
        $this->assertStringContainsString('backfilled company_id on 1 invoice(s)', $output,
            'Exactly one row may be written — the one whose partner proves where it belongs.');

        $this->assertSame(0, $this->companyOf('customer_invoices', $homeless));
        $this->assertSame(0, $this->companyOf('customer_invoices', $orphaned));
        $this->assertSame(self::COMPANY, $this->companyOf('customer_invoices', $good));
    }

    public function test_an_invoice_that_already_has_a_company_is_never_touched(): void
    {
        $id = DB::table('customer_invoices')->insertGetId([
            'company_id' => self::OTHER_COMPANY, 'customer_id' => self::CUSTOMER,
            'invoice_number' => 'INV-SETTLED', 'currency' => 'EGP', 'net_invoice_amount' => 500,
        ]);

        $this->artisan('invoices:fix-missing-company --fix')->assertSuccessful();

        $this->assertSame(self::OTHER_COMPANY, $this->companyOf('customer_invoices', $id),
            'Only invoices with NO company are in scope; an existing one is never reassigned.');
    }

    /** --company scopes the repair to one company's partners. */
    public function test_the_company_option_limits_what_gets_repaired(): void
    {
        $mine = $this->invoice('customer_invoices', ['customer_id' => self::CUSTOMER, 'invoice_number' => 'INV-MINE']);
        $theirs = $this->invoice('customer_invoices', ['customer_id' => self::OTHER_CUSTOMER, 'invoice_number' => 'INV-THEIRS']);

        $this->artisan('invoices:fix-missing-company --fix --company='.self::COMPANY)->assertSuccessful();

        $this->assertSame(self::COMPANY, $this->companyOf('customer_invoices', $mine));
        $this->assertSame(0, $this->companyOf('customer_invoices', $theirs), 'Out of scope, so untouched.');
    }

    /** Each invoice gets ITS OWN partner's company, not the first one found. */
    public function test_each_invoice_gets_its_own_partners_company(): void
    {
        $mine = $this->invoice('customer_invoices', ['customer_id' => self::CUSTOMER, 'invoice_number' => 'INV-A']);
        $theirs = $this->invoice('customer_invoices', ['customer_id' => self::OTHER_CUSTOMER, 'invoice_number' => 'INV-B']);

        $this->artisan('invoices:fix-missing-company --fix')->assertSuccessful();

        $this->assertSame(self::COMPANY, $this->companyOf('customer_invoices', $mine));
        $this->assertSame(self::OTHER_COMPANY, $this->companyOf('customer_invoices', $theirs));
    }

    /** Running it twice must be a no-op the second time. */
    public function test_it_is_safe_to_run_again(): void
    {
        $id = $this->invoice('customer_invoices', ['customer_id' => self::CUSTOMER, 'invoice_number' => 'INV-1']);

        $this->artisan('invoices:fix-missing-company --fix')->assertSuccessful();
        $this->artisan('invoices:fix-missing-company --fix')->assertSuccessful();

        $this->assertSame(self::COMPANY, $this->companyOf('customer_invoices', $id));
    }
}
