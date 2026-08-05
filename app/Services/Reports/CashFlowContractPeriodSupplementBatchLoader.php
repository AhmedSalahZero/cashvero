<?php

namespace App\Services\Reports;

use App\Enums\LcTypes;
use App\Enums\LgTypes;
use App\Models\Contract;
use App\Models\ForeignExchangeRate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Batches LC/LG fees and settlement allocations for contract cash-flow supplements (per week).
 */
class CashFlowContractPeriodSupplementBatchLoader
{
  
    public static function apply(
        array &$result,
        Collection $contracts,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
    ): void {
        if ($contracts->isEmpty()) {
            return;
        }

        $contractIds = $contracts->pluck('id')->map(fn ($id) => (int) $id)->all();
        $contractsById = $contracts->keyBy('id');

        self::applySettlementAllocations(
            $result,
            $contractIds,
            $contractsById,
            $foreignExchangeRates,
            $mainFunctionalCurrency,
            $companyId,
            $periodStart,
            $periodEnd,
            $periodsByWeekKey,
        );

        self::applyLetterOfGuaranteeFees(
            $result,
            $contractIds,
            $foreignExchangeRates,
            $mainFunctionalCurrency,
            $companyId,
            $periodStart,
            $periodEnd,
            $periodsByWeekKey,
        );

        self::applyLetterOfCreditFees(
            $result,
            $contractIds,
            $foreignExchangeRates,
            $mainFunctionalCurrency,
            $companyId,
            $periodStart,
            $periodEnd,
            $periodsByWeekKey,
        );

        self::applyLetterOfCreditRemaining(
            $result,
            $contractIds,
            $foreignExchangeRates,
            $mainFunctionalCurrency,
            $companyId,
            $periodStart,
            $periodEnd,
            $periodsByWeekKey,
        );
    }

  /**
     * @param  list<int>  $contractIds
     * @param  Collection<int, Contract>  $contractsById
     * @param  array<string, array<string, string>>  $periodsByWeekKey
     */
    private static function applySettlementAllocations(
        array &$result,
        array $contractIds,
        Collection $contractsById,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
    ): void {
        $keyNameForCurrentType = __('Letter Of Credit');

        $rows = DB::table('settlement_allocations')
            ->select([
                'settlement_allocations.contract_id',
                'settlement_allocations.allocation_amount',
                'letter_of_credit_issuances.payment_currency',
                'letter_of_credit_issuances.payment_date',
                'letter_of_credit_issuances.due_date',
                'letter_of_credit_issuances.lc_type',
            ])
            ->join('letter_of_credit_issuances', 'settlement_allocations.letter_of_credit_issuance_id', '=', 'letter_of_credit_issuances.id')
            ->whereIn('settlement_allocations.contract_id', $contractIds)
            ->whereBetween('letter_of_credit_issuances.due_date', [$periodStart, $periodEnd])
            ->where('letter_of_credit_issuances.company_id', $companyId)
            ->get();

        $lcsTypes = LcTypes::getAll();

        foreach ($rows as $row) {
            $contract = $contractsById->get((int) $row->contract_id);
            if (! $contract) {
                continue;
            }

            $weekKey = CashFlowWeekBucketer::resolveWeekKey((string) $row->due_date, $periodsByWeekKey);
            if ($weekKey === null) {
                continue;
            }

            $exchangeRate = ForeignExchangeRate::getExchangeRateAt(
                (string) $row->payment_currency,
                $mainFunctionalCurrency,
                (string) $row->payment_date,
                $companyId,
                $foreignExchangeRates,
            );
            $amount = (float) $row->allocation_amount * $exchangeRate;
            $lcType = $lcsTypes[$row->lc_type] ?? $row->lc_type;

            if (! isset($result['suppliers'][$keyNameForCurrentType][$lcType])) {
                $result['suppliers'][$keyNameForCurrentType][$lcType] = ['weeks' => [], 'total' => []];
            }
            if (! isset($result['suppliers'][$keyNameForCurrentType][$lcType]['weeks'][$weekKey])) {
                $result['suppliers'][$keyNameForCurrentType][$lcType]['weeks'][$weekKey] = 0;
            }
            if (! isset($result['suppliers'][$keyNameForCurrentType][$lcType]['total'][$weekKey])) {
                $result['suppliers'][$keyNameForCurrentType][$lcType]['total'][$weekKey] = 0;
            }
            if (! isset($result['suppliers'][$keyNameForCurrentType]['total'][$weekKey])) {
                $result['suppliers'][$keyNameForCurrentType]['total'][$weekKey] = 0;
            }

            $result['suppliers'][$keyNameForCurrentType][$lcType]['weeks'][$weekKey] += $amount;
            $result['suppliers'][$keyNameForCurrentType][$lcType]['total'][$weekKey] += $amount;
            $result['suppliers'][$keyNameForCurrentType]['total'][$weekKey] += $amount;
            $result['cash_expenses'][$keyNameForCurrentType]['total'][$weekKey] = ($result['cash_expenses'][$keyNameForCurrentType]['total'][$weekKey] ?? 0) + $amount;
        }
    }

