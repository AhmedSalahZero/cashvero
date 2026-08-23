<?php

namespace App\Traits\Models;

use App\Helpers\HArr;
use App\Models\Contract;
use App\Models\ForeignExchangeRate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shared "Forecasted Project Collection" (customer side) / "Forecasted
 * Suppliers Contract Payments" (supplier side) calculation.
 *
 * Formula per order (confirmed with project owner, 2026-08):
 *
 *   Forecast = Order Amount − Unused Down Payment Balance
 *              − Σ(each linked invoice's own net_balance)
 *
 * Both halves are already settlement-aware at the data level, so no
 * special-case branching is needed here for "invoice fully/partially
 * settled by down payment":
 *   - "Unused Down Payment Balance" reads down_payment_balance — NOT
 *     the original down_payment_amount — which is the portion of a
 *     down payment still not applied to any invoice.
 *   - Each invoice's own net_balance already reflects any down
 *     payment settlement applied against IT specifically (see
 *     DownPaymentContractsController / IsInvoice::calculateNetBalanceInEditMode).
 *     A fully down-payment-settled invoice has net_balance = 0 and so
 *     contributes nothing here (avoiding double-counting against the
 *     down payment deduction above); a partially-settled invoice
 *     contributes only its remaining balance.
 *
 * Previously this exact formula was duplicated once in CustomerInvoice
 * and once in SupplierInvoice, both with the same bug: the down
 * payment/invoice subtraction was reversed (`down_payment - invoiced`
 * instead of the other way around), which — combined with then
 * subtracting THAT from the order amount — silently turned a
 * subtraction into an addition, and both used gross amounts instead
 * of their settlement-aware balances. Consolidated here so a future
 * fix only has to happen once.
 *
 * ── Supplier side / PO allocations (added 2026-08) ──────────────────
 * A Purchase Order belongs to the SUPPLIER's own contract, never to a
 * Customer contract directly. So when $contractId is a Customer
 * contract (as it always is on the single Contract Cash Flow report),
 * "find POs directly owned by this contract" finds nothing — the only
 * link is the po_allocations table (Customer contract -> allocated PO
 * -> that PO's real Supplier contract), the same table the "Suppliers
 * Invoices" row already relies on. When $poAllocations is passed
 * (supplier side only), each allocated PO is ALSO included here,
 * weighted by its allocation_percentage — on top of, not instead of,
 * any PO the contract directly owns (relevant when $contractId is
 * itself a genuine Supplier contract, e.g. picked directly on the
 * Consolidated Cash Flow report), so no existing behavior changes.
 */
trait HasForecastedProjectCollection
{
    /**
     * @param  array<string,mixed>  $config  {
     *   main_result_type: 'customers'|'suppliers',
     *   result_key: string,                  // row label, e.g. 'Forecasted Project Collection'
     *   invoice_table: string,                // 'customer_invoices' | 'supplier_invoices'
     *   order_relation: string,               // Contract relation name: 'salesOrders' | 'purchasesOrders'
     *   order_number_key: string,             // key inside the order's own array: 'so_number' | 'po_number'
     *   invoice_order_number_column: string,  // matching column on the invoice table: 'sales_order_number' | 'purchases_order_number'
     *   down_payment_table: string,           // 'down_payment_settlements' | 'down_payment_money_payment_settlements'
     *   down_payment_order_id_column: string, // 'sales_order_id' | 'purchase_order_id'
     *   add_to_cash_inflow_total: bool,       // true for customer (cash IN); false for supplier (cash OUT, not part of inflow total)
     *   paid_or_collected_status: string,      // invoice_status value meaning "fully settled": SupplierInvoice::COLLETED_OR_PAID | CustomerInvoice::COLLETED_OR_PAID
     * }
     * @param  Collection|null  $poAllocations  Supplier side only — PoAllocation rows (each already
     *                                          joined to its purchase_orders + contracts row, so it
     *                                          carries the PO's own columns plus allocation_percentage
     *                                          and the supplier contract's code) linking a Customer
     *                                          contract to Purchase Orders on a different, Supplier
     *                                          contract. Null/omitted on the customer side.
     */
    protected static function computeForecastedProjectCollection(
        array &$result,
        string $startDate,
        string $endDate,
        $currency,
        $companyId,
        array $datesWithWeekNumber,
        ?int $contractId,
        $foreignExchangeRates,
        ?string $mainFunctionalCurrency,
        array $config,
        ?Collection $poAllocations = null
    ): void {
        $mainResultType = $config['main_result_type'];
        $resultKey = $config['result_key'];
        $invoiceTable = $config['invoice_table'];
        $orderRelation = $config['order_relation'];
        $orderNumberKey = $config['order_number_key'];
        $invoiceOrderNumberColumn = $config['invoice_order_number_column'];
        $downPaymentTable = $config['down_payment_table'];
        $downPaymentOrderIdColumn = $config['down_payment_order_id_column'];
        $addToCashInflowTotal = $config['add_to_cash_inflow_total'];
        $paidOrCollectedStatus = $config['paid_or_collected_status'];

        // Company-wide + main functional currency tab -> include contracts in
        // every currency (each gets converted below via its own currency's
        // FX rate). Any specific foreign-currency tab, or a single contract,
        // keeps the original same-currency-only filter.
        $showAllCurrenciesConverted = ! $contractId && $mainFunctionalCurrency !== null && $currency === $mainFunctionalCurrency;

        $contracts = Contract::where('company_id', $companyId)
            ->where('end_date', '<=', $endDate)
            ->when(! $showAllCurrenciesConverted, function ($query) use ($currency) {
                $query->where('currency', $currency);
            })
            ->when($contractId, function ($query) use ($contractId) {
                $query->where('id', $contractId);
            })
            ->with($orderRelation)
            ->get();

        // ── Orders the contract directly owns (customer side always;
        // supplier side only when $contractId happens to be that
        // Supplier's own contract — see class docblock). ─────────────
        foreach ($contracts as $contract) {
            foreach ($contract->{$orderRelation} as $order) {
                $orderArr = HArr::getLatestNonZeroExecutionKeys($order->toArray());
                if (empty($orderArr['end_date'])) {
                    continue;
                }

                self::applyForecastedOrderBalance(
                    $result, $orderArr, $contract, $order->id, $contract->id,
                    $startDate, $endDate, $currency, $companyId, $datesWithWeekNumber,
                    $foreignExchangeRates, $mainFunctionalCurrency,
                    $mainResultType, $resultKey, $invoiceTable, $orderNumberKey,
                    $invoiceOrderNumberColumn, $downPaymentTable, $downPaymentOrderIdColumn,
                    $addToCashInflowTotal, $paidOrCollectedStatus, 1.0
                );
            }
        }

        // ── Orders allocated to this (Customer) contract via
        // po_allocations, weighted by allocation_percentage. ──────────
        if ($poAllocations !== null) {
            foreach ($poAllocations as $poAllocation) {
                $orderArr = HArr::getLatestNonZeroExecutionKeys($poAllocation->toArray());
                if (empty($orderArr['end_date'])) {
                    continue;
                }

                // po_allocations links a Customer contract to a PO that
                // belongs to a DIFFERENT (Supplier) contract — fetch that
                // real Supplier contract fresh rather than trying to
                // reuse the joined row's attributes for it.
                $supplierContract = Contract::find($poAllocation->supplier_contract_id);
                if (! $supplierContract) {
                    continue;
                }

                $allocationPercentage = ((float) ($poAllocation->allocation_percentage ?? 0)) / 100;
                if ($allocationPercentage <= 0) {
                    continue;
                }

                self::applyForecastedOrderBalance(
                    $result, $orderArr, $supplierContract, $poAllocation->purchase_order_id, $poAllocation->customer_contract_id,
                    $startDate, $endDate, $currency, $companyId, $datesWithWeekNumber,
                    $foreignExchangeRates, $mainFunctionalCurrency,
                    $mainResultType, $resultKey, $invoiceTable, $orderNumberKey,
                    $invoiceOrderNumberColumn, $downPaymentTable, $downPaymentOrderIdColumn,
                    $addToCashInflowTotal, $paidOrCollectedStatus, $allocationPercentage
                );
            }
        }
    }

    /**
     * One order's (Sales Order / Purchase Order) contribution to the
     * forecast row — shared by both the "directly owned" and the
     * "allocated via po_allocations" paths above.
     */
    private static function applyForecastedOrderBalance(
        array &$result,
        array $orderArr,
        Contract $contract,
        $orderId,
        $downPaymentContractId,
        string $startDate,
        string $endDate,
        $currency,
        $companyId,
        array $datesWithWeekNumber,
        $foreignExchangeRates,
        ?string $mainFunctionalCurrency,
        string $mainResultType,
        string $resultKey,
        string $invoiceTable,
        string $orderNumberKey,
        string $invoiceOrderNumberColumn,
        string $downPaymentTable,
        string $downPaymentOrderIdColumn,
        bool $addToCashInflowTotal,
        string $paidOrCollectedStatus,
        float $weightMultiplier
    ): void {
        $totalCashInFlowKey = __('Total Cash Inflow');

        $orderEndDate = $orderArr['end_date'];
        $orderCollectionDays = $orderArr['collection_days'] ?? 0;
        $currentCollectionDate = Carbon::make($orderEndDate)->addDays($orderCollectionDays);
        if (! $currentCollectionDate->between($startDate, $endDate)) {
            return;
        }

        $currentCollectionDateFormatted = $currentCollectionDate->format('Y-m-d');
        $currentWeekYear = $datesWithWeekNumber[$currentCollectionDateFormatted];
        $orderAmount = $orderArr['amount'];
        $contractCode = $contract->getCode();
        $contractName = $contract->getName();
        $orderNumber = $orderArr[$orderNumberKey];
        $customerName = $contract->getClientName();

        // Sum of each linked invoice's OWN net_balance — already
        // settlement-aware (see trait docblock), so a fully
        // down-payment-settled invoice contributes 0 automatically,
        // and a partially-settled one contributes only what's left.
        // Explicitly excludes fully paid/collected invoices too (not
        // just relying on net_balance reaching 0 for them) — confirmed
        // with the project owner as the safer, authoritative signal.
        $invoicesNetBalance = (float) DB::table($invoiceTable)
            ->where('company_id', $companyId)
            ->where('currency', $contract->getCurrency())
            ->where($invoiceOrderNumberColumn, $orderNumber)
            ->where('contract_code', $contractCode)
            ->where('invoice_status', '!=', $paidOrCollectedStatus)
            ->sum('net_balance');

        // The portion of any down payment NOT yet applied to an
        // invoice — down_payment_balance, not the original
        // down_payment_amount, so it isn't double-counted once
        // it's been used to settle an invoice above.
        $unusedDownPaymentBalance = (float) DB::table($downPaymentTable)
            ->where('company_id', $companyId)
            ->where($downPaymentOrderIdColumn, $orderId)
            ->where('contract_id', $downPaymentContractId)
            ->sum('down_payment_balance');

        $orderNetBalance = $orderAmount - $unusedDownPaymentBalance - $invoicesNetBalance;
        if ($orderNetBalance < 0) {
            $orderNetBalance = 0;
        }

        // * $currency هي عملة التبويب المعروض. لو العقد بنفس العملة يبقى
        // * الرقم لازم يفضل زي ما هو — التحويل للعملة الرئيسية بيحصل بس على
        // * تبويب العملة الرئيسية اللي بيجمّع كل العملات مع بعض.
        $exchangeRate = ForeignExchangeRate::getExchangeRateForDisplayCurrency($contract->getCurrency(), $currency, $mainFunctionalCurrency, $currentCollectionDateFormatted, $companyId, $foreignExchangeRates);
        $orderNetBalance = $orderNetBalance * $exchangeRate * $weightMultiplier;

        $rowLabel = $customerName.'-'.$contractName;
        $result[$mainResultType][$resultKey][$rowLabel]['weeks'][$currentWeekYear] =
            ($result[$mainResultType][$resultKey][$rowLabel]['weeks'][$currentWeekYear] ?? 0) + $orderNetBalance;
        $result[$mainResultType][$resultKey][$rowLabel]['total'] =
            ($result[$mainResultType][$resultKey][$rowLabel]['total'] ?? 0) + $orderNetBalance;
        $result[$mainResultType][$resultKey]['total'][$currentWeekYear] =
            ($result[$mainResultType][$resultKey]['total'][$currentWeekYear] ?? 0) + $orderNetBalance;

        if ($addToCashInflowTotal) {
            $result['customers'][$totalCashInFlowKey]['total'][$currentWeekYear] =
                ($result['customers'][$totalCashInFlowKey]['total'][$currentWeekYear] ?? 0) + $orderNetBalance;
        }
    }
}

