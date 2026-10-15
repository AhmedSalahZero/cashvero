<?php

namespace Tests\Feature\Deductions;

use App\Models\CustomerInvoice;
use App\Models\Deduction;
use App\Models\InvoiceDeduction;
use App\Models\SupplierInvoice;
use App\Rules\DeductionAmountRule;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * * بوب اب الخصومات في تقرير فواتير العميل
 *
 * * الباج : تفتح البوب اب لفاتورة عليها خصومات محفوظة و تدوس حفظ من غير
 * * ما تغيّر حاجة ، فيقولك "قيمة الخصم لازم تكون أقل من أو تساوي صافي
 * * الرصيد"
 *
 * * السبب : الحفظ بيستبدل كل الخصومات (detach ثم إنشاء) ، و الكونترولر
 * * بيحسب السقف صح (net_balance + الخصومات المحفوظة) ، لكن قاعدة التحقق
 * * كانت بتقارن بـ net_balance لوحده — و هو رصيد متخصوم منه الخصومات دي
 * * أصلا . يعني كانت بتعتبر الصف اللي بتعدّله خصم جديد فوق نفسه
 */
class InvoiceDeductionEditTest extends TestCase
{
    private ?string $originalDatabase = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDatabase = config('database.connections.mysql.database');

        config(['database.connections.mysql.database' => env('SMOKE_DB', 'cashvero')]);
        DB::purge('mysql');

        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Development database not reachable.');
        }
    }

    protected function tearDown(): void
    {
        config(['database.connections.mysql.database' => $this->originalDatabase]);
        DB::purge('mysql');

        parent::tearDown();
    }

    /** * هل القاعدة بتسمح بمجموع الخصومات ده على السقف ده ؟ */
    private function ruleAllows(float $allowance, array $amounts): bool
    {
        request()->merge([
            'deductions' => array_map(fn ($a) => ['amount' => $a], $amounts),
        ]);

        return (new DeductionAmountRule($allowance))->passes('deductions.0.amount', $amounts[0] ?? 0);
    }

    /** * السقف اللي الكونترولر بيسمح بيه فعلا */
    private function allowanceFor(CustomerInvoice $invoice): float
    {
        return $invoice->getNetBalance() + (float) $invoice->deductions->sum('pivot.amount');
    }

    /**
     * * لازم فاتورة **بتعيد إنتاج الباج فعلا** : رصيدها الحالي أقل من
     * * خصوماتها المحفوظة
     *
     * * الفاتورة اللي رصيدها لسه أكبر من خصوماتها بتعدّي بالكود القديم و
     * * الجديد سواء ، فما بتثبتش حاجة — و ده اللي خلى أول نسخة من
     * * الاختبار ما تمسكش رجوع الباج
     */
    private function invoiceWithDeductions(): CustomerInvoice
    {
        $candidates = CustomerInvoice::has('deductions')->limit(300)->get()
            ->filter(fn (CustomerInvoice $i) => (float) $i->deductions->sum('pivot.amount') > 0
                && $i->getNetBalance() >= 0);

        $reproducing = $candidates->first(
            fn (CustomerInvoice $i) => $i->getNetBalance() < (float) $i->deductions->sum('pivot.amount')
        );

        if (! $reproducing) {
            $this->markTestSkipped('No invoice whose saved deductions exceed its remaining balance.');
        }

        return $reproducing;
    }

    /* ───────────── الباج المبلّغ عنه ───────────── */

    /**
     * * الحالة اللي كانت بتفشل : تفتح و تحفظ نفس القيم من غير تغيير
     */
    public function test_resaving_the_existing_deductions_unchanged_is_allowed(): void
    {
        $invoice = $this->invoiceWithDeductions();
        $amounts = $invoice->deductions->map(fn ($d) => (float) $d->pivot->amount)->all();

        $this->assertTrue(
            $this->ruleAllows($this->allowanceFor($invoice), $amounts),
            'حفظ نفس الخصومات من غير تغيير لازم يعدّي — الصف اللي بيتعدّل مش خصم جديد'
        );
    }

    /**
     * * القاعدة و الكونترولر لازم يتفقوا : اللي الكونترولر هيقبله ما ينفعش
     * * البوب اب يرفضه ، و العكس
     */
    public function test_the_rule_agrees_with_the_controller_on_every_invoice(): void
    {
        $checked = 0;
        $disagreements = [];

        foreach (CustomerInvoice::has('deductions')->limit(150)->get() as $invoice) {
            $saved = (float) $invoice->deductions->sum('pivot.amount');

            if ($saved <= 0) {
                continue;
            }

            $allowance = $this->allowanceFor($invoice);
            $amounts = $invoice->deductions->map(fn ($d) => (float) $d->pivot->amount)->all();

            // الكونترولر : currentBalance - total >= 0
            $controllerAllows = ($allowance - $saved) >= 0;
            $ruleAllows = $this->ruleAllows($allowance, $amounts);

            if ($ruleAllows !== $controllerAllows) {
                $disagreements[] = '#'.$invoice->id;
            }

            $checked++;
        }

        $this->assertGreaterThan(0, $checked, 'مفيش فواتير عليها خصومات للفحص');
        $this->assertSame([], $disagreements,
            'القاعدة و الكونترولر مختلفين على: '.implode(', ', $disagreements));
    }

    /**
     * * الاختبارات اللي فوق بتحسب السقف بنفسها ، فمش بتغطي المكان اللي
     * * الإصلاح فيه فعلا : الـ FormRequest نفسه
     *
     * * ده بيشغّل قواعد الطلب الحقيقية زي ما بتشتغل في الويب
     */
    private function validateThroughRequest($invoice, array $deductions, string $modelType = 'CustomerInvoice'): \Illuminate\Validation\Validator
    {
        $request = \App\Http\Requests\UpdateInvoiceDeductionRequest::create('/x', 'PUT', [
            'deductions' => $deductions,
        ]);

        // الطلب بيقرا الفاتورة من بارامترات الراوت
        $route = new \Illuminate\Routing\Route(['PUT'], '/x/{modelId}/{modelType}', fn () => null);
        $route->bind($request);
        $route->setParameter('modelId', $invoice->id);
        $route->setParameter('modelType', $modelType);
        $request->setRouteResolver(fn () => $route);

        // القواعد بتقرا من الـ Request() المساعد
        app()->instance('request', $request);

        return validator($request->all(), $request->rules(), $request->messages());
    }

    /**
     * * نفس السيناريو اللي بلّغ عنه المستخدم ، بس عبر الطلب الحقيقي :
     * * يفتح البوب اب و يحفظ زي ما هو
     */
    public function test_the_request_accepts_resaving_the_existing_deductions(): void
    {
        $invoice = $this->invoiceWithDeductions();

        $deductions = $invoice->deductions->map(fn ($d) => [
            'deduction_id' => $d->pivot->deduction_id,
            'date' => $d->pivot->date,
            'amount' => $d->pivot->amount,
        ])->all();

        $validator = $this->validateThroughRequest($invoice, $deductions);
        $amountErrors = collect($validator->errors()->keys())
            ->filter(fn ($key) => str_contains($key, 'amount'))->values()->all();

        $this->assertSame([], $amountErrors,
            'حفظ نفس الخصومات لازم يعدّي — رسالة الخطأ: '
            .implode(' | ', $validator->errors()->get('deductions.0.amount')));
    }

    /** * و عبر نفس الطلب : الخصم الزائد لازم يفضل مرفوض */
    public function test_the_request_still_refuses_an_excessive_deduction(): void
    {
        $invoice = $this->invoiceWithDeductions();
        $allowance = $this->allowanceFor($invoice);

        $first = $invoice->deductions->first();
        $validator = $this->validateThroughRequest($invoice, [[
            'deduction_id' => $first->pivot->deduction_id,
            'date' => $first->pivot->date,
            'amount' => $allowance + 5000,
        ]]);

        $this->assertTrue($validator->fails(), 'الخصم الزائد لازم يترفض من الطلب نفسه');
    }

    /* ───────────── اللي لازم يفضل مرفوض ───────────── */

    public function test_deducting_more_than_the_allowance_is_still_refused(): void
    {
        $invoice = $this->invoiceWithDeductions();
        $allowance = $this->allowanceFor($invoice);

        $this->assertFalse(
            $this->ruleAllows($allowance, [$allowance + 1000]),
            'الخصم الزائد لازم يفضل مرفوض — الإصلاح مش المفروض يفتح الباب'
        );
    }

    public function test_deducting_exactly_the_allowance_is_accepted(): void
    {
        $invoice = $this->invoiceWithDeductions();
        $allowance = $this->allowanceFor($invoice);

        $this->assertTrue($this->ruleAllows($allowance, [$allowance]));
    }

    /** * المبالغ المتفرمتة من الفورمة ما تكسرش الجمع */
    public function test_formatted_amounts_are_summed_correctly(): void
    {
        $this->assertTrue($this->ruleAllows(3000.0, ['1,000.00', '2,000.00']),
            '1,000 + 2,000 لازم تساوي 3,000 مش تتعامل كنص');
        $this->assertFalse($this->ruleAllows(2999.0, ['1,000.00', '2,000.00']));
    }

    /* ───────────── فواتير الموردين ───────────── */

    /**
     * * الإصلاح في IsInvoice (تريت مشترك) و في طلب واحد بياخد نوع الفاتورة
     * * كبارامتر — فالمفروض يشتغل على فواتير الموردين زي العملاء بالظبط
     *
     * * الاختبارات فوق بتشتغل على فواتير العملاء لأن دي اللي فيها بيانات ،
     * * فالحالة هنا بتتبني و بترجع عشان الجهة التانية ما تفضلش من غير غطاء
     */
    public function test_a_supplier_invoice_behaves_the_same(): void
    {
        $invoice = SupplierInvoice::whereRaw('net_balance > 0')->first();
        $deduction = Deduction::first();

        if (! $invoice || ! $deduction) {
            $this->markTestSkipped('Needs a supplier invoice with a balance and a deduction type.');
        }

        DB::beginTransaction();

        try {
            // خصم يمشي دلوقتي ، بس بيسيب رصيد أقل من نفسه — دي الحالة اللي كانت بتفشل
            $amount = round((float) $invoice->net_balance * 0.9, 2);

            InvoiceDeduction::create([
                'invoice_type' => 'SupplierInvoice',
                'invoice_id' => $invoice->id,
                'deduction_id' => $deduction->id,
                'date' => '2026-01-01',
                'amount' => $amount,
                'company_id' => $invoice->company_id,
                'amount_in_main_currency' => $amount,
                'amount_in_invoice_exchange_rate' => $amount,
                'foreign_gain_or_loss' => 0,
            ]);

            $invoice->update(['net_balance' => (float) $invoice->net_balance - $amount]);
            $invoice->refresh()->load('deductions');

            $this->assertLessThan($amount, $invoice->getNetBalance(),
                'الحالة المبنية لازم تعيد إنتاج الباج، و إلا الاختبار مش بيثبت حاجة');

            $validator = $this->validateThroughRequest($invoice, [[
                'deduction_id' => $deduction->id,
                'date' => '2026-01-01',
                'amount' => $amount,
            ]], 'SupplierInvoice');

            $amountErrors = collect($validator->errors()->keys())
                ->filter(fn ($key) => str_contains($key, 'amount'))->values()->all();

            $this->assertSame([], $amountErrors,
                'فاتورة المورد لازم تتصرف زي فاتورة العميل بالظبط');

            // و الزيادة لسه مرفوضة على جهة الموردين كمان
            $tooMuch = $this->validateThroughRequest($invoice, [[
                'deduction_id' => $deduction->id,
                'date' => '2026-01-01',
                'amount' => $amount + 100000,
            ]], 'SupplierInvoice');

            $this->assertTrue($tooMuch->fails(), 'الخصم الزائد لازم يترفض للموردين كمان');
        } finally {
            DB::rollBack();
        }
    }

    /** * و التقريب شغال على الجهتين لأنه في التريت المشترك */
    public function test_both_invoice_types_share_the_rounded_net_balance(): void
    {
        foreach ([CustomerInvoice::class, SupplierInvoice::class] as $class) {
            $invoice = new $class;
            $invoice->forceFill(['net_balance' => 110579.89000000013]);

            $this->assertSame(110579.89, $invoice->getNetBalance(),
                class_basename($class).' لازم يقرّب الرصيد زي التاني');
            $this->assertSame('110,579.89', $invoice->getNetBalanceFormatted());
        }
    }

    /* ───────────── صافي الرصيد و الكسور ───────────── */

    /**
     * * net_balance عمود double بيتراكم عليه انحراف الفاصلة العائمة —
     * * أرصدة زي 110579.89000000013 موجودة فعلا في الداتا
     */
    public function test_the_net_balance_is_rounded_to_two_decimals(): void
    {
        $drifting = CustomerInvoice::whereRaw('net_balance <> ROUND(net_balance, 2)')->first();

        if (! $drifting) {
            $this->markTestSkipped('No invoice with a drifting net balance on file.');
        }

        $raw = (float) $drifting->getRawOriginal('net_balance');
        $reported = $drifting->getNetBalance();

        $this->assertSame(round($raw, 2), $reported, 'الرصيد لازم يترجع مقرّب لخانتين');
        $this->assertEqualsWithDelta($raw, $reported, 0.01, 'التقريب ما ينفعش يغيّر القيمة فعليًا');
    }

    /**
     * * رصيد زي 0.0000001 كان بيعدّي شرط "> 0" فالفاتورة المسدّدة تبان
     * * كإن عليها باقي و يظهر لها زرار الخصم
     */
    public function test_a_rounding_residue_does_not_read_as_an_outstanding_balance(): void
    {
        $residue = CustomerInvoice::whereRaw('net_balance <> ROUND(net_balance, 2)')
            ->whereRaw('ABS(net_balance) < 0.005')
            ->first();

        if (! $residue) {
            $this->markTestSkipped('No invoice with a sub-cent residue on file.');
        }

        $this->assertSame(0.0, abs($residue->getNetBalance()),
            'الكسر الضئيل ده لازم يبقى صفر، مش رصيد قائم');
    }

    /** * و العرض بخانتين عشرية عشان الكسر الحقيقي ما يختفيش */
    public function test_the_net_balance_is_displayed_with_two_decimals(): void
    {
        $invoice = CustomerInvoice::whereRaw('net_balance <> ROUND(net_balance, 0)')
            ->whereRaw('ABS(net_balance) > 1')->first();

        if (! $invoice) {
            $this->markTestSkipped('No invoice with a fractional net balance on file.');
        }

        $this->assertMatchesRegularExpression('/\.\d{2}$/', $invoice->getNetBalanceFormatted(),
            'الرصيد لازم يتعرض بخانتين، و إلا الكسور بتختفي');
    }
}
