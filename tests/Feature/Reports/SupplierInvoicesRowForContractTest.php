<?php

namespace Tests\Feature\Reports;

use App\Models\Contract;
use App\Models\PoAllocation;
use App\Models\SupplierInvoice;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * * صف "Suppliers Invoices" في تقرير كاش فلو العقد
 *
 * * البلاغ : عقد عميل بـ 10,000 تحته عقد مورد بـ 5,000 و عليه فاتورة
 * * مورد بـ 5,000 لسه ما اتدفعتش و استحقاقها جوّه فترة التقرير . الصف
 * * كان بيطلع **فاضي** ، و في نفس الوقت صف "Forecasted Project Payment"
 * * بينزل لصفر لأن الفاتورة اتخصمت منه — فالـ 5,000 كانت بتختفي من
 * * التقرير بالكامل .
 *
 * * السبب : الصف كان بيدوّر على أوامر الشراء من جدول po_allocations بس
 * * (الربط الصريح الاختياري من مودال "Allocate") ، و ما كانش بيعرف
 * * الربط العادي contracts.parent_id — عقد المورد المتعمل تحت عقد
 * * العميل . و ده الربط اللي صف التوقّعات نفسه بيمشي عليه ، فالصفين
 * * كانوا بيشوفوا حاجتين مختلفتين .
 *
 * * المطلوب : الفاتورة تظهر بقيمتها في ميعاد استحقاقها ، و الفرق بين
 * * قيمة العقد و الفاتورة يفضل في صف التوقّعات .
 *
 * * كل الداتا هنا بتتعمل جوه transaction و بتترجع في tearDown
 */
class SupplierInvoicesRowForContractTest extends TestCase
{
    private const ROW_KEY = 'Suppliers Invoices';

    private const FORECAST_ROW_KEY = 'Forecasted Project Payment';

    private const REPORT_START = '2026-09-17';

    private const REPORT_END = '2027-03-17';

    private ?string $originalDatabase = null;

    private bool $inTransaction = false;

    private int $companyId = 0;

    private int $supplierPartnerId = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDatabase = config('database.connections.mysql.database');
        config(['database.connections.mysql.database' => env('SMOKE_DB', 'cashvero')]);
        DB::purge('mysql');

        try {
            DB::connection('mysql')->getPdo();
            DB::table('contracts')->limit(1)->get();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Development database not reachable.');
        }

        DB::beginTransaction();
        $this->inTransaction = true;

        $this->companyId = (int) DB::table('companies')->insertGetId([
            'name' => json_encode(['en' => 'Supplier Row Co', 'ar' => 'Supplier Row Co']),
            'main_functional_currency' => 'EGP',
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->inTransaction) {
            DB::rollBack();
            $this->inTransaction = false;
        }

        config(['database.connections.mysql.database' => $this->originalDatabase]);
        DB::purge('mysql');

        parent::tearDown();
    }

    /* ───────────────────────── builders ───────────────────────── */

    private function partner(string $name, bool $isSupplier = true): int
    {
        return (int) DB::table('partners')->insertGetId([
            'company_id' => $this->companyId,
            'name' => $name,
            'is_supplier' => $isSupplier ? 1 : 0,
            'is_customer' => $isSupplier ? 0 : 1,
        ]);
    }

    private function contract(array $attributes): int
    {
        return (int) DB::table('contracts')->insertGetId(array_merge([
            'company_id' => $this->companyId,
            'status' => Contract::RUNNING,
            'model_type' => 'Customer',
            'parent_id' => null,
            'name' => 'Test 2',
            'code' => 'c-'.uniqid(),
            'start_date' => '2026-09-17',
            'end_date' => '2026-11-17',
            'amount' => 0,
            'currency' => 'EGP',
            'exchange_rate' => 1,
            // * عقد عادي — العقد بتنفيذ شهري ماعندهوش أوامر أصلاً
            'is_monthly_executed' => 0,
        ], $attributes));
    }

    private function purchaseOrder(int $contractId, string $poNumber, float $amount, string $endDate = '2026-11-17'): int
    {
        return (int) DB::table('purchase_orders')->insertGetId([
            'company_id' => $this->companyId,
            'contract_id' => $contractId,
            'po_number' => $poNumber,
            'amount' => $amount,
            'start_date_1' => '2026-09-17',
            'end_date_1' => $endDate,
            'execution_percentage_1' => 100,
            'execution_days_1' => 0,
            'collection_days_1' => 0,
        ]);
    }

