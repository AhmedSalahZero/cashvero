<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records the INTEREST half of every installment repayment on the loan's
 * own statement, next to the principle half that already lives in `debit`.
 *
 * Why (project owner, 2026-08-16): "لازم نفصل بين الفايدة اللي انا كاتبها
 * والفايدة اللي انا دافعها" — the interest a schedule SAYS is due is a
 * different number from the interest actually PAID so far, and the loan's
 * statement has to show both sides.
 *
 * Deliberately NOT part of the balance formula: `end_balance` stays
 * `beginning_balance + debit - credit`, so paying interest never moves the
 * drawn balance or frees up room — only principle does. This column is a
 * record of where the money went, not a ledger movement.
 *
 * (Same column name the overdraft statement tables use, but a different
 * meaning: there it is interest ACCRUED by the facility; here it is
 * interest PAID by the company. An MTL never accrues interest of its own —
 * the installment already bundles it.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medium_term_loan_bank_statements', function (Blueprint $table) {
            $table->decimal('interest_amount', 14)->default(0)->after('credit');
        });
    }

    public function down(): void
    {
        Schema::table('medium_term_loan_bank_statements', function (Blueprint $table) {
            $table->dropColumn('interest_amount');
        });
    }
};
