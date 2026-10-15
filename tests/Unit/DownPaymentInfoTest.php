<?php

namespace Tests\Unit;

use App\Models\Contract;
use App\Models\DownPaymentMoneyPaymentSettlement;
use App\Models\DownPaymentSettlement;
use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use App\Models\Settlement;
use Tests\TestCase;

/**
 * * تفاصيل الدفعة المقدمة في بوب اب "تفاصيل التسوية"
 *
 * * قبل كده البوب اب مكانش بيقول اي حاجة عن الدفعة المقدمة نفسها :
 * *   - الدفعة المقدمة الصافية ملهاش فواتير مسوّاة ، فكان بيقول بس
 * *     "مفيش فواتير مسوّاة" و خلاص
 * *   - و التسوية مع دفعة مقدمة كانت بتقول المبلغ من غير ما تقول هي عامة
 * *     ولا على عقد
 *
 * * كل الموديلات هنا من غير حفظ ، و العلاقات متحطوطة بالإيد ، فمفيش
 * * داتابيز في الطريق
 */
class DownPaymentInfoTest extends TestCase
{
    private function contract(string $name, string $code): Contract
    {
        $contract = new Contract;
        $contract->forceFill(['name' => $name, 'code' => $code]);

        return $contract;
    }

    private function allocation(string $class, float $amount, ?Contract $contract): object
    {
        $allocation = new $class;
        $allocation->forceFill([
            'down_payment_amount' => $amount,
            'contract_id' => $contract ? 1 : null,
        ]);
        $allocation->setRelation('contract', $contract);

        return $allocation;
    }

    private function moneyReceived(array $attributes, array $allocations = [], ?Contract $contract = null): MoneyReceived
    {
        $money = new MoneyReceived;
        $money->forceFill($attributes + ['currency' => 'EGP']);
        $money->setRelation('settlements', collect([]));
        $money->setRelation('downPaymentSettlements', collect($allocations));
        $money->setRelation('contract', $contract);

        return $money;
    }

    /* ───────────── الدفعة المقدمة الصافية ───────────── */

    public function test_a_down_payment_over_a_contract_names_the_contract(): void
    {
        $contract = $this->contract('Smart & Planet Contract', 'CT-001');

        $money = $this->moneyReceived([
            'money_type' => MoneyReceived::DOWN_PAYMENT,
            'down_payment_type' => MoneyReceived::DOWN_PAYMENT_OVER_CONTRACT,
            'received_amount' => 5000000,
        ], [$this->allocation(DownPaymentSettlement::class, 5000000, $contract)], $contract);

        $info = $money->getSettlementsInfo()['down_payment'];

        $this->assertNotNull($info, 'الدفعة المقدمة الصافية لازم يبان لها تفاصيل');
        $this->assertSame(MoneyReceived::DOWN_PAYMENT_OVER_CONTRACT, $info['type']);
        $this->assertSame(__('Over Contract'), $info['type_label']);
        $this->assertTrue($info['is_over_contract']);
        $this->assertSame('Smart & Planet Contract', $info['contract_name'], 'لازم يقول اسم العقد');
        $this->assertSame('CT-001', $info['contract_code']);
        $this->assertSame('5,000,000.00', $info['amount']);
    }

    public function test_a_general_down_payment_says_it_is_general_and_names_no_contract(): void
    {
        $money = $this->moneyReceived([
            'money_type' => MoneyReceived::DOWN_PAYMENT,
            'down_payment_type' => MoneyReceived::DOWN_PAYMENT_GENERAL,
            'received_amount' => 400,
        ], [$this->allocation(DownPaymentSettlement::class, 400, null)]);

        $info = $money->getSettlementsInfo()['down_payment'];

        $this->assertSame(MoneyReceived::DOWN_PAYMENT_GENERAL, $info['type']);
        $this->assertSame(__('General'), $info['type_label']);
        $this->assertFalse($info['is_over_contract']);
        $this->assertNull($info['contract_name'], 'الدفعة العامة مالهاش عقد');
        $this->assertSame('400.00', $info['amount']);
    }

