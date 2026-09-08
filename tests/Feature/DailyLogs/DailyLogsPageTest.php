<?php

namespace Tests\Feature\DailyLogs;

use App\Http\Controllers\DailyLogController;
use App\Models\Company;
use App\Models\RecordActivity;
use App\Models\User;
use App\Support\Activity\ActivityRegistry;
use App\Support\Permissions\PermissionRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * * صفحة السجل اليومي : كل التعديلات المسجّلة في مكان واحد ، تاب لكل نوع سجل
 *
 * * الحاجات المهمة هنا :
 * *   - التقسيم الصفحي في قاعدة البيانات مش في الميموري (الجدول بيكبر)
 * *   - التابات متقيّدة بصلاحية كل موديول ، فاللي مش بيشوف موديل ما
 * *     يشوفش تاريخه من هنا
 * *   - الصفحة نفسها ليها صلاحية مستقلة
 */
class DailyLogsPageTest extends TestCase
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

    private function company(): Company
    {
        $company = Company::find(92) ?: Company::first();

        if (! $company) {
            $this->markTestSkipped('No company on file.');
        }

        return $company;
    }

    private function user(): User
    {
        $user = User::first();

        if (! $user) {
            $this->markTestSkipped('No user on file.');
        }

        return $user;
    }

    private function props(string $query = ''): array
    {
        $user = $this->user();
        $request = Request::create('/daily-logs'.$query, 'GET');
        $request->setUserResolver(fn () => $user);

        $response = (new DailyLogController)($request, $this->company());

        return $response->toResponse($request)->getOriginalContent()->getData()['page']['props'];
    }

    /* ───────────── التابات ───────────── */

    public function test_it_offers_a_tab_per_logged_record_type(): void
    {
        $props = $this->props();

        $this->assertNotEmpty($props['tabs'], 'لازم يكون فيه تابات');
        $this->assertNotEmpty($props['activeTab']);

        foreach ($props['tabs'] as $tab) {
            $this->assertArrayHasKey('key', $tab);
            $this->assertArrayHasKey('label', $tab);
            $this->assertNotSame('', $tab['label'], 'كل تاب لازم يكون ليها اسم مقروء');
        }
    }

    /** * كل تاب لازم تكون موديول حقيقي في سجل الصلاحيات */
    public function test_every_tab_maps_to_a_real_permission_module(): void
    {
        $modules = array_column(PermissionRegistry::all(), 'module');

        foreach ($this->props()['tabs'] as $tab) {
            $this->assertContains($tab['key'], $modules,
                'التاب "'.$tab['key'].'" مش مربوطة بموديول صلاحيات');
        }
    }

    /** * تاب مش موجودة ما تكسرش الصفحة — بترجع لأول تاب */
    public function test_an_unknown_tab_falls_back_to_the_first_one(): void
    {
        $props = $this->props('?tab=definitely_not_a_tab');

        $this->assertSame($props['tabs'][0]['key'], $props['activeTab']);
    }

    /* ───────────── التقسيم الصفحي ───────────── */

    /**
     * * التقسيم لازم يكون في قاعدة البيانات : الصفحة التانية بتجيب صفوف
     * * مختلفة فعلا ، و العدد الكلي بيتحسب من غير ما نحمّل الجدول كله
     */
    public function test_paging_happens_in_the_database(): void
    {
        $tab = $this->tabWithRows();

        if ($tab === null) {
            $this->markTestSkipped('No tab with more than one page of activity.');
        }

        $page1 = $this->props('?tab='.$tab.'&page=1');
        $page2 = $this->props('?tab='.$tab.'&page=2');

        $this->assertSame(1, $page1['pagination']['currentPage']);
        $this->assertSame(2, $page2['pagination']['currentPage'], 'رقم الصفحة لازم يتقرا فعلا');

        $this->assertNotSame(
            $page1['entries'][0]['id'],
            $page2['entries'][0]['id'],
            'الصفحة التانية لازم تجيب صفوف مختلفة'
        );

        $this->assertGreaterThan(count($page1['entries']), $page1['pagination']['total'],
            'الإجمالي أكبر من صفحة واحدة، يعني فعلا مقسّمة');
    }

    /**
     * * الصفحة ما ترجعش الجدول كله : لو حد كبّر حجم الصفحة عشان "يسهّلها"
     * * الجدول ده بيكبر مع الوقت و الصفحة هتبقى أبطأ كل يوم
     *
     * * الاختبار ده مستقل عن الداتا : بيتأكد من الحجم نفسه ، مش من عدد
     * * الصفحات اللي صدف إنه موجود
     */
    public function test_a_page_never_returns_more_than_the_page_size(): void
    {
        $reflection = new \ReflectionClass(DailyLogController::class);
        $perPage = $reflection->getConstant('PER_PAGE');

        $this->assertIsInt($perPage);
        $this->assertGreaterThan(0, $perPage);
        $this->assertLessThanOrEqual(100, $perPage, 'حجم الصفحة لازم يفضل صغير');

        $busiest = $this->busiestTab();

        if ($busiest === null) {
            $this->markTestSkipped('No activity on file.');
        }

        $props = $this->props('?tab='.$busiest);

        $this->assertLessThanOrEqual($perPage, count($props['entries']),
            'الصفحة رجّعت صفوف أكتر من حجمها — يعني مش بتقسّم فعلا');
    }

    /**
     * * الصفحة ليها صلاحيتها الخاصة : اللي مش معاه الصلاحية ما يدخلش ،
     * * حتى لو بيشوف الموديلات نفسها من شاشاتها
     */
    public function test_a_user_without_the_permission_is_refused(): void
    {
        $blocked = User::doesntHave('roles')->get()
            ->first(fn (User $candidate) => ! \App\Support\Permissions\PermissionResolver::allows($candidate, 'daily_log.view'));

        if (! $blocked) {
            $this->markTestSkipped('Every user on file may open the page.');
        }

        $request = Request::create('/daily-logs', 'GET');
        $request->setUserResolver(fn () => $blocked);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        (new DailyLogController)($request, $this->company());
    }

    /**
     * * اسم أول تاب فيها صفوف تكفي لأكتر من صفحة — و إلا الاختبار
     * * ما يقدرش يثبت إن التقسيم شغال فعلا
     */
    private function tabWithRows(): ?string
    {
        foreach ($this->props()['tabs'] as $tab) {
            if ($this->props('?tab='.$tab['key'])['pagination']['lastPage'] > 1) {
                return $tab['key'];
            }
        }

        return null;
    }

    /* ───────────── الفلاتر ───────────── */

    public function test_the_event_filter_narrows_the_result(): void
    {
        $tab = $this->busiestTab();

        if ($tab === null) {
            $this->markTestSkipped('No activity on file.');
        }

        $all = $this->props('?tab='.$tab);
        $created = $this->props('?tab='.$tab.'&event=created');
        $deleted = $this->props('?tab='.$tab.'&event=deleted');

        $this->assertLessThanOrEqual($all['pagination']['total'], $created['pagination']['total']);
        $this->assertLessThanOrEqual($all['pagination']['total'], $deleted['pagination']['total']);

        foreach ($created['entries'] as $entry) {
            $this->assertSame('created', $entry['event'], 'الفلتر لازم يستبعد باقي الأحداث');
        }
    }

    public function test_a_date_range_in_the_future_returns_nothing(): void
    {
        $tab = $this->busiestTab();

        if ($tab === null) {
            $this->markTestSkipped('No activity on file.');
        }

        $props = $this->props('?tab='.$tab.'&from=2099-01-01&to=2099-12-31');

        $this->assertSame(0, $props['pagination']['total']);
        $this->assertSame([], $props['entries']);
    }

    /* ───────────── الصلاحيات و التوصيلات ───────────── */

    public function test_the_page_has_its_own_permission(): void
    {
        $all = PermissionRegistry::all();

        $this->assertArrayHasKey('daily_log.view', $all, 'الصفحة لازم يكون ليها صلاحية مستقلة');
        $this->assertSame('administration', $all['daily_log.view']['group']);
    }

    public function test_the_route_exists_and_is_permission_mapped(): void
    {
        $route = Route::getRoutes()->getByName('daily-logs.index');

        $this->assertNotNull($route, 'الراوت مش متسجل');
        $this->assertContains('GET', $route->methods());

        $map = file_get_contents(app_path('Support/Permissions/RoutePermissionMap.php'));
        $this->assertStringContainsString("'daily-logs.index' => 'daily_log.view'", $map);
    }

    /**
     * * الأيقونة بتتبعت من السيرفر بس لما اليوزر مسموحله ، فما تقدرش
     * * تخالف اللي الراوت نفسه بيسمح بيه
     */
    public function test_the_icon_is_only_sent_when_permitted(): void
    {
        $middleware = file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php'));

        $this->assertStringContainsString("'dailyLogsUrl'", $middleware);
        $this->assertStringContainsString("PermissionResolver::allows(\$request->user(), 'daily_log.view')", $middleware);

        $layout = file_get_contents(resource_path('js/Layouts/AppLayout.vue'));
        $this->assertStringContainsString('dailyLogsUrl', $layout);
        $this->assertStringContainsString('v-if="dailyLogsUrl"', $layout, 'الأيقونة لازم تختفي من غير الصلاحية');
    }

    /** * الأيقونة لازم تستخدم رسمة موجودة فعلا — غير كده بتطلع دايرة فاضية */
    public function test_the_icon_name_exists_in_the_icon_set(): void
    {
        $layout = file_get_contents(resource_path('js/Layouts/AppLayout.vue'));
        preg_match('/dailyLogsUrl.*?<NavIcon name="([a-z-]+)"/s', $layout, $m);

        $this->assertNotEmpty($m[1] ?? null, 'مفيش أيقونة متحطة');

        $icons = file_get_contents(resource_path('js/Components/NavIcon.vue'));
        $this->assertMatchesRegularExpression(
            "/['\"]?".preg_quote($m[1], '/')."['\"]?\s*:/",
            $icons,
            'اسم الأيقونة "'.$m[1].'" مش موجود في NavIcon — هتطلع دايرة فاضية'
        );
    }

    /* ───────────── الترجمة ───────────── */

    /**
     * * جملة السجل كانت بتطلع نص عربي نص إنجليزي — "أنشأ Money Payment" —
     * * لأن اسم الموديل كان بيترجع من السجل من غير __() ، و مفتاح التعديل
     * * (trans_choice) مكانش موجود في العربي أصلا فكان بيطلع "updated ..."
     */
    public function test_every_activity_label_has_an_arabic_entry(): void
    {
        $arabic = json_decode(file_get_contents(resource_path('lang/ar.json')), true);
        $missing = [];

        foreach (\App\Support\Activity\ActivityRegistry::models() as $class) {
            $label = \App\Support\Activity\ActivityRegistry::all()[$class]['label'] ?? null;

            if ($label && ! isset($arabic[$label])) {
                $missing[] = $label;
            }
        }

        $this->assertSame([], $missing,
            'الأسماء دي هتطلع إنجليزي جوه جملة عربية: '.implode(', ', $missing));
    }

    /** * الاسم لازم يترجم فعلا وقت العرض ، مش يفضل مفتاح إنجليزي */
    public function test_the_label_is_translated_at_render_time(): void
    {
        $previous = app()->getLocale();
        app()->setLocale('ar');

        try {
            $label = \App\Support\Activity\ActivityRegistry::labelFor(\App\Models\MoneyPayment::class);

            $this->assertNotSame('Money Payment', $label, 'الاسم لسه إنجليزي');
            $this->assertMatchesRegularExpression('/\p{Arabic}/u', $label);
        } finally {
            app()->setLocale($previous);
        }
    }

    /**
     * * كل صيغ جملة السجل لازم تكون مترجمة — بما فيها صيغة التعديل اللي
     * * بتعدّ الحقول (trans_choice) و اللي كانت ناقصة
     */
    public function test_every_sentence_form_is_translated(): void
    {
        $arabic = json_decode(file_get_contents(resource_path('lang/ar.json')), true);
        $source = file_get_contents(app_path('Models/RecordActivity.php'));

        preg_match_all("/(?:__|trans_choice)\(\s*'([^']+)'/", $source, $matches);

        $missing = array_values(array_filter(
            array_unique($matches[1]),
            fn (string $key) => ! isset($arabic[$key])
        ));

        $this->assertSame([], $missing,
            'صيغ الجملة دي هتطلع إنجليزي: '.implode(' // ', $missing));
    }

    /**
     * * الصيغة العربية للتعديل لازم تشتغل صح مع كل الأعداد — العربي عنده
     * * صيغ جمع أكتر من الإنجليزي ، فصيغتين بس كانت بتطلع "1 حقول"
     */
    public function test_the_arabic_update_sentence_reads_correctly_for_any_count(): void
    {
        $previous = app()->getLocale();
        app()->setLocale('ar');

        try {
            $key = 'updated :subject|updated :subject (:count fields)';

            foreach ([0, 1, 2, 3, 11] as $count) {
                $rendered = trans_choice($key, $count, ['subject' => 'س', 'count' => $count]);

                $this->assertStringNotContainsString('updated', $rendered, 'العدد '.$count.' لسه إنجليزي');
                $this->assertMatchesRegularExpression('/\p{Arabic}/u', $rendered);
            }

            // عدد صغير ما يتقالش عنه "حقول"
            $this->assertStringNotContainsString('1 حقول',
                trans_choice($key, 1, ['subject' => 'س', 'count' => 1]));
        } finally {
            app()->setLocale($previous);
        }
    }

    /**
     * * أسماء الحقول اللي مالهاش اسم معرّف في السجل بتتشتق من اسم العمود
     * * (purchase_order_id ← "Purchase Order") — و المسار ده كان بيرجّع
     * * إنجليزي زي ما هو ، حتى لما الترجمة تكون موجودة أصلا
     */
    public function test_a_derived_field_label_is_translated(): void
    {
        $previous = app()->getLocale();
        app()->setLocale('ar');

        try {
            $class = \App\Models\LetterOfGuaranteeIssuance::class;

            foreach ([
                'purchase_order_id',
                'transaction_date',
                'source',
                'supplier_contract_id',
                'overdraft_assignment_contract_id',
            ] as $field) {
                $label = \App\Support\Activity\ActivityRegistry::fieldLabel($class, $field);

                $this->assertDoesNotMatchRegularExpression('/[A-Za-z]/', $label,
                    'اسم الحقل "'.$field.'" لسه بيطلع إنجليزي: '.$label);
            }
        } finally {
            app()->setLocale($previous);
        }
    }

    /**
     * * القيم المتخزنة الخام (زي "lg-facility") كانت بتتعرض زي ما هي
     */
    public function test_a_raw_stored_value_is_made_readable(): void
    {
        $previous = app()->getLocale();
        app()->setLocale('ar');

        try {
            $class = \App\Models\LetterOfGuaranteeIssuance::class;

            foreach (['lg-facility', 'running', 'finished'] as $value) {
                $rendered = \App\Support\Activity\ActivityRegistry::valueLabel($class, 'source', $value);

                $this->assertNotSame($value, $rendered, 'القيمة "'.$value.'" لسه بتتعرض خام');
                $this->assertStringNotContainsString('-', $rendered, 'القيمة لسه بصيغة الكود');
            }
        } finally {
            app()->setLocale($previous);
        }
    }

    /**
     * * كل تاب في الصفحة لازم تطلع بالعربي — مفيش استثناء
     *
     * * التاب اللي فيها أكتر من موديل كانت بتاخد اسمها من مفتاح الموديول
     * * نفسه (lc_issuance ← "Lc Issuance") ، و ده لا اسم صح و لا مفتاح
     * * ترجمة — دلوقتي الاسم بييجي من سجل الصلاحيات
     */
    public function test_no_tab_label_falls_back_to_english(): void
    {
        $previous = app()->getLocale();
        app()->setLocale('ar');

        try {
            $english = array_values(array_filter(
                $this->props()['tabs'],
                fn (array $tab) => (bool) preg_match('/[A-Za-z]/', $tab['label'])
            ));

            $this->assertSame([], $english,
                'التابات دي لسه إنجليزي: '.implode(', ', array_column($english, 'label')));
        } finally {
            app()->setLocale($previous);
        }
    }

    /** * و كل نص مكتوب في الصفحة نفسها له ترجمة */
    public function test_every_page_string_has_an_arabic_entry(): void
    {
        $arabic = json_decode(file_get_contents(resource_path('lang/ar.json')), true);
        $vue = file_get_contents(resource_path('js/Pages/DailyLogs/Index.vue'));

        preg_match_all("/\\\$t\('([^']+)'\)/", $vue, $matches);

        $missing = array_values(array_filter(
            array_unique($matches[1]),
            fn (string $key) => ! isset($arabic[$key])
        ));

        $this->assertSame([], $missing, 'نصوص الصفحة دي من غير ترجمة: '.implode(', ', $missing));
    }

    /* ───────────── مساعدات ───────────── */

    private function busiestTab(): ?string
    {
        $props = $this->props();

        foreach ($props['tabs'] as $tab) {
            if ($this->props('?tab='.$tab['key'])['pagination']['total'] > 0) {
                return $tab['key'];
            }
        }

        return null;
    }
}
