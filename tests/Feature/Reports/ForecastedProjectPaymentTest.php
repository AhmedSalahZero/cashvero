<?php

namespace Tests\Feature\Reports;

use App\Http\Controllers\CashFlowReportController;
use App\Models\Company;
use App\Models\Contract;
use App\Models\SupplierInvoice;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * * صف "Forecasted Project Payment" في تقرير الكاش فلو بتاع العقد
 *
 * * كان فيه مشكلتين مع بعض :
 * *   1. الصف الرئيسي ما كانش بيجمع الـ total بتاع كل عمود — السطر اللي
 * *      بيكتب $result['suppliers'][$key]['total'][$week] كان متعلّق عليه
 * *      كومنت ، فالصف الأب كان بيفضل صفر مهما كان تحته أبناء بقيم ،
 * *      وكمان Total Cash Outflow / Net Cash / Accumulated كانوا بيتجاهلوه
 * *      لإن sumAllTotalKeys() بتقرا من ['total'] بالظبط
 * *   2. الأبناء كانوا بيتبنوا من عقد العميل نفسه (salesOrders) و باسم
 * *      العميل ، يعني الصف كان نسخة من "Forecasted Project Collection"
 * *      بس على ناحية المصروفات . المفروض يعرض عقود الموردين
 * *      (contracts.parent_id) و أوامر الشراء بتاعتهم
 *
 * * الاتنين بيتحلوا مع بعض : الدالة بقت بتنادي
 * * computeForecastedProjectCollection() المشتركة بـ contract_scope =
 * * supplier_children ، و الدالة دي أصلا بتكتب total الصف الأب
 *
 * * كل الداتا هنا بتتعمل جوه transaction و بتترجع في tearDown
 */
class ForecastedProjectPaymentTest extends TestCase
{
    private const ROW_KEY = 'Forecasted Project Payment';

    private const SUPPLIER_CONTRACT_ROW_KEY = 'Forecasted Suppliers Contract Payments';

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

