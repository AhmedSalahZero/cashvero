<?php

namespace Tests\Feature\Isolation;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The test suite is sealed off from every real database.
 *
 * Three walls, each checked here:
 *   1. The connection: tests use cashvero_test as cashvero_tester (phpunit.xml).
 *   2. MySQL itself: cashvero_tester has no right on any other database, so even a test that
 *      names `cashvero.companies` outright is refused by the server.
 *   3. The code: no test points the app at another database, a hard-coded record of real data,
 *      or a file outside the project.
 *
 * Run: php artisan test --filter=TestIsolationTest
 */
class TestIsolationTest extends TestCase
{
    public function test_the_suite_is_connected_to_the_test_database_as_the_test_user(): void
    {
        $this->assertSame('cashvero_test', DB::connection()->getDatabaseName());
        $this->assertSame('cashvero_tester', config('database.connections.mysql.username'));
        $this->assertSame('cashvero_test_source', DB::connection('mysql_source')->getDatabaseName());
        $this->assertStringStartsWith('cashvero_tester@', (string) DB::selectOne('SELECT CURRENT_USER() AS u')->u);
    }

    /** @return array<string, array{string}> */
    public static function realDatabases(): array
    {
        return [
            'development database' => ['cashvero'],
            'Odoo import source' => ['veroanalysis'],
            'CFO database' => ['vero_cfo'],
            'MySQL system tables' => ['mysql'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('realDatabases')]
    public function test_mysql_refuses_the_test_user_on_a_real_database(string $database): void
    {
        try {
            DB::select("SELECT 1 FROM `{$database}`.`users` LIMIT 1");
            $this->fail("The test user could read the database '{$database}'.");
        } catch (\Illuminate\Database\QueryException $e) {
            // 1142 = command denied, 1044 = access denied to database, 1146 = no such table
            // (MySQL hides tables the user may not see).
            $this->assertContains((int) ($e->errorInfo[1] ?? 0), [1142, 1044, 1146], $e->getMessage());
        }
    }

    public function test_the_test_user_cannot_change_the_shape_of_the_test_database(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::statement('CREATE TABLE `isolation_probe` (`id` int)');
    }

    public function test_every_trigger_and_procedure_is_installed(): void
    {
        $definedInFiles = [];
        foreach (glob(app_path('Triggers/Cashvero/*.sql')) as $file) {
            preg_match_all('/^\s*create\s+(?:definer\s*=\s*\S+\s+)?trigger\s+`?(\w+)`?/im', (string) file_get_contents($file), $m);
            foreach ($m[1] as $name) {
                $definedInFiles[strtolower($name)] = true;
            }
        }

        // Written by cashvero:prepare-test-db from the real trigger list (the test user may not
        // read information_schema.TRIGGERS itself).
        $installed = array_map('strtolower', DB::table('test_schema_triggers')->pluck('name')->all());

        $this->assertNotEmpty($definedInFiles);
        $this->assertSame([], array_values(array_diff(array_keys($definedInFiles), $installed)), 'Triggers missing from the test database');
        $this->assertGreaterThanOrEqual(16, DB::table('information_schema.ROUTINES')->where('ROUTINE_SCHEMA', 'cashvero_test')->count());
    }

    public function test_the_test_database_starts_with_reference_lists_only(): void
    {
        // Committed rows only: this test's own transaction has written nothing yet.
        $this->assertSame(0, DB::table('companies')->count(), 'A company is stored in the test database outside any test.');
        $this->assertSame(0, DB::table('users')->count(), 'A user is stored in the test database outside any test.');
        $this->assertGreaterThan(0, DB::table('account_types')->count());
        $this->assertGreaterThan(0, DB::table('permissions')->count());
    }

    public function test_no_test_can_reach_a_real_odoo(): void
    {
        $this->assertInstanceOf(\Tests\Fakes\FakeOdooClientFactory::class, app(\App\Services\Api\OdooClientFactory::class));

        $this->assertSame([], $this->filesMatching('/ripcord::client\s*\(/', app_path(), ['OdooClientFactory.php']),
            'Odoo is opened somewhere other than OdooClientFactory, where tests cannot replace it.');
    }

    /**
     * Nothing in tests/ may point at a real database, a record that only exists in real data,
     * or a file outside the project.
     */
    public function test_no_test_points_at_real_data(): void
    {
        $patterns = [
            'the old development-database switch' => '/SMOKE_DB/',
            'a real database by name' => "/['\"`](cashvero|veroanalysis|vero_cfo)['\"`.]/",
            'a copy of real data' => '/--from=/',
            'a file outside the project' => '#[\'"]/(media|home|mnt)/#',
            // A test may number its OWN rows however it likes; what it may not do is name a company or
            // a record that only exists in the real data.
            'a real company by number' => '/\bCOMPANY\s*=\s*(92|148|154)\b|company_id[\'"]?\s*(=>|=|,)\s*(92|148|154)\b|Company::find\(\s*(92|148|154)\b/',
            'a real record by number' => '/::find(OrFail)?\(\s*1537\b/',
        ];

        $found = [];
        foreach ($patterns as $what => $pattern) {
            foreach ($this->filesMatching($pattern, base_path('tests'), ['TestIsolationTest.php', 'e2e']) as $file) {
                $found[] = "{$file}: {$what}";
            }
        }

        $this->assertSame([], $found, "These test files still depend on real data:\n  ".implode("\n  ", $found));
    }

    /** @return list<string> project-relative paths of PHP files whose code matches */
    private function filesMatching(string $pattern, string $directory, array $skip = []): array
    {
        $matches = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            $path = str_replace('\\', '/', $file->getPathname());

            if (! str_ends_with($path, '.php') || str_contains($path, '/dompdf/')) {
                continue;
            }

            foreach ($skip as $part) {
                if (str_contains($path, $part)) {
                    continue 2;
                }
            }

            if (preg_match($pattern, (string) file_get_contents($path))) {
                $matches[] = substr($path, strlen(base_path()) + 1);
            }
        }

        sort($matches);

        return $matches;
    }
}
