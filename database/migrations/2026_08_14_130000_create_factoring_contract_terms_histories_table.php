<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Facility Renewal — Phase 7 (Factoring Contract, final facility
 * type). Structurally identical to Clean Overdraft's terms history:
 * Factoring Contract has the same shape of flat rate fields
 * (borrowing/margin/interest/min-interest/highest-debt-balance/
 * admin-fees rates, limit, settlement days) and no per-type matrix —
 * so this follows that pattern exactly rather than LG/LC's matrix
 * approach.
 *
 * Every Factoring Contract today stores exactly one, undated set of
 * terms directly on `factoring_contracts`. This table gives each
 * contract a dated timeline of terms instead: one row per "chapter"
 * (original contract + every renewal after it), each with the date
 * it took effect.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('factoring_contract_terms_histories', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('factoring_contract_id');
            $table->date('effective_date');

            $table->decimal('limit', 20, 2)->nullable();
            $table->decimal('borrowing_rate', 8, 4)->nullable();
            $table->decimal('margin_rate', 8, 4)->nullable();
            $table->decimal('interest_rate', 8, 4)->nullable();
            $table->decimal('min_interest_rate', 8, 4)->nullable();
            $table->decimal('highest_debt_balance_rate', 8, 4)->nullable();
            $table->decimal('admin_fees_rate', 8, 4)->nullable();
            $table->unsignedInteger('to_be_setteled_max_within_days')->nullable();

            $table->date('contract_end_date')->nullable();
            $table->text('notes')->nullable();

            // true only for the row auto-created from the contract's
            // original terms (never itself a renewal).
            $table->boolean('is_original')->default(false);

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->index(['factoring_contract_id', 'effective_date'], 'fcth_contract_date_idx');
            $table->foreign('factoring_contract_id', 'fcth_contract_foreign')->references('id')->on('factoring_contracts')->onDelete('cascade');

            // A contract can only have one set of terms taking effect
            // on any given date.
            $table->unique(['factoring_contract_id', 'effective_date'], 'fcth_contract_date_unique');
        });

        // Backfill: give every existing Factoring Contract its
        // "chapter one" row, built from its current field values,
        // dated to its contract_start_date.
        $rows = DB::table('factoring_contracts')->select(
            'id', 'company_id', 'contract_start_date', 'contract_end_date', 'limit',
            'borrowing_rate', 'margin_rate', 'interest_rate', 'min_interest_rate',
            'highest_debt_balance_rate', 'admin_fees_rate', 'to_be_setteled_max_within_days'
        )->get();

        $now = now();
        foreach ($rows as $row) {
            DB::table('factoring_contract_terms_histories')->insert([
                'company_id' => $row->company_id,
                'factoring_contract_id' => $row->id,
                'effective_date' => $row->contract_start_date ?: '2000-01-01',
                'limit' => $row->limit ?: 0,
                'borrowing_rate' => $row->borrowing_rate ?: 0,
                'margin_rate' => $row->margin_rate ?: 0,
                'interest_rate' => $row->interest_rate ?: 0,
                'min_interest_rate' => $row->min_interest_rate ?: 0,
                'highest_debt_balance_rate' => $row->highest_debt_balance_rate ?: 0,
                'admin_fees_rate' => $row->admin_fees_rate ?: 0,
                'to_be_setteled_max_within_days' => $row->to_be_setteled_max_within_days ?: 0,
                'contract_end_date' => $row->contract_end_date,
                'is_original' => true,
                'notes' => 'Backfilled from original facility terms.',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('factoring_contract_terms_histories');
    }
};
