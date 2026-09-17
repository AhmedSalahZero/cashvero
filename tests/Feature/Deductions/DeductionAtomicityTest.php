<?php

namespace Tests\Feature\Deductions;

use App\Http\Controllers\InvoiceDeductionsController;
use App\Models\Company;
use App\Models\CustomerInvoice;
use App\Models\Deduction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * * تعديل الخصومات لازم يتم كله أو ما يتمش .
 *
 * * الميثود معمولة "امسح الكل و اكتب من تاني" ، و أول update بيحفظ
 * * net_balance المعدّل كمان (Eloquent بيحفظ كل الحقول المتغيّرة مش
 * * اللي بتتبعت بس) . فمن غير ترانزاكشن ، لو الكتابة وقعت في النص
 * * الفاتورة بتفضل برصيد مخفّض و من غير خصومات تسنده — فلوس بتختفي من
 * * الذمم و مفيش حاجة على الشاشة بتقول .
 *
 * * الاختبار ده بيفشّل الكتابة عن قصد و بيتأكد إن مفيش حاجة اتغيّرت .
 */
class DeductionAtomicityTest extends TestCase
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

    /** * الكود لازم يفضل ملفوف — لو حد شال اللفّة الاختبار يقع */
    public function test_the_update_is_wrapped_in_a_transaction(): void
    {
        $reflection = new \ReflectionMethod(InvoiceDeductionsController::class, 'update');
        $lines = file($reflection->getFileName());
        $body = implode('', array_slice(
            $lines,
            $reflection->getStartLine() - 1,
            $reflection->getEndLine() - $reflection->getStartLine() + 1
        ));

        $this->assertStringContainsString('DB::transaction(', $body,
            'تعديل الخصومات لازم يكون جوه ترانزاكشن');

        // و المسح و الكتابة لازم يكونوا جوه اللفّة ، مش قبلها
        $inside = substr($body, strpos($body, 'DB::transaction('));

        foreach (['deductions()->detach()', 'InvoiceDeduction::create('] as $step) {
            $this->assertStringContainsString($step, $inside,
                "الخطوة {$step} لازم تكون جوه الترانزاكشن");
        }
    }

    /**
     * * الاختبار الحقيقي : نفشّل الكتابة **بعد** ما الخصومات اتمسحت و
     * * الرصيد اتحفظ ، و نشوف الفاتورة رجعت زي ما كانت ولا لأ .
     *
     * * ⚠️ نقطة الفشل مهمة : أول محاولة عملتها كانت بتفشل عند فحص
     * * الرصيد في أول الميثود — يعني قبل المسح أصلا — فالاختبار كان
     * * بيعدّي حتى من غير ترانزاكشن . الفشل هنا بيتعمل بتاريخ غير صالح ،
     * * و ده بيتقرا في calculateAmountInMainCurrency اللي بتتنادى بعد
     * * detach() و بعد ما الرصيد اتحفظ — و دي بالظبط اللحظة الخطيرة .
     */
    public function test_a_failure_after_the_delete_leaves_the_invoice_untouched(): void
    {
        /* فاتورة الحارس بتاعها بيعدّي ، و إلا الاختبار بيقف قبل المسح */
        $invoice = CustomerInvoice::whereHas('deductions')->get()
            ->first(fn (CustomerInvoice $i) => ((float) $i->net_balance + (float) $i->deductions->sum('pivot.amount')) > 10);

        if (! $invoice) {
            $this->markTestSkipped('Needs an invoice with deductions and a usable balance.');
        }

        DB::beginTransaction();

        try {
            $company = Company::findOrFail($invoice->company_id);
            $user = User::firstOrFail();

            $balanceBefore = (float) $invoice->net_balance;
            $totalBefore = (float) $invoice->total_deductions;
            $countBefore = DB::table('invoice_deductions')
                ->where('invoice_type', 'CustomerInvoice')
                ->where('invoice_id', $invoice->id)->count();

            $this->assertGreaterThan(0, $countBefore, 'الفاتورة المفروض عليها خصومات');

            $request = \App\Http\Requests\UpdateInvoiceDeductionRequest::create('/x', 'PATCH', [
                'deductions' => [[
                    'deduction_id' => $invoice->deductions->first()->id,
                    // تاريخ ما ينفعش يتقرا — بيقع في تحويل العملة بعد المسح
                    'date' => 'not-a-date',
                    'amount' => 1,
                ]],
            ]);
            $request->setLaravelSession(app('session.store'));
            $request->setUserResolver(fn () => $user);
            $this->app->instance('request', $request);
            auth()->setUser($user);

            $failedAfterTheDelete = false;

            try {
                app(InvoiceDeductionsController::class)
                    ->update($request, $company, $invoice->id, 'CustomerInvoice');
            } catch (\Carbon\Exceptions\InvalidFormatException $e) {
                $failedAfterTheDelete = true;
            } catch (\Throwable $e) {
                $this->fail('المفروض يقع في قراءة التاريخ بعد المسح، وقع في: '.get_class($e).' — '.$e->getMessage());
            }

            $this->assertTrue($failedAfterTheDelete,
                'الكتابة المفروض تفشل بعد المسح — الاختبار مالوش معنى من غير كده');

            $after = CustomerInvoice::find($invoice->id);
            $countAfter = DB::table('invoice_deductions')
                ->where('invoice_type', 'CustomerInvoice')
                ->where('invoice_id', $invoice->id)->count();

            $this->assertSame($countBefore, $countAfter,
                'الخصومات اتمسحت و ما رجعتش — الترانزاكشن مش شغالة');
            $this->assertEqualsWithDelta($balanceBefore, (float) $after->net_balance, 0.01,
                'الرصيد اتغيّر رغم إن العملية فشلت — دي الفلوس اللي بتختفي من الذمم');
            $this->assertEqualsWithDelta($totalBefore, (float) $after->total_deductions, 0.01,
                'إجمالي الخصومات اتغيّر رغم إن العملية فشلت');
        } finally {
            DB::rollBack();
        }
    }
}
