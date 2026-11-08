<?php

namespace Tests\Feature\Security;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Security\Support\AttacksRoutes;
use Tests\Feature\Security\Support\Finding;
use Tests\Feature\Security\Support\SourceScanner;
use Tests\TestCase;

/**
 * FOREIGN RECORDS INSIDE FORM DATA
 * ==================================================================
 * The route tests prove that a user of company A cannot OPEN company B's screens. A different attack
 * skips the address and hides company B's record inside the FORM: "pay this invoice from bank account
 * #512", where #512 belongs to someone else. If the form only checks that the account exists (not that it
 * is the user's own), the user can link their data to another customer's account, or read its name back.
 *
 *   SEC-34a  Every validation rule "exists:table,id" / Rule::exists(...) on a table that holds one company's
 *            data must be limited to the company ("->where('company_id', ...)").
 *   SEC-34b  A company-A user submits every write form with company B's record numbers (and
 *            company_id = B) in the form. Whenever a request is accepted, nothing may have been stored
 *            for company B or pointing at company B's records.
 *
 * SEC-34b only judges requests the application ACCEPTED (an empty form is usually bounced by validation first),
 * so it proves the screens that can be reached with a bare-minimum form. SEC-34a covers the rest by reading the rules.
 */
#[Group('security')]
class ForeignRecordsInFormsSecurityTest extends TestCase
{
    use AttacksRoutes;

    /** "relative/file.php|table" pairs reviewed by a human and accepted. Keep EMPTY unless you must. */
    private const REVIEWED_UNSCOPED = [];

