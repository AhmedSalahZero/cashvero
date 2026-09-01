<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Other Dues" — amounts owed between the company and a partner that are
 * not invoices: a deposit held at a customer, a retention, a balance
 * carried over from before the company started using CashVero.
 *
 * Entered as a repeater alongside the Suppliers Opening Balance, and
 * dated on the company's opening balance date, because that is what it
 * is: part of the opening position rather than a transaction.
 *
 * The same partner may appear on several rows — each is its own due with
 * its own comment, and they are deliberately NOT summed together.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('other_dues', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->unsignedBigInteger('partner_id')->index();

            /**
             * Which side owes: 'due_from' — the partner owes us;
             * 'due_to' — we owe the partner. This is the only thing that
             * decides whether the statement row is a debit or a credit.
             */
            $table->string('direction', 16);

            /**
             * The partner flag the row was entered under (is_customer,
             * is_supplier, is_shareholder, …). Kept because a partner can
             * carry several flags at once, and the statement this due
             * belongs in follows the type chosen here, not a guess.
             */
            $table->string('partner_type', 32);

            $table->decimal('amount', 14, 2)->default(0);
            $table->string('currency', 16);

            /**
             * Only meaningful when the currency is not the company's main
             * one; kept nullable rather than defaulted to 1 so "not
             * applicable" and "rate of one" stay distinguishable.
             */
            $table->decimal('exchange_rate', 14, 6)->nullable();

            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'partner_id', 'currency']);
        });

        // Lets a statement row be traced back to the due that created it,
        // so editing or removing the due cleans up after itself.
        foreach ([
            'employee_statements',
            'shareholder_statements',
            'subsidiary_company_statements',
            'other_partner_statements',
            'tax_statements',
        ] as $statementTable) {
            if (Schema::hasTable($statementTable) && ! Schema::hasColumn($statementTable, 'other_due_id')) {
                Schema::table($statementTable, function (Blueprint $table) {
                    $table->unsignedBigInteger('other_due_id')->nullable()->index()->after('money_payment_id');
                });
            }
        }
    }

    public function down(): void
    {
        foreach ([
            'employee_statements',
            'shareholder_statements',
            'subsidiary_company_statements',
            'other_partner_statements',
            'tax_statements',
        ] as $statementTable) {
            if (Schema::hasTable($statementTable) && Schema::hasColumn($statementTable, 'other_due_id')) {
                Schema::table($statementTable, function (Blueprint $table) {
                    $table->dropColumn('other_due_id');
                });
            }
        }

        Schema::dropIfExists('other_dues');
    }
};
