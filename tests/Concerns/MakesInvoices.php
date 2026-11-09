<?php

namespace Tests\Concerns;

use App\Models\Company;
use App\Models\Contract;
use App\Models\CustomerInvoice;
use App\Models\Deduction;
use App\Models\InvoiceDeduction;
use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use App\Models\Partner;
use App\Models\PaymentSettlement;
use App\Models\Settlement;
use App\Models\SupplierInvoice;
use Database\Factories\ContractFactory;
use Database\Factories\CustomerInvoiceFactory;
use Database\Factories\DeductionFactory;
use Database\Factories\InvoiceDeductionFactory;
use Database\Factories\MoneyPaymentFactory;
use Database\Factories\MoneyReceivedFactory;
use Database\Factories\PartnerFactory;
use Database\Factories\PaymentSettlementFactory;
use Database\Factories\SettlementFactory;
use Database\Factories\SupplierInvoiceFactory;

/**
 * Customers, suppliers, their contracts and invoices, and the money that settles them — all
 * made by the test (add `use \Tests\Concerns\MakesInvoices;`).
 *
 *   $customer = $this->customer($company);
 *   $contract = $this->contract($customer, ['amount' => 5000]);
 *   $invoice  = $this->invoice($customer, ['invoice_amount' => 1000, 'vat_amount' => 140], $contract);
 *   $money    = $this->received($customer, ['received_amount' => 600]);
 *   $this->settle($money, $invoice, 600);                       // the triggers update the invoice
 *   $this->deduct($invoice, 100);                               // a deduction off the invoice
 *   $invoice->fresh()->net_balance;                              // 1140 − 600 − 100 = 440
 *
 * The same for the supplier side: supplier(), supplierInvoice(), payment(), settle().
 */
trait MakesInvoices
{
    protected function customer(Company $company, array $overrides = []): Partner
    {
        return PartnerFactory::new()->forCompany($company)->customer()->create($overrides);
    }

    protected function supplier(Company $company, array $overrides = []): Partner
    {
        return PartnerFactory::new()->forCompany($company)->supplier()->create($overrides);
    }

    /** A contract with that partner: a customer contract for a customer, a supplier one otherwise. */
    protected function contract(Partner $partner, array $overrides = []): Contract
    {
        $factory = ContractFactory::new()->forPartner($partner);

        return ($partner->is_customer ? $factory->customer() : $factory->supplier())->create($overrides);
    }

    protected function invoice(Partner $customer, array $overrides = [], ?Contract $contract = null): CustomerInvoice
    {
        $factory = CustomerInvoiceFactory::new()->forCustomer($customer);

        return ($contract ? $factory->onContract($contract) : $factory)->create($overrides)->refresh();
    }

    protected function supplierInvoice(Partner $supplier, array $overrides = [], ?Contract $contract = null): SupplierInvoice
    {
        $factory = SupplierInvoiceFactory::new()->forSupplier($supplier);

        return ($contract ? $factory->onContract($contract) : $factory)->create($overrides)->refresh();
    }

    /** Money received from a customer (cash in safe unless told otherwise). */
    protected function received(Partner $customer, array $overrides = []): MoneyReceived
    {
        return MoneyReceivedFactory::new()->from($customer)->create($overrides);
    }

    /** Money paid to a supplier (cash payment unless told otherwise). */
    protected function payment(Partner $supplier, array $overrides = []): MoneyPayment
    {
        return MoneyPaymentFactory::new()->to($supplier)->create($overrides);
    }

    /** Settle part of an invoice with that money; the triggers write it onto the invoice. */
    protected function settle(MoneyReceived|MoneyPayment $money, CustomerInvoice|SupplierInvoice $invoice, float $amount, float $withhold = 0, array $overrides = []): Settlement|PaymentSettlement
    {
        $factory = $money instanceof MoneyReceived ? SettlementFactory::new() : PaymentSettlementFactory::new();

        return $factory->from($money)->against($invoice)->create([
            'settlement_amount' => $amount,
            'withhold_amount' => $withhold,
        ] + $overrides);
    }

    /** A kind of deduction of that company (retention, penalty...). */
    protected function deductionType(Company $company, array $overrides = []): Deduction
    {
        return DeductionFactory::new()->forCompany($company)->create($overrides);
    }

    /** Take a deduction off an invoice; the triggers write it onto the invoice's total_deductions. */
    protected function deduct(CustomerInvoice|SupplierInvoice $invoice, float $amount, ?Deduction $type = null, string $date = '2020-01-20'): InvoiceDeduction
    {
        $type ??= $this->deductionType(Company::findOrFail($invoice->company_id));

        return InvoiceDeductionFactory::new()->on($invoice)->create([
            'deduction_id' => $type->id,
            'amount' => $amount,
            'date' => $date,
        ]);
    }
}
