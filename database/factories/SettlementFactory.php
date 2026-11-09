<?php

namespace Database\Factories;

use App\Models\CustomerInvoice;
use App\Models\MoneyReceived;
use App\Models\Settlement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Part of a customer invoice settled by money received. The settlements triggers write the
 * sum back onto the invoice (collected_amount / withhold_amount), and the invoice triggers
 * then recompute its net_balance and invoice_status — read the invoice back with ->fresh().
 *
 *   SettlementFactory::new()->from($moneyReceived)->against($invoice)->create(['settlement_amount' => 400]);
 */
class SettlementFactory extends Factory
{
    protected $model = Settlement::class;

    public function definition(): array
    {
        return [
            'invoice_id' => CustomerInvoiceFactory::new(),
            'company_id' => fn (array $attributes) => CustomerInvoice::find($attributes['invoice_id'])?->company_id,
            'partner_id' => fn (array $attributes) => CustomerInvoice::find($attributes['invoice_id'])?->customer_id ?? 0,
            'money_received_id' => fn (array $attributes) => MoneyReceivedFactory::new()->create([
                'company_id' => $attributes['company_id'],
                'partner_id' => $attributes['partner_id'],
            ])->id,
            'settlement_amount' => 100,
            'withhold_amount' => 0,
            'is_from_down_payment' => 0,
        ];
    }

    public function against(CustomerInvoice $invoice): static
    {
        return $this->state(fn () => [
            'invoice_id' => $invoice->id,
            'company_id' => $invoice->company_id,
            'partner_id' => $invoice->customer_id,
        ]);
    }

    public function from(MoneyReceived $moneyReceived): static
    {
        return $this->state(fn () => [
            'money_received_id' => $moneyReceived->id,
            'company_id' => $moneyReceived->company_id,
            'partner_id' => $moneyReceived->partner_id,
        ]);
    }
}
