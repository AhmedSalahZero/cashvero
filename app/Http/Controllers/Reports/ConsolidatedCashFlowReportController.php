<?php

namespace App\Http\Controllers\Reports;

use App\Models\Company;
use App\Models\Contract;
use App\Services\Reports\ConsolidatedCashFlowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ConsolidatedCashFlowReportController
{
    public function index(Company $company): View
    {
        $activeContracts = Contract::query()
            ->where('company_id', $company->id)
            ->whereIn('status', [Contract::RUNNING, Contract::RUNNING_AND_AGAINST])
            ->orderBy('name')
            ->get(['id', 'name', 'code']);

        return view('reports.consolidated_cash_flow.index', [
            'company' => $company,
            'activeContracts' => $activeContracts,
        ]);
    }

    public function result(Company $company, Request $request, ConsolidatedCashFlowService $service): View|RedirectResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'report_interval' => ['required', 'in:daily,weekly,monthly'],
            'contract_ids' => ['nullable', 'array'],
            'contract_ids.*' => ['integer', Rule::exists('contracts', 'id')->where('company_id', $company->id)],
            'currency' => ['nullable', 'string', 'max:32'],
        ]);

        $request->merge($validated);

        try {
            $payload = $service->build($company, $request);
        } catch (\Throwable $e) {
            return redirect()
                ->route('reports.consolidated-cash-flow.index', ['company' => $company->id])
                ->with('fail', $e->getMessage());
        }

        return view('reports.consolidated_cash_flow.result', array_merge($payload, [
            'company' => $company,
        ]));
    }
}
