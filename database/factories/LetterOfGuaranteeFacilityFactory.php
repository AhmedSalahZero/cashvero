<?php

namespace Database\Factories;

use App\Models\FinancialInstitution;
use App\Models\LetterOfGuaranteeFacility;
use Illuminate\Database\Eloquent\Factories\Factory;

/** An LG facility (limit) a company holds at one of its banks. */
class LetterOfGuaranteeFacilityFactory extends Factory
{
    protected $model = LetterOfGuaranteeFacility::class;

    public function definition(): array
    {
        return [
            'financial_institution_id' => FinancialInstitutionFactory::new(),
            'company_id' => fn (array $attributes) => FinancialInstitution::find($attributes['financial_institution_id'])?->company_id,
            'name' => 'LG Facility '.$this->faker->unique()->numerify('####'),
            'currency' => 'EGP',
            'limit' => 1000000,
            'contract_start_date' => '2020-01-01',
            'contract_end_date' => '2030-12-31',
            'outstanding_date' => '2020-01-01',
            'outstanding_amount' => 0,
        ];
    }

    public function at(FinancialInstitution $bank): static
    {
        return $this->state(fn () => ['financial_institution_id' => $bank->id, 'company_id' => $bank->company_id]);
    }
}