    /**
     * * net_balance و invoice_status بيتحسبوا من ترايجر على الجدول ، فالتست
     * * بيبعت قيمة الفاتورة و المدفوع و يسيب الترايجر يوصل للرصيد
     */
    private function supplierInvoice(string $contractCode, string $poNumber, float $amount, float $paidAmount = 0, string $dueDate = '2026-10-15'): int
    {
        return (int) DB::table('supplier_invoices')->insertGetId([
            'company_id' => $this->companyId,
            'supplier_id' => $this->supplierPartnerId,
            'supplier_name' => 'Vendor Test 2',
            'currency' => 'EGP',
            'exchange_rate' => 1,
            'contract_code' => $contractCode,
            'purchases_order_number' => $poNumber,
            'invoice_number' => 'BILL-'.uniqid(),
            'invoice_amount' => $amount,
            'paid_amount' => $paidAmount,
            'invoice_date' => '2026-09-20',
            'invoice_due_date' => $dueDate,
        ]);
    }

    /**
     * * السيناريو اللي اتبلّغ : عميل عنده عقد مورد واحد ابن فيه أمر شراء
     * * واحد — من غير أي صف في po_allocations
     */
    private function buildScenario(float $customerAmount = 10000, float $poAmount = 5000): array
    {
        $customerPartner = $this->partner('Cust. Test 2', false);
        $this->supplierPartnerId = $this->partner('Vendor Test 2');

        $customerContractId = $this->contract([
            'partner_id' => $customerPartner,
            'model_type' => 'Customer',
            'code' => 'c-09-2026-CT2-'.$this->companyId,
            'amount' => $customerAmount,
        ]);

        $supplierContractCode = 's-09-2026-VT2-'.$this->companyId;
        $supplierContractId = $this->contract([
            'partner_id' => $this->supplierPartnerId,
            'model_type' => 'Supplier',
            'parent_id' => $customerContractId,
            'code' => $supplierContractCode,
            'amount' => $poAmount,
        ]);
        $purchaseOrderId = $this->purchaseOrder($supplierContractId, 'P00256', $poAmount);

        return [
            'customer_contract_id' => $customerContractId,
            'supplier_contract_id' => $supplierContractId,
            'supplier_contract_code' => $supplierContractCode,
            'purchase_order_id' => $purchaseOrderId,
        ];
    }

    private function allocate(int $customerContractId, int $purchaseOrderId, float $percentage): void
    {
        // * po_allocations مالهاش company_id — بتتفلتر بالعقد
        DB::table('po_allocations')->insert([
            'contract_id' => $customerContractId,
            'purchase_order_id' => $purchaseOrderId,
            'allocation_percentage' => $percentage,
            'allocation_amount' => 0,
        ]);
    }

    /* ───────────────────────── runners ───────────────────────── */

    /** @return array<string,string> */
    private function datesWithWeekNumber(): array
    {
        $map = [];
        $cursor = \Carbon\Carbon::make(self::REPORT_START);
        $end = \Carbon\Carbon::make(self::REPORT_END);

        while ($cursor->lessThanOrEqualTo($end)) {
            $map[$cursor->format('Y-m-d')] = $cursor->month.'-'.$cursor->year;
            $cursor = $cursor->copy()->addDay();
        }

        return $map;
    }

    /**
     * * بيرجّع [صفوف الفواتير , لستة الفواتير المتأخرة]
     *
     * @return array{0:array<string,array<string,float>>,1:int}
     */
    private function runRow(?int $contractId): array
    {
        $result = ['customers' => [], 'suppliers' => [], 'cash_expenses' => []];
        $pastDue = collect([]);

        $poAllocations = $contractId
            ? PoAllocation::withSupplierPurchaseOrderDetails()->where('po_allocations.contract_id', $contractId)->get()
            : collect([]);

        SupplierInvoice::getSupplierInvoicesForPoUnderCollectionAtDates(
            $result,
            $this->companyId,
            $this->datesWithWeekNumber(),
            self::REPORT_START,
            self::REPORT_END,
            $poAllocations,
            $pastDue,
            $contractId,
        );

        $rows = [];
        foreach ($result['suppliers'][self::ROW_KEY] ?? [] as $label => $value) {
            if ($label === 'total') {
                continue;
            }
            $rows[$label] = array_map('floatval', $value['weeks'] ?? []);
        }

        return [$rows, $pastDue->count()];
    }

    /** @return array<string,float> اسم الصف => إجماليه */
    private function invoiceRows(?int $contractId): array
    {
        [$rows] = $this->runRow($contractId);

        return array_map(fn (array $weeks) => round(array_sum($weeks), 2), $rows);
    }

    private function forecastTotal(int $contractId): float
    {
        $result = ['customers' => [], 'suppliers' => [], 'cash_expenses' => []];

        SupplierInvoice::getForecastedProjectPayment(
            $result,
            self::REPORT_START,
            self::REPORT_END,
            'EGP',
            $this->companyId,
            $this->datesWithWeekNumber(),
            $contractId,
            collect([]),
            'EGP',
        );

        return round(array_sum($result['suppliers'][self::FORECAST_ROW_KEY]['total'] ?? []), 2);
    }

