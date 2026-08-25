<?php

namespace App\Services\Reports;

use App\Enums\LcTypes;
use App\Enums\LgTypes;
use App\Models\CashExpense;
use App\Models\Cheque;
use App\Models\ForeignExchangeRate;
use App\Models\LetterOfCreditIssuance;
use App\Models\LetterOfGuaranteeIssuance;
use App\Support\LetterOfGuarantee\LgRenewalTerms;
use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use App\Models\PayableCheque;
use App\Models\SupplierInvoice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Loads single-contract cash-flow movements for the full report period in one query per category,
 * then buckets amounts into week columns in PHP (replaces per-week queries in CashFlowReportController::result).
 */
final class CashFlowContractDetailPeriodBatchLoader
{
    public static function apply(
        array &$result,
        array &$letterOfGuaranteeModelData,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        string $contractCode,
        int $contractId,
        int $customerId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
        array &$incomingTransferModelData = [],
        ?Collection $poAllocations = null,
    ): void {
        self::applySettlementMovements($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $contractCode, $periodStart, $periodEnd, $periodsByWeekKey, $incomingTransferModelData);
        self::applyDownPaymentMovements($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $contractId, $periodStart, $periodEnd, $periodsByWeekKey, $incomingTransferModelData);
        self::applySettlementAllocations($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $contractId, $customerId, $periodStart, $periodEnd, $periodsByWeekKey);
        self::applyMoneyPaymentMovements($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $contractId, $periodStart, $periodEnd, $periodsByWeekKey);
        self::applyLetterOfGuaranteeMovements($result, $letterOfGuaranteeModelData, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $contractId, $periodStart, $periodEnd, $periodsByWeekKey);
        self::applyLetterOfCreditMovements($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodStart, $periodEnd, $periodsByWeekKey);
        self::applyCashExpenseMovements($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $contractId, $periodStart, $periodEnd, $periodsByWeekKey);
        if ($poAllocations !== null && $poAllocations->isNotEmpty()) {
            self::applySupplierPaymentMovementsViaPoAllocations($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $poAllocations, $periodStart, $periodEnd, $periodsByWeekKey);
        }
    }

    private static function applySettlementMovements(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        string $contractCode,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
        array &$incomingTransferModelData,
    ): void {
        $totalCashInFlowKey = __('Total Cash Inflow');

        self::applyContractChequeSettlements($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $contractCode, $periodStart, $periodEnd, $periodsByWeekKey, Cheque::UNDER_COLLECTION, 'cheques.expected_collection_date', __('Cheques Under Collection'), $totalCashInFlowKey);
        self::applyContractChequeSettlements($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $contractCode, $periodStart, $periodEnd, $periodsByWeekKey, Cheque::COLLECTED, 'cheques.actual_collection_date', __('Checks Collected'), $totalCashInFlowKey);
        self::applyContractMoneyReceivedByType($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $contractCode, $periodStart, $periodEnd, $periodsByWeekKey, MoneyReceived::INCOMING_TRANSFER, __('Incoming Transfers'), $totalCashInFlowKey, $incomingTransferModelData);
        self::applyContractMoneyReceivedByType($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $contractCode, $periodStart, $periodEnd, $periodsByWeekKey, MoneyReceived::CASH_IN_BANK, __('Bank Deposits'), $totalCashInFlowKey, $incomingTransferModelData);
        self::applyContractMoneyReceivedByType($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $contractCode, $periodStart, $periodEnd, $periodsByWeekKey, MoneyReceived::CASH_IN_SAFE, __('Cash Collections'), $totalCashInFlowKey, $incomingTransferModelData);
        self::applyContractChequeSettlements($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $contractCode, $periodStart, $periodEnd, $periodsByWeekKey, Cheque::IN_SAFE, 'cheques.due_date', __('Cheques In Safe'), $totalCashInFlowKey);
    }

    private static function applyContractChequeSettlements(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        string $contractCode,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
        string $chequeStatus,
        string $dateColumn,
        string $resultKey,
        string $totalCashInFlowKey,
    ): void {
        if ($chequeStatus === Cheque::COLLECTED) {
            self::applyContractCollectedChequeMovements($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $contractCode, $periodStart, $periodEnd, $periodsByWeekKey, $resultKey, $totalCashInFlowKey);

            return;
        }

        $settlementAmountExpression = self::settlementAmountInReceivingCurrencySql();
        $query = DB::table('money_received')
            ->join('cheques', 'cheques.money_received_id', '=', 'money_received.id')
            ->join('settlements', 'money_received.id', '=', 'settlements.money_received_id')
            ->join('customer_invoices', 'customer_invoices.id', '=', 'settlements.invoice_id')
            ->where('money_received.company_id', $companyId)
            ->where('cheques.status', $chequeStatus)
            ->where('money_received.type', MoneyReceived::CHEQUE)
            ->where('customer_invoices.contract_code', $contractCode)
            ->where(function ($q) {
                $q->whereNull('money_received.down_payment_type')
                    ->orWhere('money_received.down_payment_type', '=', 'general');
            })
            ->whereBetween($dateColumn, [$periodStart, $periodEnd])
            ->selectRaw('money_received.receiving_currency, '.$dateColumn.' as movement_date, customer_invoices.invoice_number, '.$settlementAmountExpression.' as received_amount');

        foreach ($query->cursor() as $row) {
            self::accumulateContractMoneyReceivedRow($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodsByWeekKey, $resultKey, $totalCashInFlowKey, $row, (string) $row->invoice_number);
        }
    }

    private static function applyContractCollectedChequeMovements(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        string $contractCode,
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
            ->join('customer_invoices', 'customer_invoices.id', '=', 'settlements.invoice_id')
            ->join('partners', 'partners.id', '=', 'money_received.partner_id')
            ->where('money_received.company_id', $companyId)
            ->where('cheques.status', Cheque::COLLECTED)
            ->where('money_received.type', MoneyReceived::CHEQUE)
            ->where('customer_invoices.contract_code', $contractCode)
            ->where(function ($q) {
                $q->whereNull('money_received.down_payment_type')
                    ->orWhere('money_received.down_payment_type', '=', 'general');
            })
            ->whereBetween('cheques.actual_collection_date', [$periodStart, $periodEnd])
            ->groupByRaw('money_received.id, money_received.receiving_currency, cheques.actual_collection_date, cheques.cheque_number, partners.name')
            ->selectRaw('money_received.id as money_received_id, money_received.receiving_currency, cheques.actual_collection_date as movement_date, cheques.cheque_number, partners.name as customer_name, sum('.$settlementAmountExpression.') as received_amount');

        foreach ($query->cursor() as $row) {
            self::accumulateContractCollectedChequeRow($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodsByWeekKey, $resultKey, $totalCashInFlowKey, $row);
        }
    }

