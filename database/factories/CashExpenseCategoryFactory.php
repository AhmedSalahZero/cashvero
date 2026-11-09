<?php

namespace Database\Factories;

use App\Models\CashExpenseCategory;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** A main expense category of one company (its expense names hang under it). */
class CashExpenseCategoryFactory extends Factory
{
    protected $model = CashExpenseCategory::class;

    public function definition(): array
    {
        return [
            'company_id' => CompanyFactory::new(),
            'name' => 'Test Category '.$this->faker->unique()->numerify('####'),
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn () => ['company_id' => $company->id]);
    }
}