    /** Form fields that must never decide the owner of a record or point at another company's data. */
    private const SKIP_KEYS = ['company_id', 'user_id', 'created_by', 'updated_by', 'deleted_by', 'reviewed_by'];

    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        $this->cleanupScratch();
        parent::tearDown();
    }

    /* ────────────────────────────── SEC-34a ────────────────────────────── */

    public function test_validation_rules_that_check_a_record_exists_are_limited_to_the_company(): void
    {
        try {
            $companyTables = $this->companyTables();
        } catch (\Throwable $e) {
            $this->markTestSkipped('The database could not be read: '.$e->getMessage());
        }

        if ($companyTables === []) {
            $this->markTestSkipped('No table with a company_id column was found; run php artisan cashvero:prepare-test-db --from=cashvero');
        }

        $scanner = new SourceScanner(base_path());
        $findings = [];
        $checked = 0;

        foreach ($scanner->files('app', 'php', ['app/Console/', 'app/Providers/']) as $file) {
            $relative = $scanner->relative($file);
            $source = (string) file_get_contents($file);

            // 1) The string form  'exists:table,column'  cannot be limited to a company at all, unless it
            //    carries extra conditions ('exists:table,id,company_id,5').
            if (preg_match_all('/[\'"][^\'"\n]*\bexists:([A-Za-z0-9_]+)(?:,([A-Za-z0-9_]+))?((?:,[A-Za-z0-9_]+)*)[^\'"\n]*[\'"]/', $source, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[1] as $i => [$table, $offset]) {
                    $checked++;

                    if (! in_array($table, $companyTables, true) || str_contains($m[3][$i][0], 'company_id')) {
                        continue;
                    }

                    $this->flag($findings, $relative, $table, $source, $offset, "exists:{$table}");
                }
            }

            // 2) The object form  Rule::exists('table', 'id')  or  Rule::exists(Model::class, 'id'):
            //    it must be followed by a ->where(...) that mentions the company.
            if (preg_match_all('/Rule::exists\(\s*([^,)]+?)\s*(?:,[^)]*)?\)/', $source, $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[1] as $i => [$arg, $offset]) {
                    $checked++;
                    $table = $this->tableOf(trim($arg));

                    if ($table === null || ! in_array($table, $companyTables, true)) {
                        continue;
                    }

                    $end = $m[0][$i][1] + strlen($m[0][$i][0]);
                    $window = substr($source, $end, 500);
                    $window = preg_split('/;|Rule::exists\(/', $window)[0];

                    if (! preg_match('/->where\w*\([^;]*company/is', $window)) {
                        $this->flag($findings, $relative, $table, $source, $offset, "Rule::exists({$table})");
                    }
                }
            }
        }

        $this->assertGreaterThan(0, $checked, 'No "exists" validation rule was found at all: the scan could not read the source.');

        $this->assertSame([], $findings, Finding::message(
            'SEC-34a', 'MEDIUM',
            'A form accepts the number of a record that belongs to ANY company',
            $findings,
            'The rule only asks "does a record with this number exist?". A user of one company can then submit the number of another company\'s bank account, customer, contract... and the application accepts it. Depending on what the screen does next, that links their data to another customer\'s records or reveals their names.',
            'Limit the rule to the company: Rule::exists(\'table\', \'id\')->where(\'company_id\', $company->id). If the table is really shared by all companies on purpose, add "file|table" to REVIEWED_UNSCOPED in this test with a comment saying why.'
        ));
    }

    private function flag(array &$findings, string $relative, string $table, string $source, int $offset, string $rule): void
    {
        if (in_array($relative.'|'.$table, self::REVIEWED_UNSCOPED, true)) {
            return;
        }

        $line = substr_count(substr($source, 0, $offset), "\n") + 1;
        $findings[] = "{$relative}:{$line}  {$rule}  (table \"{$table}\" holds data of one company each)";
    }

    /** 'bank_accounts' (a string) or Partner::class (a model) → table name, or null when it cannot be told. */
    private function tableOf(string $argument): ?string
    {
        if (preg_match('/^[\'"]([A-Za-z0-9_]+)[\'"]$/', $argument, $m)) {
            return $m[1];
        }

        if (preg_match('/^\\\\?([A-Za-z0-9_\\\\]+)::class$/', $argument, $m)) {
            $short = Str::afterLast($m[1], '\\');
            $class = class_exists($m[1]) ? $m[1] : 'App\\Models\\'.$short;

            if (class_exists($class) && is_subclass_of($class, \Illuminate\Database\Eloquent\Model::class)) {
                return (new $class)->getTable();
            }
        }

        return null;
    }

    /* ────────────────────────────── SEC-34b ────────────────────────────── */

    public function test_a_user_of_company_a_cannot_store_company_b_records_through_form_data(): void
    {
        $this->bootAttack();

        $a = $this->companyA->id;
        $b = $this->companyB->id;
        $companyTables = $this->companyTables();

        // Form fields named like a link to a record (bank_account_id, customer_id...), each filled with a
        // record of company B from the matching table.
        $foreign = [];

        foreach ($this->foreignKeyColumns() as [$table, $column]) {
            if (isset($foreign[$column]) || in_array($column, self::SKIP_KEYS, true)) {
                continue;
            }

            $parent = Str::plural(Str::beforeLast($column, '_id'));

            if (! in_array($parent, $companyTables, true)) {
                continue;
            }

            $id = DB::table($parent)->where('company_id', $b)->value('id');

            if ($id !== null) {
                $foreign[$column] = $id;
            }
        }

        $this->assertNotSame([], $foreign, 'Test set-up problem: company '.$b.' has no records to hide in the forms. Run: php artisan cashvero:prepare-test-db --from=cashvero');

        $payload = array_merge($foreign, ['company_id' => $b]);

        // What must NOT change: rows stamped with company B, and rows of company A that point at B's records.
        $pairs = collect($this->foreignKeyColumns())
            ->filter(fn ($p) => isset($foreign[$p[1]]))
            ->values()->all();

        $baseline = $this->snapshot($companyTables, $pairs, $a, $b, $foreign);

        $leaks = [];
        $sent = 0;
        $accepted = 0;

        foreach ($this->allRoutes() as $route) {
            if (! $this->hasCompanyParam($route)) {
                continue;
            }

            $values = array_merge($this->recordIdsFor($route, $a), $this->companyValues($route, $a));

            foreach ($this->verbsOf($route) as $verb) {
                if (! $this->isWrite($verb)) {
                    continue;
                }

                $url = $this->urlFor($route, $values);
                $status = $this->send($verb, $url, $location, $validationOnly, $payload, function ($status, $location, $validationOnly) use ($companyTables, $pairs, $a, $b, $foreign, $baseline) {
                    // Only look at the database when the request was not simply refused or bounced.
                    if (! $this->looksSuccessful($status, $location, $validationOnly)) {
                        return null;
                    }

                    return $this->differences($baseline, $this->snapshot($companyTables, $pairs, $a, $b, $foreign));
                });
                $sent++;

                if ($this->looksSuccessful($status, $location, $validationOnly)) {
                    $accepted++;

                    if (is_array($this->inspected) && $this->inspected !== []) {
                        $leaks[] = "{$verb} /{$route->uri()}  →  ".implode('; ', $this->inspected);
                    }
                }
            }
        }

        $this->assertGreaterThan(20, $sent, 'Too few write requests were sent ('.$sent.'): the route table could not be read.');

        $this->assertSame([], $leaks, Finding::message(
            'SEC-34b', 'HIGH',
            'A form submitted by a company A user stored data for company B, or linked to a company B record',
            $leaks,
            'The screen trusted the record numbers (or the company_id) sent inside the form instead of the company in the address. A user could attach their data to another customer, or write into another customer\'s books.',
            'Never take company_id from the request: set it from the company in the address. Load every related record through that company ($company->bankAccounts()->findOrFail($id)) or validate it with Rule::exists(...)->where(\'company_id\', $company->id).'
        )."\n\n{$sent} write requests were sent; {$accepted} were accepted by the application and checked in the database.");
    }

    /**
     * @param  list<string>  $companyTables
     * @param  list<array{0:string,1:string}>  $pairs
     * @return array<string,int>
     */
    private function snapshot(array $companyTables, array $pairs, int $a, int $b, array $foreign): array
    {
        $counts = [];

        foreach ($companyTables as $table) {
            $counts["stamped for company {$b}: {$table}"] = DB::table($table)->where('company_id', $b)->count();
        }

        foreach ($pairs as [$table, $column]) {
            $counts["company {$a} row in {$table} pointing at {$column}={$foreign[$column]}"] = DB::table($table)
                ->where('company_id', $a)->where($column, $foreign[$column])->count();
        }

        return $counts;
    }

    /** @return list<string> what grew since the baseline */
    private function differences(array $before, array $after): array
    {
        $changes = [];

        foreach ($after as $label => $count) {
            if ($count > ($before[$label] ?? 0)) {
                $changes[] = $label.' ('.($before[$label] ?? 0).' → '.$count.')';
            }
        }

        return $changes;
    }
}
