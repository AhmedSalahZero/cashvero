<?php

namespace Database\Factories;

use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/** A current account at one of the company's banks. */
class FinancialInstitutionAccountFactory extends Factory
{
    protected $model = FinancialInstitutionAccount::class;

    public function definition(): array
    {
        return [
            'financial_institution_id' => FinancialInstitutionFactory::new(),
            'company_id' => fn (array $attributes) => FinancialInstitution::find($attributes['financial_institution_id'])?->company_id,
            'account_number' => 'ACC-'.$this->faker->unique()->numerify('########'),
            'currency' => 'EGP',
            'exchange_rate' => 1,
            'balance_amount' => 0,
            'balance_date' => '2020-01-01',
            'is_active' => 1,
            'is_shareholder_account' => 0,
        ];
    }

    public function at(FinancialInstitution $bank): static
    {
        return $this->state(fn () => ['financial_institution_id' => $bank->id, 'company_id' => $bank->company_id]);
    }

    public function currency(string $currency): static
    {
        return $this->state(fn () => ['currency' => strtoupper($currency)]);
    }

    public function ownedBy(int $shareholderPartnerId): static
    {
        return $this->state(fn () => ['is_shareholder_account' => 1, 'shareholder_partner_id' => $shareholderPartnerId]);
    }
}
