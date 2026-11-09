<?php

namespace Tests\Feature\Activity;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Company;
use App\Models\Log;
use App\Models\User;
use App\Support\Permissions\PermissionResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * الـ login بيحدّث users.last_login_at وبيتسجل في logs من غير شركة، من
 * غير ما يكتب audit في record_activities. وكل صفحة جوه شركة المستخدم
 * بيفتحها (أول تحميل أو تنقل Inertia) بتتسجل في logs بالشركة واسم القسم.
 * الـ partial reload والـ ajax والـ JSON وتحميل الملفات ما بيتسجلوش.
 */
class SectionVisitLoggingTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private User $user;

    public function createApplication()
    {
        putenv('ROUTING_LOCALE=en');
        $_ENV['ROUTING_LOCALE'] = 'en';
        $_SERVER['ROUTING_LOCALE'] = 'en';

        return parent::createApplication();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            LocaleSessionRedirect::class,
            LaravelLocalizationRedirectFilter::class,
            LaravelLocalizationViewPath::class,
        ]);

        // A fresh company and user, made here and rolled back afterwards.
        $this->company = $this->makeCompany();

        $user = $this->makeUser(['money_received.view'], $this->company, null, ['name' => 'Section Visit Test']);
        $user->assignRole(Role::firstOrCreate(['name' => 'test-scratch-role', 'guard_name' => 'web']));

        $this->forgetPermissions();
        $this->user = $user->fresh();
        $this->user->load('roles.permissions', 'permissions');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        putenv('ROUTING_LOCALE');
        unset($_ENV['ROUTING_LOCALE'], $_SERVER['ROUTING_LOCALE']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        PermissionResolver::flush();

        parent::tearDown();
    }

    public function test_login_sets_last_login_at_without_touching_updated_at_or_the_audit_trail(): void
    {
        $updatedAtBefore = $this->user->fresh()->updated_at;
        $activitiesBefore = DB::table('record_activities')->count();
        Carbon::setTestNow(now()->addDays(3)->startOfMinute());

        Auth::login($this->user);

        $fresh = $this->user->fresh();
        $this->assertNotNull($fresh->last_login_at);
        $this->assertSame(now()->format('Y-m-d H:i:s'), $fresh->last_login_at->format('Y-m-d H:i:s'));
        $this->assertEquals($updatedAtBefore, $fresh->updated_at);
        $this->assertSame($activitiesBefore, DB::table('record_activities')->count());
    }

    public function test_login_and_logout_are_stored_without_a_company(): void
    {
        Auth::login($this->user);
        $login = $this->lastLogRow();
        $this->assertNull($login->company_id);
        $this->assertSame(Log::generateLogMessage('successLogin'), $login->activity);

        Auth::logout();
        $logout = $this->lastLogRow();
        $this->assertNull($logout->company_id);
        $this->assertSame(Log::generateLogMessage('successLogout'), $logout->activity);
    }

    public function test_opening_a_page_records_the_section(): void
    {
        $this->assertStringContainsString('/en/'.$this->company->id.'/', $this->moneyReceivedUrl());

        $this->actingAs($this->user)->get($this->moneyReceivedUrl())->assertOk();

        $row = $this->lastLogRow();
        $this->assertSame($this->company->id, (int) $row->company_id);
        $this->assertSame(Log::generateLogMessage('enterSection', 'Money Received'), $row->activity);
    }

    public function test_reopening_the_same_section_is_stored_again_only_after_the_window(): void
    {
        $this->actingAs($this->user)->get($this->moneyReceivedUrl())->assertOk();
        $this->actingAs($this->user)->get($this->moneyReceivedUrl())->assertOk();
        $this->assertSame(1, $this->companyLogCount());

        DB::table('logs')->where('id', $this->lastLogRow()->id)->update([
            'created_at' => now()->subMinutes(Log::DEDUPE_MINUTES + 1)->format('Y-m-d H:i:s'),
        ]);

        $this->actingAs($this->user)->get($this->moneyReceivedUrl())->assertOk();
        $this->assertSame(2, $this->companyLogCount());
    }

    public function test_inertia_navigation_is_recorded_but_partial_reloads_are_not(): void
    {
        $inertia = [
            'X-Inertia' => 'true',
            'X-Requested-With' => 'XMLHttpRequest',
            'X-Inertia-Version' => (string) app(HandleInertiaRequests::class)->version(Request::create('/')),
        ];

        $this->actingAs($this->user)
            ->get($this->moneyReceivedUrl(), $inertia + [
                'X-Inertia-Partial-Data' => 'rows',
                'X-Inertia-Partial-Component' => 'Unknown/Component',
            ])
            ->assertOk();
        $this->assertSame(0, $this->companyLogCount());

        $this->actingAs($this->user)
            ->get($this->moneyReceivedUrl(), $inertia)
            ->assertOk()
            ->assertHeader('X-Inertia', 'true');
        $this->assertSame(1, $this->companyLogCount());
    }

    public function test_ajax_and_json_requests_are_not_recorded(): void
    {
        $this->actingAs($this->user)->get($this->moneyReceivedUrl(), ['X-Requested-With' => 'XMLHttpRequest']);
        $this->actingAs($this->user)->getJson($this->moneyReceivedUrl());

        $this->assertSame(0, $this->companyLogCount());
    }

    public function test_downloads_and_json_responses_are_not_recorded(): void
    {
        config(['permissions.unmapped' => 'allow']);

        Route::middleware(['web', 'auth'])->group(function () {
            Route::get('/en/{company}/section-visit-probe/download', fn () => response()->streamDownload(fn () => print('x'), 'x.txt'))
                ->name('section.visit.probe.download');
            Route::get('/en/{company}/section-visit-probe/json', fn () => response()->json(['ok' => true]))
                ->name('section.visit.probe.json');
            Route::get('/en/{company}/section-visit-probe/page', fn () => response('<html><body>page</body></html>'))
                ->name('section.visit.probe.page');
        });

        $base = '/en/'.$this->company->id.'/section-visit-probe/';
        $this->actingAs($this->user)->get($base.'download')->assertOk();
        $this->actingAs($this->user)->get($base.'json')->assertOk();
        $this->assertSame(0, $this->companyLogCount());

        $this->actingAs($this->user)->get($base.'page')->assertOk();
        $this->assertSame(1, $this->companyLogCount());
    }

    private function moneyReceivedUrl(): string
    {
        return route('view.money.receive', ['company' => $this->company->id]);
    }

    private function lastLogRow(): object
    {
        $row = DB::table('logs')->where('user_id', $this->user->id)->orderByDesc('id')->first();
        $this->assertNotNull($row, 'No logs row was written for the test user.');

        return $row;
    }

    private function companyLogCount(): int
    {
        return DB::table('logs')
            ->where('user_id', $this->user->id)
            ->where('company_id', $this->company->id)
            ->count();
    }
}
