<?php

namespace Tests\Feature\Contracts;

use App\Models\Company;
use App\Models\Contract;
use App\Models\FinancialInstitution;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\TestCase;

/**
 * The Contracts list has a fourth tab, All: every contract whatever its
 * state, each row saying whether it is Running, Running And Against or
 * Finished. The three status tabs are unchanged.
 */
class AllContractsTabTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private Partner $customer;

    private User $user;

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

        if (! Schema::connection('mysql')->hasTable('contracts')) {
            $this->markTestSkipped('No development schema on '.DB::connection('mysql')->getDatabaseName().' - set SMOKE_DB to run this suite.');
        }

        $institution = FinancialInstitution::query()->whereNotNull('company_id')->first();
        if (! $institution) {
            $this->markTestSkipped('Development database has no financial institution to attach company context to.');
        }

        $this->company = Company::findOrFail($institution->company_id);
        $this->customer = Partner::create(['company_id' => $this->company->id, 'name' => 'All Tab Customer '.uniqid(), 'is_customer' => 1]);

        $user = User::create([
            'name' => 'All Contracts Tab '.uniqid(),
            'email' => 'all-contracts-tab-'.uniqid().'@example.test',
            'password' => bcrypt('secret-for-tests'),
            'company_id' => $this->company->id,
        ]);
        $user->givePermissionTo(['customer_contract.view', 'supplier_contract.view']);
        $user->companies()->attach($this->company->id);
        $user->load('companies');
        $this->user = $user;
    }

    private function makeContract(string $type, string $status): Contract
    {
        $tag = uniqid('all-');

        return Contract::create([
            'company_id' => $this->company->id,
            'partner_id' => $this->customer->id,
            'model_type' => $type,
            'name' => 'All Tab '.$tag,
            'code' => 'ALL-'.$tag,
            'currency' => 'EGP',
            'amount' => 100,
            'exchange_rate' => 1,
            'status' => $status,
            'start_date' => '2099-01-01',
            'end_date' => '2099-12-31',
        ]);
    }

    private function props(string $type, array $query = []): array
    {
        return $this->actingAs($this->user)
            ->get(route('contracts.index', ['company' => $this->company->id, 'type' => $type] + $query))
            ->assertOk()
            ->viewData('page')['props'];
    }

    public function test_the_all_tab_is_the_fourth_tab(): void
    {
        $this->assertSame(['running', 'running_and_against', 'finished', 'all'], $this->props('Customer')['contractStatues']);
        $this->assertArrayHasKey('all', $this->props('Customer')['tabUrls']);
    }

    public function test_the_all_tab_lists_every_status_and_says_which_each_one_is(): void
    {
        $running = $this->makeContract('Customer', Contract::RUNNING);
        $against = $this->makeContract('Customer', Contract::RUNNING_AND_AGAINST);
        $finished = $this->makeContract('Customer', Contract::FINISHED);

        // The 2099 start date keeps them on the first page: the list sorts newest start date first.
        $all = collect($this->props('Customer', ['active' => 'all'])['contracts']['all'])->keyBy('id');

        $this->assertSame(Contract::RUNNING, $all[$running->id]['status']);
        $this->assertSame(Contract::RUNNING_AND_AGAINST, $all[$against->id]['status']);
        $this->assertSame(Contract::FINISHED, $all[$finished->id]['status']);
    }

    public function test_the_status_tabs_still_hold_only_their_own_contracts(): void
    {
        $running = $this->makeContract('Customer', Contract::RUNNING);
        $finished = $this->makeContract('Customer', Contract::FINISHED);

        $contracts = $this->props('Customer')['contracts'];

        $this->assertContains($running->id, collect($contracts['running'])->pluck('id')->all());
        $this->assertNotContains($finished->id, collect($contracts['running'])->pluck('id')->all());
        $this->assertContains($finished->id, collect($contracts['finished'])->pluck('id')->all());
        $this->assertNotContains($running->id, collect($contracts['finished'])->pluck('id')->all());
    }

    public function test_the_all_tab_only_holds_the_contracts_of_the_page_type(): void
    {
        $customerContract = $this->makeContract('Customer', Contract::RUNNING);
        $supplierContract = $this->makeContract('Supplier', Contract::RUNNING);

        $customerIds = collect($this->props('Customer')['contracts']['all'])->pluck('id')->all();
        $supplierIds = collect($this->props('Supplier')['contracts']['all'])->pluck('id')->all();

        $this->assertContains($customerContract->id, $customerIds);
        $this->assertNotContains($supplierContract->id, $customerIds);
        $this->assertContains($supplierContract->id, $supplierIds);
        $this->assertNotContains($customerContract->id, $supplierIds);
    }

    public function test_the_all_tab_total_counts_every_status(): void
    {
        $before = $this->props('Customer')['paginators']['all']['total'];

        $this->makeContract('Customer', Contract::RUNNING);
        $this->makeContract('Customer', Contract::RUNNING_AND_AGAINST);
        $this->makeContract('Customer', Contract::FINISHED);

        $this->assertSame($before + 3, $this->props('Customer')['paginators']['all']['total']);
    }
}
