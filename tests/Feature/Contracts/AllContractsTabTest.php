<?php

namespace Tests\Feature\Contracts;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Partner;
use App\Models\User;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\Concerns\MakesInvoices;
use Tests\TestCase;

/**
 * The Contracts list has a fourth tab, All: every contract whatever its
 * state, each row saying whether it is Running, Running And Against or
 * Finished. The three status tabs are unchanged.
 *
 * The company, its customer and every contract are made by the test.
 */
class AllContractsTabTest extends TestCase
{
    use MakesInvoices;

    private Company $company;

    private Partner $customer;

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
        $this->customer = $this->customer($this->company, ['name' => 'All Tab Customer']);
        $this->user = $this->makeUser(['customer_contract.view', 'supplier_contract.view'], $this->company);
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
