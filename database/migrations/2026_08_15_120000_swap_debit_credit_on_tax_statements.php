<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bug fix (client-flagged, confirmed 2026-08-15): HasPartnerStatement::
 * handlePartnerDebitStatement() recorded every tax/insurance PAYMENT as a
 * debit, using the same logic written for Employee/Shareholder/Subsidiary/
 * Other Partner — where a payment out is correctly a debit (it's money
 * those partners now owe back to the company). Taxes & Insurance is the
 * opposite: the company owes THEM, so a payment should be a credit
 * (shrinking that payable), not a debit. That code is now fixed (see
 * HasPartnerStatement.php), but every tax_statements row created before
 * this fix still has the amount sitting in the wrong column.
 *
 * Since is_tax was never handled in handlePartnerCreditStatement() at all
 * (money "received" from a tax/insurance partner was never recorded),
 * every existing row is safely known to be a payment — debit > 0,
 * credit = 0 — so this is an unconditional swap, not a conditional one.
 *
 * This only swaps the debit/credit VALUES. beginning_balance/end_balance/
 * is_debit/is_credit are derived from those columns by the
 * before_insert_tax_statements / before_update_tax_statements triggers,
 * which only run on INSERT/UPDATE — a raw column swap here does not
 * re-trigger them. Run this immediately followed by:
 *   php artisan statements:repair-balances --table=tax_statements --fix
 * to rebuild the running balance chain from the corrected values.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->swap();
    }

    public function down(): void
    {
        $this->swap();
    }

    /**
     * A single `SET debit = credit, credit = debit` is NOT a safe swap in
     * MySQL — assignments are evaluated left to right, so by the time
     * `credit = debit` runs, `debit` already holds the new value from the
     * first assignment, leaving both columns equal to the original
     * `credit`. Doing this row-by-row in PHP instead, using each row's
     * own original values captured before either column is touched, so
     * there's no ambiguity about evaluation order.
     */
    private function swap(): void
    {
        DB::table('tax_statements')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('tax_statements')
                    ->where('id', $row->id)
                    ->update([
                        'debit' => $row->credit,
                        'credit' => $row->debit,
                    ]);
            }
        });
    }
};
