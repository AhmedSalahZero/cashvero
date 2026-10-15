<?php

/**
 * Parity check for the Statement pagination rewrite.
 *
 * For every real filter combination present in the database, computes the
 * KPI set both ways — the old way (fetch every row, sum in PHP) and the new
 * way (SQL aggregates + boundary rows) — and reports any drift. Also asserts
 * that walking the paginated pages yields exactly the same row ids, in the
 * same order, as the single unpaginated fetch.
 *
 * Run: php scripts/verify_statement_pagination.php
 */

use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

const PER_PAGE = 50;

$failures = 0;
$checked = 0;

function money($value): string
{
    return number_format((float) $value, 2, '.', '');
}

/**
 * Old behaviour: hydrate everything, then read totals off the collection.
 */
function legacyKpis(callable $freshQuery): array
{
    $results = $freshQuery()->get();

    return [
        'beginningBalance' => money($results->last()->beginning_balance ?? 0),
        'endingBalance' => money($results->first()->end_balance ?? 0),
        'totalDebit' => money($results->sum('debit')),
        'totalCredit' => money($results->sum('credit')),
        'transactionCount' => $results->count(),
    ];
}

/**
 * New behaviour: SQL aggregates + boundary rows, mirroring
 * App\Traits\PaginatesStatementQueries::ledgerStatementKpis().
 */
function sqlKpis(callable $freshQuery, string $table, string $dateColumn = 'date'): array
{
    $sums = $freshQuery()->reorder()->select(DB::raw(
        "COALESCE(SUM({$table}.debit), 0) AS total_debit, COALESCE(SUM({$table}.credit), 0) AS total_credit"
    ))->first();

    $oldest = $freshQuery()->reorder()->orderBy("{$table}.{$dateColumn}")->orderBy("{$table}.id")->first();
    $newest = $freshQuery()->reorder()->orderByDesc("{$table}.{$dateColumn}")->orderByDesc("{$table}.id")->first();

    return [
        'beginningBalance' => money($oldest->beginning_balance ?? 0),
        'endingBalance' => money($newest->end_balance ?? 0),
        'totalDebit' => money($sums->total_debit),
        'totalCredit' => money($sums->total_credit),
        'transactionCount' => $freshQuery()->reorder()->count(),
    ];
}

/**
 * Every page, concatenated, must equal the unpaginated fetch — same ids in
 * the same order, no duplicates across page boundaries, nothing dropped.
 */
function pagingIsStable(callable $freshQuery): array
{
    $expected = $freshQuery()->get()->pluck('id')->all();

    $walked = [];
    $lastPage = (int) ceil(max(count($expected), 1) / PER_PAGE);
    for ($page = 1; $page <= $lastPage; $page++) {
        $slice = $freshQuery()->forPage($page, PER_PAGE)->get()->pluck('id')->all();
        $walked = array_merge($walked, $slice);
    }

    return [$expected, $walked];
}

function check(string $label, callable $freshQuery, string $table, string $dateColumn = 'date'): void
{
    global $failures, $checked;
    $checked++;

    $legacy = legacyKpis($freshQuery);
    $sql = sqlKpis($freshQuery, $table, $dateColumn);

    if ($legacy !== $sql) {
        $failures++;
        echo "  KPI MISMATCH  {$label}\n";
        foreach ($legacy as $key => $value) {
            if ($value !== $sql[$key]) {
                echo "      {$key}: collection={$value}  sql={$sql[$key]}\n";
            }
        }

        return;
    }

    [$expected, $walked] = pagingIsStable($freshQuery);
    if ($expected !== $walked) {
        $failures++;
        $missing = count(array_diff($expected, $walked));
        $duplicated = count($walked) - count(array_unique($walked));
        echo "  PAGING UNSTABLE  {$label}  (rows={$expected[0]}… missing={$missing} duplicated={$duplicated})\n";

        return;
    }

    echo "  ok  {$label}  rows={$legacy['transactionCount']} debit={$legacy['totalDebit']} credit={$legacy['totalCredit']}\n";
}

/**
 * Reports without beginning/end balances only need their SUMs and count to
 * match, plus stable paging.
 *
 * @param  array<string,string>  $columns  alias => qualified column
 */
