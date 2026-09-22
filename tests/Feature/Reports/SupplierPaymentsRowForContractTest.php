<?php

namespace Tests\Feature\Reports;

use App\Models\Contract;
use App\Models\MoneyPayment;
use App\Services\Reports\CashFlowContractDetailPeriodBatchLoader;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * * صفوف مدفوعات الموردين في تقرير كاش فلو العقد
 * * (Outgoing Transfers / Cash Payments / Payable Cheques)
 *
 * * امتداد لنفس الباج بتاع صف "Suppliers Invoices" : الصفوف دي كانت
 * * بتوصل لأوامر شراء الموردين من جدول po_allocations بس ، و كمان
 * * المسار كله كان متلغّي لو العقد مالوش ولا صف allocation :
 * *
 * *     if ($poAllocations !== null && $poAllocations->isNotEmpty()) { ... }
 * *
 * * فعقد مورد مربوط بالطريقة العادية (contracts.parent_id) ما كانتش
 * * مدفوعاته بتظهر خالص .
 *
 * * و ده كان بيخلّي تصليح صف الفواتير لوحده بينقل المشكلة خطوة قدام
 * * مش بيحلها : الفاتورة تظهر و هي مش مدفوعة ، و أول ما تتدفع تختفي من
 * * التقرير — بتخرج من صف الفواتير لأن net_balance بقى صفر ، و ما
 * * بتظهرش هنا .
 *
 * * كل الداتا هنا بتتعمل جوه transaction و بتترجع في tearDown
 */
class SupplierPaymentsRowForContractTest extends TestCase
{
    private const PERIOD_START = '2026-09-01';

    private const PERIOD_END = '2026-12-31';

