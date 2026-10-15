<?php

namespace App\Http\Controllers\Reports;

use App\Exports\CashFlowMatrixExport;
use App\Models\Company;
use App\Models\Contract;
use App\Services\Reports\ConsolidatedCashFlowService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * ConsolidatedCashFlowReportController
 * ------------------------------------------------------------------
 * Rolls up the Company Cash Flow (banks section) and every selected
 * Contract Cash Flow into one combined report.
 *
 * ⚠️ CALCULATION LOGIC IS 100% UNTOUCHED. All of it lives in
 * ConsolidatedCashFlowService::build(), which was already clean and
 * well-documented — this migration only changes HOW the payload
 * reaches the browser (Inertia::render() instead of view()).
 *
 * ── Frontend migration status ───────────────────────────────────
 *   index()      → ✅ Inertia::render, Pages/ConsolidatedCashFlowReport/Index.vue
 *   result()     → ✅ Inertia::render, Pages/ConsolidatedCashFlowReport/Result.vue
 *   exportExcel() → ✅ New (project-owner requested, "same as the
 *              Statements reports"). Previously a client-side,
 *              uncolored SheetJS dump built from a JSON blob embedded
 *              in the Blade page (reports/consolidated_cash_flow/
 *              result.blade.php). Replaced with a real, colored,
 *              server-side export via the shared
 *              App\Exports\CashFlowMatrixExport — same filters as
 *              result(), same $service->build() call, so the export
 *              always matches what's on screen. Unlike Company/
 *              Contract Cash Flow's export, this one CAN be built
 *              straight from a fresh server-side call (GET, same
 *              pattern as LGLCSBanktatementController::exportExcel())
 *              because ConsolidatedCashFlowService computes the whole
 *              payload server-side already — no client-side row
 *              mutation pass to replicate.
 */
class ConsolidatedCashFlowReportController
{
    public function index(Company $company)
    {
        $activeContracts = Contract::query()
            ->where('company_id', $company->id)
            ->whereIn('status', [Contract::RUNNING, Contract::RUNNING_AND_AGAINST])
            // ⚠️ Bug fix: this query had no contract-type filter, so
            // Supplier contracts leaked into the picker — this report
            // only ever consolidates Customer contracts (Supplier data
            // comes in per-contract via po_allocations).
            ->where('model_type', 'Customer')
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'currency', 'end_date']);

        return Inertia::render('ConsolidatedCashFlowReport/Index', [
            'company' => ['id' => $company->id, 'name' => $company->getName()],
            'mainFunctionalCurrency' => $company->getMainFunctionalCurrency(),
            'currencies' => collect(getBanksCurrencies())->map(fn ($label, $code) => ['code' => $code, 'label' => touppercase($label)])->values(),
            'activeContracts' => $activeContracts->map(fn (Contract $c) => [
                'id' => $c->id,
                'name' => $c->getName(),
                'code' => $c->getCode(),
                'currency' => touppercase(trim((string) $c->getCurrency())),
                'end_date' => $c->end_date ? (string) $c->end_date : null,
            ])->values(),
            'urls' => [
                'result' => route('reports.consolidated-cash-flow.result', ['company' => $company->id]),
            ],
        ]);
    }

    public function result(Company $company, Request $request, ConsolidatedCashFlowService $service)
    {
        $validated = $this->validateRequest($company, $request);
        $request->merge($validated);

        try {
            $payload = $service->build($company, $request);
        } catch (\Throwable $e) {
            return redirect()
                ->route('reports.consolidated-cash-flow.index', ['company' => $company->id])
                ->with('fail', $e->getMessage());
        }

        return Inertia::render('ConsolidatedCashFlowReport/Result', array_merge($payload, [
            'company' => ['id' => $company->id, 'name' => $company->getName()],
            'filters' => [
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'report_interval' => $validated['report_interval'],
                'contract_ids' => $validated['contract_ids'] ?? [],
                'currencies' => $validated['currencies'] ?? [],
                'min_end_year' => $validated['min_end_year'] ?? null,
                'customer_past_due_percentages' => $validated['customer_past_due_percentages'] ?? [],
                'customer_past_due_days' => $validated['customer_past_due_days'] ?? [],
                'supplier_past_due_percentages' => $validated['supplier_past_due_percentages'] ?? [],
                'supplier_past_due_days' => $validated['supplier_past_due_days'] ?? [],
            ],
            'urls' => [
                'index' => route('reports.consolidated-cash-flow.index', ['company' => $company->id]),
                'exportExcel' => route('reports.consolidated-cash-flow.export', array_merge(['company' => $company->id], $request->only([
                    'start_date', 'end_date', 'report_interval', 'contract_ids', 'currencies', 'min_end_year',
                    'customer_past_due_percentages', 'customer_past_due_days',
                    'supplier_past_due_percentages', 'supplier_past_due_days',
                ]))),
            ],
        ]));
    }

    /**
     * Colored Excel export — same filters/validation as result(), same
     * $service->build() call. Rows are shaped to mirror the on-screen
     * table's original structure (_table.blade.php, pre-migration):
     * Section A (banks), one block per contract (inflow/outflow/net),
     * the unallocated cash-out row, then Section C's grand totals.
     */
    public function exportExcel(Company $company, Request $request, ConsolidatedCashFlowService $service)
    {
        $validated = $this->validateRequest($company, $request);
        $request->merge($validated);

        try {
            $payload = $service->build($company, $request);
        } catch (\Throwable $e) {
            return redirect()
                ->route('reports.consolidated-cash-flow.index', ['company' => $company->id])
                ->with('fail', $e->getMessage());
        }

        $weekKeys = array_keys($payload['weeks']);
        $periodLabels = array_map(fn ($wk) => (string) ($payload['weeks'][$wk] ?? $wk), $weekKeys);
        $rowTotal = static function (array $values) {
            return array_sum(array_map('floatval', $values));
        };
        $valuesFor = static function (array $series) use ($weekKeys) {
            return array_map(fn ($wk) => (float) ($series[$wk] ?? 0), $weekKeys);
        };

        $headings = array_merge([__('Item')], $periodLabels, [__('Total')]);
        $rows = [];

        $rows[] = ['label' => __('Section A — Company level (Cash & Banks Balance)'), 'type' => 'section', 'values' => [], 'total' => 0];
        foreach (($payload['banksSection'] ?? []) as $label => $row) {
            $values = $valuesFor($row['total'] ?? []);
            $rows[] = ['label' => $label, 'type' => 'row', 'values' => $values, 'total' => $rowTotal($values)];
        }

        $unallocatedIn = $valuesFor($payload['companyUnallocatedCashIn'] ?? []);
        $rows[] = ['label' => __('Cash Inflow (unallocated)'), 'type' => 'row', 'values' => $unallocatedIn, 'total' => $rowTotal($unallocatedIn)];

        foreach (($payload['contractsSection'] ?? []) as $block) {
            $blockLabel = $block['contract_name'].(! empty($block['contract_code']) ? ' ['.$block['contract_code'].']' : '');
            $rows[] = ['label' => $blockLabel, 'type' => 'section', 'values' => [], 'total' => 0];

            $inflow = $valuesFor($block['cash_inflow'] ?? []);
            $rows[] = ['label' => __('Total Cash Inflow'), 'type' => 'row', 'values' => $inflow, 'total' => $rowTotal($inflow)];

            $outflow = $valuesFor($block['cash_outflow'] ?? []);
            $rows[] = ['label' => __('Total Cash Outflow'), 'type' => 'row', 'values' => $outflow, 'total' => $rowTotal($outflow)];

            $net = $valuesFor($block['net_cash'] ?? []);
            $rows[] = ['label' => __('Net Cash (+/-)'), 'type' => 'net', 'values' => $net, 'total' => $rowTotal($net)];
        }

        $unallocatedOut = $valuesFor($payload['companyUnallocatedCashOut'] ?? []);
        $rows[] = ['label' => __('Company cash out (unallocated)'), 'type' => 'section', 'values' => [], 'total' => 0];
        $rows[] = ['label' => __('Company cash out (unallocated)'), 'type' => 'row', 'values' => $unallocatedOut, 'total' => $rowTotal($unallocatedOut)];

        $rows[] = ['label' => __('Section C — Grand total'), 'type' => 'section', 'values' => [], 'total' => 0];
        $gtBanks = $valuesFor($payload['grandTotal']['cash_and_banks'] ?? []);
        $rows[] = ['label' => __('Cash & Banks Balance'), 'type' => 'row', 'values' => $gtBanks, 'total' => $rowTotal($gtBanks)];
        $gtInflow = $valuesFor($payload['grandTotal']['cash_inflow'] ?? []);
        $rows[] = ['label' => __('Total Cash Inflow'), 'type' => 'row', 'values' => $gtInflow, 'total' => $rowTotal($gtInflow)];
        $gtOutflow = $valuesFor($payload['grandTotal']['cash_outflow'] ?? []);
        $rows[] = ['label' => __('Total Cash Outflow'), 'type' => 'row', 'values' => $gtOutflow, 'total' => $rowTotal($gtOutflow)];
        $gtNet = $valuesFor($payload['grandTotal']['net_cash'] ?? []);
        $rows[] = ['label' => __('Net Cash (+/-)'), 'type' => 'net', 'values' => $gtNet, 'total' => $rowTotal($gtNet)];
        $gtAccumulated = $valuesFor($payload['grandTotal']['accumulated_net'] ?? []);
        $lastAccumulated = $gtAccumulated !== [] ? $gtAccumulated[count($gtAccumulated) - 1] : 0.0;
        $rows[] = ['label' => __('Accumulated Net Cash (+/-)'), 'type' => 'total', 'values' => $gtAccumulated, 'total' => $lastAccumulated];

        $displayCurrency = (string) ($payload['displayCurrency'] ?? '');
        $fileNameParts = ['Consolidated-Cash-Flow', $displayCurrency !== '' ? $displayCurrency : ($payload['currencyName'] ?? null)];
        $fileName = preg_replace('/[^A-Za-z0-9\-]+/', '-', implode('-', array_filter($fileNameParts))).'.xlsx';

        // الأرقام كلها بالعملة الوظيفية وليست بعملة الفلتر، فلازم العنوان يوضح ده
        $sheetTitle = (string) ($payload['title'] ?? __('Consolidated Cash Flow Report'));
        if ($displayCurrency !== '') {
            $sheetTitle .= ' — '.__('All amounts are shown in').' '.$displayCurrency;
        }

        return (new CashFlowMatrixExport($headings, $rows, $sheetTitle))->download($fileName);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateRequest(Company $company, Request $request): array
    {
        return $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'report_interval' => ['required', 'in:daily,weekly,monthly'],
            'contract_ids' => ['nullable', 'array'],
            'contract_ids.*' => ['integer', Rule::exists('contracts', 'id')->where('company_id', $company->id)],
            'currencies' => ['nullable', 'array'],
            'currencies.*' => ['string', 'max:32'],
            // Kept for any old link/bookmark still sending the pre-multi-select shape.
            'currency' => ['nullable', 'string', 'max:32'],
            'min_end_year' => ['nullable', 'integer', 'digits:4'],
            // Fixed 4-tier past-due collection/payment plan — sent as two
            // flat, parallel arrays (matched by index) rather than one
            // array of {percentage, days} objects, since the latter
            // doesn't survive GET query-string serialization reliably
            // (see ConsolidatedCashFlowService::normalizeTiers()).
            'customer_past_due_percentages' => ['nullable', 'array', 'max:4'],
            'customer_past_due_percentages.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'customer_past_due_days' => ['nullable', 'array', 'max:4'],
            'customer_past_due_days.*' => ['nullable', 'integer', 'min:1'],
            'supplier_past_due_percentages' => ['nullable', 'array', 'max:4'],
            'supplier_past_due_percentages.*' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'supplier_past_due_days' => ['nullable', 'array', 'max:4'],
            'supplier_past_due_days.*' => ['nullable', 'integer', 'min:1'],
        ]);
    }
}
