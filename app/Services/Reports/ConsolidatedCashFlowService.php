<?php

namespace App\Services\Reports;

use App\Http\Controllers\CashFlowReportController;
use App\Models\CashExpense;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractLoanSchedule;
use App\Models\CustomerInvoice;
use App\Models\ForeignExchangeRate;
use App\Models\LoanSchedule;
use App\Models\PoAllocation;
use App\Models\SupplierInvoice;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConsolidatedCashFlowService
{
    private const MAX_CONTRACTS_PER_RUN = 50;

    /**
     * @return array{
     *   weeks: array<string, string|int>,
     *   dates: array<string, array{start_date: string, end_date: string}>,
     *   reportInterval: string,
     *   banksSection: array<string, array{total: array<string, float|int>}>,
     *   contractsSection: list<array{
     *     contract_id: int,
     *     contract_name: string,
     *     contract_code: string,
     *     cash_inflow: array<string, float|int>,
     *     cash_outflow: array<string, float|int>,
     *     net_cash: array<string, float|int>
     *   }>,
     *   companyUnallocatedCashOut: array<string, float|int>,
     *   grandTotal: array{
     *     cash_inflow: array<string, float|int>,
     *     cash_outflow: array<string, float|int>,
     *     net_cash: array<string, float|int>,
     *     accumulated_net: array<string, float|int>
     *   },
     *   currencyName: string,
     *   title: string
     * }
     */
    public function build(Company $company, Request $request): array
    {
        $contractIds = $this->resolveContractIds($company, $request);

        return $this->buildReport($company, $request, $contractIds);
    }

    /**
     * @return list<int>
     */
    private function resolveContractIds(Company $company, Request $request): array
    {
        $contractIds = $request->input('contract_ids', []);
        if (! is_array($contractIds)) {
            $contractIds = [];
        }
        $contractIds = array_values(array_unique(array_filter(array_map('intval', $contractIds))));
        if ($contractIds === []) {
            $contractIds = Contract::query()
                ->where('company_id', $company->id)
                ->whereIn('status', [Contract::RUNNING, Contract::RUNNING_AND_AGAINST])
                ->orderBy('name')
                ->pluck('id')
                ->all();
        }

        if (count($contractIds) > self::MAX_CONTRACTS_PER_RUN) {
            throw new \RuntimeException(__(
                'Too many contracts selected (:count). Please choose up to :max contracts per run.',
                ['count' => count($contractIds), 'max' => self::MAX_CONTRACTS_PER_RUN]
            ));
        }

        return $contractIds;
    }

    /**
     * @param  list<int>  $contractIds
     * @return array<string, mixed>
     */
    private function buildReport(Company $company, Request $request, array $contractIds): array
    {
        $controller = app(CashFlowReportController::class);
        $subRequest = $this->buildBaseCashFlowRequest($company, $request);

        ForeignExchangeRate::beginRequestMemo();

        try {
            $sharedTimeline = $controller->buildSharedTimelineContext($company, $subRequest);
            if ($sharedTimeline instanceof RedirectResponse) {
                throw new \RuntimeException(__('Company cash flow could not be built. Ensure the date range includes today, matching the main cash flow report rules.'));
            }

            $companyPayload = $this->loadCompanyReport($controller, $company, $request, $sharedTimeline);

            /** @var array<string, string|int> $weeks */
            $weeks = $companyPayload['weeks'];
            /** @var array<string, array{start_date: string, end_date: string}> $dates */
            $dates = $companyPayload['dates'];
            $currencyName = (string) $companyPayload['currencyName'];
            $reportInterval = (string) $companyPayload['reportInterval'];

            /** @var array $companyResult */
            $companyResult = $companyPayload['result'];
            $banksSection = $this->extractBanksSection($companyResult);

            $contracts = Contract::query()
                ->where('company_id', $company->id)
                ->whereIn('id', $contractIds)
                ->get();

            $poByContract = $this->preloadPoAllocations($contractIds);

            $contractsSection = app(ContractCashFlowBatchBuilder::class)->build(
                $company,
                $request,
                $contracts,
                $poByContract,
                $sharedTimeline,
                $controller,
            );

            $sumInflow = [];
            $sumOutflow = [];
            $sumNet = [];

            foreach ($contractsSection as $row) {
                $sumInflow = $this->sumByWeek($sumInflow, $row['cash_inflow']);
                $sumOutflow = $this->sumByWeek($sumOutflow, $row['cash_outflow']);
                $sumNet = $this->sumByWeek($sumNet, $row['net_cash']);
            }

            $companyUnallocatedCashOut = $this->computeUnallocatedCashOut(
                $companyResult,
                $contractsSection,
                $weeks,
            );

            $grandTotal = [
                'cash_inflow' => $sumInflow,
                'cash_outflow' => $sumOutflow,
                'net_cash' => $sumNet,
                'accumulated_net' => $this->accumulateNetOverWeeks($sumNet, $weeks),
            ];

            return [
                'weeks' => $weeks,
                'dates' => $dates,
                'reportInterval' => $reportInterval,
                'banksSection' => $banksSection,
                'contractsSection' => $contractsSection,
                'companyUnallocatedCashOut' => $companyUnallocatedCashOut,
                'grandTotal' => $grandTotal,
                'currencyName' => $currencyName,
                'title' => __('Consolidated Cash Flow Report').' [ '.$reportInterval.' ]',
            ];
        } finally {
            ForeignExchangeRate::endRequestMemo();
        }
    }

    /**
     * @param  array<string, mixed>  $sharedTimeline
     * @return array<string, mixed>
     */
    private function loadCompanyReport(
        CashFlowReportController $controller,
        Company $company,
        Request $request,
        array $sharedTimeline,
    ): array {
        $subRequest = $this->buildBaseCashFlowRequest($company, $request);

        $currency = (string) $subRequest->input('currency', $company->getMainFunctionalCurrency());
        $companyId = (int) $company->id;
        $cashflowReportId = 0;
        $isContract = false;

        $weeks = $sharedTimeline['weeks'];
        $dates = $sharedTimeline['dates'];
        $periodsByWeekKey = $dates;
        $periodStart = (string) $sharedTimeline['startDate'];
        $periodEnd = (string) $sharedTimeline['endDate'];
        $foreignExchangeRates = $sharedTimeline['foreignExchangeRates'];
        $mainFunctionalCurrency = $sharedTimeline['mainFunctionalCurrency'];
        $formStartDate = $sharedTimeline['formStartDate'];
        $formEndDate = $sharedTimeline['formEndDate'];
        $datesWithWeekNumber = $sharedTimeline['datesWithWeekNumber'];

        $result = $this->initCompanyCashFlowResult();
		
        CustomerInvoice::getCashAndBankBalanceAtDate(
            $result,
            $foreignExchangeRates,
            $mainFunctionalCurrency,
            $periodStart,
            array_key_first($weeks),
            $companyId,
        );
        LoanSchedule::getLoanInstallmentsAtDates(
            $result,
            $foreignExchangeRates,
            $mainFunctionalCurrency,
            $companyId,
            $datesWithWeekNumber,
            $periodEnd,
        );
        ContractLoanSchedule::getContractLoanInstallmentsAtDates(
            $result,
            $foreignExchangeRates,
            $mainFunctionalCurrency,
            $companyId,
            $datesWithWeekNumber,
            $periodEnd,
        );

        CashExpense::getProjectionOtherCashOut($result, $company, $cashflowReportId, $isContract);
        CustomerInvoice::getProjectionOtherCashIn($result, $company, $cashflowReportId, $isContract);
        CustomerInvoice::getForecastedProjectCollection(
            $result,
            $periodStart,
            $periodEnd,
            $currency,
            $companyId,
            $datesWithWeekNumber,
            null,
        );
        SupplierInvoice::getForecastedProjectCollection(
            $result,
            $periodStart,
            $periodEnd,
            $currency,
            $companyId,
            $datesWithWeekNumber,
            null,
        );
        CustomerInvoice::getCustomerInvoicesUnderCollectionAtDatesForContracts(
            $result,
            $companyId,
            null,
            $datesWithWeekNumber,
            $periodEnd,
        );
        SupplierInvoice::getSupplierInvoicesUnderCollectionAtDates(
            $result,
            $companyId,
            $datesWithWeekNumber,
            $periodStart,
            $periodEnd,
        );

        CashFlowCompanyPeriodBatchLoader::apply(
            $result,
            $foreignExchangeRates,
            $mainFunctionalCurrency,
            $companyId,
            $periodStart,
            $periodEnd,
            $periodsByWeekKey,
        );

        $pastDueCustomerInvoices = $controller->getPastDueCustomerInvoices(
            'CustomerInvoice',
            $currency,
            $companyId,
            null,
        );
        $pastDueSupplierInvoices = $controller->getPastDueCustomerInvoices(
            'SupplierInvoice',
            $currency,
            $companyId,
            null,
        );
        $pastDueInstallments = $controller->getPastDueLoanSchedules($currency, $companyId);

        $customerDueInvoices = json_decode(json_encode(DB::table('weekly_cashflow_custom_due_invoices')
            ->where('weekly_cashflow_custom_due_invoices.company_id', $companyId)
            ->where('invoice_type', 'CustomerInvoice')
            ->where('cashflow_report_id', $cashflowReportId)
            ->where('is_contract', false)
            ->groupBy('week_start_date')
            ->selectRaw('week_start_date,sum(amount) as amount')
            ->get()), true);
        $supplierDueInvoices = json_decode(json_encode(DB::table('weekly_cashflow_custom_due_invoices')
            ->where('weekly_cashflow_custom_due_invoices.company_id', $companyId)
            ->where('invoice_type', 'SupplierInvoice')
            ->where('cashflow_report_id', $cashflowReportId)
            ->where('is_contract', false)
            ->groupBy('week_start_date')
            ->selectRaw('week_start_date,sum(amount) as amount')
            ->get()), true);
        $pastDueLoanInstallments = json_decode(json_encode(DB::table('weekly_cashflow_custom_past_due_schedules')
            ->where('company_id', $companyId)
            ->groupBy('week_start_date')
            ->selectRaw('week_start_date,sum(amount) as amount')
            ->get()), true);

        $controller->finalizeContractCashFlowTotals(
            $result,
            $company,
            $currency,
            null,
            $datesWithWeekNumber,
            $weeks,
            $cashflowReportId,
            $isContract,
            null,
            $formStartDate,
            $formEndDate,
            [],
            $customerDueInvoices,
            $supplierDueInvoices,
            $pastDueLoanInstallments,
        );

        $orderByKeys = [
            'Cash Payments',
            'Outgoing Transfers',
            'Paid Payable Cheques',
            'Under Payment Payable Cheques',
            'Suppliers Invoices',
            'Suppliers Past Due Invoices',
            'Loan Past Due Installments',
            'Forecasted Suppliers Contract Payments',
        ];

        $result['suppliers'] = collect($result['suppliers'])->sortBy(function ($value, $key) use ($orderByKeys) {
            return array_search($key, $orderByKeys);
        })->toArray();

        return [
            'result' => $result,
            'dates' => $dates,
            'contractCode' => null,
            'pastDueCustomerInvoices' => [$currency => $pastDueCustomerInvoices],
            'currencyName' => $currency,
            'reportInterval' => $sharedTimeline['reportInterval'],
            'weeks' => $weeks,
            'pastDueSupplierInvoices' => $pastDueSupplierInvoices,
            'pastDueInstallments' => $pastDueInstallments,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function initCompanyCashFlowResult(): array
    {
        return [
            'customers' => [
                'Cash & Banks Balance' => [],
                'Checks Collected' => [],
                'Incoming Transfers' => [],
                'Bank Deposits' => [],
                'Cash Collections' => [],
                'Time Of Deposits' => [],
                'Cheques Under Collection' => [],
                'Cheques In Safe' => [],
                'Cancelled LGs Cash Cover' => [],
                'Customers Invoices' => [],
                'Customers Past Due Invoices' => [],
                'Forecasted Project Collection' => [],
                'Projected Other Cash In Items' => [],
                __('Total Cash Inflow') => [],
            ],
            'suppliers' => [],
            'cash_expenses' => [],
        ];
    }

    private const CASH_AND_BANKS_BALANCE_KEY = 'Cash & Banks Balance';

    /**
     * Section A: opening cash & bank balances only (not movements allocated to contracts).
     *
     * @return array<string, array{total: array<string, float|int>}>
     */
    private function extractBanksSection(array $result): array
    {
        $block = $result['customers'][self::CASH_AND_BANKS_BALANCE_KEY] ?? [];
        $totals = is_array($block) && isset($block['total']) && is_array($block['total'])
            ? $block['total']
            : [];

        return [
            self::CASH_AND_BANKS_BALANCE_KEY => ['total' => $totals],
        ];
    }

    /**
     * Company-level cash outflow not covered by the selected contract cash-flow totals
     * (e.g. salaries and other expenses with no project allocation).
     *
     * @param  list<array{cash_outflow: array<string, float|int>}>  $contractsSection
     * @param  array<string, string|int>  $weeks
     * @return array<string, float|int>
     */
    private function computeUnallocatedCashOut(
        array $companyResult,
        array $contractsSection,
        array $weeks,
    ): array {
        $companyOutflow = $this->extractTotalCashOutflow($companyResult);
        $contractsOutflow = [];

        foreach ($contractsSection as $block) {
            foreach ($block['cash_outflow'] as $weekKey => $amount) {
                $contractsOutflow[$weekKey] = ($contractsOutflow[$weekKey] ?? 0.0) + (float) $amount;
            }
        }

        $unallocated = [];
        foreach (array_keys($weeks) as $weekKey) {
            $diff = (float) ($companyOutflow[$weekKey] ?? 0) - (float) ($contractsOutflow[$weekKey] ?? 0);
            $unallocated[$weekKey] = max(0.0, $diff);
        }

        return $unallocated;
    }

    /**
     * @return array<string, float|int>
     */
    private function extractTotalCashOutflow(array $result): array
    {
        $outflowKey = __('Total Cash Outflow');
        $totals = $result['cash_expenses'][$outflowKey]['total'] ?? [];

        return is_array($totals) ? $totals : [];
    }

    /**
     * @param  list<int>  $contractIds
     * @return Collection<int, Collection<int, PoAllocation>>
     */
    private function preloadPoAllocations(array $contractIds): Collection
    {
        if ($contractIds === []) {
            return collect();
        }

        $grouped = PoAllocation::query()
            ->whereIn('po_allocations.contract_id', $contractIds)
            ->join('purchase_orders', 'purchase_orders.id', '=', 'po_allocations.purchase_order_id')
            ->join('contracts', 'contracts.id', '=', 'purchase_orders.contract_id')
            ->get()
            ->groupBy('contract_id');

        return collect($grouped->all())->map(
            static fn (Collection $items): Collection => collect($items->all()),
        );
    }

    private function buildBaseCashFlowRequest(Company $company, Request $request): Request
    {
        $start = Carbon::make($request->input('start_date'))->format('Y-m-d');
        $end = Carbon::make($request->input('end_date'))->format('Y-m-d');
        $interval = $request->input('report_interval', 'monthly');
        $currency = $request->input('currency', $company->getMainFunctionalCurrency());

        return Request::create('/', 'GET', [
            'start_date' => $start,
            'end_date' => $end,
            'report_interval' => $interval,
            'currency' => $currency,
        ]);
    }

    /**
     * @param  array<string, float|int>  $a
     * @param  array<string, float|int>  $b
     * @return array<string, float|int>
     */
    private function sumByWeek(array $a, array $b): array
    {
        $keys = array_unique(array_merge(array_keys($a), array_keys($b)));
        $out = [];
        foreach ($keys as $k) {
            $out[$k] = (float) ($a[$k] ?? 0) + (float) ($b[$k] ?? 0);
        }

        return $out;
    }

    /**
     * @param  array<string, float|int>  $netByWeek
     * @param  array<string, string|int>  $weeks
     * @return array<string, float|int>
     */
    private function accumulateNetOverWeeks(array $netByWeek, array $weeks): array
    {
        $acc = [];
        $running = 0.0;
        foreach (array_keys($weeks) as $wk) {
            $running += (float) ($netByWeek[$wk] ?? 0);
            $acc[$wk] = $running;
        }

        return $acc;
    }
}
