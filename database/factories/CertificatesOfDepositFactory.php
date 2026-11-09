<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionAccount;
use App\Models\CertificatesOfDeposit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A running certificate of deposit (C/D) at one of the company's banks: 100,000 EGP at 10% for one year,
 * maturing into a current account at the same bank. With no deducted_from_account_id it is an
 * opening-balance deposit (see isOpeningBalance()).
 *
 *   CertificatesOfDepositFactory::new()->at($bank)->maturingInto($account)->deductedFrom($account)->create();
 */
class CertificatesOfDepositFactory extends Factory
{
    protected $model = CertificatesOfDeposit::class;

    public function definition(): array
    {
        return [
            'company_id' => CompanyFactory::new(),
            'financial_institution_id' => fn (array $attributes) => FinancialInstitutionFactory::new()->create(['company_id' => $attributes['company_id']])->id,
            'account_number' => 'CD-'.$this->faker->unique()->numerify('########'),
            'amount' => 100000,
            'currency' => 'EGP',
            'interest_rate' => 10,
            'interest_amount' => 10000,
            'start_date' => '2020-01-01',
            'end_date' => '2020-12-31',
            'status' => CertificatesOfDeposit::RUNNING,
            'maturity_amount_added_to_account_id' => fn (array $attributes) => FinancialInstitutionAccountFactory::new()->create([
                'company_id' => $attributes['company_id'],
                'financial_institution_id' => $attributes['financial_institution_id'],
                'currency' => $attributes['currency'],
            ])->id,
            'deducted_from_account_id' => null,
            'is_at_maturity' => 1,
            'is_active' => 1,
            'is_shareholder_account' => 0,
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn () => ['company_id' => $company->id]);
    }

    public function at(FinancialInstitution $bank): static
    {
        return $this->state(fn () => ['company_id' => $bank->company_id, 'financial_institution_id' => $bank->id]);
    }

    public function currency(string $currency): static
    {
        return $this->state(fn () => ['currency' => strtoupper($currency)]);
    }

    /** The current account the principal and interest go to at maturity. */
    public function maturingInto(FinancialInstitutionAccount $account): static
    {
        return $this->state(fn () => ['maturity_amount_added_to_account_id' => $account->id]);
    }

    /** The current account the deposit was paid from (none = an opening-balance deposit). */
    public function deductedFrom(?FinancialInstitutionAccount $account): static
    {
        return $this->state(fn () => ['deducted_from_account_id' => $account?->id]);
    }

    public function ownedBy(int $shareholderPartnerId): static
    {
        return $this->state(fn () => ['is_shareholder_account' => 1, 'shareholder_partner_id' => $shareholderPartnerId]);
    }
}
