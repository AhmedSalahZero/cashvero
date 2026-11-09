<?php

namespace Database\Factories;

use App\Models\LeasingCompany;
use App\Models\LeasingContract;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A running leasing contract: 1,000,000 EGP limit, 10% + 2% margin, 36 monthly installments,
 * started two months ago and running three more years. Saved through the model.
 *
 *   LeasingContractFactory::new()->of($leasingCompany)->create(['limit' => 250000]);
 */
class LeasingContractFactory extends Factory
{
    protected $model = LeasingContract::class;

    public function definition(): array
    {
        return [
            'leasing_company_id' => LeasingCompanyFactory::new(),
            'company_id' => fn (array $attributes) => LeasingCompany::find($attributes['leasing_company_id'])?->company_id,
            'status' => LeasingContract::RUNNING,
            'name' => 'TEST-LC-'.$this->faker->unique()->numerify('######'),
            'start_date' => now()->subMonths(2)->format('Y-m-d'),
            'end_date' => now()->addYears(3)->format('Y-m-d'),
            'currency' => 'EGP',
            'limit' => 1000000,
            'borrowing_rate' => 10,
            'margin_rate' => 2,
            'duration' => 36,
            'installment_payment_interval' => 'monthly',
        ];
    }

    /** A contract with that leasing company (and so of the company it belongs to). */
    public function of(LeasingCompany $leasingCompany): static
    {
        return $this->state(fn () => ['leasing_company_id' => $leasingCompany->id, 'company_id' => $leasingCompany->company_id]);
    }

    public function closed(): static
    {
        return $this->state(fn () => ['status' => 'closed']);
    }
}
