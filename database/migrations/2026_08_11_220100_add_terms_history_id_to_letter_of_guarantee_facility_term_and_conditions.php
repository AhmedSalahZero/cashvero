<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Facility Renewal — Phase 5 (LG Facility), part 2.
 *
 * Each row in letter_of_guarantee_facility_term_and_conditions (the
 * per-LG-type rate matrix) now belongs to a specific chapter. A
 * renewal creates a whole NEW set of 4 rows tagged to the new
 * chapter; the OLD chapter's rows are never touched again — exactly
 * matching the client's own example (Final LG commission 0.2% under
 * the original, 0.4% from the renewal onward, both visible in
 * history).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('letter_of_guarantee_facility_term_and_conditions', function (Blueprint $table) {
            $table->unsignedBigInteger('terms_history_id')->nullable()->after('letter_of_guarantee_facility_id');
        });

        $originalChapters = DB::table('letter_of_guarantee_facility_terms_histories')
            ->where('is_original', true)
            ->pluck('id', 'letter_of_guarantee_facility_id');

        foreach ($originalChapters as $facilityId => $termsHistoryId) {
            DB::table('letter_of_guarantee_facility_term_and_conditions')
                ->where('letter_of_guarantee_facility_id', $facilityId)
                ->whereNull('terms_history_id')
                ->update(['terms_history_id' => $termsHistoryId]);
        }

        Schema::table('letter_of_guarantee_facility_term_and_conditions', function (Blueprint $table) {
            $table->foreign('terms_history_id', 'lgftc_terms_history_foreign')
                ->references('id')->on('letter_of_guarantee_facility_terms_histories')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('letter_of_guarantee_facility_term_and_conditions', function (Blueprint $table) {
            $table->dropForeign('lgftc_terms_history_foreign');
            $table->dropColumn('terms_history_id');
        });
    }
};
