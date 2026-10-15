<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Through Leasing" — a Money Payment made by the LEASING COMPANY, not
 * by the company's own bank.
 *
 * Business shape (confirmed with the project owner, 2026-08-18):
 *
 *   - The company signs a leasing contract with a leasing company for,
 *     say, 1,000,000. That money is never handed over as cash: the
 *     leasing company pays the company's suppliers directly out of the
 *     contract. So on the Money Payment screen "Through Leasing" is a
 *     MONEY TYPE of its own, sitting next to Cash Payment / Payable
 *     Cheque / Outgoing Transfer, with its own card asking only for the
 *     Leasing Company and then the Contract.
 *
 *   - Deliberately NOT an account type. An earlier attempt modelled it
 *     as a Leasing Contract account type inside the Outgoing Transfer
 *     card, which forced the "Payment Bank" picker to swap itself for a
 *     "Leasing Company" picker and dragged the bank-account validation
 *     rules along with it. A separate money type keeps the bank cascade
 *     completely untouched — no account type, no account number, no
 *     bank.
 *
 *   - The company's own cash never moves, so these payments stay out of
 *     the cash-flow reports (which enumerate their money types
 *     explicitly and therefore ignore this one by construction). The
 *     supplier is still really paid, so the partner statement is
 *     debited exactly as with any other money type.
 *
 *   - The contract's statement is modelled on the Medium Term Loan's,
 *     which is itself Clean Overdraft MINUS interest: a leasing
 *     installment already bundles its interest inside schedule_payment,
 *     so accruing interest on the drawn balance as well would
 *     double-count it. Sign conventions are identical —
 *     credit = drawdown (end_balance goes negative, room shrinks),
 *     debit = principle portion of a repaid installment.
 *
 *   - Every RUNNING contract of the chosen leasing company is payable
 *     from; there is no new/existing flag on the contract (owner's
 *     decision, 2026-08-18).
 */
return new class extends Migration
{
    public function up(): void
    {
        /**
         * The relation row hanging off money_payments, sibling of
         * outgoing_transfers / payable_cheques / cash_payments. Holds the
         * two — and only two — fields this money type asks for.
         *
         * actual_payment_date mirrors outgoing_transfers': the reports and
         * the statement cascade both read it, and for this type it is
         * always the delivery date (no cheque clearing to wait for).
         */
        Schema::create('leasing_payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('money_payment_id')->index('leasing_payments_money_payment_id_index');
            $table->unsignedBigInteger('leasing_company_id')->index('leasing_payments_leasing_company_id_index');
            $table->unsignedBigInteger('leasing_contract_id')->index('leasing_payments_leasing_contract_id_index');
            $table->unsignedBigInteger('company_id');
            $table->date('actual_payment_date')->nullable();
            $table->timestamps();
        });

        /**
         * The contract's drawdown/repayment ledger — a straight copy of
         * medium_term_loan_bank_statements, keyed to leasing_contract_id.
         */
        Schema::create('leasing_contract_bank_statements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('leasing_contract_id')->index('lcbs_leasing_contract_id_index');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('money_payment_id')->nullable();
            $table->unsignedBigInteger('contract_loan_schedule_settlement_id')->nullable();
            // The money type that produced the row ('leasing_payment') or
            // 'installment_repayment' for the debit side.
            $table->string('type')->nullable();
            $table->boolean('is_debit')->default(false);
            $table->boolean('is_credit')->default(true);
            // No `priority` column: that exists on the overdraft statements
            // only to sort same-day interest rows ahead of principal ones,
            // and this table has no interest rows at all.
            $table->date('date')->nullable();
            $table->dateTime('full_date')->nullable();
            $table->decimal('limit', 14)->default(0);
            $table->decimal('beginning_balance', 14)->default(0);
            $table->decimal('debit', 14)->nullable()->default(0);
            $table->decimal('credit', 14)->nullable()->default(0);
            // Recorded only — never part of the balance equation. See the
            // trigger header for why a leasing installment's interest must
            // not move the drawn balance.
            $table->decimal('interest_amount', 14)->default(0);
            $table->decimal('end_balance', 14)->default(0);
            $table->decimal('room', 14)->default(0);
            $table->string('comment_en')->nullable();
            $table->string('comment_ar')->nullable();
            $table->timestamps();

            $table->index(['leasing_contract_id', 'full_date'], 'lcbs_contract_full_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leasing_contract_bank_statements');
        Schema::dropIfExists('leasing_payments');
    }
};