    public function test_a_settlement_of_opening_balance_keeps_its_own_label(): void
    {
        $money = $this->moneyReceived([
            'money_type' => MoneyReceived::DOWN_PAYMENT,
            'down_payment_type' => MoneyReceived::SETTLEMENT_OF_OPENING_BALANCE,
            'received_amount' => 63500,
        ]);

        $info = $money->getSettlementsInfo()['down_payment'];

        $this->assertSame(MoneyReceived::SETTLEMENT_OF_OPENING_BALANCE, $info['type']);
        $this->assertSame(__('Settlement Of Opening Balance'), $info['type_label']);
    }

    /**
     * * الدفعة الصافية مش دايمًا ليها صف توزيع — فالمبلغ بييجي من الحركة
     * * نفسها بدل ما يبان صفر
     */
    public function test_a_down_payment_with_no_allocation_row_still_reports_its_amount(): void
    {
        $money = $this->moneyReceived([
            'money_type' => MoneyReceived::DOWN_PAYMENT,
            'down_payment_type' => MoneyReceived::DOWN_PAYMENT_GENERAL,
            'received_amount' => 1250,
        ]);

        $this->assertSame('1,250.00', $money->getSettlementsInfo()['down_payment']['amount']);
    }

    /* ───────────── التسوية مع دفعة مقدمة ───────────── */

    /**
     * * العمود down_payment_type بيفضل فاضي دايمًا في النوع ده ، فالنوع
     * * بيتحدد من صف التوزيع : فيه عقد يبقى على عقد
     */
    public function test_a_settlement_with_down_payment_derives_over_contract_from_the_allocation(): void
    {
        $contract = $this->contract('Salah Building', 'CT-777');

        $money = $this->moneyReceived([
            'money_type' => MoneyReceived::INVOICE_SETTLEMENT_WITH_DOWN_PAYMENT,
            'down_payment_type' => null,
            'received_amount' => 500000,
        ], [$this->allocation(DownPaymentSettlement::class, 100000, $contract)]);

        $info = $money->getSettlementsInfo()['down_payment'];

        $this->assertSame(MoneyReceived::DOWN_PAYMENT_OVER_CONTRACT, $info['type'],
            'مفيش نوع متخزن ، فلازم يستنتجه من العقد اللي في صف التوزيع');
        $this->assertSame('Salah Building', $info['contract_name']);
        $this->assertSame('100,000.00', $info['amount']);
        $this->assertTrue($info['is_with_invoice_settlement']);
    }

    public function test_a_settlement_with_down_payment_and_no_contract_is_general(): void
    {
        $money = $this->moneyReceived([
            'money_type' => MoneyReceived::INVOICE_SETTLEMENT_WITH_DOWN_PAYMENT,
            'down_payment_type' => null,
            'received_amount' => 500,
        ], [$this->allocation(DownPaymentSettlement::class, 2, null)]);

        $info = $money->getSettlementsInfo()['down_payment'];

        $this->assertSame(MoneyReceived::DOWN_PAYMENT_GENERAL, $info['type']);
        $this->assertSame(__('General'), $info['type_label']);
        $this->assertSame('2.00', $info['amount']);
    }

    /* ───────────── جهة الصرف ───────────── */

    public function test_money_payment_reports_its_down_payment_the_same_way(): void
    {
        $contract = $this->contract('Supplier Frame Agreement', 'PO-42');

        $money = new MoneyPayment;
        $money->forceFill([
            'money_type' => MoneyPayment::DOWN_PAYMENT,
            'down_payment_type' => MoneyPayment::DOWN_PAYMENT_OVER_CONTRACT,
            'paid_amount' => 740374.10,
            'currency' => 'EGP',
        ]);
        $money->setRelation('settlements', collect([]));
        $money->setRelation('downPaymentSettlements', collect([
            $this->allocation(DownPaymentMoneyPaymentSettlement::class, 740374.10, $contract),
        ]));
        $money->setRelation('contract', $contract);

        $info = $money->getSettlementsInfo()['down_payment'];

        $this->assertSame(MoneyPayment::DOWN_PAYMENT_OVER_CONTRACT, $info['type']);
        $this->assertSame('Supplier Frame Agreement', $info['contract_name']);
        $this->assertSame('740,374.10', $info['amount']);
    }

