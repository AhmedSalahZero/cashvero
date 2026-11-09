<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * `php artisan cashvero:prepare-test-db`
 * ==================================================================
 * Builds the databases `php artisan test` runs against, from nothing.
 *
 * WHAT IT BUILDS
 *   cashvero_test          every table (migrations) + every trigger, procedure and event
 *                          (app/Triggers/Cashvero) + the fixed reference lists the app needs
 *                          (TestReferenceSeeder: account types, currencies, sections, import
 *                          fields, permissions/roles). NO company, user, bank or movement —
 *                          every test makes the records it needs itself.
 *   cashvero_test_source   the same tables, empty of business data, for the tests of the
 *                          "import a company from another system" feature.
 *   cashvero_test_scratch  an empty database for the few tests that build their own small
 *                          tables (see tests/Support/UsesFixtureDatabase.php).
 *   cashvero_tester        the MySQL user the tests connect as. It can read and write those
 *                          three databases and NOTHING else, so no test can ever read or change
 *                          your real database, even by mistake.
 *
 * NOTHING IS COPIED FROM ANY OTHER DATABASE. The build only reads files that are in the project.
 *
 * SAFETY
 *   - The database names are fixed in this file; it cannot be pointed at another database.
 *   - It refuses to run in production, or against a MySQL server that is not on this machine.
 *   - It connects with the account in your .env file (it needs to create databases, triggers
 *     and the test user); the tests themselves never use that account.
 *
 * WHEN TO RUN IT
 *   You normally don't: tests/bootstrap.php runs it with --if-stale before every test run, which
 *   rebuilds only when a migration, a trigger file or the reference data changed. Run it by hand
 *   (without --if-stale) any time the test database looks broken.
 */
class PrepareTestDatabaseCommand extends Command
{
    /** The only databases this command is allowed to touch. */
    public const TEST_DATABASE = 'cashvero_test';

    public const SOURCE_DATABASE = 'cashvero_test_source';

    public const SCRATCH_DATABASE = 'cashvero_test_scratch';

    /**
     * The account the tests connect as (phpunit.xml). A local account that can only reach the
     * three test databases, so its password protects nothing and lives in the project on purpose.
     */
    public const TEST_USER = 'cashvero_tester';

    public const TEST_PASSWORD = 'cashvero_tester_local_only';

    protected $signature = 'cashvero:prepare-test-db
                            {--if-stale : Rebuild only when migrations, triggers or reference data changed since the last build}';

    protected $description = 'Build the cashvero_test databases (tables, triggers, reference data) and the test-only MySQL user';

    public function handle(): int
    {
        if (! $this->safeToRun()) {
            return self::FAILURE;
        }

        $fingerprint = $this->fingerprint();

        if ($this->option('if-stale') && $this->builtFingerprint() === $fingerprint) {
            $leftovers = $this->leftovers();

            if ($leftovers === []) {
                $this->ensureTestUser();
                $this->line('Test database is up to date.');

                return self::SUCCESS;
            }

            // Every test runs in a rolled-back transaction, so a company or user still here was
            // committed by a test that escaped it. Start from a clean database rather than let the
            // next run see it.
            $this->warn('The test database still holds records a previous run left behind ('.implode(', ', $leftovers).'); rebuilding it clean.');
        }

        $this->info("Building the test database '".self::TEST_DATABASE."' from the project files (no other database is read)...");

        if (! $this->recreateDatabases()) {
            return self::FAILURE;
        }

        config(['database.connections.mysql.database' => self::TEST_DATABASE]);
        DB::purge('mysql');

        if ($this->call('migrate', ['--database' => 'mysql', '--force' => true]) !== self::SUCCESS) {
            $this->error('Building the tables failed — see the message above.');

            return self::FAILURE;
        }

        if (! $this->installTriggers()) {
            return self::FAILURE;
        }

        if ($this->call('db:seed', ['--class' => \Database\Seeders\Testing\TestReferenceSeeder::class, '--database' => 'mysql', '--force' => true]) !== self::SUCCESS) {
            $this->error('Filling in the reference data failed — see the message above.');

            return self::FAILURE;
        }

        $this->buildSourceTwin();
        $this->ensureTestUser();

        DB::statement('CREATE TABLE `test_schema_fingerprint` (`fingerprint` char(64) NOT NULL, `built_at` datetime NOT NULL)');
        DB::table('test_schema_fingerprint')->insert(['fingerprint' => $fingerprint, 'built_at' => now()]);

        $this->newLine();
        $this->info('Done. The test databases are ready — now run: php artisan test');

        return self::SUCCESS;
    }