    /**
     * @param  list<int>  $contractIds
     * @param  array<string, array<string, string>>  $periodsByWeekKey
     */
    private static function applyLetterOfGuaranteeFees(
        array &$result,
        array $contractIds,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
    ): void {
        $lgsTypes = LgTypes::getAll();
        $mainType = __('Letter Of Guarantee');
        $subTypeFees = __('Fees');
        $subTypeCover = __('Cash Cover');
        $totalCashInFlowKey = __('Total Cash Inflow');

        $feeRows = DB::table('current_account_bank_statements')
            ->select([
                'letter_of_guarantee_issuances.lg_type',
                'financial_institution_accounts.currency',
                'current_account_bank_statements.date',
                DB::raw('sum(credit) as total_amount'),
            ])
            ->join('financial_institution_accounts', 'financial_institution_accounts.id', '=', 'current_account_bank_statements.financial_institution_account_id')
            ->join('letter_of_guarantee_issuances', 'letter_of_guarantee_issuances.id', '=', 'current_account_bank_statements.letter_of_guarantee_issuance_id')
            ->where('current_account_bank_statements.company_id', $companyId)
            ->whereBetween('current_account_bank_statements.date', [$periodStart, $periodEnd])
            ->where('current_account_bank_statements.letter_of_guarantee_issuance_id', '>', 0)
            ->where(function ($q) {
                $q->where('is_renewal_fees', 1)
                    ->orWhere('is_commission_fees', 1)
                    ->orWhere('is_issuance_fees', 1);
            })
            ->whereIn('letter_of_guarantee_issuances.contract_id', $contractIds)
            ->groupBy('letter_of_guarantee_issuances.lg_type', 'financial_institution_accounts.currency', 'current_account_bank_statements.date')
            ->get();

        foreach ($feeRows as $row) {
            self::applyLgFeeRow($result, $row, $lgsTypes, $mainType, $subTypeFees, $totalCashInFlowKey, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodsByWeekKey, false);
        }

        $coverSumQuery = DB::table('letter_of_guarantee_cash_cover_statements')
            ->join('letter_of_guarantee_issuances', 'letter_of_guarantee_issuances.id', '=', 'letter_of_guarantee_cash_cover_statements.letter_of_guarantee_issuance_id')
            ->where('letter_of_guarantee_cash_cover_statements.company_id', $companyId);
        $coverSumQuery = LgCashCoverEffectiveDate::joinTo($coverSumQuery);
        $effectiveDateSql = LgCashCoverEffectiveDate::sql();
        $coverSumRows = $coverSumQuery
            ->selectRaw('letter_of_guarantee_issuances.lg_type, letter_of_guarantee_cash_cover_statements.currency, '.$effectiveDateSql.' as movement_date, sum(debit) as total_amount')
            ->whereBetween(DB::raw($effectiveDateSql), [$periodStart, $periodEnd])
            ->where('letter_of_guarantee_cash_cover_statements.letter_of_guarantee_issuance_id', '>', 0)
            ->whereIn('letter_of_guarantee_issuances.contract_id', $contractIds)
            ->groupByRaw('letter_of_guarantee_issuances.lg_type, letter_of_guarantee_cash_cover_statements.currency, '.$effectiveDateSql)
            ->get();

        foreach ($coverSumRows as $row) {
            self::applyLgFeeRow($result, $row, $lgsTypes, $mainType, $subTypeCover, $totalCashInFlowKey, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodsByWeekKey, true);
        }

        $coverDetailQuery = DB::table('letter_of_guarantee_cash_cover_statements')
            ->join('letter_of_guarantee_issuances', 'letter_of_guarantee_issuances.id', '=', 'letter_of_guarantee_cash_cover_statements.letter_of_guarantee_issuance_id')
            ->where('letter_of_guarantee_cash_cover_statements.company_id', $companyId);
        $coverDetailQuery = LgCashCoverEffectiveDate::joinTo($coverDetailQuery);
        $coverDetailRows = $coverDetailQuery
            ->selectRaw('letter_of_guarantee_issuances.lg_type, letter_of_guarantee_cash_cover_statements.currency, '.$effectiveDateSql.' as movement_date, letter_of_guarantee_cash_cover_statements.debit as total_amount')
            ->whereBetween(DB::raw($effectiveDateSql), [$periodStart, $periodEnd])
            ->where('letter_of_guarantee_cash_cover_statements.letter_of_guarantee_issuance_id', '>', 0)
            ->whereIn('letter_of_guarantee_issuances.contract_id', $contractIds)
            ->get();

        foreach ($coverDetailRows as $row) {
            self::applyLgFeeRow($result, $row, $lgsTypes, $mainType, $subTypeCover, $totalCashInFlowKey, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodsByWeekKey, true);
        }
    }