    private static function applyContractMoneyReceivedByType(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        string $contractCode,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
        string $moneyType,
        string $resultKey,
        string $totalCashInFlowKey,
        array &$incomingTransferModelData,
    ): void {
        if ($moneyType === MoneyReceived::INCOMING_TRANSFER) {
            self::applyContractIncomingTransferMovements($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $contractCode, $periodStart, $periodEnd, $periodsByWeekKey, $resultKey, $totalCashInFlowKey, $incomingTransferModelData);

            return;
        }

        $settlementAmountExpression = self::settlementAmountInReceivingCurrencySql();
        $query = DB::table('money_received')
            ->join('settlements', 'money_received.id', '=', 'settlements.money_received_id')
            ->join('customer_invoices', 'customer_invoices.id', '=', 'settlements.invoice_id')
            ->where('money_received.company_id', $companyId)
            ->where('money_received.type', $moneyType)
            ->where('customer_invoices.contract_code', $contractCode)
            ->where(function ($q) {
                $q->whereNull('money_received.down_payment_type')
                    ->orWhere('money_received.down_payment_type', '=', 'general');
            })
            ->whereBetween('money_received.receiving_date', [$periodStart, $periodEnd])
            ->selectRaw('money_received.receiving_currency, money_received.receiving_date as movement_date, customer_invoices.invoice_number, '.$settlementAmountExpression.' as received_amount');

        foreach ($query->cursor() as $row) {
            self::accumulateContractMoneyReceivedRow($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodsByWeekKey, $resultKey, $totalCashInFlowKey, $row, (string) $row->invoice_number);
        }
    }

    private static function applyContractIncomingTransferMovements(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        string $contractCode,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
        string $resultKey,
        string $totalCashInFlowKey,
        array &$incomingTransferModelData,
    ): void {
        $settlementAmountExpression = self::settlementAmountInReceivingCurrencySql();
        $query = DB::table('money_received')
            ->join('settlements', 'money_received.id', '=', 'settlements.money_received_id')
            ->join('customer_invoices', 'customer_invoices.id', '=', 'settlements.invoice_id')
            ->join('partners', 'partners.id', '=', 'money_received.partner_id')
            ->leftJoin('incoming_transfers', 'incoming_transfers.money_received_id', '=', 'money_received.id')
            ->leftJoin('financial_institutions', 'financial_institutions.id', '=', 'incoming_transfers.receiving_bank_id')
            ->leftJoin('banks', 'banks.id', '=', 'financial_institutions.bank_id')
            ->where('money_received.company_id', $companyId)
            ->where('money_received.type', MoneyReceived::INCOMING_TRANSFER)
            ->where('customer_invoices.contract_code', $contractCode)
            ->where(function ($q) {
                $q->whereNull('money_received.down_payment_type')
                    ->orWhere('money_received.down_payment_type', '=', 'general');
            })
            ->whereBetween('money_received.receiving_date', [$periodStart, $periodEnd])
            ->groupByRaw('money_received.id, money_received.receiving_currency, money_received.receiving_date, partners.name, financial_institutions.type, financial_institutions.name, banks.view_name, banks.name_en, banks.name_ar')
            ->selectRaw('money_received.id as money_received_id, money_received.receiving_currency, money_received.receiving_date as movement_date, partners.name as customer_name, '.financial_institution_display_name_sql().', sum('.$settlementAmountExpression.') as received_amount');

        foreach ($query->cursor() as $row) {
            self::accumulateContractIncomingTransferRow($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodsByWeekKey, $resultKey, $totalCashInFlowKey, $row, $incomingTransferModelData);
        }
    }

