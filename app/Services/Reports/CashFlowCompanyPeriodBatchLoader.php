<?php

namespace App\Services\Reports;

use App\Models\CashExpense;
use App\Models\Cheque;
use App\Models\ForeignExchangeRate;
use App\Models\LetterOfCreditIssuance;
use App\Models\LetterOfGuaranteeIssuance;
use App\Enums\LcTypes;
use App\Enums\LgTypes;
use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use App\Models\PayableCheque;
use App\Models\TimeOfDeposit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Loads company-level cash-flow movements for the full report period in one query per category,
 * then buckets amounts into week columns in PHP (replaces per-week queries in CashFlowReportController::result).
 */
final class CashFlowCompanyPeriodBatchLoader
{
   
    public static function apply(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
        array &$letterOfGuaranteeModelData = [],
        ?string $reportCurrency = null,
        array &$incomingTransferModelData = [],
        array &$crossCurrencyNotes = [],
    ): void {
        // The currency tab the user picked. Falls back to the main
        // functional currency (old always-convert behaviour) if the
        // caller doesn't pass one, so nothing breaks for callers that
        // haven't been updated yet.
        $reportCurrency = $reportCurrency ?? $mainFunctionalCurrency;
        self::applyMoneyReceivedMovements($result, $foreignExchangeRates, $mainFunctionalCurrency, $reportCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey, $incomingTransferModelData);
        self::applyCrossCurrencyCollectionNotes($crossCurrencyNotes, $companyId, $periodStart, $periodEnd, $periodsByWeekKey, $reportCurrency, $mainFunctionalCurrency);
        self::applyMoneyPaymentMovements($result, $foreignExchangeRates, $mainFunctionalCurrency, $reportCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey);
        self::applyTimeOfDepositMovements($result, $foreignExchangeRates, $mainFunctionalCurrency, $reportCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey);
        self::applyLetterOfGuaranteeMovements($result, $letterOfGuaranteeModelData, $foreignExchangeRates, $mainFunctionalCurrency, $reportCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey);
        self::applyLetterOfCreditMovements($result, $foreignExchangeRates, $mainFunctionalCurrency, $reportCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey);
        self::applyCashExpenseMovements($result, $foreignExchangeRates, $mainFunctionalCurrency, $reportCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey);
    }

    /**
     * Shared helper: amount stays in its own currency (no conversion) when
     * the user is viewing that currency's own tab; gets converted into the
     * main functional currency only when the EGP/main-currency tab is active.
     */
    private static function convertedAmount(float $rawAmount, float $exchangeRate, string $mainFunctionalCurrency, string $reportCurrency): float
    {
        return $reportCurrency === $mainFunctionalCurrency ? $rawAmount * $exchangeRate : $rawAmount;
    }

    private static function applyMoneyReceivedMovements(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        string $reportCurrency,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
        array &$incomingTransferModelData,
    ): void {
        $totalCashInFlowKey = __('Total Cash Inflow');

        self::applyChequeSettlements($result, $foreignExchangeRates, $mainFunctionalCurrency, $reportCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey, Cheque::UNDER_COLLECTION, __('Cheques Under Collection'), $totalCashInFlowKey);
        self::applyChequeSettlements($result, $foreignExchangeRates, $mainFunctionalCurrency, $reportCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey, Cheque::COLLECTED, __('Checks Collected'), $totalCashInFlowKey);
        self::applyMoneyReceivedByType($result, $foreignExchangeRates, $mainFunctionalCurrency, $reportCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey, MoneyReceived::INCOMING_TRANSFER, __('Incoming Transfers'), $totalCashInFlowKey, $incomingTransferModelData);
        self::applyMoneyReceivedByType($result, $foreignExchangeRates, $mainFunctionalCurrency, $reportCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey, MoneyReceived::CASH_IN_BANK, __('Bank Deposits'), $totalCashInFlowKey);
        self::applyMoneyReceivedByType($result, $foreignExchangeRates, $mainFunctionalCurrency, $reportCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey, MoneyReceived::CASH_IN_SAFE, __('Cash Collections'), $totalCashInFlowKey);
        self::applyChequeInSafe($result, $foreignExchangeRates, $mainFunctionalCurrency, $reportCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey, $totalCashInFlowKey);
    }

    private static function applyChequeSettlements(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        string $reportCurrency,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
        string $chequeStatus,
        string $resultKey,
        string $totalCashInFlowKey,
    ): void {
        if ($chequeStatus === Cheque::COLLECTED) {
            self::applyCollectedChequeMovements($result, $foreignExchangeRates, $mainFunctionalCurrency, $reportCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey, $resultKey, $totalCashInFlowKey);

            return;
        }

        $dateColumn = $chequeStatus === Cheque::COLLECTED ? 'cheques.actual_collection_date' : 'cheques.expected_collection_date';
        $settlementAmountExpression = self::settlementAmountInReceivingCurrencySql();

        $query = DB::table('money_received')
            ->join('cheques', 'cheques.money_received_id', '=', 'money_received.id')
            ->join('settlements', 'money_received.id', '=', 'settlements.money_received_id')
            ->join('customer_invoices', 'customer_invoices.id', '=', 'settlements.invoice_id')
            ->where('money_received.company_id', $companyId)
            ->where('cheques.status', $chequeStatus)
            ->where('money_received.type', MoneyReceived::CHEQUE)
            ->where(function ($q) {
                $q->whereNull('money_received.down_payment_type')
                    ->orWhere('money_received.down_payment_type', '=', 'general');
            })
            ->when($reportCurrency !== $mainFunctionalCurrency, function ($q) use ($reportCurrency) {
                $q->where('money_received.receiving_currency', $reportCurrency);
            })
            ->whereBetween($dateColumn, [$periodStart, $periodEnd])
            ->selectRaw('customer_invoices.contract_code as contract_code, '.$settlementAmountExpression.' as received_amount, money_received.receiving_currency, '.$dateColumn.' as movement_date, customer_invoices.invoice_number');

        foreach ($query->cursor() as $row) {
            self::accumulateMoneyReceivedRow($result, $foreignExchangeRates, $mainFunctionalCurrency, $reportCurrency, $companyId, $periodsByWeekKey, $resultKey, $totalCashInFlowKey, $row, true);
        }
    }

