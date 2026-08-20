<?php

namespace Tests\Feature\OverdraftAgainstAssignmentOfContract;

use App\Models\Company;
use App\Models\Contract;
use App\Models\FinancialInstitution;
use App\Models\OverdraftAgainstAssignmentOfContract;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\TestCase;

/**
 * Lending Information may only offer open contracts of the chosen
 * customer as collateral — a finished contract is closed business.
 */
class LendingInformationExcludesFinishedContractsTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private FinancialInstitution $bank;

    private Partner $customer;

    private User $user;

    private Contract $running;

    private Contract $finished;

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

        $this->bank = $bank;
        $this->company = Company::findOrFail($bank->company_id);

        $this->customer = Partner::create([
            'company_id' => $this->company->id,
            'name' => 'Lending Finished Filter Customer '.uniqid(),
            'is_customer' => 1,
        ]);

        $this->running = $this->makeContract(Contract::RUNNING, 'RUN');
        $this->finished = $this->makeContract(Contract::FINISHED, 'FIN');

        /** @var User $user */
        $user = User::create([
            'name' => 'Lending Finished Filter Test '.uniqid(),
            'email' => 'lending-finished-'.uniqid().'@example.test',
            'password' => bcrypt('secret-for-tests'),
            'company_id' => $this->company->id,
        ]);
        $user->givePermissionTo([
            'overdraft_assignment_contract.view',
            'overdraft_assignment_contract.update',
        ]);
        $user->companies()->attach($this->company->id);
        $user->load('companies');
        $this->user = $user;
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
        $facility = OverdraftAgainstAssignmentOfContract::withoutEvents(function () {
            return OverdraftAgainstAssignmentOfContract::create([
                'company_id' => $this->company->id,
                'financial_institution_id' => $this->bank->id,
                'account_number' => 'LFF-'.uniqid(),
                'currency' => 'EGP',
                'limit' => 1000,
                'is_active' => 1,
            ]);
        });

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
