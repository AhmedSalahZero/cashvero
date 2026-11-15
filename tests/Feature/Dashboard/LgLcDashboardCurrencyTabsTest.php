<?php

namespace Tests\Feature\Dashboard;

use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\User;
use Database\Factories\LetterOfCreditFacilityFactory;
use Database\Factories\LetterOfGuaranteeFacilityFactory;
use Illuminate\Testing\TestResponse;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\Concerns\MakesBanks;
use Tests\TestCase;

/**
 * LG/LC dashboard currency pills: one per currency with an LG or LC facility still running on
 * the chosen date — the old system's rule for its currency tabs.
 *
 * Mirrors company 92: an EGP LG facility running to 2030, and one USD LG facility that ended on
 * 2026-07-01. On 2026-11-15 the USD pill used to show and open onto an empty page.
 *
 * Everything is made by the test and rolled back by TestCase.
 */
class LgLcDashboardCurrencyTabsTest extends TestCase
{
    use MakesBanks;

    private Company $company;

    private FinancialInstitution $bank;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            LocaleSessionRedirect::class,
            LaravelLocalizationRedirectFilter::class,
            LaravelLocalizationViewPath::class,
        ]);

        $this->company = $this->makeCompany();
        $this->assertSame('EGP', $this->company->getMainFunctionalCurrency());

        $this->bank = $this->bank($this->company);
        $this->lgFacility('EGP', 1_000_000, '2030-12-31');
        $this->lgFacility('USD', 54_750, '2026-07-01');

        $this->user = $this->makeUser(['dashboard_lg_lc.view'], $this->company);
        $this->assertFalse($this->user->isSuperAdmin(), 'The test user must not bypass permission checks.');
    }

    public function test_a_currency_whose_only_facility_has_ended_gets_no_pill(): void
    {
        $response = $this->dashboard(['date' => '2026-11-15']);

        $this->assertSame(['EGP'], $response->inertiaProps('selectedCurrencies'));
        $this->assertEqualsCanonicalizing(['EGP', 'USD'], $response->inertiaProps('allCurrencies'));
        $this->assertSame(['EGP'], $this->computedCurrencies($response));
    }

    public function test_a_facility_still_counts_on_its_last_day(): void
    {
        $response = $this->dashboard(['date' => '2026-07-01']);

        $this->assertEqualsCanonicalizing(['EGP', 'USD'], $response->inertiaProps('selectedCurrencies'));
    }

    public function test_asking_for_a_currency_without_a_pill_falls_back_to_the_main_currency(): void
    {
        $response = $this->dashboard(['date' => '2026-11-15', 'currencies' => ['USD']]);

        $this->assertSame(['EGP'], $this->computedCurrencies($response));
        $this->assertTrue($response->inertiaProps('canShowDashboardPerCurrency.lg.EGP'));
    }

    public function test_asking_for_a_currency_with_a_pill_computes_it(): void
    {
        $response = $this->dashboard(['date' => '2026-06-30', 'currencies' => ['USD']]);

        $this->assertSame(['USD'], $this->computedCurrencies($response));
        $this->assertEquals(54_750, $response->inertiaProps('reports.lg.USD.limit'));
        $this->assertTrue($response->inertiaProps('canShowDashboardPerCurrency.lg.USD'));
    }

    public function test_a_running_lc_facility_keeps_the_pill_after_the_lg_one_ended(): void
    {
        LetterOfCreditFacilityFactory::new()->at($this->bank)->create([
            'currency' => 'USD',
            'limit' => 200_000,
            'contract_end_date' => '2027-12-31',
        ]);

        $response = $this->dashboard(['date' => '2026-11-15']);

        $this->assertEqualsCanonicalizing(['EGP', 'USD'], $response->inertiaProps('selectedCurrencies'));
    }

    public function test_a_running_facility_with_a_zero_limit_does_not_count(): void
    {
        $this->lgFacility('EUR', 0, '2030-12-31');

        $response = $this->dashboard(['date' => '2026-11-15']);

        $this->assertSame(['EGP'], $response->inertiaProps('selectedCurrencies'));
    }

    public function test_the_page_still_opens_when_no_facility_is_running(): void
    {
        $response = $this->dashboard(['date' => '2031-01-01']);

        $this->assertSame([], $response->inertiaProps('selectedCurrencies'));
        $this->assertSame([], $this->computedCurrencies($response));
    }

    public function test_a_facility_of_another_company_gives_no_pill(): void
    {
        $otherCompany = $this->makeCompany();
        LetterOfGuaranteeFacilityFactory::new()->at($this->bank($otherCompany))->create([
            'currency' => 'SAR',
            'limit' => 500_000,
            'contract_end_date' => '2030-12-31',
        ]);

        $response = $this->dashboard(['date' => '2026-11-15']);

        $this->assertSame(['EGP'], $response->inertiaProps('selectedCurrencies'));
    }

    private function lgFacility(string $currency, float $limit, string $contractEndDate): void
    {
        LetterOfGuaranteeFacilityFactory::new()->at($this->bank)->create([
            'currency' => $currency,
            'limit' => $limit,
            'contract_start_date' => '2025-01-01',
            'contract_end_date' => $contractEndDate,
        ]);
    }

    private function dashboard(array $query): TestResponse
    {
        return $this->actingAs($this->user)
            ->get(route('view.lglc.dashboard', ['company' => $this->company->id] + $query))
            ->assertOk();
    }

    /** The currencies the server worked the figures out for on this visit. */
    private function computedCurrencies(TestResponse $response): array
    {
        $reports = $response->inertiaProps('reports');

        return array_values(array_unique(array_merge(
            array_keys($reports['lg'] ?? []),
            array_keys($reports['lc'] ?? []),
        )));
    }
}
