<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\LeasingCompany;
use Illuminate\Database\Eloquent\Factories\Factory;

/** A leasing company one company works with (the lessor of its leasing contracts). */
class LeasingCompanyFactory extends Factory
{
    protected $model = LeasingCompany::class;

    public function definition(): array
    {
        return [
            'company_id' => CompanyFactory::new(),
            'name' => 'Test Leasing Co '.$this->faker->unique()->numerify('#####'),
            'branch_name' => null,
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn () => ['company_id' => $company->id]);
    }
}
