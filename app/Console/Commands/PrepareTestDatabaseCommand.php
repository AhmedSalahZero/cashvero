<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * `php artisan cashvero:prepare-test-db`
 * ==================================================================
 * Builds the database that `php artisan test` runs against.
 *
 * WHY THIS EXISTS
 * The test suite is pointed at a database called `cashvero_test`
 * (phpunit.xml). Creating an EMPTY database in MySQL is not enough —
 * it also needs every table and the starting reference data, otherwise
 * hundreds of tests fail with "Table 'cashvero_test.companies' doesn't
 * exist". This command does that in one step: it builds the tables from
 * the migrations and fills in the starting data (companies, roles,
 * permissions, banks, currencies...) from the seeders.
 *
 * SAFETY
 * The database name is fixed in this file. The command can only ever
 * wipe and rebuild `cashvero_test`; it cannot be pointed at your real
 * `cashvero` database, whatever is written in your .env file.
 *
 * Safe to run as often as you like. Run it:
 *   • once, before the first `php artisan test`
 *   • again after you add a new migration (a new database change)
 *   • any time the test database looks broken
 */
class PrepareTestDatabaseCommand extends Command
{
    /** The only database this command is allowed to touch. */
    private const TEST_DATABASE = 'cashvero_test';

    protected $signature = 'cashvero:prepare-test-db {--from= : Name of your development database to copy the data from (for example --from=cashvero)}';

    protected $description = 'Wipe and rebuild the cashvero_test database (tables + data + triggers) so php artisan test can run';

