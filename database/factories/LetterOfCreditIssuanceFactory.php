<?php

namespace Database\Factories;

use App\Models\FinancialInstitution;
use App\Models\LetterOfCreditFacility;
use App\Models\LetterOfCreditIssuance;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A running sight LC issued at one of the company's banks for a supplier of that company.
 *
 * Saved through the model (serial number included). No statement rows are written on save —
 * the screen's controller writes them, so a test that needs them inserts them itself.
 *
 *   LetterOfCreditIssuanceFactory::new()->at($bank)->forSupplier($supplier)->create(['lc_amount' => 5000]);
 */
class LetterOfCreditIssuanceFactory extends Factory
{
    protected $model = LetterOfCreditIssuance::class;

    public function definition(): array
    {
        return [
            'financial_institution_id' => FinancialInstitutionFactory::new(),
            'company_id' => fn (array $attributes) => FinancialInstitution::find($attributes['financial_institution_id'])?->company_id,
            'partner_id' => fn (array $attributes) => PartnerFactory::new()->supplier()->create(['company_id' => $attributes['company_id']])->id,
            'category_name' => LetterOfCreditIssuance::NEW_ISSUANCE,
            'source' => LetterOfCreditIssuance::LC_FACILITY,
            'status' => LetterOfCreditIssuance::RUNNING,
            'lc_type' => 'sight-lc',
            'lc_code' => 'LC-'.$this->faker->unique()->numerify('######'),
            'transaction_name' => 'Test LC transaction',
            'lc_amount' => 100000,
            'lc_currency' => 'EGP',
            'lc_cash_cover_currency' => 'EGP',
            'cash_cover_rate' => 10,
            'cash_cover_amount' => 10000,
            'exchange_rate' => 1,
            'issuance_date' => '2020-01-15',
            'transaction_date' => '2020-01-15',
            'lc_duration_days' => 90,
            'due_date' => '2020-04-14',
        ];
    }

    public function at(FinancialInstitution $bank): static
    {
        return $this->state(fn () => ['financial_institution_id' => $bank->id, 'company_id' => $bank->company_id]);
    }

    public function forSupplier(Partner $supplier): static
    {
        return $this->state(fn () => ['partner_id' => $supplier->id]);
    }

    public function underFacility(LetterOfCreditFacility $facility): static
    {
        return $this->state(fn () => [
            'lc_facility_id' => $facility->id,
            'financial_institution_id' => $facility->financial_institution_id,
            'company_id' => $facility->company_id,
            'source' => LetterOfCreditIssuance::LC_FACILITY,
            'lc_currency' => $facility->currency,
        ]);
    }
}
