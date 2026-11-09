<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Deduction;
use Illuminate\Database\Eloquent\Factories\Factory;

/** A kind of deduction a company takes off invoices (retention, penalty...). */
class DeductionFactory extends Factory
{
    protected $model = Deduction::class;

    public function definition(): array
    {
        return [
            'company_id' => CompanyFactory::new(),
            'name' => 'Test Deduction '.$this->faker->unique()->numerify('####'),
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn () => ['company_id' => $company->id]);
    }
}
