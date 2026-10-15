<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Facility Renewal — Phase 6 (LC Facility).
 *
 * LC Facility is a hybrid of two things the renewal feature already
 * covers separately: it has the same flat "Financing Terms &
 * Conditions" fields as Fully Secured Overdraft (limit — possibly
 * CD/TD-calculated — borrowing/margin/interest/min-interest/highest-
 * debt-balance/admin-fees rates), PLUS the same fixed per-LC-type
 * rate matrix LG Facility has (see the next migration for that half).
 *
 * This table stores the flat side as dated chapters, exactly like
 * fully_secured_overdraft_terms_histories.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_of_credit_facility_terms_histories', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id');
            $table->integer('letter_of_credit_facility_id');
            $table->date('effective_date');

            $table->decimal('limit', 14, 2)->nullable();
            $table->float('cd_or_td_lending_percentage')->nullable();
            $table->float('borrowing_rate')->nullable();
            $table->float('bank_margin_rate')->nullable();
            $table->float('interest_rate')->nullable();
            $table->float('min_interest_rate')->nullable();
            $table->float('highest_debt_balance_rate')->nullable();
            $table->float('admin_fees_rate')->nullable();

            $table->date('contract_end_date')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_original')->default(false);

            $table->integer('created_by')->nullable();
            $table->timestamps();

            $table->index(['letter_of_credit_facility_id', 'effective_date'], 'lcfth_facility_date_idx');
            $table->foreign('letter_of_credit_facility_id', 'lcfth_facility_foreign')->references('id')->on('letter_of_credit_facilities')->onDelete('cascade');
            $table->unique(['letter_of_credit_facility_id', 'effective_date'], 'lcfth_facility_date_unique');
        });

        $rows = DB::table('letter_of_credit_facilities')->select(
            'id', 'company_id', 'contract_start_date', 'contract_end_date',
            'limit', 'cd_or_td_lending_percentage', 'borrowing_rate', 'bank_margin_rate',
            'interest_rate', 'min_interest_rate', 'highest_debt_balance_rate', 'admin_fees_rate'
        )->get();

        $now = now();
        foreach ($rows as $row) {
            DB::table('letter_of_credit_facility_terms_histories')->insert([
                'company_id' => $row->company_id,
                'letter_of_credit_facility_id' => $row->id,
                'effective_date' => $row->contract_start_date ?: '2000-01-01',
                'limit' => $row->limit ?: 0,
                'cd_or_td_lending_percentage' => $row->cd_or_td_lending_percentage ?: 0,
                'borrowing_rate' => $row->borrowing_rate ?: 0,
                'bank_margin_rate' => $row->bank_margin_rate ?: 0,
                'interest_rate' => $row->interest_rate ?: 0,
                'min_interest_rate' => $row->min_interest_rate ?: 0,
                'highest_debt_balance_rate' => $row->highest_debt_balance_rate ?: 0,
                'admin_fees_rate' => $row->admin_fees_rate ?: 0,
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
        Schema::dropIfExists('letter_of_credit_facility_terms_histories');
    }
};
