<?php

namespace App\Traits\Models;

use App\Helpers\HArr;
use App\Models\Contract;
use App\Models\ForeignExchangeRate;
use App\Support\Contracts\MonthlyExecutionSchedule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Shared "Forecasted Project Collection" (customer side) / "Forecasted
 * Suppliers Contract Payments" (supplier side) calculation.
 *
 * Formula per order (confirmed with project owner, 2026-09):
 *
 *   Forecast = Total Contract Amount
 *              − Total Invoice Settlement
 *                  (total_collected_amount_in_main_currency
 *                   + total_deductions_in_main_currency
 *                   + net_balance_in_main_currency)
 *              − Down Payment Balance
 *
 * net_balance is in there because the open part of an invoice is
 * already reported by the "Customers Invoices" / "Suppliers Invoices"
 * row; leaving it in the forecast counts the same money twice inside
 * one report.
 *
 * Everything is compared in the MAIN FUNCTIONAL CURRENCY. The invoice
 * side reads the *_in_main_currency columns directly, so nothing is
 * converted twice. The down payment tables carry no main-currency
 * column, so each row converts at the FX rate of the date its cash
 * actually moved (money_received.receiving_date /
 * money_payments.delivery_date) — the same date
 * CustomerInvoice::getDownPaymentsOverContracts() converts at.
 *
 * Because the order amount is stored in the contract's own currency,
 * each phase is converted to the main functional currency BEFORE the
 * deduction is applied to it (the deduction is already in main
 * currency).
 *
 * ── Same as system.veroanalysisb.com (decided 2026-11-11) ───────────
 * A foreign-currency tab (USD, EUR…) shows this row as the MAIN
 * currency equivalent, like every other row of that tab (Cash & Banks
 * Balance, Customers/Suppliers Invoices) and like the source system.
 * For a while cashvero showed it in the contract's own currency
 * instead (getExchangeRateForDisplayCurrency), which left one row of
 * dollars inside a tab of pound equivalents; the client confirmed the
 * source system's behaviour is the intended one.
 *
 * Note this formula subtracts SETTLED amounts, not open balances, so
 * a fully collected/paid invoice must NOT be filtered out —
 * 'paid_or_collected_status' is no longer used for the deduction.
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
 *
 * ── Supplier side / child contracts (contract_scope, added 2026-09) ─
 * po_allocations is the OPTIONAL, explicit way to attach a PO to a
 * Customer contract (the "Allocate" modal on a Purchase Order). The
 * ordinary one is contracts.parent_id: a Supplier contract created
 * under a Customer contract is that contract's child, and its POs are
 * what the Customer contract's "Supplier Contracts" popup lists. With
 * 'contract_scope' => 'supplier_children' the contract query walks
 * that link instead (children of $contractId), which is how
 * SupplierInvoice::getForecastedProjectPayment builds the "Forecasted
 * Project Payment" row. Two notes on that mode:
 *   - Currency is NOT filtered. A Supplier contract routinely bills in
 *     a different currency from the Customer contract it hangs under
 *     (e.g. a EUR supplier under a USD project), and every amount is
 *     converted to the main functional currency further down anyway.
 *   - POs already reachable through po_allocations for this same
 *     Customer contract are skipped ($excludeOrderIds), so a PO that
 *     happens to be linked BOTH ways is counted once, by the
 *     po_allocations row, and never twice.
 *
 * ── Currency selection ──────────────────────────────────────────────
 * $currency accepts either a single currency code or a LIST of codes.
 * The list form is what the Consolidated Cash Flow report passes (it
 * has an explicit multi-currency picker) and always narrows contracts
 * to exactly those currencies. For the single-code form, the
 * company-wide report viewing the MAIN functional currency tab widens
 * to every currency instead — each contract's own amounts are then
 * converted via its own FX rate below — while any specific foreign
 * currency tab, or a single contract, keeps the strict
 * same-currency-only filter.
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
     *   invoice_settled_column: string,        // 'total_collected_amount' | 'total_paid_amount'
     *   invoice_settled_main_column: string,   // 'total_collected_amount_in_main_currency' | 'total_paid_amount_in_main_currency'
     *   down_payment_money_table: string,      // 'money_received' | 'money_payments'
     *   down_payment_money_id_column: string,  // 'money_received_id' | 'money_payment_id'
     *   down_payment_money_date_column: string,// 'receiving_date' | 'delivery_date'
     *   contract_scope?: 'self'|'supplier_children', // default 'self' — see class docblock
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
        $invoiceSettledColumn = $config['invoice_settled_column'];
        $invoiceSettledMainColumn = $config['invoice_settled_main_column'];
        $downPaymentMoneyTable = $config['down_payment_money_table'];
        $downPaymentMoneyIdColumn = $config['down_payment_money_id_column'];
        $downPaymentMoneyDateColumn = $config['down_payment_money_date_column'];
        $contractScope = $config['contract_scope'] ?? 'self';
        $useSupplierChildren = $contractScope === 'supplier_children';

        // 'supplier_children' only means anything relative to a specific
        // Customer contract — without one there is no parent to walk down
        // from, and an unscoped query would sweep in every Supplier
        // contract in the company.
        if ($useSupplierChildren && ! $contractId) {
            return;
        }

        // A PO reachable BOTH as a child contract's PO and through
        // po_allocations must be counted once — the allocation row wins
        // (it carries the allocation_percentage), so skip it here.
        $excludeOrderIds = [];
        if ($useSupplierChildren) {
            $excludeOrderIds = DB::table('po_allocations')
                ->where('contract_id', $contractId)
                ->whereNotNull('purchase_order_id')
                ->pluck('purchase_order_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        // An explicit LIST of currencies (Consolidated Cash Flow's
        // multi-currency picker) always means "exactly these" — the
        // widen-to-everything rule below only applies to the single
        // currency form used by the Company/Contract Cash Flow report.
        $currencyIsExplicitList = is_array($currency);
        $currencyList = $currencyIsExplicitList
            ? array_values(array_filter(array_map('strval', $currency)))
            : [(string) $currency];

        // Company-wide + main functional currency tab -> include contracts in
        // every currency (each gets converted below via its own currency's
        // FX rate). Any specific foreign-currency tab, or a single contract,
        // keeps the original same-currency-only filter.
        $showAllCurrenciesConverted = ! $currencyIsExplicitList
            && ! $contractId
            && $mainFunctionalCurrency !== null
            && $currencyList !== []
            && $currencyList[0] === $mainFunctionalCurrency;

        // Child Supplier contracts bill in their own currency, which is
        // routinely NOT the parent Customer contract's — filtering them
        // by it would silently drop them (everything is converted to the
        // main functional currency further down anyway).
        $filterByCurrency = ! $showAllCurrenciesConverted && ! $useSupplierChildren;

        // * مفيش فلترة بتاريخ العقد خالص.
        // *
        // * كان في شرط على العقد العادي: end_date <= $endDate يعني "هات
        // * العقود اللي بتنتهي جوّه فترة التقرير بس". الشرط ده كان مبني
        // * على إن العقد العادي بينزل دفعة واحدة في تاريخ تحصيل أمره —
        // * و ده بالظبط الباج اللي اتصلّح في applyForecastedOrderBalance:
        // * دلوقتي كل مرحلة تنفيذ بتنزل في بُكِتها. فعقد ماشي لحد يوليو
        // * 2027 و مراحله بتتحصّل في أكتوبر و نوفمبر و ديسمبر 2026 كان
        // * بيتشال بالكامل و صفّه يطلع فاضي.
        // *
        // * و مافيش عمود تاريخ في جدول العقود ينفع يقوم مقام الشرط الصح
        // * أصلاً: مواعيد المراحل متخزّنة على الأمر نفسه
        // * (sales_orders/purchase_orders.end_date_N + collection_days_N)
        // * مش على العقد.
        // *
        // * الفلترة الحقيقية بتحصل في مكانها الصح على مستوى الشريحة:
        // *   - العقد العادي: كل مرحلة بتتشيّك على [$startDate, $endDate]
        // *     في applyForecastedOrderBalance()
        // *   - العقد الشهري: كل شريحة بتتشيّك على $datesWithWeekNumber
        // *     في applyMonthlyExecutedContractBalance()
        // * و الأمر أو العقد اللي مالوش أي حاجة جوّه الفترة ما بيكتبش صف
        // * من أصله. فالشرط ده كان تصفية مسبقة خشنة — و غلط. و شيله
        // * ما بيكلّفش حاجة: النظام كله فيه أقل من 200 عقد.
        $contracts = Contract::where('company_id', $companyId)
            ->when($filterByCurrency, function ($query) use ($currencyList) {
                count($currencyList) === 1
                    ? $query->where('currency', $currencyList[0])
                    : $query->whereIn('currency', $currencyList);
            })
            ->when($contractId, function ($query) use ($contractId, $useSupplierChildren) {
                $useSupplierChildren
                    ? $query->where('parent_id', $contractId)->where('model_type', 'Supplier')
                    : $query->where('id', $contractId);
            })
            ->with($orderRelation)
            ->get();

        // ── Orders the contract directly owns (customer side always;
        // supplier side only when $contractId happens to be that
        // Supplier's own contract — see class docblock). ─────────────
        foreach ($contracts as $contract) {
            // * العقد بتنفيذ شهري ماعندهوش أوامر أصلاً (الجزئية دي مخفية
            // * في الفورم) — المتبقي منه بيتوزّع على شهوره الجايّة.
            if ($contract->isMonthlyExecuted()) {
                self::applyMonthlyExecutedContractBalance(
                    $result, $contract, $startDate, $endDate, $currency, $companyId,
                    $datesWithWeekNumber, $foreignExchangeRates, $mainFunctionalCurrency,
                    $mainResultType, $resultKey, $invoiceTable, $downPaymentTable,
                    $addToCashInflowTotal, $paidOrCollectedStatus, $invoiceSettledColumn
                );
                continue;
            }

            foreach ($contract->{$orderRelation} as $order) {
                if (in_array((int) $order->id, $excludeOrderIds, true)) {
                    continue;
                }

                $phases = HArr::getNonZeroExecutionPhases($order->toArray());
                if (! $phases) {
                    continue;
                }

                self::applyForecastedOrderBalance(
                    $result, $phases, $contract, $order->id, $contract->id,
                    $startDate, $endDate, $currency, $companyId, $datesWithWeekNumber,
                    $foreignExchangeRates, $mainFunctionalCurrency,
                    $mainResultType, $resultKey, $invoiceTable, $orderNumberKey,
                    $invoiceOrderNumberColumn, $downPaymentTable, $downPaymentOrderIdColumn,
                    $addToCashInflowTotal, $paidOrCollectedStatus, 1.0,
                    $invoiceSettledMainColumn, $downPaymentMoneyTable,
                    $downPaymentMoneyIdColumn, $downPaymentMoneyDateColumn
                );
            }
        }

        // ── Orders allocated to this (Customer) contract via
        // po_allocations, weighted by allocation_percentage. ──────────
        if ($poAllocations !== null) {
            foreach ($poAllocations as $poAllocation) {
                $phases = HArr::getNonZeroExecutionPhases($poAllocation->toArray());
                if (! $phases) {
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
                    $result, $phases, $supplierContract, $poAllocation->purchase_order_id, $poAllocation->customer_contract_id,
                    $startDate, $endDate, $currency, $companyId, $datesWithWeekNumber,
                    $foreignExchangeRates, $mainFunctionalCurrency,
                    $mainResultType, $resultKey, $invoiceTable, $orderNumberKey,
                    $invoiceOrderNumberColumn, $downPaymentTable, $downPaymentOrderIdColumn,
                    $addToCashInflowTotal, $paidOrCollectedStatus, $allocationPercentage,
                    $invoiceSettledMainColumn, $downPaymentMoneyTable,
                    $downPaymentMoneyIdColumn, $downPaymentMoneyDateColumn
                );
            }
        }
    }

    /**
     * One order's (Sales Order / Purchase Order) contribution to the
     * forecast row — shared by both the "directly owned" and the
     * "allocated via po_allocations" paths above.
     *
     * ── Per-execution-phase split (fixed 2026-09) ───────────────────
     * $phases is every non-zero execution phase of the order, oldest
     * end_date first (HArr::getNonZeroExecutionPhases). Each phase is
     * invoiced at its own end_date and collected collection_days
     * later, so each lands in its OWN period bucket, carrying its own
     * share of the order amount.
     *
     * Before this, only the phase with the furthest end_date was read
     * and the WHOLE order amount was dropped into that single bucket —
     * a 1,000,000 contract split 50/20/30 across Sep/Oct/Nov showed as
     * 1,000,000 in December instead of 500,000 / 200,000 / 300,000
     * across October / November / December.
     *
     * This is what made an ordinary contract "pay out on one date",
     * the premise the monthly-executed contract (added later) was
     * built to work around. An ordinary contract now pays out across
     * its phases too; the monthly one still differs in that it has no
     * orders at all and spreads its remainder evenly over its months.
     *
     * ── Where the deduction lands (confirmed with project owner) ────
     * "Unused down payment + open invoices" is one number for the
     * whole order, but the forecast is now several buckets, so it has
     * to be spent somewhere. It eats the phases OLDEST FIRST: money
     * already received, or already invoiced, covers the phases that
     * have actually been executed, and only what is left over stays in
     * the later phases at their own dates.
     *
     * The deduction walk runs over EVERY phase in order — including
     * phases whose collection date falls outside the report window —
     * before the window check, otherwise an early out-of-window phase
     * would keep its share of the deduction unspent and the in-window
     * phases would be over-deducted.
     */
    private static function applyForecastedOrderBalance(
        array &$result,
        array $phases,
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
        float $weightMultiplier,
        string $invoiceSettledMainColumn,
        string $downPaymentMoneyTable,
        string $downPaymentMoneyIdColumn,
        string $downPaymentMoneyDateColumn
    ): void {
        $totalCashInFlowKey = __('Total Cash Inflow');

        // Order-level columns (amount, so_number/po_number) are copied
        // onto every phase, so any phase answers for the whole order.
        $orderAmount = (float) $phases[0]['amount'];
        $orderNumber = $phases[0][$orderNumberKey];

        $contractCode = $contract->getCode();
        $contractName = $contract->getName();
        $customerName = $contract->getClientName();

        // كل ما اتفوتر على الأمر ده و اتحسب في مكان تاني ، بالعملة
        // الوظيفية مباشرة من أعمدة _in_main_currency :
        //   - المسدّد فعلا (اتحصّل/اتدفع) + الخصومات : فلوس خلصت
        //   - net_balance : الرصيد المفتوح ، و ده ظاهر اصلا في صف
        //     Customers Invoices / Suppliers Invoices ، فلو مااتطرحش
        //     هنا بيتحسب مرتين في نفس التقرير
        // مفيش فلتر على invoice_status هنا : المعادلة بتجمع المفوتر
        // مش المتبقي بس ، فالفاتورة المحصّلة بالكامل لازم تدخل .
        $invoicesSettlement = (float) DB::table($invoiceTable)
            ->where('company_id', $companyId)
            ->where('currency', $contract->getCurrency())
            ->where($invoiceOrderNumberColumn, $orderNumber)
            ->where('contract_code', $contractCode)
            ->sum(DB::raw(
                'ifnull('.$invoiceSettledMainColumn.', 0)'
                .' + ifnull(total_deductions_in_main_currency, 0)'
                .' + ifnull(net_balance_in_main_currency, 0)'
            ));

        // Down Payment Balance — الرصيد اللي لسه ما اتصرفش على فاتورة
        // بس ، مش الدفعة كلها : الجزء اللي اتصرف موجود اصلا جوه
        // total_collected_amount فوق (تريجر insert_total_collected_amount
        // بيجمع كل التسويات سواء is_from_down_payment بصفر او واحد) ،
        // فطرح الدفعة كلها كان بيطرحه مرتين . الجدولين مفيهمش عمود
        // بالعملة الوظيفية ، فكل صف بيتحوّل بسعر تاريخ حركة الفلوس .
        $downPaymentRows = DB::table($downPaymentTable.' as dp')
            ->leftJoin($downPaymentMoneyTable.' as m', 'm.id', '=', 'dp.'.$downPaymentMoneyIdColumn)
            ->where('dp.company_id', $companyId)
            ->where('dp.'.$downPaymentOrderIdColumn, $orderId)
            ->where('dp.contract_id', $downPaymentContractId)
            ->select([
                'dp.down_payment_balance',
                'dp.currency',
                'm.'.$downPaymentMoneyDateColumn.' as money_date',
            ])
            ->get();

        $downPaymentDeduction = 0.0;
        foreach ($downPaymentRows as $downPaymentRow) {
            $downPaymentCurrency = $downPaymentRow->currency ?: $contract->getCurrency();
            $downPaymentDate = $downPaymentRow->money_date ?: $startDate;
            $downPaymentRate = ForeignExchangeRate::getExchangeRateAtOrOne(
                $downPaymentCurrency, $mainFunctionalCurrency, $downPaymentDate, $companyId, $foreignExchangeRates
            );

            $downPaymentDeduction += (float) $downPaymentRow->down_payment_balance * $downPaymentRate;
        }

        $remainingDeduction = $invoicesSettlement + $downPaymentDeduction;
        $rowLabel = $customerName.'-'.$contractName;

        foreach ($phases as $phase) {
            $currentCollectionDate = Carbon::make($phase['end_date'])
                ->addDays((int) $phase['collection_days']);
            $currentCollectionDateFormatted = $currentCollectionDate->format('Y-m-d');

            // Each phase converts at the rate of ITS own collection
            // date, not one rate for the whole order. The conversion
            // happens BEFORE the deduction, because the deduction is
            // already in the main functional currency.
            $exchangeRate = ForeignExchangeRate::getExchangeRateAtOrOne($contract->getCurrency(), $mainFunctionalCurrency, $currentCollectionDateFormatted, $companyId, $foreignExchangeRates);
            $phaseAmount = $orderAmount * $phase['share'] * $exchangeRate;

            // min() is what keeps the forecast from going negative and
            // carries any excess on to the next phase.
            $deducted = min($remainingDeduction, $phaseAmount);
            $remainingDeduction -= $deducted;
            $phaseNetBalance = ($phaseAmount - $deducted) * $weightMultiplier;

            if (! $currentCollectionDate->between($startDate, $endDate)) {
                continue;
            }

            if (! isset($datesWithWeekNumber[$currentCollectionDateFormatted])) {
                continue;
            }
            $currentWeekYear = $datesWithWeekNumber[$currentCollectionDateFormatted];

            $result[$mainResultType][$resultKey][$rowLabel]['weeks'][$currentWeekYear] =
                ($result[$mainResultType][$resultKey][$rowLabel]['weeks'][$currentWeekYear] ?? 0) + $phaseNetBalance;
            $result[$mainResultType][$resultKey][$rowLabel]['total'] =
                ($result[$mainResultType][$resultKey][$rowLabel]['total'] ?? 0) + $phaseNetBalance;
            $result[$mainResultType][$resultKey]['total'][$currentWeekYear] =
                ($result[$mainResultType][$resultKey]['total'][$currentWeekYear] ?? 0) + $phaseNetBalance;

            if ($addToCashInflowTotal) {
                $result['customers'][$totalCashInFlowKey]['total'][$currentWeekYear] =
                    ($result['customers'][$totalCashInFlowKey]['total'][$currentWeekYear] ?? 0) + $phaseNetBalance;
            }
        }
    }

    /**
     * * مساهمة عقد بتنفيذ شهري في صف التوقّعات.
     *
     * * نفس صيغة المتبقي بتاعة الأوامر بالظبط، بس على مستوى العقد:
     * *   المتبقي = قيمة العقد − الدفعات المقدّمة غير المستخدمة
     * *             − Σ(صافي رصيد كل فاتورة عليه لسه ما اتحصّلتش)
     *
     * * الفرق الوحيد في **التوزيع**: بدل ما المتبقي كله ينزل في يوم واحد،
     * * بيتقسّم بالتساوي على شهور العقد اللي لسه ما بدأتش.
     *
     * * الشرائح اللي بره فترة التقرير بتتحسب في المقسوم عليه بس ما بتتعرضش —
     * * عشان عقد سنة معروض عليه تقرير 3 شهور يفضل يعرض القسط الشهري
     * * الصح، مش المتبقي كله متقسّم على 3.
     */
    private static function applyMonthlyExecutedContractBalance(
        array &$result,
        Contract $contract,
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
        string $downPaymentTable,
        bool $addToCashInflowTotal,
        string $paidOrCollectedStatus,
        string $invoiceSettledColumn
    ): void {
        $contractStart = $contract->getStartDate();
        $contractEnd = $contract->getEndDate();
        if (empty($contractStart) || empty($contractEnd)) {
            return;
        }

        // * نفس معادلة applyForecastedOrderBalance بالظبط . هنا الخصم
        // * لازم يفضل بعملة العقد : المبلغ الكلي بعملة العقد ، و كل
        // * شريحة شهرية بتتحوّل بسعر يومها بعد القسمة — فلو الخصم
        // * اتقرا بالعملة الوظيفية كان هيتحوّل مرتين .
        $invoicesSettlement = (float) DB::table($invoiceTable)
            ->where('company_id', $companyId)
            ->where('currency', $contract->getCurrency())
            ->where('contract_code', $contract->getCode())
            ->sum(DB::raw(
                'ifnull('.$invoiceSettledColumn.', 0)'
                .' + ifnull(total_deductions, 0)'
                .' + ifnull(net_balance, 0)'
            ));

        $unusedDownPaymentBalance = (float) DB::table($downPaymentTable)
            ->where('company_id', $companyId)
            ->where('contract_id', $contract->id)
            ->sum('down_payment_balance');

        $remaining = (float) $contract->getAmount() - $unusedDownPaymentBalance - $invoicesSettlement;
        if ($remaining <= 0) {
            return;
        }

        $slices = MonthlyExecutionSchedule::forContract($remaining, $contractStart, $contractEnd);
        if (! $slices) {
            return;
        }

        $totalCashInFlowKey = __('Total Cash Inflow');
        $rowLabel = $contract->getClientName().'-'.$contract->getName();

        foreach ($slices as $sliceDate => $sliceAmount) {
            // * الشهور اللي بره الفترة المعروضة اتحسبت في المقسوم عليه
            // * وبس — datesWithWeekNumber فيها تواريخ الفترة لوحدها.
            if (! isset($datesWithWeekNumber[$sliceDate])) {
                continue;
            }

            $currentWeekYear = $datesWithWeekNumber[$sliceDate];
            // * دايماً بالعملة الوظيفية زي باقي صف التوقّعات (و زي
            // * system.veroanalysisb.com) ، حتى على تبويب عملة العقد نفسها .
            $exchangeRate = ForeignExchangeRate::getExchangeRateAtOrOne(
                $contract->getCurrency(), $mainFunctionalCurrency, $sliceDate, $companyId, $foreignExchangeRates
            );
            $amount = $sliceAmount * $exchangeRate;

            $result[$mainResultType][$resultKey][$rowLabel]['weeks'][$currentWeekYear] =
                ($result[$mainResultType][$resultKey][$rowLabel]['weeks'][$currentWeekYear] ?? 0) + $amount;
            $result[$mainResultType][$resultKey][$rowLabel]['total'] =
                ($result[$mainResultType][$resultKey][$rowLabel]['total'] ?? 0) + $amount;
            $result[$mainResultType][$resultKey]['total'][$currentWeekYear] =
                ($result[$mainResultType][$resultKey]['total'][$currentWeekYear] ?? 0) + $amount;

            if ($addToCashInflowTotal) {
                $result['customers'][$totalCashInFlowKey]['total'][$currentWeekYear] =
                    ($result['customers'][$totalCashInFlowKey]['total'][$currentWeekYear] ?? 0) + $amount;
            }
        }
    }
}
