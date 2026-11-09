<?php

namespace Database\Factories;

use App\Models\CleanOverdraft;
use App\Models\FinancialInstitution;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * A clean overdraft (no collateral) at one of the company's banks, running from 2020 to 2030.
 *
 * Saved without the model's events: on create the model stores an interest rate read from the
 * current HTTP request, which a factory does not have. Give it a rate with ->withRate().
 */
class CleanOverdraftFactory extends Factory
{
    protected $model = CleanOverdraft::class;

    public function definition(): array
    {
        return [
            'financial_institution_id' => FinancialInstitutionFactory::new(),
            'company_id' => fn (array $attributes) => FinancialInstitution::find($attributes['financial_institution_id'])?->company_id,
            'account_number' => 'OD-'.$this->faker->unique()->numerify('########'),
            'currency' => 'EGP',
            'limit' => 1000000,
            'contract_start_date' => '2020-01-01',
            'contract_end_date' => '2030-12-31',
            'is_active' => 1,
        ];
    }

    public function create($attributes = [], ?Model $parent = null)
    {
        return CleanOverdraft::withoutEvents(fn () => parent::create($attributes, $parent));
    }

    public function at(FinancialInstitution $bank): static
    {
        return $this->state(fn () => ['financial_institution_id' => $bank->id, 'company_id' => $bank->company_id]);
    }

    /** An interest rate (percent a year) from that date on. */
    public function withRate(float $interestRate, ?string $date = null, float $minInterestRate = 0): static
    {
        return $this->afterCreating(function (CleanOverdraft $overdraft) use ($interestRate, $date, $minInterestRate) {
            DB::table('clean_overdraft_rates')->insert([
                'company_id' => $overdraft->company_id,
                'clean_overdraft_id' => $overdraft->id,
                'date' => $date ?? $overdraft->contract_start_date,
                'interest_rate' => $interestRate,
                'min_interest_rate' => $minInterestRate,
            ]);
        });
    }
}
