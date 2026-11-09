<?php

namespace Tests\Feature\Contracts;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\Concerns\MakesBanks;
use Tests\Concerns\MakesInvoices;
use Tests\TestCase;

/**
 * Finished → Running is the undo for an ordinary contract. A contract
 * still pledged as overdraft collateral must not take that path.
 *
 * The company, its customer, supplier, contracts and the overdraft facility are all made by
 * the test (and rolled back by TestCase).
 */
class MarkContractAsRunningTest extends TestCase
{
    use MakesBanks;
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
        $this->customer = $this->customer($this->company, ['name' => 'Back To Running Customer']);
        $this->user = $this->makeUser([
            'customer_contract.view',
            'customer_contract.approve',
            'supplier_contract.view',
            'supplier_contract.approve',
        ], $this->company);
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
        $supplier = $this->supplier($this->company, ['name' => 'Back To Running Supplier']);
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
        $contract = $this->makeContract('Customer', Contract::FINISHED, pledgedFacilityId: $this->overdraftFacility());

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
        return $this->contract($partner ?? $this->customer, [
            'model_type' => $type,
            'status' => $status,
            'amount' => 100,
            'start_date' => now()->subMonths(6)->format('Y-m-d'),
            'end_date' => now()->addMonths(6)->format('Y-m-d'),
            'overdraft_against_assignment_of_contract_id' => $pledgedFacilityId,
        ]);
    }

    /** An "overdraft against assignment of contracts" facility of the company, at its own bank. */
    private function overdraftFacility(): int
    {
        return DB::table('overdraft_against_assignment_of_contracts')->insertGetId([
            'company_id' => $this->company->id,
            'financial_institution_id' => $this->bank($this->company)->id,
            'account_number' => 'OD-AOC-'.uniqid(),
            'currency' => 'EGP',
            'limit' => 1000000,
            'contract_start_date' => now()->subYear()->format('Y-m-d'),
            'contract_end_date' => now()->addYear()->format('Y-m-d'),
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
