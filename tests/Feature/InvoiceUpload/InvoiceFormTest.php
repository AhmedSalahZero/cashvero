<?php

namespace Tests\Feature\InvoiceUpload;

use App\Http\Controllers\SalesGatheringTestController;
use App\Models\Company;
use App\Models\CustomerInvoice;
use App\Models\SupplierInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The single-invoice form behind /create-item/{CustomerInvoice,SupplierInvoice}.
 *
 * Three things are guarded here, all of them client-reported:
 *   1. the saved row belongs to a company — without that the invoice is
 *      invisible on every screen that scopes by company_id, while still
 *      showing on the balances page, which does not
 *   2. Currency is chosen from the list, never typed
 *   3. Total Invoice Amount is computed from its three parts
 *
 * @see \App\Http\Controllers\SalesGatheringTestController::storeModel()
 */
class InvoiceFormTest extends TestCase
{
    private const COMPANY = 900;

    private const OTHER_COMPANY = 901;

    private const CUSTOMER = 910;

    private const SUPPLIER = 911;

    /** @var list<string> */
    private array $tables = [
        'customer_invoices', 'supplier_invoices', 'purchase_orders', 'sales_orders',
        'contracts', 'partners', 'tables_fields', 'record_activities',
        'customized_fields_exportations', 'companies',
    ];

    /**
     * The columns both invoice forms are configured to show. Deliberately
     * WITHOUT withhold_amount for the customer side and WITH it for the
     * supplier side — that is how company 146 and company 92 are really
     * configured, and the computed total has to hold either way.
     */
    private const CUSTOMER_FIELDS = ['customer_name', 'invoice_number', 'invoice_date', 'currency', 'invoice_amount', 'vat_amount', 'net_invoice_amount'];

    private const SUPPLIER_FIELDS = ['supplier_name', 'invoice_number', 'invoice_date', 'currency', 'invoice_amount', 'vat_amount', 'withhold_amount', 'net_invoice_amount'];