    /**
     * @param  list<int>  $contractIds
     * @param  array<string, array<string, string>>  $periodsByWeekKey
     */
    private static function applyLetterOfCreditFees(
        array &$result,
        array $contractIds,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
    ): void {
        $lcsTypes = LcTypes::getAll();
        $keyNameForCurrentType = __('Letter Of Credit');

        $feeRows = DB::table('current_account_bank_statements')
            ->select([
                'letter_of_credit_issuances.lc_type',
                'financial_institution_accounts.currency',
                'current_account_bank_statements.date',
                DB::raw('sum(credit) as paid_amount'),
            ])
            ->join('financial_institution_accounts', 'financial_institution_accounts.id', '=', 'current_account_bank_statements.financial_institution_account_id')
            ->join('letter_of_credit_issuances', 'letter_of_credit_issuances.id', '=', 'current_account_bank_statements.letter_of_credit_issuance_id')
            ->where('current_account_bank_statements.company_id', $companyId)
            ->whereBetween('current_account_bank_statements.date', [$periodStart, $periodEnd])
            ->where('current_account_bank_statements.letter_of_credit_issuance_id', '>', 0)
            ->where(function ($q) {
                $q->where('is_renewal_fees', 1)
                    ->orWhere('is_commission_fees', 1)
                    ->orWhere('is_issuance_fees', 1);
            })
            ->whereIn('letter_of_credit_issuances.contract_id', $contractIds)
            ->groupBy('letter_of_credit_issuances.lc_type', 'financial_institution_accounts.currency', 'current_account_bank_statements.date')
            ->get();

        foreach ($feeRows as $row) {
            $weekKey = CashFlowWeekBucketer::resolveWeekKey((string) $row->date, $periodsByWeekKey);
            if ($weekKey === null) {
                continue;
            }

            $exchangeRate = ForeignExchangeRate::getExchangeRateAt(
                (string) $row->currency,
                $mainFunctionalCurrency,
                (string) $row->date,
                $companyId,
                $foreignExchangeRates,
            );
            $amount = (float) $row->paid_amount * $exchangeRate;
            $lcType = $lcsTypes[$row->lc_type] ?? $row->lc_type;

            if (! isset($result['suppliers'][$keyNameForCurrentType][$lcType])) {
                $result['suppliers'][$keyNameForCurrentType][$lcType] = ['weeks' => [], 'total' => []];
            }
            if (! isset($result['suppliers'][$keyNameForCurrentType][$lcType]['weeks'][$weekKey])) {
                $result['suppliers'][$keyNameForCurrentType][$lcType]['weeks'][$weekKey] = 0;
            }
            if (! isset($result['suppliers'][$keyNameForCurrentType][$lcType]['total'][$weekKey])) {
                $result['suppliers'][$keyNameForCurrentType][$lcType]['total'][$weekKey] = 0;
            }
            if (! isset($result['suppliers'][$keyNameForCurrentType]['total'][$weekKey])) {
                $result['suppliers'][$keyNameForCurrentType]['total'][$weekKey] = 0;
            }

            $result['suppliers'][$keyNameForCurrentType][$lcType]['weeks'][$weekKey] += $amount;
            $result['suppliers'][$keyNameForCurrentType][$lcType]['total'][$weekKey] += $amount;
            $result['suppliers'][$keyNameForCurrentType]['total'][$weekKey] += $amount;
            $result['cash_expenses'][$keyNameForCurrentType]['total'][$weekKey] = ($result['cash_expenses'][$keyNameForCurrentType]['total'][$weekKey] ?? 0) + $amount;
        }
    }

