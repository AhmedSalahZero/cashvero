<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Internal settlements — offsetting a partner against themselves.
 *
 * A partner can be BOTH a customer and a supplier (is_customer and
 * is_supplier are independent flags). When they are, money can be owed
 * in both directions at once: they owe us on their customer invoices,
 * we owe them on their supplier invoices. Rather than each side paying
 * the other in full, an internal settlement offsets the two.
 *
 * One row is ONE offset, and it reduces both sides by the same amount:
 * the partner's customer balance goes down (they owe us less) and
 * their supplier balance goes down (we owe them less). It shows on
 * BOTH statements — a credit on the customer's, a debit on the
 * supplier's — commented as an internal settlement, so neither
 * statement has an unexplained movement.
 *
 * Kept as its own table rather than allocated across invoices, so the
 * invoice-level columns and the DB triggers that maintain them
 * (customer_invoices / supplier_invoices net_balance) are untouched.
 * This is the same shape down payments already have on this page: a
 * separate amount, subtracted from the invoice total to give the net.
 *
 * @see \App\Models\InternalSettlement
 * @see \App\Http\Controllers\BalancesController::storeInternalSettlement()
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('internal_settlements', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('partner_id')
                ->comment('The partner being offset against themselves — is_customer AND is_supplier');
            /**
             * The settlement lives in ONE currency: it offsets a
             * customer balance against a supplier balance held in the
             * same currency. Cross-currency offsetting would need a
             * rate per side and is deliberately not supported.
             */
            $table->string('currency');
            $table->date('settlement_date');
            $table->decimal('amount', 14, 2)->default(0);
            /**
             * Stamped at save time from the rate on settlement_date, so
             * the "main currency" view of the balances page and the
             * statements read the rate that applied when the offset was
             * agreed — not today's.
             */
            $table->decimal('exchange_rate', 14, 6)->default(1);
            $table->decimal('amount_in_main_currency', 14, 2)->default(0);
            $table->text('user_comment')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            // Every read is "this company's settlements for this partner
            // in this currency" — the balances page, the cap check, and
            // both statements.
            $table->index(['company_id', 'partner_id', 'currency'], 'internal_settlements_company_partner_currency_index');
            $table->index(['company_id', 'settlement_date'], 'internal_settlements_company_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('internal_settlements');
    }
};
