<?php

namespace Tests\Feature\Notifications;

use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\Partner;
use App\Models\User;
use App\Notification;
use App\Support\Notifications\ExpiryNotifications;
use App\Support\Permissions\PermissionResolver;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The bell warns about LGs and facilities that are about to expire:
 * from today up to the days set in Notification Settings (7 for an LG,
 * 30 for a facility, by default). Already-expired ones are left to the
 * Expired LGs report.
 *
 * Runs against the development database inside a rolled-back transaction.
 */
class ExpiryNotificationsTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private FinancialInstitution $bank;

    private Partner $beneficiary;

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

        if (! Schema::connection('mysql')->hasColumn('notification_settings', 'lg_expiry_notification_days')) {
            $this->markTestSkipped('Run the migrations on '.DB::connection('mysql')->getDatabaseName().' first.');
        }

        $bank = FinancialInstitution::query()->whereNotNull('company_id')->whereNotNull('bank_id')->first();
        if (! $bank) {
            $this->markTestSkipped('Development database needs a bank-type financial institution.');
        }

        $this->bank = $bank;
        $this->company = Company::findOrFail($bank->company_id);
        $this->beneficiary = Partner::create(['company_id' => $this->company->id, 'name' => 'Expiry Beneficiary '.uniqid(), 'is_customer' => 1]);

        // Start from a clean bell for this company — the transaction puts it back.
        DB::table('notifications')->where('notifiable_id', $this->company->id)->delete();
        DB::table('notification_settings')->where('company_id', $this->company->id)->delete();
        $this->company->unsetRelation('notificationSetting');
    }

    private function lg(string $code, int $daysFromToday, array $overrides = []): void
    {
        DB::table('letter_of_guarantee_issuances')->insert(array_merge([
            'company_id' => $this->company->id,
            'financial_institution_id' => $this->bank->id,
            'partner_id' => $this->beneficiary->id,
            'lg_type' => 'bid-bond',
            'lg_code' => $code,
            'lg_amount' => 5000,
            'cash_cover_amount' => 500,
            'is_activated' => 1,
            'lg_currency' => 'EGP',
            'status' => 'running',
            'issuance_date' => now()->subYear()->format('Y-m-d'),
            'renewal_date' => now()->addDays($daysFromToday)->format('Y-m-d'),
        ], $overrides));
    }

    private function cleanOverdraft(string $accountNumber, int $daysFromToday): void
    {
        DB::table('clean_overdrafts')->insert([
            'company_id' => $this->company->id,
            'financial_institution_id' => $this->bank->id,
            'account_number' => $accountNumber,
            'currency' => 'EGP',
            'limit' => 250000,
            'contract_start_date' => now()->subYear()->format('Y-m-d'),
            'contract_end_date' => now()->addDays($daysFromToday)->format('Y-m-d'),
        ]);
    }

    /** @return list<array<string, mixed>> the data_array of every notification of that bell sub-type */
    private function notified(string $type): array
    {
        $this->company->unsetRelation('notifications');

        return $this->company->notifications()->get()
            ->filter(fn ($n) => ($n->data['type'] ?? null) === $type)
            ->map(fn ($n) => $n->data['data_array'])
            ->values()->all();
    }

    private function rebuild(): void
    {
        DB::table('notifications')->where('notifiable_id', $this->company->id)->delete();
        $this->company->unsetRelation('notificationSetting');
        ExpiryNotifications::notify($this->company);
    }

    private function userWith(array $permissions): User
    {
        $user = User::create([
            'name' => 'Expiry Notifications '.uniqid(),
            'email' => 'expiry-notifications-'.uniqid().'@example.test',
            'password' => bcrypt('secret-for-tests'),
            'company_id' => $this->company->id,
        ]);
        $user->givePermissionTo($permissions);
        $user->companies()->attach($this->company->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        PermissionResolver::flush();

        return $user->fresh();
    }

    public function test_an_lg_expiring_within_the_default_seven_days_is_notified(): void
    {
        $this->lg('EXP-LG-SOON', 3);
        $this->lg('EXP-LG-LATER', 10);
        $this->lg('EXP-LG-CANCELLED', 2, ['status' => 'cancelled']);
        $this->lg('EXP-LG-EXPIRED', -1);

        $this->rebuild();

        $rows = $this->notified(ExpiryNotifications::lgType('bid-bond'));
        $codes = array_column($rows, 'LG Code');
        $this->assertContains('EXP-LG-SOON', $codes);
        $this->assertNotContains('EXP-LG-LATER', $codes, 'Ten days away is outside the default 7-day window.');
        $this->assertNotContains('EXP-LG-CANCELLED', $codes);
        $this->assertNotContains('EXP-LG-EXPIRED', $codes, 'Already expired LGs belong to the Expired LGs report.');

        $soon = collect($rows)->firstWhere('LG Code', 'EXP-LG-SOON');
        $this->assertSame($this->beneficiary->name, $soon['Beneficiary Name']);
        $this->assertSame('5,000.00', $soon['LG Amount']);
        $this->assertSame('500.00', $soon['Cash Cover']);
        $this->assertSame('EGP', $soon['Currency']);
        $this->assertSame(3, $soon['Days Left']);
    }

    public function test_the_lg_window_follows_the_notification_setting(): void
    {
        $this->lg('EXP-LG-LATER', 10);
        $this->company->notificationSetting()->create(['lg_expiry_notification_days' => 15]);

        $this->rebuild();

        $this->assertContains('EXP-LG-LATER', array_column($this->notified(ExpiryNotifications::lgType('bid-bond')), 'LG Code'));
    }

    public function test_a_facility_expiring_within_thirty_days_is_notified(): void
    {
        $this->cleanOverdraft('EXP-OD-SOON', 20);
        $this->cleanOverdraft('EXP-OD-LATER', 45);

        $this->rebuild();

        $rows = $this->notified(ExpiryNotifications::facilityType(ExpiryNotifications::CLEAN_OVERDRAFT));
        $names = array_column($rows, 'Facility Name');
        $this->assertContains('EXP-OD-SOON', $names);
        $this->assertNotContains('EXP-OD-LATER', $names);

        $soon = collect($rows)->firstWhere('Facility Name', 'EXP-OD-SOON');
        $this->assertSame($this->bank->getName(), $soon['Bank Name']);
        $this->assertSame('250,000.00', $soon['Limit']);
        $this->assertSame(20, $soon['Days Left']);
    }

    public function test_the_bell_lists_only_the_facility_types_the_company_has(): void
    {
        $this->cleanOverdraft('EXP-OD-SOON', 20);
        $held = ExpiryNotifications::facilityTypesHeldBy($this->company->id);
        $this->assertContains(ExpiryNotifications::facilityType(ExpiryNotifications::CLEAN_OVERDRAFT), $held);

        $user = $this->userWith(['view clean overdraft', 'view letter of guarantee issuance']);
        auth()->login($user);
        $menu = collect(Notification::formatForMenuItem($this->company))->keyBy('key');
        auth()->logout();

        $this->assertTrue($menu->has(Notification::LETTER_OF_GUARANTEE));
        $this->assertCount(4, $menu[Notification::LETTER_OF_GUARANTEE]['submenu'], 'One child per LG type.');

        $facilityChildren = collect($menu[Notification::FACILITY]['submenu'])->pluck('data-show-notification-modal')->all();
        $this->assertContains(ExpiryNotifications::facilityType(ExpiryNotifications::CLEAN_OVERDRAFT), $facilityChildren);
        foreach ($facilityChildren as $child) {
            $this->assertContains($child, $held, 'A facility type the company has none of must not be a child.');
        }
        // Only the clean overdraft screen is granted.
        $this->assertSame([ExpiryNotifications::facilityType(ExpiryNotifications::CLEAN_OVERDRAFT)], $facilityChildren);
    }

    public function test_the_detail_endpoint_refuses_a_type_the_user_cannot_see(): void
    {
        $this->cleanOverdraft('EXP-OD-SOON', 20);
        $this->rebuild();
        $user = $this->userWith(['view clean overdraft']);

        $this->actingAs($user)
            ->getJson(route('notifications.detail', ['company' => $this->company->id, 'type' => ExpiryNotifications::lgType('bid-bond')]))
            ->assertForbidden();

        $this->actingAs($user)
            ->getJson(route('notifications.detail', ['company' => $this->company->id, 'type' => ExpiryNotifications::facilityType(ExpiryNotifications::CLEAN_OVERDRAFT)]))
            ->assertOk()
            ->assertJsonPath('headers', ['Bank Name', 'Facility Name', 'Expiry Date', 'Limit', 'Currency', 'Days Left']);
    }
}
