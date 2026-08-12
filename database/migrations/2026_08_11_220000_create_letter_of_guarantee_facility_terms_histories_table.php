<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Facility Renewal — Phase 5 (LG Facility, final facility type).
 *
 * Simplest of all five: no interest, no settlement-days mechanism, no
 * auto-calculated limit. Just a dated chapter of limit + contract end
 * date. The per-LG-type Term & Conditions matrix gets tagged to a
 * chapter separately (next migration) — each individual LG issuance
 * already snapshots its own rate at the moment it's issued, so old
 * issuances never need any date-aware lookup; this is purely for
 * keeping old rate history visible and never silently overwritten.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('letter_of_guarantee_facility_terms_histories', function (Blueprint $table) {
            $table->id();
            $table->integer('company_id');
            $table->integer('letter_of_guarantee_facility_id');
            $table->date('effective_date');

            $table->decimal('limit', 14, 2)->nullable();
            $table->date('contract_end_date')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_original')->default(false);

            $table->integer('created_by')->nullable();
            $table->timestamps();

            $table->index(['letter_of_guarantee_facility_id', 'effective_date'], 'lgfth_facility_date_idx');
            $table->foreign('letter_of_guarantee_facility_id', 'lgfth_facility_foreign')->references('id')->on('letter_of_guarantee_facilities')->onDelete('cascade');
            $table->unique(['letter_of_guarantee_facility_id', 'effective_date'], 'lgfth_facility_date_unique');
        });

        $rows = DB::table('letter_of_guarantee_facilities')->select(
            'id', 'company_id', 'contract_start_date', 'contract_end_date', 'limit'
        )->get();

        $now = now();
        foreach ($rows as $row) {
            DB::table('letter_of_guarantee_facility_terms_histories')->insert([
                'company_id' => $row->company_id,
                'letter_of_guarantee_facility_id' => $row->id,
                'effective_date' => $row->contract_start_date ?: '2000-01-01',
                'limit' => $row->limit ?: 0,
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
        Schema::dropIfExists('letter_of_guarantee_facility_terms_histories');
    }
};
