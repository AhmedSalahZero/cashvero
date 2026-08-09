<?php

namespace App\Support\CashDashboard;

use App\Models\Company;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OverdraftCashDashboardHelper
{
    /**
     * @return array<int, true> currencies that have at least one overdraft row
     */
    public static function currenciesWithRecords(string $table, int $companyId, array $currencies): array
    {
        if ($currencies === []) {
            return [];
        }

        return DB::table($table)
            ->where('company_id', $companyId)
            ->whereIn('currency', $currencies)
            ->distinct()
            ->pluck('currency')
            ->mapWithKeys(fn (string $currency) => [$currency => true])
            ->all();
    }

    public static function overdraftIdsByCurrency(
        string $table,
        int $companyId,
        array $currencies,
        string $date,
        string $startDateColumn = 'contract_start_date'
    ): array {
        if ($currencies === []) {
            return [];
        }

        $grouped = [];
        foreach ($currencies as $currency) {
            $grouped[$currency] = [];
        }

        $rows = DB::table($table)
            ->where('company_id', $companyId)
            ->whereIn('currency', $currencies)
            ->where($startDateColumn, '<=', $date)
            ->orderBy('id')
            ->get(['id', 'currency']);

        foreach ($rows as $row) {
            $grouped[$row->currency][] = (int) $row->id;
        }

        return $grouped;
    }

    public static function overdraftIdsForCurrency(
        string $table,
        int $companyId,
        string $currencyName,
        string $date,
        string $startDateColumn = 'contract_start_date'
    ): array {
        return DB::table($table)
            ->where('currency', $currencyName)
            ->where('company_id', $companyId)
            ->where($startDateColumn, '<=', $date)
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return array<int, object{financial_institution_id:int, limit:float}>
     */
    public static function overdraftMetaById(string $table, array $overdraftIds): array
    {
        if ($overdraftIds === []) {
            return [];
        }

        return DB::table($table)
            ->whereIn('id', $overdraftIds)
            ->get(['id', 'financial_institution_id', 'limit'])
            ->keyBy('id')
            ->all();
    }

    /**
     * @return array<int, object>
     */
    public static function latestStatementsForOverdrafts(
        string $statementTable,
        string $overdraftTable,
        string $foreignKeyColumn,
        int $companyId,
        string $currencyName,
        string $date,
        array $overdraftIds
    ): array {
        if ($overdraftIds === []) {
            return [];
        }

        return LatestStatementQuery::latestStatementsByForeignKey(
            $statementTable,
            $foreignKeyColumn,
            $companyId,
            $date,
            $overdraftIds,
            function (Builder $query) use ($overdraftTable, $foreignKeyColumn, $currencyName) {
                $query->join($overdraftTable.' as overdrafts', 'overdrafts.id', '=', 'statements.'.$foreignKeyColumn)
                    ->where('overdrafts.currency', $currencyName);
            }
        );
    }

    /**
     * YTD interest_amount per overdraft id (same filters as yearCardData interest).
     *
     * @return array<int, float>
     */
    public static function interestByOverdraftId(
        string $statementTable,
        string $overdraftTable,
        string $foreignKeyColumn,
        int $companyId,
        string $currencyName,
        string $date,
        int $year,
        array $overdraftIds
    ): array {
        if ($overdraftIds === []) {
            return [];
        }

        $rows = DB::table($statementTable.' as statements')
            ->join($overdraftTable.' as overdrafts', 'overdrafts.id', '=', 'statements.'.$foreignKeyColumn)
            ->where('statements.company_id', $companyId)
            ->whereRaw('YEAR(statements.date) = ?', [$year])
            ->where('statements.date', '<=', $date)
            ->where('overdrafts.currency', $currencyName)
            ->whereIn('statements.'.$foreignKeyColumn, $overdraftIds)
            ->groupBy('statements.'.$foreignKeyColumn)
            ->selectRaw('statements.'.$foreignKeyColumn.' as overdraft_id, sum(statements.interest_amount) as total_interest')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->overdraft_id] = (float) $row->total_interest;
        }

        return $out;
    }

    public static function applyFinancialInstitutionRoomData(
        array &$totalRoomForEachCurrency,
        string $currencyName,
        array $overdraftIds,
        array $overdraftMetaById,
        array $latestStatementsByOverdraftId,
        int $financialInstitutionBankId,
        string $financialInstitutionName,
        float &$totalRoomAccumulator,
        array $interestByOverdraftId = []
    ): void {
        foreach ($overdraftIds as $overdraftId) {
            $meta = $overdraftMetaById[$overdraftId] ?? null;

            if (! $meta || (int) $meta->financial_institution_id !== $financialInstitutionBankId) {
                continue;
            }

            $statement = $latestStatementsByOverdraftId[$overdraftId] ?? null;
            $room = $statement ? (float) $statement->room : 0.0;
            $totalRoomAccumulator += $room;

            $totalRoomForEachCurrency[$currencyName][] = [
                'item' => $financialInstitutionName,
                'available_room' => $room,
                'limit' => $statement ? (float) $statement->limit : 0.0,
                'end_balance' => $statement ? (float) $statement->end_balance : 0.0,
                'interest' => (float) ($interestByOverdraftId[$overdraftId] ?? 0),
            ];
        }
    }

    /**
     * @return array{limit: float|int, outstanding: float, room: float, interest_amount: float}
     */
    public static function yearCardData(
        string $statementTable,
        string $overdraftTable,
        string $foreignKeyColumn,
        Builder $limitQuery,
        int $companyId,
        string $currencyName,
        string $date,
        int $year,
        array $overdraftIds,
        ?array $latestStatements = null
    ): array {
        if ($overdraftIds === []) {
            return [
                'limit' => 0,
                'outstanding' => 0,
                'room' => 0,
                'interest_amount' => 0,
            ];
        }

        $latestStatements ??= self::latestStatementsForOverdrafts(
            $statementTable,
            $overdraftTable,
            $foreignKeyColumn,
            $companyId,
            $currencyName,
            $date,
            $overdraftIds
        );

        $limit = 0.0;
        $outstanding = 0.0;
        $room = 0.0;
        foreach ($latestStatements as $statement) {
            $limit += (float) ($statement->limit ?? 0);
            $outstanding += (float) ($statement->end_balance ?? 0);
            $room += (float) ($statement->room ?? 0);
        }

        $interestAmount = (float) DB::table($statementTable.' as statements')
            ->join($overdraftTable.' as overdrafts', 'overdrafts.id', '=', 'statements.'.$foreignKeyColumn)
            ->where('statements.company_id', $companyId)
            ->whereRaw('YEAR(statements.date) = ?', [$year])
            ->where('statements.date', '<=', $date)
            ->where('overdrafts.currency', $currencyName)
            ->whereIn('statements.'.$foreignKeyColumn, $overdraftIds)
            ->sum('statements.interest_amount');

        return [
            'limit' => $limit,
            'outstanding' => $outstanding,
            'room' => $room,
            'interest_amount' => $interestAmount,
        ];
    }
}
