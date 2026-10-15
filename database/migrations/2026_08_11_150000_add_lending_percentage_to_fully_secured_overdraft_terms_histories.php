<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Client-flagged (2026-08-11): the Renew popup was missing "CD Or TD
 * Lending Percentage (%)" — a field the original facility form has,
 * since a Fully Secured Overdraft's limit is CD/TD amount × this
 * percentage, not a directly-typed number. This adds the column so a
 * renewal can carry its own (possibly changed) percentage, same as the
 * original facility does.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fully_secured_overdraft_terms_histories', function (Blueprint $table) {
            $table->float('cd_or_td_lending_percentage')->nullable()->after('limit');
        });

        // Backfill: every existing chapter's percentage is whatever the
        // facility's own current percentage is — there's no history of
        // this value before now, so this is the best available truth.
        $rows = DB::table('fully_secured_overdraft_terms_histories')
            ->join('fully_secured_overdrafts', 'fully_secured_overdraft_terms_histories.fully_secured_overdraft_id', '=', 'fully_secured_overdrafts.id')
            ->select('fully_secured_overdraft_terms_histories.id', 'fully_secured_overdrafts.cd_or_td_lending_percentage')
            ->get();

        foreach ($rows as $row) {
            DB::table('fully_secured_overdraft_terms_histories')
                ->where('id', $row->id)
                ->update(['cd_or_td_lending_percentage' => $row->cd_or_td_lending_percentage]);
        }
    }

    public function down(): void
    {
        Schema::table('fully_secured_overdraft_terms_histories', function (Blueprint $table) {
            $table->dropColumn('cd_or_td_lending_percentage');
        });
    }
};
