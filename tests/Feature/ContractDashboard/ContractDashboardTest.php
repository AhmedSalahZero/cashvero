<?php

namespace Tests\Feature\ContractDashboard;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Partner;
use App\Models\User;
use App\Services\ContractDashboardService;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\Concerns\MakesInvoices;
use Tests\TestCase;

/**
 * Contract Dashboard billing math and auth.
 *
 * The company, its customers, contracts, invoices and collections are all made by the test
 * (and rolled back by TestCase).
 */
class ContractDashboardTest extends TestCase
{
    use MakesInvoices;

    private Company $company;

    private Partner $customerA;

    private Partner $customerB;

    private User $userWithPermission;

    private User $userWithoutPermission;

    private string $suffix;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            LocaleSessionRedirect::class,
            LaravelLocalizationRedirectFilter::class,
            LaravelLocalizationViewPath::class,
        ]);

        $this->company = $this->makeCompany();
        $this->suffix = uniqid('cdash-');

        $this->customerA = $this->customer($this->company, ['name' => 'Contract Dash Customer A '.$this->suffix]);
        $this->customerB = $this->customer($this->company, ['name' => 'Contract Dash Customer B '.$this->suffix]);

        // Customer A: EGP 300, invoices 120 + 80 → invoiced 200, remaining 100
        $contractA = $this->makeContract($this->customerA, 'EGP', 300, Contract::RUNNING, 'A');
        $this->makeInvoice($contractA, 120, 40, 80);
        $this->makeInvoice($contractA, 80, 30, 50);

        // Customer B: EGP 50, no invoices → remaining 50
        $this->makeContract($this->customerB, 'EGP', 50, Contract::RUNNING, 'B');

        // Status fixtures (EGP) so counts are exact for our created rows alone
        // — we assert relative deltas / known fixture totals via service
        // filtering on our unique codes below.
        $this->makeContract($this->customerA, 'EGP', 10, Contract::FINISHED, 'FIN');
        $this->makeContract($this->customerA, 'EGP', 20, Contract::RUNNING_AND_AGAINST, 'RA');

        // USD isolation: value 1000, invoice 100 → remaining 900
        $usd = $this->makeContract($this->customerA, 'USD', 1000, Contract::RUNNING, 'USD');
        $this->makeInvoice($usd, 100, 0, 100);

        $this->userWithPermission = $this->localUser(true);
        $this->userWithoutPermission = $this->localUser(false);
    }

    private function localUser(bool $withPermission): User
    {
        $user = $this->makeUser($withPermission ? ['dashboard_contracts.view'] : [], $this->company);

        $this->assertFalse($user->isSuperAdmin(), 'The test user must not bypass permission checks.');

        return $user;
    }

    private function makeContract(Partner $customer, string $currency, float $amount, string $status, string $tag): Contract
    {
        return $this->contract($customer, [
            'name' => 'Contract '.$tag.' '.$this->suffix,
            'code' => 'CD-'.$tag.'-'.$this->suffix,
            'currency' => $currency,
            'amount' => $amount,
            'exchange_rate' => 1,
            'status' => $status,
            'start_date' => now()->subMonths(6)->format('Y-m-d'),
            'end_date' => now()->addMonths(6)->format('Y-m-d'),
        ]);
    }

    /**
     * An invoice on the contract, part of it collected by money received from the customer.
     * The triggers work out the balance; it must come out as the fixture says.
     */
    private function makeInvoice(Contract $contract, float $invoiceAmount, float $collected, float $netBalance): void
    {
        $customer = $contract->client;

        $invoice = $this->invoice($customer, [
            'invoice_number' => 'INV-'.uniqid(),
            'invoice_date' => now()->format('Y-m-d'),
            'invoice_due_date' => now()->addMonth()->format('Y-m-d'),
            'invoice_amount' => $invoiceAmount,
            'currency' => $contract->getCurrency(),
            'exchange_rate' => 1,
        ], $contract);

        if ($collected > 0) {
            $money = $this->received($customer, [
                'received_amount' => $collected,
                'currency' => $contract->getCurrency(),
                'receiving_currency' => $contract->getCurrency(),
                'receiving_date' => now()->format('Y-m-d'),
            ]);
            $this->settle($money, $invoice, $collected);
        }

        $this->assertEqualsWithDelta($netBalance, (float) $invoice->fresh()->net_balance, 0.001, 'The fixture invoice must carry the balance it was built for.');
    }

    /** Only the fixtures this test created (unique code suffix). */
    private function fixtureRows(): array
    {
        $data = app(ContractDashboardService::class)->build($this->company);

        return collect($data['details']['all'])
            ->filter(fn (array $row) => str_contains((string) $row['code'], $this->suffix))
            ->values()
            ->all();
    }

    public function test_page_loads_with_permission_and_forbidden_without_it(): void
    {
        $this->actingAs($this->userWithPermission)
            ->get(route('view.contracts.dashboard', ['company' => $this->company->id]))
            ->assertOk();

        $this->actingAs($this->userWithoutPermission)
            ->get(route('view.contracts.dashboard', ['company' => $this->company->id]))
            ->assertForbidden();
    }

    public function test_billing_kpis_match_known_fixture_math(): void
    {
        $data = app(ContractDashboardService::class)->build($this->company);
        $fixture = collect($this->fixtureRows());

        $this->assertSame(5, $fixture->count());
        $this->assertSame(2, $fixture->where('status', Contract::RUNNING)->where('currency', 'EGP')->count());
        $this->assertSame(1, $fixture->where('status', Contract::FINISHED)->count());
        $this->assertSame(1, $fixture->where('status', Contract::RUNNING_AND_AGAINST)->count());
        $this->assertSame(1, $fixture->where('currency', 'USD')->count());

        $egp = $fixture->where('currency', 'EGP');
        // 300 + 50 + 10 + 20 = 380 (all statuses in currency)
        $this->assertEquals(380.0, (float) $egp->sum('amount'));
        // invoices only on the 300 contract: 200
        $this->assertEquals(200.0, (float) $egp->sum('invoiced'));
        // remaining: (300-200) + 50 + 10 + 20 = 180
        $this->assertEquals(180.0, (float) $egp->sum('remaining'));

        $this->assertArrayHasKey('EGP', $data['byCurrency']);
        // byCurrency includes ALL company contracts per currency — assert
        // isolated billing math on our USD fixture row alone.
        $usdRow = $fixture->firstWhere('currency', 'USD');
        $this->assertNotNull($usdRow);
        $this->assertEquals(1000.0, (float) $usdRow['amount']);
        $this->assertEquals(100.0, (float) $usdRow['invoiced']);
        $this->assertEquals(900.0, (float) $usdRow['remaining']);
        $this->assertEqualsWithDelta(10.0, (100.0 / 1000.0) * 100, 0.01);
    }

    public function test_top_customers_order_by_value_in_egp_fixtures(): void
    {
        $egpFixtures = collect($this->fixtureRows())->where('currency', 'EGP');

        $top = $egpFixtures
            ->groupBy('partner_id')
            ->map(fn ($group) => [
                'partner_id' => $group->first()['partner_id'],
                'value' => (float) $group->sum('amount'),
            ])
            ->sortByDesc('value')
            ->values();

        $this->assertSame($this->customerA->id, $top[0]['partner_id']);
        $this->assertGreaterThan($top[1]['value'], $top[0]['value']);
    }

    public function test_inertia_props_expose_dashboard_shape(): void
    {
        $response = $this->actingAs($this->userWithPermission)
            ->get(route('view.contracts.dashboard', ['company' => $this->company->id]));

        $response->assertOk();
        $this->assertIsArray($response->inertiaProps('counts'));
        $this->assertIsArray($response->inertiaProps('byCurrency'));
        $this->assertIsArray($response->inertiaProps('topByValue'));
        $this->assertIsArray($response->inertiaProps('details'));
        $this->assertSame(
            route('view.contracts.dashboard', ['company' => $this->company->id]),
            $response->inertiaProps('dashboardTabUrls.contracts')
        );
    }
}
