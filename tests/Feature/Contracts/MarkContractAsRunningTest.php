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
 * Finished → Running is the undo for an ordinary contract. A contract
 * still pledged as overdraft collateral must not take that path.
 */
class MarkContractAsRunningTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private Partner $customer;

    private User $user;

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

        $this->customer = Partner::create([
            'company_id' => $this->company->id,
            'name' => 'Back To Running Customer '.uniqid(),
            'is_customer' => 1,
        ]);

        /** @var User $user */
        $user = User::create([
            'name' => 'Back To Running Test '.uniqid(),
            'email' => 'back-to-running-'.uniqid().'@example.test',
            'password' => bcrypt('secret-for-tests'),
            'company_id' => $this->company->id,
        ]);
        $user->givePermissionTo([
            'customer_contract.view',
            'customer_contract.approve',
            'supplier_contract.view',
            'supplier_contract.approve',
        ]);
        $user->companies()->attach($this->company->id);
        $user->load('companies');
        $this->user = $user;
    }

    public function test_a_finished_customer_contract_returns_to_running(): void
    {
        $contract = $this->makeContract('Customer', Contract::FINISHED);

        $response = $this->actingAs($this->user)
            ->from($this->indexUrl('Customer'))
            ->put($this->markRunningUrl($contract, 'Customer'));

        $response->assertRedirect(route('contracts.index', [
            'company' => $this->company->id,
            'type' => 'Customer',
            'active' => Contract::RUNNING,
        ]));
        $this->assertSame(Contract::RUNNING, $contract->fresh()->status);
    }

    public function test_a_finished_supplier_contract_returns_to_running(): void
    {
        $supplier = Partner::create([
            'company_id' => $this->company->id,
            'name' => 'Back To Running Supplier '.uniqid(),
            'is_supplier' => 1,
        ]);
        $contract = $this->makeContract('Supplier', Contract::FINISHED, $supplier);

        $this->actingAs($this->user)
            ->from($this->indexUrl('Supplier'))
            ->put($this->markRunningUrl($contract, 'Supplier'))
            ->assertRedirect(route('contracts.index', [
                'company' => $this->company->id,
                'type' => 'Supplier',
                'active' => Contract::RUNNING,
            ]));

        $this->assertSame(Contract::RUNNING, $contract->fresh()->status);
    }

    public function test_a_pledged_finished_contract_cannot_return_to_plain_running(): void
    {
        $contract = $this->makeContract('Customer', Contract::FINISHED, pledgedFacilityId: 1);

        $this->actingAs($this->user)
            ->from($this->indexUrl('Customer'))
            ->put($this->markRunningUrl($contract, 'Customer'))
            ->assertRedirect($this->indexUrl('Customer'))
            ->assertSessionHas('fail');

        $this->assertSame(Contract::FINISHED, $contract->fresh()->status);
    }

    public function test_a_running_contract_cannot_be_marked_running_again_via_this_action(): void
    {
        $contract = $this->makeContract('Customer', Contract::RUNNING);

        $this->actingAs($this->user)
            ->from($this->indexUrl('Customer'))
            ->put($this->markRunningUrl($contract, 'Customer'))
            ->assertRedirect($this->indexUrl('Customer'))
            ->assertSessionHas('fail');

        $this->assertSame(Contract::RUNNING, $contract->fresh()->status);
    }

    private function makeContract(string $type, string $status, ?Partner $partner = null, ?int $pledgedFacilityId = null): Contract
    {
        $tag = uniqid('btr-');

        return Contract::create([
            'company_id' => $this->company->id,
            'partner_id' => ($partner ?? $this->customer)->id,
            'model_type' => $type,
            'name' => 'Back To Running '.$tag,
            'code' => 'BTR-'.$tag,
            'currency' => 'EGP',
            'amount' => 100,
            'exchange_rate' => 1,
            'status' => $status,
            'start_date' => now()->subMonths(6)->format('Y-m-d'),
            'end_date' => now()->addMonths(6)->format('Y-m-d'),
            'overdraft_against_assignment_of_contract_id' => $pledgedFacilityId,
        ]);
    }

    private function indexUrl(string $type): string
    {
        return route('contracts.index', ['company' => $this->company->id, 'type' => $type]);
    }

    private function markRunningUrl(Contract $contract, string $type): string
    {
        return route('contract.mark.as.running', [
            'company' => $this->company->id,
            'contract' => $contract->id,
            'type' => $type,
        ]);
    }
}