    /* ───────────── البلاغ نفسه ───────────── */

    public function test_an_invoice_on_a_child_supplier_contract_shows_in_the_row(): void
    {
        $scenario = $this->buildScenario();
        $this->supplierInvoice($scenario['supplier_contract_code'], 'P00256', 5000);

        $rows = $this->invoiceRows($scenario['customer_contract_id']);

        $this->assertCount(1, $rows, 'الفاتورة لازم تظهر من غير أي صف في po_allocations');
        $this->assertSame(5000.0, array_values($rows)[0]);
    }

    public function test_the_invoice_lands_in_the_month_of_its_due_date(): void
    {
        $scenario = $this->buildScenario();
        $this->supplierInvoice($scenario['supplier_contract_code'], 'P00256', 5000, dueDate: '2026-11-19');

        [$rows] = $this->runRow($scenario['customer_contract_id']);

        $this->assertSame(['11-2026' => 5000.0], array_values($rows)[0]);
    }

    public function test_the_row_is_labelled_with_the_invoice_and_supplier_name(): void
    {
        $scenario = $this->buildScenario();
        $this->supplierInvoice($scenario['supplier_contract_code'], 'P00256', 5000);

        $label = array_key_first($this->invoiceRows($scenario['customer_contract_id']));

        $this->assertStringContainsString('Vendor Test 2', $label);
        $this->assertStringContainsString('BILL-', $label);
    }

    /**
     * * المثال اللي صاحب المشروع طلبه بالنص : عقد بـ 10,000 عليه فاتورة
     * * بـ 7,000 → الفاتورة تظهر بـ 7,000 و الفرق 3,000 يفضل في صف
     * * التوقّعات .
     */
    public function test_the_invoice_and_the_remainder_add_up_to_the_contract(): void
    {
        $scenario = $this->buildScenario(customerAmount: 20000, poAmount: 10000);
        $this->supplierInvoice($scenario['supplier_contract_code'], 'P00256', 7000);

        $invoiced = array_sum($this->invoiceRows($scenario['customer_contract_id']));
        $forecast = $this->forecastTotal($scenario['customer_contract_id']);

        $this->assertSame(7000.0, $invoiced);
        $this->assertSame(3000.0, $forecast);
        $this->assertSame(10000.0, $invoiced + $forecast, 'الاتنين مع بعض لازم يساووا قيمة أمر الشراء');
    }

    public function test_a_fully_invoiced_order_leaves_nothing_in_the_forecast(): void
    {
        $scenario = $this->buildScenario();
        $this->supplierInvoice($scenario['supplier_contract_code'], 'P00256', 5000);

        $this->assertSame(5000.0, array_sum($this->invoiceRows($scenario['customer_contract_id'])));
        $this->assertSame(0.0, $this->forecastTotal($scenario['customer_contract_id']));
    }

    /* ───────────── حالات الفاتورة ───────────── */

    public function test_a_partially_paid_invoice_only_shows_what_is_left(): void
    {
        $scenario = $this->buildScenario();
        $this->supplierInvoice($scenario['supplier_contract_code'], 'P00256', 5000, paidAmount: 2000);

        $this->assertSame(3000.0, array_sum($this->invoiceRows($scenario['customer_contract_id'])));
    }

    public function test_a_fully_paid_invoice_does_not_show(): void
    {
        $scenario = $this->buildScenario();
        $this->supplierInvoice($scenario['supplier_contract_code'], 'P00256', 5000, paidAmount: 5000);

        $this->assertSame([], $this->invoiceRows($scenario['customer_contract_id']));
    }

    public function test_an_invoice_due_outside_the_window_does_not_show(): void
    {
        $scenario = $this->buildScenario();
        $this->supplierInvoice($scenario['supplier_contract_code'], 'P00256', 5000, dueDate: '2027-09-15');

        $this->assertSame([], $this->invoiceRows($scenario['customer_contract_id']));
    }

    /**
     * * الفاتورة المتأخرة بتتشال من الصف ده و بتروح لصف
     * * "Suppliers Past Due Invoices" — نفس معاملة فواتير الـ allocations
     */
    public function test_a_past_due_invoice_goes_to_the_past_due_list_instead(): void
    {
        $scenario = $this->buildScenario();
        $this->supplierInvoice($scenario['supplier_contract_code'], 'P00256', 5000, dueDate: '2026-09-18');

        [$rows, $pastDueCount] = $this->runRow($scenario['customer_contract_id']);

        $this->assertSame([], $rows);
        $this->assertSame(1, $pastDueCount);
    }

