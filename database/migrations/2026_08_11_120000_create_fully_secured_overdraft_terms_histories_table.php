<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Facility Renewal — Phase 2 (Fully Secured Overdraft).
 *
 * Identical purpose and shape to clean_overdraft_terms_histories — see
 * that migration's comments for the full reasoning. Fully Secured
 * Overdraft uses the exact same single-static-limit engine as Clean
 * Overdraft, so it needed the exact same fix.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fully_secured_overdraft_terms_histories', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id');
            $table->integer('fully_secured_overdraft_id');
            $table->date('effective_date');

            $table->decimal('limit', 14, 2)->nullable();
            $table->float('highest_debt_balance_rate')->nullable();
            $table->float('admin_fees_rate')->nullable();
            $table->integer('to_be_setteled_max_within_days')->nullable();

            $table->date('contract_end_date')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_original')->default(false);

            $table->integer('created_by')->nullable();
            $table->timestamps();

            $table->index(['fully_secured_overdraft_id', 'effective_date'], 'fsodoh_facility_date_idx');
            $table->foreign('fully_secured_overdraft_id', 'fsodoh_facility_foreign')->references('id')->on('fully_secured_overdrafts')->onDelete('cascade');
            $table->unique(['fully_secured_overdraft_id', 'effective_date'], 'fsodoh_facility_date_unique');
        });

        // Backfill every existing Fully Secured Overdraft's "chapter one"
        // row, exactly as done for Clean Overdraft.
        $rows = DB::table('fully_secured_overdrafts')->select(
            'id', 'company_id', 'contract_start_date', 'contract_end_date',
            'limit', 'highest_debt_balance_rate', 'admin_fees_rate', 'to_be_setteled_max_within_days'
        )->get();

        $now = now();
        foreach ($rows as $row) {
            DB::table('fully_secured_overdraft_terms_histories')->insert([
                'company_id' => $row->company_id,
                'fully_secured_overdraft_id' => $row->id,
                'effective_date' => $row->contract_start_date ?: '2000-01-01',
                'limit' => $row->limit ?: 0,
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
        Schema::dropIfExists('fully_secured_overdraft_terms_histories');
    }
};
