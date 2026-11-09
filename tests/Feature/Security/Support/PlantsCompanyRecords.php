<?php

namespace Tests\Feature\Security\Support;

use App\Models\Company;
use Database\Factories\FinancialInstitutionAccountFactory;
use Database\Factories\FinancialInstitutionFactory;
use Database\Factories\PartnerFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Fake records for a company: one row in EVERY table that carries a company_id, so an attack always has
 * a record of company B to aim at, and company A has records of its own for the positive controls.
 *
 * The rows are as small as each table allows. Only the columns that may not be left empty (plus amounts and
 * currencies, which triggers copy into the statements) are filled, with neutral values; a column MySQL links
 * to another table (a foreign key), or named after one (money_received_id), points at the row planted for
 * the same company in that table. A few child tables whose triggers need a fully built parent (overdraft
 * limits and their statements) may stay empty: plantFailuresFor() says which and why. A bank, a current account at it and a partner are made through their
 * factories first, because so many screens are built around them.
 *
 * Nothing is committed: the rows live inside the test's own transaction (see Tests\TestCase).
 */
trait PlantsCompanyRecords
{
    /** @var array<int, array<string, int>> company id => [table => id of the row planted there] */
    private array $planted = [];

    /** @var array<int, array<string, string>> company id => [table => why nothing could be planted] */
    private array $plantFailures = [];

    /**
     * Tables that decide WHO may do WHAT (membership, subscription, running jobs) rather than hold a
     * company's business data. A stray row there would change the outcome of every request.
     */
    private static array $notPlanted = ['companies_users', 'company_systems', 'caching_company', 'active_jobs'];

    /**
     * @param  int|null  $userId  used for columns that must point at a user (conversations.created_by...)
     * @return array<string, int> table => id of the planted row
     */
    protected function plantRecordsFor(Company $company, ?int $userId = null): array
    {
        $companyId = (int) $company->id;
        $planted = &$this->planted[$companyId];
        $planted ??= [];

        $bank = FinancialInstitutionFactory::new()->forCompany($company)->create();
        $planted['financial_institutions'] = (int) $bank->id;
        $planted['banks'] = (int) $bank->bank_id;
        $planted['financial_institution_accounts'] = (int) FinancialInstitutionAccountFactory::new()->at($bank)->create()->id;
        $planted['partners'] = (int) PartnerFactory::new()->forCompany($company)->customer()->supplier()->create()->id;

        $pending = array_values(array_diff($this->plantableTables(), array_keys($planted), self::$notPlanted));

        // A child can only be planted after its parent: keep going round until a pass plants nothing new.
        // The first rounds also wait for parents that are only guessed from a column name
        // (money_received_id → money_received); the last rounds take what is there.
        foreach ([true, false] as $strict) {
            do {
                $progress = false;

                foreach ($pending as $i => $table) {
                    try {
                        $row = $this->plantedRowFor($table, $companyId, $planted, $userId, $strict);

                        if ($row === null) {
                            continue;   // a parent it must point at is not planted yet
                        }

                        $planted[$table] = DB::transaction(fn () => $this->insertPlanted($table, $row));
                        unset($this->plantFailures[$companyId][$table]);
                    } catch (\Throwable $e) {
                        $this->plantFailures[$companyId][$table] = Str::limit($e->getMessage(), 300);
                    }

                    unset($pending[$i]);
                    $progress = true;
                }
            } while ($progress && $pending !== []);
        }

        foreach ($pending as $table) {
            $this->plantFailures[$companyId][$table] ??= 'a table it must point at could not be planted';
        }

        return $planted;
    }

    /** @return array<string, int> table => id planted for that company */
    protected function plantedFor(Company|int $company): array
    {
        return $this->planted[$company instanceof Company ? $company->id : $company] ?? [];
    }

    /** @return array<string, string> table => why no row could be planted there */
    protected function plantFailuresFor(Company|int $company): array
    {
        return $this->plantFailures[$company instanceof Company ? $company->id : $company] ?? [];
    }

    /* ───────────────────────────── internals ───────────────────────────── */

    private function insertPlanted(string $table, array $row): int
    {
        if ($this->columnsOf($table)['id']->auto ?? false) {
            return (int) DB::table($table)->insertGetId($row);
        }

        DB::table($table)->insert($row);

        return 0;
    }

    /**
     * The smallest row MySQL accepts for that table, or null when a parent it must point at is missing.
     *
     * @param  array<string, int>  $planted
     */
    private function plantedRowFor(string $table, int $companyId, array $planted, ?int $userId, bool $strict): ?array
    {
        $row = ['company_id' => $companyId];
        $foreign = $this->foreignKeysOf($table);

        foreach ($this->columnsOf($table) as $name => $column) {
            if ($name === 'company_id' || $column->auto) {
                continue;
            }

            if (isset($foreign[$name])) {
                $value = $this->parentId($foreign[$name], $companyId, $planted, $userId);

                if ($value === null && ! $column->nullable) {
                    if (in_array($foreign[$name], $this->plantableTables(), true) && ! in_array($foreign[$name], self::$notPlanted, true)) {
                        return null;   // try again once the parent is planted
                    }

                    throw new \LogicException("{$table}.{$name} must point at a {$foreign[$name]} row and there is none");
                }

                if ($value !== null) {
                    $row[$name] = $value;
                }

                continue;
            }

            // Amounts and currencies are filled even where they may be empty: triggers copy them into
            // the statements and refuse a missing one (an overdraft without a limit...).
            if (($column->nullable || $column->hasDefault) && ! $this->alwaysFilled($name, $column)) {
                continue;
            }

            $value = $this->neutralValue($name, $column, $planted, $strict);

            if ($value === null) {
                return null;   // the column names a parent that is not planted yet
            }

            $row[$name] = $value;
        }

        return $row;
    }