    /**
     * * حركات كتير في الداتا (عهدة لموظف ، تمويل شركة تابعة ، ضرائب ...)
     * * الـ money_type بتاعها اتكتب invoice-settlement-with-down-payment
     * * بالغلط من كود قديم ، و هي ملهاش دفعة مقدمة خالص — فما ينفعش البوب
     * * اب يقول عنها "دفعة مقدمة : عام ٠٫٠٠"
     */
    public function test_a_row_flagged_as_settlement_with_down_payment_but_carrying_none_reports_nothing(): void
    {
        $money = new MoneyPayment;
        $money->forceFill([
            'money_type' => MoneyPayment::INVOICE_SETTLEMENT_WITH_DOWN_PAYMENT,
            'down_payment_type' => null,
            'partner_type' => 'is_employee',
            'transaction_type' => 'custody',
            'paid_amount' => 10000,
            'currency' => 'EGP',
        ]);
        $money->setRelation('settlements', collect([]));
        $money->setRelation('downPaymentSettlements', collect([]));
        $money->setRelation('contract', null);

        $this->assertNull(
            $money->getSettlementsInfo()['down_payment'],
            'مفيش صف توزيع و لا مبلغ — يبقى مفيش دفعة مقدمة نوصفها'
        );
    }

    /**
     * * و برضه لو المتبقي طلع صفر فعلا في تسوية حقيقية
     */
    public function test_a_zero_remainder_reports_no_down_payment(): void
    {
        $money = $this->moneyReceived([
            'money_type' => MoneyReceived::INVOICE_SETTLEMENT_WITH_DOWN_PAYMENT,
            'down_payment_type' => null,
            'received_amount' => 1000,
        ]);

        $this->assertNull($money->getSettlementsInfo()['down_payment']);
    }

    /* ───────────── اللي ما يتغيّرش ───────────── */

    /**
     * * الحركة العادية (مش دفعة مقدمة) ما ينفعش يبان لها القسم ده خالص
     */
    public function test_a_plain_money_row_reports_no_down_payment_block(): void
    {
        $settlement = new Settlement;
        $settlement->forceFill(['settlement_amount' => 100, 'withhold_amount' => 0, 'is_from_down_payment' => 0]);
        $settlement->setRelation('invoice', null);

        $money = new MoneyReceived;
        $money->forceFill(['money_type' => 'money-received', 'received_amount' => 100, 'currency' => 'EGP']);
        $money->setRelation('settlements', collect([$settlement]));

        $this->assertNull($money->getSettlementsInfo()['down_payment']);
    }

    /**
     * * العقد ممكن يكون اتمسح و الـ contract_id فضل — ساعتها بنقول النوع
     * * الصح من غير ما نخترع اسم
     */
    public function test_a_missing_contract_row_reports_the_type_without_inventing_a_name(): void
    {
        $money = $this->moneyReceived([
            'money_type' => MoneyReceived::DOWN_PAYMENT,
            'down_payment_type' => MoneyReceived::DOWN_PAYMENT_OVER_CONTRACT,
            'received_amount' => 740374.10,
            'contract_id' => 776,
        ]);

        $info = $money->getSettlementsInfo()['down_payment'];

        $this->assertSame(MoneyReceived::DOWN_PAYMENT_OVER_CONTRACT, $info['type']);
        $this->assertNull($info['contract_name'], 'العقد مش موجود — ما نخترعش اسم');
        $this->assertSame('740,374.10', $info['amount']);
    }

    /* ───────────── الواجهة ───────────── */

    /**
     * * الواجهتين مختلفتين (Vue هنا و Blade في النظام التاني) بس الاتنين
     * * بيقروا نفس المفتاح ، فبنفحص اللي موجود منهم
     */
    public function test_the_modal_renders_the_down_payment_details(): void
    {
        $candidates = [
            resource_path('js/Components/SettlementsInfoButton.vue'),
            resource_path('views/reports/_settlements_info_modal.blade.php'),
        ];

        $template = null;

        foreach ($candidates as $path) {
            if (is_file($path)) {
                $template = $path;
                break;
            }
        }

        $this->assertNotNull($template, 'مفيش اي قالب للبوب اب');

        $markup = file_get_contents($template);

        foreach (['type_label', 'contract_name', 'Down Payment Type'] as $needle) {
            $this->assertStringContainsString($needle, $markup,
                'القالب لازم يعرض تفاصيل الدفعة المقدمة: '.basename($template));
        }
    }
}
