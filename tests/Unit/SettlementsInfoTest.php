<?php

namespace Tests\Unit;

use App\Models\CustomerInvoice;
use App\Models\DownPaymentSettlement;
use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use App\Models\PaymentSettlement;
use App\Models\Settlement;
use App\Models\SupplierInvoice;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use Tests\TestCase;

/**
 * * البوب اب اللي بيعرض الفواتير المسوّاة على الحركة — للقراءة فقط
 *
 * * كل الموديلات هنا من غير حفظ ، و العلاقات متحطوطة بالإيد بـ
 * * setRelation ، فمفيش داتابيز في الطريق
 */
class SettlementsInfoTest extends TestCase
{
    private function invoice(string $class, array $attributes): object
    {
        $invoice = new $class;
        $invoice->forceFill($attributes);

        return $invoice;
    }

    /**
     * * استلام ١٠٠٠ : فاتورتين اتسوّوا بـ ٦٠٠ و الباقي ٤٠٠ دفعة مقدمة
     */
    private function moneyReceivedWithTwoSettledInvoices(): MoneyReceived
    {
        $first = new Settlement;
        $first->forceFill(['settlement_amount' => 350, 'withhold_amount' => 15, 'is_from_down_payment' => 0]);
        $first->setRelation('invoice', $this->invoice(CustomerInvoice::class, [
            'invoice_number' => 'INV/2026/00001',
            'invoice_date' => '2026-01-10',
            'invoice_due_date' => '2026-02-10',
            'invoice_amount' => 350,
        ]));

        $second = new Settlement;
        $second->forceFill(['settlement_amount' => 250, 'withhold_amount' => 5, 'is_from_down_payment' => 1]);
        $second->setRelation('invoice', $this->invoice(CustomerInvoice::class, [
            'invoice_number' => 'INV/2026/00002',
            'invoice_date' => '2026-03-01',
            'invoice_due_date' => '2026-04-01',
            'invoice_amount' => 900,
        ]));

        $money = new MoneyReceived;
        $money->forceFill([
            'money_type' => MoneyReceived::INVOICE_SETTLEMENT_WITH_DOWN_PAYMENT,
            'received_amount' => 1000,
            'currency' => 'EGP',
        ]);
        $money->setRelation('settlements', collect([$first, $second]));

        $downPayment = new DownPaymentSettlement;
        $downPayment->forceFill(['down_payment_amount' => 400]);
        $money->setRelation('downPaymentSettlements', collect([$downPayment]));

        return $money;
    }

    public function test_every_requested_column_is_present_for_each_invoice(): void
    {
        $info = $this->moneyReceivedWithTwoSettledInvoices()->getSettlementsInfo();

        $this->assertCount(2, $info['rows']);

        foreach ($info['rows'] as $row) {
            foreach (['invoice_number', 'invoice_date', 'due_date', 'invoice_amount', 'settlement_amount', 'withhold_amount'] as $column) {
                $this->assertArrayHasKey($column, $row, 'العمود المطلوب ناقص: '.$column);
                $this->assertNotSame('', $row[$column]);
            }
        }
    }

    public function test_the_values_come_from_the_invoice_and_the_settlement(): void
    {
        $info = $this->moneyReceivedWithTwoSettledInvoices()->getSettlementsInfo();
        $first = $info['rows'][0];

        $this->assertSame('INV/2026/00001', $first['invoice_number']);
        $this->assertSame('10-01-2026', $first['invoice_date'], 'التاريخ بصيغة العرض');
        $this->assertSame('10-02-2026', $first['due_date']);
        $this->assertSame('350.00', $first['invoice_amount'], 'مبلغ الفاتورة من الفاتورة');
        $this->assertSame('350.00', $first['settlement_amount'], 'مبلغ التسوية من التسوية');
        $this->assertSame('15.00', $first['withhold_amount']);
        $this->assertFalse($first['is_from_down_payment']);
    }

