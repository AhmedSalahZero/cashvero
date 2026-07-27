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
    /** Same as LetterOfGuaranteeIssuance::getCashCovers() — issuance renewal_date, not cash_cover_statements.date. */
    private const LG_CASH_COVER_DATE_COLUMN = 'letter_of_guarantee_issuances.renewal_date';

   
    public static function apply(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
        array &$letterOfGuaranteeModelData = [],
    ): void {
        self::applyMoneyReceivedMovements($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey);
        self::applyMoneyPaymentMovements($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey);
        self::applyTimeOfDepositMovements($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey);
        self::applyLetterOfGuaranteeMovements($result, $letterOfGuaranteeModelData, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey);
        self::applyLetterOfCreditMovements($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey);
        self::applyCashExpenseMovements($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey);
    }

    private static function applyMoneyReceivedMovements(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
    ): void {
        $totalCashInFlowKey = __('Total Cash Inflow');

        self::applyChequeSettlements($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey, Cheque::UNDER_COLLECTION, __('Cheques Under Collection'), $totalCashInFlowKey);
        self::applyChequeSettlements($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey, Cheque::COLLECTED, __('Checks Collected'), $totalCashInFlowKey);
        self::applyMoneyReceivedByType($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey, MoneyReceived::INCOMING_TRANSFER, __('Incoming Transfers'), $totalCashInFlowKey);
        self::applyMoneyReceivedByType($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey, MoneyReceived::CASH_IN_BANK, __('Bank Deposits'), $totalCashInFlowKey);
        self::applyMoneyReceivedByType($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey, MoneyReceived::CASH_IN_SAFE, __('Cash Collections'), $totalCashInFlowKey);
        self::applyChequeInSafe($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey, $totalCashInFlowKey);
    }

    private static function applyChequeSettlements(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
        string $chequeStatus,
        string $resultKey,
        string $totalCashInFlowKey,
    ): void {
        $dateColumn = $chequeStatus === Cheque::COLLECTED ? 'cheques.actual_collection_date' : 'cheques.expected_collection_date';

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
            ->whereBetween($dateColumn, [$periodStart, $periodEnd])
            ->selectRaw('customer_invoices.contract_code as contract_code, settlements.settlement_amount as received_amount, money_received.receiving_currency, '.$dateColumn.' as movement_date, customer_invoices.invoice_number');

        foreach ($query->cursor() as $row) {
            self::accumulateMoneyReceivedRow($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodsByWeekKey, $resultKey, $totalCashInFlowKey, $row, true);
        }
    }

    private static function applyChequeInSafe(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
        string $totalCashInFlowKey,
    ): void {
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
            ->whereBetween('cheques.due_date', [$periodStart, $periodEnd])
            ->selectRaw('customer_invoices.contract_code as contract_code, settlements.settlement_amount as received_amount, money_received.receiving_currency, cheques.due_date as movement_date, customer_invoices.invoice_number');

        foreach ($query->cursor() as $row) {
            self::accumulateMoneyReceivedRow($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodsByWeekKey, __('Cheques In Safe'), $totalCashInFlowKey, $row, true);
        }
    }

    private static function applyMoneyReceivedByType(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
        string $moneyType,
        string $resultKey,
        string $totalCashInFlowKey,
    ): void {
        $query = DB::table('money_received')
            ->join('partners', 'partners.id', '=', 'money_received.partner_id')
            ->where('money_received.company_id', $companyId)
            ->where('money_received.type', $moneyType)
            ->whereBetween('money_received.receiving_date', [$periodStart, $periodEnd])
            ->selectRaw('money_received.received_amount, money_received.receiving_currency, money_received.receiving_date as movement_date, '.partner_display_name_sql('partners', 'partner_name'));

        foreach ($query->cursor() as $row) {
            self::accumulateMoneyReceivedRow($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodsByWeekKey, $resultKey, $totalCashInFlowKey, $row, false);
        }
    }

    private static function accumulateMoneyReceivedRow(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
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

        $amount = (float) $row->received_amount * $exchangeRate;
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

    private static function applyMoneyPaymentMovements(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
    ): void {
        self::applyMoneyPaymentByType($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey, MoneyPayment::OUTGOING_TRANSFER, null);
        self::applyMoneyPaymentByType($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey, MoneyPayment::CASH_PAYMENT, null);
        self::applyMoneyPaymentByType($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey, MoneyPayment::PAYABLE_CHEQUE, PayableCheque::PAID);
        self::applyMoneyPaymentByType($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey, MoneyPayment::PAYABLE_CHEQUE, PayableCheque::PENDING);
    }

    private static function applyMoneyPaymentByType(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
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
            ->where('money_payments.type', $moneyType);

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

            $amount = (float) $row->paid_amount * $exchangeRate;
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

            $rawPaid = (float) $row->paid_amount;
            $result['suppliers'][$typeLabel][$supplierName]['weeks'][$weekKey] += $rawPaid;
            $result['suppliers'][$typeLabel][$supplierName]['total'][$weekKey] += $rawPaid;

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

            $currentPaidAmount = (float) $row->total_amount * $exchangeRate;

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
            $amount = (float) $row->paid_amount * $exchangeRate;

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
        $coverRows = DB::table('letter_of_guarantee_cash_cover_statements')
            ->where('letter_of_guarantee_cash_cover_statements.company_id', $companyId)
            ->join('letter_of_guarantee_issuances', 'letter_of_guarantee_issuances.id', '=', 'letter_of_guarantee_cash_cover_statements.letter_of_guarantee_issuance_id')
            ->join('partners', 'partners.id', '=', 'letter_of_guarantee_issuances.partner_id')
            ->whereBetween(self::LG_CASH_COVER_DATE_COLUMN, [$periodStart, $periodEnd])
            ->where('letter_of_guarantee_cash_cover_statements.letter_of_guarantee_issuance_id', '>', 0)
            ->selectRaw('letter_of_guarantee_issuances.lg_type as lg_type, letter_of_guarantee_cash_cover_statements.debit as total_amount, letter_of_guarantee_cash_cover_statements.currency as currency, '.self::LG_CASH_COVER_DATE_COLUMN.' as movement_date, partners.name as partner_name, letter_of_guarantee_issuances.lg_code as lg_code')
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
            $amount = (float) $row->total_amount * $exchangeRate;

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
            $letterOfGuaranteeModelData[$lgType]['weeks'][$weekKey][] = [
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
            $amount = (float) $row->paid_amount * $exchangeRate;

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
            $amount = (float) $row->paid_amount * $exchangeRate;

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
        int $companyId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
    ): void {
        self::applyCashExpenseType($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey, CashExpense::OUTGOING_TRANSFER, 'payment_date', null);
        self::applyCashExpenseType($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey, CashExpense::CASH_PAYMENT, 'payment_date', null);
        self::applyCashExpenseType($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey, CashExpense::PAYABLE_CHEQUE, 'actual_payment_date', PayableCheque::PAID);
        self::applyCashExpenseType($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey, CashExpense::PAYABLE_CHEQUE, 'due_date', PayableCheque::PENDING);
    }

    private static function applyCashExpenseType(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
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
            $amount = (float) $row->paid_amount * $exchangeRate;

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
}