        // شركة خاصة بالتست جوه الترانزاكشن — sales_orders.company_id عليها
        // foreign key على companies فما ينفعش رقم متخيّل
        $this->companyId = (int) DB::table('companies')->insertGetId([
            'name' => json_encode(['en' => 'Forecast Test Co', 'ar' => 'Forecast Test Co']),
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
            'name' => 'Test Project',
            'code' => 'c-'.uniqid(),
            'start_date' => '2026-09-17',
            'end_date' => '2026-11-17',
            'amount' => 0,
            'currency' => 'EGP',
            'exchange_rate' => 1,
        ], $attributes));
    }

    private function purchaseOrder(int $contractId, string $poNumber, float $amount, string $endDate = '2026-11-17', int $collectionDays = 0): int
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
            'collection_days_1' => $collectionDays,
        ]);
    }

    private function salesOrder(int $contractId, string $soNumber, float $amount, string $endDate = '2026-11-17'): int
    {
        return (int) DB::table('sales_orders')->insertGetId([
            'company_id' => $this->companyId,
            'contract_id' => $contractId,
            'so_number' => $soNumber,
            'amount' => $amount,
            'start_date_1' => '2026-09-17',
            'end_date_1' => $endDate,
            'execution_percentage_1' => 100,
            'execution_days_1' => 0,
            'collection_days_1' => 0,
        ]);
    }

    /**
     * * net_balance و invoice_status مالهمش لازمة لو اتبعتوا بالإيد —
     * * على الجدول trigger قبل الـ insert بيعيد حسابهم من
     * * (invoice_amount - المدفوع - الخصومات) ، فالتست بيبعت المدفوع و
     * * بيسيب الترايجر يوصل للرصيد و الحالة زي ما بيحصل في التطبيق
     */
    private function supplierInvoice(string $contractCode, string $poNumber, string $currency, float $amount, float $paidAmount = 0): int
    {
        return (int) DB::table('supplier_invoices')->insertGetId([
            'company_id' => $this->companyId,
            'supplier_id' => $this->supplierPartnerId,
            'currency' => $currency,
            'contract_code' => $contractCode,
            'purchases_order_number' => $poNumber,
            'invoice_number' => 'BILL-'.uniqid(),
            'invoice_amount' => $amount,
            'paid_amount' => $paidAmount,
            'invoice_date' => '2026-10-01',
            'invoice_due_date' => '2026-10-15',
        ]);
    }

    private function storedInvoice(int $id): object
    {
        return DB::table('supplier_invoices')->where('id', $id)->first();
    }

    /**
     * * السيناريو اللي اتبلّغ : عميل عنده عقد مورد واحد فيه أمر شراء واحد
     */
    private function buildBaseScenario(float $customerAmount = 10000, float $poAmount = 5000): array
    {
        $customerPartner = $this->partner('Cust. Test 2', false);
        $supplierPartner = $this->supplierPartnerId = $this->partner('Vendor Test 2');

        $customerContractId = $this->contract([
            'partner_id' => $customerPartner,
            'model_type' => 'Customer',
            'name' => 'Test 2',
            'code' => 'c-09-2026-CT2-'.$this->companyId,
            'amount' => $customerAmount,
        ]);
        $this->salesOrder($customerContractId, 'S00221', $customerAmount);

        $supplierContractCode = 's-09-2026-VT2-'.$this->companyId;
        $supplierContractId = $this->contract([
            'partner_id' => $supplierPartner,
            'model_type' => 'Supplier',
            'parent_id' => $customerContractId,
            'name' => 'Test 2',
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

    /* ───────────────────────── runners ───────────────────────── */

    /**
     * * نفس المدى اللي في البلاغ : من 17-09-2026 لحد 17-03-2027 شهري
     *
     * @return array<string,int> تاريخ => المفتاح الشهري في التقرير
     */
    private function datesWithWeekNumber(): array
    {
        $map = [];
        $cursor = \Carbon\Carbon::make('2026-09-17');
        $end = \Carbon\Carbon::make('2027-03-17');

        while ($cursor->lessThanOrEqualTo($end)) {
            $map[$cursor->format('Y-m-d')] = $cursor->month.'-'.$cursor->year;
            $cursor = $cursor->copy()->addDay();
        }

        return $map;
    }

    private function runRow(?int $contractId, string $currency = 'EGP'): array
    {
        $result = ['customers' => [], 'suppliers' => [], 'cash_expenses' => []];

        SupplierInvoice::getForecastedProjectPayment(
            $result,
            '2026-09-17',
            '2027-03-17',
            $currency,
            $this->companyId,
            $this->datesWithWeekNumber(),
            $contractId,
            \App\Models\ForeignExchangeRate::where('company_id', $this->companyId)->get(),
            'EGP',
        );

        return $result;
    }

    /** @return array<string,float> اسم الصف الفرعي => إجماليه */
    private function subRows(array $result): array
    {
        $row = $result['suppliers'][self::ROW_KEY] ?? [];
        $subRows = [];

        foreach ($row as $key => $value) {
            if ($key === 'total') {
                continue;
            }
            $subRows[$key] = (float) ($value['total'] ?? 0);
        }

        return $subRows;
    }

    private function parentTotals(array $result): array
    {
        return $result['suppliers'][self::ROW_KEY]['total'] ?? [];
    }

    /* ───────────── المشكلة رقم ٢ : عقد المورد مش عقد العميل ───────────── */

    public function test_the_sub_row_names_the_supplier_contract_not_the_customer_contract(): void
    {
        $this->buildBaseScenario();

        $subRows = $this->subRows($this->runRow($this->lastCustomerContractId()));

        $this->assertSame(['Vendor Test 2-Test 2' => 5000.0], $subRows);
    }

    public function test_the_customer_own_sales_order_no_longer_leaks_into_the_row(): void
    {
        $scenario = $this->buildBaseScenario(customerAmount: 10000, poAmount: 5000);

        $subRows = $this->subRows($this->runRow($scenario['customer_contract_id']));

        $this->assertArrayNotHasKey('Cust. Test 2-Test 2', $subRows);
        $this->assertNotContains(10000.0, $subRows, 'The customer contract amount must not appear on the payment side.');
    }

    public function test_every_supplier_contract_under_the_customer_contract_gets_its_own_sub_row(): void
    {
        $scenario = $this->buildBaseScenario();

        $secondSupplier = $this->partner('Second Vendor');
        $secondContractId = $this->contract([
            'partner_id' => $secondSupplier,
            'model_type' => 'Supplier',
            'parent_id' => $scenario['customer_contract_id'],
            'name' => 'Test 2',
            'code' => 's-09-2026-SV-'.$this->companyId,
            'amount' => 1500,
        ]);
        $this->purchaseOrder($secondContractId, 'P00257', 1500);

        $subRows = $this->subRows($this->runRow($scenario['customer_contract_id']));

        $this->assertEqualsCanonicalizing([
            'Vendor Test 2-Test 2' => 5000.0,
            'Second Vendor-Test 2' => 1500.0,
        ], $subRows);
        $this->assertCount(2, $subRows);
    }

    public function test_a_supplier_contract_belonging_to_another_customer_contract_is_not_picked_up(): void
    {
        $scenario = $this->buildBaseScenario();

        $otherCustomer = $this->contract([
            'partner_id' => $this->partner('Other Customer', false),
            'model_type' => 'Customer',
            'code' => 'c-other-'.$this->companyId,
            'amount' => 999,
        ]);
        $strayContractId = $this->contract([
            'partner_id' => $this->partner('Stray Vendor'),
            'model_type' => 'Supplier',
            'parent_id' => $otherCustomer,
            'name' => 'Stray',
            'code' => 's-stray-'.$this->companyId,
            'amount' => 777,
        ]);
        $this->purchaseOrder($strayContractId, 'P00999', 777);

        $subRows = $this->subRows($this->runRow($scenario['customer_contract_id']));

        $this->assertSame(['Vendor Test 2-Test 2' => 5000.0], $subRows);
    }

    public function test_a_customer_child_contract_is_ignored_only_supplier_children_count(): void
    {
        $scenario = $this->buildBaseScenario();

        $childCustomerId = $this->contract([
            'partner_id' => $this->partner('Child Customer', false),
            'model_type' => 'Customer',
            'parent_id' => $scenario['customer_contract_id'],
            'name' => 'Child',
            'code' => 'c-child-'.$this->companyId,
            'amount' => 2500,
        ]);
        $this->purchaseOrder($childCustomerId, 'P00555', 2500);

        $this->assertSame(['Vendor Test 2-Test 2' => 5000.0], $this->subRows($this->runRow($scenario['customer_contract_id'])));
    }

    /* ───────────── المشكلة رقم ١ : الصف الأب ما كانش بيجمع ───────────── */

    public function test_the_parent_row_carries_a_total_per_period(): void
    {
        $scenario = $this->buildBaseScenario();

        $result = $this->runRow($scenario['customer_contract_id']);

        $this->assertSame(['11-2026' => 5000.0], $this->parentTotals($result));
    }

    public function test_the_parent_total_is_the_sum_of_its_sub_rows(): void
    {
        $scenario = $this->buildBaseScenario();

        $secondContractId = $this->contract([
            'partner_id' => $this->partner('Second Vendor'),
            'model_type' => 'Supplier',
            'parent_id' => $scenario['customer_contract_id'],
            'name' => 'Test 2',
            'code' => 's-second-'.$this->companyId,
            'amount' => 1500,
        ]);
        $this->purchaseOrder($secondContractId, 'P00257', 1500);

        $result = $this->runRow($scenario['customer_contract_id']);

        $this->assertSame(6500.0, array_sum($this->parentTotals($result)));
        $this->assertSame(array_sum($this->subRows($result)), array_sum($this->parentTotals($result)));
    }

    public function test_purchase_orders_landing_in_different_months_keep_separate_period_totals(): void
    {
        $scenario = $this->buildBaseScenario();

        $lateContractId = $this->contract([
            'partner_id' => $this->partner('Late Vendor'),
            'model_type' => 'Supplier',
            'parent_id' => $scenario['customer_contract_id'],
            'name' => 'Late',
            'code' => 's-late-'.$this->companyId,
            'amount' => 800,
            'end_date' => '2027-01-20',
        ]);
        $this->purchaseOrder($lateContractId, 'P00258', 800, '2027-01-20');

        $totals = $this->parentTotals($this->runRow($scenario['customer_contract_id']));

        $this->assertSame(['11-2026' => 5000.0, '1-2027' => 800.0], $totals);
    }

    /* ───────────── الصيغة نفسها ───────────── */

    public function test_an_open_supplier_invoice_reduces_the_forecast_by_its_net_balance(): void
    {
        $scenario = $this->buildBaseScenario();
        $invoiceId = $this->supplierInvoice($scenario['supplier_contract_code'], 'P00256', 'EGP', 2000);

        $this->assertSame(2000.0, (float) $this->storedInvoice($invoiceId)->net_balance);
        $this->assertSame(['Vendor Test 2-Test 2' => 3000.0], $this->subRows($this->runRow($scenario['customer_contract_id'])));
    }

    public function test_a_partially_settled_supplier_invoice_only_deducts_what_is_left(): void
    {
        $scenario = $this->buildBaseScenario();
        $invoiceId = $this->supplierInvoice($scenario['supplier_contract_code'], 'P00256', 'EGP', 2000, 1250);

        $stored = $this->storedInvoice($invoiceId);
        $this->assertSame(750.0, (float) $stored->net_balance);
        $this->assertNotSame(SupplierInvoice::COLLETED_OR_PAID, $stored->invoice_status);
        $this->assertSame(['Vendor Test 2-Test 2' => 4250.0], $this->subRows($this->runRow($scenario['customer_contract_id'])));
    }

    public function test_a_fully_paid_supplier_invoice_is_excluded_like_on_the_collection_side(): void
    {
        $scenario = $this->buildBaseScenario();
        $invoiceId = $this->supplierInvoice($scenario['supplier_contract_code'], 'P00256', 'EGP', 2000, 2000);

        $stored = $this->storedInvoice($invoiceId);
        $this->assertSame(0.0, (float) $stored->net_balance);
        $this->assertSame(SupplierInvoice::COLLETED_OR_PAID, $stored->invoice_status);
        $this->assertSame(['Vendor Test 2-Test 2' => 5000.0], $this->subRows($this->runRow($scenario['customer_contract_id'])));
    }

    public function test_an_invoice_on_another_purchase_order_does_not_touch_this_one(): void
    {
        $scenario = $this->buildBaseScenario();
        $this->supplierInvoice($scenario['supplier_contract_code'], 'P00999', 'EGP', 2000);

        $this->assertSame(['Vendor Test 2-Test 2' => 5000.0], $this->subRows($this->runRow($scenario['customer_contract_id'])));
    }

    public function test_an_unused_down_payment_balance_reduces_the_forecast(): void
    {
        $scenario = $this->buildBaseScenario();

        DB::table('down_payment_money_payment_settlements')->insert([
            'company_id' => $this->companyId,
            'contract_id' => $scenario['supplier_contract_id'],
            'purchase_order_id' => $scenario['purchase_order_id'],
            'down_payment_amount' => '1200',
            'down_payment_balance' => 1200,
            'currency' => 'EGP',
        ]);

        $this->assertSame(['Vendor Test 2-Test 2' => 3800.0], $this->subRows($this->runRow($scenario['customer_contract_id'])));
    }

    public function test_the_forecast_never_goes_negative(): void
    {
        $scenario = $this->buildBaseScenario();
        $this->supplierInvoice($scenario['supplier_contract_code'], 'P00256', 'EGP', 9000);

        $this->assertSame(['Vendor Test 2-Test 2' => 0.0], $this->subRows($this->runRow($scenario['customer_contract_id'])));
    }

    /* ───────────── العملات ───────────── */

    /**
     * * التقرير بيتعرض تبويب لكل عملة. عقد المورّد اللي بعملة التبويب
     * * المعروض بيتعرض بأرقامه زي ما هي (سعر = ١) ، و اللي بعملة تانية
     * * بيتحوّل للعملة الرئيسية. الصف ده كان بيحسب التحويل بنفسه جوه
     * * SupplierInvoice قبل ما ينتقل للدالة المشتركة — التست ده بيتأكد
     * * إن السلوك اتنقل معاه فعليا ، مش بس إن النص موجود في الملف.
     */
    public function test_the_row_is_drawn_in_the_currency_of_the_tab(): void
    {
        $scenario = $this->buildBaseScenario();

        $foreignContractId = $this->contract([
            'partner_id' => $this->partner('Foreign Vendor'),
            'model_type' => 'Supplier',
            'parent_id' => $scenario['customer_contract_id'],
            'name' => 'Test 2',
            'code' => 's-fx-tab-'.$this->companyId,
            'amount' => 100,
            'currency' => 'USD',
        ]);
        $this->purchaseOrder($foreignContractId, 'P00310', 100);

        DB::table('foreign_exchange_rates')->insert([
            'company_id' => $this->companyId,
            'from_currency' => 'USD',
            'to_currency' => 'EGP',
            'date' => '2026-11-17',
            'exchange_rate' => 50,
        ]);

        // تبويب الدولار: عقد المورّد بالدولار أصلا ، فيتعرض زي ما هو
        $onUsdTab = $this->subRows($this->runRow($scenario['customer_contract_id'], 'USD'));
        $this->assertSame(100.0, $onUsdTab['Foreign Vendor-Test 2'] ?? null, 'سعر ١ لما العملة هي عملة التبويب');

        // تبويب الجنيه: يتحوّل بسعر الصرف
        $onEgpTab = $this->subRows($this->runRow($scenario['customer_contract_id'], 'EGP'));
        $this->assertSame(5000.0, $onEgpTab['Foreign Vendor-Test 2'] ?? null, '١٠٠ دولار × ٥٠');
    }

    public function test_a_supplier_contract_in_a_different_currency_is_still_included(): void
    {
        $scenario = $this->buildBaseScenario();

        $foreignContractId = $this->contract([
            'partner_id' => $this->partner('Foreign Vendor'),
            'model_type' => 'Supplier',
            'parent_id' => $scenario['customer_contract_id'],
            'name' => 'Test 2',
            'code' => 's-fx-'.$this->companyId,
            'amount' => 100,
            'currency' => 'USD',
        ]);
        $this->purchaseOrder($foreignContractId, 'P00300', 100);

        $subRows = $this->subRows($this->runRow($scenario['customer_contract_id']));

        $this->assertArrayHasKey('Foreign Vendor-Test 2', $subRows);
        $this->assertGreaterThan(0, $subRows['Foreign Vendor-Test 2']);
    }

    public function test_a_supplier_invoice_in_a_different_currency_does_not_deduct_from_a_local_purchase_order(): void
    {
        $scenario = $this->buildBaseScenario();
        $this->supplierInvoice($scenario['supplier_contract_code'], 'P00256', 'USD', 100);

        $this->assertSame(['Vendor Test 2-Test 2' => 5000.0], $this->subRows($this->runRow($scenario['customer_contract_id'])));
    }

    /* ───────────── الحدود ───────────── */

    public function test_nothing_is_produced_without_a_contract_the_company_wide_report_is_untouched(): void
    {
        $this->buildBaseScenario();

        $this->assertArrayNotHasKey(self::ROW_KEY, $this->runRow(null)['suppliers']);
    }

    public function test_a_purchase_order_collecting_outside_the_window_is_skipped(): void
    {
        $scenario = $this->buildBaseScenario();

        $outsideContractId = $this->contract([
            'partner_id' => $this->partner('Outside Vendor'),
            'model_type' => 'Supplier',
            'parent_id' => $scenario['customer_contract_id'],
            'name' => 'Outside',
            'code' => 's-out-'.$this->companyId,
            'amount' => 4000,
            'end_date' => '2026-10-10',
        ]);
        // ينتهي جوه المدى بس بعد إضافة أيام التحصيل بيقع بره
        $this->purchaseOrder($outsideContractId, 'P00301', 4000, '2026-10-10', 300);

        $this->assertSame(['Vendor Test 2-Test 2' => 5000.0], $this->subRows($this->runRow($scenario['customer_contract_id'])));
    }

    public function test_a_purchase_order_with_no_executed_phase_is_skipped(): void
    {
        $scenario = $this->buildBaseScenario();

        $emptyContractId = $this->contract([
            'partner_id' => $this->partner('Empty Vendor'),
            'model_type' => 'Supplier',
            'parent_id' => $scenario['customer_contract_id'],
            'name' => 'Empty',
            'code' => 's-empty-'.$this->companyId,
            'amount' => 3300,
        ]);
        DB::table('purchase_orders')->insert([
            'company_id' => $this->companyId,
            'contract_id' => $emptyContractId,
            'po_number' => 'P00302',
            'amount' => 3300,
            'start_date_1' => '2026-09-17',
            'end_date_1' => '2026-11-17',
            'execution_percentage_1' => 0,
        ]);

        $this->assertSame(['Vendor Test 2-Test 2' => 5000.0], $this->subRows($this->runRow($scenario['customer_contract_id'])));
    }

    /* ───────── عقد المورّد بتنفيذ شهري (خاص بـ cashvero) ───────── */

    /**
     * * عقد بتنفيذ شهري مالوش أوامر أصلاً — المتبقي منه بيتوزّع على
     * * شهوره. لازم عقود الموردين الشهرية تعدّي من نفس المسار ده و
     * * تتسمّى باسم المورّد زي أي عقد مورّد تاني
     */
    public function test_a_monthly_executed_supplier_contract_is_spread_over_its_months(): void
    {
        $scenario = $this->buildBaseScenario();

        $monthlyContractId = $this->contract([
            'partner_id' => $this->partner('Monthly Vendor'),
            'model_type' => 'Supplier',
            'parent_id' => $scenario['customer_contract_id'],
            'name' => 'Test 2',
            'code' => 's-monthly-'.$this->companyId,
            'amount' => 3000,
            'start_date' => '2026-09-17',
            'end_date' => '2026-12-17',
            'is_monthly_executed' => 1,
        ]);

        $result = $this->runRow($scenario['customer_contract_id']);
        $subRows = $this->subRows($result);

        $this->assertArrayHasKey('Monthly Vendor-Test 2', $subRows, 'العقد الشهري بيدخل زي أي عقد مورّد');
        $this->assertGreaterThan(0, $subRows['Monthly Vendor-Test 2']);

        $this->assertSame(
            round(array_sum($this->parentTotals($result)), 2),
            round(array_sum($subRows), 2),
            'إجمالي الصف الأب لسه بيساوي مجموع أبنائه'
        );
    }

    public function test_a_monthly_executed_contract_of_another_customer_is_not_picked_up(): void
    {
        $scenario = $this->buildBaseScenario();

        $otherCustomerId = $this->contract([
            'partner_id' => $this->partner('Other Customer', false),
            'model_type' => 'Customer',
            'code' => 'c-other-monthly-'.$this->companyId,
            'amount' => 999,
        ]);
        $this->contract([
            'partner_id' => $this->partner('Stray Monthly Vendor'),
            'model_type' => 'Supplier',
            'parent_id' => $otherCustomerId,
            'name' => 'Stray',
            'code' => 's-stray-monthly-'.$this->companyId,
            'amount' => 4000,
            'start_date' => '2026-09-17',
            'end_date' => '2026-12-17',
            'is_monthly_executed' => 1,
        ]);

        $this->assertSame(['Vendor Test 2-Test 2' => 5000.0], $this->subRows($this->runRow($scenario['customer_contract_id'])));
    }

    /* ───────── ما يتعدّش مرتين مع صف الـ po_allocations ───────── */

    public function test_a_purchase_order_already_allocated_to_this_contract_is_not_counted_twice(): void
    {
        $scenario = $this->buildBaseScenario();

        DB::table('po_allocations')->insert([
            'contract_id' => $scenario['customer_contract_id'],
            'purchase_order_id' => $scenario['purchase_order_id'],
            'partner_id' => null,
            'allocation_percentage' => 100,
            'allocation_amount' => 5000,
        ]);

        $this->assertSame([], $this->subRows($this->runRow($scenario['customer_contract_id'])));
    }

    public function test_an_allocation_on_a_different_contract_does_not_hide_the_purchase_order(): void
    {
        $scenario = $this->buildBaseScenario();

        DB::table('po_allocations')->insert([
            'contract_id' => $scenario['customer_contract_id'] + 900000,
            'purchase_order_id' => $scenario['purchase_order_id'],
            'partner_id' => null,
            'allocation_percentage' => 100,
            'allocation_amount' => 5000,
        ]);

        $this->assertSame(['Vendor Test 2-Test 2' => 5000.0], $this->subRows($this->runRow($scenario['customer_contract_id'])));
    }

    /* ───────── الصف التاني (po_allocations) ما اتغيّرش ───────── */

    public function test_the_po_allocations_forecast_row_is_left_alone(): void
    {
        $scenario = $this->buildBaseScenario();

        $result = ['customers' => [], 'suppliers' => [], 'cash_expenses' => []];
        SupplierInvoice::getForecastedProjectCollection(
            $result,
            '2026-09-17',
            '2027-03-17',
            'EGP',
            $this->companyId,
            $this->datesWithWeekNumber(),
            $scenario['customer_contract_id'],
            collect([]),
            'EGP',
            collect([]),
        );

        // عقد العميل نفسه ملوش أوامر شراء و مفيش allocations ، فالصف فاضي
        $this->assertArrayNotHasKey(self::SUPPLIER_CONTRACT_ROW_KEY, $result['suppliers']);
    }

    public function test_the_customer_forecast_row_still_reads_the_customer_own_sales_orders(): void
    {
        $scenario = $this->buildBaseScenario();

        $result = ['customers' => [], 'suppliers' => [], 'cash_expenses' => []];
        \App\Models\CustomerInvoice::getForecastedProjectCollection(
            $result,
            '2026-09-17',
            '2027-03-17',
            'EGP',
            $this->companyId,
            $this->datesWithWeekNumber(),
            $scenario['customer_contract_id'],
            collect([]),
            'EGP',
        );

        $row = $result['customers']['Forecasted Project Collection'] ?? [];

        $this->assertSame(['11-2026' => 10000.0], $row['total'] ?? []);
        $this->assertSame(10000.0, (float) ($row['Cust. Test 2-Test 2']['total'] ?? 0));
    }

    /* ───────── الوصلة مع Total Cash Outflow ───────── */

    public function test_the_row_now_reaches_total_cash_outflow_and_net_cash(): void
    {
        $scenario = $this->buildBaseScenario();

        $company = new Company;
        $company->forceFill(['id' => $this->companyId, 'main_functional_currency' => 'EGP']);

        $datesWithWeekNumber = $this->datesWithWeekNumber();
        $weeks = [];
        foreach ($datesWithWeekNumber as $period) {
            $weeks[$period] = $period;
        }

        $result = ['customers' => [], 'suppliers' => [], 'cash_expenses' => []];

        (new CashFlowReportController)->finalizeContractCashFlowTotals(
            $result,
            $company,
            'EGP',
            'c-09-2026-CT2-'.$this->companyId,
            $datesWithWeekNumber,
            $weeks,
            0,
            true,
            $scenario['customer_contract_id'],
            '2026-09-17',
            '2027-03-17',
            collect([]),
            [],
            [],
            [],
            collect([]),
            'EGP',
        );

        $outflow = $result['cash_expenses'][__('Total Cash Outflow')]['total'] ?? [];
        $netCash = $result['cash_expenses'][__('Net Cash (+/-)')]['total'] ?? [];

        $this->assertSame(5000.0, (float) ($outflow['11-2026'] ?? 0));
        $this->assertSame(-5000.0, (float) ($netCash['11-2026'] ?? 0));
    }

    /* ───────────────────────── helpers ───────────────────────── */

    private function lastCustomerContractId(): int
    {
        return (int) DB::table('contracts')
            ->where('company_id', $this->companyId)
            ->where('model_type', 'Customer')
            ->orderByDesc('id')
            ->value('id');
    }
}
