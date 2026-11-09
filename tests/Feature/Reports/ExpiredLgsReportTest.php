<?php

namespace Tests\Feature\Reports;

use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\Partner;
use App\Models\User;
use Database\Factories\PartnerFactory;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\Concerns\MakesBanks;
use Tests\TestCase;

/**
 * Expired LGs: every LG past its expiry as of TODAY and never cancelled,
 * filtered by currencies / banks / LG types / LG sources, one group (tab)
 * per currency.
 *
 * The company, its bank, the beneficiary and every LG are made by the test
 * and rolled back afterwards.
 */
class ExpiredLgsReportTest extends TestCase
{
    use MakesBanks;

    private const CUR_A = 'EGP';

    private const CUR_B = 'USD';

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

        $this->company = $this->makeCompany();
        $this->bank = $this->bank($this->company);
        $this->beneficiary = PartnerFactory::new()->forCompany($this->company)->customer()->create(['name' => 'Expired LG Beneficiary']);
    }

    private function user(): User
    {
        return $this->makeUser(['view lg by bank name report'], $this->company);
    }

    private function lg(string $code, string $renewalDate, string $currency, array $overrides = []): void
    {
        DB::table('letter_of_guarantee_issuances')->insert(array_merge([
            'company_id' => $this->company->id,
            'financial_institution_id' => $this->bank->id,
            'partner_id' => $this->beneficiary->id,
            'lg_type' => 'bid-bond',
            'lg_code' => $code,
            'transaction_name' => 'Tx '.$code,
            'lg_amount' => 1000,
            'cash_cover_amount' => 100,
            'is_activated' => 1,
            'lg_currency' => $currency,
            'status' => 'running',
            'source' => null,
            'issuance_date' => now()->subYear()->format('Y-m-d'),
            'renewal_date' => $renewalDate,
        ], $overrides));
    }

    private function filters(array $overrides = []): array
    {
        return array_merge([
            'currencies' => [self::CUR_A, self::CUR_B],
            'bank_id' => [$this->bank->id],
            'lg_types' => ['bid-bond', 'final-lgs', 'advanced-payment-lgs', 'performance-lg'],
            'sources' => ['lg-facility', 'against-cd', 'against-td', 'hundred-percentage-cash-cover'],
        ], $overrides);
    }

    /** @return array<string, list<string>> currency => LG codes */
    private function codesByCurrency($response): array
    {
        return collect($response->viewData('page')['props']['groups'])
            ->map(fn ($group) => collect($group['rows'])->pluck('lgCode')->all())
            ->all();
    }

    public function test_only_expired_uncancelled_lgs_are_listed_one_group_per_currency(): void
    {
        $yesterday = now()->subDay()->format('Y-m-d');
        $this->lg('LG-EXPIRED-A', $yesterday, self::CUR_A);
        $this->lg('LG-EXPIRED-TODAY', now()->format('Y-m-d'), self::CUR_A);
        $this->lg('LG-EXPIRED-B', $yesterday, self::CUR_B, ['lg_type' => 'performance-lg', 'source' => 'against-cd']);
        $this->lg('LG-CANCELLED', $yesterday, self::CUR_A, ['status' => 'cancelled']);
        $this->lg('LG-RUNNING', now()->addDay()->format('Y-m-d'), self::CUR_A);

        $response = $this->actingAs($this->user())
            ->get(route('result.expired.lgs.report', ['company' => $this->company->id] + $this->filters()));

        $response->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Statements/ExpiredLgs/Result', false));

        $codes = $this->codesByCurrency($response);
        $this->assertSame([self::CUR_A, self::CUR_B], array_keys($codes));
        $this->assertEqualsCanonicalizing(['LG-EXPIRED-A', 'LG-EXPIRED-TODAY'], $codes[self::CUR_A]);
        $this->assertSame(['LG-EXPIRED-B'], $codes[self::CUR_B]);

        $groupA = $response->viewData('page')['props']['groups'][self::CUR_A];
        $this->assertEquals(2000, $groupA['totalLgAmount']);
        $this->assertEquals(200, $groupA['totalCashCover']);
    }

    public function test_type_source_and_currency_filters_narrow_the_list(): void
    {
        $yesterday = now()->subDay()->format('Y-m-d');
        $this->lg('LG-BID-FACILITY', $yesterday, self::CUR_A);
        $this->lg('LG-PERF-CD', $yesterday, self::CUR_A, ['lg_type' => 'performance-lg', 'source' => 'against-cd']);
        $this->lg('LG-OTHER-CURRENCY', $yesterday, self::CUR_B);

        $response = $this->actingAs($this->user())->get(route('result.expired.lgs.report', ['company' => $this->company->id] + $this->filters([
            'currencies' => [self::CUR_A],
            'lg_types' => ['bid-bond'],
            // A null source counts as an LG-facility LG.
            'sources' => ['lg-facility'],
        ])));

        $this->assertSame([self::CUR_A => ['LG-BID-FACILITY']], $this->codesByCurrency($response));
    }

    public function test_the_date_is_always_today_whatever_is_sent(): void
    {
        $this->lg('LG-EXPIRES-NEXT-WEEK', now()->addWeek()->format('Y-m-d'), self::CUR_A);
        $this->lg('LG-EXPIRED', now()->subDay()->format('Y-m-d'), self::CUR_A);

        $response = $this->actingAs($this->user())->get(route('result.expired.lgs.report', ['company' => $this->company->id]
            + $this->filters(['date' => now()->addMonth()->format('Y-m-d'), 'start_date' => now()->addMonth()->format('Y-m-d')])));

        $this->assertSame([self::CUR_A => ['LG-EXPIRED']], $this->codesByCurrency($response));
    }

    public function test_export_and_print_cover_every_selected_currency(): void
    {
        $yesterday = now()->subDay()->format('Y-m-d');
        $this->lg('LG-EXPIRED-A', $yesterday, self::CUR_A);
        $this->lg('LG-EXPIRED-B', $yesterday, self::CUR_B);
        $user = $this->user();
        $query = ['company' => $this->company->id] + $this->filters();

        $this->actingAs($user)->get(route('export.expired.lgs.report', $query))->assertOk()->assertDownload();

        $print = $this->actingAs($user)->get(route('print.expired.lgs.report', $query));
        $print->assertOk();
        $rows = $print->viewData('page')['props']['rows'];
        $this->assertSame(['LG-EXPIRED-A', 'LG-EXPIRED-B'], array_column($rows, 'LG Code'));
        $this->assertSame([self::CUR_A, self::CUR_B], array_column($rows, 'Currency'));
    }

    public function test_the_filter_page_offers_today_and_the_lg_currencies(): void
    {
        $this->lg('LG-EXPIRED-A', now()->subDay()->format('Y-m-d'), self::CUR_A);

        $this->actingAs($this->user())
            ->get(route('view.expired.lgs.report', ['company' => $this->company->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Statements/ExpiredLgs/Index', false)
                ->where('today', now()->format('Y-m-d'))
                ->where('currencies', fn ($currencies) => collect($currencies)->pluck('value')->contains(self::CUR_A)));
    }
}
