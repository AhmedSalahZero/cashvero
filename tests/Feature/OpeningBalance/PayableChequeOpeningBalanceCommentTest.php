<?php

namespace Tests\Feature\OpeningBalance;

use App\Models\MoneyPayment;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The bank statement reads the comment stored on the money payment.
 * Changing the supplier on an opening-balance payable cheque has to
 * rewrite that comment.
 */
class PayableChequeOpeningBalanceCommentTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUpTraits()
    {
        config(['database.connections.mysql.database' => env('SMOKE_DB', 'cashvero')]);
        DB::purge('mysql');

        return parent::setUpTraits();
    }

    public function test_changing_the_supplier_rewrites_the_statement_comment(): void
    {
        $template = DB::table('companies')->whereNull('deleted_at')->orderBy('id')->first();
        if (! $template) {
            $this->markTestSkipped('No company row to copy a column shape from.');
        }

        $company = (array) $template;
        unset($company['id']);
        $company['name'] = json_encode(['en' => 'Comment Source', 'ar' => 'Comment Source']);
        $company['is_caching_now'] = 0;
        $company['deleted_at'] = null;
        $companyId = (int) DB::table('companies')->insertGetId($company);

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
        return (int) DB::table('partners')->insertGetId([
            'company_id' => $companyId,
            'name' => $name,
            'is_supplier' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
