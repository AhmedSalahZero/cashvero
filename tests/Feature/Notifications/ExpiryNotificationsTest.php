<?php

namespace Tests\Feature\Notifications;

use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\Partner;
use App\Models\User;
use App\Notification;
use App\Support\Notifications\ExpiryNotifications;
use Database\Factories\PartnerFactory;
use Illuminate\Support\Facades\DB;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\Concerns\MakesBanks;
use Tests\TestCase;

/**
 * The bell warns about LGs and facilities that expire within the days set
 * in Notification Settings (7 for an LG, 30 for a facility, by default) —
 * and about those already past their expiry, which stay listed until they
 * are cancelled or renewed.
 *
 * The company, its bank, the beneficiary, the LGs and the overdrafts are made
 * by the test and rolled back afterwards.
 */
class ExpiryNotificationsTest extends TestCase
{
    use MakesBanks;

    private Company $company;

    private FinancialInstitution $bank;

    private Partner $beneficiary;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            LocaleSessionRedirect::class,
            LaravelLocalizationRedirectFilter::class,
            LaravelLocalizationViewPath::class,
        ]);

        // A new company: no notification settings (so the default windows apply) and an empty bell.
        $this->company = $this->makeCompany();
        $this->bank = $this->bank($this->company);
        $this->beneficiary = PartnerFactory::new()->forCompany($this->company)->customer()->create(['name' => 'Expiry Beneficiary']);
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
        return $this->makeUser($permissions, $this->company);
    }

    public function test_an_lg_expiring_within_seven_days_or_already_expired_is_notified(): void
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
        $this->assertContains('EXP-LG-EXPIRED', $codes, 'Once the count reaches zero or below the LG is expired, and must still be shown.');

        $soon = collect($rows)->firstWhere('LG Code', 'EXP-LG-SOON');
        $this->assertSame($this->beneficiary->name, $soon['Beneficiary Name']);
        $this->assertSame('5,000.00', $soon['LG Amount']);
        $this->assertSame('500.00', $soon['Cash Cover']);
        $this->assertSame('EGP', $soon['Currency']);
        $this->assertSame('Expires after 3 days', $soon['Status']);
        $this->assertSame('Expired since 1 days', collect($rows)->firstWhere('LG Code', 'EXP-LG-EXPIRED')['Status']);
    }

    public function test_the_lg_window_follows_the_notification_setting(): void
    {
        $this->lg('EXP-LG-LATER', 10);
        $this->company->notificationSetting()->create(['lg_expiry_notification_days' => 15]);

        $this->rebuild();

        $this->assertContains('EXP-LG-LATER', array_column($this->notified(ExpiryNotifications::lgType('bid-bond')), 'LG Code'));
    }

    public function test_a_facility_expiring_within_thirty_days_or_already_expired_is_notified(): void
    {
        $this->cleanOverdraft('EXP-OD-SOON', 20);
        $this->cleanOverdraft('EXP-OD-LATER', 45);
        $this->cleanOverdraft('EXP-OD-EXPIRED', -1);
        $this->cleanOverdraft('EXP-OD-TODAY', 0);

        $this->rebuild();

        $rows = $this->notified(ExpiryNotifications::facilityType(ExpiryNotifications::CLEAN_OVERDRAFT));
        $names = array_column($rows, 'Facility Name');
        $this->assertContains('EXP-OD-SOON', $names);
        $this->assertContains('EXP-OD-EXPIRED', $names);
        $this->assertContains('EXP-OD-TODAY', $names);
        $this->assertNotContains('EXP-OD-LATER', $names);
        $this->assertSame('Expired since 1 days', collect($rows)->firstWhere('Facility Name', 'EXP-OD-EXPIRED')['Status']);
        $this->assertSame('Expires today', collect($rows)->firstWhere('Facility Name', 'EXP-OD-TODAY')['Status']);

        $soon = collect($rows)->firstWhere('Facility Name', 'EXP-OD-SOON');
        $this->assertSame($this->bank->getName(), $soon['Bank Name']);
        $this->assertSame('250,000.00', $soon['Limit']);
        $this->assertSame('Expires after 20 days', $soon['Status']);
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
            ->assertJsonPath('headers', ['Bank Name', 'Facility Name', 'Expiry Date', 'Limit', 'Currency', 'Status']);
    }
}
