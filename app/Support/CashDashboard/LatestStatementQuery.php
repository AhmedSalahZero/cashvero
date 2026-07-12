<?php

namespace App\Support\CashDashboard;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class LatestStatementQuery
{
    /**
     * Latest statement row id per foreign key (ordered by date desc, id desc).
     */
    public static function latestIdSubquery(
        string $statementTable,
        string $foreignKeyColumn,
        int $companyId,
        string $date,
        ?array $foreignKeyIds = null
    ): Builder {
        $query = DB::table($statementTable)
            ->select([
                $foreignKeyColumn,
                DB::raw(
                    "SUBSTRING_INDEX(GROUP_CONCAT(id ORDER BY date DESC, id DESC SEPARATOR ','), ',', 1) as latest_id"
                ),
            ])
            ->where('company_id', $companyId)
            ->where('date', '<=', $date);

        if ($foreignKeyIds !== null && $foreignKeyIds !== []) {
            $query->whereIn($foreignKeyColumn, $foreignKeyIds);
        }

        return $query->groupBy($foreignKeyColumn);
    }

    /**
     * @return array<int, object> keyed by foreign key id
     */
    public static function latestStatementsByForeignKey(
        string $statementTable,
        string $foreignKeyColumn,
        int $companyId,
        string $date,
        ?array $foreignKeyIds = null,
        ?callable $joinParent = null
    ): array {
        $latestSubquery = self::latestIdSubquery(
            $statementTable,
            $foreignKeyColumn,
            $companyId,
            $date,
            $foreignKeyIds
        );

        $query = DB::table($statementTable.' as statements')
            ->joinSub($latestSubquery, 'latest_rows', function ($join) {
                $join->on('statements.id', '=', 'latest_rows.latest_id');
            })
            ->where('statements.company_id', $companyId);

        if ($joinParent !== null) {
            $joinParent($query);
        }

        return $query
            ->select('statements.*')
            ->get()
            ->keyBy($foreignKeyColumn)
            ->all();
    }

    /**
     * @return array<int, object> keyed by financial_institution_account_id
     */
    public static function latestCurrentAccountBalances(
        int $companyId,
        string $date,
        array $bankIds,
        array $currencies
    ): array {
        if ($bankIds === [] || $currencies === []) {
            return [];
        }

        $latestSubquery = DB::table('current_account_bank_statements')
            ->select([
                'financial_institution_account_id',
                DB::raw(
                    "SUBSTRING_INDEX(GROUP_CONCAT(id ORDER BY full_date DESC, id DESC SEPARATOR ','), ',', 1) as latest_id"
                ),
            ])
            ->where('company_id', $companyId)
            ->where('date', '<=', $date)
            ->groupBy('financial_institution_account_id');

        return DB::table('current_account_bank_statements as statements')
            ->joinSub($latestSubquery, 'latest_rows', function ($join) {
                $join->on('statements.id', '=', 'latest_rows.latest_id');
            })
            ->join('financial_institution_accounts as accounts', 'accounts.id', '=', 'statements.financial_institution_account_id')
            ->where('accounts.company_id', $companyId)
            ->whereIn('accounts.financial_institution_id', $bankIds)
            ->whereIn('accounts.currency', $currencies)
            ->where('accounts.is_active', 1)
            ->select([
                'statements.end_balance',
                'accounts.account_number',
                'accounts.currency',
                'accounts.financial_institution_id',
            ])
            ->get()
            ->groupBy('currency')
            ->map(fn ($rows) => $rows->keyBy(fn ($row) => $row->financial_institution_id.'|'.$row->account_number))
            ->all();
    }

    /**
     * @return array<string, array<int, object>> currency => branch_id => row
     */
    public static function latestCashInSafeByBranch(
        int $companyId,
        string $date,
        array $branchIds,
        array $currencies
    ): array {
        if ($branchIds === [] || $currencies === []) {
            return [];
        }

        $latestSubquery = DB::table('cash_in_safe_statements')
            ->select([
                'branch_id',
                'currency',
                DB::raw(
                    "SUBSTRING_INDEX(GROUP_CONCAT(id ORDER BY date DESC, id DESC SEPARATOR ','), ',', 1) as latest_id"
                ),
            ])
            ->where('company_id', $companyId)
            ->where('date', '<=', $date)
            ->whereIn('branch_id', $branchIds)
            ->whereIn('currency', $currencies)
            ->groupBy('branch_id', 'currency');

        $rows = DB::table('cash_in_safe_statements as statements')
            ->joinSub($latestSubquery, 'latest_rows', function ($join) {
                $join->on('statements.id', '=', 'latest_rows.latest_id');
            })
            ->where('statements.company_id', $companyId)
            ->select(['statements.end_balance', 'statements.branch_id', 'statements.currency'])
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$row->currency][$row->branch_id] = $row;
        }

        return $result;
    }
}