    /* ───────────────────────── safety ───────────────────────── */

    private function safeToRun(): bool
    {
        if (app()->environment('production')) {
            $this->error('Refusing to run in production.');

            return false;
        }

        $host = (string) config('database.connections.mysql.host');

        if (! in_array($host, ['127.0.0.1', 'localhost', '::1', ''], true)) {
            $this->error("Refusing to run: your .env points at the MySQL server '{$host}', which is not this machine. The test databases are only ever built locally.");

            return false;
        }

        return true;
    }

    /* ───────────────────────── staleness ───────────────────────── */

    /** One hash over every file the build reads. Any change to any of them means "rebuild". */
    private function fingerprint(): string
    {
        $files = array_merge(
            glob(database_path('migrations/*.php')) ?: [],
            glob(app_path('Triggers/Cashvero/*.sql')) ?: [],
            glob(database_path('seeders/Testing/*.php')) ?: [],
            [
                database_path('seeders/data/cash_vero_general.php'),
                database_path('seeders/data/tables_fields.php'),
                database_path('seeders/PermissionSeeder.php'),
                app_path('Support/Permissions/PermissionRegistry.php'),
                __FILE__,
            ],
        );
        sort($files);

        $context = hash_init('sha256');
        foreach ($files as $file) {
            hash_update($context, basename($file)."\0".hash_file('sha256', $file)."\0");
        }

        return hash_final($context);
    }

