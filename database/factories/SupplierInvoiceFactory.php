<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Contract;
use App\Models\SupplierInvoice;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A supplier invoice of one company. Saved through the model, and the supplier_invoices
 * triggers fill in everything derived: net_invoice_amount (amount + VAT − discount),
 * net_balance, invoice_status, the *_in_main_currency columns, invoice_month/year.
 *
 *   SupplierInvoiceFactory::new()->forSupplier($supplier)->create(['invoice_amount' => 1000, 'vat_amount' => 140]);
 *   SupplierInvoiceFactory::new()->forSupplier($supplier)->onContract($contract)->currency('USD', 50)->create();
 */
class SupplierInvoiceFactory extends Factory
{
    protected $model = SupplierInvoice::class;

    public function definition(): array
    {
        return [
            'company_id' => CompanyFactory::new(),
            'supplier_id' => fn (array $attributes) => PartnerFactory::new()->supplier()->create(['company_id' => $attributes['company_id']])->id,
            'supplier_name' => fn (array $attributes) => Partner::find($attributes['supplier_id'])?->name,
            'invoice_number' => 'SINV-'.$this->faker->unique()->numerify('######'),
            'invoice_date' => '2020-01-15',
            'invoice_due_date' => '2020-03-15',
            'invoice_amount' => 1000,
            'vat_amount' => 0,
            'currency' => 'EGP',
            'exchange_rate' => 1,
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn () => ['company_id' => $company->id]);
    }

    /** The invoice of that supplier, in the supplier's company. */
    public function forSupplier(Partner $supplier): static
    {
        return $this->state(fn () => [
            'company_id' => $supplier->company_id,
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
        ]);
    }

    /** Linked to a contract the way the app links them: by contract code. */
    public function onContract(Contract $contract): static
    {
        return $this->state(fn () => [
            'contract_code' => $contract->code,
            'contract_name' => $contract->name,
            'contract_amount' => $contract->amount,
        ]);
    }

    public function currency(string $currency, float $exchangeRate = 1): static
    {
        return $this->state(fn () => ['currency' => strtoupper($currency), 'exchange_rate' => $exchangeRate]);
    }
}
