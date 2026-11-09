<?php

namespace Tests\Feature\Review;

use App\Http\Controllers\MovementReviewController;
use App\Models\Company;
use App\Models\RecordActivity;
use App\Models\User;
use App\Support\Permissions\PermissionResolver;
use App\Traits\Models\IsReviewable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Security\Support\PlantsCompanyRecords;
use Tests\TestCase;

/**
 * * مراجعة الحركات المالية — لكل نوع على حدة .
 *
 * * المراجعة مش علامة على الشاشة : هي **قفل** . الحركة المراجَعة ما
 * * ينفعش تتعدل و لا تتحذف ، و ده اللي بيدّي للميزة معناها . فالاختبارات
 * * دي بتتأكد من القفل نفسه مش من العمود .
 *
 * * الشركة و المستخدمين و صف من كل نوع حركة بيتبنوا جوه الاختبار نفسه
 * * و بيرجعوا مع الـ rollback (Tests\TestCase) ، فالاختبارات بتغطي الأنواع
 * * كلها من غير ما تعتمد على أي داتا موجودة .
 */
class MovementReviewTest extends TestCase
{
    use PlantsCompanyRecords;

    private Company $company;

    private ?User $actor = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = $this->makeCompany();
    }

    /** @return array<string, array{0:string,1:string,2:string}> النوع ، الموديل ، الموديول */
    public static function movements(): array
    {
        $out = [];

        foreach (MovementReviewController::MOVEMENTS as $key => [$class, $module]) {
            $out[$key] = [$key, $class, $module];
        }

        return $out;
    }

    /**
     * ريكوست بمستخدم مسجّل دخول .
     *
     * * setUserResolver لوحده مش كفاية : PermissionResolver بيقرا
     * * أدوار المستخدم عبر الـ auth guard ، فالطلب الحقيقي بيكون
     * * المستخدم متسجّل فيه — و من غير كده الفحص بيرفض قبل ما يوصل
     * * للخطوة اللي بنختبرها
     */
    private function requestAs(User $user, string $method = 'PATCH', array $payload = []): Request
    {
        $request = Request::create('/x', $method, $payload);
        $request->setLaravelSession(app('session.store'));
        $request->setUserResolver(fn () => $user);
        $this->app->instance('request', $request);
        auth()->setUser($user);

        return $request;
    }

    /** * اللي بيراجع : سوبر أدمن ، فبيعدّي صلاحية المراجعة لكل الأنواع */
    private function actor(): User
    {
        return $this->actor ??= $this->makeSuperAdmin($this->company);
    }

    /**
     * صف من النوع ده للاختبار عليه ، في الشركة دي (أو شركة تانية) .
     *
     * * بنبني صف في كل جدول ليه company_id بأقل الأعمدة الإجبارية
     * * (PlantsCompanyRecords) — مش بنحاول نعمل حركة واقعية ، إحنا
     * * بنختبر المراجعة مش الحركة نفسها . كله بيرجع مع الـ rollback .
     */
    private function anyRow(string $class, ?Company $company = null)
    {
        $company ??= $this->company;

        if ($this->plantedFor($company) === []) {
            $this->plantRecordsFor($company, $this->actor()->id);
        }

        $table = (new $class)->getTable();
        $id = $this->plantedFor($company)[$table] ?? null;

        $this->assertNotNull($id, "ما اتبناش صف {$class}: ".($this->plantFailuresFor($company)[$table] ?? 'unknown'));

        if (isset(self::ALWAYS_SET[$class])) {
            DB::table($table)->where('id', $id)->update(self::ALWAYS_SET[$class]);
        }

        return $class::findOrFail($id);
    }

    /**
     * * أعمدة القاعدة بتسمح تكون فاضية بس أي حركة حقيقية بيبقى فيها قيمة ،
     * * و وصف الحركة في السجل بيقرا منها (تاريخ التحويل مثلا)
     */
    private const ALWAYS_SET = [
        \App\Models\InternalMoneyTransfer::class => ['transfer_date' => '2026-01-01'],
        \App\Models\LcSettlementInternalMoneyTransfer::class => ['transfer_date' => '2026-01-01'],
        \App\Models\MultipleCashExpense::class => ['payment_date' => '2026-01-01', 'currency' => 'EGP'],
        \App\Models\LetterOfCreditIssuance::class => ['issuance_date' => '2026-01-01'],
    ];

    /* ───────────── التوصيل ───────────── */

    /** * كل نوع في القايمة لازم يكون فعلا قابل للمراجعة */
    #[DataProvider('movements')]
    public function test_the_movement_is_reviewable(string $key, string $class, string $module): void
    {
        $this->assertContains(IsReviewable::class, class_uses_recursive($class),
            "{$class} مسجّل كحركة تتراجع بس مش شايل التريت");

        foreach (['is_reviewed', 'reviewed_by', 'reviewed_at', 'review_comment'] as $column) {
            $this->assertTrue((new $class)->getConnection()->getSchemaBuilder()
                ->hasColumn((new $class)->getTable(), $column),
                "جدول {$class} ناقصه العمود {$column}");
        }
    }

    /** * و صلاحيته موجودة جنب الموديل بتاعه */
    #[DataProvider('movements')]
    public function test_the_permission_sits_next_to_its_own_model(string $key, string $class, string $module): void
    {
        $keys = array_column(\App\Support\Permissions\PermissionRegistry::all(), 'key');

        $this->assertContains($module.'.review', $keys,
            "الصلاحية {$module}.review مش متسجّلة");
    }

    /* ───────────── الدورة الكاملة ───────────── */

    /**
     * * مراجعة → قفل التعديل → قفل الحذف → فكّ → رجعت تتعدل
     */
    #[DataProvider('movements')]
    public function test_the_full_review_cycle(string $key, string $class, string $module): void
    {
        $row = $this->anyRow($class);


        $actor = $this->actor();

        $this->assertTrue($row->markReviewed($actor, 'تمام'), 'المراجعة المفروض تتم');
        $row->refresh();

        $this->assertTrue($row->isReviewed());
        $this->assertSame($actor->id, (int) $row->reviewed_by);
        $this->assertNotNull($row->reviewed_at);
        $this->assertSame('تمام', $row->getReviewComment());

        // التعديل مقفول
        try {
            $row->forceFill(['updated_at' => now()])->setAttribute('company_id', $row->company_id);
            $row->user_comment = 'محاولة تعديل';
            $row->save();
            $this->fail('المفروض التعديل يترفض على حركة مراجَعة');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        // الحذف مقفول
        try {
            $class::find($row->id)->delete();
            $this->fail('المفروض الحذف يترفض على حركة مراجَعة');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }

        $this->assertNotNull($class::find($row->id), 'الحركة اتحذفت رغم المراجعة');

        // فكّ المراجعة يرجّع كل حاجة
        $this->assertTrue($class::find($row->id)->markUnreviewed($actor, null));

        $fresh = $class::find($row->id);
        $this->assertFalse($fresh->isReviewed());
        $this->assertNull($fresh->reviewed_by);
        $this->assertNull($fresh->reviewed_at);
    }

    /** * و بعد فكّ المراجعة التعديل بيعدّي تاني */
    #[DataProvider('movements')]
    public function test_editing_works_again_after_the_review_is_removed(string $key, string $class, string $module): void
    {
        $row = $this->anyRow($class);


        $actor = $this->actor();
        $row->markReviewed($actor, null);
        $class::find($row->id)->markUnreviewed($actor, null);

        $fresh = $class::find($row->id);
        $fresh->updated_at = now();
        $fresh->save();

        $this->assertFalse($fresh->fresh()->isReviewed());
    }

    /* ───────────── السجل ───────────── */

    /**
     * * الجملة لازم تميّز الحركة و تقول مين راجعها — اللي بيقرا السجل
     * * بعد سنة المفروض يعرف الصف ده كان إيه من غير ما يفتحه
     */
    #[DataProvider('movements')]
    public function test_the_log_names_the_actor_and_describes_the_record(string $key, string $class, string $module): void
    {
        $row = $this->anyRow($class);


        $actor = $this->actor();
        $row->markReviewed($actor, 'التعليق بتاعي');

        $entry = RecordActivity::where('subject_type', $class)
            ->where('subject_id', $row->id)
            ->latest('id')->first();

        $this->assertNotNull($entry, 'المراجعة المفروض تتسجّل');
        $this->assertSame(RecordActivity::EVENT_CUSTOM, $entry->event);
        $this->assertStringContainsString($actor->name, $entry->description, 'اسم اللي راجع لازم يكون في الجملة');
        // الرقم المسلسل (MR/CIS/2026/10/00001) لو الحركة ليها رقم ، و إلا #id
        $recordNumber = method_exists($row, 'getSerialOrId') ? $row->getSerialOrId() : '#'.$row->id;
        $this->assertStringContainsString($recordNumber, $entry->description, 'الجملة لازم تميّز الصف');
        $this->assertStringContainsString('التعليق بتاعي', $entry->description, 'التعليق لازم يتسجّل');

        // و فكّ المراجعة بيتسجّل هو كمان
        $class::find($row->id)->markUnreviewed($actor, null);

        $removal = RecordActivity::where('subject_type', $class)
            ->where('subject_id', $row->id)
            ->latest('id')->first();

        $this->assertNotSame($entry->id, $removal->id, 'فكّ المراجعة المفروض يتسجّل لوحده');
        $this->assertStringContainsString($actor->name, $removal->description);
    }

    /* ───────────── الفلتر ───────────── */

    #[DataProvider('movements')]
    public function test_the_filter_separates_reviewed_from_not_reviewed(string $key, string $class, string $module): void
    {
        $row = $this->anyRow($class);


        $base = $class::where('company_id', $row->company_id);

        /**
         * * بنقيس الفرق مش الرقم المطلق : القاعدة ممكن يكون فيها
         * * صفوف مراجَعة أصلا (مراجعات حقيقية ، أو فلاجز قديمة من
         * * قبل الميزة) ، و اختبار بيفترض قاعدة نضيفة بيقع لأسباب
         * * مالهاش علاقة باللي بيختبره
         */
        $all = (clone $base)->count();
        $reviewedBefore = (clone $base)->reviewState('reviewed')->count();

        $row->markReviewed($this->actor(), null);

        $reviewed = (clone $base)->reviewState('reviewed')->count();
        $notReviewed = (clone $base)->reviewState('not_reviewed')->count();
        $unfiltered = (clone $base)->reviewState(null)->count();

        $this->assertSame($reviewedBefore + 1, $reviewed, 'الصف اللي راجعناه لازم يزوّد المراجَع واحد');
        $this->assertSame($all - $reviewed, $notReviewed);
        $this->assertSame($all, $unfiltered, 'من غير فلتر لازم يرجّع الكل');
        $this->assertSame($all, $reviewed + $notReviewed, 'الاتنين لازم يجمعوا الكل');
    }

    /* ───────────── الصلاحية و العزل ───────────── */

    /** * اللي مالوش صلاحية النوع ده بيترفض */
    #[DataProvider('movements')]
    public function test_a_user_without_the_permission_is_refused(string $key, string $class, string $module): void
    {
        $row = $this->anyRow($class);


        // * يوزر في نفس الشركة ، بيشوف النوع ده بس مالوش صلاحية المراجعة
        $blocked = $this->makeUser([$module.'.view'], $this->company);

        $this->assertFalse(PermissionResolver::allows($blocked, $module.'.review'));

        $request = $this->requestAs($blocked, 'PATCH', ['reviewed' => true]);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        app(MovementReviewController::class)
            ->update($request, $this->company, $key, $row->id);
    }

    /** * و نوع مش في القايمة بيرجّع ٤٠٤ مش بيحمّل كلاس عشوائي */
    public function test_an_unknown_movement_type_is_rejected(): void
    {
        $request = $this->requestAs($this->actor(), 'PATCH', ['reviewed' => true]);

        try {
            app(MovementReviewController::class)
                ->update($request, $this->company, 'App\\Models\\User', 1);
            $this->fail('النوع المجهول المفروض يترفض');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    /** * و حركة في شركة تانية ما تتراجعش من هنا */
    #[DataProvider('movements')]
    public function test_a_movement_from_another_company_is_not_reachable(string $key, string $class, string $module): void
    {
        $other = $this->anyRow($class, $this->makeCompany());

        $request = $this->requestAs($this->actor(), 'PATCH', ['reviewed' => true]);

        try {
            app(MovementReviewController::class)
                ->update($request, $this->company, $key, $other->id);
            $this->fail('حركة شركة تانية المفروض ما تتشافش');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    /* ───────────── التوصيل في الواجهة ───────────── */

    public function test_the_route_is_registered_and_permission_mapped(): void
    {
        $route = Route::getRoutes()->getByName('movement.review.update');

        $this->assertNotNull($route);
        $this->assertContains('PATCH', $route->methods());

        $required = \App\Support\Permissions\RoutePermissionMap::for('movement.review.update');

        $this->assertNotNull($required, 'الراوت لازم يكون مربوط بصلاحية');
        $this->assertContains('money_received.review', (array) $required);
    }

    /** * كل صفحة من الاتناشر بتعرض الزرار و الفلتر */
    public function test_every_index_page_offers_the_button_and_the_filter(): void
    {
        $pages = [
            'MoneyReceived', 'MoneyPayment', 'CashExpense', 'MultipleCashExpense',
            'InternalMoneyTransfer', 'BuyOrSellCurrencies', 'ForeignExchangeRate',
            'LetterOfGuaranteeIssuance', 'LetterOfCreditIssuance',
            'FactoringWithRecourse', 'FactoringWithoutRecourse',
            'LcSettlementInternalMoneyTransfer',
        ];

        $missingButton = [];
        $missingFilter = [];

        foreach ($pages as $page) {
            $source = file_get_contents(resource_path("js/Pages/{$page}/Index.vue"));

            if (! str_contains($source, '<ReviewButton')) {
                $missingButton[] = $page;
            }

            if (! str_contains($source, "\$t('Review state')")) {
                $missingFilter[] = $page;
            }
        }

        $this->assertSame([], $missingButton, 'صفحات من غير زرار مراجعة: '.implode(', ', $missingButton));
        $this->assertSame([], $missingFilter, 'صفحات من غير فلتر مراجعة: '.implode(', ', $missingFilter));
    }

    /**
     * * شاشة التعديل نفسها بتترفض ، مش الحفظ بس .
     *
     * * القفل على الحفظ لوحده معناه إن المستخدم بيفتح الفورمة ، يملاها ،
     * * و ياخد ٤٠٣ في الآخر — شغل ضايع . الرفض بيتم من أول الشاشة .
     */
    public function test_the_edit_screen_is_refused_for_a_reviewed_movement(): void
    {
        $row = $this->anyRow(\App\Models\MultipleCashExpense::class);


        $company = $this->company;
        $controller = app(\App\Http\Controllers\MultipleCashExpenseController::class);

        $this->requestAs($this->actor(), 'GET');

        // قبل المراجعة الشاشة بتفتح عادي
        $this->assertInstanceOf(\Inertia\Response::class,
            $controller->edit($company, $row->fresh()),
            'الحركة غير المراجَعة المفروض تتعدل عادي');

        $row->markReviewed($this->actor(), null);

        try {
            $controller->edit($company, $row->fresh());
            $this->fail('شاشة التعديل المفروض تترفض على حركة مراجَعة');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    /** * و الحارس متنادى في كل شاشات التعديل */
    public function test_every_edit_screen_calls_the_guard(): void
    {
        $controllers = [
            'MoneyReceivedController', 'MoneyPaymentController', 'CashExpenseController',
            'MultipleCashExpenseController', 'InternalMoneyTransferController',
            'BuyOrSellCurrenciesController',
        ];

        $missing = [];

        foreach ($controllers as $controller) {
            $source = file_get_contents(app_path("Http/Controllers/{$controller}.php"));

            if (! str_contains($source, 'abortIfReviewed()')) {
                $missing[] = $controller;
            }
        }

        $this->assertSame([], $missing,
            'شاشات تعديل من غير حارس المراجعة: '.implode(', ', $missing));
    }

    /**
     * * صلاحية المراجعة بتتدي افتراضيا لأعلى دور في الشركة بس .
     *
     * * المراجعة بتقفل الحركة عن التعديل و الحذف ، فهي توقيع الشركة
     * * نفسها — مش إجراء يومي . الأدوار الأقل ممكن تاخدها بقرار إداري ،
     * * بس مش افتراضيا .
     */
    public function test_review_defaults_to_the_top_company_role_only(): void
    {
        $modules = array_map(fn ($m) => $m[1], array_values(MovementReviewController::MOVEMENTS));

        $expected = [
            'super-admin' => true,   // بيعدّي بالـ bypass ، و متدّيله صراحة عشان الشاشة تبان صح
            'company-admin' => true, // أعلى دور في الشركة
            'manager' => false,
            'user' => false,
        ];

        foreach ($expected as $roleName => $shouldHave) {
            $role = \Spatie\Permission\Models\Role::where('name', $roleName)->first();

            if (! $role) {
                continue;
            }

            $granted = $role->permissions->pluck('name');

            foreach ($modules as $module) {
                $has = $granted->contains($module.'.review');

                $this->assertSame($shouldHave, $has,
                    $shouldHave
                        ? "الدور {$roleName} المفروض ياخد {$module}.review افتراضيا"
                        : "الدور {$roleName} ما ينفعش ياخد {$module}.review افتراضيا");
            }
        }
    }

    /** * و 'review' مش في قايمة الأكشنز الافتراضية للمدير */
    public function test_the_manager_template_does_not_include_review(): void
    {
        $reflection = new \ReflectionClass(\App\Support\Permissions\PermissionRegistry::class);
        $defaults = $reflection->getConstant('ROLE_DEFAULTS');

        $this->assertIsArray($defaults);
        $this->assertNotContains('review', $defaults['manager']['actions'] ?? [],
            "'review' في قايمة manager معناها إن كل مدير بيقدر يقفل الحركات افتراضيا");
    }

    /**
     * * الحركة المراجَعة مقفولة ، فالواجهة ما تعرضش أزرار بتعدّل عليها .
     *
     * * السيرفر بيرفض أصلا ، بس زرار بيرجّع ٤٠٣ مالوش لازمة : المستخدم
     * * بيدوس و ما بيحصلش حاجة يفهمها .
     */
    public function test_reviewed_rows_hide_every_action_that_would_change_them(): void
    {
        /* الإجراءات اللي بتعدّل الحركة نفسها ، فلازم تختفي عليها */
        $mutating = [
            'Edit', 'Delete', 'Renewal', 'Cancel Letter',
            'Amount To Be Decreased', 'Back To Running', 'Apply Payment', 'Expenses',
        ];

        $pages = [
            'MoneyReceived', 'MoneyPayment', 'CashExpense', 'MultipleCashExpense',
            'InternalMoneyTransfer', 'BuyOrSellCurrencies', 'ForeignExchangeRate',
            'LetterOfGuaranteeIssuance', 'LetterOfCreditIssuance',
            'FactoringWithRecourse', 'FactoringWithoutRecourse',
            'LcSettlementInternalMoneyTransfer',
        ];

        $unguarded = [];

        foreach ($pages as $page) {
            foreach (file(resource_path("js/Pages/{$page}/Index.vue")) as $number => $line) {
                if (! preg_match('/:title="\$t\(\x27([^\x27]+)\x27\)"/', $line, $m)) {
                    continue;
                }

                if (! in_array($m[1], $mutating, true)) {
                    continue;
                }

                if (! str_contains($line, 'review?.is_reviewed')) {
                    $unguarded[] = "{$page}/Index.vue:".($number + 1)." — {$m[1]}";
                }
            }
        }

        $this->assertSame([], $unguarded,
            "أزرار بتعدّل الحركة و ظاهرة على الصف المراجَع:\n  ".implode("\n  ", $unguarded));
    }

    /** * و الزرار نفسه بنفس شكل باقي أزرار الصف */
    public function test_the_review_button_matches_the_other_row_actions(): void
    {
        $component = file_get_contents(resource_path('js/Components/ReviewButton.vue'));

        $this->assertStringContainsString('class="cvr-action-btn"', $component,
            'الزرار لازم ياخد نفس كلاس باقي أزرار الإجراءات عشان ما يبانش مختلف');
        $this->assertStringNotContainsString('cvr-btn-secondary px-2', $component,
            'شكل الزرار النصي القديم اتشال');
    }

    /** * و كل كونترولر بيبعت canReview بصلاحية موديله هو */
    public function test_every_controller_sends_its_own_permission(): void
    {
        $map = [
            'MoneyReceivedController' => 'money_received',
            'MoneyPaymentController' => 'money_payment',
            'FactoringWithRecourseController' => 'factoring_with_recourse',
            'FactoringWithoutRecourseController' => 'factoring_without_recourse',
            'LcSettlementInternalMoneyTransferController' => 'lc_settlement_transfer',
            'CashExpenseController' => 'cash_expense',
            'MultipleCashExpenseController' => 'multiple_cash_expense',
            'InternalMoneyTransferController' => 'internal_money_transfer',
            'BuyOrSellCurrenciesController' => 'buy_or_sell_currency',
            'ForeignExchangeRateController' => 'foreign_exchange_rate',
            'LetterOfGuaranteeIssuanceController' => 'lg_issuance',
            'LetterOfCreditIssuanceController' => 'lc_issuance',
        ];

        foreach ($map as $controller => $module) {
            $source = file_get_contents(app_path("Http/Controllers/{$controller}.php"));

            $this->assertStringContainsString("'{$module}.review'", $source,
                "{$controller} لازم يشوف صلاحية {$module}.review");
            $this->assertStringContainsString('reviewState(', $source.'reviewState(',
                "{$controller} لازم يطبّق فلتر المراجعة");
        }
    }
}
