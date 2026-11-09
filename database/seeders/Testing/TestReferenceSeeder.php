<?php

namespace Database\Seeders\Testing;

use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * The starting data of the TEST database — and nothing else.
 *
 * Only the fixed lists the application itself needs to work: account types,
 * currencies, menu sections, the import/export field definitions, and the
 * permissions/roles. No company, no user, no bank, no movement: every test
 * makes the records it needs itself (factories and tests/Concerns), so no
 * test depends on whatever happens to be in some other database.
 *
 * account_types keep their fixed ids (27 = current account is written into
 * the code, MoneyReceived and LetterOfGuaranteeIssuanceController).
 *
 * Safe to run twice: everything is an upsert.
 */
class TestReferenceSeeder extends Seeder
{
    /** The reference lists taken from the static snapshot; its users and companies are ignored. */
    private const FROM_SNAPSHOT = ['account_types', 'currencies', 'sections'];

    public function run(): void
    {
        $snapshot = require database_path('seeders/data/cash_vero_general.php');

        foreach (self::FROM_SNAPSHOT as $table) {
            $this->upsert($table, $snapshot[$table] ?? []);
        }

        $this->upsert('tables_fields', require database_path('seeders/data/tables_fields.php'));

        $this->call(PermissionSeeder::class);
    }

    private function upsert(string $table, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $columns = array_keys($rows[0]);

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table($table)->upsert($chunk, ['id'], array_values(array_diff($columns, ['id'])));
        }

        $this->command?->info("{$table}: ".count($rows).' rows');
    }
}