    private function parentId(string $parent, int $companyId, array $planted, ?int $userId): ?int
    {
        return match (true) {
            $parent === 'companies' => $companyId,
            $parent === 'users' => $userId,
            isset($planted[$parent]) => $planted[$parent],
            in_array($parent, $this->plantableTables(), true) => null,
            // A shared list (account types, banks...): any row of it.
            default => ($id = DB::table($parent)->value('id')) === null ? null : (int) $id,
        };
    }

    private function neutralValue(string $name, object $column, array $planted, bool $strict): mixed
    {
        return match (true) {
            in_array($column->type, ['tinyint', 'smallint', 'mediumint', 'int', 'bigint'], true) => str_ends_with($name, '_id') ? $this->guessParent($name, $planted, $strict) : 0,
            in_array($column->type, ['decimal', 'double', 'float'], true) => 0,
            $column->type === 'date' => '2020-01-01',
            in_array($column->type, ['datetime', 'timestamp'], true) => '2020-01-01 00:00:00',
            $column->type === 'enum' => $column->enum[0],
            $column->type === 'json' => '[]',
            in_array($column->type, ['text', 'longtext', 'mediumtext'], true) => '[]',
            str_contains($name, 'currency') => 'EGP',
            str_contains($name, 'date') || str_ends_with($name, '_at') => '2020-01-01',
            default => Str::limit('Planted '.$name, max(1, (int) $column->length), ''),
        };
    }

    private function alwaysFilled(string $name, object $column): bool
    {
        return ! $column->hasDefault && (
            in_array($column->type, ['decimal', 'double', 'float'], true)
            || (in_array($column->type, ['varchar', 'char'], true) && in_array($name, ['currency', 'currency_name'], true))
        );
    }

    /**
     * money_received_id → the planted money_received row, medium_term_loan_id → medium_term_loans...
     * Null (strict rounds only) while that table is still waiting for its own row; 0 when no table matches.
     */
    private function guessParent(string $name, array $planted, bool $strict): ?int
    {
        $base = Str::beforeLast($name, '_id');

        foreach ([Str::plural($base), $base] as $table) {
            if (isset($planted[$table])) {
                return $planted[$table];
            }

            if ($strict && in_array($table, $this->plantableTables(), true) && ! in_array($table, self::$notPlanted, true)) {
                return null;
            }
        }

        return 0;
    }

    /** @return list<string> every table that carries a company_id column */
    private function plantableTables(): array
    {
        static $tables = [];
        $database = DB::connection()->getDatabaseName();

        return $tables[$database] ??= collect(DB::select(
            "select table_name as t from information_schema.columns where table_schema = ? and column_name = 'company_id' order by table_name",
            [$database]
        ))->pluck('t')->unique()->values()->all();
    }

    /** @return array<string, object{type:string,nullable:bool,hasDefault:bool,auto:bool,length:?int,enum:list<string>}> */
    private function columnsOf(string $table): array
    {
        static $all = [];
        $database = DB::connection()->getDatabaseName();

        if (! isset($all[$database])) {
            $all[$database] = [];

            foreach (DB::select(
                'select table_name as t, column_name as c, data_type as type, column_type as full_type, is_nullable as nullable, column_default as def, extra as extra, character_maximum_length as len
                   from information_schema.columns where table_schema = ? order by table_name, ordinal_position',
                [$database]
            ) as $c) {
                preg_match_all("/'((?:[^']|'')*)'/", (string) $c->full_type, $m);

                $all[$database][$c->t][$c->c] = (object) [
                    'type' => strtolower((string) $c->type),
                    'nullable' => $c->nullable === 'YES',
                    'hasDefault' => $c->def !== null || str_contains((string) $c->extra, 'GENERATED'),
                    'auto' => str_contains((string) $c->extra, 'auto_increment'),
                    'length' => $c->len === null ? null : (int) $c->len,
                    'enum' => $m[1] ?? [],
                ];
            }
        }

        return $all[$database][$table] ?? [];
    }

    /** @return array<string, string> column => table it is a foreign key to */
    private function foreignKeysOf(string $table): array
    {
        static $all = [];
        $database = DB::connection()->getDatabaseName();

        if (! isset($all[$database])) {
            $all[$database] = [];

            foreach (DB::select(
                'select table_name as t, column_name as c, referenced_table_name as p from information_schema.key_column_usage
                  where table_schema = ? and referenced_table_name is not null',
                [$database]
            ) as $k) {
                $all[$database][$k->t][$k->c] = $k->p;
            }
        }

        return $all[$database][$table] ?? [];
    }
}
