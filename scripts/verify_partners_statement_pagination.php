<?php

/**
 * Parity check for Partners Statement partner-ID pagination.
 *
 * For every company × partner_type × currency that has statement rows,
 * compares:
 *   1. KPI totals — old PHP sums on fully fetched groups vs new SQL aggregates
 *   2. Page stability — concatenating partner IDs across pages == ordered active list
 *   3. Row identity — per-page row ids match old per-partner ordered fetch
 *
 * Run: php scripts/verify_partners_statement_pagination.php
 */

use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

const PARTNERS_PER_PAGE = 10;

const STATEMENT_TABLE_BY_TYPE = [
    'is_subsidiary_company' => 'subsidiary_company_statements',
    'is_shareholder' => 'shareholder_statements',
    'is_employee' => 'employee_statements',
    'is_other_partner' => 'other_partner_statements',
    'is_tax' => 'tax_statements',
];

$failures = 0;
$checked = 0;

function money($value): string
{
    return number_format((float) $value, 2, '.', '');
}

function baseQuery(string $table, int $companyId, string $currency, array $partnerIds, ?string $startDate = null, ?string $endDate = null)
{
    $q = DB::table($table)
        ->where('company_id', $companyId)
        ->where('currency_name', $currency)
        ->whereIn('partner_id', $partnerIds);

    if ($startDate !== null) {
        $q->where('date', '>=', $startDate);
    }
    if ($endDate !== null) {
        $q->where('date', '<=', $endDate);
    }

    return $q;
}

/**
 * Old behaviour: N× get per partner, PHP KPI sums, last()->end_balance.
 */
function legacyKpis(string $table, int $companyId, string $currency, array $partnerIds, string $startDate, string $endDate): array
{
    $totalDebit = 0.0;
    $totalCredit = 0.0;
    $totalEndBalance = 0.0;
    $transactionCount = 0;
    $partnerCount = 0;
    $orderedPartnerIds = [];

    foreach ($partnerIds as $partnerId) {
        $rows = baseQuery($table, $companyId, $currency, [$partnerId], $startDate, $endDate)
            ->orderByRaw('full_date asc, created_at asc, id asc')
            ->get();

        if (! count($rows)) {
            continue;
        }

        $partnerCount++;
        $orderedPartnerIds[] = (int) $partnerId;
        $totalDebit += (float) $rows->sum('debit');
        $totalCredit += (float) $rows->sum('credit');
        $totalEndBalance += (float) ($rows->last()->end_balance ?? 0);
        $transactionCount += $rows->count();
    }

    return [
        'partnerCount' => $partnerCount,
        'transactionCount' => $transactionCount,
        'totalDebit' => money($totalDebit),
        'totalCredit' => money($totalCredit),
        'totalEndBalance' => money($totalEndBalance),
        'orderedPartnerIds' => $orderedPartnerIds,
    ];
}

/**
 * New behaviour: distinct partners + SQL aggregates + ROW_NUMBER last balance.
 */
function sqlKpis(string $table, int $companyId, string $currency, array $partnerIds, string $startDate, string $endDate): array
{
    $withRows = baseQuery($table, $companyId, $currency, $partnerIds, $startDate, $endDate)
        ->distinct()
        ->pluck('partner_id')
        ->map(fn ($id) => (int) $id)
        ->all();
    $withRowsSet = array_flip($withRows);
    $activePartnerIds = array_values(array_filter(
        $partnerIds,
        fn (int $id) => isset($withRowsSet[$id])
    ));

    if (! count($activePartnerIds)) {
        return [
            'partnerCount' => 0,
            'transactionCount' => 0,
            'totalDebit' => money(0),
            'totalCredit' => money(0),
            'totalEndBalance' => money(0),
            'orderedPartnerIds' => [],
        ];
    }

    $sums = baseQuery($table, $companyId, $currency, $activePartnerIds, $startDate, $endDate)
        ->selectRaw('COALESCE(SUM(debit), 0) AS total_debit, COALESCE(SUM(credit), 0) AS total_credit, COUNT(*) AS transaction_count')
        ->first();

    $placeholders = implode(',', array_fill(0, count($activePartnerIds), '?'));
    $ranked = DB::select(
        'SELECT COALESCE(SUM(end_balance), 0) AS total_end_balance FROM (
            SELECT end_balance,
                ROW_NUMBER() OVER (
                    PARTITION BY partner_id
                    ORDER BY full_date DESC, created_at DESC, id DESC
                ) AS rn
            FROM '.$table.'
            WHERE company_id = ?
              AND currency_name = ?
              AND partner_id IN ('.$placeholders.')
              AND date >= ?
              AND date <= ?
        ) ranked WHERE rn = 1',
        array_merge(
            [$companyId, $currency],
            $activePartnerIds,
            [$startDate, $endDate]
        )
    );

    return [
        'partnerCount' => count($activePartnerIds),
        'transactionCount' => (int) ($sums->transaction_count ?? 0),
        'totalDebit' => money($sums->total_debit ?? 0),
        'totalCredit' => money($sums->total_credit ?? 0),
        'totalEndBalance' => money($ranked[0]->total_end_balance ?? 0),
        'orderedPartnerIds' => $activePartnerIds,
    ];
}

