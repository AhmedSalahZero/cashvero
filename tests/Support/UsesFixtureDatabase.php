<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;

/**
 * For tests that build their OWN small copies of real tables (Schema::create ...) and drop them again.
 *
 * Those tests must never run inside `cashvero_test`: that database holds the real, fully built
 * tables the other tests rely on, and "drop my little copy of customer_invoices" would delete the
 * real one. Everything that follows (including the security tests) would then fail with
 * "Table ... doesn't exist".
 *
 * This trait moves the test onto a separate throw-away database, `cashvero_test_scratch`, which is
 * created on demand. The name still contains "test", so the existing "refusing to run on a
 * non-test database" checks keep working.
 *
 * Laravel runs setUpUsesFixtureDatabase() automatically while the app boots, before the test's
 * own setUp() code, and the app (and so this setting) is rebuilt for every test - nothing leaks.
 */
trait UsesFixtureDatabase
{
    protected function setUpUsesFixtureDatabase(): void
    {
        // Connect without a database first, so the scratch one can be created if it is missing.
        config(['database.connections.mysql.database' => null]);
        DB::purge('mysql');
        DB::statement('CREATE DATABASE IF NOT EXISTS `cashvero_test_scratch` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        config(['database.connections.mysql.database' => 'cashvero_test_scratch']);
        DB::purge('mysql');
    }
}
