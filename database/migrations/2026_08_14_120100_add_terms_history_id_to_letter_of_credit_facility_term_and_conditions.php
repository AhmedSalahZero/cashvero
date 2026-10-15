<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Facility Renewal — Phase 6 (LC Facility), part 2. Mirrors the LG
 * Facility migration of the same shape exactly.
 *
 * Each row in letter_of_credit_facility_term_and_conditions (the
 * per-LC-type rate matrix — Sight LC / Deferred / Cash Against
 * Document) now belongs to a specific chapter. A renewal creates a
 * whole NEW set of 3 rows tagged to the new chapter; the OLD
 * chapter's rows are never touched again, so old rates stay visible
 * as history exactly as they were.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letter_of_credit_facility_term_and_conditions', function (Blueprint $table) {
            $table->unsignedBigInteger('terms_history_id')->nullable()->after('letter_of_credit_facility_id');
        });

        $originalChapters = DB::table('letter_of_credit_facility_terms_histories')
            ->where('is_original', true)
            ->pluck('id', 'letter_of_credit_facility_id');

        foreach ($originalChapters as $facilityId => $termsHistoryId) {
            DB::table('letter_of_credit_facility_term_and_conditions')
                ->where('letter_of_credit_facility_id', $facilityId)
                ->whereNull('terms_history_id')
                ->update(['terms_history_id' => $termsHistoryId]);
        }

        Schema::table('letter_of_credit_facility_term_and_conditions', function (Blueprint $table) {
            $table->foreign('terms_history_id', 'lcftc_terms_history_foreign')
                ->references('id')->on('letter_of_credit_facility_terms_histories')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('letter_of_credit_facility_term_and_conditions', function (Blueprint $table) {
            $table->dropForeign('lcftc_terms_history_foreign');
            $table->dropColumn('terms_history_id');
        });
    }
};
