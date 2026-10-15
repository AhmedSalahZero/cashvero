<?php

namespace App\Services\Reports;

use App\Models\CashExpense;
use App\Models\Cheque;
use App\Models\ForeignExchangeRate;
use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use App\Models\PayableCheque;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Loads cash-flow movements for all contracts in one query per movement type (full report period),
 * then buckets amounts into week/month/day columns in PHP.
 *
 * Column map (aligned with CashFlowReportController):
 * - money_received settlements: receiving_date/currency + settlement_amount converted
 *   to receiving currency before report FX conversion to avoid fan-out and FX drift
 * - money_received: receiving_date, receiving_currency, received_amount; cheques: expected/actual_collection_date, due_date
 * - money_payments: delivery_date, payment_currency; payable_cheques: actual_payment_date, due_date, status
 * - settlement_allocations: allocation_amount, contract_id
 * - cash_expenses: type, payment_date, currency; cash_expense_contract: amount
 * - payable_cheques (cash expense): actual_payment_date, due_date, status (no payment_date column)
 */
final class CashFlowPeriodBatchLoader
{
    /**
     * @param  array<string, array<string, mixed>>  $resultsByContractCode
     * @param  array<string, array{start_date: string, end_date: string}>  $periodsByWeekKey
     * @param  list<string>  $contractCodes
     */
    public static function applyContractPeriodMovements(
        array &$resultsByContractCode,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $contractCodes,
        array $periodsByWeekKey,
        array $contractIds,
    ): void {
        if ($contractCodes === []) {
            return;
        }

        self::applyMoneyReceivedSettlements($resultsByContractCode, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $contractCodes, $periodsByWeekKey, MoneyReceived::CHEQUE, 'expected_collection_date', Cheque::UNDER_COLLECTION);
        self::applyMoneyReceivedSettlements($resultsByContractCode, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $contractCodes, $periodsByWeekKey, MoneyReceived::CHEQUE, 'actual_collection_date', Cheque::COLLECTED);
        self::applyMoneyReceivedSettlements($resultsByContractCode, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $contractCodes, $periodsByWeekKey, MoneyReceived::INCOMING_TRANSFER, 'receiving_date');
        self::applyMoneyReceivedSettlements($resultsByContractCode, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $contractCodes, $periodsByWeekKey, MoneyReceived::CASH_IN_BANK, 'receiving_date');
        self::applyMoneyReceivedSettlements($resultsByContractCode, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $contractCodes, $periodsByWeekKey, MoneyReceived::CASH_IN_SAFE, 'receiving_date');
        self::applyMoneyReceivedSettlements($resultsByContractCode, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $contractCodes, $periodsByWeekKey, MoneyReceived::CHEQUE, 'due_date', Cheque::IN_SAFE);

        self::applyDownPayments($resultsByContractCode, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $contractIds, $periodsByWeekKey);

        self::applyMoneyPaymentOutflows($resultsByContractCode, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $contractIds, $periodsByWeekKey, MoneyPayment::OUTGOING_TRANSFER, 'delivery_date');
        self::applyMoneyPaymentOutflows($resultsByContractCode, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $contractIds, $periodsByWeekKey, MoneyPayment::CASH_PAYMENT, 'delivery_date');
        self::applyMoneyPaymentOutflows($resultsByContractCode, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $contractIds, $periodsByWeekKey, MoneyPayment::PAYABLE_CHEQUE, 'actual_payment_date', PayableCheque::PAID);
        self::applyMoneyPaymentOutflows($resultsByContractCode, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $contractIds, $periodsByWeekKey, MoneyPayment::PAYABLE_CHEQUE, 'due_date', PayableCheque::PENDING);

        self::applyCashExpenseOutflows($resultsByContractCode, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $contractIds, $periodsByWeekKey, CashExpense::OUTGOING_TRANSFER, 'payment_date');
        self::applyCashExpenseOutflows($resultsByContractCode, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $contractIds, $periodsByWeekKey, CashExpense::CASH_PAYMENT, 'payment_date');
        self::applyCashExpenseOutflows($resultsByContractCode, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $contractIds, $periodsByWeekKey, CashExpense::PAYABLE_CHEQUE, 'actual_payment_date', PayableCheque::PAID);
        self::applyCashExpenseOutflows($resultsByContractCode, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $contractIds, $periodsByWeekKey, CashExpense::PAYABLE_CHEQUE, 'due_date', PayableCheque::PENDING);
    }

