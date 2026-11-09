<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A running customer contract of one company. Invoices find their contract by its code
 * (customer_invoices.contract_code), so every contract gets a code of its own.
 *
 *   ContractFactory::new()->forPartner($customer)->create(['amount' => 5000]);
 *   ContractFactory::new()->forPartner($supplier)->supplier()->finished()->create();
 */
class ContractFactory extends Factory
{
    protected $model = Contract::class;

    public function definition(): array
    {
        $tag = $this->faker->unique()->numerify('######');

        return [
            'company_id' => CompanyFactory::new(),
            'partner_id' => fn (array $attributes) => PartnerFactory::new()->customer()->create(['company_id' => $attributes['company_id']])->id,
            'model_type' => 'Customer',
            'status' => Contract::RUNNING,
            'name' => 'Test Contract '.$tag,
            'code' => 'CT-'.$tag,
            'start_date' => '2020-01-01',
            'end_date' => '2020-12-31',
            'amount' => 100000,
            'currency' => 'EGP',
            'exchange_rate' => 1,
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn () => ['company_id' => $company->id]);
    }

    /** That partner's contract, in the partner's company. */
    public function forPartner(Partner $partner): static
    {
        return $this->state(fn () => ['company_id' => $partner->company_id, 'partner_id' => $partner->id]);
    }

    public function customer(): static
    {
        return $this->state(fn () => ['model_type' => 'Customer']);
    }

    public function supplier(): static
    {
        return $this->state(fn () => ['model_type' => 'Supplier']);
    }

    public function status(string $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function finished(): static
    {
        return $this->status(Contract::FINISHED);
    }

    public function runningAndAgainst(): static
    {
        return $this->status(Contract::RUNNING_AND_AGAINST);
    }

    public function currency(string $currency, float $exchangeRate = 1): static
    {
        return $this->state(fn () => ['currency' => strtoupper($currency), 'exchange_rate' => $exchangeRate]);
    }
}
