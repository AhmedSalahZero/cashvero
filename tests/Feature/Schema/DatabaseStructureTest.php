<?php

namespace Tests\Feature\Schema;

use Database\Seeders\PermissionSeeder;
use Database\Seeders\Testing\TestReferenceSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The shape of the database the migrations build, checked on the test database (which is built
 * from nothing but the migrations and app/Triggers).
 *
 * Run: php artisan test --filter=DatabaseStructureTest
 */
class DatabaseStructureTest extends TestCase
{
    /** Every table in the database, from information_schema of the test database. */
    private function tables(): array
    {
        return DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('TABLE_TYPE', 'BASE TABLE')
            ->pluck('TABLE_NAME')->all();
    }

    /** @return list<string> tables that carry a company_id */
    private function companyTables(): array
    {
        return DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('COLUMN_NAME', 'company_id')
            ->distinct()->pluck('TABLE_NAME')->sort()->values()->all();
    }

    public function test_the_migrations_build_every_table_the_models_use(): void
    {
        $tables = $this->tables();
        $missing = [];

        foreach (glob(app_path('Models/*.php')) as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');

            if (! class_exists($class) || ! is_subclass_of($class, \Illuminate\Database\Eloquent\Model::class) || (new \ReflectionClass($class))->isAbstract()) {
                continue;
            }

            $table = (new $class)->getTable();

            if (! in_array($table, $tables, true)) {
                $missing[] = "{$class} → {$table}";
            }
        }

        $this->assertSame([], $missing, 'Models whose table no migration creates');
    }

    /**
     * Every screen filters a company's records by company_id, so a table without an index on it
     * is read in full for every page.
     */
    public function test_every_company_owned_table_is_indexed_by_company(): void
    {
        $unindexed = [];

        foreach ($this->companyTables() as $table) {
            $leading = DB::table('information_schema.STATISTICS')
                ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->where('COLUMN_NAME', 'company_id')
                ->where('SEQ_IN_INDEX', 1)
                ->exists();

            if (! $leading) {
                $unindexed[] = $table;
            }
        }

        $this->assertSame([], $unindexed);
    }

    /** Account type 27 is the current account in the code (MoneyReceived, the LG screens). */
    public function test_the_account_types_the_code_relies_on_are_there_with_their_ids(): void
    {
        $this->assertSame('current-account', DB::table('account_types')->where('id', 27)->value('slug'));

        foreach (\App\Models\AccountType::OVERDRAFT_ACCOUNT_SLUGS as $slug) {
            $this->assertTrue(DB::table('account_types')->where('slug', $slug)->exists(), $slug);
        }

        $this->assertTrue(DB::table('account_types')->where('slug', \App\Models\AccountType::MEDIUM_TERM_LOAN)->exists());
    }

    /** Running the starting data twice must not add or change anything. */
    public function test_the_reference_data_and_permissions_can_be_seeded_twice(): void
    {
        $tables = ['account_types', 'currencies', 'sections', 'tables_fields', 'permissions', 'roles', 'role_has_permissions'];
        $before = array_map(fn ($t) => DB::table($t)->count(), array_combine($tables, $tables));

        $this->seed(TestReferenceSeeder::class);
        $this->seed(PermissionSeeder::class);

        $after = array_map(fn ($t) => DB::table($t)->count(), array_combine($tables, $tables));

        $this->assertSame($before, $after);
    }

    /** Every permission the code can check exists, so granting it is possible. */
    public function test_every_registry_permission_exists(): void
    {
        $missing = array_diff(\App\Support\Permissions\PermissionRegistry::seedableNames(), DB::table('permissions')->pluck('name')->all());

        $this->assertSame([], array_values($missing));
    }
}
