<?php

namespace Tests\Feature\Imports;

use App\Models\ActiveJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * * صفحة رفع الإكسل بتخفي فورمة الرفع طول ما فيه صف في active_jobs
 *
 * * لو الـ job مات من غير ما يرمي ImportFailed (الـ worker اتقفل ، الذاكرة
 * * خلصت ، انقطاع) الصف كان بيفضل موجود للأبد : الصفحة تقول "جاري
 * * المعالجة" على طول ، من غير سبب ، و من غير أي طريقة يرفع تاني
 *
 * * كل الكتابة هنا جوه ترانزاكشن بترجع
 */
class StuckImportRecoveryTest extends TestCase
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

    private function job(array $attributes = []): ActiveJob
    {
        $job = new ActiveJob;
        $job->forceFill(array_merge([
            'id' => 999999,
            'company_id' => 92,
            'status' => 'test_table',
            'model' => 'CustomerInvoice',
            'model_name' => 'SalesGatheringTest',
            'failed_at' => null,
            'failure_reason' => null,
            'created_at' => Carbon::now(),
        ], $attributes));

        return $job;
    }

    /* ───────────── التعرّف على الحالة ───────────── */

    public function test_a_running_import_needs_no_attention(): void
    {
        $job = $this->job(['created_at' => Carbon::now()->subMinutes(2)]);

        $this->assertFalse($job->hasFailed());
        $this->assertFalse($job->isStale());
        $this->assertFalse($job->needsAttention(), 'الرفع اللي لسه شغال ما يزعجش المستخدم');
    }

    public function test_a_failed_import_reports_its_reason(): void
    {
        $job = $this->job();
        $job->failed_at = Carbon::now();
        $job->failure_reason = 'Excel Import Failed — Undefined column: invoice_date';

        $this->assertTrue($job->hasFailed());
        $this->assertTrue($job->needsAttention());
        $this->assertStringContainsString('Undefined column', $job->statusMessage());
    }

    /**
     * * الحالة اللي كانت بتقفل الشاشة : مات من غير ما يبلّغ ، فمفيش failed_at
     * * و مفيش سبب — بنعرفه من الوقت
     */
    public function test_an_import_that_died_silently_is_detected_as_stale(): void
    {
        $job = $this->job([
            'created_at' => Carbon::now()->subMinutes(ActiveJob::STALE_AFTER_MINUTES + 5),
        ]);

        $this->assertFalse($job->hasFailed(), 'مات من غير ما يسجّل فشل');
        $this->assertTrue($job->isStale());
        $this->assertTrue($job->needsAttention());
        $this->assertNotSame('', $job->statusMessage(), 'لازم يقول للمستخدم حاجة مفهومة');
    }

    public function test_the_staleness_boundary_is_respected(): void
    {
        $justInside = $this->job(['created_at' => Carbon::now()->subMinutes(ActiveJob::STALE_AFTER_MINUTES - 1)]);
        $justOutside = $this->job(['created_at' => Carbon::now()->subMinutes(ActiveJob::STALE_AFTER_MINUTES + 1)]);

        $this->assertFalse($justInside->isStale());
        $this->assertTrue($justOutside->isStale());
    }

    /** * الفشل المسجّل مالوش لازمة يتقال عنه "واقف" كمان — سبب واحد يكفي */
    public function test_a_failed_import_is_not_also_reported_as_stale(): void
    {
        $job = $this->job([
            'created_at' => Carbon::now()->subMinutes(ActiveJob::STALE_AFTER_MINUTES + 5),
            'failed_at' => Carbon::now(),
            'failure_reason' => 'boom',
        ]);

        $this->assertFalse($job->isStale());
        $this->assertSame('boom', $job->statusMessage());
    }

    /* ───────────── التسجيل ───────────── */

    public function test_marking_a_job_failed_stores_the_reason(): void
    {
        DB::beginTransaction();

        try {
            $job = ActiveJob::create([
                'company_id' => 92,
                'status' => 'test_table',
                'model' => 'ZzTestModel',
                'model_name' => 'SalesGatheringTest',
            ]);

            $job->markFailed('Excel Import Failed — out of memory');
            $job->refresh();

            $this->assertNotNull($job->failed_at);
            $this->assertStringContainsString('out of memory', $job->failure_reason);
            $this->assertTrue($job->needsAttention());
        } finally {
            DB::rollBack();
        }
    }

    /** * السبب الطويل ما يكسرش العمود */
    public function test_a_very_long_reason_is_truncated(): void
    {
        DB::beginTransaction();

        try {
            $job = ActiveJob::create([
                'company_id' => 92,
                'status' => 'test_table',
                'model' => 'ZzTestModel',
                'model_name' => 'SalesGatheringTest',
            ]);

            $job->markFailed(str_repeat('x', 8000));
            $job->refresh();

            $this->assertLessThanOrEqual(2000, mb_strlen((string) $job->failure_reason));
        } finally {
            DB::rollBack();
        }
    }

    /* ───────────── التوصيلات ───────────── */

    /** * لازم يكون فيه راوت يفرّغ الحالة الواقفة */
    public function test_the_reset_route_exists_and_is_a_get(): void
    {
        $route = \Illuminate\Support\Facades\Route::getRoutes()->getByName('salesGatheringTest.resetImport');

        $this->assertNotNull($route, 'الراوت اللي بيفرّغ الرفع الواقف مش متسجل');
        $this->assertContains('GET', $route->methods());
    }

    /**
     * * الاستيراد لازم يسجّل الفشل على الصف ، مش يمسحه و السبب يضيع في الكاش
     */
    public function test_the_import_records_the_failure_on_the_row(): void
    {
        $source = file_get_contents(app_path('Imports/ImportData.php'));
        $start = strpos($source, 'public function registerEvents');
        $this->assertNotFalse($start);
        $body = substr($source, $start, 1800);

        $this->assertStringContainsString('markFailed', $body,
            'لازم يسجّل الفشل على صف active_jobs');
        $this->assertStringNotContainsString("ActiveJob::where('id', \$this->job_id)->where('model',\$this->uploadModelName)->delete();", $body,
            'مسح الصف كان بيضيّع السبب');
    }

    /** * الصفحة لازم تاخد وصف الحالة الواقفة و تبطّل تلف على نفسها */
    public function test_the_page_is_told_about_a_stuck_import(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/SalesGatheringTestController.php'));
        $this->assertStringContainsString("'stuckImport' =>", $controller);
        $this->assertStringContainsString('public function resetImport', $controller);

        $vue = file_get_contents(resource_path('js/Pages/InvoiceUpload/Import.vue'));
        $this->assertStringContainsString('stuckImport', $vue);
        $this->assertStringContainsString('stuckImport.resetUrl', $vue, 'لازم يكون فيه زرار يرجّع الشاشة');
        $this->assertStringContainsString('!stuckImport && isParsing', $vue,
            'شاشة "جاري المعالجة" ما تفضلش ظاهرة فوق حالة واقفة');
    }

    /** * الرفع الجديد على نفس الصف لازم يشيل أثر الفشل القديم */
    public function test_a_reused_job_row_clears_the_previous_failure(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/SalesGatheringTestController.php'));

        $this->assertMatchesRegularExpression(
            "/\\\$active_job->update\(\[\s*'failed_at' => null,\s*'failure_reason' => null,/",
            $controller,
            'إعادة استخدام الصف من غير تنظيف بتخلي الرفع الجديد يبان فاشل'
        );
    }
}
