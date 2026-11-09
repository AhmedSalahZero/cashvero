<?php

namespace Tests\Feature\OverdraftAgainstAssignmentOfContract;

use App\Models\Company;
use App\Models\Contract;
use App\Models\FinancialInstitution;
use App\Models\Partner;
use App\Models\User;
use Database\Factories\PartnerFactory;
use Inertia\Testing\AssertableInertia as Assert;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\Concerns\MakesBanks;
use Tests\Concerns\MakesFacilities;
use Tests\TestCase;

/**
 * Lending Information may only offer open contracts of the chosen
 * customer as collateral — a finished contract is closed business.
 *
 * The company, bank, customer, contracts and facility are made by the test
 * and rolled back afterwards.
 */
class LendingInformationExcludesFinishedContractsTest extends TestCase
{
    use MakesBanks;
    use MakesFacilities;

    private Company $company;

    private FinancialInstitution $bank;

    private Partner $customer;

    private User $user;

    private Contract $running;

    private Contract $finished;

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

        $this->customer = PartnerFactory::new()->forCompany($this->company)->customer()->create([
            'name' => 'Lending Finished Filter Customer '.uniqid(),
        ]);

        $this->running = $this->makeContract(Contract::RUNNING, 'RUN');
        $this->finished = $this->makeContract(Contract::FINISHED, 'FIN');

        $this->user = $this->makeUser([
            'overdraft_assignment_contract.view',
            'overdraft_assignment_contract.update',
        ], $this->company);
    }

    public function test_the_lending_information_contract_list_omits_finished_contracts(): void
    {
        $this->actingAs($this->user)
            ->get(route('view.overdraft.against.assignment.of.contract', [
                'company' => $this->company->id,
                'financialInstitution' => $this->bank->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('OverdraftAgainstAssignmentOfContract/Index', false)
                ->has('contracts')
                ->where('contracts', function ($contracts) {
                    $ids = collect($contracts)->pluck('id');

                    return $ids->contains($this->running->id)
                        && ! $ids->contains($this->finished->id);
                })
            );
    }

    public function test_a_finished_contract_cannot_be_assigned_as_collateral(): void
    {
        $facility = $this->overdraftAgainstAssignmentOfContract($this->bank, [
            'account_number' => 'LFF-'.uniqid(),
            'currency' => 'EGP',
            'limit' => 1000,
        ]);

        $indexUrl = route('view.overdraft.against.assignment.of.contract', [
            'company' => $this->company->id,
            'financialInstitution' => $this->bank->id,
        ]);

        $this->actingAs($this->user)
            ->from($indexUrl)
            ->post(route('lending.information.apply.for.against.assignment.of.contract', [
                'company' => $this->company->id,
                'financialInstitution' => $this->bank->id,
                'odAgainstAssignmentOfContract' => $facility->id,
            ]), [
                'customer_id_create' => $this->customer->id,
                'contract_id_create' => $this->finished->id,
                'assignment_date_create' => now()->format('Y-m-d'),
                'lending_rate_create' => 50,
            ])
            ->assertRedirect($indexUrl)
            ->assertSessionHasErrors('contract_id_create');

        $this->assertSame(Contract::FINISHED, $this->finished->fresh()->status);
        $this->assertSame(0, $facility->lendingInformation()->count());
    }

    private function makeContract(string $status, string $tag): Contract
    {
        $suffix = uniqid('lff-');

        return Contract::create([
            'company_id' => $this->company->id,
            'partner_id' => $this->customer->id,
            'model_type' => 'Customer',
            'name' => 'Lending Filter '.$tag.' '.$suffix,
            'code' => 'LFF-'.$tag.'-'.$suffix,
            'currency' => 'EGP',
            'amount' => 100,
            'exchange_rate' => 1,
            'status' => $status,
            'start_date' => now()->subMonths(6)->format('Y-m-d'),
            'end_date' => now()->addMonths(6)->format('Y-m-d'),
        ]);
    }
}