    public function handle(): int
    {
        $db = self::TEST_DATABASE;

        $this->info("Rebuilding the test database '{$db}' (your real database is not touched)...");

        // 1. Drop and re-create the empty database. Dropping the whole database (not just the
        //    tables) also removes the stored procedures the migrations create, which a plain
        //    "migrate:fresh" would leave behind and then fail on the second run.
        config(['database.connections.mysql.database' => null]);
        DB::purge('mysql');

        try {
            // Written out in full (no variables) on purpose: the name is fixed, and the security
            // scanner treats any SQL assembled from variables as something to review.
            DB::statement('DROP DATABASE IF EXISTS `cashvero_test`');
            DB::statement('CREATE DATABASE `cashvero_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        } catch (\Throwable $e) {
            $this->error('Could not create the database: '.$e->getMessage());
            $this->line('Check that MySQL is running and that DB_USERNAME / DB_PASSWORD in your .env file can create databases.');

            return self::FAILURE;
        }

        // 2. Point the connection at it. From here on, everything in this run uses cashvero_test.
        config(['database.connections.mysql.database' => $db]);
        DB::purge('mysql');

        // 3. Build the tables, then fill in the starting data.
        $status = $this->call('migrate', ['--database' => 'mysql', '--force' => true]);

        if ($status !== self::SUCCESS) {
            $this->error('Building the tables failed — see the message above.');

            return self::FAILURE;
        }

        $source = (string) $this->option('from');

        if ($source !== '') {
            // Copy the data of your development database, so the tests that look for real
            // companies, banks and invoices find them (otherwise they are "skipped").
            if (! $this->copyDataFrom($source)) {
                return self::FAILURE;
            }

            // Permissions are added (never removed) so roles/permissions introduced after your
            // development database was last updated exist here too.
            $this->call('db:seed', ['--class' => 'PermissionSeeder', '--database' => 'mysql', '--force' => true]);
        } else {
            $status = $this->call('db:seed', ['--database' => 'mysql', '--force' => true]);

            if ($status !== self::SUCCESS) {
                $this->error('Filling in the starting data failed — see the message above.');

                return self::FAILURE;
            }
        }

        // 4. Production also has database triggers (they work out invoice balances, statuses,
        //    statement running balances...). They are kept as .sql files and installed by hand,
        //    not by migrations, so a rebuilt database does not have them until we load them here.
        $this->installTriggers();

        // 5. Switch the CashVero system on for the seeded companies. Without this every page
        //    answers 403, because a company must be subscribed to a system to open its screens.
        $this->enableCashVeroForCompanies();

        // 6. A few tests read from a second ("source") database, as the Odoo import does in production.
        $this->buildSourceTwin();

        // 7. Some tests need records your data may not contain yet (a factoring transaction, a
        //    down-payment cheque, a second company...). Without them those tests are "skipped".
        $this->addSampleData();

        $this->newLine();
        $this->info("Done. '{$db}' is ready — now run: php artisan test");

        return self::SUCCESS;
    }

    /**
     * Load every app/Triggers/Cashvero/*.sql file. One statement that fails is reported and skipped,
     * the rest of the file still loads (a few of the files have an old quirk in them).
     */
    private function installTriggers(): void
    {
        $files = glob(app_path('Triggers/Cashvero/*.sql')) ?: [];
        sort($files);

        $loaded = 0;
        $problems = [];

        foreach ($files as $file) {
            // Safe to run unprepared: these are the project's own fixed .sql files, never user input.
            foreach ($this->statementsIn((string) file_get_contents($file)) as $statement) {
                try {
                    DB::unprepared($statement);
                    $loaded++;
                } catch (\Throwable $e) {
                    $problems[basename($file)] = ($problems[basename($file)] ?? 0) + 1;
                }
            }
        }

        $this->info("Database triggers and procedures: {$loaded} statements loaded from ".count($files).' files.');

        foreach ($problems as $name => $count) {
            $this->warn("  {$name}: {$count} statement(s) could not be loaded (already like that in the file; not blocking)");
        }
    }

    private function enableCashVeroForCompanies(): void
    {
        $now = now();

        foreach (DB::table('companies')->pluck('id') as $companyId) {
            DB::table('company_systems')->updateOrInsert(
                ['company_id' => $companyId, 'system_name' => CASH_VERO],
                ['created_at' => $now, 'updated_at' => $now]
            );
        }

        $this->info('CashVero system switched on for '.DB::table('companies')->count().' companies.');
    }

    /**
     * Copy every table's rows from the development database into cashvero_test. Only columns that exist
     * on both sides are copied, so a small difference between the two databases cannot stop it.
     * Done BEFORE the triggers are installed, so the stored numbers are copied exactly as they are.
     */
    private function copyDataFrom(string $source): bool
    {
        if (! preg_match('/^[A-Za-z0-9_]+$/', $source) || $source === self::TEST_DATABASE) {
            $this->error("'{$source}' is not a valid development database name.");

            return false;
        }

        if (! DB::table('information_schema.SCHEMATA')->where('SCHEMA_NAME', $source)->exists()) {
            $this->error("The database '{$source}' does not exist on this MySQL server.");

            return false;
        }

        $this->info("Copying the data of '{$source}' into '".self::TEST_DATABASE."' (the source is only read)...");

        DB::statement('SET SESSION foreign_key_checks = 0');
        DB::statement("SET SESSION sql_mode = ''");

        $tables = DB::table('information_schema.TABLES')
            ->where('TABLE_SCHEMA', self::TEST_DATABASE)->where('TABLE_TYPE', 'BASE TABLE')
            ->pluck('TABLE_NAME');

        $copied = 0;
        $problems = [];

        foreach ($tables as $table) {
            if ($table === 'migrations') {
                continue;
            }

            $mine = $this->columnsOf(self::TEST_DATABASE, $table);
            $theirs = $this->columnsOf($source, $table);
            $common = array_values(array_intersect($mine, $theirs));

            if ($common === []) {
                continue;
            }

            $list = implode(', ', array_map(fn ($c) => '`'.$c.'`', $common));

            try {
                // Rows the migrations already put there would clash with the copied ones (same ids).
                // Only cleared when the development table really has rows to give.
                if (DB::table($source.'.'.$table)->exists()) {
                    DB::table($table)->delete();
                }

                DB::statement('INSERT INTO `'.self::TEST_DATABASE.'`.`'.$table.'` ('.$list.') SELECT '.$list.' FROM `'.$source.'`.`'.$table.'`');
                $copied++;
            } catch (\Throwable $e) {
                $problems[] = $table.': '.substr($e->getMessage(), 0, 160);
            }
        }

        DB::statement('SET SESSION foreign_key_checks = 1');

        $this->info("Copied {$copied} tables.");

        foreach ($problems as $message) {
            $this->warn('  could not copy '.$message);
        }

        return true;
    }

    /** A full copy of cashvero_test under another name, used by the tests that read a "source" database. */
    private function buildSourceTwin(): void
    {
        try {
            DB::statement('DROP DATABASE IF EXISTS `cashvero_test_source`');
            DB::statement('CREATE DATABASE `cashvero_test_source` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            DB::statement('SET SESSION foreign_key_checks = 0');
            DB::statement("SET SESSION sql_mode = ''");

            $count = 0;

            foreach (DB::table('information_schema.TABLES')->where('TABLE_SCHEMA', self::TEST_DATABASE)->where('TABLE_TYPE', 'BASE TABLE')->pluck('TABLE_NAME') as $table) {
                DB::statement('CREATE TABLE `cashvero_test_source`.`'.$table.'` LIKE `'.self::TEST_DATABASE.'`.`'.$table.'`');
                $columns = $this->columnsOf(self::TEST_DATABASE, $table);
                $list = implode(', ', array_map(fn ($c) => '`'.$c.'`', $columns));
                DB::statement('INSERT INTO `cashvero_test_source`.`'.$table.'` ('.$list.') SELECT '.$list.' FROM `'.self::TEST_DATABASE.'`.`'.$table.'`');
                $count++;
            }

            DB::statement('SET SESSION foreign_key_checks = 1');
            $this->info("Source database 'cashvero_test_source' built ({$count} tables).");
        } catch (\Throwable $e) {
            $this->warn('Could not build the source database (3 import tests will be skipped): '.substr($e->getMessage(), 0, 160));
        }
    }

    /**
     * Adds the few records the tests look for and that a database may not contain. Every block is
     * independent: if one cannot be added it says so and the others still run.
     * Nothing here touches your real database.
     */
    private function addSampleData(): void
    {
        DB::statement('SET SESSION foreign_key_checks = 0');
        DB::statement("SET SESSION sql_mode = ''");

        $ids = DB::table('companies')->orderBy('id')->pluck('id')->all();
        $main = in_array(148, $ids) ? 148 : ($ids[0] ?? null);
        $other = null;

        foreach ($ids as $id) {
            if ($id !== $main) {
                $other = $id;
                break;
            }
        }

        if ($main === null) {
            $this->warn('No company found — sample records not added.');

            return;
        }

        $this->info("Adding sample records (main company {$main}, second company ".($other ?? 'none').')...');

        $blocks = [
            'invoice deductions' => fn () => $this->sampleDeduction($main),
            'supplier down-payment cheque' => fn () => $this->sampleDownPaymentCheque($main),
            'cash expense cheque' => fn () => $this->sampleCashExpenseCheque($main),
            'LGs for a second page' => fn () => $this->sampleManyLgs($main).($other !== null ? ' / second company: '.$this->sampleManyLgs($other) : ''),
            'factoring transactions' => fn () => $this->sampleFactoring($main, $other),
            'fields shown on the invoice forms' => fn () => $this->sampleManagedFields($main),
        ];

        if ($other !== null) {
            $blocks['records in the second company'] = fn () => $this->sampleOtherCompany($main, $other);
        }

        foreach ($blocks as $label => $run) {
            try {
                $this->line('  ✓ '.$label.': '.$run());
            } catch (\Throwable $e) {
                $this->warn('  ✗ '.$label.' not added: '.substr($e->getMessage(), 0, 200));
            }
        }

        DB::statement('SET SESSION foreign_key_checks = 1');
    }

    /** Makes sure the company's invoice forms show the sales person / business sector / business unit fields. */
    private function sampleManagedFields(int $company): string
    {
        $names = ['sales_person' => 'Sales Person', 'business_sector' => 'Business Sector', 'business_unit' => 'Business Unit'];

        foreach (['CustomerInvoice', 'SupplierInvoice'] as $model) {
            foreach ($names as $field => $label) {
                if (! DB::table('tables_fields')->where('model_name', $model)->where('field_name', $field)->exists()) {
                    $this->insertRow('tables_fields', ['model_name' => $model, 'field_name' => $field, 'view_name' => $label]);
                }
            }

            $row = \App\Models\CustomizedFieldsExportation::where('model_name', $model)->where('company_id', $company)->first();
            $fields = array_values(array_unique(array_merge($row ? (array) $row->fields : [], array_keys($names))));

            if ($row) {
                $row->fields = $fields;
                $row->save();
            } else {
                \App\Models\CustomizedFieldsExportation::create(['model_name' => $model, 'company_id' => $company, 'fields' => $fields]);
            }
        }

        return 'selected for both invoice types';
    }

    private function sampleDeduction(int $company): string
    {
        $invoice = DB::table('customer_invoices')->where('company_id', $company)->where('net_balance', '>=', 20)
            ->whereNotExists(fn ($q) => $q->select(DB::raw('1'))->from('invoice_deductions')->whereColumn('invoice_deductions.invoice_id', 'customer_invoices.id')->where('invoice_type', 'CustomerInvoice'))
            ->orderByDesc('net_balance')->first();

        if (! $invoice) {
            $id = $this->insertRow('customer_invoices', ['company_id' => $company, 'invoice_amount' => 1000, 'net_balance' => 1000, 'invoice_date' => now()->format('Y-m-d'), 'invoice_number' => 'SAMPLE-DED-1']);
            $invoice = DB::table('customer_invoices')->find($id);
        }

        $deduction = DB::table('deductions')->where('company_id', $company)->first();
        $deductionId = $deduction->id ?? $this->insertRow('deductions', ['name' => 'Sample deduction', 'company_id' => $company]);
        $amount = round((float) $invoice->net_balance * 0.75, 2);

        $this->insertRow('invoice_deductions', ['invoice_id' => $invoice->id, 'invoice_type' => 'CustomerInvoice', 'deduction_id' => $deductionId, 'amount' => $amount, 'date' => now()->format('Y-m-d'), 'company_id' => $company]);

        // If the trigger is not installed the stored balance must be brought in line by hand.
        $fresh = DB::table('customer_invoices')->find($invoice->id);

        if ((float) $fresh->total_deductions == 0.0) {
            DB::table('customer_invoices')->where('id', $invoice->id)->update(['total_deductions' => $amount, 'net_balance' => round((float) $invoice->net_balance - $amount, 2)]);
        }

        return "invoice {$invoice->id} now carries a deduction of {$amount}";
    }

    private function sampleDownPaymentCheque(int $company): string
    {
        $table = (new \App\Models\MoneyPayment)->getTable();
        $overrides = ['company_id' => $company, 'type' => \App\Models\MoneyPayment::PAYABLE_CHEQUE, 'money_type' => 'down-payment', 'partner_type' => 'is_supplier'];
        $existing = DB::table($table)->where('company_id', $company)->first();

        $id = $existing ? $this->cloneRow($table, $existing->id, $overrides) : $this->insertRow($table, $overrides);

        return "payment {$id}";
    }

    private function sampleCashExpenseCheque(int $company): string
    {
        $table = (new \App\Models\CashExpense)->getTable();
        $overrides = ['company_id' => $company, 'type' => \App\Models\CashExpense::PAYABLE_CHEQUE, 'partner_type' => null];
        $existing = DB::table($table)->where('company_id', $company)->first();

        $id = $existing ? $this->cloneRow($table, $existing->id, $overrides) : $this->insertRow($table, $overrides);

        return "expense {$id}";
    }

    private function sampleManyLgs(int $company): string
    {
        $table = (new \App\Models\LetterOfGuaranteeIssuance)->getTable();
        $have = DB::table($table)->where('company_id', $company)->where('lg_type', 'final-lgs')->count();
        $template = DB::table($table)->where('company_id', $company)->first() ?? DB::table($table)->whereNotNull('company_id')->first();
        $added = 0;
        $lastProblem = '';

        for ($i = $have; $i < 45; $i++) {
            $overrides = ['company_id' => $company, 'lg_type' => 'final-lgs', 'renewal_date' => now()->addMonths(6)->format('Y-m-d')];
            try {
                $template ? $this->cloneRow($table, $template->id, $overrides) : $this->insertRow($table, $overrides);
                $added++;
            } catch (\Throwable $e) {
                $lastProblem = substr($e->getMessage(), 0, 120);
            }
        }

        return "{$added} added".($lastProblem !== '' ? " (some could not be copied: {$lastProblem})" : '');
    }

    private function sampleFactoring(int $company, ?int $other): string
    {
        $table = (new \App\Models\FactoringTransaction)->getTable();
        $added = 0;

        foreach ([$company, $other] as $target) {
            if ($target === null) {
                continue;
            }

            foreach ([\App\Models\FactoringTransaction::WITH_RECOURSE, \App\Models\FactoringTransaction::WITHOUT_RECOURSE] as $recourse) {
                if (DB::table($table)->where('company_id', $target)->where('recourse_type', $recourse)->exists()) {
                    continue;
                }

                $invoiceId = DB::table('customer_invoices')->where('company_id', $target)
                    ->whereNotIn('id', DB::table($table)->select('customer_invoice_id'))->value('id')
                    ?? (900000 + $target * 10 + $added);

                $this->insertRow($table, [
                    'company_id' => $target, 'recourse_type' => $recourse, 'factoring_date' => now()->format('Y-m-d'),
                    'customer_invoice_id' => $invoiceId, 'invoice_currency' => 'EGP', 'invoice_amount' => 1000,
                    'factoring_percentage' => 80, 'factoring_amount' => 800, 'received_amount' => 790,
                    'account_number' => 'SAMPLE-1',
                ]);
                $added++;
            }
        }

        return "{$added} added";
    }

    /** Copies one record of each kind to the second company, so "another company's record" can be tested. */
    private function sampleOtherCompany(int $main, int $other): string
    {
        $done = [];

        foreach ([\App\Models\LcSettlementInternalMoneyTransfer::class, \App\Models\MultipleCashExpense::class, \App\Models\LetterOfCreditIssuance::class] as $class) {
            $table = (new $class)->getTable();

            if (DB::table($table)->where('company_id', $other)->exists()) {
                continue;
            }

            $template = DB::table($table)->whereNotNull('company_id')->first();

            if (! $template) {
                $this->insertRow($table, ['company_id' => $other]);
            } else {
                $this->cloneRow($table, $template->id, ['company_id' => $other]);
            }

            $done[] = class_basename($class);
        }

        return $done === [] ? 'already there' : implode(', ', $done);
    }

    /** Insert a row, filling any required column that is not given with a harmless placeholder. */
    private function insertRow(string $table, array $values): int
    {
        $row = [];
        $known = [];

        foreach (DB::table('information_schema.COLUMNS')->where('TABLE_SCHEMA', self::TEST_DATABASE)->where('TABLE_NAME', $table)->orderBy('ORDINAL_POSITION')->get() as $c) {
            $name = $c->COLUMN_NAME;
            $known[] = $name;

            if (array_key_exists($name, $values)) {
                $row[$name] = $values[$name];
            } elseif (str_contains($c->EXTRA, 'auto_increment') || str_contains($c->EXTRA, 'GENERATED')) {
                continue;
            } elseif (in_array($name, ['created_at', 'updated_at'], true)) {
                $row[$name] = now();
            } elseif ($c->IS_NULLABLE === 'NO' && $c->COLUMN_DEFAULT === null) {
                $row[$name] = $this->placeholderFor($c);
            }
        }

        return (int) DB::table($table)->insertGetId($row);
    }

    private function placeholderFor(object $c)
    {
        $type = strtolower($c->DATA_TYPE);

        return match (true) {
            in_array($type, ['int', 'bigint', 'smallint', 'mediumint', 'tinyint', 'decimal', 'float', 'double'], true) => 0,
            $type === 'date' => now()->format('Y-m-d'),
            in_array($type, ['datetime', 'timestamp'], true) => now()->format('Y-m-d H:i:s'),
            $type === 'time' => '00:00:00',
            $type === 'year' => (int) now()->format('Y'),
            $type === 'json' => '[]',
            in_array($type, ['enum', 'set'], true) => (preg_match("/'([^']*)'/", $c->COLUMN_TYPE, $m) ? $m[1] : ''),
            default => 'sample',
        };
    }

    /** Copy an existing row (new id) with some values replaced. */
    private function cloneRow(string $table, int $id, array $overrides): int
    {
        $row = (array) DB::table($table)->find($id);
        $allowed = $this->columnsOf(self::TEST_DATABASE, $table);

        $row = array_intersect_key($row, array_flip($allowed));
        unset($row['id']);

        // A copy must not repeat values that have to be unique (serial numbers, codes...).
        static $counter = 0;
        $counter++;

        foreach (DB::table('information_schema.STATISTICS')->where('TABLE_SCHEMA', self::TEST_DATABASE)
            ->where('TABLE_NAME', $table)->where('NON_UNIQUE', 0)->where('INDEX_NAME', '<>', 'PRIMARY')
            ->pluck('COLUMN_NAME')->unique() as $column) {
            if ($column !== 'company_id' && isset($row[$column]) && is_string($row[$column]) && ! array_key_exists($column, $overrides)) {
                $row[$column] = $row[$column].'-'.$counter;
            }
        }

        foreach ($overrides as $column => $value) {
            if (in_array($column, $allowed, true)) {
                $row[$column] = $value;
            }
        }

        return (int) DB::table($table)->insertGetId($row);
    }

    /** @return list<string> normal (not generated) column names of a table, in order */
    private function columnsOf(string $schema, string $table): array
    {
        return DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', $schema)->where('TABLE_NAME', $table)
            ->where('EXTRA', 'not like', '%GENERATED%')
            ->orderBy('ORDINAL_POSITION')
            ->pluck('COLUMN_NAME')->all();
    }

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
