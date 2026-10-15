<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeds global (non-company-specific) reference data from a static snapshot.
 *
 * Data file: database/seeders/data/cash_vero_general.php
 * No live source DB is required at seed time.
 *
 * To refresh the snapshot when cash-vero is available:
 *   php scripts/export-cash-vero-general-seeder-data.php
 */
class CashVeroGeneralSeeder extends Seeder
{
    /**
     * @var array<string, list<string>>
     */
    private const UNIQUE_BY = [
        'account_types' => ['id'],
        'banks' => ['id'],
        'currencies' => ['id'],
        'permissions' => ['id'],
        'roles' => ['id'],
        'role_has_permissions' => ['permission_id', 'role_id'],
        'sections' => ['id'],
        'users' => ['id'],
        'companies' => ['id'],
        'companies_users' => ['user_id', 'company_id'],
        'model_has_roles' => ['role_id', 'model_id', 'model_type'],
        'model_has_permissions' => ['permission_id', 'model_id', 'model_type'],
    ];

    /**
     * Seed order matters for FKs.
     *
     * @var list<string>
     */
    private const TABLES = [
        'account_types',
        'banks',
        'currencies',
        'permissions',
        'roles',
        'role_has_permissions',
        'sections',
        'users',
        'companies',
        'companies_users',
        'model_has_roles',
        'model_has_permissions',
    ];

    public function run(): void
    {
        $path = database_path('seeders/data/cash_vero_general.php');

        if (! is_file($path)) {
            throw new \RuntimeException("Missing static seeder data: {$path}");
        }

        /** @var array<string, list<array<string, mixed>>> $data */
        $data = require $path;

        Schema::disableForeignKeyConstraints();

        try {
            foreach (self::TABLES as $table) {
                $this->upsertRows($table, $data[$table] ?? []);
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function upsertRows(string $table, array $rows): void
    {
        if ($rows === []) {
            $this->command?->warn("{$table}: 0 rows (skipped)");

            return;
        }

        $uniqueBy = self::UNIQUE_BY[$table];
        $updateColumns = array_values(array_diff(array_keys($rows[0]), $uniqueBy));

        if ($updateColumns === []) {
            DB::table($table)->insertOrIgnore($rows);
        } else {
            foreach (array_chunk($rows, 200) as $chunk) {
                DB::table($table)->upsert($chunk, $uniqueBy, $updateColumns);
            }
        }

        $this->command?->info("{$table}: ".count($rows).' rows');
    }
}
