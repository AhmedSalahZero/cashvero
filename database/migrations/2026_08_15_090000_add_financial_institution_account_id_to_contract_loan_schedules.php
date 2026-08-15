<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bug fix (client-flagged, confirmed 2026-08-15): contract_loan_schedules
 * stored the drawee bank as an id (drawee_bank_id, correct) but the
 * account as a plain text copy (account_number). Once a schedule row was
 * imported, that text never updated again — so editing an account's
 * number under Financial Institution Accounts silently left every
 * schedule row that used it pointing at a stale value, with no way to
 * tell it had drifted.
 *
 * This adds a real foreign key to the actual account row. account_number
 * is kept (not dropped) as a fallback display value for older rows that
 * can't be confidently matched to one specific account — see the backfill
 * migration that runs right after this one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contract_loan_schedules', function (Blueprint $table) {
            $table->integer('financial_institution_account_id')->nullable()->after('drawee_bank_id');

            $table->foreign('financial_institution_account_id')
                ->references('id')->on('financial_institution_accounts')
                ->nullOnDelete();

            $table->index('financial_institution_account_id', 'contract_loan_schedules_fi_account_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('contract_loan_schedules', function (Blueprint $table) {
            $table->dropForeign(['financial_institution_account_id']);
            $table->dropIndex('contract_loan_schedules_fi_account_id_index');
            $table->dropColumn('financial_institution_account_id');
        });
    }
};
