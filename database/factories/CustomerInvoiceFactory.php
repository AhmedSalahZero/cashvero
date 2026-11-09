<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Contract;
use App\Models\CustomerInvoice;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A customer invoice of one company. Saved through the model, and the customer_invoices
 * triggers fill in everything derived: net_invoice_amount (amount + VAT − discount),
 * net_balance, invoice_status, the *_in_main_currency columns, invoice_month/year.
 *
 *   CustomerInvoiceFactory::new()->forCustomer($customer)->create(['invoice_amount' => 1000, 'vat_amount' => 140]);
 *   CustomerInvoiceFactory::new()->forCustomer($customer)->onContract($contract)->currency('USD', 50)->create();
 */
class CustomerInvoiceFactory extends Factory
{
    protected $model = CustomerInvoice::class;

    public function definition(): array
    {
        return [
            'company_id' => CompanyFactory::new(),
            'customer_id' => fn (array $attributes) => PartnerFactory::new()->customer()->create(['company_id' => $attributes['company_id']])->id,
            'customer_name' => fn (array $attributes) => Partner::find($attributes['customer_id'])?->name,
            'invoice_number' => 'INV-'.$this->faker->unique()->numerify('######'),
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

    /** The invoice of that customer, in the customer's company. */
    public function forCustomer(Partner $customer): static
    {
        return $this->state(fn () => [
            'company_id' => $customer->company_id,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
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