    private static function applyCollectedChequeMovements(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        string $reportCurrency,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
        string $resultKey,
        string $totalCashInFlowKey,
    ): void {
        $settlementAmountExpression = self::settlementAmountInReceivingCurrencySql();
        $query = DB::table('money_received')
            ->join('cheques', 'cheques.money_received_id', '=', 'money_received.id')
            ->join('settlements', 'money_received.id', '=', 'settlements.money_received_id')
            ->join('partners', 'partners.id', '=', 'money_received.partner_id')
            ->where('money_received.company_id', $companyId)
            ->where('cheques.status', Cheque::COLLECTED)
            ->where('money_received.type', MoneyReceived::CHEQUE)
            ->where(function ($q) {
                $q->whereNull('money_received.down_payment_type')
                    ->orWhere('money_received.down_payment_type', '=', 'general');
            })
            ->when($reportCurrency !== $mainFunctionalCurrency, function ($q) use ($reportCurrency) {
                $q->where('money_received.receiving_currency', $reportCurrency);
            })
            ->whereBetween('cheques.actual_collection_date', [$periodStart, $periodEnd])
            ->groupByRaw('money_received.id, money_received.receiving_currency, cheques.actual_collection_date, cheques.cheque_number, partners.name')
            ->selectRaw('money_received.id as money_received_id, money_received.receiving_currency, cheques.actual_collection_date as movement_date, cheques.cheque_number, '.partner_display_name_sql('partners', 'partner_name').', sum('.$settlementAmountExpression.') as received_amount');

        foreach ($query->cursor() as $row) {
            self::accumulateCollectedChequeRow($result, $foreignExchangeRates, $mainFunctionalCurrency, $reportCurrency, $companyId, $periodsByWeekKey, $resultKey, $totalCashInFlowKey, $row);
        }
    }

    private static function applyChequeInSafe(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        string $reportCurrency,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
        string $totalCashInFlowKey,
    ): void {
        $settlementAmountExpression = self::settlementAmountInReceivingCurrencySql();
        $query = DB::table('money_received')
            ->join('cheques', 'cheques.money_received_id', '=', 'money_received.id')
            ->join('settlements', 'money_received.id', '=', 'settlements.money_received_id')
            ->join('customer_invoices', 'customer_invoices.id', '=', 'settlements.invoice_id')
            ->where('money_received.company_id', $companyId)
            ->where('cheques.status', Cheque::IN_SAFE)
            ->where('money_received.type', MoneyReceived::CHEQUE)
            ->where(function ($q) {
                $q->whereNull('money_received.down_payment_type')
                    ->orWhere('money_received.down_payment_type', '=', 'general');
            })
            ->when($reportCurrency !== $mainFunctionalCurrency, function ($q) use ($reportCurrency) {
                $q->where('money_received.receiving_currency', $reportCurrency);
            })
            ->whereBetween('cheques.due_date', [$periodStart, $periodEnd])
            ->selectRaw('customer_invoices.contract_code as contract_code, '.$settlementAmountExpression.' as received_amount, money_received.receiving_currency, cheques.due_date as movement_date, customer_invoices.invoice_number');

        foreach ($query->cursor() as $row) {
            self::accumulateMoneyReceivedRow($result, $foreignExchangeRates, $mainFunctionalCurrency, $reportCurrency, $companyId, $periodsByWeekKey, __('Cheques In Safe'), $totalCashInFlowKey, $row, true);
        }
    }

    private static function applyMoneyReceivedByType(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        string $reportCurrency,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
        string $moneyType,
        string $resultKey,
        string $totalCashInFlowKey,
        array &$incomingTransferModelData = [],
    ): void {
        if ($moneyType === MoneyReceived::INCOMING_TRANSFER) {
            self::applyIncomingTransferMovements($result, $foreignExchangeRates, $mainFunctionalCurrency, $reportCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey, $resultKey, $totalCashInFlowKey, $incomingTransferModelData);

            return;
        }

        $query = DB::table('money_received')
            ->join('partners', 'partners.id', '=', 'money_received.partner_id')
            ->where('money_received.company_id', $companyId)
            ->where('money_received.type', $moneyType)
            ->when($reportCurrency !== $mainFunctionalCurrency, function ($q) use ($reportCurrency) {
                $q->where('money_received.receiving_currency', $reportCurrency);
            })
            ->whereBetween('money_received.receiving_date', [$periodStart, $periodEnd])
            ->selectRaw('money_received.received_amount, money_received.receiving_currency, money_received.receiving_date as movement_date, '.partner_display_name_sql('partners', 'partner_name'));

        foreach ($query->cursor() as $row) {
            self::accumulateMoneyReceivedRow($result, $foreignExchangeRates, $mainFunctionalCurrency, $reportCurrency, $companyId, $periodsByWeekKey, $resultKey, $totalCashInFlowKey, $row, false);
        }
    }

    private static function applyIncomingTransferMovements(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        string $reportCurrency,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
        string $resultKey,
        string $totalCashInFlowKey,
        array &$incomingTransferModelData,
    ): void {
        $query = DB::table('money_received')
            ->join('partners', 'partners.id', '=', 'money_received.partner_id')
            ->leftJoin('incoming_transfers', 'incoming_transfers.money_received_id', '=', 'money_received.id')
            ->leftJoin('financial_institutions', 'financial_institutions.id', '=', 'incoming_transfers.receiving_bank_id')
            ->leftJoin('banks', 'banks.id', '=', 'financial_institutions.bank_id')
            ->where('money_received.company_id', $companyId)
            ->where('money_received.type', MoneyReceived::INCOMING_TRANSFER)
            ->when($reportCurrency !== $mainFunctionalCurrency, function ($q) use ($reportCurrency) {
                $q->where('money_received.receiving_currency', $reportCurrency);
            })
            ->whereBetween('money_received.receiving_date', [$periodStart, $periodEnd])
            ->selectRaw('money_received.id as money_received_id, money_received.received_amount, money_received.receiving_currency, money_received.receiving_date as movement_date, '.partner_display_name_sql('partners', 'partner_name').', '.financial_institution_display_name_sql());

        foreach ($query->cursor() as $row) {
            self::accumulateIncomingTransferRow($result, $foreignExchangeRates, $mainFunctionalCurrency, $reportCurrency, $companyId, $periodsByWeekKey, $resultKey, $totalCashInFlowKey, $row, $incomingTransferModelData);
        }
    }

