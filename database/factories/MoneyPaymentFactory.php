<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\MoneyPayment;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Money paid to a supplier. Saved through the model, so it gets its comment and its serial
 * number (HasSerial) exactly like a payment entered on the screen. No bank or safe movement
 * is written — that is the controller's job, not the model's.
 */
class MoneyPaymentFactory extends Factory
{
    protected $model = MoneyPayment::class;

    public function definition(): array
    {
        return [
            'company_id' => CompanyFactory::new(),
            'partner_id' => fn (array $attributes) => PartnerFactory::new()->supplier()->create(['company_id' => $attributes['company_id']])->id,
            'partner_type' => 'is_supplier',
            'type' => MoneyPayment::CASH_PAYMENT,
            'money_type' => 'invoice-settlement',
            'delivery_date' => '2020-01-15',
            'paid_amount' => 1000,
            'currency' => 'EGP',
            'payment_currency' => 'EGP',
            'exchange_rate' => 1,
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn () => ['company_id' => $company->id]);
    }

    /** Paid to that partner, in the partner's company. */
    public function to(Partner $partner): static
    {
        return $this->state(fn () => ['company_id' => $partner->company_id, 'partner_id' => $partner->id]);
    }

    public function cashPayment(): static
    {
        return $this->state(fn () => ['type' => MoneyPayment::CASH_PAYMENT]);
    }

    /** A down payment: money paid ahead of any invoice, settled against invoices later. */
    public function downPayment(): static
    {
        return $this->state(fn () => [
            'money_type' => MoneyPayment::DOWN_PAYMENT,
            'down_payment_type' => MoneyPayment::DOWN_PAYMENT_GENERAL,
            // Read when the row is made, so an amount given to create() is the one used.
            'amount_in_invoice_currency' => fn (array $attributes) => $attributes['paid_amount'],
        ]);
    }
}
