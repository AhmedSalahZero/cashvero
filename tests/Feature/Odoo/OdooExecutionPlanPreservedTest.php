<?php

namespace Tests\Feature\Odoo;

use App\Models\Contract;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Services\Api\OdooService;
use Illuminate\Support\Facades\DB;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * * خطة التنفيذ (بوب اب الـ execution) بيملاها المستخدم بايده — اودو
 * * مالهاش خطة ، عندها تاريخ بداية و نهاية المشروع بس
 *
 * * كانت كل قراءة للعقد من اودو بتكتب تواريخ المشروع فوق الخانة و
 * * بتمسح اللي المستخدم دخّله ، و كمان رقم الخانة كان جاي من ترتيب
 * * الامر في المشروع فاختلاف الترتيب كان بيملي خانة جديدة و يسيب
 * * القديمة (مرحلتين بـ 100% لنفس الامر)
 *
 * * التستات دي بتثبت القاعدة الجديدة : خطة مليانة = النسب و تواريخ
 * * البداية و ايام التحصيل ما بتتلمسش ، و اللي بيتحدّث تاريخ نهاية
 * * اخر مرحلة بس . خطة فاضية = خانة واحدة بـ 100% من بداية المشروع
 * * لنهايته
 */
class OdooExecutionPlanPreservedTest extends TestCase
{
    private ?string $originalDatabase = null;

    private bool $inTransaction = false;

    private int $companyId = 0;

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
            'name' => json_encode(['en' => 'Execution Plan Test Co', 'ar' => 'Execution Plan Test Co']),
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

    /* ───────────────────── أدوات ───────────────────── */

    /**
     * * الميثود protected و الـ constructor عايز شركة عندها تكامل اودو ،
     * * و احنا مش بنكلم اودو اصلا هنا — فبنعمل النسخة من غير constructor
     */
    private function planKeys($oldOrder, ?string $start, ?string $end): array
    {
        $service = (new ReflectionClass(OdooService::class))->newInstanceWithoutConstructor();

        $method = new ReflectionMethod(OdooService::class, 'executionPlanKeysFromOdoo');
        $method->setAccessible(true);

        return $method->invokeArgs($service, [$oldOrder, $start, $end]);
    }

    private function contract(string $start, string $end): Contract
    {
        $id = (int) DB::table('contracts')->insertGetId([
            'company_id' => $this->companyId,
            'name' => 'Execution Plan Contract',
            'model_type' => 'Customer',
            'currency' => 'EGP',
            'exchange_rate' => 1,
            'amount' => 100000,
            'start_date' => $start,
            'end_date' => $end,
        ]);

        return Contract::findOrFail($id);
    }

    /* ───────────────────── الخطة الفاضية ───────────────────── */

    public function test_an_order_that_has_no_plan_yet_gets_one_full_phase_from_the_project_dates(): void
    {
        $keys = $this->planKeys(null, '2026-01-01', '2026-06-30');

        $this->assertSame([
            'start_date_1' => '2026-01-01',
            'end_date_1' => '2026-06-30',
            'collection_days_1' => 0,
            'execution_percentage_1' => 100,
        ], $keys);
    }

    public function test_an_existing_order_with_an_empty_plan_gets_the_project_dates_but_a_recorded_zero_is_kept(): void
    {
        $order = new SalesOrder;
        $order->execution_percentage_1 = 0;

        $keys = $this->planKeys($order, '2026-01-01', '2026-06-30');

        $this->assertSame('2026-01-01', $keys['start_date_1']);
        $this->assertSame('2026-06-30', $keys['end_date_1']);
        $this->assertArrayNotHasKey(
            'execution_percentage_1',
            $keys,
            'الصفر قرار المستخدم — مش المفروض يرجع 100'
        );
    }

    /* ───────────────────── الخطة المليانة ───────────────────── */

    public function test_a_filled_plan_only_gets_the_end_date_of_its_last_phase_moved(): void
    {
        $order = new SalesOrder;
        $order->execution_percentage_1 = 30;
        $order->start_date_1 = '2025-01-01';
        $order->end_date_1 = '2025-03-31';
        $order->execution_percentage_2 = 50;
        $order->start_date_2 = '2025-04-01';
        $order->end_date_2 = '2025-08-31';
        $order->execution_percentage_3 = 20;
        $order->start_date_3 = '2025-09-01';
        $order->end_date_3 = '2025-12-31';

        $keys = $this->planKeys($order, '2025-01-01', '2026-06-30');

        $this->assertSame(['end_date_3' => '2026-06-30'], $keys);
    }

    /**
     * * الداتا القديمة فيها امور خطتها بدأت من خانة 2 او 3 بسبب باج
     * * ترتيب الامر — لازم نلاقي اخر خانة مليانة فعلا مش نفترض الاولى
     */
    public function test_the_last_filled_phase_is_found_even_when_the_plan_does_not_start_at_the_first_slot(): void
    {
        $order = new SalesOrder;
        $order->execution_percentage_2 = 100;
        $order->start_date_2 = '2025-02-17';
        $order->end_date_2 = '2025-12-17';

        $keys = $this->planKeys($order, '2025-02-17', '2026-08-15');

        $this->assertSame(['end_date_2' => '2026-08-15'], $keys);
    }