    private static function accumulateContractMoneyReceivedRow(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        array $periodsByWeekKey,
        string $typeKey,
        string $totalCashInFlowKey,
        object $row,
        string $label,
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

        if (! isset($result['customers'][$typeKey][$label])) {
            $result['customers'][$typeKey][$label] = ['weeks' => [], 'total' => 0];
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
        $result['customers'][$typeKey][$label]['total'] = ($result['customers'][$typeKey][$label]['total'] ?? 0) + $amount;
        $result['customers'][$typeKey]['total'][$weekKey] += $amount;
        $result['customers'][$totalCashInFlowKey]['total'][$weekKey] += $amount;
    }

    private static function accumulateContractCollectedChequeRow(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
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

        $amount = (float) $row->received_amount * $exchangeRate;
        $subRowKey = 'money_received_'.$row->money_received_id;
        $label = (string) ($row->customer_name ?: __('Unknown Customer'));

        if (! isset($result['customers'][$typeKey][$subRowKey])) {
            $result['customers'][$typeKey][$subRowKey] = [
                'weeks' => [],
                'total' => 0,
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
        if (! isset($result['customers'][$typeKey]['total'][$weekKey])) {
            $result['customers'][$typeKey]['total'][$weekKey] = 0;
        }
        if (! isset($result['customers'][$totalCashInFlowKey]['total'][$weekKey])) {
            $result['customers'][$totalCashInFlowKey]['total'][$weekKey] = 0;
        }

        $result['customers'][$typeKey][$subRowKey]['weeks'][$weekKey] += $amount;
        $result['customers'][$typeKey][$subRowKey]['total'] += $amount;
        $result['customers'][$typeKey]['total'][$weekKey] += $amount;
        $result['customers'][$totalCashInFlowKey]['total'][$weekKey] += $amount;
    }

    private static function accumulateContractIncomingTransferRow(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
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

        $amount = (float) $row->received_amount * $exchangeRate;
        $subRowKey = 'money_received_'.$row->money_received_id;
        $label = (string) ($row->customer_name ?: __('Unknown Customer'));

        if (! isset($result['customers'][$typeKey][$subRowKey])) {
            $result['customers'][$typeKey][$subRowKey] = [
                'weeks' => [],
                'total' => 0,
                'label' => $label,
            ];
        }
        if (! isset($result['customers'][$typeKey][$subRowKey]['weeks'][$weekKey])) {
            $result['customers'][$typeKey][$subRowKey]['weeks'][$weekKey] = 0;
        }
        if (! isset($result['customers'][$typeKey]['total'][$weekKey])) {
            $result['customers'][$typeKey]['total'][$weekKey] = 0;
        }
        if (! isset($result['customers'][$totalCashInFlowKey]['total'][$weekKey])) {
            $result['customers'][$totalCashInFlowKey]['total'][$weekKey] = 0;
        }

        $result['customers'][$typeKey][$subRowKey]['weeks'][$weekKey] += $amount;
        $result['customers'][$typeKey][$subRowKey]['total'] += $amount;
        $result['customers'][$typeKey]['total'][$weekKey] += $amount;
        $result['customers'][$totalCashInFlowKey]['total'][$weekKey] += $amount;

        // ⚠️ Bug fix: this row used to build a breakdown payload
        // ('incoming_transfer_info') that nothing ever read — the "ℹ️"
        // breakdown popup on this row reads from the separate
        // incomingTransferModelData structure (same one the already-working
        // Company Cash Flow report populates), keyed by this row's own key
        // (one row per transfer here, vs. per-customer on the company
        // report) and bucketed by week, matching what Result.vue's
        // buildSubRow() actually looks up.
        $incomingTransferModelData[$subRowKey]['weeks'][$weekKey][] = [
            'amount' => $amount,
            'bank_name' => (string) ($row->bank_name ?? __('N/A')),
            'movement_date' => (string) $row->movement_date,
        ];
    }

    private static function applyDownPaymentMovements(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        int $contractId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
        array &$incomingTransferModelData,
    ): void {
        $configs = [
            [MoneyReceived::CHEQUE, 'expected_collection_date', Cheque::UNDER_COLLECTION],
            [MoneyReceived::CHEQUE, 'actual_collection_date', Cheque::COLLECTED],
            [MoneyReceived::INCOMING_TRANSFER, 'receiving_date', null],
            [MoneyReceived::CASH_IN_BANK, 'receiving_date', null],
            [MoneyReceived::CASH_IN_SAFE, 'receiving_date', null],
        ];

        $totalCashInFlowKey = __('Total Cash Inflow');
        $label = __('Down Payment');

        foreach ($configs as [$moneyType, $dateColumn, $chequeStatus]) {
            $typeKey = self::moneyReceivedTypeLabel($moneyType, $chequeStatus);
            $isIncomingTransfer = $moneyType === MoneyReceived::INCOMING_TRANSFER;

            $query = DB::table('money_received')
                ->where('money_received.company_id', $companyId)
                ->where('down_payment_type', 'over_contract')
                ->where('money_received.type', $moneyType)
                ->where('contract_id', $contractId)
                ->when($chequeStatus, function ($builder) use ($chequeStatus) {
                    $builder->join('cheques', 'cheques.money_received_id', '=', 'money_received.id')
                        ->where('cheques.status', $chequeStatus);
                });

            $qualifiedDate = $chequeStatus
                ? 'cheques.'.$dateColumn
                : 'money_received.'.$dateColumn;

            $query->whereBetween($qualifiedDate, [$periodStart, $periodEnd]);

            // ⚠️ Bug fix: down-payment collections received by Incoming
            // Transfer land in this same combined "Down Payment" row as
            // every other down-payment collection method, but — unlike the
            // regular (non-down-payment) Incoming Transfer rows above —
            // this loop never joined the bank tables or recorded anything
            // into incomingTransferModelData, so the "ℹ️" breakdown on this
            // row always showed "No breakdown entries." even when the row
            // had a real total. Joining the bank tables only for this one
            // money type (the others have no meaningful "bank" to show)
            // and recording each contributing transaction the same way
            // accumulateContractIncomingTransferRow() already does.
            if ($isIncomingTransfer) {
                $query->leftJoin('incoming_transfers', 'incoming_transfers.money_received_id', '=', 'money_received.id')
                    ->leftJoin('financial_institutions', 'financial_institutions.id', '=', 'incoming_transfers.receiving_bank_id')
                    ->leftJoin('banks', 'banks.id', '=', 'financial_institutions.bank_id')
                    ->selectRaw('money_received.received_amount, money_received.receiving_currency, '.$qualifiedDate.' as movement_date, '.financial_institution_display_name_sql());
            } else {
                $query->selectRaw('money_received.received_amount, money_received.receiving_currency, '.$qualifiedDate.' as movement_date');
            }

            foreach ($query->cursor() as $row) {
                self::accumulateContractMoneyReceivedRow($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $periodsByWeekKey, $typeKey, $totalCashInFlowKey, $row, $label);

                if ($isIncomingTransfer) {
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
                    $incomingTransferModelData[$label]['weeks'][$weekKey][] = [
                        'amount' => (float) $row->received_amount * $exchangeRate,
                        'bank_name' => (string) ($row->bank_name ?? __('N/A')),
                        'movement_date' => (string) $row->movement_date,
                    ];
                }
            }
        }
    }

    private static function applySettlementAllocations(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        int $contractId,
        int $customerId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
    ): void {
        $rows = DB::table('settlement_allocations')
            ->join('letter_of_credit_issuances', 'settlement_allocations.letter_of_credit_issuance_id', '=', 'letter_of_credit_issuances.id')
            // ⚠️ Was `letter_of_credit_issuances.supplier_id` — that column doesn't
            // exist (confirmed against schema_full.txt). The correct FK is
            // `partner_id`, same as LetterOfCreditIssuance::supplier()'s own
            // belongsTo(Partner::class, 'partner_id', 'id') — same bug class as
            // roadmap §14 (invalid columns not matching the actual schema).
            ->join('partners', 'partners.id', '=', 'letter_of_credit_issuances.partner_id')
            ->where('settlement_allocations.contract_id', $contractId)
            ->where('settlement_allocations.partner_id', $customerId)
            ->whereBetween('letter_of_credit_issuances.due_date', [$periodStart, $periodEnd])
            ->where('letter_of_credit_issuances.company_id', $companyId)
            ->selectRaw('settlement_allocations.allocation_amount, settlement_allocations.invoice_id, letter_of_credit_issuances.payment_currency, letter_of_credit_issuances.payment_date, letter_of_credit_issuances.due_date, partners.name as supplier_name')
            ->get();

        $invoiceIds = $rows->pluck('invoice_id')->filter()->unique()->values();
        $invoicesById = $invoiceIds->isNotEmpty()
            ? SupplierInvoice::whereIn('id', $invoiceIds->all())->get()->keyBy('id')
            : collect();

        foreach ($rows as $row) {
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

            $invoiceNumber = '';
            if ($row->invoice_id && $invoicesById->has($row->invoice_id)) {
                $invoiceNumber = $invoicesById->get($row->invoice_id)->getInvoiceNumber();
            }

            $lcKey = __('Letter Of Credit').' - '.__('Invoice No').' '.$invoiceNumber;
            $supplierName = (string) $row->supplier_name;
            $amount = (float) $row->allocation_amount * $exchangeRate;

            if (! isset($result['suppliers'][$lcKey][$supplierName])) {
                $result['suppliers'][$lcKey][$supplierName] = ['weeks' => [], 'total' => 0];
            }
            if (! isset($result['suppliers'][$lcKey][$supplierName]['weeks'][$weekKey])) {
                $result['suppliers'][$lcKey][$supplierName]['weeks'][$weekKey] = 0;
            }
            if (! isset($result['suppliers'][$lcKey]['total'][$weekKey])) {
                $result['suppliers'][$lcKey]['total'][$weekKey] = 0;
            }

            $result['suppliers'][$lcKey][$supplierName]['weeks'][$weekKey] += $amount;
            $result['suppliers'][$lcKey][$supplierName]['total'] += $amount;
            $result['suppliers'][$lcKey]['total'][$weekKey] += $amount;
        }
    }

    /**
     * Supplier payments that never get tagged with THIS (Customer)
     * contract directly — a supplier payment settlement always carries
     * the SUPPLIER's own contract_id (see settlement_allocations),
     * never the Customer contract it might be linked to. The only link
     * is po_allocations: Customer contract -> allocated PO -> that PO's
     * real Supplier invoice(s) -> whatever payments/LCs settled them.
     * Each match is weighted by the PO's allocation_percentage for this
     * contract, so a payment on a PO that's 60% allocated here shows
     * 60% of its paid amount — same weighting already used for the
     * "Suppliers Invoices" row (SupplierInvoice::getSupplierInvoicesForPoUnderCollectionAtDates).
     * Covers every payment type Company Cash Flow shows (Outgoing
     * Transfers, Cash Payments, Paid/Under-Payment Payable Cheques)
     * plus Letters of Credit that settled the invoice.
     */
    private static function applySupplierPaymentMovementsViaPoAllocations(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        Collection $poAllocations,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
    ): void {
        foreach ($poAllocations as $poAllocation) {
            $allocationPercentage = ((float) ($poAllocation->allocation_percentage ?? 0)) / 100;
            if ($allocationPercentage <= 0) {
                continue;
            }

            $invoiceIds = DB::table('supplier_invoices')
                ->where('company_id', $companyId)
                ->where('contract_code', $poAllocation->code)
                ->where('purchases_order_number', $poAllocation->po_number)
                ->pluck('id');

            if ($invoiceIds->isEmpty()) {
                continue;
            }

            self::applyPoAllocatedMoneyPayments($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $invoiceIds, $allocationPercentage, $periodStart, $periodEnd, $periodsByWeekKey);
            self::applyPoAllocatedLetterOfCreditSettlements($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $invoiceIds, $allocationPercentage, $periodStart, $periodEnd, $periodsByWeekKey);
        }
    }

    /**
     * Outgoing Transfers / Cash Payments / Paid & Under-Payment Payable
     * Cheques for invoices matched via po_allocations.
     *
     * ⚠️ Bug fix: the first version of this method only checked
     * settlement_allocations (mirroring applyContractMoneyPaymentByType()
     * below), and a real paid invoice was confirmed missing from this
     * row as a result. Confirmed with the project owner: which table
     * actually holds the invoice/payment link depends on which screen
     * was used to record the settlement — the regular "Invoice
     * Settlement" flow writes to payment_settlements (invoice_id +
     * money_payment_id + settlement_amount), while other flows can
     * still write to settlement_allocations. Both are checked and
     * summed here so neither is silently missed. payment_settlements'
     * is_from_down_payment=0 excludes down-payment-sourced settlements
     * — those aren't a new cash movement (the actual outflow already
     * happened when the down payment itself was paid), they're just an
     * accounting reallocation onto this invoice, already reflected in
     * the Forecasted row via the down payment balance deduction.
     */
    private static function applyPoAllocatedMoneyPayments(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        Collection $invoiceIds,
        float $allocationPercentage,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
    ): void {
        $paymentTypes = [
            [MoneyPayment::OUTGOING_TRANSFER, null],
            [MoneyPayment::CASH_PAYMENT, null],
            [MoneyPayment::PAYABLE_CHEQUE, PayableCheque::PAID],
            [MoneyPayment::PAYABLE_CHEQUE, PayableCheque::PENDING],
        ];

        foreach ($paymentTypes as [$moneyType, $chequeStatus]) {
            $typeLabel = match ($moneyType) {
                MoneyPayment::OUTGOING_TRANSFER => __('Outgoing Transfers'),
                MoneyPayment::CASH_PAYMENT => __('Cash Payments'),
                MoneyPayment::PAYABLE_CHEQUE => $chequeStatus === PayableCheque::PAID
                    ? __('Paid Payable Cheques')
                    : __('Under Payment Payable Cheques'),
                default => $moneyType,
            };

            // settlement_allocations link
            $settlementAllocationsQuery = DB::table('money_payments')
                ->join('partners', 'partners.id', '=', 'money_payments.partner_id')
                ->join('settlement_allocations', 'money_payments.id', '=', 'settlement_allocations.money_payment_id')
                ->where('money_payments.company_id', $companyId)
                ->where('money_payments.type', $moneyType)
                ->whereIn('settlement_allocations.invoice_id', $invoiceIds);

            // payment_settlements link
            $paymentSettlementsQuery = DB::table('money_payments')
                ->join('partners', 'partners.id', '=', 'money_payments.partner_id')
                ->join('payment_settlements', 'money_payments.id', '=', 'payment_settlements.money_payment_id')
                ->where('money_payments.company_id', $companyId)
                ->where('money_payments.type', $moneyType)
                ->where('payment_settlements.is_from_down_payment', 0)
                ->whereIn('payment_settlements.invoice_id', $invoiceIds);

            if ($chequeStatus !== null) {
                $settlementAllocationsQuery->join('payable_cheques', 'payable_cheques.money_payment_id', '=', 'money_payments.id')
                    ->where('payable_cheques.status', $chequeStatus);
                $paymentSettlementsQuery->join('payable_cheques', 'payable_cheques.money_payment_id', '=', 'money_payments.id')
                    ->where('payable_cheques.status', $chequeStatus);
                $dateField = $chequeStatus === PayableCheque::PAID ? 'payable_cheques.actual_payment_date' : 'payable_cheques.due_date';
            } else {
                $dateField = 'money_payments.delivery_date';
            }

            $rows = $settlementAllocationsQuery->whereBetween($dateField, [$periodStart, $periodEnd])
                ->selectRaw('settlement_allocations.allocation_amount as paid_amount, money_payments.payment_currency, '.$dateField.' as movement_date, partners.name as supplier_name')
                ->get()
                ->concat(
                    $paymentSettlementsQuery->whereBetween($dateField, [$periodStart, $periodEnd])
                        ->selectRaw('payment_settlements.settlement_amount as paid_amount, money_payments.payment_currency, '.$dateField.' as movement_date, partners.name as supplier_name')
                        ->get()
                );

            foreach ($rows as $row) {
                $weekKey = CashFlowWeekBucketer::resolveWeekKey((string) $row->movement_date, $periodsByWeekKey);
                if ($weekKey === null) {
                    continue;
                }

                $exchangeRate = ForeignExchangeRate::getExchangeRateForCurrencyAndClosestDate(
                    (string) $row->payment_currency,
                    $mainFunctionalCurrency,
                    (string) $row->movement_date,
                    $companyId,
                    $foreignExchangeRates,
                );
                $amount = (float) $row->paid_amount * $exchangeRate * $allocationPercentage;
                $supplierName = (string) $row->supplier_name;

                if (! isset($result['suppliers'][$typeLabel][$supplierName])) {
                    $result['suppliers'][$typeLabel][$supplierName] = ['weeks' => [], 'total' => 0];
                }
                if (! isset($result['suppliers'][$typeLabel][$supplierName]['weeks'][$weekKey])) {
                    $result['suppliers'][$typeLabel][$supplierName]['weeks'][$weekKey] = 0;
                }
                if (! isset($result['suppliers'][$typeLabel]['total'][$weekKey])) {
                    $result['suppliers'][$typeLabel]['total'][$weekKey] = 0;
                }

                $result['suppliers'][$typeLabel][$supplierName]['weeks'][$weekKey] += $amount;
                $result['suppliers'][$typeLabel][$supplierName]['total'] += $amount;
                $result['suppliers'][$typeLabel]['total'][$weekKey] += $amount;
            }
        }
    }

    /**
     * Letters of Credit that settled an invoice matched via
     * po_allocations. Same reasoning as applyPoAllocatedMoneyPayments()
     * above — checks both settlement_allocations and payment_settlements
     * and sums both, since which one holds the real link depends on
     * which screen recorded the settlement.
     */
    private static function applyPoAllocatedLetterOfCreditSettlements(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        Collection $invoiceIds,
        float $allocationPercentage,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
    ): void {
        $rows = DB::table('settlement_allocations')
            ->join('letter_of_credit_issuances', 'settlement_allocations.letter_of_credit_issuance_id', '=', 'letter_of_credit_issuances.id')
            ->join('partners', 'partners.id', '=', 'letter_of_credit_issuances.partner_id')
            ->whereIn('settlement_allocations.invoice_id', $invoiceIds)
            ->where('letter_of_credit_issuances.company_id', $companyId)
            ->whereBetween('letter_of_credit_issuances.due_date', [$periodStart, $periodEnd])
            ->selectRaw('settlement_allocations.allocation_amount as allocation_amount, settlement_allocations.invoice_id, letter_of_credit_issuances.payment_currency, letter_of_credit_issuances.payment_date, letter_of_credit_issuances.due_date, partners.name as supplier_name')
            ->get()
            ->concat(
                DB::table('payment_settlements')
                    ->join('letter_of_credit_issuances', 'payment_settlements.letter_of_credit_issuance_id', '=', 'letter_of_credit_issuances.id')
                    ->join('partners', 'partners.id', '=', 'letter_of_credit_issuances.partner_id')
                    ->whereIn('payment_settlements.invoice_id', $invoiceIds)
                    ->where('letter_of_credit_issuances.company_id', $companyId)
                    ->whereBetween('letter_of_credit_issuances.due_date', [$periodStart, $periodEnd])
                    ->selectRaw('payment_settlements.settlement_amount as allocation_amount, payment_settlements.invoice_id, letter_of_credit_issuances.payment_currency, letter_of_credit_issuances.payment_date, letter_of_credit_issuances.due_date, partners.name as supplier_name')
                    ->get()
            );

        if ($rows->isEmpty()) {
            return;
        }

        $invoiceIdsForNumbers = $rows->pluck('invoice_id')->filter()->unique()->values();
        $invoicesById = $invoiceIdsForNumbers->isNotEmpty()
            ? SupplierInvoice::whereIn('id', $invoiceIdsForNumbers->all())->get()->keyBy('id')
            : collect();

        foreach ($rows as $row) {
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

            $invoiceNumber = '';
            if ($row->invoice_id && $invoicesById->has($row->invoice_id)) {
                $invoiceNumber = $invoicesById->get($row->invoice_id)->getInvoiceNumber();
            }

            $lcKey = __('Letter Of Credit').' - '.__('Invoice No').' '.$invoiceNumber;
            $supplierName = (string) $row->supplier_name;
            $amount = (float) $row->allocation_amount * $exchangeRate * $allocationPercentage;

            if (! isset($result['suppliers'][$lcKey][$supplierName])) {
                $result['suppliers'][$lcKey][$supplierName] = ['weeks' => [], 'total' => 0];
            }
            if (! isset($result['suppliers'][$lcKey][$supplierName]['weeks'][$weekKey])) {
                $result['suppliers'][$lcKey][$supplierName]['weeks'][$weekKey] = 0;
            }
            if (! isset($result['suppliers'][$lcKey]['total'][$weekKey])) {
                $result['suppliers'][$lcKey]['total'][$weekKey] = 0;
            }

            $result['suppliers'][$lcKey][$supplierName]['weeks'][$weekKey] += $amount;
            $result['suppliers'][$lcKey][$supplierName]['total'] += $amount;
            $result['suppliers'][$lcKey]['total'][$weekKey] += $amount;
        }
    }

    private static function applyMoneyPaymentMovements(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        int $contractId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
    ): void {
        self::applyContractMoneyPaymentByType($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $contractId, $periodStart, $periodEnd, $periodsByWeekKey, MoneyPayment::OUTGOING_TRANSFER, null);
        self::applyContractMoneyPaymentByType($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $contractId, $periodStart, $periodEnd, $periodsByWeekKey, MoneyPayment::CASH_PAYMENT, null);
        self::applyContractMoneyPaymentByType($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $contractId, $periodStart, $periodEnd, $periodsByWeekKey, MoneyPayment::PAYABLE_CHEQUE, PayableCheque::PAID);
        self::applyContractMoneyPaymentByType($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $contractId, $periodStart, $periodEnd, $periodsByWeekKey, MoneyPayment::PAYABLE_CHEQUE, PayableCheque::PENDING);
    }

    private static function applyContractMoneyPaymentByType(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        int $contractId,
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
            ->join('settlement_allocations', 'money_payments.id', '=', 'settlement_allocations.money_payment_id')
            ->where('money_payments.company_id', $companyId)
            ->where('money_payments.type', $moneyType)
            ->where('settlement_allocations.contract_id', $contractId);

        if ($chequeStatus !== null) {
            $query->join('payable_cheques', 'payable_cheques.money_payment_id', '=', 'money_payments.id')
                ->where('payable_cheques.status', $chequeStatus);
            $dateField = $chequeStatus === PayableCheque::PAID ? 'payable_cheques.actual_payment_date' : 'payable_cheques.due_date';
        } else {
            $dateField = 'money_payments.delivery_date';
        }

        $query->whereBetween($dateField, [$periodStart, $periodEnd])
            ->selectRaw('settlement_allocations.allocation_amount as paid_amount, money_payments.payment_currency, '.$dateField.' as movement_date, partners.name as supplier_name');

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
            $supplierName = (string) $row->supplier_name;

            if (! isset($result['suppliers'][$typeLabel][$supplierName])) {
                $result['suppliers'][$typeLabel][$supplierName] = ['weeks' => [], 'total' => 0];
            }
            if (! isset($result['suppliers'][$typeLabel][$supplierName]['weeks'][$weekKey])) {
                $result['suppliers'][$typeLabel][$supplierName]['weeks'][$weekKey] = 0;
            }
            if (! isset($result['suppliers'][$typeLabel]['total'][$weekKey])) {
                $result['suppliers'][$typeLabel]['total'][$weekKey] = 0;
            }

            $result['suppliers'][$typeLabel][$supplierName]['weeks'][$weekKey] += (float) $row->paid_amount;
            $result['suppliers'][$typeLabel][$supplierName]['total'] += (float) $row->paid_amount;
            $result['suppliers'][$typeLabel]['total'][$weekKey] += $amount;
        }
    }

    private static function applyLetterOfGuaranteeMovements(
        array &$result,
        array &$letterOfGuaranteeModelData,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        int $contractId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
    ): void {
        $lgsTypes = LgTypes::getAll();
        $mainType = 'cash_expenses';
        $subTypeFees = __('LGs Commission & Fees');
        $subTypeCover = __('Cancelled LGs Cash Cover');
        $totalCashInFlowKey = __('Total Cash Inflow');
        $inflowMainType = 'customers';

        $feeRows = DB::table('current_account_bank_statements')
            ->join('financial_institution_accounts', 'financial_institution_accounts.id', '=', 'current_account_bank_statements.financial_institution_account_id')
            ->join('letter_of_guarantee_issuances', 'letter_of_guarantee_issuances.id', '=', 'current_account_bank_statements.letter_of_guarantee_issuance_id')
            ->where('current_account_bank_statements.company_id', $companyId)
            ->whereBetween('current_account_bank_statements.date', [$periodStart, $periodEnd])
            ->where('current_account_bank_statements.letter_of_guarantee_issuance_id', '>', 0)
            ->where('letter_of_guarantee_issuances.contract_id', $contractId)
            ->where(function ($q) {
                $q->where('is_renewal_fees', 1)->orWhere('is_commission_fees', 1)->orWhere('is_issuance_fees', 1);
            })
            ->selectRaw('letter_of_guarantee_issuances.lg_type as lg_type, credit as paid_amount, financial_institution_accounts.currency as currency, current_account_bank_statements.date as movement_date')
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

            if (! isset($result[$mainType][$subTypeFees][$lgType]['weeks'][$weekKey])) {
                $result[$mainType][$subTypeFees][$lgType]['weeks'][$weekKey] = 0;
            }
            $result[$mainType][$subTypeFees][$lgType]['weeks'][$weekKey] += $amount;
            $result[$mainType][$subTypeFees][$lgType]['total'] = ($result[$mainType][$subTypeFees][$lgType]['total'] ?? 0) + $amount;
            $result[$mainType][$subTypeFees]['total'][$weekKey] = ($result[$mainType][$subTypeFees]['total'][$weekKey] ?? 0) + $amount;
        }

        // ⚠️ Bug fix: this used to be a single, unfiltered query that read
        // the 'debit' column (money being LOCKED AWAY at issuance — an
        // outflow) but mislabeled it 'Cancelled LGs Cash Cover' and placed
        // it in the Cash IN section. It also never captured genuine
        // cancellation refunds (the 'credit' column, type=for-cancellation)
        // at all. Split into the two correct rows below, matching the
        // already-correct Company Cash Flow logic
        // (CashFlowCompanyPeriodBatchLoader::applyLetterOfGuaranteeMovements).

        // "Cancelled LGs Cash Cover" (Cash In) — money returned when an LG
        // is cancelled. Per explicit product decision, this includes LGs of
        // EITHER issuance type (New Issuance and Opening Balance) — no
        // category_name filter here, unlike the Issued row below.
        $cancelledCoverQuery = DB::table('letter_of_guarantee_cash_cover_statements')
            ->join('letter_of_guarantee_issuances', 'letter_of_guarantee_issuances.id', '=', 'letter_of_guarantee_cash_cover_statements.letter_of_guarantee_issuance_id')
            ->join('partners', 'partners.id', '=', 'letter_of_guarantee_issuances.partner_id')
            ->where('letter_of_guarantee_cash_cover_statements.company_id', $companyId)
            ->where('letter_of_guarantee_cash_cover_statements.type', LetterOfGuaranteeIssuance::FOR_CANCELLATION)
            ->where('letter_of_guarantee_issuances.contract_id', $contractId);
        $cancelledCoverQuery = LgCashCoverEffectiveDate::joinTo($cancelledCoverQuery);
        $effectiveDateSql = LgCashCoverEffectiveDate::sql();
        $cancelledCoverRows = $cancelledCoverQuery
            ->whereBetween(DB::raw($effectiveDateSql), [$periodStart, $periodEnd])
            ->where('letter_of_guarantee_cash_cover_statements.letter_of_guarantee_issuance_id', '>', 0)
            ->selectRaw('letter_of_guarantee_issuances.lg_type as lg_type, letter_of_guarantee_cash_cover_statements.credit as total_amount, letter_of_guarantee_cash_cover_statements.currency as currency, '.$effectiveDateSql.' as movement_date, partners.name as partner_name, letter_of_guarantee_issuances.lg_code as lg_code')
            ->get();

        foreach ($cancelledCoverRows as $row) {
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

            if (! isset($result[$inflowMainType][$subTypeCover][$lgType]['weeks'][$weekKey])) {
                $result[$inflowMainType][$subTypeCover][$lgType]['weeks'][$weekKey] = 0;
            }
            $result[$inflowMainType][$subTypeCover][$lgType]['weeks'][$weekKey] += $amount;
            $result[$inflowMainType][$subTypeCover][$lgType]['total'] = ($result[$inflowMainType][$subTypeCover][$lgType]['total'] ?? 0) + $amount;
            $result[$inflowMainType][$subTypeCover]['total'][$weekKey] = ($result[$inflowMainType][$subTypeCover]['total'][$weekKey] ?? 0) + $amount;
            $result['customers'][$totalCashInFlowKey]['total'][$weekKey] = ($result['customers'][$totalCashInFlowKey]['total'][$weekKey] ?? 0) + $amount;

            $letterOfGuaranteeModelData[$subTypeCover][$lgType]['weeks'][$weekKey][] = [
                'amount' => $amount,
                'lg_code' => $row->lg_code,
                'name' => $row->partner_name,
            ];
        }

        // "Issued LG Cash Cover" (Cash Out) — money LOCKED AWAY when an LG
        // is issued. Restricted to category_name = New Issuance only,
        // excluding Opening Balance, per explicit product decision — this
        // is the row previously missing from the Contract Cash Flow report
        // entirely.
        //
        // The cover a renewal re-prices moves on this same line — a NET
        // movement (debit - credit), so raising it funds more and lowering
        // it releases some back. Issuance rows carry credit = 0 and are
        // unaffected. Same treatment as the company-level loader.
        // @see \App\Support\LetterOfGuarantee\LgRenewalTerms
        $subTypeIssued = __('Issued LG Cash Cover');
        $issuedCoverRows = DB::table('letter_of_guarantee_cash_cover_statements')
            ->join('letter_of_guarantee_issuances', 'letter_of_guarantee_issuances.id', '=', 'letter_of_guarantee_cash_cover_statements.letter_of_guarantee_issuance_id')
            ->join('partners', 'partners.id', '=', 'letter_of_guarantee_issuances.partner_id')
            ->where('letter_of_guarantee_cash_cover_statements.company_id', $companyId)
            ->whereIn('letter_of_guarantee_cash_cover_statements.type', ['debit-lg-amount', LgRenewalTerms::CASH_COVER_TYPE])
            ->where('letter_of_guarantee_issuances.contract_id', $contractId)
            ->where('letter_of_guarantee_issuances.category_name', LetterOfGuaranteeIssuance::NEW_ISSUANCE)
            ->where('letter_of_guarantee_cash_cover_statements.letter_of_guarantee_issuance_id', '>', 0)
            ->whereBetween('letter_of_guarantee_cash_cover_statements.date', [$periodStart, $periodEnd])
            ->selectRaw('letter_of_guarantee_issuances.lg_type as lg_type, (letter_of_guarantee_cash_cover_statements.debit - letter_of_guarantee_cash_cover_statements.credit) as total_amount, letter_of_guarantee_cash_cover_statements.currency as currency, letter_of_guarantee_cash_cover_statements.date as movement_date, partners.name as partner_name, letter_of_guarantee_issuances.lg_code as lg_code')
            ->get();

        foreach ($issuedCoverRows as $row) {
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

            if (! isset($result[$mainType][$subTypeIssued][$lgType]['weeks'][$weekKey])) {
                $result[$mainType][$subTypeIssued][$lgType]['weeks'][$weekKey] = 0;
            }
            $result[$mainType][$subTypeIssued][$lgType]['weeks'][$weekKey] += $amount;
            $result[$mainType][$subTypeIssued][$lgType]['total'] = ($result[$mainType][$subTypeIssued][$lgType]['total'] ?? 0) + $amount;
            $result[$mainType][$subTypeIssued]['total'][$weekKey] = ($result[$mainType][$subTypeIssued]['total'][$weekKey] ?? 0) + $amount;

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
            ->join('financial_institution_accounts', 'financial_institution_accounts.id', '=', 'current_account_bank_statements.financial_institution_account_id')
            ->join('letter_of_credit_issuances', 'letter_of_credit_issuances.id', '=', 'current_account_bank_statements.letter_of_credit_issuance_id')
            ->where('current_account_bank_statements.company_id', $companyId)
            ->whereBetween('current_account_bank_statements.date', [$periodStart, $periodEnd])
            ->where('current_account_bank_statements.letter_of_credit_issuance_id', '>', 0)
            ->where(function ($q) {
                $q->where('is_renewal_fees', 1)->orWhere('is_commission_fees', 1)->orWhere('is_issuance_fees', 1);
            })
            ->selectRaw('letter_of_credit_issuances.lc_type as lc_type, credit as paid_amount, financial_institution_accounts.currency as currency, current_account_bank_statements.date as movement_date')
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

            if (! isset($result[$mainType][$subTypeFees][$lcType]['weeks'][$weekKey])) {
                $result[$mainType][$subTypeFees][$lcType]['weeks'][$weekKey] = 0;
            }
            $result[$mainType][$subTypeFees][$lcType]['weeks'][$weekKey] += $amount;
            $result[$mainType][$subTypeFees][$lcType]['total'] = ($result[$mainType][$subTypeFees][$lcType]['total'] ?? 0) + $amount;
            $result[$mainType][$subTypeFees]['total'][$weekKey] = ($result[$mainType][$subTypeFees]['total'][$weekKey] ?? 0) + $amount;
        }

        $lcRows = DB::table('letter_of_credit_issuances')
            ->where('letter_of_credit_issuances.company_id', $companyId)
            ->where('status', LetterOfCreditIssuance::RUNNING)
            ->whereBetween('letter_of_credit_issuances.due_date', [$periodStart, $periodEnd])
            ->selectRaw('due_date as movement_date, transaction_name, letter_of_credit_issuances.lc_type as lc_type, (amount_in_main_currency - cash_cover_amount) as paid_amount, lc_cash_cover_currency as currency')
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

            if (! isset($result[$mainType][$subTypeRemaining][$lcType]['weeks'][$weekKey])) {
                $result[$mainType][$subTypeRemaining][$lcType]['weeks'][$weekKey] = 0;
            }
            $result[$mainType][$subTypeRemaining][$lcType]['weeks'][$weekKey] += $amount;
            $result[$mainType][$subTypeRemaining][$lcType]['total'] = ($result[$mainType][$subTypeRemaining][$lcType]['total'] ?? 0) + $amount;
            $result[$mainType][$subTypeRemaining]['total'][$weekKey] = ($result[$mainType][$subTypeRemaining]['total'][$weekKey] ?? 0) + $amount;
        }
    }

