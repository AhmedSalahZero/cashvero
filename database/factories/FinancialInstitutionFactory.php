<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\FinancialInstitution;
use Illuminate\Database\Eloquent\Factories\Factory;

/** A company's bank (financial_institutions of type "bank"), backed by a row in banks. */
class FinancialInstitutionFactory extends Factory
{
    protected $model = FinancialInstitution::class;

    public function definition(): array
    {
        return [
            'type' => FinancialInstitution::BANK,
            'bank_id' => BankFactory::new(),
            'company_id' => CompanyFactory::new(),
            'branch_name' => 'Main',
            'company_account_number' => $this->faker->numerify('########'),
            'created_by' => 0,
            'updated_by' => 0,
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn () => ['company_id' => $company->id]);
    }
}
