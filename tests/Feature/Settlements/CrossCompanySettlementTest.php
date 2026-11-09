<?php

namespace Tests\Feature\Settlements;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\MakesInvoices;
use Tests\TestCase;

/**
 * Money of one company can only settle that company's own invoices. Before this was enforced, a
 * settlement row naming another company's invoice id was stored, and quietly reduced that other
 * company's balance.
 *
 * Run: php artisan test --filter=CrossCompanySettlementTest
 */
class CrossCompanySettlementTest extends TestCase
{
    use MakesInvoices;

    public function test_a_receipt_settles_an_invoice_of_its_own_company(): void
    {
        $company = $this->makeCompany();
        $customer = $this->customer($company);
        $invoice = $this->invoice($customer, ['invoice_amount' => 1000, 'net_invoice_amount' => 1000]);
        $money = $this->received($customer, ['received_amount' => 400]);

        $money->storeNewSettlement([['invoice_id' => $invoice->id, 'settlement_amount' => 400]], $customer->id, $company, syncWithOdoo: false);

        $this->assertSame(1, DB::table('settlements')->where('invoice_id', $invoice->id)->count());
    }

    public function test_a_receipt_cannot_settle_another_companys_invoice(): void
    {
        $company = $this->makeCompany();
        $customer = $this->customer($company);
        $money = $this->received($customer, ['received_amount' => 400]);

        $otherCompany = $this->makeCompany();
        $theirInvoice = $this->invoice($this->customer($otherCompany), ['invoice_amount' => 1000, 'net_invoice_amount' => 1000]);
        $theirBalance = (float) $theirInvoice->fresh()->net_balance;

        try {
            $money->storeNewSettlement([['invoice_id' => $theirInvoice->id, 'settlement_amount' => 400]], $customer->id, $company, syncWithOdoo: false);
            $this->fail('A settlement on another company\'s invoice was accepted.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('does not exist', $e->getMessage());
        }

        $this->assertSame(0, DB::table('settlements')->where('invoice_id', $theirInvoice->id)->count());
        $this->assertEqualsWithDelta($theirBalance, (float) $theirInvoice->fresh()->net_balance, 0.001);
    }

    public function test_a_payment_cannot_settle_another_companys_supplier_invoice(): void
    {
        $company = $this->makeCompany();
        $supplier = $this->supplier($company);
        $payment = $this->payment($supplier, ['paid_amount' => 300]);

        $otherCompany = $this->makeCompany();
        $theirInvoice = $this->supplierInvoice($this->supplier($otherCompany), ['invoice_amount' => 900, 'net_invoice_amount' => 900]);

        $this->expectException(\RuntimeException::class);

        $payment->storeNewSettlement([['invoice_id' => $theirInvoice->id, 'settlement_amount' => 300]], $supplier->id, $company, syncWithOdoo: false);
    }
}
