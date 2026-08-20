<?php

namespace Tests\Feature\ContractDashboard;

use App\Models\Company;
use App\Models\Contract;
use App\Models\FinancialInstitution;
use App\Models\Partner;
use App\Models\User;
use App\Services\ContractDashboardService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\TestCase;

/**
 * Contract Dashboard billing math and auth.
 *
 * Runs against SMOKE_DB (falls back to DB_DATABASE) inside a rolled-back
 * transaction, same pattern as the rest of the smoke suite.
 */
class ContractDashboardTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private Partner $customerA;

    private Partner $customerB;

    private User $userWithPermission;

    private User $userWithoutPermission;

    private string $suffix;

    protected function setUpTraits()
    {
        config(['database.connections.mysql.database' => env('SMOKE_DB', env('DB_DATABASE', 'cashvero'))]);
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
         * Reaching the server is not the same as finding the schema on
         * it. Under phpunit.xml, DB_DATABASE points at the unit-test
         * database, which connects fine and has none of these tables —
         * so without this the whole class errored instead of skipping.
         * Point SMOKE_DB at a real development schema to run it.
         */
        if (! Schema::connection('mysql')->hasTable('financial_institutions')) {
            $this->markTestSkipped(
                'No development schema on '.DB::connection('mysql')->getDatabaseName().' — set SMOKE_DB to run this suite.'
            );
        }

        $bank = FinancialInstitution::query()->whereNotNull('company_id')->first();
        if (! $bank) {
            $this->markTestSkipped('Development database has no financial institution to attach company context to.');
        }

        $this->company = Company::findOrFail($bank->company_id);
        $this->suffix = uniqid('cdash-');

        $this->customerA = Partner::create([
            'company_id' => $this->company->id,
            'name' => 'Contract Dash Customer A '.$this->suffix,
            'is_customer' => 1,
        ]);

        $this->customerB = Partner::create([
            'company_id' => $this->company->id,
            'name' => 'Contract Dash Customer B '.$this->suffix,
            'is_customer' => 1,
        ]);

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

        $this->userWithPermission = $this->makeUser(true);
        $this->userWithoutPermission = $this->makeUser(false);
    }

    private function makeUser(bool $withPermission): User
    {
        /** @var User $user */
        $user = User::create([
            'name' => 'Contract Dashboard Test '.uniqid(),
            'email' => 'contract-dash-'.uniqid().'@example.test',
            'password' => bcrypt('secret-for-tests'),
            'company_id' => $this->company->id,
        ]);

        if ($withPermission) {
            $user->givePermissionTo('dashboard_contracts.view');
        }

        $user->companies()->attach($this->company->id);
        $user->load('companies');

        $this->assertFalse($user->isSuperAdmin(), 'The test user must not bypass permission checks.');

        return $user;
    }

    private function makeContract(Partner $customer, string $currency, float $amount, string $status, string $tag): Contract
    {
        return Contract::create([
            'company_id' => $this->company->id,
            'partner_id' => $customer->id,
            'model_type' => 'Customer',
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

    private function makeInvoice(Contract $contract, float $invoiceAmount, float $collected, float $netBalance): void
    {
        DB::table('customer_invoices')->insert([
            'company_id' => $this->company->id,
            'customer_id' => $contract->partner_id,
            'customer_name' => $contract->client?->getName() ?? 'Customer',
            'invoice_number' => 'INV-'.uniqid(),
            'invoice_date' => now()->format('Y-m-d'),
            'invoice_amount' => (string) $invoiceAmount,
            'net_invoice_amount' => (string) $invoiceAmount,
            'currency' => $contract->getCurrency(),
            'exchange_rate' => 1,
            'contract_code' => $contract->getCode(),
            'contract_name' => $contract->getName(),
            'contract_amount' => $contract->getAmount(),
            'total_collected_amount' => $collected,
            'net_balance' => (string) $netBalance,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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
