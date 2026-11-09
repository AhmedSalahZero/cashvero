<?php

namespace Database\Factories;

use App\Models\MoneyPayment;
use App\Models\PaymentSettlement;
use App\Models\SupplierInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Part of a supplier invoice settled by a money payment. The payment_settlements triggers
 * write the sum back onto the invoice (paid_amount / withhold_amount), and the invoice
 * triggers then recompute its net_balance — read the invoice back with ->fresh().
 *
 *   PaymentSettlementFactory::new()->from($moneyPayment)->against($supplierInvoice)->create(['settlement_amount' => 400]);
 */
class PaymentSettlementFactory extends Factory
{
    protected $model = PaymentSettlement::class;

    public function definition(): array
    {
        return [
            'invoice_id' => SupplierInvoiceFactory::new(),
            'company_id' => fn (array $attributes) => SupplierInvoice::find($attributes['invoice_id'])?->company_id,
            'partner_id' => fn (array $attributes) => SupplierInvoice::find($attributes['invoice_id'])?->supplier_id ?? 0,
            'money_payment_id' => fn (array $attributes) => MoneyPaymentFactory::new()->create([
                'company_id' => $attributes['company_id'],
                'partner_id' => $attributes['partner_id'],
            ])->id,
            'settlement_amount' => 100,
            'withhold_amount' => 0,
            'is_from_down_payment' => 0,
            // NOT NULL without a default; 0 = "not settled through a letter of credit".
            'letter_of_credit_issuance_id' => 0,
        ];
    }

    public function against(SupplierInvoice $invoice): static
    {
        return $this->state(fn () => [
            'invoice_id' => $invoice->id,
            'company_id' => $invoice->company_id,
            'partner_id' => $invoice->supplier_id,
        ]);
    }

    public function from(MoneyPayment $moneyPayment): static
    {
        return $this->state(fn () => [
            'money_payment_id' => $moneyPayment->id,
            'company_id' => $moneyPayment->company_id,
            'partner_id' => $moneyPayment->partner_id,
        ]);
    }
}
