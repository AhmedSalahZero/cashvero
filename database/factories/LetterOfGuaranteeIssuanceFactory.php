<?php

namespace Database\Factories;

use App\Models\FinancialInstitution;
use App\Models\LetterOfGuaranteeFacility;
use App\Models\LetterOfGuaranteeIssuance;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A running bid-bond LG issued at one of the company's banks to a customer of that company.
 *
 * Saved through the model, so it gets a serial number and is_activated is worked out from
 * activation_date exactly like an LG entered on the screen. Nothing else happens on save: the
 * LG, cash cover and current account statement rows are written by the screen's controller,
 * not by the model — a test that needs them inserts them itself.
 *
 *   LetterOfGuaranteeIssuanceFactory::new()->at($bank)->to($customer)->create(['lg_amount' => 5000]);
 *   LetterOfGuaranteeIssuanceFactory::new()->at($bank)->underFacility($facility)->create();
 */
class LetterOfGuaranteeIssuanceFactory extends Factory
{
    protected $model = LetterOfGuaranteeIssuance::class;

    public function definition(): array
    {
        return [
            'financial_institution_id' => FinancialInstitutionFactory::new(),
            'company_id' => fn (array $attributes) => FinancialInstitution::find($attributes['financial_institution_id'])?->company_id,
            'partner_id' => fn (array $attributes) => PartnerFactory::new()->customer()->create(['company_id' => $attributes['company_id']])->id,
            'category_name' => LetterOfGuaranteeIssuance::NEW_ISSUANCE,
            'source' => LetterOfGuaranteeIssuance::LG_FACILITY,
            'status' => LetterOfGuaranteeIssuance::RUNNING,
            'lg_type' => 'bid-bond',
            'lg_code' => 'LG-'.$this->faker->unique()->numerify('######'),
            'transaction_name' => 'Test LG transaction',
            'lg_amount' => 100000,
            'lg_currency' => 'EGP',
            'cash_cover_rate' => 10,
            'cash_cover_amount' => 10000,
            'issuance_date' => '2020-01-15',
            'transaction_date' => '2020-01-15',
            'lg_duration_months' => 12,
            'renewal_date' => '2021-01-15',
        ];
    }

    public function at(FinancialInstitution $bank): static
    {
        return $this->state(fn () => ['financial_institution_id' => $bank->id, 'company_id' => $bank->company_id]);
    }

    public function to(Partner $beneficiary): static
    {
        return $this->state(fn () => ['partner_id' => $beneficiary->id]);
    }

    public function underFacility(LetterOfGuaranteeFacility $facility): static
    {
        return $this->state(fn () => [
            'lg_facility_id' => $facility->id,
            'financial_institution_id' => $facility->financial_institution_id,
            'company_id' => $facility->company_id,
            'source' => LetterOfGuaranteeIssuance::LG_FACILITY,
            'lg_currency' => $facility->currency,
        ]);
    }

    public function cancelled(string $date = '2020-06-30'): static
    {
        return $this->state(fn () => ['status' => LetterOfGuaranteeIssuance::CANCELLED, 'cancellation_date' => $date]);
    }
}
