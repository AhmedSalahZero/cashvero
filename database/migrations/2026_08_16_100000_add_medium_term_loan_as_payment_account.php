<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes a Medium Term Loan usable as a SOURCE OF PAYMENT, not just a
 * repayment schedule.
 *
 * Business shape (confirmed with the project owner, 2026-08-16):
 *
 *   - A company takes a 1,000,000 MTL from the bank. That money is not
 *     handed over as cash — the bank pays the company's suppliers
 *     directly out of it. So on the Money Payment screen the user must
 *     be able to pick the LOAN itself as the paying account, exactly
 *     like they already pick a Clean Overdraft.
 *
 *   - Only a loan that has NOT been consumed yet can be paid from.
 *     A loan the company already drew and spent BEFORE joining
 *     CashVero (and is now merely repaying) has nothing left to draw —
 *     hence `consumption_status`, defaulting to 'existing' so every
 *     already-entered loan keeps its current, non-payable behaviour.
 *
 *   - The statement is modelled on Clean Overdraft MINUS interest: an
 *     MTL installment already bundles its own interest inside
 *     schedule_payment, so charging daily interest on the drawn balance
 *     as well would double-count it. That is the ONLY structural
 *     difference; sign conventions are identical (credit = drawdown →
 *     end_balance goes negative, room = limit + end_balance).
 *
 *   - Repaying an installment posts its PRINCIPLE portion as a debit
 *     here, which lifts end_balance back toward zero and — per the
 *     owner's explicit decision — replenishes room, same as any
 *     overdraft settlement. The interest portion never touches this
 *     statement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medium_term_loans', function (Blueprint $table) {
            // 'existing' = already drawn/spent before CashVero, repayment only.
            // 'new'      = not consumed yet, can be used to pay suppliers.
            // Defaulting to 'existing' keeps every pre-existing row inert.
            $table->string('consumption_status')->default('existing')->after('status');
        });

        Schema::create('medium_term_loan_bank_statements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('medium_term_loan_id')->index('mtl_bank_statements_medium_term_loan_id_foreign');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('money_payment_id')->nullable();
            $table->unsignedBigInteger('loan_schedule_settlement_id')->nullable();
            // Mirrors clean_overdraft_bank_statements.type — the money type that
            // produced the row ('outgoing-transfer', 'payable_cheque', ...) or
            // 'principle_repayment' for the debit side.
            $table->string('type')->nullable();
            $table->boolean('is_debit')->default(false);
            $table->boolean('is_credit')->default(true);
            // NB: no `priority` column, unlike the overdraft statement tables.
            // Priority there exists only to sort same-day interest rows ahead of
            // principal ones — this table has no interest rows at all, so the
            // sequence is plain full_date + id (same as loan_statements).
            $table->date('date')->nullable();
            $table->dateTime('full_date')->nullable();
            $table->decimal('limit', 14)->default(0);
            $table->decimal('beginning_balance', 14)->default(0);
            $table->decimal('debit', 14)->nullable()->default(0);
            $table->decimal('credit', 14)->nullable()->default(0);
            $table->decimal('end_balance', 14)->default(0);
            $table->decimal('room', 14)->default(0);
            $table->string('comment_en')->nullable();
            $table->string('comment_ar')->nullable();
            $table->timestamps();

            $table->index(['medium_term_loan_id', 'full_date'], 'mtl_bank_statements_loan_full_date_index');
        });

        // The account type is what makes the loan selectable in the Money
        // Payment account dropdown and what routes handleCreditStatement()
        // to the right statement table. updateOrInsert on the slug so this
        // is safe to re-run and never collides with the seeded IDs.
        DB::table('account_types')->updateOrInsert(
            ['slug' => 'medium-term-loan'],
            [
                'name_en' => 'Medium Term Loan',
                'name_ar' => 'قرض متوسط الأجل',
                'type' => 'credit',
                'model_name' => 'MediumTermLoan',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('account_types')->where('slug', 'medium-term-loan')->delete();

        Schema::dropIfExists('medium_term_loan_bank_statements');

        Schema::table('medium_term_loans', function (Blueprint $table) {
            $table->dropColumn('consumption_status');
        });
    }
};