    private static function accumulateMoneyReceivedRow(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        string $reportCurrency,
        int $companyId,
        array $periodsByWeekKey,
        string $typeKey,
        string $totalCashInFlowKey,
        object $row,
        bool $useInvoiceDetail,
    ): void {
        $weekKey = CashFlowWeekBucketer::resolveWeekKey((string) $row->movement_date, $periodsByWeekKey);
        if ($weekKey === null) {
            return;
        }

        $exchangeRate = ForeignExchangeRate::getExchangeRateAt(
            (string) $row->receiving_currency,
            $mainFunctionalCurrency,
            (string) $row->movement_date,
            $companyId,
            $foreignExchangeRates,
        );

        $amount = self::convertedAmount((float) $row->received_amount, $exchangeRate, $mainFunctionalCurrency, $reportCurrency);
        $label = $useInvoiceDetail && isset($row->invoice_number)
            ? (string) $row->invoice_number
            : (string) ($row->partner_name ?? '');

        if (! isset($result['customers'][$typeKey][$label])) {
            $result['customers'][$typeKey][$label] = ['weeks' => [], 'total' => []];
        }
        if (! isset($result['customers'][$typeKey][$label]['weeks'][$weekKey])) {
            $result['customers'][$typeKey][$label]['weeks'][$weekKey] = 0;
        }
        if (! isset($result['customers'][$typeKey]['total'][$weekKey])) {
            $result['customers'][$typeKey]['total'][$weekKey] = 0;
        }
        if (! isset($result['customers'][$totalCashInFlowKey]['total'][$weekKey])) {
            $result['customers'][$totalCashInFlowKey]['total'][$weekKey] = 0;
        }

        $result['customers'][$typeKey][$label]['weeks'][$weekKey] += $amount;
        $result['customers'][$typeKey]['total'][$weekKey] += $amount;
        $result['customers'][$totalCashInFlowKey]['total'][$weekKey] += $amount;
    }

    private static function accumulateCollectedChequeRow(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        string $reportCurrency,
        int $companyId,
        array $periodsByWeekKey,
        string $typeKey,
        string $totalCashInFlowKey,
        object $row,
    ): void {
        $weekKey = CashFlowWeekBucketer::resolveWeekKey((string) $row->movement_date, $periodsByWeekKey);
        if ($weekKey === null) {
            return;
        }

        $exchangeRate = ForeignExchangeRate::getExchangeRateAt(
            (string) $row->receiving_currency,
            $mainFunctionalCurrency,
            (string) $row->movement_date,
            $companyId,
            $foreignExchangeRates,
        );

        $amount = self::convertedAmount((float) $row->received_amount, $exchangeRate, $mainFunctionalCurrency, $reportCurrency);
        $subRowKey = 'money_received_'.$row->money_received_id;
        $label = (string) ($row->partner_name ?: __('Unknown Customer'));

        if (! isset($result['customers'][$typeKey][$subRowKey])) {
            $result['customers'][$typeKey][$subRowKey] = [
                'weeks' => [],
                'total' => [],
                'label' => $label,
                'checks_collected_info' => [
                    'customer_name' => $label,
                    'cheque_number' => (string) ($row->cheque_number ?? ''),
                    'amount' => $amount,
                    'movement_date' => (string) $row->movement_date,
                ],
            ];
        }
        if (! isset($result['customers'][$typeKey][$subRowKey]['weeks'][$weekKey])) {
            $result['customers'][$typeKey][$subRowKey]['weeks'][$weekKey] = 0;
        }
        if (! isset($result['customers'][$typeKey][$subRowKey]['total'][$weekKey])) {
            $result['customers'][$typeKey][$subRowKey]['total'][$weekKey] = 0;
        }
        if (! isset($result['customers'][$typeKey]['total'][$weekKey])) {
            $result['customers'][$typeKey]['total'][$weekKey] = 0;
        }
        if (! isset($result['customers'][$totalCashInFlowKey]['total'][$weekKey])) {
            $result['customers'][$totalCashInFlowKey]['total'][$weekKey] = 0;
        }

        $result['customers'][$typeKey][$subRowKey]['weeks'][$weekKey] += $amount;
        $result['customers'][$typeKey][$subRowKey]['total'][$weekKey] += $amount;
        $result['customers'][$typeKey]['total'][$weekKey] += $amount;
        $result['customers'][$totalCashInFlowKey]['total'][$weekKey] += $amount;
    }

    private static function accumulateIncomingTransferRow(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        string $reportCurrency,
        int $companyId,
        array $periodsByWeekKey,
        string $typeKey,
        string $totalCashInFlowKey,
        object $row,
        array &$incomingTransferModelData,
    ): void {
        $weekKey = CashFlowWeekBucketer::resolveWeekKey((string) $row->movement_date, $periodsByWeekKey);
        if ($weekKey === null) {
            return;
        }

        $exchangeRate = ForeignExchangeRate::getExchangeRateAt(
            (string) $row->receiving_currency,
            $mainFunctionalCurrency,
            (string) $row->movement_date,
            $companyId,
            $foreignExchangeRates,
        );

        $amount = self::convertedAmount((float) $row->received_amount, $exchangeRate, $mainFunctionalCurrency, $reportCurrency);
        // Grouped by source name (not by individual transaction) so the same
        // sender collapses into one row instead of one row per transfer; the
        // per-transaction detail (bank, date, amount) is kept separately
        // below and shown via a per-cell "ℹ️" breakdown, the same pattern
        // already used for Cancelled LGs Cash Cover.
        $label = (string) ($row->partner_name ?: __('Unknown Customer'));

        if (! isset($result['customers'][$typeKey][$label])) {
            $result['customers'][$typeKey][$label] = ['weeks' => [], 'total' => []];
        }
        if (! isset($result['customers'][$typeKey][$label]['weeks'][$weekKey])) {
            $result['customers'][$typeKey][$label]['weeks'][$weekKey] = 0;
        }
        if (! isset($result['customers'][$typeKey]['total'][$weekKey])) {
            $result['customers'][$typeKey]['total'][$weekKey] = 0;
        }
        if (! isset($result['customers'][$totalCashInFlowKey]['total'][$weekKey])) {
            $result['customers'][$totalCashInFlowKey]['total'][$weekKey] = 0;
        }

        $result['customers'][$typeKey][$label]['weeks'][$weekKey] += $amount;
        $result['customers'][$typeKey][$label]['total'][$weekKey] = ($result['customers'][$typeKey][$label]['total'][$weekKey] ?? 0) + $amount;
        $result['customers'][$typeKey]['total'][$weekKey] += $amount;
        $result['customers'][$totalCashInFlowKey]['total'][$weekKey] += $amount;

        $incomingTransferModelData[$label]['weeks'][$weekKey][] = [
            'amount' => $amount,
            'bank_name' => (string) ($row->bank_name ?? __('N/A')),
            'movement_date' => (string) $row->movement_date,
        ];
    }

    private static function applyMoneyPaymentMovements(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        string $reportCurrency,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
    ): void {
        self::applyMoneyPaymentByType($result, $foreignExchangeRates, $mainFunctionalCurrency, $reportCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey, MoneyPayment::OUTGOING_TRANSFER, null);
        self::applyMoneyPaymentByType($result, $foreignExchangeRates, $mainFunctionalCurrency, $reportCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey, MoneyPayment::CASH_PAYMENT, null);
        self::applyMoneyPaymentByType($result, $foreignExchangeRates, $mainFunctionalCurrency, $reportCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey, MoneyPayment::PAYABLE_CHEQUE, PayableCheque::PAID);
        self::applyMoneyPaymentByType($result, $foreignExchangeRates, $mainFunctionalCurrency, $reportCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey, MoneyPayment::PAYABLE_CHEQUE, PayableCheque::PENDING);
    }

