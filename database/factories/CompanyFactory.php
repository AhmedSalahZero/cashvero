<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * A company as the app expects one: a name in both languages (Company::getName() reads
 * name[locale]), EGP as its main currency, and no Odoo connection unless asked for.
 */
class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        $name = 'Test Co '.$this->faker->unique()->numerify('####');

        return [
            'name' => ['en' => $name, 'ar' => $name],
            'sub_of' => '0',
            'is_caching_now' => 0,
            'main_functional_currency' => 'EGP',
            'opening_balance_date' => '2020-01-01',
            'odoo_db_url' => null,
            'odoo_db_name' => null,
        ];
    }

    /** Subscribed to the CashVero system — without it every company screen answers 403. */
    public function withCashVero(): static
    {
        return $this->afterCreating(function (Company $company) {
            DB::table('company_systems')->insert([
                'company_id' => $company->id,
                'system_name' => CASH_VERO,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    /** Connected to an Odoo that does not exist (the .invalid domain never resolves). */
    public function withOdoo(): static
    {
        return $this->state(fn () => [
            'odoo_db_url' => 'https://odoo.invalid',
            'odoo_db_name' => 'test',
            'odoo_integration_start_date' => '2020-01-01',
        ]);
    }
}
