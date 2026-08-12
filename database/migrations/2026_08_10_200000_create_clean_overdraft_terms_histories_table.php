<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Facility Renewal — Phase 1 (Clean Overdraft).
 *
 * Every Clean Overdraft today stores exactly one, undated set of terms
 * (limit / highest_debt_balance_rate / admin_fees_rate /
 * to_be_setteled_max_within_days) directly on `clean_overdrafts`. There is
 * no way to change those terms from a given date forward without silently
 * rewriting history — which is exactly the gap the facility-renewal
 * feature closes.
 *
 * This table gives each Clean Overdraft a dated timeline of terms instead
 * of one static snapshot: one row per "chapter" (original contract +
 * every renewal after it), each with the date it took effect. Bank
 * statement calculations look up "whichever row's effective_date is the
 * latest one on/before this transaction's date" rather than trusting a
 * single current value — so backdated entries and old-vs-new splits
 * resolve correctly on their own, without special-casing in the app layer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clean_overdraft_terms_histories', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id');
            $table->integer('clean_overdraft_id');
            $table->date('effective_date');

            // Mirrors the four fields on `clean_overdrafts` that a
            // renewal can change. Nullable so a renewal that only
            // changes, say, the limit doesn't have to also restate the
            // other three — but the backfill row below always fills all
            // four so a lookup never has to fall back to nothing.
            $table->decimal('limit', 14, 2)->nullable();
            $table->float('highest_debt_balance_rate')->nullable();
            $table->float('admin_fees_rate')->nullable();
            $table->integer('to_be_setteled_max_within_days')->nullable();

            $table->date('contract_end_date')->nullable();
            $table->text('notes')->nullable();

            // true only for the row auto-created from the facility's
            // original contract terms (never itself a renewal) — lets
            // the UI show "Original Terms" vs "Renewal" without guessing.
            $table->boolean('is_original')->default(false);

            $table->integer('created_by')->nullable();
            $table->timestamps();

            $table->index(['clean_overdraft_id', 'effective_date'], 'codoh_facility_date_idx');
            $table->foreign('clean_overdraft_id')->references('id')->on('clean_overdrafts')->onDelete('cascade');

            // A facility can only have one set of terms taking effect on
            // any given date — mirrors the "no duplicate renewal dates"
            // rule agreed in the design brief.
            $table->unique(['clean_overdraft_id', 'effective_date'], 'codoh_facility_date_unique');
        });

        // Backfill: give every existing Clean Overdraft its "chapter one"
        // row, built from its current field values, dated to its
        // contract_start_date. Without this, a facility renewed before
        // ever getting a backfilled row would have no history to fall
        // back to for anything dated before the renewal.
        $rows = DB::table('clean_overdrafts')->select(
            'id', 'company_id', 'contract_start_date', 'contract_end_date',
            'limit', 'highest_debt_balance_rate', 'admin_fees_rate', 'to_be_setteled_max_within_days'
        )->get();

        $now = now();
        foreach ($rows as $row) {
            DB::table('clean_overdraft_terms_histories')->insert([
                'company_id' => $row->company_id,
                'clean_overdraft_id' => $row->id,
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
        Schema::dropIfExists('clean_overdraft_terms_histories');
    }
};