    private static function applyMoneyPaymentByType(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        string $reportCurrency,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
        string $moneyType,
        ?string $chequeStatus,
    ): void {
        $typeLabel = match ($moneyType) {
            MoneyPayment::OUTGOING_TRANSFER => __('Outgoing Transfers'),
            MoneyPayment::CASH_PAYMENT => __('Cash Payments'),
            MoneyPayment::PAYABLE_CHEQUE => $chequeStatus === PayableCheque::PAID
                ? __('Paid Payable Cheques')
                : __('Under Payment Payable Cheques'),
            default => $moneyType,
        };
        $query = DB::table('money_payments')
            ->join('partners', 'partners.id', '=', 'money_payments.partner_id')
            ->where('money_payments.company_id', $companyId)
            ->where('money_payments.type', $moneyType)
            ->when($reportCurrency !== $mainFunctionalCurrency, function ($q) use ($reportCurrency) {
                $q->where('money_payments.payment_currency', $reportCurrency);
            });

        if ($chequeStatus !== null) {
            $query->join('payable_cheques', 'payable_cheques.money_payment_id', '=', 'money_payments.id')
                ->where('payable_cheques.status', $chequeStatus);
            $dateField = $chequeStatus === PayableCheque::PAID ? 'payable_cheques.actual_payment_date' : 'payable_cheques.due_date';
        } else {
            $dateField = 'money_payments.delivery_date';
        }

        $query->whereBetween($dateField, [$periodStart, $periodEnd])
            ->selectRaw('money_payments.paid_amount, money_payments.payment_currency, '.$dateField.' as movement_date, '.partner_display_name_sql('partners', 'partner_name'));

        foreach ($query->cursor() as $row) {
            $weekKey = CashFlowWeekBucketer::resolveWeekKey((string) $row->movement_date, $periodsByWeekKey);
            if ($weekKey === null) {
                continue;
            }

            $exchangeRate = ForeignExchangeRate::getExchangeRateAt(
                (string) $row->payment_currency,
                $mainFunctionalCurrency,
                (string) $row->movement_date,
                $companyId,
                $foreignExchangeRates,
            );

            $amount = self::convertedAmount((float) $row->paid_amount, $exchangeRate, $mainFunctionalCurrency, $reportCurrency);
            $supplierName = (string) $row->partner_name;

            if (! isset($result['suppliers'][$typeLabel][$supplierName])) {
                $result['suppliers'][$typeLabel][$supplierName] = ['weeks' => [], 'total' => []];
            }
            if (! isset($result['suppliers'][$typeLabel][$supplierName]['weeks'][$weekKey])) {
                $result['suppliers'][$typeLabel][$supplierName]['weeks'][$weekKey] = 0;
            }
            if (! isset($result['suppliers'][$typeLabel][$supplierName]['total'][$weekKey])) {
                $result['suppliers'][$typeLabel][$supplierName]['total'][$weekKey] = 0;
            }

            $result['suppliers'][$typeLabel][$supplierName]['weeks'][$weekKey] += $amount;
            $result['suppliers'][$typeLabel][$supplierName]['total'][$weekKey] += $amount;

            if (! isset($result['suppliers'][$typeLabel]['total'][$weekKey])) {
                $result['suppliers'][$typeLabel]['total'][$weekKey] = 0;
            }
            $result['suppliers'][$typeLabel]['total'][$weekKey] += $amount;
        }
    }

