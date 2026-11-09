<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

/**
 * Guard: a test that creates or drops tables must run on the scratch database.
 *
 * Why: such a test used to run inside `cashvero_test` and left it without its real tables, which
 * made unrelated tests (security, reports...) fail with "Table ... doesn't exist" depending on
 * the order they ran in. If this test fails, add `use \Tests\Support\UsesFixtureDatabase;` to the
 * class it names (see that file for the explanation).
 */
class FixtureIsolationGuardTest extends TestCase
{
    /** Files that touch only a private probe table and may stay as they are. */
    private const ALLOWED = [];

    public function test_tests_that_create_or_drop_tables_run_on_the_scratch_database(): void
    {
        $offenders = [];
        $root = dirname(__DIR__);
        $schemaCall = '/Schema::(create|drop|dropIfExists)\s*\(/';

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

        foreach ($files as $file) {
            $path = str_replace('\\', '/', $file->getPathname());

            if (! str_ends_with($path, '.php') || str_contains($path, '/Support/') || str_ends_with($path, 'FixtureIsolationGuardTest.php')) {
                continue;
            }

            $relative = substr($path, strlen(str_replace('\\', '/', $root)) + 1);

            if (in_array(preg_replace('#^Feature/#', '', $relative), self::ALLOWED, true)) {
                continue;
            }

            $code = file_get_contents($path);

            if (preg_match($schemaCall, $code) && ! str_contains($code, 'UsesFixtureDatabase') && ! str_contains($code, 'LgSchemaFixture')) {
                $offenders[] = $relative;
            }
        }

        $this->assertSame([], $offenders, "These tests create/drop tables but do not use the scratch database:\n  ".implode("\n  ", $offenders));
    }
}
