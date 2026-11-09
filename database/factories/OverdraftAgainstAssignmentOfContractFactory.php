<?php

namespace Database\Factories;

use App\Models\FinancialInstitution;
use App\Models\OverdraftAgainstAssignmentOfContract;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * An overdraft against assignment of contracts at one of the company's banks.
 *
 * Saved without the model's events: on create the model stores an interest rate read from the
 * current HTTP request, which a factory does not have.
 */
class OverdraftAgainstAssignmentOfContractFactory extends Factory
{
    protected $model = OverdraftAgainstAssignmentOfContract::class;

    public function definition(): array
    {
        return [
            'financial_institution_id' => FinancialInstitutionFactory::new(),
            'company_id' => fn (array $attributes) => FinancialInstitution::find($attributes['financial_institution_id'])?->company_id,
            'account_number' => 'ODAC-'.$this->faker->unique()->numerify('########'),
            'currency' => 'EGP',
            'limit' => 1000000,
            'contract_start_date' => '2020-01-01',
            'contract_end_date' => '2030-12-31',
            'is_active' => 1,
        ];
    }

    public function create($attributes = [], ?Model $parent = null)
    {
        return OverdraftAgainstAssignmentOfContract::withoutEvents(fn () => parent::create($attributes, $parent));
    }

    public function at(FinancialInstitution $bank): static
    {
        return $this->state(fn () => ['financial_institution_id' => $bank->id, 'company_id' => $bank->company_id]);
    }
}
