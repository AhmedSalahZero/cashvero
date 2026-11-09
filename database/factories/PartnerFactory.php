<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/** A customer, supplier, shareholder, employee... of one company. */
class PartnerFactory extends Factory
{
    protected $model = Partner::class;

    public function definition(): array
    {
        return [
            'company_id' => CompanyFactory::new(),
            'name' => 'Test Partner '.$this->faker->unique()->numerify('#####'),
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn () => ['company_id' => $company->id]);
    }

    public function customer(): static
    {
        return $this->state(fn () => ['is_customer' => 1]);
    }

    public function supplier(): static
    {
        return $this->state(fn () => ['is_supplier' => 1]);
    }

    public function shareholder(): static
    {
        return $this->state(fn () => ['is_shareholder' => 1]);
    }

    public function employee(): static
    {
        return $this->state(fn () => ['is_employee' => 1]);
    }
}