    /** * بُكِت واحد شهري يغطّي المدى كله عشان التأكيدات تبقى بسيطة */
    private const PERIODS = ['q-2026' => ['start_date' => self::PERIOD_START, 'end_date' => self::PERIOD_END]];

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
            'name' => json_encode(['en' => 'Supplier Pay Co', 'ar' => 'Supplier Pay Co']),
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
            'start_date' => '2026-09-01',
            'end_date' => '2026-11-30',
            'amount' => 0,
            'currency' => 'EGP',
            'exchange_rate' => 1,
            // * عقد عادي — العقد بتنفيذ شهري ماعندهوش أوامر أصلاً
            'is_monthly_executed' => 0,
        ], $attributes));
    }

    private function purchaseOrder(int $contractId, string $poNumber, float $amount): int
    {
        return (int) DB::table('purchase_orders')->insertGetId([
            'company_id' => $this->companyId,
            'contract_id' => $contractId,
            'po_number' => $poNumber,
            'amount' => $amount,
            'start_date_1' => '2026-09-01',
            'end_date_1' => '2026-11-30',
            'execution_percentage_1' => 100,
            'execution_days_1' => 0,
            'collection_days_1' => 0,
        ]);
    }

    private function supplierInvoice(string $contractCode, string $poNumber, float $amount, float $paidAmount = 0): int
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
            'invoice_due_date' => '2026-10-15',
        ]);
    }

    private function moneyPayment(string $type, float $amount, string $deliveryDate = '2026-10-20'): int
    {
        return (int) DB::table('money_payments')->insertGetId([
            'company_id' => $this->companyId,
            'partner_id' => $this->supplierPartnerId,
            'type' => $type,
            'delivery_date' => $deliveryDate,
            'paid_amount' => $amount,
            'currency' => 'EGP',
            'payment_currency' => 'EGP',
            'exchange_rate' => 1,
        ]);
    }

    /** * الربط اللي بتكتبه شاشة "تسوية الفاتورة" العادية */
    private function settleViaPaymentSettlements(int $moneyPaymentId, int $invoiceId, float $amount): void
    {
        DB::table('payment_settlements')->insert([
            'money_payment_id' => $moneyPaymentId,
            'invoice_id' => $invoiceId,
            'partner_id' => $this->supplierPartnerId,
            'settlement_amount' => $amount,
            'withhold_amount' => 0,
            'is_from_down_payment' => 0,
        ]);
    }

    /** * الربط التاني اللي بتكتبه شاشات تانية */
    private function settleViaSettlementAllocations(int $moneyPaymentId, int $invoiceId, float $amount): void
    {
        DB::table('settlement_allocations')->insert([
            'money_payment_id' => $moneyPaymentId,
            'invoice_id' => $invoiceId,
            'partner_id' => $this->supplierPartnerId,
            'allocation_amount' => $amount,
        ]);
    }

    private function allocate(int $customerContractId, int $purchaseOrderId, float $percentage): void
    {
        DB::table('po_allocations')->insert([
            'contract_id' => $customerContractId,
            'purchase_order_id' => $purchaseOrderId,
            'allocation_percentage' => $percentage,
            'allocation_amount' => 0,
        ]);
    }

    /**
     * * نفس السيناريو المبلّغ : عقد عميل تحته عقد مورد فيه أمر شراء
     * * واحد و فاتورة عليه — من غير أي صف في po_allocations
     */
    private function buildScenario(float $poAmount = 5000): array
    {
        $customerPartner = $this->partner('Cust. Test 2', false);
        $this->supplierPartnerId = $this->partner('Vendor Test 2');

        $customerContractCode = 'c-09-2026-CT2-'.$this->companyId;
        $customerContractId = $this->contract([
            'partner_id' => $customerPartner,
            'model_type' => 'Customer',
            'code' => $customerContractCode,
            'amount' => 10000,
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
        $invoiceId = $this->supplierInvoice($supplierContractCode, 'P00256', $poAmount);

        return [
            'customer_contract_id' => $customerContractId,
            'customer_contract_code' => $customerContractCode,
            'customer_partner_id' => $customerPartner,
            'supplier_contract_id' => $supplierContractId,
            'supplier_contract_code' => $supplierContractCode,
            'purchase_order_id' => $purchaseOrderId,
            'invoice_id' => $invoiceId,
        ];
    }

    /* ───────────────────────── runner ───────────────────────── */

    /** @return array<string,float> اسم الصف => إجماليه في البُكِت */
    private function supplierRows(array $scenario): array
    {
        $result = ['customers' => [], 'suppliers' => [], 'cash_expenses' => []];
        $letterOfGuaranteeModelData = [];
        $incomingTransferModelData = [];

        $poAllocations = \App\Models\PoAllocation::withSupplierPurchaseOrderDetails()
            ->where('po_allocations.contract_id', $scenario['customer_contract_id'])
            ->get();

        CashFlowContractDetailPeriodBatchLoader::apply(
            $result,
            $letterOfGuaranteeModelData,
            collect([]),
            'EGP',
            $this->companyId,
            $scenario['customer_contract_code'],
            $scenario['customer_contract_id'],
            $scenario['customer_partner_id'],
            self::PERIOD_START,
            self::PERIOD_END,
            self::PERIODS,
            $incomingTransferModelData,
            $poAllocations,
        );

        $rows = [];
        foreach ($result['suppliers'] as $rowKey => $value) {
            $total = (float) ($value['total']['q-2026'] ?? 0);
            if ($total != 0.0) {
                $rows[$rowKey] = round($total, 2);
            }
        }

        return $rows;
    }

    /* ───────────── الباج نفسه ───────────── */

    public function test_a_payment_on_a_child_supplier_contract_shows_in_the_row(): void
    {
        $scenario = $this->buildScenario();
        $payment = $this->moneyPayment(MoneyPayment::OUTGOING_TRANSFER, 5000);
        $this->settleViaPaymentSettlements($payment, $scenario['invoice_id'], 5000);

        $this->assertSame(['Outgoing Transfers' => 5000.0], $this->supplierRows($scenario));
    }

    public function test_a_cash_payment_lands_in_its_own_row(): void
    {
        $scenario = $this->buildScenario();
        $payment = $this->moneyPayment(MoneyPayment::CASH_PAYMENT, 3000);
        $this->settleViaPaymentSettlements($payment, $scenario['invoice_id'], 3000);

        $this->assertSame(['Cash Payments' => 3000.0], $this->supplierRows($scenario));
    }

    /**
     * * الربط ممكن يتكتب في أي من الجدولين حسب الشاشة اللي اتعمل منها
     * * التسوية — الاتنين لازم يتقروا
     */
    public function test_a_payment_linked_through_settlement_allocations_also_shows(): void
    {
        $scenario = $this->buildScenario();
        $payment = $this->moneyPayment(MoneyPayment::OUTGOING_TRANSFER, 5000);
        $this->settleViaSettlementAllocations($payment, $scenario['invoice_id'], 5000);

        $this->assertSame(['Outgoing Transfers' => 5000.0], $this->supplierRows($scenario));
    }

    public function test_a_partial_payment_shows_only_what_was_settled(): void
    {
        $scenario = $this->buildScenario();
        $payment = $this->moneyPayment(MoneyPayment::OUTGOING_TRANSFER, 2000);
        $this->settleViaPaymentSettlements($payment, $scenario['invoice_id'], 2000);

        $this->assertSame(['Outgoing Transfers' => 2000.0], $this->supplierRows($scenario));
    }

    /* ───────────── الفلترة ───────────── */

    public function test_a_payment_outside_the_period_does_not_show(): void
    {
        $scenario = $this->buildScenario();
        $payment = $this->moneyPayment(MoneyPayment::OUTGOING_TRANSFER, 5000, deliveryDate: '2027-05-10');
        $this->settleViaPaymentSettlements($payment, $scenario['invoice_id'], 5000);

        $this->assertSame([], $this->supplierRows($scenario));
    }

    /**
     * * تسوية جاية من دفعة مقدّمة مش حركة كاش جديدة — الفلوس خرجت
     * * فعلاً وقت ما الدفعة المقدّمة اتدفعت
     */
    public function test_a_down_payment_sourced_settlement_is_excluded(): void
    {
        $scenario = $this->buildScenario();
        $payment = $this->moneyPayment(MoneyPayment::OUTGOING_TRANSFER, 5000);
        DB::table('payment_settlements')->insert([
            'money_payment_id' => $payment,
            'invoice_id' => $scenario['invoice_id'],
            'partner_id' => $this->supplierPartnerId,
            'settlement_amount' => 5000,
            'withhold_amount' => 0,
            'is_from_down_payment' => 1,
        ]);

        $this->assertSame([], $this->supplierRows($scenario));
    }

    public function test_a_payment_on_another_customers_supplier_contract_is_not_picked_up(): void
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
        $strayInvoiceId = $this->supplierInvoice($strayCode, 'P00999', 4000);

        $payment = $this->moneyPayment(MoneyPayment::OUTGOING_TRANSFER, 4000);
        $this->settleViaPaymentSettlements($payment, $strayInvoiceId, 4000);

        $this->assertSame([], $this->supplierRows($scenario));
    }

    public function test_nothing_is_produced_when_no_payment_settled_the_invoice(): void
    {
        $scenario = $this->buildScenario();
        $this->moneyPayment(MoneyPayment::OUTGOING_TRANSFER, 5000);

        $this->assertSame([], $this->supplierRows($scenario));
    }

    /* ───────────── الوزن و الازدواج ───────────── */

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
        $standaloneInvoiceId = $this->supplierInvoice($standaloneCode, 'P00777', 8000);
        $this->allocate($scenario['customer_contract_id'], $standalonePoId, 25);

        $payment = $this->moneyPayment(MoneyPayment::OUTGOING_TRANSFER, 8000);
        $this->settleViaPaymentSettlements($payment, $standaloneInvoiceId, 8000);

        $this->assertSame(['Outgoing Transfers' => 2000.0], $this->supplierRows($scenario));
    }

    /**
     * * أمر شراء مربوط بالطريقتين لازم يتعدّ مرة واحدة ، و صف الـ
     * * allocation بيكسب لأنه هو اللي شايل النسبة
     */
    public function test_an_order_linked_both_ways_is_counted_once(): void
    {
        $scenario = $this->buildScenario();
        $this->allocate($scenario['customer_contract_id'], $scenario['purchase_order_id'], 60);

        $payment = $this->moneyPayment(MoneyPayment::OUTGOING_TRANSFER, 5000);
        $this->settleViaPaymentSettlements($payment, $scenario['invoice_id'], 5000);

        $this->assertSame(['Outgoing Transfers' => 3000.0], $this->supplierRows($scenario));
    }

    public function test_two_child_supplier_contracts_both_report_their_payments(): void
    {
        $scenario = $this->buildScenario();
        $firstPayment = $this->moneyPayment(MoneyPayment::OUTGOING_TRANSFER, 5000);
        $this->settleViaPaymentSettlements($firstPayment, $scenario['invoice_id'], 5000);

        $secondCode = 's-second-'.$this->companyId;
        $secondContractId = $this->contract([
            'partner_id' => $this->partner('Second Vendor'),
            'model_type' => 'Supplier',
            'parent_id' => $scenario['customer_contract_id'],
            'code' => $secondCode,
            'amount' => 1500,
        ]);
        $this->purchaseOrder($secondContractId, 'P00257', 1500);
        $secondInvoiceId = $this->supplierInvoice($secondCode, 'P00257', 1500);
        $secondPayment = $this->moneyPayment(MoneyPayment::CASH_PAYMENT, 1500);
        $this->settleViaPaymentSettlements($secondPayment, $secondInvoiceId, 1500);

        $this->assertSame([
            'Outgoing Transfers' => 5000.0,
            'Cash Payments' => 1500.0,
        ], $this->supplierRows($scenario));
    }

    /**
     * * السيناريو الكامل اللي التصليح ده موجود عشانه : الفاتورة تتدفع
     * * فتخرج من صف "Suppliers Invoices" (لأن net_balance بقى صفر) ،
     * * فلازم تظهر هنا — مش تختفي من التقرير خالص
     */
    public function test_a_paid_invoice_moves_from_the_invoices_row_to_the_payments_row(): void
    {
        $scenario = $this->buildScenario();

        DB::table('supplier_invoices')->where('id', $scenario['invoice_id'])->update(['paid_amount' => 5000]);
        $payment = $this->moneyPayment(MoneyPayment::OUTGOING_TRANSFER, 5000);
        $this->settleViaPaymentSettlements($payment, $scenario['invoice_id'], 5000);

        $invoiceNetBalance = (float) DB::table('supplier_invoices')->where('id', $scenario['invoice_id'])->value('net_balance');

        $this->assertSame(0.0, $invoiceNetBalance, 'الفاتورة اتدفعت بالكامل فخرجت من صف الفواتير');
        $this->assertSame(['Outgoing Transfers' => 5000.0], $this->supplierRows($scenario));
    }
}