    public function test_a_phase_that_has_only_a_start_date_and_no_percentage_still_counts_as_filled(): void
    {
        $order = new SalesOrder;
        $order->execution_percentage_1 = 0;
        $order->start_date_1 = '2025-01-01';

        $keys = $this->planKeys($order, '2026-01-01', '2026-06-30');

        $this->assertSame(['end_date_1' => '2026-06-30'], $keys);
    }

    public function test_purchase_orders_are_protected_by_the_same_rule(): void
    {
        $order = new PurchaseOrder;
        $order->execution_percentage_1 = 40;
        $order->start_date_1 = '2025-01-01';
        $order->end_date_1 = '2025-06-30';
        $order->execution_percentage_2 = 60;
        $order->start_date_2 = '2025-07-01';
        $order->end_date_2 = '2025-12-31';

        $keys = $this->planKeys($order, '2025-01-01', '2026-03-31');

        $this->assertSame(['end_date_2' => '2026-03-31'], $keys);
    }

    /* ───────────────────── الحفظ الحقيقي ───────────────────── */

    /**
     * * دي اللي بتمسك الباج اللي العميل شافه : نكتب خطة بالايد ، نعدي
     * * على نفس مسار الحفظ اللي المزامنة بتستخدمه ، و نتأكد ان الخطة
     * * لسه مكانها في الداتابيز
     */
    public function test_resyncing_the_contract_does_not_overwrite_a_manually_entered_plan(): void
    {
        $contract = $this->contract('2025-01-01', '2025-12-31');

        $salesOrderId = (int) DB::table('sales_orders')->insertGetId([
            'company_id' => $this->companyId,
            'contract_id' => $contract->id,
            'odoo_id' => 987654,
            'so_number' => 'SO-PLAN-1',
            'amount' => 100000,
            'execution_percentage_1' => 25,
            'start_date_1' => '2025-01-01',
            'end_date_1' => '2025-03-31',
            'collection_days_1' => 30,
            'execution_percentage_2' => 35,
            'start_date_2' => '2025-04-01',
            'end_date_2' => '2025-08-31',
            'collection_days_2' => 45,
            'execution_percentage_3' => 40,
            'start_date_3' => '2025-09-01',
            'end_date_3' => '2025-12-31',
            'collection_days_3' => 60,
        ]);

        $oldSalesOrder = SalesOrder::findOrFail($salesOrderId);

        // نفس اللي المزامنة بتبنيه بعد التعديل ، بتاريخ مشروع اطول من اودو
        $formatted = [
            'id' => $salesOrderId,
            'odoo_id' => 987654,
            'so_number' => 'SO-PLAN-1',
            'amount' => 120000,
            'company_id' => $this->companyId,
        ] + $this->planKeys($oldSalesOrder, '2025-01-01', '2026-06-30');

        $contract->storeBasicForm((new \Illuminate\Http\Request)->merge([
            'company_id' => $this->companyId,
            'amount' => 120000,
            'salesOrders' => [$formatted],
        ]));

        $fresh = SalesOrder::findOrFail($salesOrderId);

        $this->assertEquals(25, (float) $fresh->execution_percentage_1);
        $this->assertEquals(35, (float) $fresh->execution_percentage_2);
        $this->assertEquals(40, (float) $fresh->execution_percentage_3);

        $this->assertSame('2025-01-01', (string) $fresh->start_date_1);
        $this->assertSame('2025-04-01', (string) $fresh->start_date_2);
        $this->assertSame('2025-09-01', (string) $fresh->start_date_3);

        $this->assertSame('2025-03-31', (string) $fresh->end_date_1);
        $this->assertSame('2025-08-31', (string) $fresh->end_date_2);

        $this->assertEquals(30, (float) $fresh->collection_days_1);
        $this->assertEquals(45, (float) $fresh->collection_days_2);
        $this->assertEquals(60, (float) $fresh->collection_days_3);

        // اللي المفروض يتغير : نهاية اخر مرحلة بس
        $this->assertSame('2026-06-30', (string) $fresh->end_date_3);

        // و ما تتفتحش خانة رابعة
        $this->assertNull($fresh->start_date_4);
        $this->assertEquals(0, (float) $fresh->execution_percentage_4);
    }

    /* ───────────────────── حرس على الكود ───────────────────── */

    /**
     * * رقم الخانة ما يرجعش تاني ياخد من ترتيب الامر في المشروع — لو
     * * رجع ، الخانات بتتكرر و مجموع النسب بيطلع 200%
     */
    public function test_the_sync_no_longer_builds_phase_columns_from_the_order_position(): void
    {
        /**
         * * فيه نسخة قديمة من المزامنة متعلق عليها كومنت في نفس الملف ،
         * * فبنفحص الكود اللي بيشتغل فعلا بس
         */
        $source = '';

        foreach (token_get_all(file_get_contents((new ReflectionClass(OdooService::class))->getFileName())) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $source .= is_array($token) ? $token[1] : $token;
        }

        foreach (['start_date_', 'end_date_', 'collection_days_', 'execution_percentage_'] as $column) {
            $this->assertStringNotContainsString(
                "'".$column."'.\$currentOrderIndex",
                $source,
                $column.' لازم يتحدد من خطة الامر نفسها مش من ترتيبه'
            );
        }

        $this->assertStringNotContainsString(
            "'start_date_1'=>\$startDate",
            $source,
            'امر الشراء كان بيكتب تاريخ البداية على طول — لازم يعدي على executionPlanKeysFromOdoo'
        );
    }
}
