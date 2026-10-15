<?php

namespace Tests\Unit;

use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use Illuminate\Http\Request;
use ReflectionClass;
use Tests\TestCase;

/**
 * * لما المستخدم يستلم ١٠٠٠ و يوزّع ٦٠٠ على الفواتير ، الـ ٤٠٠ الباقية
 * * لازم تتسجل كدفعة مقدمة و money_type يبقى
 * * invoice-settlement-with-down-payment — و ده اللي بيخلي مسارات اودو
 * * تبعت الجزء ده
 *
 * * الشرط كان بيقارن type (نوع الاستلام) بـ 'is_customer' اللي هي قيمة من
 * * partner_type ، فما كانش بيتحقق ولا مرة و الدفعة المقدمة ما كانتش
 * * بتروح اودو خالص
 */
class InvoiceSettlementWithDownPaymentTest extends TestCase
{
    /**
     * * قيم عمود type الحقيقية زي ما هي متسجلة في الداتابيز
     */
    private const RECEIVED_TYPES = ['cash-in-safe', 'cash-in-bank', 'incoming-transfer', 'cheque'];

    private const PAYMENT_TYPES = ['cash_payment', 'outgoing-transfer', 'payable_cheque'];

    private function request(array $data): Request
    {
        return Request::create('/', 'POST', $data);
    }

    /**
     * * السيناريو بتاع العميل : ١٠٠٠ استلام ، ٦٠٠ على الفواتير ، ٤٠٠ متبقي
     */
    public function test_customer_receipt_with_a_remainder_is_flagged_for_every_receipt_type(): void
    {
        foreach (self::RECEIVED_TYPES as $type) {
            $request = $this->request([
                'type' => $type,
                'partner_type' => 'is_customer',
                'unapplied_amount' => 400,
            ]);

            $this->assertTrue(
                MoneyReceived::requestHasInvoiceSettlementWithDownPayment($request),
                'استلام من عميل بمتبقي ٤٠٠ لازم يتعلّم — نوع الاستلام: '.$type
            );
        }
    }

    public function test_supplier_payment_with_a_remainder_is_flagged_for_every_payment_type(): void
    {
        foreach (self::PAYMENT_TYPES as $type) {
            $request = $this->request([
                'type' => $type,
                'partner_type' => 'is_supplier',
                'unapplied_amount' => 400,
            ]);

            $this->assertTrue(
                MoneyPayment::requestHasInvoiceSettlementWithDownPayment($request),
                'صرف لمورّد بمتبقي ٤٠٠ لازم يتعلّم — نوع الصرف: '.$type
            );
        }
    }

    public function test_partner_type_defaults_keep_working_when_the_form_omits_it(): void
    {
        $this->assertTrue(MoneyReceived::requestHasInvoiceSettlementWithDownPayment(
            $this->request(['type' => 'cash-in-safe', 'unapplied_amount' => 400])
        ), 'الافتراضي في الاستلام is_customer');

        $this->assertTrue(MoneyPayment::requestHasInvoiceSettlementWithDownPayment(
            $this->request(['type' => 'cash_payment', 'unapplied_amount' => 400])
        ), 'الافتراضي في الصرف is_supplier');
    }

    public function test_no_remainder_means_no_down_payment(): void
    {
        foreach ([0, '0', null] as $amount) {
            $this->assertFalse(MoneyReceived::requestHasInvoiceSettlementWithDownPayment(
                $this->request(['type' => 'cash-in-safe', 'partner_type' => 'is_customer', 'unapplied_amount' => $amount])
            ), 'مفيش متبقي = مفيش دفعة مقدمة');
        }

        $this->assertFalse(MoneyReceived::requestHasInvoiceSettlementWithDownPayment(
            $this->request(['type' => 'cash-in-safe', 'partner_type' => 'is_customer'])
        ), 'الحقل مش مبعوت أصلاً');
    }

    public function test_a_pure_down_payment_is_not_the_mixed_type(): void
    {
        $this->assertFalse(MoneyReceived::requestHasInvoiceSettlementWithDownPayment(
            $this->request([
                'type' => 'cash-in-safe',
                'partner_type' => 'is_customer',
                'unapplied_amount' => 400,
                'is_down_payment' => 1,
            ]),
        ), 'الدفعة المقدمة الصافية ليها money_type بتاعها');
    }

    public function test_other_partner_types_are_untouched(): void
    {
        foreach (['is_employee', 'is_shareholder', 'is_subsidiary_company', 'is_tax', 'is_other_partner'] as $partnerType) {
            $this->assertFalse(MoneyReceived::requestHasInvoiceSettlementWithDownPayment(
                $this->request(['type' => 'cash-in-safe', 'partner_type' => $partnerType, 'unapplied_amount' => 400])
            ), 'الأنواع دي بتعدّي من الشرط الأول في الكنترولر، مالهاش تتغير: '.$partnerType);

            $this->assertFalse(MoneyPayment::requestHasInvoiceSettlementWithDownPayment(
                $this->request(['type' => 'cash_payment', 'partner_type' => $partnerType, 'unapplied_amount' => 400])
            ), $partnerType);
        }

        // العميل مش مورّد و العكس
        $this->assertFalse(MoneyReceived::requestHasInvoiceSettlementWithDownPayment(
            $this->request(['type' => 'cash-in-safe', 'partner_type' => 'is_supplier', 'unapplied_amount' => 400])
        ));
        $this->assertFalse(MoneyPayment::requestHasInvoiceSettlementWithDownPayment(
            $this->request(['type' => 'cash_payment', 'partner_type' => 'is_customer', 'unapplied_amount' => 400])
        ));
    }