function checkSums(string $label, callable $freshQuery, array $columns): void
{
    global $failures, $checked;
    $checked++;

    $rows = $freshQuery()->get();
    $legacy = ['count' => $rows->count()];
    foreach ($columns as $alias => $column) {
        $legacy[$alias] = money($rows->sum(substr($column, strrpos($column, '.') + 1)));
    }

    $selects = [];
    foreach ($columns as $alias => $column) {
        $selects[] = "COALESCE(SUM({$column}), 0) AS {$alias}";
    }
    $aggregate = $freshQuery()->reorder()->select(DB::raw(implode(', ', $selects)))->first();

    $sql = ['count' => $freshQuery()->reorder()->count()];
    foreach ($columns as $alias => $column) {
        $sql[$alias] = money($aggregate->{$alias} ?? 0);
    }

    if ($legacy !== $sql) {
        $failures++;
        echo "  SUM MISMATCH  {$label}\n";
        foreach ($legacy as $key => $value) {
            if ($value !== $sql[$key]) {
                echo "      {$key}: collection={$value}  sql={$sql[$key]}\n";
            }
        }

        return;
    }

    [$expected, $walked] = pagingIsStable($freshQuery);
    if ($expected !== $walked) {
        $failures++;
        echo "  PAGING UNSTABLE  {$label}  (rows=".count($expected).", walked=".count($walked).", unique=".count(array_unique($walked)).")\n";

        return;
    }

    echo "  ok  {$label}  rows={$legacy['count']} ".implode(' ', array_map(
        fn ($a) => "{$a}={$legacy[$a]}",
        array_keys($columns)
    ))."\n";
}

/* ── Safe Statement ─────────────────────────────────────────────── */
echo "\nSafe Statement (cash_in_safe_statements)\n";
$safeCombos = DB::table('cash_in_safe_statements')
    ->select('company_id', 'currency', 'branch_id')
    ->groupBy('company_id', 'currency', 'branch_id')
    ->get();

foreach ($safeCombos as $combo) {
    $range = DB::table('cash_in_safe_statements')
        ->where('company_id', $combo->company_id)
        ->where('currency', $combo->currency)
        ->where('branch_id', $combo->branch_id)
        ->selectRaw('MIN(date) AS start_date, MAX(date) AS end_date')
        ->first();

    $freshQuery = fn () => DB::table('cash_in_safe_statements')
        ->where('company_id', $combo->company_id)
        ->where('currency', $combo->currency)
        ->where('branch_id', $combo->branch_id)
        ->where('date', '>=', $range->start_date)
        ->where('date', '<=', $range->end_date)
        ->orderByRaw('date desc , id desc');

    check("company={$combo->company_id} currency={$combo->currency} branch={$combo->branch_id}", $freshQuery, 'cash_in_safe_statements');
}

/* ── Bank Statement — current account (the joined branch) ───────── */
echo "\nBank Statement / current account (current_account_bank_statements JOIN financial_institution_accounts)\n";
$accountCombos = DB::table('current_account_bank_statements')
    ->join('financial_institution_accounts', 'financial_institution_account_id', '=', 'financial_institution_accounts.id')
    ->where('current_account_bank_statements.is_active', 1)
    ->select(
        'current_account_bank_statements.company_id',
        'current_account_bank_statements.financial_institution_account_id',
        'financial_institution_accounts.currency'
    )
    ->groupBy(
        'current_account_bank_statements.company_id',
        'current_account_bank_statements.financial_institution_account_id',
        'financial_institution_accounts.currency'
    )
    ->limit(25)
    ->get();

foreach ($accountCombos as $combo) {
    $range = DB::table('current_account_bank_statements')
        ->where('company_id', $combo->company_id)
        ->where('financial_institution_account_id', $combo->financial_institution_account_id)
        ->selectRaw('MIN(date) AS start_date, MAX(date) AS end_date')
        ->first();

    $freshQuery = fn () => DB::table('current_account_bank_statements')
        ->where('date', '>=', $range->start_date)
        ->where('date', '<=', $range->end_date)
        ->where('current_account_bank_statements.is_active', 1)
        ->where('current_account_bank_statements.financial_institution_account_id', $combo->financial_institution_account_id)
        ->where('current_account_bank_statements.company_id', $combo->company_id)
        ->join('financial_institution_accounts', 'financial_institution_account_id', '=', 'financial_institution_accounts.id')
        ->where('financial_institution_accounts.currency', $combo->currency)
        ->leftJoin('money_received', 'current_account_bank_statements.money_received_id', '=', 'money_received.id')
        ->selectRaw('current_account_bank_statements.*,financial_institution_accounts.*,money_received.is_reviewed,money_received.reviewed_by,current_account_bank_statements.id as id,current_account_bank_statements.full_date as full_date,current_account_bank_statements.date as date')
        ->orderByRaw('date desc , current_account_bank_statements.id desc');

    check(
        "company={$combo->company_id} account={$combo->financial_institution_account_id} currency={$combo->currency}",
        $freshQuery,
        'current_account_bank_statements'
    );
}

