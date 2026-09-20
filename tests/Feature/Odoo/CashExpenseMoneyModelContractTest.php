<?php

namespace Tests\Feature\Odoo;

use App\Models\Branch;
use App\Models\CashExpense;
use App\Models\MoneyPayment;
use App\Services\Api\OdooPayment;
use App\Traits\HasOdooPaymentMethod;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * * عقد "موديل الفلوس" اللي OdooPayment بيتوقعه
 *
 * * OdooPayment::buildDownPaymentData() بتنادي مجموعة دوال على أي
 * * موديل بيتبعتله. العقد ده معرّف في IsMoney اللي MoneyPayment و
 * * MoneyReceived بيستخدموه — و CashExpense بيستخدم IsMoneyOut اللي
 * * مفيهوش أي دالة منه.
 *
 * * فحفظ مصروف نقدي نوعه شيك دائن على شركة مربوطة بأودو كان بيضرب:
 * *   Call to undefined method CashExpense::getReceivingOrPaymentMoneyDate()
 * * (CashExpenseController::store → storeNonCustomerOrSupplierOdooExpense
 * *  → HasNonCustomerOrSupplier → OdooPayment::createDownPayment)
 *
 * * و buildDownPaymentData() بتنادي ٤ دوال مش واحدة ، فإصلاح التاريخ
 * * لوحده كان هينقل العطل للي بعده.
 */
class CashExpenseMoneyModelContractTest extends TestCase
{
    /**
     * * الدوال اللي OdooPayment بينديها فعلا على الموديل — بتتقري من
     * * الكود نفسه ، فلو حد ضاف نداء جديد التست هيمسكه من غير ما حد
     * * يفتكر يحدّث القايمة
     *
     * @return list<string>
     */
    private function methodsOdooCallsOnTheMoneyModel(): array
    {
        $source = file_get_contents((new ReflectionClass(OdooPayment::class))->getFileName());

        preg_match_all('/\$moneyModel->([a-zA-Z_][a-zA-Z0-9_]*)\(/', $source, $matches);

        // update() موروثة من Eloquent فمش جزء من العقد
        return array_values(array_diff(array_unique($matches[1]), ['update']));
    }

    public function test_odoo_really_calls_methods_on_the_money_model(): void
    {
        $this->assertNotEmpty($this->methodsOdooCallsOnTheMoneyModel(),
            'لو القايمة فضيت يبقى الاستخراج باظ و التست بقى بلا معنى');
    }

    /**
     * * الحارس الأساسي: أي دالة OdooPayment بينديها لازم تكون موجودة
     * * على CashExpense ، مش على MoneyPayment بس
     */
    public function test_cash_expense_satisfies_every_method_odoo_calls(): void
    {
        $missing = [];

        foreach ($this->methodsOdooCallsOnTheMoneyModel() as $method) {
            if (! method_exists(CashExpense::class, $method)) {
                $missing[] = $method;
            }
        }

        $this->assertSame([], $missing,
            "المصروف النقدي بيوصل OdooPayment من مسار الشيكات ، فأي دالة ناقصة دي بتبقى\n"
            ."Call to undefined method وقت الحفظ:\n  ".implode("\n  ", $missing));
    }

    public function test_money_payment_satisfies_them_too(): void
    {
        $missing = [];

        foreach ($this->methodsOdooCallsOnTheMoneyModel() as $method) {
            if (! method_exists(MoneyPayment::class, $method)) {
                $missing[] = $method;
            }
        }

        $this->assertSame([], $missing);
    }

    /* ───────── القيم نفسها ───────── */

    private function payableChequeExpense(): CashExpense
    {
        $expense = new CashExpense;
        $expense->forceFill([
            'type' => CashExpense::PAYABLE_CHEQUE,
            'payment_date' => '2026-05-14',
        ]);

        return $expense;
    }

    /**
     * * نفس اللي MoneyPayment بيرجّعه بالظبط — الاتنين بيروحوا لأودو
     * * من نفس الدالة ، فلازم يتفقوا
     */
    public function test_the_date_is_the_delivery_date_exactly_like_money_payment(): void
    {
        $expense = $this->payableChequeExpense();

        $this->assertSame('2026-05-14', $expense->getReceivingOrPaymentMoneyDate());
        $this->assertSame($expense->getDeliveryDate(), $expense->getReceivingOrPaymentMoneyDate());

        $payment = new MoneyPayment;
        $payment->forceFill(['delivery_date' => '2026-05-14']);

        $this->assertSame($payment->getReceivingOrPaymentMoneyDate(), $expense->getReceivingOrPaymentMoneyDate(),
            'الشيك الدائن لازم يتسجّل في أودو بنفس التاريخ سواء جه من مصروف نقدي أو من دفعة');
    }