    /**
     * * الباج الأصلي : قيم type و قيم partner_type مجموعتين منفصلتين تماماً ،
     * * فأي مقارنة بينهم بترجع false دايماً
     */
    public function test_the_two_field_domains_never_overlap(): void
    {
        $partnerTypes = ['is_customer', 'is_supplier', 'is_employee', 'is_shareholder', 'is_subsidiary_company', 'is_tax', 'is_other_partner'];

        $this->assertSame([], array_intersect(self::RECEIVED_TYPES, $partnerTypes));
        $this->assertSame([], array_intersect(self::PAYMENT_TYPES, $partnerTypes));
    }

    public function test_the_controllers_no_longer_compare_the_receipt_type_to_a_partner_type(): void
    {
        $cases = [
            ['Http/Controllers/MoneyReceivedController.php', 'isDownPaymentFromMoneyReceived', 'MoneyReceived::requestHasInvoiceSettlementWithDownPayment'],
            ['Http/Controllers/MoneyPaymentController.php', 'isDownPaymentFromMoneyPayment', 'MoneyPayment::requestHasInvoiceSettlementWithDownPayment'],
        ];

        foreach ($cases as [$file, $variable, $call]) {
            $source = file_get_contents(app_path($file));
            preg_match('/\$'.$variable.'\s*=\s*[^;]+;/', $source, $matches);

            $this->assertNotEmpty($matches, 'مش لاقي إسناد '.$variable);
            $this->assertStringNotContainsString('$moneyType', $matches[0], 'ممنوع نقارن نوع الاستلام/الصرف بنوع الشريك');
            $this->assertStringContainsString($call, $matches[0]);
        }
    }

    /**
     * * السلسلة كاملة : الفلاج -> money_type -> شرط الكنترولر -> المبلغ
     * * اللي بيتبعت لاودو ، من غير داتابيز و لا اودو
     */
    public function test_the_whole_chain_reaches_odoo_with_the_remainder_only(): void
    {
        $request = $this->request(['type' => 'cash-in-safe', 'partner_type' => 'is_customer', 'unapplied_amount' => 400]);

        $flag = MoneyReceived::requestHasInvoiceSettlementWithDownPayment($request);
        $this->assertTrue($flag, 'الخطوة ١: الفلاج');

        $moneyType = $flag ? MoneyReceived::INVOICE_SETTLEMENT_WITH_DOWN_PAYMENT : 'money-received';
        $this->assertSame('invoice-settlement-with-down-payment', $moneyType, 'الخطوة ٢: money_type');

        /**
         * * نفس شرط الكنترولر بالحرف
         */
        $partnerType = 'is_customer';
        $isDownPayment = false;
        $reachesOdoo = ($partnerType && $partnerType != 'is_customer') || ($isDownPayment || $flag);
        $this->assertTrue($reachesOdoo, 'الخطوة ٣: بينادي storeNonCustomerOrSupplierOdooExpense');
        $this->assertTrue($isDownPayment || $flag, 'الخطوة ٣ب: بيتبعتله isDownPayment = true');

        /**
         * * الخطوة ٤: المبلغ — موديل من غير حفظ ، و العلاقة متحطوطة بالإيد
         */
        $model = new MoneyReceived;
        $model->forceFill(['money_type' => $moneyType, 'received_amount' => 1000]);
        $model->setRelation('downPaymentSettlements', collect([
            new \App\Models\DownPaymentSettlement(['down_payment_amount' => 250]),
            new \App\Models\DownPaymentSettlement(['down_payment_amount' => 150]),
        ]));

        $this->assertTrue($model->isInvoiceSettlementWithDownPayment());
        $this->assertEqualsWithDelta(400, $model->getDownPaymentAmount(), 0.001, 'اللي بيروح اودو هو المتبقي ٤٠٠');
        $this->assertEquals(1000, $model->getAmount(), 'و getAmount() لسه بترجع المبلغ الكامل للمسارات التانية');
    }

    /**
     * * الحسبة اللي بتحدد المبلغ اللي بيروح اودو موجودة و بتفرّق بين
     * * الدفعة الصافية و الحالة المختلطة
     */
    public function test_the_odoo_amount_source_handles_the_mixed_case(): void
    {
        $source = file_get_contents((new ReflectionClass(\App\Traits\Models\IsMoney::class))->getFileName());
        $body = substr($source, strpos($source, 'function getDownPaymentAmount'), 500);

        $this->assertStringContainsString('isInvoiceSettlementWithDownPayment', $body);
        $this->assertStringContainsString("downPaymentSettlements->sum('down_payment_amount')", $body);
    }
}