/* ── LG / LC Bank Statements (three interchangeable tables) ─────── */
foreach (['letter_of_credit_statements', 'letter_of_guarantee_statements', 'lc_overdraft_bank_statements'] as $table) {
    echo "\nLG-LC Bank Statement ({$table})\n";
    $combos = DB::table($table)->select('company_id')->groupBy('company_id')->limit(10)->get();

    foreach ($combos as $combo) {
        $range = DB::table($table)->where('company_id', $combo->company_id)
            ->selectRaw('MIN(date) AS start_date, MAX(date) AS end_date')->first();

        $freshQuery = fn () => DB::table($table)
            ->where($table.'.company_id', $combo->company_id)
            ->where('date', '>=', $range->start_date)
            ->where('date', '<=', $range->end_date)
            ->orderByRaw('date desc , '.$table.'.id desc');

        check("company={$combo->company_id}", $freshQuery, $table);
    }
}

/* ── Cash Expense Statement ─────────────────────────────────────── */
echo "\nCash Expense Statement (cash_expenses)\n";
$expenseCombos = DB::table('cash_expenses')->select('company_id', 'currency')
    ->groupBy('company_id', 'currency')->limit(15)->get();

foreach ($expenseCombos as $combo) {
    $range = DB::table('cash_expenses')
        ->where('company_id', $combo->company_id)->where('currency', $combo->currency)
        ->selectRaw('MIN(payment_date) AS start_date, MAX(payment_date) AS end_date')->first();

    $categoryIds = DB::table('cash_expenses')
        ->where('company_id', $combo->company_id)->where('currency', $combo->currency)
        ->distinct()->pluck('cash_expense_category_name_id')->all();

    $freshQuery = fn () => DB::table('cash_expenses')
        ->where('cash_expenses.company_id', $combo->company_id)
        ->where('currency', $combo->currency)
        ->where('payment_date', '>=', $range->start_date)
        ->where('payment_date', '<=', $range->end_date)
        ->whereIn('cash_expense_category_name_id', $categoryIds)
        ->orderByRaw('payment_date asc, cash_expenses.id asc')
        ->join('cash_expense_category_names', 'cash_expense_category_names.id', '=', 'cash_expenses.cash_expense_category_name_id')
        ->join('cash_expense_categories', 'cash_expense_categories.id', '=', 'cash_expense_category_names.cash_expense_category_id')
        ->selectRaw('cash_expenses.*,cash_expense_category_names.name as sub_category_name , cash_expense_categories.name as main_category_name');

    checkSums("company={$combo->company_id} currency={$combo->currency}", $freshQuery, [
        'total_paid' => 'cash_expenses.paid_amount',
        'total_withhold' => 'cash_expenses.total_withhold_amount',
    ]);
}

/* ── LG by Beneficiary / Bank Name ──────────────────────────────── */
echo "\nLG by Beneficiary / Bank Name (letter_of_guarantee_issuances)\n";
$lgCombos = DB::table('letter_of_guarantee_issuances')
    ->select('company_id', 'lg_currency')->groupBy('company_id', 'lg_currency')->limit(15)->get();

foreach ($lgCombos as $combo) {
    $freshQuery = fn () => DB::table('letter_of_guarantee_issuances')
        ->where('letter_of_guarantee_issuances.company_id', $combo->company_id)
        ->where('letter_of_guarantee_issuances.lg_currency', $combo->lg_currency)
        ->leftJoin('letter_of_guarantee_statements as cancellation_statement', function ($join) {
            $join->on('cancellation_statement.letter_of_guarantee_issuance_id', '=', 'letter_of_guarantee_issuances.id')
                ->where('cancellation_statement.type', '=', 'for-cancellation');
        })
        ->join('partners', 'partners.id', '=', 'letter_of_guarantee_issuances.partner_id')
        ->join('financial_institutions', 'financial_institutions.id', '=', 'letter_of_guarantee_issuances.financial_institution_id')
        ->join('banks', 'banks.id', '=', 'financial_institutions.bank_id')
        ->selectRaw('letter_of_guarantee_issuances.id as id, letter_of_guarantee_issuances.lg_amount as lg_amount, letter_of_guarantee_issuances.cash_cover_amount as cash_cover_amount')
        ->orderBy('letter_of_guarantee_issuances.id');

    checkSums("company={$combo->company_id} currency={$combo->lg_currency}", $freshQuery, [
        'total_lg_amount' => 'letter_of_guarantee_issuances.lg_amount',
        'total_cash_cover' => 'letter_of_guarantee_issuances.cash_cover_amount',
    ]);
}

echo "\n".($failures === 0
    ? "PASS — {$checked} filter combinations, no drift\n"
    : "FAIL — {$failures} of {$checked} combinations drifted\n");

exit($failures === 0 ? 0 : 1);
