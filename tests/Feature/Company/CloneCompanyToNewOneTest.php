<?php

namespace Tests\Feature\Company;

use App\Services\CompanyData\CompanyImportService;
use App\Services\CompanyData\DemoCompanyAnonymizer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A small company is cloned inside the test transaction, which is rolled
 * back. Company 148 is not copied.
 */
class CloneCompanyToNewOneTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUpTraits()
    {
        config(['database.connections.mysql.database' => env('SMOKE_DB', 'cashvero')]);
        DB::purge('mysql');

        return parent::setUpTraits();
    }

    public function test_a_clone_matches_the_source_numbers_and_renames_only_the_copy(): void
    {
        $template = DB::table('companies')->whereNull('deleted_at')->orderBy('id')->first();
        if (! $template) {
            $this->markTestSkipped('No company row to copy a column shape from.');
        }

        $payload = (array) $template;
        unset($payload['id']);
        $sourceName = 'Clone Source '.uniqid();
        $payload['name'] = json_encode(['en' => $sourceName, 'ar' => $sourceName], JSON_UNESCAPED_UNICODE);
        $payload['is_caching_now'] = 0;
        $payload['deleted_at'] = null;
        $sourceId = (int) DB::table('companies')->insertGetId($payload);

        $customerId = $this->partner($sourceId, 'Real Customer', customer: true);
        $bothId = $this->partner($sourceId, 'Real Both', customer: true, supplier: true);
        $supplierId = $this->partner($sourceId, 'Real Supplier', supplier: true);
        $this->partner($sourceId, 'Real Employee', employee: true);

        $customerInvoiceId = (int) DB::table('customer_invoices')->insertGetId([
            'company_id' => $sourceId,
            'customer_id' => $customerId,
            'customer_name' => 'Real Customer',
            'project_name' => 'Real Project',
            'currency' => 'EGP',
            'invoice_amount' => 1000.50,
            'exchange_rate' => 1,
            'invoice_number' => 'INV-KEEP',
        ]);
        DB::table('supplier_invoices')->insert([
            'company_id' => $sourceId,
            'supplier_id' => $supplierId,
            'supplier_name' => 'Real Supplier',
            'currency' => 'EGP',
            'invoice_amount' => 250.25,
            'exchange_rate' => 1,
            'invoice_number' => 'BILL-KEEP',
        ]);

        $demoName = 'Clone Demo '.uniqid();
        $result = (new CompanyImportService())->cloneToNewCompany($sourceId, $demoName);

        $this->assertTrue($result['ok'], json_encode($result['report']['errors'] ?? $result['report']['verification']['mismatches'] ?? []));
        $newId = (int) $result['new_company_id'];
        $this->assertNotSame($sourceId, $newId);

        $this->assertSame('Real Customer', DB::table('partners')->where('id', $customerId)->value('name'));
        $this->assertSame('Real Customer', DB::table('customer_invoices')->where('id', $customerInvoiceId)->value('customer_name'));
        $this->assertSame(1, DB::table('customer_invoices')->where('company_id', $sourceId)->count());
        $this->assertEquals(1000.50, (float) DB::table('customer_invoices')->where('company_id', $sourceId)->sum('invoice_amount_in_main_currency'));

        $this->assertSame(
            ['Customer 1', 'Customer / Supplier 1', 'Supplier 1', 'Employee 1'],
            DB::table('partners')->where('company_id', $newId)->orderBy('id')->pluck('name')->all()
        );

        $clonedCustomer = DB::table('customer_invoices')->where('company_id', $newId)->first();
        $this->assertSame(DemoCompanyAnonymizer::COMPANY_NAMES[0], $clonedCustomer->customer_name);
        $this->assertSame(DemoCompanyAnonymizer::PROJECT_NAMES[0], $clonedCustomer->project_name);
        $this->assertSame('INV-KEEP', $clonedCustomer->invoice_number);
        $this->assertEquals(1000.50, (float) $clonedCustomer->invoice_amount_in_main_currency);

        $clonedSupplier = DB::table('supplier_invoices')->where('company_id', $newId)->first();
        $this->assertSame(DemoCompanyAnonymizer::COMPANY_NAMES[0], $clonedSupplier->supplier_name);
        $this->assertSame('BILL-KEEP', $clonedSupplier->invoice_number);
        $this->assertEquals(250.25, (float) DB::table('supplier_invoices')->where('company_id', $newId)->sum('invoice_amount_in_main_currency'));
        $this->assertEquals(1000.50, (float) DB::table('customer_invoices')->where('company_id', $newId)->sum('invoice_amount_in_main_currency'));

        $newCompanyName = json_decode((string) DB::table('companies')->where('id', $newId)->value('name'), true);
        $this->assertSame($demoName, $newCompanyName['en']);
        $this->assertSame($demoName, $newCompanyName['ar']);

        $this->assertSame($sourceName, json_decode((string) DB::table('companies')->where('id', $sourceId)->value('name'), true)['en']);
        $this->assertNull(DB::table('partners')->where('company_id', $newId)->where('name', 'Real Customer')->first());
        $this->assertSame($bothId, (int) DB::table('partners')->where('company_id', $sourceId)->where('name', 'Real Both')->value('id'));
    }

    private function partner(int $companyId, string $name, bool $customer = false, bool $supplier = false, bool $employee = false): int
    {
        return (int) DB::table('partners')->insertGetId([
            'company_id' => $companyId,
            'name' => $name,
            'is_customer' => $customer ? 1 : 0,
            'is_supplier' => $supplier ? 1 : 0,
            'is_employee' => $employee ? 1 : 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