    public function test_a_settlement_paid_from_an_older_down_payment_is_marked(): void
    {
        $info = $this->moneyReceivedWithTwoSettledInvoices()->getSettlementsInfo();

        $this->assertTrue($info['rows'][1]['is_from_down_payment'], 'التسوية الجاية من دفعة مقدمة قديمة لازم تتميّز');
        $this->assertSame('900.00', $info['rows'][1]['invoice_amount'], 'مبلغ الفاتورة مش مبلغ التسوية');
        $this->assertSame('250.00', $info['rows'][1]['settlement_amount']);
    }

    public function test_totals_and_the_down_payment_remainder(): void
    {
        $info = $this->moneyReceivedWithTwoSettledInvoices()->getSettlementsInfo();

        $this->assertSame('1,000.00', $info['total_amount'], 'المبلغ المستلم كامل');
        $this->assertSame('600.00', $info['total_settlement'], '350 + 250');
        $this->assertSame('20.00', $info['total_withhold'], '15 + 5');
        $this->assertSame('400.00', $info['down_payment_amount'], 'الباقي دفعة مقدمة');
        $this->assertSame('EGP', $info['currency']);
    }

    public function test_a_plain_receipt_without_a_remainder_reports_no_down_payment(): void
    {
        $settlement = new Settlement;
        $settlement->forceFill(['settlement_amount' => 1000, 'withhold_amount' => 0, 'is_from_down_payment' => 0]);
        $settlement->setRelation('invoice', $this->invoice(CustomerInvoice::class, [
            'invoice_number' => 'INV/2026/00009',
            'invoice_date' => '2026-05-01',
            'invoice_due_date' => '2026-06-01',
            'invoice_amount' => 1000,
        ]));

        $money = new MoneyReceived;
        $money->forceFill(['money_type' => 'money-received', 'received_amount' => 1000, 'currency' => 'EGP']);
        $money->setRelation('settlements', collect([$settlement]));

        $info = $money->getSettlementsInfo();

        $this->assertCount(1, $info['rows']);
        $this->assertNull($info['down_payment_amount'], 'مفيش متبقي يبقى مفيش دفعة مقدمة');
        $this->assertSame('1,000.00', $info['total_settlement']);
    }

    public function test_a_row_with_no_settlements_returns_an_empty_list_not_an_error(): void
    {
        $money = new MoneyReceived;
        $money->forceFill(['money_type' => 'money-received', 'received_amount' => 500, 'currency' => 'USD']);
        $money->setRelation('settlements', collect([]));

        $info = $money->getSettlementsInfo();

        $this->assertSame([], $info['rows']);
        $this->assertSame('0.00', $info['total_settlement']);
        $this->assertSame('0.00', $info['total_withhold']);
        $this->assertNull($info['down_payment_amount']);
    }

    public function test_a_settlement_whose_invoice_was_removed_does_not_break_the_popup(): void
    {
        $orphan = new Settlement;
        $orphan->forceFill(['settlement_amount' => 100, 'withhold_amount' => 0, 'is_from_down_payment' => 0]);
        $orphan->setRelation('invoice', null);

        $money = new MoneyReceived;
        $money->forceFill(['money_type' => 'money-received', 'received_amount' => 100, 'currency' => 'EGP']);
        $money->setRelation('settlements', collect([$orphan]));

        $info = $money->getSettlementsInfo();

        $this->assertSame(__('N/A'), $info['rows'][0]['invoice_number']);
        $this->assertSame('0.00', $info['rows'][0]['invoice_amount']);
        $this->assertSame('100.00', $info['rows'][0]['settlement_amount']);
    }

    /* ───────────── جهة الصرف ───────────── */

