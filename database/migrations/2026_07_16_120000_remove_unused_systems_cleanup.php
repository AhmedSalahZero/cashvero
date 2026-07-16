<?php

use App\Helpers\HAuth;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cleanup of leftovers from legacy systems (only Cash Vero remains):
     *  1. Re-apply the current_account_bank_statements triggers without the
     *     dev-only inserts into the `debugging` table, then drop that table.
     *  2. Remove orphaned Spatie permissions that are no longer registered in
     *     HAuth::getPermissions() (legacy income-statement / pricing / sales
     *     gathering / labeling / analysis / forecast permissions).
     *  3. Remove company_systems rows for legacy systems, keeping only cash-vero.
     */
    public function up(): void
    {
        $this->reapplyCurrentAccountTriggers();

        Schema::dropIfExists('debugging');

        $this->deleteOrphanedPermissions();

        DB::table('company_systems')->where('system_name', '!=', CASH_VERO)->delete();
    }

    public function down(): void
    {
        // Irreversible: dropped table, deleted rows and permissions cannot be restored.
    }

    private function reapplyCurrentAccountTriggers(): void
    {
        $path = app_path('Triggers/Cashvero/current_account_bank_statements.sql');

        if (! is_file($path)) {
            return;
        }

        $sql = file_get_contents($path);

        // Same delimiter normalization used by the run:sql command.
        $sql = str_replace(['delimiter ;', 'delimiter //', 'DELIMITER $$', 'delimiter $$', 'DELIMITER ;'], '', $sql);
        $sql = str_replace(['//', '$$'], ';', $sql);
        $sql = str_replace(['DELIMITER ;'], '', $sql);

        DB::unprepared($sql);
    }

    private function deleteOrphanedPermissions(): void
    {
        $activeNames = collect(HAuth::getPermissions())->pluck('name')->unique()->all();

        $orphanIds = DB::table('permissions')
            ->whereNotIn('name', $activeNames)
            ->pluck('id')
            ->all();

        if (empty($orphanIds)) {
            return;
        }

        DB::table('model_has_permissions')->whereIn('permission_id', $orphanIds)->delete();
        DB::table('role_has_permissions')->whereIn('permission_id', $orphanIds)->delete();
        DB::table('permissions')->whereIn('id', $orphanIds)->delete();
    }
};
