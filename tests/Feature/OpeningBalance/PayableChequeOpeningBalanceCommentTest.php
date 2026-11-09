<?php

namespace Tests\Feature\OpeningBalance;

use App\Models\MoneyPayment;
use Database\Factories\PartnerFactory;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The bank statement reads the comment stored on the money payment.
 * Changing the supplier on an opening-balance payable cheque has to
 * rewrite that comment.
 *
 * The company, suppliers and opening balance are made by the test and rolled back afterwards.
 */
class PayableChequeOpeningBalanceCommentTest extends TestCase
{
    public function test_changing_the_supplier_rewrites_the_statement_comment(): void
    {
        $companyId = $this->makeCompany(['name' => ['en' => 'Comment Source', 'ar' => 'Comment Source']])->id;

        $oldSupplierId = $this->supplier($companyId, 'Old Supplier Name');
        $newSupplierId = $this->supplier($companyId, 'New Supplier Name');
        $openingBalanceId = (int) DB::table('opening_balances')->insertGetId([
            'company_id' => $companyId,
            'date' => '2026-01-01',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payment = MoneyPayment::create([
            'type' => MoneyPayment::PAYABLE_CHEQUE,
            'partner_id' => $oldSupplierId,
            'opening_balance_id' => $openingBalanceId,
            'company_id' => $companyId,
            'paid_amount' => 1000,
            'currency' => 'EGP',
            'exchange_rate' => 1,
            'delivery_date' => '2026-01-01',
        ]);

        $this->assertStringContainsString('Old Supplier Name', $payment->comment_en);
        $this->assertStringContainsString('Old Supplier Name', $payment->comment_ar);

        $payment->update(['partner_id' => $newSupplierId]);
        $payment->rewriteCommentFromCurrentSupplier();
        $payment->refresh();

        $this->assertStringContainsString('New Supplier Name', $payment->comment_en);
        $this->assertStringContainsString('New Supplier Name', $payment->comment_ar);
        $this->assertStringNotContainsString('Old Supplier Name', $payment->comment_en);
        $this->assertStringNotContainsString('Old Supplier Name', $payment->comment_ar);
    }

    private function supplier(int $companyId, string $name): int
    {
        return PartnerFactory::new()->supplier()->create(['company_id' => $companyId, 'name' => $name])->id;
    }
}
