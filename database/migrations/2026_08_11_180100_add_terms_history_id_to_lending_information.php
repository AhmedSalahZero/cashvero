<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Facility Renewal — Phase 3 (ODA Against Commercial Paper), part 2.
 *
 * Each row in `lending_information` (the day-tier → rate schedule) now
 * belongs to a specific chapter (terms-history row). This is what makes
 * a cheque's rate lookup date-aware: a renewal creates a whole NEW set
 * of tier rows tagged to the new chapter, and the OLD chapter's tier
 * rows are never touched again — a cheque deposited under the old
 * chapter keeps resolving against the old tiers forever.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lending_information', function (Blueprint $table) {
            $table->unsignedBigInteger('terms_history_id')->nullable()->after('overdraft_against_commercial_paper_id');
        });

        // Backfill: every existing tier row belongs to its facility's
        // Original chapter (created by the previous migration).
        $originalChapters = DB::table('overdraft_against_commercial_paper_terms_histories')
            ->where('is_original', true)
            ->pluck('id', 'overdraft_against_commercial_paper_id');

        foreach ($originalChapters as $facilityId => $termsHistoryId) {
            DB::table('lending_information')
                ->where('overdraft_against_commercial_paper_id', $facilityId)
                ->whereNull('terms_history_id')
                ->update(['terms_history_id' => $termsHistoryId]);
        }

        Schema::table('lending_information', function (Blueprint $table) {
            $table->foreign('terms_history_id', 'li_terms_history_foreign')
                ->references('id')->on('overdraft_against_commercial_paper_terms_histories')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('lending_information', function (Blueprint $table) {
            $table->dropForeign('li_terms_history_foreign');
            $table->dropColumn('terms_history_id');
        });
    }
};