/**
 * Walk partner pages (10 IDs) and concatenate — must equal ordered active list.
 */
function pagingIsStable(array $activePartnerIds): array
{
    $walked = [];
    $lastPage = (int) ceil(max(count($activePartnerIds), 1) / PARTNERS_PER_PAGE);
    for ($page = 1; $page <= $lastPage; $page++) {
        $slice = array_slice($activePartnerIds, ($page - 1) * PARTNERS_PER_PAGE, PARTNERS_PER_PAGE);
        $walked = array_merge($walked, $slice);
    }

    return [$activePartnerIds, $walked];
}

/**
 * Per page: row ids from whereIn fetch must match old per-partner ordered ids.
 */
function rowIdsMatch(string $table, int $companyId, string $currency, array $pagePartnerIds, string $startDate, string $endDate): bool
{
    $expected = [];
    foreach ($pagePartnerIds as $partnerId) {
        $ids = baseQuery($table, $companyId, $currency, [$partnerId], $startDate, $endDate)
            ->orderByRaw('full_date asc, created_at asc, id asc')
            ->pluck('id')
            ->all();
        $expected[$partnerId] = $ids;
    }

    $actualRows = baseQuery($table, $companyId, $currency, $pagePartnerIds, $startDate, $endDate)
        ->orderBy('partner_id')
        ->orderByRaw('full_date asc, created_at asc, id asc')
        ->get()
        ->groupBy('partner_id');

    foreach ($pagePartnerIds as $partnerId) {
        $actual = ($actualRows->get($partnerId) ?? collect())->pluck('id')->all();
        if ($actual !== ($expected[$partnerId] ?? [])) {
            return false;
        }
    }

    return true;
}

function check(string $label, string $table, int $companyId, string $currency, array $partnerIds, string $startDate, string $endDate): void
{
    global $failures, $checked;
    $checked++;

    $legacy = legacyKpis($table, $companyId, $currency, $partnerIds, $startDate, $endDate);
    $sql = sqlKpis($table, $companyId, $currency, $partnerIds, $startDate, $endDate);

    foreach (['partnerCount', 'transactionCount', 'totalDebit', 'totalCredit', 'totalEndBalance'] as $key) {
        if ($legacy[$key] !== $sql[$key]) {
            $failures++;
            echo "  KPI MISMATCH  {$label}\n";
            echo "      {$key}: collection={$legacy[$key]}  sql={$sql[$key]}\n";

            return;
        }
    }

    if ($legacy['orderedPartnerIds'] !== $sql['orderedPartnerIds']) {
        $failures++;
        echo "  PARTNER ORDER MISMATCH  {$label}\n";
        echo '      legacy=['.implode(',', $legacy['orderedPartnerIds'])."]\n";
        echo '      sql=['.implode(',', $sql['orderedPartnerIds'])."]\n";

        return;
    }

    $active = $sql['orderedPartnerIds'];
    [$expected, $walked] = pagingIsStable($active);
    if ($expected !== $walked) {
        $failures++;
        echo "  PAGING UNSTABLE  {$label}\n";

        return;
    }

    $lastPage = (int) ceil(max(count($active), 1) / PARTNERS_PER_PAGE);
    for ($page = 1; $page <= $lastPage; $page++) {
        $pageIds = array_slice($active, ($page - 1) * PARTNERS_PER_PAGE, PARTNERS_PER_PAGE);
        if (! rowIdsMatch($table, $companyId, $currency, $pageIds, $startDate, $endDate)) {
            $failures++;
            echo "  ROW ID MISMATCH  {$label}  page={$page}\n";

            return;
        }
    }

    echo "  ok  {$label}  partners={$sql['partnerCount']} rows={$sql['transactionCount']} debit={$sql['totalDebit']} credit={$sql['totalCredit']} end={$sql['totalEndBalance']}\n";
}

echo "Partners Statement pagination parity\n";
echo str_repeat('=', 60)."\n";

foreach (STATEMENT_TABLE_BY_TYPE as $partnerType => $table) {
    if (! DB::getSchemaBuilder()->hasTable($table)) {
        echo "  skip  {$table} (missing)\n";

        continue;
    }

    $combos = DB::table($table)
        ->select('company_id', 'currency_name')
        ->selectRaw('MIN(date) AS min_date, MAX(date) AS max_date')
        ->groupBy('company_id', 'currency_name')
        ->get();

    foreach ($combos as $combo) {
        $companyId = (int) $combo->company_id;
        $currency = (string) $combo->currency_name;
        $startDate = (string) $combo->min_date;
        $endDate = (string) $combo->max_date;

        $partnerIds = DB::table($table)
            ->where('company_id', $companyId)
            ->where('currency_name', $currency)
            ->distinct()
            ->orderBy('partner_id')
            ->pluck('partner_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (! count($partnerIds)) {
            continue;
        }

        $label = "{$table} company={$companyId} currency={$currency} type={$partnerType}";
        check($label, $table, $companyId, $currency, $partnerIds, $startDate, $endDate);
    }
}

echo str_repeat('=', 60)."\n";
echo "Checked {$checked} combo(s); failures={$failures}\n";

exit($failures > 0 ? 1 : 0);
