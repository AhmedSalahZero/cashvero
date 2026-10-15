<?php

namespace Tests\Feature\Odoo;

use App\Models\Branch;
use App\Models\CashExpense;
use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use App\Services\Api\OdooPayment;
use App\Traits\HasOdooPaymentMethod;
use App\Traits\Models\HasNonCustomerOrSupplier;
use Illuminate\Database\Eloquent\Model;
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
     * * فروق مقصودة بين الموديلات — أي دالة تانية تختلف تبقى غير مقصودة.
     *
     * * getPartnerType : المصروف النقدي مالوش نوع شريك أصلا فبيرجّع null ،
     * * و OdooPayment بيقارنها بـ in_array(..., ['is_customer','is_supplier'], true)
     * * فالـ null بتدي false بأمان. test_a_cash_expense_has_no_partner_type_on_purpose
     * * تحت بيثبّت السلوك ده عشان ما يتغيّرش بالغلط.
     */
    private const INTENTIONAL_DIVERGENCES = ['getPartnerType'];

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

    /**
     * * كل موديل بيستخدم HasNonCustomerOrSupplier بيقدر يوصل
     * * OdooPayment::createDownPayment() ، فالاكتشاف بيتم من الـ trait
     * * نفسه — لو حد ضاف موديل رابع للمسار ده التست بيغطّيه تلقائيا
     * * من غير ما حد يفتكر يحدّث قايمة.
     *
     * @return list<class-string>
     */
    private function modelsOnTheOdooDownPaymentPath(): array
    {
        $found = [];

        foreach (glob(app_path('Models').'/*.php') as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');

            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }

            if (in_array(HasNonCustomerOrSupplier::class, class_uses_recursive($class), true)) {
                $found[] = $class;
            }
        }

        sort($found);

        return $found;
    }

    /**
     * * موديل مملوء بالحد الأدنى الواقعي. الأعمدة دي nullable في الـ schema
     * * بس مفيهاش ولا صف فاضي في القاعدتين (٠ من ٧٥٨ و ٠ من ٧٢٧) ، فالحالة
     * * اللي بنفحصها هي اللي بتحصل فعلا — مش موديل جديد لسه مااتملاش.
     */
    private function populated(string $model): Model
    {
        $instance = new $model;

        $instance->forceFill([
            'type' => 'payable_cheque',
            'money_type' => 'money-payment',
            'payment_date' => '2026-05-14',
            'delivery_date' => '2026-05-14',
            'receiving_date' => '2026-05-14',
        ]);

        return $instance;
    }

    /** لو الاكتشاف باظ ، كل اللي تحته بيبقى بيفحص فاضي */
    public function test_the_discovery_finds_the_models_on_the_odoo_path(): void
    {
        $models = $this->modelsOnTheOdooDownPaymentPath();

        $this->assertContains(CashExpense::class, $models);
        $this->assertContains(MoneyPayment::class, $models);
        $this->assertContains(MoneyReceived::class, $models);
    }

    /**
     * * ده اللي الـ interface كان هيعمله : مصفوفة كاملة موديل × دالة.
     * * أي موديل بيدخل مسار أودو لازم يلبّي العقد كله ، مش بعضه.
     */
    public function test_every_model_on_the_odoo_path_satisfies_the_whole_contract(): void
    {
        $gaps = [];

        foreach ($this->modelsOnTheOdooDownPaymentPath() as $model) {
            foreach ($this->methodsOdooCallsOnTheMoneyModel() as $method) {
                if (! method_exists($model, $method)) {
                    $gaps[] = class_basename($model).'::'.$method.'()';
                }
            }
        }

        $this->assertSame([], $gaps,
            "الموديلات دي بتوصل OdooPayment ، فكل دالة ناقصة هنا بتبقى Call to undefined method\n"
            ."وقت الحفظ على شركة مربوطة بأودو:\n  ".implode("\n  ", $gaps));
    }

    /**
     * * method_exists() بتقول إن الدالة موجودة ، مش إنها بترجّع حاجة سليمة.
     * * getReceivingOrPaymentMoneyDate() معلنة `: string` — لو رجّعت null
     * * (تاريخ فاضي) دي TypeError وقت التشغيل ، و الطلب بيقع زي ما كان بيقع
     * * وهي مش موجودة أصلا. بننادي كل دالة مالهاش بارامترات على موديل فاضي
     * * عن قصد و نمسك الـ TypeError بس — الأخطاء التانية (علاقة مش محمّلة
     * * مثلا) مش موضوع التست ده.
     */
    public function test_no_contract_method_breaks_its_own_declared_return_type(): void
    {
        $typeErrors = [];

        foreach ($this->modelsOnTheOdooDownPaymentPath() as $model) {
            foreach ($this->methodsOdooCallsOnTheMoneyModel() as $method) {
                $reflection = new ReflectionMethod($model, $method);

                if ($reflection->getNumberOfRequiredParameters() > 0 || ! $reflection->hasReturnType()) {
                    continue;
                }

                try {
                    $reflection->invoke($this->populated($model));
                } catch (\TypeError $e) {
                    $typeErrors[] = class_basename($model).'::'.$method.'() → '.$e->getMessage();
                } catch (\Throwable $e) {
                    // محتاجة بيانات أو علاقة — مش خرق للنوع
                }
            }
        }

        $this->assertSame([], $typeErrors,
            "دوال معلنة نوع إرجاع و بتخالفه على موديل مملوء:\n  ".implode("\n  ", $typeErrors));
    }

    /**
     * * الموديلات بتروح لأودو من نفس الدالة ، فلازم ترجّع نفس أنواع القيم.
     * * الأنواع المعلنة مش متطابقة فعلا (CashExpense معلن `: string` على
     * * getInboundOrOutbound و التانيين لأ) ، فبنقارن النوع وقت التشغيل —
     * * هو اللي بيوصل أودو.
     */
    public function test_the_models_agree_on_the_runtime_type_of_each_contract_method(): void
    {
        $comparable = 0;
        $disagreements = [];

        foreach ($this->methodsOdooCallsOnTheMoneyModel() as $method) {
            $types = [];

            foreach ($this->modelsOnTheOdooDownPaymentPath() as $model) {
                $reflection = new ReflectionMethod($model, $method);

                if ($reflection->getNumberOfRequiredParameters() > 0) {
                    continue 2;
                }

                try {
                    $types[class_basename($model)] = get_debug_type($reflection->invoke($this->populated($model)));
                } catch (\Throwable $e) {
                    continue 2;   // مش قابلة للمقارنة من غير قاعدة بيانات
                }
            }

            if (count(array_unique($types)) > 1 && ! in_array($method, self::INTENTIONAL_DIVERGENCES, true)) {
                $disagreements[] = $method.'() → '.json_encode($types);
            }

            $comparable++;
        }

        $this->assertGreaterThan(0, $comparable, 'مفيش ولا دالة اتقارنت — التست بقى بلا معنى');
        $this->assertSame([], $disagreements,
            "موديلات على نفس المسار بترجّع أنواع مختلفة:\n  ".implode("\n  ", $disagreements));
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

    /**
     * * الاستثناء الوحيد المسموح بيه في اتفاق الأنواع فوق — مثبّت هنا
     * * بحيث لو حد خلّى CashExpense يرجّع نص ، أو شال الـ strict من
     * * in_array في OdooPayment ، حد ياخد باله
     */
    public function test_a_cash_expense_has_no_partner_type_on_purpose(): void
    {
        $this->assertNull((new CashExpense)->getPartnerType(),
            'المصروف النقدي مالوش نوع شريك — لو بقى بيرجّع نص لازم نراجع المقارنة في OdooPayment');

        $source = file_get_contents((new ReflectionClass(OdooPayment::class))->getFileName());

        $this->assertStringContainsString(
            "in_array(\$moneyModel->getPartnerType(), ['is_customer', 'is_supplier'], true)",
            $source,
            'المقارنة لازم تفضل strict — من غير الـ true الأخيرة الـ null هتساوي أي نص فاضي'
        );
    }
}
