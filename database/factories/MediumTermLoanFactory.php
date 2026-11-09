<?php

namespace Database\Factories;

use App\Models\FinancialInstitution;
use App\Models\MediumTermLoan;
use Illuminate\Database\Eloquent\Factories\Factory;

/** A running medium-term loan at one of the company's banks, paid back monthly. */
class MediumTermLoanFactory extends Factory
{
    protected $model = MediumTermLoan::class;

    public function definition(): array
    {
        return [
            'financial_institution_id' => FinancialInstitutionFactory::new(),
            'company_id' => fn (array $attributes) => FinancialInstitution::find($attributes['financial_institution_id'])?->company_id,
            'name' => 'Test Loan '.$this->faker->unique()->numerify('#####'),
            'account_number' => 'MTL-'.$this->faker->unique()->numerify('########'),
            'currency' => 'EGP',
            'limit' => 1000000,
            'start_date' => '2020-01-01',
            'end_date' => '2025-12-31',
            'duration' => 12,
            'installment_payment_interval' => 'monthly',
            'status' => MediumTermLoan::RUNNING,
        ];
    }

    public function at(FinancialInstitution $bank): static
    {
        return $this->state(fn () => ['financial_institution_id' => $bank->id, 'company_id' => $bank->company_id]);
    }
}