    private function builtFingerprint(): ?string
    {
        try {
            $row = DB::selectOne('SELECT `fingerprint` FROM `'.self::TEST_DATABASE.'`.`test_schema_fingerprint` LIMIT 1');

            return $row->fingerprint ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * A freshly built test database has no company and no user (only reference lists), so any
     * found here was left by a test.
     *
     * @return list<string>
     */
    private function leftovers(): array
    {
        $found = [];

        foreach (['companies', 'users'] as $table) {
            $count = (int) DB::selectOne('SELECT COUNT(*) AS c FROM `'.self::TEST_DATABASE.'`.`'.$table.'`')->c;

            if ($count > 0) {
                $found[] = "{$count} {$table}";
            }
        }

        return $found;
    }

    /* ───────────────────────── build ───────────────────────── */

    /**
     * Dropping the whole databases (not just the tables) also removes the stored procedures the
     * migrations create, which a plain "migrate:fresh" would leave behind and then fail on.
     * Written out in full on purpose: the names are fixed.
     */
    private function recreateDatabases(): bool
    {
        config(['database.connections.mysql.database' => null]);
        DB::purge('mysql');

        try {
            DB::statement('DROP DATABASE IF EXISTS `cashvero_test`');
            DB::statement('CREATE DATABASE `cashvero_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            DB::statement('DROP DATABASE IF EXISTS `cashvero_test_source`');
            DB::statement('CREATE DATABASE `cashvero_test_source` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            DB::statement('DROP DATABASE IF EXISTS `cashvero_test_scratch`');
            DB::statement('CREATE DATABASE `cashvero_test_scratch` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        } catch (\Throwable $e) {
            $this->error('Could not create the test databases: '.$e->getMessage());
            $this->line('Check that MySQL is running and that DB_USERNAME / DB_PASSWORD in your .env file can create databases.');

            return false;
        }

        return true;
    }

    /**
     * Load every app/Triggers/Cashvero/*.sql file, then check that every trigger those files
     * define really exists. A statement that fails is reported; a missing trigger stops the build,
     * because the tests of statements, invoices and loans depend on them.
     */
    private function installTriggers(): bool
    {
        $files = glob(app_path('Triggers/Cashvero/*.sql')) ?: [];
        sort($files);

        $loaded = 0;
        $expected = [];
        $problems = [];

        foreach ($files as $file) {
            // Safe to run unprepared: these are the project's own fixed .sql files, never user input.
            foreach ($this->statementsIn((string) file_get_contents($file)) as $statement) {
                if (preg_match('/^\s*create\s+(?:definer\s*=\s*\S+\s+)?trigger\s+(?:if\s+not\s+exists\s+)?`?(\w+)`?/i', $statement, $m)) {
                    $expected[strtolower($m[1])] = basename($file);
                }

                try {
                    DB::unprepared($statement);
                    $loaded++;
                } catch (\Throwable $e) {
                    $problems[basename($file)][] = substr($e->getMessage(), 0, 160);
                }
            }
        }

        $installed = array_map('strtolower', DB::table('information_schema.TRIGGERS')
            ->where('TRIGGER_SCHEMA', self::TEST_DATABASE)->pluck('TRIGGER_NAME')->all());
        $missing = array_diff(array_keys($expected), $installed);

        $this->info("Database triggers and procedures: {$loaded} statements loaded from ".count($files).' files, '
            .count($installed).' triggers installed.');

        foreach ($problems as $name => $messages) {
            $this->warn("  {$name}: ".count($messages).' statement(s) could not be loaded — '.$messages[0]);
        }

        if ($missing !== []) {
            $this->error('These triggers are defined in app/Triggers/Cashvero but could not be installed: '.implode(', ', $missing));

            return false;
        }

        // The test user may not read the trigger list (that needs the TRIGGER right, which would
        // also let it drop them), so the names are written down here for the isolation test.
        DB::statement('CREATE TABLE `test_schema_triggers` (`name` varchar(64) NOT NULL PRIMARY KEY)');
        DB::table('test_schema_triggers')->insert(array_map(fn ($name) => ['name' => $name], $installed));

        return true;
    }

    /** The same tables under another name, for the tests that read a "source" database. */
    private function buildSourceTwin(): void
    {
        $count = 0;

        foreach (DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', self::TEST_DATABASE)->where('TABLE_TYPE', 'BASE TABLE')->pluck('TABLE_NAME') as $table) {
            // Table names come from information_schema of the database this command just built.
            DB::statement('CREATE TABLE `cashvero_test_source`.`'.$table.'` LIKE `cashvero_test`.`'.$table.'`');
            $count++;
        }

        $this->info("Source database '".self::SOURCE_DATABASE."' built ({$count} empty tables).");
    }

    /**
     * The account the tests connect as. Created once; its rights are re-applied every time so a
     * rebuilt database is reachable again. It gets no right on any other database.
     */
    private function ensureTestUser(): void
    {
        $user = "'".self::TEST_USER."'@'localhost'";

        DB::statement("CREATE USER IF NOT EXISTS {$user} IDENTIFIED BY '".self::TEST_PASSWORD."'");
        DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE ON `cashvero_test`.* TO {$user}");
        DB::statement("GRANT SELECT, INSERT, UPDATE, DELETE ON `cashvero_test_source`.* TO {$user}");
        DB::statement("GRANT ALL PRIVILEGES ON `cashvero_test_scratch`.* TO {$user}");
    }

    /* ───────────────────────── .sql parsing ───────────────────────── */

    /**
     * Splits a mysql-client script into statements, honouring DELIMITER.
     * (PDO does not understand the DELIMITER directive, so it is handled here.)
     *
     * A few of the project's .sql files forget the "delimiter //" line in front of a trigger that
     * ends with "end //". When we meet such a trigger/procedure while the delimiter is still ";",
     * and its body does end with "end //", we read it with "//" as the delimiter, as intended.
     *
     * @return list<string>
     */
    private function statementsIn(string $sql): array
    {
        $delimiter = ';';
        $buffer = '';
        $statements = [];
        $lines = preg_split('/\R/u', $sql);

        foreach ($lines as $index => $line) {
            if (preg_match('/^\s*delimiter\s+(\S+)\s*$/i', $line, $m)) {
                $delimiter = $m[1];

                continue;
            }

            // Whole-line comments are dropped: the Arabic notes in these files can contain a ';' or '//',
            // which would otherwise be mistaken for the end of a statement.
            if (preg_match('/^\s*(--|#)/', $line)) {
                continue;
            }

            if ($delimiter === ';' && trim($buffer) === ''
                && preg_match('/^\s*create\s+(definer\s*=\s*\S+\s+)?(trigger|procedure|event)\b/i', $line)
                && $this->bodyEndsWithDoubleSlash($lines, $index)) {
                $delimiter = '//';
            }

            $buffer .= $line."\n";

            while (($at = strpos($buffer, $delimiter)) !== false) {
                $statement = trim(substr($buffer, 0, $at));
                $buffer = substr($buffer, $at + strlen($delimiter));

                if ($statement !== '') {
                    $statements[] = $statement;
                }
            }
        }

        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }

        return $statements;
    }

    /** Does the body that starts at this line finish with "end //" before the next DELIMITER line? */
    private function bodyEndsWithDoubleSlash(array $lines, int $from): bool
    {
        for ($i = $from + 1; $i < count($lines); $i++) {
            if (preg_match('/^\s*delimiter\b/i', $lines[$i])) {
                return false;
            }

            if (preg_match('/^\s*end\s*\/\/\s*$/i', $lines[$i])) {
                return true;
            }
        }

        return false;
    }
}