    /* ───────────── العزل ───────────── */

    public function test_an_invoice_on_another_customers_supplier_contract_is_not_picked_up(): void
    {
        $scenario = $this->buildScenario();

        $otherCustomerId = $this->contract([
            'partner_id' => $this->partner('Other Customer', false),
            'model_type' => 'Customer',
            'code' => 'c-other-'.$this->companyId,
        ]);
        $strayCode = 's-stray-'.$this->companyId;
        $strayContractId = $this->contract([
            'partner_id' => $this->partner('Stray Vendor'),
            'model_type' => 'Supplier',
            'parent_id' => $otherCustomerId,
            'code' => $strayCode,
            'amount' => 4000,
        ]);
        $this->purchaseOrder($strayContractId, 'P00999', 4000);
        $this->supplierInvoice($strayCode, 'P00999', 4000);

        $this->assertSame([], $this->invoiceRows($scenario['customer_contract_id']));
    }

    public function test_a_customer_child_contract_is_ignored_only_supplier_children_count(): void
    {
        $scenario = $this->buildScenario();

        $childCustomerCode = 'c-child-'.$this->companyId;
        $childCustomerId = $this->contract([
            'partner_id' => $this->partner('Child Customer', false),
            'model_type' => 'Customer',
            'parent_id' => $scenario['customer_contract_id'],
            'code' => $childCustomerCode,
            'amount' => 3000,
        ]);
        $this->purchaseOrder($childCustomerId, 'P00888', 3000);
        $this->supplierInvoice($childCustomerCode, 'P00888', 3000);

        $this->assertSame([], $this->invoiceRows($scenario['customer_contract_id']));
    }

    public function test_without_a_contract_id_only_allocations_are_read(): void
    {
        $scenario = $this->buildScenario();
        $this->supplierInvoice($scenario['supplier_contract_code'], 'P00256', 5000);

        // ده مسار تقرير الشركة — ما بيبعتش عقد ، فما بيمشيش على الأبناء
        $this->assertSame([], $this->invoiceRows(null));
    }

    /* ───────────── مسار po_allocations لسه شغال ───────────── */

    public function test_an_allocated_purchase_order_is_still_weighted_by_its_percentage(): void
    {
        $scenario = $this->buildScenario();

        // عقد مورد مش ابن — الربط الوحيد هو الـ allocation
        $standaloneCode = 's-standalone-'.$this->companyId;
        $standaloneContractId = $this->contract([
            'partner_id' => $this->partner('Allocated Vendor'),
            'model_type' => 'Supplier',
            'code' => $standaloneCode,
            'amount' => 8000,
        ]);
        $standalonePoId = $this->purchaseOrder($standaloneContractId, 'P00777', 8000);
        $this->supplierInvoice($standaloneCode, 'P00777', 8000);
        $this->allocate($scenario['customer_contract_id'], $standalonePoId, 25);

        $this->assertSame([2000.0], array_values($this->invoiceRows($scenario['customer_contract_id'])));
    }

    /**
     * * أمر شراء مربوط بالطريقتين لازم يتعدّ مرة واحدة ، و صف الـ
     * * allocation بيكسب لأنه هو اللي شايل النسبة — نفس القاعدة اللي في
     * * HasForecastedProjectCollection
     */
    public function test_an_order_linked_both_ways_is_counted_once(): void
    {
        $scenario = $this->buildScenario();
        $this->supplierInvoice($scenario['supplier_contract_code'], 'P00256', 5000);
        $this->allocate($scenario['customer_contract_id'], $scenario['purchase_order_id'], 60);

        $rows = $this->invoiceRows($scenario['customer_contract_id']);

        $this->assertCount(1, $rows);
        $this->assertSame(3000.0, array_values($rows)[0], 'نسبة الـ allocation هي اللي بتتطبّق مش 100%');
    }

    public function test_two_child_supplier_contracts_each_get_their_own_invoice_row(): void
    {
        $scenario = $this->buildScenario();
        $this->supplierInvoice($scenario['supplier_contract_code'], 'P00256', 5000);

        $secondCode = 's-second-'.$this->companyId;
        $secondContractId = $this->contract([
            'partner_id' => $this->partner('Second Vendor'),
            'model_type' => 'Supplier',
            'parent_id' => $scenario['customer_contract_id'],
            'code' => $secondCode,
            'amount' => 1500,
        ]);
        $this->purchaseOrder($secondContractId, 'P00257', 1500);
        $this->supplierInvoice($secondCode, 'P00257', 1500);

        $rows = $this->invoiceRows($scenario['customer_contract_id']);

        $this->assertCount(2, $rows);
        $this->assertSame(6500.0, array_sum($rows));
    }
}
