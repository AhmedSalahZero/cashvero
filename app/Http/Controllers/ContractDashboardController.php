<?php

namespace App\Http\Controllers;

use App\Exports\Statements\ContractDashboardExport;
use App\Models\Company;
use App\Services\ContractDashboardService;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * ContractDashboardController
 * ------------------------------------------------------------------
 * Customer Contract Dashboard (Dashboard sidebar section, under Cash
 * Status). Thin controller: all aggregates live in
 * ContractDashboardService::build(). Renders Dashboard/ContractStatus.
 *
 * The `date` filter is an AS-OF date, not a range: it re-asks every
 * question at that point in time — what had been invoiced, which
 * contracts had expired, how overdue the receivables were — the same
 * meaning the `date` filter carries on the LG/LC dashboard.
 */
class ContractDashboardController
{
    public function index(Company $company, Request $request)
    {
        $data = app(ContractDashboardService::class)->build($company, $request->get('date'));

        $canViewContracts = (bool) auth()->user()?->hasPermissionKey('customer_contract.view');

        return Inertia::render('Dashboard/ContractStatus', array_merge($data, [
            'company' => ['id' => $company->id],
            'canViewContracts' => $canViewContracts,
            'contractsIndexUrl' => route('contracts.index', ['company' => $company->id, 'type' => 'Customer']),
            'filterUrl' => route('view.contracts.dashboard', ['company' => $company->id]),
            'exportUrl' => route('export.contracts.dashboard', ['company' => $company->id]),
            'dashboardTabUrls' => [
                'cash' => route('view.customer.invoice.dashboard.cash', ['company' => $company->id]),
                'contracts' => route('view.contracts.dashboard', ['company' => $company->id]),
                'lglc' => route('view.lglc.dashboard', ['company' => $company->id]),
                'forecast' => route('view.customer.invoice.dashboard.forecast', ['company' => $company->id]),
            ],
        ]));
    }

    /**
     * The same contract rows the page is built from, at the same as-of
     * date — so a number questioned on screen can be traced line by
     * line in the sheet.
     *
     * Re-runs the service rather than reading anything the page
     * prepared, and writes RAW numeric values (not the page's
     * comma-formatted display strings) so Excel's own formatting and
     * the =SUM() totals row work on real numbers.
     */
    public function export(Company $company, Request $request)
    {
        $data = app(ContractDashboardService::class)->build($company, $request->get('date'));

        $currency = $request->get('currency');
        $rows = collect($data['details']['all']);

        if ($currency) {
            $rows = $rows->where('currency', strtoupper($currency))->values();
        }

        if ($rows->isEmpty()) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }

        $headings = [
            '#', 'Customer', 'Code', 'Name', 'Currency', 'Status',
            'Start Date', 'End Date', 'Invoices',
            'Contract Value', 'Invoiced', 'Remaining', 'Utilization %',
            'Billed (incl. tax)', 'Collected', 'Uncollected',
        ];

        $sheetRows = $rows->values()->map(function (array $row, int $index) {
            $value = (float) $row['amount'];

            return [
                '#' => $index + 1,
                'Customer' => $row['customer_name'],
                'Code' => $row['code'],
                'Name' => $row['name'],
                'Currency' => $row['currency'],
                'Status' => $row['status_label'],
                'Start Date' => $row['start_date'],
                'End Date' => $row['end_date'],
                'Invoices' => $row['invoice_count'],
                'Contract Value' => $value,
                'Invoiced' => (float) $row['invoiced'],
                'Remaining' => (float) $row['remaining'],
                'Utilization %' => $value > 0 ? round(((float) $row['invoiced'] / $value) * 100, 2) : 0.0,
                'Billed (incl. tax)' => (float) $row['billed'],
                'Collected' => (float) $row['collected'],
                'Uncollected' => (float) $row['uncollected'],
            ];
        });

        $fileNameParts = array_filter(['Contract-Dashboard', $currency, $data['asOfDate']]);
        $fileName = preg_replace('/[^A-Za-z0-9\-]+/', '-', implode('-', $fileNameParts)).'.xlsx';

        return (new ContractDashboardExport($headings, $sheetRows))->download($fileName);
    }
}
