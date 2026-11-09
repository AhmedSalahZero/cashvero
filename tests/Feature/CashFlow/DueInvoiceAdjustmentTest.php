<?php

namespace Tests\Feature\CashFlow;

use App\Http\Controllers\CashFlowReportController;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MakesInvoices;
use Tests\TestCase;

/**
 * * ضبط الفواتير المتأخرة في تقرير التدفق النقدي .
 *
 * * الباج اللي حصل في الإنتاج (17-09-2026) : المودال بيعرض كل الفواتير
 * * المتأخرة و بيبعتها كلها ، حتى اللي المستخدم ما اختارلهاش أسبوع .
 * * ميدلوير ConvertEmptyStringsToNull بيحوّل الأسبوع الفاضي لـ null ،
 * * و العمود week_start_date نوعه NOT NULL — فالصفحة كانت بتقع بـ ٥٠٠
 * * بدل ما تحفظ اللي المستخدم ظبطه فعلا .
 *
 * * الإصلاح في مكانين : الواجهة ما بتبعتش الصفوف دي ، و الكونترولر
 * * بيتخطّاها لو وصلته من أي مكان تاني .
 *
 * * الشركة و العميل و الفواتير بيتعملوا في الاختبار نفسه .
 */
class DueInvoiceAdjustmentTest extends TestCase
{
    use MakesInvoices;

    private Company $company;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->makeCompany();
        $this->user = $this->makeUser([], $this->company);
    }

    private function submit(array $payload): string
    {
        $request = Request::create('/x', 'POST', $payload);
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => $this->user);
        $this->app->instance('request', $request);
        auth()->setUser($this->user);

        try {
            app(CashFlowReportController::class)
                ->adjustCustomerDueInvoices($request, $this->company);

            return 'ok';
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    /**
     * فاتورتين متأخرتين لعميل شركة الاختبار — زي اللي المودال بيعرضهم
     *
     * @return array{0:int,1:int}
     */
    private function twoInvoices(): array
    {
        $customer = $this->customer($this->company);

        return [
            $this->invoice($customer, ['invoice_amount' => 1000, 'invoice_due_date' => now()->subMonths(2)->format('Y-m-d')])->id,
            $this->invoice($customer, ['invoice_amount' => 88816.98, 'invoice_due_date' => now()->subMonth()->format('Y-m-d')])->id,
        ];
    }

    /**
     * * الطلب اللي وقّع الإنتاج : فاتورة متحدد ليها أسبوع و التانية لأ .
     * * المفروض ينجح و يحفظ المختارة بس .
     */
    public function test_a_row_without_a_week_no_longer_breaks_the_save(): void
    {
        [$chosen, $untouched] = $this->twoInvoices();

        $result = $this->submit([
            'invoiceType' => 'CustomerInvoice',
            'currency_name' => 'EGP',
            'is_contract' => 0,
            'cashflow_report_id' => 0,
            'customer_invoice_id' => [$chosen, $untouched],
            'invoice_amount' => [$chosen => 1000, $untouched => 88816.98],
            'percentage' => [$chosen => 100, $untouched => 100],
            // null هو اللي بيوصل فعلا : الميدلوير بيحوّل '' لـ null
            'week_start_date' => [$chosen => '2026-01-05', $untouched => null],
        ]);

        $this->assertSame('ok', $result, 'الحفظ وقع: '.$result);

        $saved = fn (int $id) => DB::table('weekly_cashflow_custom_due_invoices')
            ->where('company_id', $this->company->id)->where('invoice_id', $id)->exists();

        $this->assertTrue($saved($chosen), 'الفاتورة اللي المستخدم اختارلها أسبوع لازم تتحفظ');
        $this->assertFalse($saved($untouched), 'الفاتورة اللي ما لمسهاش ما تتحفظش');
    }

    /** * و الأسبوع الفاضي (قبل ما الميدلوير يحوّله) بيتخطّى برضه */
    public function test_an_empty_week_is_skipped_too(): void
    {
        [$chosen, $untouched] = $this->twoInvoices();

        $result = $this->submit([
            'invoiceType' => 'CustomerInvoice',
            'currency_name' => 'EGP',
            'is_contract' => 0,
            'cashflow_report_id' => 0,
            'customer_invoice_id' => [$chosen, $untouched],
            'invoice_amount' => [$chosen => 1000, $untouched => 2000],
            'percentage' => [$chosen => 100, $untouched => 100],
            'week_start_date' => [$chosen => '2026-01-05', $untouched => '   '],
        ]);

        $this->assertSame('ok', $result, 'الحفظ وقع: '.$result);
        $this->assertFalse(
            DB::table('weekly_cashflow_custom_due_invoices')
                ->where('company_id', $this->company->id)->where('invoice_id', $untouched)->exists()
        );
    }

    /** * و لو كل الصفوف من غير أسبوع ، مفيش حاجة تتحفظ و مفيش خطأ */
    public function test_nothing_is_saved_when_no_week_was_chosen(): void
    {
        [$a, $b] = $this->twoInvoices();

        $before = DB::table('weekly_cashflow_custom_due_invoices')
            ->where('company_id', $this->company->id)->count();

        $result = $this->submit([
            'invoiceType' => 'CustomerInvoice',
            'currency_name' => 'EGP',
            'is_contract' => 0,
            'cashflow_report_id' => 0,
            'customer_invoice_id' => [$a, $b],
            'invoice_amount' => [$a => 1000, $b => 2000],
            'percentage' => [$a => 100, $b => 100],
            'week_start_date' => [$a => null, $b => null],
        ]);

        $this->assertSame('ok', $result, 'الحفظ وقع: '.$result);
        $this->assertSame($before, DB::table('weekly_cashflow_custom_due_invoices')
            ->where('company_id', $this->company->id)->count());
    }

    /** * و الواجهة كمان ما بتبعتش الصفوف دي أصلا */
    public function test_the_page_filters_unselected_rows_before_sending(): void
    {
        $page = file_get_contents(resource_path('js/Pages/CashFlowReport/Result.vue'));

        foreach (['submitDueInvoiceModal', 'submitLoanInstallmentModal'] as $fn) {
            $start = strpos($page, "async function {$fn}(");
            $this->assertNotFalse($start, "مش لاقي {$fn}");

            $body = substr($page, $start, 900);

            $this->assertStringContainsString('.filter(', $body,
                "{$fn} لازم يفلتر الصفوف اللي من غير أسبوع قبل الإرسال");
            $this->assertStringContainsString('week_start_date', $body);
        }
    }

    /** * و الكونترولر بيتخطّى الصف الناقص في المودالين */
    public function test_the_controller_skips_incomplete_rows_in_both_modals(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/CashFlowReportController.php'));

        $this->assertSame(2, substr_count($source, "trim((string) \$weekStartDate) === ''"),
            'المودالين — الفواتير و أقساط القروض — لازم يتخطّوا الصف اللي من غير أسبوع');
    }
}