    public function test_money_payment_reports_supplier_invoices_the_same_way(): void
    {
        $settlement = new PaymentSettlement;
        $settlement->forceFill(['settlement_amount' => 600, 'withhold_amount' => 30, 'is_from_down_payment' => 0]);
        $settlement->setRelation('invoice', $this->invoice(SupplierInvoice::class, [
            'invoice_number' => 'BILL/2026/07/0022',
            'invoice_date' => '2026-07-09',
            'invoice_due_date' => '2026-08-09',
            'invoice_amount' => 600,
        ]));

        $money = new MoneyPayment;
        $money->forceFill([
            'money_type' => MoneyPayment::INVOICE_SETTLEMENT_WITH_DOWN_PAYMENT,
            'paid_amount' => 1000,
            'currency' => 'EGP',
        ]);
        $money->setRelation('settlements', collect([$settlement]));

        $downPayment = new \App\Models\DownPaymentMoneyPaymentSettlement;
        $downPayment->forceFill(['down_payment_amount' => 400]);
        $money->setRelation('downPaymentSettlements', collect([$downPayment]));

        $info = $money->getSettlementsInfo();

        $this->assertSame('BILL/2026/07/0022', $info['rows'][0]['invoice_number']);
        $this->assertSame('09-07-2026', $info['rows'][0]['invoice_date']);
        $this->assertSame('09-08-2026', $info['rows'][0]['due_date']);
        $this->assertSame('600.00', $info['rows'][0]['settlement_amount']);
        $this->assertSame('30.00', $info['rows'][0]['withhold_amount']);
        $this->assertSame('400.00', $info['down_payment_amount']);
    }

    /* ───────────── التوصيلات ───────────── */

    public function test_both_read_only_endpoints_are_registered(): void
    {
        foreach (['money.received.settlements.info', 'money.payment.settlements.info'] as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, 'الراوت مش متسجل: '.$name);
            $this->assertSame(['GET', 'HEAD'], $route->methods(), 'للقراءة فقط — GET بس');
        }
    }

    public function test_the_controllers_expose_the_endpoint(): void
    {
        foreach ([\App\Http\Controllers\MoneyReceivedController::class, \App\Http\Controllers\MoneyPaymentController::class] as $controller) {
            $this->assertTrue(
                (new ReflectionClass($controller))->hasMethod('settlementsInfo'),
                $controller.' لازم يكون فيه settlementsInfo'
            );
        }
    }

    /**
     * * كاشفيرو واجهته Inertia + Vue مش بليد ، فالزرار كومبوننت مشترك
     * * و الـ url بيتبعت مع صف الجدول من الكنترولر
     */
    public function test_the_vue_index_pages_render_the_shared_button(): void
    {
        $this->assertFileExists(resource_path('js/Components/SettlementsInfoButton.vue'));

        foreach (['MoneyReceived', 'MoneyPayment'] as $page) {
            $source = file_get_contents(resource_path('js/Pages/'.$page.'/Index.vue'));

            $this->assertStringContainsString(
                "import SettlementsInfoButton from '@/Components/SettlementsInfoButton.vue';",
                $source,
                $page.' لازم يستورد الكومبوننت'
            );
            $this->assertStringContainsString(
                '<SettlementsInfoButton v-if="row.settlements_info_url" :url="row.settlements_info_url" />',
                $source,
                $page.' لازم يعرض الزرار في عمود الأكشن'
            );
        }
    }

    public function test_the_controllers_send_the_url_with_every_row(): void
    {
        foreach ([
            ['MoneyReceivedController.php', 'money.received.settlements.info'],
            ['MoneyPaymentController.php', 'money.payment.settlements.info'],
        ] as [$file, $route]) {
            $source = file_get_contents(app_path('Http/Controllers/'.$file));

            $this->assertStringContainsString("'settlements_info_url' => route('".$route."'", $source, $file);
        }
    }

    public function test_the_vue_component_reads_every_requested_column(): void
    {
        $source = file_get_contents(resource_path('js/Components/SettlementsInfoButton.vue'));

        foreach (['invoice_number', 'invoice_date', 'due_date', 'invoice_amount', 'settlement_amount', 'withhold_amount'] as $column) {
            $this->assertStringContainsString('row.'.$column, $source, 'العمود ناقص في الواجهة: '.$column);
        }
    }
}