    protected function setUp(): void
    {
        parent::setUp();

        $database = DB::connection()->getDatabaseName();
        $this->assertStringContainsString('test', $database, "Refusing to run against '{$database}'.");

        $this->dropTables();
        $this->createSchema();
        $this->seedFixture();
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
        Schema::create('contracts', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('partner_id')->nullable();
            $table->string('name')->nullable();
            $table->string('code')->nullable();
            $table->string('model_type')->nullable();
        });
        Schema::create('sales_orders', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('so_number')->nullable();
        });
        Schema::create('purchase_orders', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('po_number')->nullable();
        });
        Schema::create('customized_fields_exportations', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('model_name')->nullable();
            $table->json('fields')->nullable();
        });
        Schema::create('record_activities', function ($table) {
            $table->bigIncrements('id');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->string('event')->nullable();
            $table->text('description')->nullable();
            $table->longText('field_changes')->nullable();
            $table->timestamp('created_at')->nullable();
        });
        Schema::create('tables_fields', function ($table) {
            $table->bigIncrements('id');
            $table->string('model_name')->nullable();
            $table->string('field_name')->nullable();
            $table->string('view_name')->nullable();
            $table->integer('sort_order')->nullable();
        });

        foreach (['customer_invoices', 'supplier_invoices'] as $invoiceTable) {
            $clientColumn = $invoiceTable === 'customer_invoices' ? 'customer' : 'supplier';
            Schema::create($invoiceTable, function ($table) use ($clientColumn) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('company_id')->default(0);
                $table->unsignedBigInteger($clientColumn.'_id')->nullable();
                $table->string($clientColumn.'_name')->nullable();
                $table->string('invoice_number')->nullable();
                $table->date('invoice_date')->nullable();
                $table->string('currency')->nullable();
                $table->decimal('invoice_amount', 18, 5)->default(0);
                $table->decimal('vat_amount', 18, 5)->nullable();
                $table->decimal('withhold_amount', 18, 5)->default(0);
                $table->decimal('net_invoice_amount', 18, 5)->default(0);
                $table->string('contract_name')->nullable();
                $table->string('project_name')->nullable();
                $table->string('sales_order_number')->nullable();
                $table->string('purchases_order_number')->nullable();
                // Stamped by the app's own created-by/updated-by hooks.
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }
    }

    private function seedFixture(): void
    {
        DB::table('companies')->insert([
            ['id' => self::COMPANY, 'main_functional_currency' => 'EGP'],
            ['id' => self::OTHER_COMPANY, 'main_functional_currency' => 'EGP'],
        ]);
        DB::table('partners')->insert([
            ['id' => self::CUSTOMER, 'company_id' => self::COMPANY, 'name' => '3S Software', 'is_customer' => 1, 'is_supplier' => 0],
            ['id' => self::SUPPLIER, 'company_id' => self::COMPANY, 'name' => 'A Supplier', 'is_customer' => 0, 'is_supplier' => 1],
        ]);

        foreach ([
            'CustomerInvoice' => self::CUSTOMER_FIELDS,
            'SupplierInvoice' => self::SUPPLIER_FIELDS,
        ] as $modelName => $fields) {
            DB::table('customized_fields_exportations')->insert([
                'company_id' => self::COMPANY, 'model_name' => $modelName, 'fields' => json_encode($fields),
            ]);
            foreach ($fields as $sort => $fieldName) {
                DB::table('tables_fields')->insert([
                    'model_name' => $modelName,
                    'field_name' => $fieldName,
                    'view_name' => $this->labelFor($fieldName),
                    'sort_order' => $sort,
                ]);
            }
        }
    }

    private function labelFor(string $fieldName): string
    {
        return [
            'customer_name' => 'Customer Name',
            'supplier_name' => 'Supplier Name',
            'invoice_number' => 'Invoice Number',
            'invoice_date' => 'Invoice Date',
            'currency' => 'Currency',
            'invoice_amount' => 'Invoice Amount',
            'vat_amount' => 'VAT Amount',
            'withhold_amount' => 'Withhold Amount',
            'net_invoice_amount' => 'Total Invoice Amount',
        ][$fieldName];
    }

    private function controller(): SalesGatheringTestController
    {
        return app(SalesGatheringTestController::class);
    }

    private function bindRequest(Request $request): Request
    {
        $request->setLaravelSession(app('session.store'));
        $this->app->instance('request', $request);

        return $request;
    }

    /** @return array<string, mixed> */
    private function formProps(string $modelName): array
    {
        $request = $this->bindRequest(Request::create('/en/'.self::COMPANY.'/create-item/'.$modelName, 'GET'));
        $response = $this->controller()->createModel(Company::findOrFail(self::COMPANY), $request, $modelName);

        $property = new \ReflectionProperty($response, 'props');
        $property->setAccessible(true);

        return $property->getValue($response);
    }

    /** @return array<string, mixed>|null */
    private function fieldNamed(string $modelName, string $fieldName): ?array
    {
        foreach ($this->formProps($modelName)['fields'] as $field) {
            if ($field['field'] === $fieldName) {
                return $field;
            }
        }

        return null;
    }

    // ---------------------------------------------------------------
    // 1. the invoice belongs to a company
    // ---------------------------------------------------------------

    /**
     * The reported symptom exactly: an invoice saved from this form
     * showed on Customers Balances but nowhere else, because it was
     * saved with company_id = 0 and every other screen scopes by it.
     */
    public function test_a_customer_invoice_is_saved_against_the_company_it_was_created_in(): void
    {
        $this->storeCustomerInvoice(['invoice_number' => 'INV-1']);

        $invoice = CustomerInvoice::where('invoice_number', 'INV-1')->firstOrFail();

        $this->assertSame(self::COMPANY, (int) $invoice->company_id);
        $this->assertSame(1, CustomerInvoice::where('company_id', self::COMPANY)->count(),
            'The invoice must be findable by the company scope every other screen uses.');
    }

    public function test_a_supplier_invoice_is_saved_against_the_company_it_was_created_in(): void
    {
        $this->storeSupplierInvoice(['invoice_number' => 'SINV-1']);

        $invoice = SupplierInvoice::where('invoice_number', 'SINV-1')->firstOrFail();

        $this->assertSame(self::COMPANY, (int) $invoice->company_id);
        $this->assertSame(1, SupplierInvoice::where('company_id', self::COMPANY)->count());
    }

    /**
     * The company comes from the route, not the payload — otherwise the
     * form would be a way to write an invoice into someone else's books.
     */
    public function test_a_posted_company_id_cannot_override_the_route_company(): void
    {
        $this->storeCustomerInvoice(['invoice_number' => 'INV-SPOOF', 'company_id' => self::OTHER_COMPANY]);

        $this->assertSame(self::COMPANY, (int) CustomerInvoice::where('invoice_number', 'INV-SPOOF')->firstOrFail()->company_id);
        $this->assertSame(0, CustomerInvoice::where('company_id', self::OTHER_COMPANY)->count());
    }

    /** Editing an invoice that was already saved without one repairs it. */
    public function test_editing_an_invoice_gives_it_the_company_it_was_missing(): void
    {
        $orphanId = DB::table('customer_invoices')->insertGetId([
            'company_id' => 0, 'customer_id' => self::CUSTOMER, 'customer_name' => '3S Software',
            'invoice_number' => 'INV-ORPHAN', 'currency' => 'EGP', 'invoice_amount' => 1000,
            'net_invoice_amount' => 1000, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $request = $this->bindRequest(Request::create('/en/'.self::COMPANY.'/create-item/CustomerInvoice/update/'.$orphanId, 'POST', [
            'customer_id' => self::CUSTOMER, 'invoice_number' => 'INV-ORPHAN', 'currency' => 'EGP',
            'invoice_amount' => 1000, 'net_invoice_amount' => 1000,
        ]));
        $this->controller()->updateModel(Company::findOrFail(self::COMPANY), $request, 'CustomerInvoice', $orphanId);

        $this->assertSame(self::COMPANY, (int) CustomerInvoice::findOrFail($orphanId)->company_id);
    }

    // ---------------------------------------------------------------
    // 2. currency is chosen, not typed
    // ---------------------------------------------------------------

    /**
     * Every screen downstream groups and filters on this exact string,
     * so a typed "egp" would split a partner's balance in two.
     */
    public function test_currency_is_a_dropdown_of_the_known_currencies(): void
    {
        foreach (['CustomerInvoice', 'SupplierInvoice'] as $modelName) {
            $currency = $this->fieldNamed($modelName, 'currency');

            $this->assertNotNull($currency, "{$modelName} has no currency field.");
            $this->assertSame('currency_select', $currency['type'], "{$modelName}'s currency must not be free text.");

            $options = collect($currency['options'])->toArray();
            $this->assertSame(array_values(getCurrencies()), array_keys($options));
            $this->assertContains('EGP', array_keys($options));
        }
    }

    // ---------------------------------------------------------------
    // 3. Total Invoice Amount is computed
    // ---------------------------------------------------------------

    public function test_total_invoice_amount_is_computed_not_typed(): void
    {
        foreach (['CustomerInvoice', 'SupplierInvoice'] as $modelName) {
            $total = $this->fieldNamed($modelName, 'net_invoice_amount');

            $this->assertNotNull($total, "{$modelName} has no Total Invoice Amount field.");
            $this->assertSame('computed_total', $total['type']);
            $this->assertSame('Total Invoice Amount', $total['label']);
        }
    }

    /** Invoice Amount + VAT − Withholding, named by the server. */
    public function test_the_form_is_told_which_fields_make_up_the_total(): void
    {
        foreach (['CustomerInvoice', 'SupplierInvoice'] as $modelName) {
            $formula = $this->formProps($modelName)['totalInvoiceFormula'];

            $this->assertSame('net_invoice_amount', $formula['target']);
            $this->assertSame(['invoice_amount', 'vat_amount'], $formula['add']);
            $this->assertSame(['withhold_amount'], $formula['subtract']);
        }
    }

    /**
     * A company that does not display Withhold Amount still gets a
     * correct total — the missing term counts as zero rather than
     * breaking the sum. Company 146 is configured exactly this way.
     */
    public function test_a_company_that_hides_withholding_still_gets_a_total(): void
    {
        $shown = collect($this->formProps('CustomerInvoice')['fields'])->pluck('field')->all();

        $this->assertNotContains('withhold_amount', $shown, 'This fixture mirrors company 146, which hides it.');
        $this->assertContains('net_invoice_amount', $shown);
        $this->assertSame(['withhold_amount'], $this->formProps('CustomerInvoice')['totalInvoiceFormula']['subtract'],
            'The formula still names it, so the form can treat it as 0 rather than guess.');
    }

    // ---------------------------------------------------------------
    // helpers
    // ---------------------------------------------------------------

    private function storeCustomerInvoice(array $overrides = []): void
    {
        $request = $this->bindRequest(Request::create('/en/'.self::COMPANY.'/create-item/CustomerInvoice', 'POST', $overrides + [
            'customer_id' => self::CUSTOMER,
            'invoice_number' => 'INV-1',
            'invoice_date' => now()->format('Y-m-d'),
            'currency' => 'EGP',
            'invoice_amount' => 1000,
            'vat_amount' => 140,
            'net_invoice_amount' => 1140,
        ]));

        $this->controller()->storeModel(Company::findOrFail(self::COMPANY), $request, 'CustomerInvoice');
    }

    private function storeSupplierInvoice(array $overrides = []): void
    {
        $request = $this->bindRequest(Request::create('/en/'.self::COMPANY.'/create-item/SupplierInvoice', 'POST', $overrides + [
            'supplier_id' => self::SUPPLIER,
            'invoice_number' => 'SINV-1',
            'invoice_date' => now()->format('Y-m-d'),
            'currency' => 'EGP',
            'invoice_amount' => 1000,
            'vat_amount' => 140,
            'withhold_amount' => 30,
            'net_invoice_amount' => 1110,
        ]));

        $this->controller()->storeModel(Company::findOrFail(self::COMPANY), $request, 'SupplierInvoice');
    }
}
