<?php

namespace Database\Factories;

use App\Models\CustomerInvoice;
use App\Models\InvoiceDeduction;
use App\Models\SupplierInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A deduction taken off one invoice. The invoice_deductions triggers write the sum onto the
 * invoice's total_deductions, and the invoice triggers then take it off net_balance — read
 * the invoice back with ->fresh().
 *
 *   InvoiceDeductionFactory::new()->on($invoice)->create(['amount' => 250]);
 */
class InvoiceDeductionFactory extends Factory
{
    protected $model = InvoiceDeduction::class;

    public function definition(): array
    {
        return [
            'invoice_type' => 'CustomerInvoice',
            'invoice_id' => CustomerInvoiceFactory::new(),
            'company_id' => fn (array $attributes) => ('App\\Models\\'.$attributes['invoice_type'])::find($attributes['invoice_id'])?->company_id,
            'deduction_id' => fn (array $attributes) => DeductionFactory::new()->create(['company_id' => $attributes['company_id']])->id,
            'amount' => 100,
            'date' => '2020-01-20',
        ];
    }

    public function on(CustomerInvoice|SupplierInvoice $invoice): static
    {
        return $this->state(fn () => [
            'invoice_type' => class_basename($invoice),
            'invoice_id' => $invoice->id,
            'company_id' => $invoice->company_id,
        ]);
    }
}
