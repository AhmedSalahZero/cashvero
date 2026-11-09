<?php

namespace Tests\Feature\Console;

use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\MakesBanks;
use Tests\Concerns\MakesFacilities;
use Tests\TestCase;

/**
 * The console commands people run on the server.
 *
 *   - The ones that destroy data never do it without an explicit yes (or --force).
 *   - Deleting a company removes everything it owned from every table, and nothing of anyone else.
 *   - The read-only checks change nothing; the fixers change nothing the second time.
 *
 * Run: php artisan test --filter=ConsoleCommandsTest
 */
class ConsoleCommandsTest extends TestCase
{
    use MakesBanks;
    use MakesFacilities;

    /** @return list<string> every table that has a company_id column */
    private function companyTables(): array
    {
        return DB::table('information_schema.COLUMNS')
            ->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())
            ->where('COLUMN_NAME', 'company_id')
            ->distinct()->orderBy('TABLE_NAME')->pluck('TABLE_NAME')->all();
    }

    /**
     * One row of this company in every table that will take a row made of placeholders. Each
     * insert runs in its own savepoint, so a table that refuses (a trigger, a foreign key) is
     * simply left out.
     *
     * @return list<string> the tables that now hold a row of the company
     */
    private function plantInEveryTable(Company $company): array
    {
        $planted = [];

        foreach ($this->companyTables() as $table) {
            try {
                DB::transaction(function () use ($table, $company) {
                    DB::table($table)->insert($this->placeholderRow($table, $company->id));
                });
                $planted[] = $table;
            } catch (\Throwable) {
            }
        }

        return $planted;
    }

    private function placeholderRow(string $table, int $companyId): array
    {
        $row = ['company_id' => $companyId];

        foreach (DB::table('information_schema.COLUMNS')->where('TABLE_SCHEMA', DB::connection()->getDatabaseName())->where('TABLE_NAME', $table)->get() as $c) {
            if ($c->COLUMN_NAME === 'company_id' || str_contains($c->EXTRA, 'auto_increment') || str_contains($c->EXTRA, 'GENERATED')
                || $c->IS_NULLABLE === 'YES' || $c->COLUMN_DEFAULT !== null) {
                continue;
            }

            $row[$c->COLUMN_NAME] = match (true) {
                in_array($c->DATA_TYPE, ['int', 'bigint', 'smallint', 'mediumint', 'tinyint', 'decimal', 'float', 'double'], true) => 0,
                $c->DATA_TYPE === 'date' => '2020-01-01',
                in_array($c->DATA_TYPE, ['datetime', 'timestamp'], true) => '2020-01-01 00:00:00',
                $c->DATA_TYPE === 'json' => '[]',
                in_array($c->DATA_TYPE, ['enum', 'set'], true) => preg_match("/'([^']*)'/", $c->COLUMN_TYPE, $m) ? $m[1] : '',
                default => 'test',
            };
        }

        return $row;
    }

    private function rowsOf(Company $company, array $tables): array
    {
        $counts = [];
        foreach ($tables as $table) {
            $counts[$table] = DB::table($table)->where('company_id', $company->id)->count();
        }

        return $counts;
    }

    /* ───────────────────────── destroying data ───────────────────────── */

    public function test_deleting_a_company_removes_everything_it_owned_and_nothing_of_anyone_else(): void
    {
        $doomed = $this->makeCompany();
        $neighbour = $this->makeCompany();
        $planted = $this->plantInEveryTable($doomed);
        $theirs = $this->plantInEveryTable($neighbour);
        $neighbourBefore = $this->rowsOf($neighbour, $this->companyTables());

        $this->assertGreaterThan(60, count($planted), 'Too few tables took a test row for this check to mean anything.');

        $this->artisan('delete:all', ['company_id' => $doomed->id, '--force' => true])->assertSuccessful();

        $left = array_filter($this->rowsOf($doomed, $this->companyTables()));
        $this->assertSame([], $left, 'These tables still hold rows of the deleted company');
        $this->assertSame($neighbourBefore, $this->rowsOf($neighbour, $this->companyTables()), 'Another company lost data');
        $this->assertNotEmpty($theirs);
    }

    public function test_deleting_a_company_asks_first_and_does_nothing_on_no(): void
    {
        $company = $this->makeCompany();
        $partner = \Database\Factories\PartnerFactory::new()->forCompany($company)->create();

        $this->artisan('delete:all', ['company_id' => $company->id])
            ->expectsConfirmation("Delete ALL data of company {$company->id}? This cannot be undone.", 'no')
            ->assertFailed();

        $this->assertTrue(DB::table('partners')->where('id', $partner->id)->exists());
    }

    public function test_emptying_cashvero_asks_first_and_does_nothing_on_no(): void
    {
        $company = $this->makeCompany();
        $partner = \Database\Factories\PartnerFactory::new()->forCompany($company)->create();

        $this->artisan('empty:cashvero')
            ->expectsConfirmation('Delete the data of EVERY company except company 41? This cannot be undone.', 'no')
            ->assertFailed();

        $this->assertTrue(DB::table('partners')->where('id', $partner->id)->exists());
    }

    public function test_resetting_every_permission_asks_first_and_does_nothing_on_no(): void
    {
        $user = $this->makeUser(['report_bank_statement.view']);
        $before = DB::table('permissions')->count();

        $this->artisan('refresh:permissions')
            ->expectsConfirmation('Remove every permission of every user and give them the defaults again? Permissions granted by hand are lost.', 'no')
            ->assertFailed();

        $this->assertSame($before, DB::table('permissions')->count());
        $this->assertTrue($user->fresh()->hasPermissionKey('report_bank_statement.view'));
    }

    /** Called from code (no one to answer the question), the destructive commands refuse. */
    public function test_without_anyone_to_answer_the_destructive_commands_refuse(): void
    {
        $company = $this->makeCompany();
        $partner = \Database\Factories\PartnerFactory::new()->forCompany($company)->create();

        $this->assertSame(1, \Illuminate\Support\Facades\Artisan::call('delete:all', ['company_id' => $company->id]));
        $this->assertSame(1, \Illuminate\Support\Facades\Artisan::call('empty:cashvero'));

        $this->assertTrue(DB::table('partners')->where('id', $partner->id)->exists());
    }

    /* ───────────────────────── checks and fixers ───────────────────────── */

    /** @return array<string, array{0: string, 1: array}> */
    public static function readOnlyCommands(): array
    {
        return [
            'orphan LG rows' => ['lg:orphan-rows', []],
            'orphan loan statements' => ['loan-statements:orphan-rows', []],
            'orphan money rows' => ['money:orphan-rows', []],
            'loan outstanding diagnosis' => ['loans:diagnose-outstanding', ['--mtl' => ':loan']],
            'settlements without invoice' => ['settlements:report-without-invoice', []],
            'bank statements resequence (dry run)' => ['bank-statements:resequence', []],
            'other statements resequence (dry run)' => ['other-statements:resequence', []],
            'safe statements resequence (dry run)' => ['safe-statements:resequence', []],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('readOnlyCommands')]
    public function test_a_check_runs_and_changes_nothing(string $command, array $arguments): void
    {
        $company = $this->makeCompany();
        $bank = $this->bank($company);
        $this->currentAccount($bank, 'EGP');
        \Database\Factories\MoneyReceivedFactory::new()->forCompany($company)->create();
        $loan = $this->mediumTermLoan($bank);
        $this->loanSchedule($loan, '2020-02-01', ['schedule_payment' => 1000, 'interest_amount' => 600, 'principle_amount' => 400]);
        $arguments = array_map(fn ($value) => $value === ':loan' ? $loan->id : $value, $arguments);

        $tables = ['companies', 'partners', 'financial_institutions', 'financial_institution_accounts', 'money_received', 'current_account_bank_statements', 'loan_schedules'];
        $before = array_map(fn ($t) => DB::table($t)->count(), array_combine($tables, $tables));

        $this->artisan($command, $arguments)->assertSuccessful();

        $this->assertSame($before, array_map(fn ($t) => DB::table($t)->count(), array_combine($tables, $tables)));
    }

    public function test_syncing_permissions_twice_adds_nothing_the_second_time(): void
    {
        $this->artisan('permissions:sync')->assertSuccessful();
        $before = [DB::table('permissions')->count(), DB::table('role_has_permissions')->count()];

        $this->artisan('permissions:sync')->expectsOutputToContain('0 created')->assertSuccessful();

        $this->assertSame($before, [DB::table('permissions')->count(), DB::table('role_has_permissions')->count()]);
    }

    public function test_backfilling_serials_twice_changes_nothing_the_second_time(): void
    {
        $company = $this->makeCompany();
        \Database\Factories\MoneyReceivedFactory::new()->forCompany($company)->count(3)->create();

        $this->artisan('serials:backfill')->assertSuccessful();
        $serials = DB::table('money_received')->where('company_id', $company->id)->orderBy('id')->pluck('serial')->all();

        $this->artisan('serials:backfill')->assertSuccessful();

        $this->assertSame($serials, DB::table('money_received')->where('company_id', $company->id)->orderBy('id')->pluck('serial')->all());
        $this->assertCount(3, array_unique(array_filter($serials)), 'Every receipt should carry its own serial');
    }
}
