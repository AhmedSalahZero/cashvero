<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Facility Renewal — Phase 4 (ODA Against Assignment of Contract).
 *
 * Simpler than Commercial Paper's equivalent: this facility type's
 * lending rate is already locked per-contract at assignment time (via
 * lending_information_against_assignment_of_contracts.contract_id,
 * now correctly matched per the standalone bug fix) — no tier-tagging
 * mechanism needed here. This table just tracks the facility's own
 * dated terms, same shape as Clean/Fully Secured Overdraft's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overdraft_against_assignment_of_contract_terms_histories', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id');
            $table->integer('overdraft_against_assignment_of_contract_id');
            $table->date('effective_date');

            $table->decimal('limit', 14, 2)->nullable();
            $table->decimal('max_lending_limit_per_contract', 14, 2)->nullable();
            $table->float('highest_debt_balance_rate')->nullable();
            $table->float('admin_fees_rate')->nullable();
            $table->integer('to_be_setteled_max_within_days')->nullable();

            $table->date('contract_end_date')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_original')->default(false);

            $table->integer('created_by')->nullable();
            $table->timestamps();

            $table->index(['overdraft_against_assignment_of_contract_id', 'effective_date'], 'oaacth_facility_date_idx');
            $table->foreign('overdraft_against_assignment_of_contract_id', 'oaacth_facility_foreign')->references('id')->on('overdraft_against_assignment_of_contracts')->onDelete('cascade');
            $table->unique(['overdraft_against_assignment_of_contract_id', 'effective_date'], 'oaacth_facility_date_unique');
        });

        $rows = DB::table('overdraft_against_assignment_of_contracts')->select(
            'id', 'company_id', 'contract_start_date', 'contract_end_date',
            'limit', 'max_lending_limit_per_contract', 'highest_debt_balance_rate', 'admin_fees_rate', 'to_be_setteled_max_within_days'
        )->get();

        $now = now();
        foreach ($rows as $row) {
            DB::table('overdraft_against_assignment_of_contract_terms_histories')->insert([
                'company_id' => $row->company_id,
                'overdraft_against_assignment_of_contract_id' => $row->id,
                'effective_date' => $row->contract_start_date ?: '2000-01-01',
                'limit' => $row->limit ?: 0,
                'max_lending_limit_per_contract' => $row->max_lending_limit_per_contract ?: 0,
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
        Schema::dropIfExists('overdraft_against_assignment_of_contract_terms_histories');
    }
};
