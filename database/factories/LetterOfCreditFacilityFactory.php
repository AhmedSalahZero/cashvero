<?php

namespace Database\Factories;

use App\Models\FinancialInstitution;
use App\Models\LetterOfCreditFacility;
use Illuminate\Database\Eloquent\Factories\Factory;

/** An unsecured LC facility (limit) a company holds at one of its banks. */
class LetterOfCreditFacilityFactory extends Factory
{
    protected $model = LetterOfCreditFacility::class;

    public function definition(): array
    {
        return [
            'financial_institution_id' => FinancialInstitutionFactory::new(),
            'company_id' => fn (array $attributes) => FinancialInstitution::find($attributes['financial_institution_id'])?->company_id,
            'type' => 'unsecured',
            'name' => 'LC Facility '.$this->faker->unique()->numerify('####'),
            'currency' => 'EGP',
            'limit' => 1000000,
            'contract_start_date' => '2020-01-01',
            'contract_end_date' => '2030-12-31',
            'financing_duration' => 90,
        ];
    }

    public function at(FinancialInstitution $bank): static
    {
        return $this->state(fn () => ['financial_institution_id' => $bank->id, 'company_id' => $bank->company_id]);
    }
}