    /**
     * * payment_date عمود nullable و التوقيع :string — من غير الحارس
     * * ده كنا هنستبدل undefined method بـ TypeError
     */
    public function test_a_missing_date_does_not_become_a_type_error(): void
    {
        $this->assertSame('', (new CashExpense)->getReceivingOrPaymentMoneyDate());
        $this->assertSame('', (new CashExpense)->getReceivingOrPaymentMoneyDateFormatted());
    }

    public function test_a_cash_expense_is_always_money_going_out(): void
    {
        $this->assertSame('outbound', $this->payableChequeExpense()->getInboundOrOutbound());
    }

    public function test_the_memo_names_the_expense_category(): void
    {
        $expense = $this->payableChequeExpense();

        $this->assertStringContainsString(
            (string) $expense->getExpenseCategoryName(),
            $expense->generateDownPaymentMessage()
        );
    }

    /**
     * * الدفع النقدي بيتصرف من خزنة (Branch) ، و Branch فعلا عنده
     * * getOdooOutboundTransferPaymentMethodId() جايه من trait
     * * HasOdooPaymentMethod — مش معرّفة جوه Branch.php نفسه ، فاللي
     * * بيدوّر عليها بـ grep في الكلاس بس مش هيلاقيها و يفتكرها ناقصة
     */
    public function test_a_branch_really_has_the_outbound_transfer_payment_method(): void
    {
        $this->assertTrue(
            method_exists(Branch::class, 'getOdooOutboundTransferPaymentMethodId'),
            'Branch لازم تفضل شايلة الدالة دي — getPaymentMethodLineId بينده عليها للدفع النقدي'
        );

        $this->assertContains(
            HasOdooPaymentMethod::class,
            class_uses_recursive(Branch::class),
            'الدالة جايه من HasOdooPaymentMethod ، فلو الـ trait اتشال من Branch الدفع النقدي هيقع'
        );
    }

    /**
     * * الدفع النقدي بياخد طريقة الدفع من خزنة التسليم
     */
    public function test_a_cash_payment_takes_its_payment_method_from_the_delivery_branch(): void
    {
        $branch = new Branch;
        $branch->forceFill(['odoo_outbound_transfer_payment_method_id' => 77]);

        $expense = new class extends CashExpense
        {
            public $stubBranch;

            public function cashPaymentDeliveryBranch()
            {
                return $this->stubBranch;
            }
        };
        $expense->forceFill(['type' => CashExpense::CASH_PAYMENT]);
        $expense->stubBranch = $branch;

        $this->assertSame(77, (int) $expense->getPaymentMethodLineId());
    }

    /**
     * * و من غير خزنة تسليم بنرجّع null بدل ما نقع على null->method()
     */
    public function test_a_cash_payment_without_a_delivery_branch_returns_null(): void
    {
        $expense = new class extends CashExpense
        {
            public function cashPaymentDeliveryBranch()
            {
                return null;
            }
        };
        $expense->forceFill(['type' => CashExpense::CASH_PAYMENT]);

        $this->assertNull($expense->getPaymentMethodLineId());
    }

    /**
     * * الشرط اللي بيدخّل المصروف المسار ده أصلا
     */
    public function test_a_payable_cheque_expense_takes_the_odoo_down_payment_path(): void
    {
        $this->assertTrue($this->payableChequeExpense()->isChequeOrChequePayment(),
            'ده الشرط في HasNonCustomerOrSupplier اللي بيوصّل CashExpense لـ createDownPayment');
    }

    /**
     * * الدوال اللي OdooPayment بيقراها من buildDownPaymentData تحديدا
     */
    public function test_building_the_odoo_payload_needs_all_four(): void
    {
        $source = file_get_contents((new ReflectionClass(OdooPayment::class))->getFileName());

        $method = new ReflectionMethod(OdooPayment::class, 'buildDownPaymentData');
        $lines = explode("\n", $source);
        $body = implode("\n", array_slice($lines, $method->getStartLine() - 1, $method->getEndLine() - $method->getStartLine() + 1));

        preg_match_all('/\$moneyModel->([a-zA-Z_][a-zA-Z0-9_]*)\(/', $body, $matches);

        foreach (array_unique($matches[1]) as $called) {
            $this->assertTrue(method_exists(CashExpense::class, $called),
                "buildDownPaymentData() بتنادي {$called}() و المصروف النقدي مش عنده");
        }
    }
}