    /**
     * @param  list<int>  $contractIds
     * @param  array<string, array<string, string>>  $periodsByWeekKey
     */
    private static function applyLetterOfCreditRemaining(
        array &$result,
        array $contractIds,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
    ): void {
        $lcsTypes = LcTypes::getAll();
        $keyNameForCurrentType = __('Letter Of Credit');

        $rows = DB::table('letter_of_credit_issuances')
            ->select([
                'due_date',
                'lc_type',
                'lc_cash_cover_currency as currency',
                DB::raw('(amount_in_main_currency - cash_cover_amount) as paid_amount'),
            ])
            ->where('letter_of_credit_issuances.company_id', $companyId)
            ->where('letter_of_credit_issuances.status', 'running')
            ->whereBetween('letter_of_credit_issuances.due_date', [$periodStart, $periodEnd])
            ->whereIn('letter_of_credit_issuances.contract_id', $contractIds)
            ->get();

        foreach ($rows as $row) {
            $weekKey = CashFlowWeekBucketer::resolveWeekKey((string) $row->due_date, $periodsByWeekKey);
            if ($weekKey === null) {
                continue;
            }

            $exchangeRate = ForeignExchangeRate::getExchangeRateAt(
                (string) $row->currency,
                $mainFunctionalCurrency,
                (string) $row->due_date,
                $companyId,
                $foreignExchangeRates,
            );
            $amount = (float) $row->paid_amount * $exchangeRate;
            $lcType = $lcsTypes[$row->lc_type] ?? $row->lc_type;

            if (! isset($result['suppliers'][$keyNameForCurrentType][$lcType])) {
                $result['suppliers'][$keyNameForCurrentType][$lcType] = ['weeks' => [], 'total' => []];
            }
            if (! isset($result['suppliers'][$keyNameForCurrentType][$lcType]['weeks'][$weekKey])) {
                $result['suppliers'][$keyNameForCurrentType][$lcType]['weeks'][$weekKey] = 0;
            }
            if (! isset($result['suppliers'][$keyNameForCurrentType][$lcType]['total'][$weekKey])) {
                $result['suppliers'][$keyNameForCurrentType][$lcType]['total'][$weekKey] = 0;
            }
            if (! isset($result['suppliers'][$keyNameForCurrentType]['total'][$weekKey])) {
                $result['suppliers'][$keyNameForCurrentType]['total'][$weekKey] = 0;
            }

            $result['suppliers'][$keyNameForCurrentType][$lcType]['weeks'][$weekKey] += $amount;
            $result['suppliers'][$keyNameForCurrentType][$lcType]['total'][$weekKey] += $amount;
            $result['suppliers'][$keyNameForCurrentType]['total'][$weekKey] += $amount;
            $result['cash_expenses'][$keyNameForCurrentType]['total'][$weekKey] = ($result['cash_expenses'][$keyNameForCurrentType]['total'][$weekKey] ?? 0) + $amount;
        }
    }

    /**
     * @param  object  $row
     * @param  array<string, string>  $lgsTypes
     */
    private static function applyLgFeeRow(
        array &$result,
        object $row,
        array $lgsTypes,
        string $mainType,
        string $subType,
        string $totalCashInFlowKey,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        array $periodsByWeekKey,
        bool $isCashCover,
    ): void {
        $dateField = $isCashCover ? 'movement_date' : 'date';
        $weekKey = CashFlowWeekBucketer::resolveWeekKey((string) $row->{$dateField}, $periodsByWeekKey);
        if ($weekKey === null) {
            return;
        }

        $exchangeRate = ForeignExchangeRate::getExchangeRateAt(
            (string) $row->currency,
            $mainFunctionalCurrency,
            (string) $row->{$dateField},
            $companyId,
            $foreignExchangeRates,
        );
        // Fee + cash-cover queries both alias the amount as `total_amount`.
        $amount = (float) $row->total_amount * $exchangeRate;
        $lgType = $lgsTypes[$row->lg_type] ?? $row->lg_type;

        if (! isset($result[$mainType][$subType][$lgType])) {
            $result[$mainType][$subType][$lgType] = ['weeks' => [], 'total' => []];
        }
        if (! isset($result[$mainType][$subType][$lgType]['weeks'][$weekKey])) {
            $result[$mainType][$subType][$lgType]['weeks'][$weekKey] = 0;
        }
        if (! isset($result[$mainType][$subType][$lgType]['total'][$weekKey])) {
            $result[$mainType][$subType][$lgType]['total'][$weekKey] = 0;
        }
        if (! isset($result[$mainType][$subType]['total'][$weekKey])) {
            $result[$mainType][$subType]['total'][$weekKey] = 0;
        }

        $result[$mainType][$subType][$lgType]['weeks'][$weekKey] += $amount;
        $result[$mainType][$subType][$lgType]['total'][$weekKey] += $amount;
        $result[$mainType][$subType]['total'][$weekKey] += $amount;

        if ($isCashCover) {
            $result['customers'][$subType]['total'][$weekKey] = ($result['customers'][$subType]['total'][$weekKey] ?? 0) + $amount;
            $result['customers'][$totalCashInFlowKey]['total'][$weekKey] = ($result['customers'][$totalCashInFlowKey]['total'][$weekKey] ?? 0) + $amount;
        } else {
            $result['cash_expenses'][$mainType]['total'][$weekKey] = ($result['cash_expenses'][$mainType]['total'][$weekKey] ?? 0) + $amount;
        }
    }
}
