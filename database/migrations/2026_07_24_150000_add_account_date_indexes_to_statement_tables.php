<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supports the new MoneyReceivedCanBeDeletedRule full-history balance lookup, which asks each
 * statement table for the lowest end_balance/room from an account on or after a given date.
 * Without an index on (account foreign key, date), that lookup falls back to a full table scan
 * as history grows. Structural/performance only — does not change any stored value or
 * calculation, and is fully reversible.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('current_account_bank_statements', function (Blueprint $table) {
            $table->index(['financial_institution_account_id', 'date'], 'current_acc_stmts_account_date_index');
        });

        Schema::table('cash_in_safe_statements', function (Blueprint $table) {
            $table->index(['branch_id', 'currency', 'date'], 'cash_in_safe_stmts_branch_currency_date_index');
        });

        Schema::table('fully_secured_overdraft_bank_statements', function (Blueprint $table) {
            $table->index(['fully_secured_overdraft_id', 'date'], 'fso_bank_stmts_account_date_index');
        });

        Schema::table('clean_overdraft_bank_statements', function (Blueprint $table) {
            $table->index(['clean_overdraft_id', 'date'], 'co_bank_stmts_account_date_index');
        });

        Schema::table('overdraft_against_commercial_paper_bank_statements', function (Blueprint $table) {
            $table->index(['overdraft_against_commercial_paper_id', 'date'], 'oacp_bank_stmts_account_date_index');
        });

        Schema::table('overdraft_against_assignment_of_contract_bank_statements', function (Blueprint $table) {
            $table->index(['overdraft_against_assignment_of_contract_id', 'date'], 'oaaoc_bank_stmts_account_date_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('current_account_bank_statements', function (Blueprint $table) {
            $table->dropIndex('current_acc_stmts_account_date_index');
        });

        Schema::table('cash_in_safe_statements', function (Blueprint $table) {
            $table->dropIndex('cash_in_safe_stmts_branch_currency_date_index');
        });

        Schema::table('fully_secured_overdraft_bank_statements', function (Blueprint $table) {
            $table->dropIndex('fso_bank_stmts_account_date_index');
        });

        Schema::table('clean_overdraft_bank_statements', function (Blueprint $table) {
            $table->dropIndex('co_bank_stmts_account_date_index');
        });

        Schema::table('overdraft_against_commercial_paper_bank_statements', function (Blueprint $table) {
            $table->dropIndex('oacp_bank_stmts_account_date_index');
        });

        Schema::table('overdraft_against_assignment_of_contract_bank_statements', function (Blueprint $table) {
            $table->dropIndex('oaaoc_bank_stmts_account_date_index');
        });
    }
};
