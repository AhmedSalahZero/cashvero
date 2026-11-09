<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** A branch (cash safe) of one company — table `branch`. */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    public function definition(): array
    {
        return [
            'company_id' => CompanyFactory::new(),
            'name' => 'Test Branch '.$this->faker->unique()->numerify('####'),
            'currency' => 'EGP',
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn () => ['company_id' => $company->id, 'currency' => $company->getMainFunctionalCurrency()]);
    }
}
