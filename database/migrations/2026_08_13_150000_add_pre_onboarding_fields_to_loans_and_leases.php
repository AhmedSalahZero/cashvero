<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the fields needed to onboard a company that already has a
 * running MTL / Leasing facility before it started using CashVero:
 *
 *   - already_paid_amount:        reference/memo only — tells the user
 *                                  what Beginning Balance to put on row 1
 *                                  of their schedule upload
 *                                  (Limit − already_paid_amount). Does
 *                                  NOT feed the Outstanding/Paid
 *                                  dashboard figures — those are
 *                                  computed live from SUM(remaining) on
 *                                  the schedule tables.
 *   - first_installment_date:     due date of the next (first) coming
 *                                  installment; the uploaded schedule's
 *                                  first row must match this.
 *   - remaining_installment_count: number of installments left to pay;
 *                                  the uploaded schedule's row count
 *                                  must match this.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medium_term_loans', function (Blueprint $table) {
            $table->decimal('already_paid_amount', 14, 2)->default(0.00)->after('outstanding_amount');
            $table->date('first_installment_date')->nullable()->after('already_paid_amount');
            $table->unsignedInteger('remaining_installment_count')->nullable()->after('first_installment_date');
        });

        Schema::table('leasing_contracts', function (Blueprint $table) {
            $table->decimal('already_paid_amount', 14, 2)->default(0.00)->after('outstanding_amount');
            $table->date('first_installment_date')->nullable()->after('already_paid_amount');
            $table->unsignedInteger('remaining_installment_count')->nullable()->after('first_installment_date');
        });
    }

    public function down(): void
    {
        Schema::table('medium_term_loans', function (Blueprint $table) {
            $table->dropColumn(['already_paid_amount', 'first_installment_date', 'remaining_installment_count']);
        });

        Schema::table('leasing_contracts', function (Blueprint $table) {
            $table->dropColumn(['already_paid_amount', 'first_installment_date', 'remaining_installment_count']);
        });
    }
};