    /**
     * @param  array<string, array<string, mixed>>  $resultsByContractCode
     * @param  array<string, array{start_date: string, end_date: string}>  $periodsByWeekKey
     * @param  list<string>  $contractCodes
     */
    private static function applyMoneyReceivedSettlements(
        array &$resultsByContractCode,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $contractCodes,
        array $periodsByWeekKey,
        string $moneyType,
        string $dateColumnName,
        ?string $chequeStatus = null,
    ): void {
        $totalCashInFlowKey = __('Total Cash Inflow');
        $currentTypeText = self::moneyReceivedTypeLabel($moneyType, $chequeStatus);

        $query = DB::table('money_received')
            ->where('money_received.company_id', $companyId)
            ->when($chequeStatus, function ($builder) use ($chequeStatus) {
                $builder->join('cheques', 'cheques.money_received_id', '=', 'money_received.id')
                    ->where('cheques.status', $chequeStatus);
            })
            ->join('settlements', 'money_received.id', '=', 'settlements.money_received_id')
            ->join('customer_invoices', 'customer_invoices.id', '=', 'settlements.invoice_id')
            ->whereIn('customer_invoices.contract_code', $contractCodes)
            ->where(function ($q) {
                $q->whereNull('money_received.down_payment_type')->orWhere('money_received.down_payment_type', '=', 'general');
            })
            ->where('money_received.type', '=', $moneyType);
        $dateColumn = self::qualifiedMoneyReceivedDateColumn($dateColumnName, $chequeStatus !== null);
        $settlementAmountExpression = self::settlementAmountInReceivingCurrencySql();
        $query
            ->whereBetween($dateColumn, [$periodStart, $periodEnd])
            // Use each settlement's share, converted to receiving currency when
            // needed, so a receipt split across N invoices is not counted N times.
            ->selectRaw('customer_invoices.contract_code as contract_code, '.$settlementAmountExpression.' as received_amount, money_received.receiving_currency, '.$dateColumn.' as movement_date');

        foreach ($query->cursor() as $row) {
            $code = (string) $row->contract_code;
            if (! isset($resultsByContractCode[$code])) {
                continue;
            }
            $weekKey = CashFlowWeekBucketer::resolveWeekKey((string) $row->movement_date, $periodsByWeekKey);
            if ($weekKey === null) {
                continue;
            }
            $exchangeRate = ForeignExchangeRate::getExchangeRateAt(
                (string) $row->receiving_currency,
                $mainFunctionalCurrency,
                (string) $row->movement_date,
                $companyId,
                $foreignExchangeRates,
            );
            $amount = (float) $row->received_amount * (float) $exchangeRate;
            $result = &$resultsByContractCode[$code];
            $result['customers'][$currentTypeText]['total'][$weekKey] = ($result['customers'][$currentTypeText]['total'][$weekKey] ?? 0) + $amount;
            $result['customers'][$totalCashInFlowKey]['total'][$weekKey] = ($result['customers'][$totalCashInFlowKey]['total'][$weekKey] ?? 0) + $amount;
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $resultsByContractCode
     * @param  array<string, array{start_date: string, end_date: string}>  $periodsByWeekKey
     * @param  list<int>  $contractIds
     */
    private static function applyDownPayments(
        array &$resultsByContractCode,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $contractIds,
        array $periodsByWeekKey,
    ): void {
        $configs = [
            [MoneyReceived::CHEQUE, 'expected_collection_date', Cheque::UNDER_COLLECTION],
            [MoneyReceived::CHEQUE, 'actual_collection_date', Cheque::COLLECTED],
            [MoneyReceived::INCOMING_TRANSFER, 'receiving_date', null],
            [MoneyReceived::CASH_IN_BANK, 'receiving_date', null],
            [MoneyReceived::CASH_IN_SAFE, 'receiving_date', null],
        ];

        $totalCashInFlowKey = __('Total Cash Inflow');

        foreach ($configs as [$moneyType, $dateColumn, $chequeStatus]) {
            $currentTypeText = self::moneyReceivedTypeLabel($moneyType, $chequeStatus);
            $query = DB::table('money_received')
                ->where('money_received.company_id', $companyId)
                ->when($chequeStatus, function ($builder) use ($chequeStatus) {
                    $builder->join('cheques', 'cheques.money_received_id', '=', 'money_received.id')
                        ->where('cheques.status', $chequeStatus);
                })
                ->join('contracts', 'contracts.id', '=', 'money_received.contract_id')
                ->whereIn('money_received.contract_id', $contractIds)
                ->where('money_received.down_payment_type', '=', 'over_contract')
                ->where('money_received.type', '=', $moneyType)
                ->whereNotNull('money_received.contract_id');
            $qualifiedDate = self::qualifiedMoneyReceivedDateColumn($dateColumn, $chequeStatus !== null);
            $query
                ->whereBetween($qualifiedDate, [$periodStart, $periodEnd])
                ->selectRaw('contracts.code as contract_code, money_received.received_amount, money_received.receiving_currency, money_received.receiving_date as movement_date');

            foreach ($query->cursor() as $row) {
                $code = (string) $row->contract_code;
                if ($code === '' || ! isset($resultsByContractCode[$code])) {
                    continue;
                }
                $weekKey = CashFlowWeekBucketer::resolveWeekKey((string) $row->movement_date, $periodsByWeekKey);
                if ($weekKey === null) {
                    continue;
                }
                $exchangeRate = ForeignExchangeRate::getExchangeRateAt(
                    (string) $row->receiving_currency,
                    $mainFunctionalCurrency,
                    (string) $row->movement_date,
                    $companyId,
                    $foreignExchangeRates,
                );
                $amount = (float) $row->received_amount * (float) $exchangeRate;
                $result = &$resultsByContractCode[$code];
                $result['customers'][$currentTypeText]['total'][$weekKey] = ($result['customers'][$currentTypeText]['total'][$weekKey] ?? 0) + $amount;
                $result['customers'][$totalCashInFlowKey]['total'][$weekKey] = ($result['customers'][$totalCashInFlowKey]['total'][$weekKey] ?? 0) + $amount;
            }
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $resultsByContractCode
     * @param  array<string, array{start_date: string, end_date: string}>  $periodsByWeekKey
     * @param  list<int>  $contractIds
     */
    private static function applyMoneyPaymentOutflows(
        array &$resultsByContractCode,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $contractIds,
        array $periodsByWeekKey,
        string $moneyType,
        string $dateFieldName,
        ?string $chequeStatus = null,
    ): void {
        $keyNameForCurrentType = [
            MoneyPayment::OUTGOING_TRANSFER => __('Outgoing Transfers'),
            MoneyPayment::CASH_PAYMENT => __('Cash Payments'),
            MoneyPayment::PAYABLE_CHEQUE => $chequeStatus === PayableCheque::PAID
                ? __('Paid Payable Cheques')
                : __('Under Payment Payable Cheques'),
        ][$moneyType];

        $query = DB::table('money_payments')
            ->where('money_payments.company_id', $companyId)
            ->when($chequeStatus, function ($builder) use ($chequeStatus) {
                $builder->join('payable_cheques', 'payable_cheques.money_payment_id', '=', 'money_payments.id')
                    ->where('payable_cheques.status', $chequeStatus);
            })
            ->join('settlement_allocations', 'money_payments.id', '=', 'settlement_allocations.money_payment_id')
            ->join('contracts', 'contracts.id', '=', 'settlement_allocations.contract_id')
            ->whereIn('settlement_allocations.contract_id', $contractIds)
            ->where('money_payments.type', '=', $moneyType);
        $dateColumn = self::qualifiedMoneyPaymentDateColumn($moneyType, $dateFieldName, $chequeStatus !== null);
        $query
            ->whereBetween($dateColumn, [$periodStart, $periodEnd])
            ->selectRaw('contracts.code as contract_code, settlement_allocations.allocation_amount as paid_amount, money_payments.payment_currency, '.$dateColumn.' as movement_date');

        foreach ($query->cursor() as $row) {
            $code = (string) $row->contract_code;
            if (! isset($resultsByContractCode[$code])) {
                continue;
            }
            $movementDate = (string) $row->movement_date;
            $weekKey = CashFlowWeekBucketer::resolveWeekKey($movementDate, $periodsByWeekKey);
            if ($weekKey === null) {
                continue;
            }
            $exchangeRate = ForeignExchangeRate::getExchangeRateAt(
                (string) $row->payment_currency,
                $mainFunctionalCurrency,
                $movementDate,
                $companyId,
                $foreignExchangeRates,
            );
            $amount = (float) $row->paid_amount * (float) $exchangeRate;
            $result = &$resultsByContractCode[$code];
            $result['suppliers'][$keyNameForCurrentType]['total'][$weekKey] = ($result['suppliers'][$keyNameForCurrentType]['total'][$weekKey] ?? 0) + $amount;
        }
    }

    /**
     * @param  array<string, array<string, mixed>>  $resultsByContractCode
     * @param  array<string, array{start_date: string, end_date: string}>  $periodsByWeekKey
     * @param  list<int>  $contractIds
     */
    private static function applyCashExpenseOutflows(
        array &$resultsByContractCode,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $contractIds,
        array $periodsByWeekKey,
        string $moneyType,
        string $dateFieldName,
        ?string $chequeStatus = null,
    ): void {
        $subTableName = (new CashExpense)->getTable();
        $mainTableName = [
            CashExpense::OUTGOING_TRANSFER => 'outgoing_transfers',
            CashExpense::CASH_PAYMENT => 'cash_payments',
            CashExpense::PAYABLE_CHEQUE => 'payable_cheques',
        ][$moneyType];

        $dateColumn = self::qualifiedCashExpenseDateColumn($moneyType, $dateFieldName);
        $amountColumn = 'cash_expense_contract.amount';
        $currencyColumn = $subTableName.'.currency';

        $query = DB::table($mainTableName)
            ->where($subTableName.'.type', $moneyType)
            ->where($subTableName.'.company_id', $companyId)
            ->join($subTableName, $subTableName.'.id', '=', $mainTableName.'.cash_expense_id')
            ->whereBetween($dateColumn, [$periodStart, $periodEnd])
            ->join('cash_expense_category_names', $subTableName.'.cash_expense_category_name_id', '=', 'cash_expense_category_names.id')
            ->join('cash_expense_categories', 'cash_expense_category_names.cash_expense_category_id', '=', 'cash_expense_categories.id')
            ->join('cash_expense_contract', 'cash_expense_contract.cash_expense_id', '=', 'cash_expenses.id')
            ->join('contracts', 'contracts.id', '=', 'cash_expense_contract.contract_id')
            ->whereIn('cash_expense_contract.contract_id', $contractIds)
            ->when($chequeStatus, function ($builder) use ($chequeStatus, $mainTableName) {
                $builder->where($mainTableName.'.status', $chequeStatus);
            })
            ->selectRaw('contracts.code as contract_code, cash_expense_categories.name as category_name, '.$amountColumn.' as paid_amount, '.$currencyColumn.' as currency, '.$dateColumn.' as movement_date');

        foreach ($query->cursor() as $row) {
            $code = (string) $row->contract_code;
            if (! isset($resultsByContractCode[$code])) {
                continue;
            }
            $weekKey = CashFlowWeekBucketer::resolveWeekKey((string) $row->movement_date, $periodsByWeekKey);
            if ($weekKey === null) {
                continue;
            }
            $exchangeRate = ForeignExchangeRate::getExchangeRateForCurrencyAndClosestDate(
                (string) $row->currency,
                $mainFunctionalCurrency,
                (string) $row->movement_date,
                $companyId,
                $foreignExchangeRates,
            );
            $amount = (float) $row->paid_amount * (float) $exchangeRate;
            $categoryName = (string) $row->category_name;
            $result = &$resultsByContractCode[$code];
            $result['cash_expenses'][$categoryName]['total'][$weekKey] = ($result['cash_expenses'][$categoryName]['total'][$weekKey] ?? 0) + $amount;
        }
    }

    private static function moneyReceivedTypeLabel(string $moneyType, ?string $chequeStatus): string
    {
        if ($chequeStatus === Cheque::UNDER_COLLECTION) {
            return __('Cheques Under Collection');
        }

        return [
            MoneyReceived::INCOMING_TRANSFER => __('Incoming Transfers'),
            MoneyReceived::CHEQUE => $chequeStatus === Cheque::IN_SAFE ? __('Cheques In Safe') : __('Checks Collected'),
            MoneyReceived::CASH_IN_BANK => __('Bank Deposits'),
            MoneyReceived::CASH_IN_SAFE => __('Cash Collections'),
        ][$moneyType] ?? $moneyType;
    }

    private static function qualifiedMoneyReceivedDateColumn(string $dateColumnName, bool $usesChequeJoin): string
    {
        if ($usesChequeJoin && in_array($dateColumnName, ['expected_collection_date', 'actual_collection_date', 'due_date'], true)) {
            return 'cheques.'.$dateColumnName;
        }

        return 'money_received.'.$dateColumnName;
    }

    private static function settlementAmountInReceivingCurrencySql(): string
    {
        return 'CASE
            WHEN money_received.currency IS NULL
                OR money_received.currency = money_received.receiving_currency
            THEN settlements.settlement_amount
            ELSE settlements.settlement_amount * money_received.exchange_rate
        END';
    }

    private static function qualifiedMoneyPaymentDateColumn(string $moneyType, string $dateFieldName, bool $usesPayableChequeJoin): string
    {
        if ($moneyType === MoneyPayment::PAYABLE_CHEQUE && $usesPayableChequeJoin) {
            return 'payable_cheques.'.$dateFieldName;
        }

        return 'money_payments.'.$dateFieldName;
    }

    private static function qualifiedCashExpenseDateColumn(string $moneyType, string $dateFieldName): string
    {
        if ($moneyType === CashExpense::PAYABLE_CHEQUE) {
            return 'payable_cheques.'.$dateFieldName;
        }

        return 'cash_expenses.'.$dateFieldName;
    }
}