    private static function applyTimeOfDepositMovements(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        string $reportCurrency,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
    ): void {
        $tdsTypes = [
            TimeOfDeposit::MATURED => __('Matured'),
            TimeOfDeposit::BROKEN => __('Broken'),
            TimeOfDeposit::RUNNING => __('Running'),
        ];

        $mainType = 'customers';
        $subType = __('Time Of Deposits');
        $totalCashInFlowKey = __('Total Cash Inflow');

        $rows = DB::table('time_of_deposits')
            ->where('time_of_deposits.company_id', $companyId)
            ->when($reportCurrency !== $mainFunctionalCurrency, function ($q) use ($reportCurrency) {
                $q->where('time_of_deposits.currency', $reportCurrency);
            })
            ->whereRaw("(CASE 
                    WHEN status = 'broken' THEN break_date 
                    WHEN status = 'matured' THEN deposit_date 
                    ELSE end_date 
                END) BETWEEN ? AND ?", [$periodStart, $periodEnd])
            ->groupByRaw('status, currency, end_date')
            ->selectRaw("
                status,
                currency,
                CASE 
                    WHEN status = 'broken' THEN break_date 
                    WHEN status = 'matured' THEN deposit_date 
                    ELSE end_date 
                END AS date,
                SUM(CASE 
                    WHEN status = 'matured' THEN amount + actual_interest_amount
                    WHEN status = 'broken' THEN amount + break_interest_amount
                    WHEN status = 'running' THEN amount + interest_amount
                    ELSE 0 
                END) AS total_amount
            ")
            ->get();

        foreach ($rows as $row) {
            $weekKey = CashFlowWeekBucketer::resolveWeekKey((string) $row->date, $periodsByWeekKey);
            if ($weekKey === null) {
                continue;
            }

            $currentStatus = $tdsTypes[$row->status] ?? $row->status;
            $exchangeRate = ForeignExchangeRate::getExchangeRateAt(
                (string) $row->currency,
                $mainFunctionalCurrency,
                (string) $row->date,
                $companyId,
                $foreignExchangeRates,
            );

            $currentPaidAmount = self::convertedAmount((float) $row->total_amount, $exchangeRate, $mainFunctionalCurrency, $reportCurrency);

            if (! isset($result[$mainType][$subType][$currentStatus])) {
                $result[$mainType][$subType][$currentStatus] = ['weeks' => [], 'total' => []];
            }
            if (! isset($result[$mainType][$subType][$currentStatus]['weeks'][$weekKey])) {
                $result[$mainType][$subType][$currentStatus]['weeks'][$weekKey] = 0;
            }
            if (! isset($result[$mainType][$subType][$currentStatus]['total'][$weekKey])) {
                $result[$mainType][$subType][$currentStatus]['total'][$weekKey] = 0;
            }
            if (! isset($result['customers'][$totalCashInFlowKey]['total'][$weekKey])) {
                $result['customers'][$totalCashInFlowKey]['total'][$weekKey] = 0;
            }

            $result[$mainType][$subType][$currentStatus]['weeks'][$weekKey] += $currentPaidAmount;
            $result[$mainType][$subType][$currentStatus]['total'][$weekKey] += $currentPaidAmount;
            $result['customers'][$totalCashInFlowKey]['total'][$weekKey] += $currentPaidAmount;
        }
    }

    private static function applyLetterOfGuaranteeMovements(
        array &$result,
        array &$letterOfGuaranteeModelData,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        string $reportCurrency,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
    ): void {
        $lgsTypes = LgTypes::getAll();
        $mainType = 'cash_expenses';
        $subTypeFees = __('LGs Commission & Fees');
        $subTypeCover = __('Cancelled LGs Cash Cover');
        $totalCashInFlowKey = __('Total Cash Inflow');

        $feeRows = DB::table('current_account_bank_statements')
            ->where('current_account_bank_statements.company_id', $companyId)
            ->join('financial_institution_accounts', 'financial_institution_accounts.id', '=', 'current_account_bank_statements.financial_institution_account_id')
            ->join('letter_of_guarantee_issuances', 'letter_of_guarantee_issuances.id', '=', 'current_account_bank_statements.letter_of_guarantee_issuance_id')
            ->whereBetween('current_account_bank_statements.date', [$periodStart, $periodEnd])
            ->where('letter_of_guarantee_issuance_id', '>', 0)
            ->where(function ($q) {
                $q->where('is_renewal_fees', 1)->orWhere('is_commission_fees', 1)->orWhere('is_issuance_fees', 1);
            })
            ->when($reportCurrency !== $mainFunctionalCurrency, function ($q) use ($reportCurrency) {
                $q->where('financial_institution_accounts.currency', $reportCurrency);
            })
            ->groupByRaw('letter_of_guarantee_issuances.lg_type, financial_institution_accounts.currency, current_account_bank_statements.date')
            ->selectRaw('letter_of_guarantee_issuances.lg_type as lg_type, sum(credit) as paid_amount, financial_institution_accounts.currency as currency, current_account_bank_statements.date as movement_date')
            ->get();

        foreach ($feeRows as $row) {
            $weekKey = CashFlowWeekBucketer::resolveWeekKey((string) $row->movement_date, $periodsByWeekKey);
            if ($weekKey === null) {
                continue;
            }

            $lgType = $lgsTypes[$row->lg_type] ?? $row->lg_type;
            $exchangeRate = ForeignExchangeRate::getExchangeRateForCurrencyAndClosestDate(
                (string) $row->currency,
                $mainFunctionalCurrency,
                (string) $row->movement_date,
                $companyId,
                $foreignExchangeRates,
            );
            $amount = self::convertedAmount((float) $row->paid_amount, $exchangeRate, $mainFunctionalCurrency, $reportCurrency);

            if (! isset($result[$mainType][$subTypeFees][$lgType])) {
                $result[$mainType][$subTypeFees][$lgType] = ['weeks' => [], 'total' => []];
            }
            if (! isset($result[$mainType][$subTypeFees][$lgType]['weeks'][$weekKey])) {
                $result[$mainType][$subTypeFees][$lgType]['weeks'][$weekKey] = 0;
            }
            if (! isset($result[$mainType][$subTypeFees][$lgType]['total'][$weekKey])) {
                $result[$mainType][$subTypeFees][$lgType]['total'][$weekKey] = 0;
            }
            if (! isset($result[$mainType][$subTypeFees]['total'][$weekKey])) {
                $result[$mainType][$subTypeFees]['total'][$weekKey] = 0;
            }

            $result[$mainType][$subTypeFees][$lgType]['weeks'][$weekKey] += $amount;
            $result[$mainType][$subTypeFees][$lgType]['total'][$weekKey] += $amount;
            $result[$mainType][$subTypeFees]['total'][$weekKey] += $amount;
        }

        $inflowMainType = 'customers';
        // Cancelled LGs Cash Cover = cash cover being RELEASED BACK to the
        // company on cancellation. handleLetterOfGuaranteeCashCoverStatement()
        // writes cancellation rows as credit=amount, debit=0, type='for-cancellation'
        // (see LetterOfGuaranteeIssuanceController::cancel...()). The row used
        // to read `debit`, which is actually the ISSUANCE amount (see below),
        // so it was showing the wrong figure and — since every LG has both an
        // issuance row (debit>0) and a cancellation row (credit>0) with the
        // same effective date — a confusing zero-value duplicate per LG in
        // the breakdown popup.
        $coverQuery = DB::table('letter_of_guarantee_cash_cover_statements')
            ->where('letter_of_guarantee_cash_cover_statements.company_id', $companyId)
            ->where('letter_of_guarantee_cash_cover_statements.type', LetterOfGuaranteeIssuance::FOR_CANCELLATION)
            ->join('letter_of_guarantee_issuances', 'letter_of_guarantee_issuances.id', '=', 'letter_of_guarantee_cash_cover_statements.letter_of_guarantee_issuance_id')
            ->join('partners', 'partners.id', '=', 'letter_of_guarantee_issuances.partner_id');
        $coverQuery = LgCashCoverEffectiveDate::joinTo($coverQuery);
        $effectiveDateSql = LgCashCoverEffectiveDate::sql();
        $coverRows = $coverQuery
            ->whereBetween(DB::raw($effectiveDateSql), [$periodStart, $periodEnd])
            ->where('letter_of_guarantee_cash_cover_statements.letter_of_guarantee_issuance_id', '>', 0)
            ->when($reportCurrency !== $mainFunctionalCurrency, function ($q) use ($reportCurrency) {
                $q->where('letter_of_guarantee_cash_cover_statements.currency', $reportCurrency);
            })
            ->selectRaw('letter_of_guarantee_issuances.lg_type as lg_type, letter_of_guarantee_cash_cover_statements.credit as total_amount, letter_of_guarantee_cash_cover_statements.currency as currency, '.$effectiveDateSql.' as movement_date, partners.name as partner_name, letter_of_guarantee_issuances.lg_code as lg_code')
            ->get();

        foreach ($coverRows as $row) {
            $weekKey = CashFlowWeekBucketer::resolveWeekKey((string) $row->movement_date, $periodsByWeekKey);
            if ($weekKey === null) {
                continue;
            }

            $lgType = $lgsTypes[$row->lg_type] ?? $row->lg_type;
            $exchangeRate = ForeignExchangeRate::getExchangeRateAt(
                (string) $row->currency,
                $mainFunctionalCurrency,
                (string) $row->movement_date,
                $companyId,
                $foreignExchangeRates,
            );
            $amount = self::convertedAmount((float) $row->total_amount, $exchangeRate, $mainFunctionalCurrency, $reportCurrency);

            if (! isset($result[$inflowMainType][$subTypeCover][$lgType])) {
                $result[$inflowMainType][$subTypeCover][$lgType] = ['weeks' => [], 'total' => []];
            }
            if (! isset($result[$inflowMainType][$subTypeCover][$lgType]['weeks'][$weekKey])) {
                $result[$inflowMainType][$subTypeCover][$lgType]['weeks'][$weekKey] = 0;
            }
            if (! isset($result[$inflowMainType][$subTypeCover][$lgType]['total'][$weekKey])) {
                $result[$inflowMainType][$subTypeCover][$lgType]['total'][$weekKey] = 0;
            }
            if (! isset($result[$inflowMainType][$subTypeCover]['total'][$weekKey])) {
                $result[$inflowMainType][$subTypeCover]['total'][$weekKey] = 0;
            }

            $result[$inflowMainType][$subTypeCover][$lgType]['weeks'][$weekKey] += $amount;
            $result[$inflowMainType][$subTypeCover][$lgType]['total'][$weekKey] += $amount;
            $result[$inflowMainType][$subTypeCover]['total'][$weekKey] += $amount;
            $result['customers'][$totalCashInFlowKey]['total'][$weekKey] = ($result['customers'][$totalCashInFlowKey]['total'][$weekKey] ?? 0) + $amount;

            // ⚠️ Bug fix: this is the piece that was entirely missing on the
            // Company Cash Flow path. The old query grouped straight down to
            // (lg_type, currency, date) in SQL, so no individual LG's name/
            // code ever survived to be shown in the "ℹ️ Breakdown" modal —
            // every popup was empty by construction, not a display bug.
            // Same capture shape as the already-working Contract Cash Flow
            // path (CashFlowContractDetailPeriodBatchLoader::applyLetterOfGuaranteeMovements()).
            // Namespaced by row name ($subTypeCover) — not just lgType — so
            // this doesn't collide with the Issued LG Cash Cover row below,
            // which uses the same lgType values but is a different movement.
            $letterOfGuaranteeModelData[$subTypeCover][$lgType]['weeks'][$weekKey][] = [
                'amount' => $amount,
                'lg_code' => $row->lg_code,
                'name' => $row->partner_name,
            ];
        }

        // Issued LG Cash Cover = cash cover being FUNDED/LOCKED AWAY at
        // issuance — an outflow, the mirror image of the cancellation row
        // above. Issuance rows are written as debit=amount, credit=0,
        // type='debit-lg-amount' (see LetterOfGuaranteeIssuanceController).
        $subTypeIssued = __('Issued LG Cash Cover');
        $issuedCoverQuery = DB::table('letter_of_guarantee_cash_cover_statements')
            ->where('letter_of_guarantee_cash_cover_statements.company_id', $companyId)
            ->where('letter_of_guarantee_cash_cover_statements.type', 'debit-lg-amount')
            ->join('letter_of_guarantee_issuances', 'letter_of_guarantee_issuances.id', '=', 'letter_of_guarantee_cash_cover_statements.letter_of_guarantee_issuance_id')
            ->join('partners', 'partners.id', '=', 'letter_of_guarantee_issuances.partner_id')
            ->where('letter_of_guarantee_cash_cover_statements.letter_of_guarantee_issuance_id', '>', 0)
            ->whereBetween('letter_of_guarantee_cash_cover_statements.date', [$periodStart, $periodEnd])
            ->when($reportCurrency !== $mainFunctionalCurrency, function ($q) use ($reportCurrency) {
                $q->where('letter_of_guarantee_cash_cover_statements.currency', $reportCurrency);
            })
            ->selectRaw('letter_of_guarantee_issuances.lg_type as lg_type, letter_of_guarantee_cash_cover_statements.debit as total_amount, letter_of_guarantee_cash_cover_statements.currency as currency, letter_of_guarantee_cash_cover_statements.date as movement_date, partners.name as partner_name, letter_of_guarantee_issuances.lg_code as lg_code')
            ->get();

        foreach ($issuedCoverQuery as $row) {
            $weekKey = CashFlowWeekBucketer::resolveWeekKey((string) $row->movement_date, $periodsByWeekKey);
            if ($weekKey === null) {
                continue;
            }

            $lgType = $lgsTypes[$row->lg_type] ?? $row->lg_type;
            $exchangeRate = ForeignExchangeRate::getExchangeRateAt(
                (string) $row->currency,
                $mainFunctionalCurrency,
                (string) $row->movement_date,
                $companyId,
                $foreignExchangeRates,
            );
            $amount = self::convertedAmount((float) $row->total_amount, $exchangeRate, $mainFunctionalCurrency, $reportCurrency);

            if (! isset($result[$mainType][$subTypeIssued][$lgType])) {
                $result[$mainType][$subTypeIssued][$lgType] = ['weeks' => [], 'total' => []];
            }
            if (! isset($result[$mainType][$subTypeIssued][$lgType]['weeks'][$weekKey])) {
                $result[$mainType][$subTypeIssued][$lgType]['weeks'][$weekKey] = 0;
            }
            if (! isset($result[$mainType][$subTypeIssued][$lgType]['total'][$weekKey])) {
                $result[$mainType][$subTypeIssued][$lgType]['total'][$weekKey] = 0;
            }
            if (! isset($result[$mainType][$subTypeIssued]['total'][$weekKey])) {
                $result[$mainType][$subTypeIssued]['total'][$weekKey] = 0;
            }

            $result[$mainType][$subTypeIssued][$lgType]['weeks'][$weekKey] += $amount;
            $result[$mainType][$subTypeIssued][$lgType]['total'][$weekKey] += $amount;
            $result[$mainType][$subTypeIssued]['total'][$weekKey] += $amount;

            $letterOfGuaranteeModelData[$subTypeIssued][$lgType]['weeks'][$weekKey][] = [
                'amount' => $amount,
                'lg_code' => $row->lg_code,
                'name' => $row->partner_name,
            ];
        }
    }

    private static function applyLetterOfCreditMovements(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        string $reportCurrency,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
    ): void {
        $lcsTypes = LcTypes::getAll();
        $mainType = 'cash_expenses';
        $subTypeFees = __('LCs Commission & Fees');
        $subTypeRemaining = __('LCs Remaining Amounts');

        $feeRows = DB::table('current_account_bank_statements')
            ->where('current_account_bank_statements.company_id', $companyId)
            ->join('financial_institution_accounts', 'financial_institution_accounts.id', '=', 'current_account_bank_statements.financial_institution_account_id')
            ->join('letter_of_credit_issuances', 'letter_of_credit_issuances.id', '=', 'current_account_bank_statements.letter_of_credit_issuance_id')
            ->whereBetween('current_account_bank_statements.date', [$periodStart, $periodEnd])
            ->where('letter_of_credit_issuance_id', '>', 0)
            ->where(function ($q) {
                $q->where('is_renewal_fees', 1)->orWhere('is_commission_fees', 1)->orWhere('is_issuance_fees', 1);
            })
            ->when($reportCurrency !== $mainFunctionalCurrency, function ($q) use ($reportCurrency) {
                $q->where('financial_institution_accounts.currency', $reportCurrency);
            })
            ->groupByRaw('letter_of_credit_issuances.lc_type, financial_institution_accounts.currency, current_account_bank_statements.date')
            ->selectRaw('letter_of_credit_issuances.lc_type as lc_type, sum(credit) as paid_amount, financial_institution_accounts.currency as currency, current_account_bank_statements.date as movement_date')
            ->get();

        foreach ($feeRows as $row) {
            $weekKey = CashFlowWeekBucketer::resolveWeekKey((string) $row->movement_date, $periodsByWeekKey);
            if ($weekKey === null) {
                continue;
            }

            $lcType = $lcsTypes[$row->lc_type] ?? $row->lc_type;
            $exchangeRate = ForeignExchangeRate::getExchangeRateForCurrencyAndClosestDate(
                (string) $row->currency,
                $mainFunctionalCurrency,
                (string) $row->movement_date,
                $companyId,
                $foreignExchangeRates,
            );
            $amount = self::convertedAmount((float) $row->paid_amount, $exchangeRate, $mainFunctionalCurrency, $reportCurrency);

            if (! isset($result[$mainType][$subTypeFees][$lcType])) {
                $result[$mainType][$subTypeFees][$lcType] = ['weeks' => [], 'total' => []];
            }
            if (! isset($result[$mainType][$subTypeFees][$lcType]['weeks'][$weekKey])) {
                $result[$mainType][$subTypeFees][$lcType]['weeks'][$weekKey] = 0;
            }
            if (! isset($result[$mainType][$subTypeFees][$lcType]['total'][$weekKey])) {
                $result[$mainType][$subTypeFees][$lcType]['total'][$weekKey] = 0;
            }
            if (! isset($result[$mainType][$subTypeFees]['total'][$weekKey])) {
                $result[$mainType][$subTypeFees]['total'][$weekKey] = 0;
            }

            $result[$mainType][$subTypeFees][$lcType]['weeks'][$weekKey] += $amount;
            $result[$mainType][$subTypeFees][$lcType]['total'][$weekKey] += $amount;
            $result[$mainType][$subTypeFees]['total'][$weekKey] += $amount;
        }

        $lcRows = DB::table('letter_of_credit_issuances')
            ->where('letter_of_credit_issuances.company_id', $companyId)
            ->where('status', LetterOfCreditIssuance::RUNNING)
            ->whereBetween('letter_of_credit_issuances.due_date', [$periodStart, $periodEnd])
            ->when($reportCurrency !== $mainFunctionalCurrency, function ($q) use ($reportCurrency) {
                $q->where('lc_cash_cover_currency', $reportCurrency);
            })
            ->selectRaw('letter_of_credit_issuances.due_date as movement_date, letter_of_credit_issuances.lc_type as lc_type, transaction_name, (amount_in_main_currency - cash_cover_amount) as paid_amount, lc_cash_cover_currency as currency')
            ->get();

        foreach ($lcRows as $row) {
            $weekKey = CashFlowWeekBucketer::resolveWeekKey((string) $row->movement_date, $periodsByWeekKey);
            if ($weekKey === null) {
                continue;
            }

            $lcType = ($lcsTypes[$row->lc_type] ?? $row->lc_type).' [ '.$row->transaction_name.' ]';
            $exchangeRate = ForeignExchangeRate::getExchangeRateAt(
                (string) $row->currency,
                $mainFunctionalCurrency,
                (string) $row->movement_date,
                $companyId,
                $foreignExchangeRates,
            );
            $amount = self::convertedAmount((float) $row->paid_amount, $exchangeRate, $mainFunctionalCurrency, $reportCurrency);

            if (! isset($result[$mainType][$subTypeRemaining][$lcType])) {
                $result[$mainType][$subTypeRemaining][$lcType] = ['weeks' => [], 'total' => []];
            }
            if (! isset($result[$mainType][$subTypeRemaining][$lcType]['weeks'][$weekKey])) {
                $result[$mainType][$subTypeRemaining][$lcType]['weeks'][$weekKey] = 0;
            }
            if (! isset($result[$mainType][$subTypeRemaining][$lcType]['total'][$weekKey])) {
                $result[$mainType][$subTypeRemaining][$lcType]['total'][$weekKey] = 0;
            }
            if (! isset($result[$mainType][$subTypeRemaining]['total'][$weekKey])) {
                $result[$mainType][$subTypeRemaining]['total'][$weekKey] = 0;
            }

            $result[$mainType][$subTypeRemaining][$lcType]['weeks'][$weekKey] += $amount;
            $result[$mainType][$subTypeRemaining][$lcType]['total'][$weekKey] += $amount;
            $result[$mainType][$subTypeRemaining]['total'][$weekKey] += $amount;
        }
    }

    private static function applyCashExpenseMovements(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        string $reportCurrency,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
    ): void {
        self::applyCashExpenseType($result, $foreignExchangeRates, $mainFunctionalCurrency, $reportCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey, CashExpense::OUTGOING_TRANSFER, 'payment_date', null);
        self::applyCashExpenseType($result, $foreignExchangeRates, $mainFunctionalCurrency, $reportCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey, CashExpense::CASH_PAYMENT, 'payment_date', null);
        self::applyCashExpenseType($result, $foreignExchangeRates, $mainFunctionalCurrency, $reportCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey, CashExpense::PAYABLE_CHEQUE, 'actual_payment_date', PayableCheque::PAID);
        self::applyCashExpenseType($result, $foreignExchangeRates, $mainFunctionalCurrency, $reportCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey, CashExpense::PAYABLE_CHEQUE, 'due_date', PayableCheque::PENDING);
    }

    private static function applyCashExpenseType(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        string $reportCurrency,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
        string $moneyType,
        string $dateField,
        ?string $chequeStatus,
    ): void {
        $subTable = (new CashExpense())->getTable();
        $mainTable = match ($moneyType) {
            CashExpense::OUTGOING_TRANSFER => (new \App\Models\OutgoingTransfer())->getTable(),
            CashExpense::CASH_PAYMENT => (new \App\Models\CashPayment())->getTable(),
            default => (new PayableCheque())->getTable(),
        };
        $dateColumn = self::qualifiedCashExpenseDateColumn($moneyType, $dateField);

        $query = DB::table($mainTable)
            ->join($subTable, $subTable.'.id', '=', $mainTable.'.cash_expense_id')
            ->join('cash_expense_category_names', $subTable.'.cash_expense_category_name_id', '=', 'cash_expense_category_names.id')
            ->join('cash_expense_categories', 'cash_expense_category_names.cash_expense_category_id', '=', 'cash_expense_categories.id')
            ->where($subTable.'.type', $moneyType)
            ->where($subTable.'.company_id', $companyId)
            ->when($chequeStatus !== null, function ($builder) use ($chequeStatus, $mainTable) {
                $builder->where($mainTable.'.status', $chequeStatus);
            })
            ->when($reportCurrency !== $mainFunctionalCurrency, function ($builder) use ($reportCurrency, $subTable) {
                $builder->where($subTable.'.currency', $reportCurrency);
            })
            ->whereBetween($dateColumn, [$periodStart, $periodEnd])
            ->groupByRaw('cash_expense_category_name_id, cash_expense_categories.name, cash_expense_category_names.name, '.$dateColumn)
            ->selectRaw('cash_expense_categories.name as category_name, cash_expense_category_names.name as expense_name, sum(paid_amount) as paid_amount, '.$subTable.'.currency as currency, '.$dateColumn.' as movement_date');

        foreach ($query->get() as $row) {
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

            $categoryName = (string) $row->category_name;
            $expenseName = (string) $row->expense_name;
            $amount = self::convertedAmount((float) $row->paid_amount, $exchangeRate, $mainFunctionalCurrency, $reportCurrency);

            if (! isset($result['cash_expenses'][$categoryName][$expenseName])) {
                $result['cash_expenses'][$categoryName][$expenseName] = ['weeks' => [], 'total' => []];
            }
            if (! isset($result['cash_expenses'][$categoryName][$expenseName]['weeks'][$weekKey])) {
                $result['cash_expenses'][$categoryName][$expenseName]['weeks'][$weekKey] = 0;
            }
            if (! isset($result['cash_expenses'][$categoryName][$expenseName]['total'][$weekKey])) {
                $result['cash_expenses'][$categoryName][$expenseName]['total'][$weekKey] = 0;
            }
            if (! isset($result['cash_expenses'][$categoryName]['total'][$weekKey])) {
                $result['cash_expenses'][$categoryName]['total'][$weekKey] = 0;
            }

            $result['cash_expenses'][$categoryName][$expenseName]['weeks'][$weekKey] += $amount;
            $result['cash_expenses'][$categoryName][$expenseName]['total'][$weekKey] += $amount;
            $result['cash_expenses'][$categoryName]['total'][$weekKey] += $amount;
        }
    }

    private static function qualifiedCashExpenseDateColumn(string $moneyType, string $dateField): string
    {
        if ($moneyType === CashExpense::PAYABLE_CHEQUE) {
            return 'payable_cheques.'.$dateField;
        }

        return 'cash_expenses.'.$dateField;
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

    /**
     * Informational-only markers for invoices whose OWN currency matches the
     * tab being viewed, but that were actually collected in a different
     * currency (e.g. a USD invoice collected in EGP). These never add to
     * that tab's totals — they exist purely so the user isn't confused by a
     * USD invoice appearing to have never been collected while viewing the
     * USD tab. Only meaningful on a specific foreign-currency tab; the main
     * functional currency tab already shows everything converted in.
     */
    public static function applyCrossCurrencyCollectionNotes(
        array &$crossCurrencyNotes,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
        string $reportCurrency,
        string $mainFunctionalCurrency,
    ): void {
        if ($reportCurrency === $mainFunctionalCurrency) {
            return;
        }

        // Cheques collected against an invoice issued in $reportCurrency
        // but banked in a different currency.
        $chequeRows = DB::table('money_received')
            ->join('cheques', 'cheques.money_received_id', '=', 'money_received.id')
            ->join('settlements', 'money_received.id', '=', 'settlements.money_received_id')
            ->join('customer_invoices', 'customer_invoices.id', '=', 'settlements.invoice_id')
            ->join('partners', 'partners.id', '=', 'money_received.partner_id')
            ->where('money_received.company_id', $companyId)
            ->where('cheques.status', Cheque::COLLECTED)
            ->where('money_received.type', MoneyReceived::CHEQUE)
            ->where('money_received.currency', $reportCurrency)
            ->whereNotNull('money_received.receiving_currency')
            ->where('money_received.receiving_currency', '!=', $reportCurrency)
            ->whereBetween('cheques.actual_collection_date', [$periodStart, $periodEnd])
            ->selectRaw('cheques.actual_collection_date as movement_date, money_received.amount_in_invoice_currency, money_received.received_amount, money_received.receiving_currency, '.partner_display_name_sql('partners', 'partner_name'))
            ->get();
        self::appendCrossCurrencyNotes($crossCurrencyNotes, __('Checks Collected'), $chequeRows, $periodsByWeekKey);

        // Bank deposits / cash collections / incoming transfers issued in
        // $reportCurrency but received in a different currency.
        $typeRowLabels = [
            MoneyReceived::CASH_IN_BANK => __('Bank Deposits'),
            MoneyReceived::CASH_IN_SAFE => __('Cash Collections'),
            MoneyReceived::INCOMING_TRANSFER => __('Incoming Transfers'),
        ];
        foreach ($typeRowLabels as $moneyType => $rowLabel) {
            $rows = DB::table('money_received')
                ->join('partners', 'partners.id', '=', 'money_received.partner_id')
                ->where('money_received.company_id', $companyId)
                ->where('money_received.type', $moneyType)
                ->where('money_received.currency', $reportCurrency)
                ->whereNotNull('money_received.receiving_currency')
                ->where('money_received.receiving_currency', '!=', $reportCurrency)
                ->whereBetween('money_received.receiving_date', [$periodStart, $periodEnd])
                ->selectRaw('money_received.receiving_date as movement_date, money_received.amount_in_invoice_currency, money_received.received_amount, money_received.receiving_currency, '.partner_display_name_sql('partners', 'partner_name'))
                ->get();
            self::appendCrossCurrencyNotes($crossCurrencyNotes, $rowLabel, $rows, $periodsByWeekKey);
        }
    }

    private static function appendCrossCurrencyNotes(array &$crossCurrencyNotes, string $rowLabel, $rows, array $periodsByWeekKey): void
    {
        foreach ($rows as $row) {
            $weekKey = CashFlowWeekBucketer::resolveWeekKey((string) $row->movement_date, $periodsByWeekKey);
            if ($weekKey === null) {
                continue;
            }

            $crossCurrencyNotes[$rowLabel]['weeks'][$weekKey][] = [
                'partner_name' => (string) ($row->partner_name ?: __('Unknown Customer')),
                'amount_in_invoice_currency' => (float) ($row->amount_in_invoice_currency ?: $row->received_amount),
                'collected_currency' => (string) $row->receiving_currency,
                'collected_amount' => (float) $row->received_amount,
                'movement_date' => (string) $row->movement_date,
            ];
        }
    }
}
