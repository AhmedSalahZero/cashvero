<?php

namespace Tests\Feature\Odoo;

use App\Http\Controllers\OdooSettingController;
use App\Models\Company;
use App\OdooSetting;
use App\Models\User;
use App\Services\Api\OdooService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * * حفظ إعدادات أودو مايمسحش ربط حسابات إيراد الفوائد لما أودو ما يردّش.
 *
 * * الكود القديم كان بيعمل كده بالترتيب ده :
 * *
 * *     $company->interestRevenuesAccounts()->delete();      // غير مشروط
 * *     if (count($revenueResults)) { ...insert... }          // مشروط
 * *
 * * و $revenueResults كانت بتتملا بس من الأكواد اللي أودو ردّ عليها. يعني
 * * أودو واقع = المسح بيتنفّذ والإدخال لأ = الشركة تفقد الربط كله ، والمستخدم
 * * يشوف صفحة نجاح عادية ومايعرفش إن حاجة راحت.
 *
 * * دي نفس فئة الباج اللي العميل شكا منها في syncDeletedInvoices — رد خارجي
 * * فاضي اتعامل معاه على إنه حقيقة.
 */
class OdooSettingStoreSafetyTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $actor;

    /**
     * * مش FakeOdooService كنوع : AuthTrait.php بينادي public_path() وقت
     * * تحميل الملف نفسه ، فأي كلاس بيورث من OdooService بيتحمّل وقت ما
     * * PHPUnit يبني السويت — قبل ما التطبيق يقوم — و بيولّع fatal. عشان
     * * كده البديل anonymous class بيتبني جوه setUp بعد الإقلاع.
     */
    private static ?object $fake = null;

    /** الكونترولر بتاع التست بيوصل للبديل من هنا */
    public static function fake(): object
    {
        return self::$fake;
    }

    /**
     * * الراوتس كلها تحت prefix اللغة ، و getCurrentCompanyId() بيقرا
     * * segment(2) — من غير ROUTING_LOCALE رقم الشركة بيقع في السيجمنت الغلط
     */
    public function createApplication()
    {
        putenv('ROUTING_LOCALE=en');
        $_ENV['ROUTING_LOCALE'] = 'en';
        $_SERVER['ROUTING_LOCALE'] = 'en';

        return parent::createApplication();
    }

    protected function tearDown(): void
    {
        putenv('ROUTING_LOCALE');
        unset($_ENV['ROUTING_LOCALE'], $_SERVER['ROUTING_LOCALE']);
        self::$fake = null;

        parent::tearDown();
    }

    protected function setUpTraits()
    {
        config(['database.connections.mysql.database' => env('SMOKE_DB', 'cashvero')]);
        DB::purge('mysql');

        return parent::setUpTraits();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            LocaleSessionRedirect::class,
            LaravelLocalizationRedirectFilter::class,
            LaravelLocalizationViewPath::class,
        ]);

        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Development database not reachable: '.$e->getMessage());
        }

        /**
         * * الميدلوير isCashManagement بيطلب يوزر عنده النظام ده ، والنظام
         * * بيتقرا من أول شركة لليوزر — فبنختار شركة النظام فعلا موجود عندها
         */
        $companyId = DB::table('company_systems')->where('system_name', CASH_VERO)->value('company_id');

        if (! $companyId) {
            $this->markTestSkipped('No company in this database has the cash-vero system.');
        }

        $this->company = Company::findOrFail($companyId);
        $this->actor = $this->makeUser();

        self::$fake = $this->makeFakeOdooService();

        $this->app->bind(OdooSettingController::class, fn () => new class extends OdooSettingController
        {
            protected function odooService(Company $company): OdooService
            {
                return OdooSettingStoreSafetyTest::fake();
            }
        });
    }

    private function makeUser(): User
    {
        /** @var User $user */
        $user = User::create([
            'name' => 'Odoo Setting Safety '.uniqid(),
            'email' => 'odoo-setting-'.uniqid().'@example.test',
            'password' => bcrypt('secret-for-tests'),
            'company_id' => $this->company->id,
        ]);

        /** getRoleName() بيقرا أول رول من غير حماية من الفاضي */
        $user->assignRole('company-admin');

        /**
         * * cashvero بيفرض الصلاحيات بميدلوير عام ، و PermissionResolver
         * * بيقرا المنح المباشرة على اليوزر نفسه — مش صلاحيات الرول. النظام
         * * الأول مالوش الميدلوير ده ولا الصلاحيات دي مسجّلة عنده ، فبنمنح
         * * اللي موجود بس بدل ما التست يتكسر في واحد منهم.
         */
        foreach (['odoo_integration.update', 'odoo_integration.view'] as $key) {
            $permission = Permission::where('name', $key)->first();

            if ($permission) {
                $user->givePermissionTo($permission);
            }
        }

        $user->companies()->attach($this->company->id);
        $user->load('roles', 'permissions', 'companies');

        return $user;
    }

    private function url(): string
    {
        return route('odoo-settings.store', ['company' => $this->company->id]);
    }

    /** بصمة الصفوف الحالية عشان نقارن قبل وبعد */
    private function mappings(): array
    {
        $rows = DB::table('interest_revenue_accounts')
            ->where('company_id', $this->company->id)
            ->orderBy('odoo_code')
            ->get(['odoo_code', 'odoo_id'])
            ->map(fn ($r) => $r->odoo_code.'=>'.$r->odoo_id)
            ->all();

        sort($rows);

        return $rows;
    }

    private function seedMapping(string $code, int $odooId): void
    {
        DB::table('interest_revenue_accounts')->insert([
            'company_id' => $this->company->id,
            'financial_institution_id' => null,
            'odoo_code' => $code,
            'odoo_id' => $odooId,
        ]);
    }

    // ────────────────────────────────────────────────────────────

    /**
     * * الحالة اللي العميل كان هيقابلها : أودو واقع ، المستخدم بيحفظ الفورم
     * * زي أي يوم ، والربط كله بيتمسح
     */
    public function test_odoo_answering_for_nothing_leaves_the_existing_mappings_alone(): void
    {
        $this->seedMapping('IR-KEEP-1', 9001);
        $this->seedMapping('IR-KEEP-2', 9002);
        $before = $this->mappings();

        self::$fake->answers = [];

        $response = $this->actingAs($this->actor)->post($this->url(), [
            'revenues' => [
                ['odoo_code' => 'IR-KEEP-1', 'bank' => null],
                ['odoo_code' => 'IR-KEEP-2', 'bank' => null],
            ],
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('fail');

        $this->assertSame($before, $this->mappings(),
            'أودو ما ردّش على ولا كود — الربط القديم لازم يفضل مكانه بالكامل');
        $this->assertNotEmpty(self::$fake->calls, 'المفروض سألنا أودو قبل ما ناخد أي قرار');
    }

    /**
     * * ripcord بيرجّع array فيه faultString لما أودو يرفض ، و الـ array ده
     * * truthy — الكود القديم كان بيعدّي if($journal) وبعدين يقع على
     * * $journal[0]['id'] بـ "Undefined array key 0"
     */
    public function test_a_ripcord_fault_neither_crashes_nor_wipes(): void
    {
        $this->seedMapping('IR-FAULT', 9100);
        $before = $this->mappings();

        self::$fake->useForced = true;
        self::$fake->forced = ['faultCode' => 2, 'faultString' => 'AccessDenied: res.users(1,)'];

        $response = $this->actingAs($this->actor)->post($this->url(), [
            'revenues' => [['odoo_code' => 'IR-FAULT', 'bank' => null]],
            'bank_charges_code' => '5010',
        ]);

        $response->assertRedirect();
        $this->assertSame($before, $this->mappings(), 'رد الخطأ من أودو مش سبب إننا نمسح');
    }

    /**
     * * الحالة السليمة لازم تفضل شغالة : أودو ردّ ، الربط بيتبدّل فعلا
     */
    public function test_a_successful_save_replaces_the_mappings(): void
    {
        $this->seedMapping('IR-OLD', 9200);

        self::$fake->answers = ['IR-NEW-A' => 7001, 'IR-NEW-B' => 7002];

        $this->actingAs($this->actor)->post($this->url(), [
            'revenues' => [
                ['odoo_code' => 'IR-NEW-A', 'bank' => null],
                ['odoo_code' => 'IR-NEW-B', 'bank' => null],
            ],
        ])->assertRedirect();

        $this->assertSame(['IR-NEW-A=>7001', 'IR-NEW-B=>7002'], $this->mappings());
    }

    /**
     * * لو أودو حلّ البعض بس ، بنحفظ اللي اتحل ومانمسحش على رد ناقص
     */
    public function test_a_partial_answer_saves_what_resolved_without_wiping_on_failure(): void
    {
        $this->seedMapping('IR-OLD', 9300);

        self::$fake->answers = ['IR-GOOD' => 7100];

        $this->actingAs($this->actor)->post($this->url(), [
            'revenues' => [
                ['odoo_code' => 'IR-GOOD', 'bank' => null],
                ['odoo_code' => 'IR-UNKNOWN', 'bank' => null],
            ],
        ])->assertRedirect();

        $this->assertSame(['IR-GOOD=>7100'], $this->mappings(),
            'الكود اللي أودو عرفه يتحفظ ، واللي ماعرفوش يتسكت عنه — من غير ما نرجّع القديم');
    }

    /**
     * * مسح كل الصفوف عن قصد لازم يفضل ممكن : المستخدم شال كل السطور من
     * * الفورم فمابيتبعتش revenues أصلا. ده مش نفس حالة "أودو ما ردّش".
     */
    public function test_submitting_no_revenue_rows_still_clears_them(): void
    {
        $this->seedMapping('IR-TO-CLEAR', 9400);

        self::$fake->answers = [];

        $this->actingAs($this->actor)->post($this->url(), [])->assertRedirect();

        $this->assertSame([], $this->mappings(),
            'الفورم المبعوت من غير سطور إيرادات = المستخدم عايز يفضّيهم');
    }

    /**
     * * المسح والإدخال لازم يبقوا كتابة واحدة. بنخلي حفظ الإعدادات يفشل
     * * (عمود مش موجود) ونتأكد إن الربط رجع زي ما كان.
     */
    public function test_the_whole_save_rolls_back_together(): void
    {
        $this->seedMapping('IR-ATOMIC', 9500);
        $before = $this->mappings();

        self::$fake->answers = ['IR-ATOMIC' => 7200];

        /**
         * * بنفجّر آخر كتابة جوه المعاملة (حفظ الإعدادات) عشان نتأكد إن
         * * المسح اللي قبلها رجع. الحدث بيتسجّل على dispatcher التست ،
         * * فبينتهي مع التست ومابيسربش لغيره.
         */
        /**
         * * saving كمان مش creating/updating بس : لو الإعدادات موجودة و
         * * $result فاضية فـ update() مابتلوّثش حاجة و updating مابيحصلش —
         * * لكن saving بيحصل في الحالتين
         */
        foreach (['saving', 'creating', 'updating'] as $hook) {
            Event::listen('eloquent.'.$hook.': '.OdooSetting::class, function () {
                throw new \RuntimeException('BOOM-FROM-TEST');
            });
        }

        $this->withoutExceptionHandling();

        /**
         * * مش $this->fail() جوه الـ try : هو نفسه بيرمي استثناء و الـ catch
         * * كان هيبلعه ، فالتست كان هيعدّي وهو مش فاحص حاجة
         */
        $failure = null;

        try {
            $this->actingAs($this->actor)->post($this->url(), [
                'revenues' => [['odoo_code' => 'IR-ATOMIC', 'bank' => null]],
            ]);
        } catch (\Throwable $e) {
            $failure = $e;
        }

        $this->assertNotNull($failure, 'حفظ الإعدادات كان المفروض يفشل');
        $this->assertStringContainsString('BOOM-FROM-TEST', $failure->getMessage());

        $this->assertSame($before, $this->mappings(),
            'الكتابة فشلت في نُصّها — المفروض مفيش أي صف اتمسح');
    }

    /**
     * * بديل الخدمة : مابيتصلش بأودو ، وبيرد بالسيناريو اللي التست بيحدده
     */
    private function makeFakeOdooService(): object
    {
        return new class extends OdooService
        {
            public array $answers = [];

            public bool $useForced = false;

            public $forced = null;

            public array $calls = [];

            public function __construct() {}

            public function fetchData(string $modelName, array $fields = [], array $filters = [[]])
            {
                $code = $filters[0][0][2] ?? null;
                $this->calls[] = $code;

                if ($this->useForced) {
                    return $this->forced;
                }

                $key = (string) $code;

                return array_key_exists($key, $this->answers)
                    ? [['id' => $this->answers[$key], 'code' => $key, 'name' => 'Fake '.$key]]
                    : [];
            }
        };
    }
}