    private static function applyCashExpenseMovements(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        int $contractId,
        string $periodStart,
        string $periodEnd,
        array $periodsByWeekKey,
    ): void {
        self::applyContractCashExpenseType($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $contractId, $periodStart, $periodEnd, $periodsByWeekKey, CashExpense::OUTGOING_TRANSFER, 'payment_date', null);
        self::applyContractCashExpenseType($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $contractId, $periodStart, $periodEnd, $periodsByWeekKey, CashExpense::CASH_PAYMENT, 'payment_date', null);
        self::applyContractCashExpenseType($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $contractId, $periodStart, $periodEnd, $periodsByWeekKey, CashExpense::PAYABLE_CHEQUE, 'actual_payment_date', PayableCheque::PAID);
        self::applyContractCashExpenseType($result, $foreignExchangeRates, $mainFunctionalCurrency, $companyId, $contractId, $periodStart, $periodEnd, $periodsByWeekKey, CashExpense::PAYABLE_CHEQUE, 'due_date', PayableCheque::PENDING);
    }

    private static function applyContractCashExpenseType(
        array &$result,
        Collection $foreignExchangeRates,
        string $mainFunctionalCurrency,
        int $companyId,
        int $contractId,
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
            ->join('cash_expense_contract', 'cash_expense_contract.cash_expense_id', '=', 'cash_expenses.id')
            ->where($subTable.'.type', $moneyType)
            ->where($subTable.'.company_id', $companyId)
            ->where('cash_expense_contract.contract_id', $contractId)
            ->when($chequeStatus !== null, function ($builder) use ($chequeStatus, $mainTable) {
                $builder->where($mainTable.'.status', $chequeStatus);
            })
            ->whereBetween($dateColumn, [$periodStart, $periodEnd])
            ->selectRaw('cash_expense_categories.name as category_name, cash_expense_category_names.name as expense_name, cash_expense_contract.amount as paid_amount, '.$subTable.'.currency as currency, '.$dateColumn.' as movement_date');

        foreach ($query->cursor() as $row) {
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

            if (! isset($result['cash_expenses'][$categoryName][$expenseName]['weeks'][$weekKey])) {
                $result['cash_expenses'][$categoryName][$expenseName]['weeks'][$weekKey] = 0;
            }
            $result['cash_expenses'][$categoryName][$expenseName]['weeks'][$weekKey] += $amount;
            $result['cash_expenses'][$categoryName][$expenseName]['total'] = ($result['cash_expenses'][$categoryName][$expenseName]['total'] ?? 0) + $amount;
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

    private static function settlementAmountInReceivingCurrencySql(): string
    {
        return 'CASE
            WHEN money_received.currency IS NULL
                OR money_received.currency = money_received.receiving_currency
            THEN settlements.settlement_amount
            ELSE settlements.settlement_amount * money_received.exchange_rate
        END';
    }

    private static function qualifiedCashExpenseDateColumn(string $moneyType, string $dateField): string
    {
        if ($moneyType === CashExpense::PAYABLE_CHEQUE) {
            return 'payable_cheques.'.$dateField;
        }

        return 'cash_expenses.'.$dateField;
    }
}
