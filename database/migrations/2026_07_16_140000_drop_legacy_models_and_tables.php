<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cleanup of unused legacy models/tables (only Cash Vero remains):
     *  1. Drop legacy tables whose Eloquent models were removed and that are no
     *     longer reachable from any live code path:
     *      - sales_gathering / sales_gathering_tests (old sales-analysis engine)
     *      - lc/lg opening-balance child tables (removed opening-balance system)
     *      - loans (old standalone Loan calculator, replaced by MediumTermLoan)
     *  2. Remove legacy per-company export/upload config rows that point to
     *     removed model names.
     */
    private array $tablesToDrop = [
        'sales_gathering',
        'sales_gathering_tests',
        'lc_against_td_or_cd_opening_balances',
        'lc_hundred_percentage_cash_cover_opening_balances',
        'lg_against_td_or_cd_opening_balances',
        'lg_hundred_percentage_cash_cover_opening_balances',
        'loans',
    ];

    private array $legacyModelNames = [
        'SalesGathering',
        'ExportAnalysis',
        'ExpenseAnalysis',
        'CustomerDueCollectionAnalysis',
        'LabelingItem',
    ];

    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($this->tablesToDrop as $table) {
            Schema::dropIfExists($table);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        if (Schema::hasTable('customized_fields_exportations')) {
            DB::table('customized_fields_exportations')
                ->whereIn('model_name', $this->legacyModelNames)
                ->delete();
        }

        if (Schema::hasTable('last_upload_file_names')) {
            DB::table('last_upload_file_names')
                ->whereIn('model_name', $this->legacyModelNames)
                ->delete();
        }
    }

    public function down(): void
    {
        // Irreversible: dropped legacy tables and deleted config rows cannot be restored.
    }
};
