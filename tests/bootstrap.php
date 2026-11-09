<?php

/**
 * Runs once, before the first test.
 *
 * Makes sure the test databases exist and match the project: if a migration, a trigger file or
 * the reference data changed since the last build, `cashvero:prepare-test-db` rebuilds them
 * (about a minute); otherwise it only checks and returns (about a second).
 *
 * It runs as a separate process with the database settings of your .env file, because building
 * needs an account that can create databases and triggers — something the test account
 * (cashvero_tester) deliberately cannot do. The command can only ever touch the cashvero_test*
 * databases on this machine.
 *
 * Set SKIP_TEST_DB_PREPARE=1 to skip the check (for example when running a single static test
 * without MySQL).
 */

require __DIR__.'/../vendor/autoload.php';

if (getenv('SKIP_TEST_DB_PREPARE') === '1') {
    return;
}

$process = new Symfony\Component\Process\Process(
    [PHP_BINARY, 'artisan', 'cashvero:prepare-test-db', '--if-stale'],
    dirname(__DIR__),
    // false = remove: the build must use your .env account, never the test one.
    ['APP_ENV' => false, 'DB_DATABASE' => false, 'DB_USERNAME' => false, 'DB_PASSWORD' => false, 'DB_HOST' => false],
    null,
    900,
);

$process->run(function ($type, $output) {
    if (! str_contains($output, 'up to date')) {
        fwrite(STDERR, $output);
    }
});

if (! $process->isSuccessful()) {
    fwrite(STDERR, "\nThe test databases could not be prepared (see above). No test was run.\n");
    exit(1);
}
