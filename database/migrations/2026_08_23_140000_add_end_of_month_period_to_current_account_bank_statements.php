<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Records WHICH MONTH an end-of-month interest row stands for.
 *
 * The row's own `date` cannot answer that: a user can edit it from the
 * bank statement screen, and 7 rows on record already sit somewhere
 * other than a month end (2025-11-01, 2025-11-26, 2026-05-25 …). Once
 * the date has moved, nothing tells you the row was October's — so the
 * generator cannot know whether a month already has a row, which is
 * why its existence check was commented out and why
 * `synced_end_of_month_years` had to block regeneration outright.
 *
 * That block is what makes moving an account's Balance Date BACKWARDS
 * leave a hole: the months between the new date and the old one never
 * get their rows, and the year is already marked synced so they never
 * will. Two accounts on record are missing rows for exactly that
 * reason.
 *
 * With the period stamped on the row, the generator can ask "does this
 * month already have one?" reliably, becomes idempotent, and can be
 * re-run safely to fill a gap.
 */
return new class extends Migration
{
    private const TABLE = 'current_account_bank_statements';

    private const COLUMN = 'end_of_month_period';

    public function up(): void
    {
        if (! Schema::hasColumn(self::TABLE, self::COLUMN)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                // 'YYYY-MM'. Null on every row that is not a generated
                // month-end interest row.
                $table->string(self::COLUMN, 7)->nullable()->after('interest_type');
                $table->index(['financial_institution_account_id', self::COLUMN], 'ca_stmt_account_eom_period_index');
            });
        }

        /*
         * Backfill from the month the row currently sits in. For a row
         * still on its month end that is exactly right. For one whose
         * date was edited it is the best available answer, and a
         * deliberate one: it keeps "one row per month" checkable, and
         * any month that ends up with two rows (or none) becomes
         * visible instead of staying hidden.
         */
        DB::table(self::TABLE)
            ->whereIn('interest_type', ['end_of_month', 'end_of_month_final'])
            ->whereNull(self::COLUMN)
            ->update([
                self::COLUMN => DB::raw("DATE_FORMAT(`date`, '%Y-%m')"),
            ]);
    }

    public function down(): void
    {
        if (Schema::hasColumn(self::TABLE, self::COLUMN)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropIndex('ca_stmt_account_eom_period_index');
                $table->dropColumn(self::COLUMN);
            });
        }
    }
};
