<?php

namespace Tests\Feature\DailyLogs;

use App\Http\Controllers\DailyLogController;
use App\Models\Company;
use Carbon\Carbon;
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

    /* ───────────── الأقسام ───────────── */

    /**
     * * فترة واسعة عشان نضمن إن فيه حركة — الصفحة بتفتح على النهاردة بس ،
     * * و الداتا اللي على الجهاز ممكن ما يكونش فيها حاجة النهاردة
     */
    private const WIDE = '?from=2000-01-01&to=2099-12-31';

    public function test_it_shows_a_section_per_logged_record_type(): void
    {
        $props = $this->props(self::WIDE);

        $this->assertNotEmpty($props['sections'], 'لازم يكون فيه أقسام على فترة واسعة');

        foreach ($props['sections'] as $section) {
            $this->assertArrayHasKey('key', $section);
            $this->assertArrayHasKey('label', $section);
            $this->assertArrayHasKey('entries', $section);
            $this->assertNotSame('', $section['label'], 'كل قسم لازم يكون ليه اسم مقروء');
        }
    }

    /** * كل قسم لازم يكون موديول حقيقي في سجل الصلاحيات */
    public function test_every_section_maps_to_a_real_permission_module(): void
    {
        $modules = array_column(PermissionRegistry::all(), 'module');

        foreach ($this->props(self::WIDE)['sections'] as $section) {
            $this->assertContains($section['key'], $modules,
                'القسم "'.$section['key'].'" مش مربوط بموديول صلاحيات');
        }
    }

    /**
     * * السؤال اللي الصفحة بتجاوب عليه هو "إيه اللي حصل النهاردة" ،
     * * فلازم تفتح على النهاردة من غير ما حد يملا حاجة
     */
    public function test_it_opens_on_today_only(): void
    {
        $props = $this->props();
        $today = Carbon::today()->format('Y-m-d');

        $this->assertSame($today, $props['filters']['from']);
        $this->assertSame($today, $props['filters']['to']);
    }

    /** * و الفترة اللي في الرابط بتغلب الافتراضي */
    public function test_an_explicit_period_overrides_today(): void
    {
        $props = $this->props('?from=2024-01-01&to=2024-01-31');

        $this->assertSame('2024-01-01', $props['filters']['from']);
        $this->assertSame('2024-01-31', $props['filters']['to']);
    }

    /** * فترة مقلوبة بتتظبط بدل ما ترجّع صفر بالغلط */
    public function test_a_backwards_period_is_swapped(): void
    {
        $props = $this->props('?from=2024-01-31&to=2024-01-01');

        $this->assertSame('2024-01-01', $props['filters']['from']);
        $this->assertSame('2024-01-31', $props['filters']['to']);
    }

    /**
     * * القسم اللي مالوش حركة في الفترة مش بيتعرض أصلا — و ده هو نفسه
     * * اللي بيقرر التابات اللي فوق ، فمفيش تاب بتودّيك على فاضي
     */
    public function test_only_types_with_activity_in_the_period_are_shown(): void
    {
        foreach ($this->props(self::WIDE)['sections'] as $section) {
            $this->assertGreaterThan(0, $section['total'],
                'القسم "'.$section['key'].'" اتعرض من غير ولا حركة');
            $this->assertNotEmpty($section['entries']);
        }
    }

    /** * فترة في المستقبل مالهاش حركة ، فمفيش أقسام خالص */
    public function test_a_period_with_no_activity_shows_no_sections(): void
    {
        $props = $this->props('?from=2099-01-01&to=2099-12-31');

        $this->assertSame([], $props['sections']);
    }

    /* ───────────── سقف الصفوف ───────────── */

    /**
     * * الصفحة ممكن تعرض عشر أقسام مع بعض ، فكل قسم ليه سقف بيتطبّق في
     * * قاعدة البيانات . الاختبار ده مستقل عن الداتا : بيتأكد من السقف
     * * نفسه ، مش من عدد الصفوف اللي صدف إنه موجود
     */
    public function test_each_section_is_capped(): void
    {
        $cap = (new \ReflectionClass(DailyLogController::class))->getConstant('ROWS_PER_SECTION');

        $this->assertIsInt($cap);
        $this->assertGreaterThan(0, $cap);
        $this->assertLessThanOrEqual(100, $cap, 'السقف لازم يفضل صغير');

        foreach ($this->props(self::WIDE)['sections'] as $section) {
            $this->assertLessThanOrEqual($cap, count($section['entries']),
                'القسم "'.$section['key'].'" رجّع صفوف أكتر من السقف');
            $this->assertSame(min($section['total'], $cap), $section['shown']);
            $this->assertSame($section['total'] > $cap, $section['hasMore'],
                'لازم نقول للمستخدم إن فيه أكتر من اللي بيشوفه');
        }
    }

    /**
     * * الإجمالي بيتحسب من قاعدة البيانات مش من الصفوف المحمّلة — و إلا
     * * ما كنّاش هنعرف إن فيه أكتر من السقف أصلا
     */
    public function test_the_total_is_counted_in_the_database(): void
    {
        $cap = (new \ReflectionClass(DailyLogController::class))->getConstant('ROWS_PER_SECTION');

        DB::beginTransaction();

        try {
            /**
             * * بنبني حركات أكتر من السقف عشان الاختبار يشتغل فعلا بدل ما
             * * يتخطّى — الداتا اللي على الجهاز ممكن ما توصلش للسقف
             */
            $class = ActivityRegistry::models()[0];
            $rows = [];

            for ($i = 0; $i < $cap + 10; $i++) {
                $rows[] = [
                    'subject_type' => $class,
                    'subject_id' => 900000 + $i,
                    'company_id' => $this->company()->id,
                    'user_id' => null,
                    'user_name' => 'Cap Test',
                    'event' => 'created',
                    'description' => 'cap test',
                    'created_at' => '2031-05-05 10:00:00',
                ];
            }

            DB::table('record_activities')->insert($rows);

            $props = $this->props('?from=2031-05-05&to=2031-05-05');

            $this->assertCount(1, $props['sections'], 'المفروض قسم واحد بس في اليوم ده');

            $section = $props['sections'][0];

            $this->assertSame($cap + 10, $section['total'], 'الإجمالي بيتعدّ في القاعدة، مش من الصفوف المحمّلة');
            $this->assertCount($cap, $section['entries'], 'المحمّل لازم يقف عند السقف');
            $this->assertTrue($section['hasMore']);
            $this->assertSame($cap, $section['shown']);
        } finally {
            DB::rollBack();
        }
    }

    /* ───────────── فلتر الشخص ───────────── */

    /** * القايمة بتتبني من الحركات نفسها ، فمفيش أسماء ما عملتش حاجة */
    public function test_the_user_filter_is_offered(): void
    {
        $props = $this->props(self::WIDE);

        if ($props['users'] === []) {
            $this->markTestSkipped('No activity with a user on file.');
        }

        foreach ($props['users'] as $user) {
            $this->assertArrayHasKey('id', $user);
            $this->assertArrayHasKey('name', $user);
        }
    }

    /** * "وريني الشخص ده عمل إيه" — كل صف راجع لازم يكون بتاعه */
    public function test_filtering_by_a_person_returns_only_their_work(): void
    {
        $props = $this->props(self::WIDE);

        if ($props['users'] === []) {
            $this->markTestSkipped('No activity with a user on file.');
        }

        $all = array_sum(array_column($props['sections'], 'total'));

        foreach ($props['users'] as $candidate) {
            $filtered = $this->props(self::WIDE.'&user='.$candidate['id']);
            $theirs = array_sum(array_column($filtered['sections'], 'total'));

            if ($theirs === 0) {
                continue;
            }

            $this->assertLessThanOrEqual($all, $theirs, 'الفلتر لازم يضيّق مش يوسّع');

            foreach ($filtered['sections'] as $section) {
                foreach ($section['entries'] as $entry) {
                    $this->assertSame($candidate['name'], $entry['actor'],
                        'الفلتر رجّع شغل شخص تاني');
                }
            }

            return;
        }

        $this->markTestSkipped('No user on file has activity in range.');
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

    /* ───────────── الفلاتر ───────────── */

    public function test_the_event_filter_narrows_the_result(): void
    {
        $total = fn (array $props) => array_sum(array_column($props['sections'], 'total'));

        $all = $this->props(self::WIDE);
        $created = $this->props(self::WIDE.'&event=created');

        if ($total($all) === 0) {
            $this->markTestSkipped('No activity on file.');
        }

        $this->assertLessThanOrEqual($total($all), $total($created));

        foreach ($created['sections'] as $section) {
            foreach ($section['entries'] as $entry) {
                $this->assertSame('created', $entry['event'], 'الفلتر لازم يستبعد باقي الأحداث');
            }
        }
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
                $this->props(self::WIDE)['sections'],
                fn (array $section) => (bool) preg_match('/[A-Za-z]/', $section['label'])
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

}
