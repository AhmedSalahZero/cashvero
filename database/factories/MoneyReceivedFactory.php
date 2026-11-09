<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\MoneyReceived;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Money received from a customer. Saved through the model, so it gets its comment and its
 * serial number (HasSerial) exactly like a receipt entered on the screen.
 */
class MoneyReceivedFactory extends Factory
{
    protected $model = MoneyReceived::class;

    public function definition(): array
    {
        return [
            'company_id' => CompanyFactory::new(),
            'partner_id' => fn (array $attributes) => PartnerFactory::new()->customer()->create(['company_id' => $attributes['company_id']])->id,
            'partner_type' => 'is_customer',
            'type' => MoneyReceived::CASH_IN_SAFE,
            'money_type' => 'invoice-settlement',
            'receiving_date' => '2020-01-15',
            'received_amount' => 1000,
            'currency' => 'EGP',
            'receiving_currency' => 'EGP',
            'exchange_rate' => 1,
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn () => ['company_id' => $company->id]);
    }

    public function cashInSafe(): static
    {
        return $this->state(fn () => ['type' => MoneyReceived::CASH_IN_SAFE]);
    }

    /** Received from that partner, in the partner's company. */
    public function from(Partner $partner): static
    {
        return $this->state(fn () => ['company_id' => $partner->company_id, 'partner_id' => $partner->id]);
    }

    /** A down payment: money received ahead of any invoice, settled against invoices later. */
    public function downPayment(): static
    {
        return $this->state(fn () => [
            'money_type' => MoneyReceived::DOWN_PAYMENT,
            'down_payment_type' => MoneyReceived::DOWN_PAYMENT_GENERAL,
            // Read when the row is made, so an amount given to create() is the one used.
            'amount_in_invoice_currency' => fn (array $attributes) => $attributes['received_amount'],
        ]);
    }
}
