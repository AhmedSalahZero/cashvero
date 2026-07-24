<?php

namespace App\Http\Controllers;

use App\Exports\Statements\FactoringStatementExport;
use App\Models\Company;
use App\Models\FactoringCompany;
use App\Models\FactoringContract;
use App\Models\FactoringStatement;
use App\Traits\GeneralFunctions;
use App\Traits\PaginatesRawCollections;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * FactoringStatementController
 * ------------------------------------------------------------------
 * Renders the "Factoring Statement" report (Statements sidebar
 * section) — a running-balance ledger for ONE specific Factoring
 * Contract (with one Factoring Company, in the contract's own fixed
 * currency), for a chosen date range. Unlike Bank/Safe Statement,
 * `factoring_statements` has no stored beginning_balance/end_balance
 * columns at all — the running balance is computed here in PHP from
 * each row's own debit/credit, starting at 0 and accumulating forward
 * in date order. That calculation (fetchStatementRows()) is UNCHANGED
 * from the original controller — only how the computed rows reach the
 * screen (pagination, KPIs, Vue) is new.
 *
 * ⚠️ Rows are ordered date ASCENDING (oldest first) here — the
 * opposite of Bank/Safe/LG & LC Statement's descending order. This
 * matters for the Excel export's totals row: the ending balance is
 * the LAST row's own value, not the first's (see
 * FactoringStatementExport's override).
 *
 * Has a sibling report, Factoring Charges Statement (its own tab on
 * the original page, via the shared <x-factoring-statement-tabs>
 * Blade component) — NOT migrated as part of this page; the Vue
 * Index/Result pages link to it as a plain (still-Blade) tab, exactly
 * like every other still-Blade sidebar item.
 *
 * ── Frontend migration status ───────────────────────────────────
 *   - index()        → ✅ Migrated to Inertia (Statements/FactoringStatement/Index)
 *   - getCurrencies() → ✅ Still an ajax data endpoint, UNCHANGED business logic.
 *                      Switched from a `{factoringCompany}` route-model-bound
 *                      path segment to a `factoring_company_id` query parameter
 *                      (matching this project's established cascading-dropdown
 *                      convention elsewhere, e.g. BankStatementController@getAccountNumbers)
 *                      purely so the URL can be pre-resolved once, server-side,
 *                      without needing Ziggy — safe, since nothing besides this
 *                      page's own (now-superseded) Blade JS referenced the old
 *                      path-based route.
 *   - getContracts()  → ✅ Same change as getCurrencies() — `factoring_company_id`
 *                      and `currency` are now query parameters, not path segments.
 *                      Business logic (date-range/currency filter on the
 *                      company's factoring contracts) UNCHANGED.
 *   - result()        → ✅ Migrated to Inertia (Statements/FactoringStatement/Result).
 *                      Query + running-balance calculation (fetchStatementRows())
 *                      UNCHANGED. Real server-side pagination (via
 *                      PaginatesRawCollections) is new — the original sent every
 *                      matching row to the Blade view at once. GET instead of the
 *                      original's already-GET method — no verb change needed.
 *   - exportExcel()   → ✅ New (project-owner requested). Reuses
 *                      fetchStatementRows() so the workbook can never drift from
 *                      what's on screen. Styled via the new
 *                      App\Exports\Statements\FactoringStatementExport.
 */
class FactoringStatementController
{
    use GeneralFunctions;
    use PaginatesRawCollections;

    /**
     * Filter form: date range, Factoring Company → Currency → Factoring
     * Contract (both cascading). Renders Statements/FactoringStatement/Index.vue.
     */
    public function index(Company $company)
    {
        return \Inertia\Inertia::render('Statements/FactoringStatement/Index', [
            'company' => ['id' => $company->id],
            'factoringCompanies' => $company->factoringCompanies()->orderBy('name')->get()->map(fn ($fc) => [
                'id' => $fc->id,
                'name' => $fc->getName(),
            ])->values(),
            'urls' => [
                'result' => route('result.factoring.statement', ['company' => $company->id]),
                'currencies' => route('factoring.statement.currencies', ['company' => $company->id]),
                'contracts' => route('factoring.statement.contracts', ['company' => $company->id]),
                'chargesStatementUrl' => route('view.factoring.charges.statement', ['company' => $company->id]),
            ],
        ]);
    }

    /**
     * JSON lookup for the Currency dropdown, once a Factoring Company is
     * chosen. UNCHANGED business logic — only reads factoring_company_id
     * from a query parameter now instead of a route-bound model (see class
     * docblock).
     */
    public function getCurrencies(Company $company, Request $request)
    {
        $factoringCompany = FactoringCompany::where('company_id', $company->id)
            ->findOrFail($request->integer('factoring_company_id'));

        $currencies = $company->factoringContracts()
            ->where('factoring_company_id', $factoringCompany->id)
            ->pluck('currency')
            ->unique()
            ->filter()
            ->mapWithKeys(function ($currency) {
                $allCurrencies = getCurrencies();

                return [$currency => $allCurrencies[$currency] ?? strtoupper($currency)];
            });

        return response()->json(['status' => true, 'currencies' => $currencies]);
    }

    /**
     * JSON lookup for the Factoring Contract dropdown, once Factoring
     * Company + Currency are chosen. UNCHANGED business logic — only reads
     * factoring_company_id/currency from query parameters now (see class
     * docblock).
     */
    public function getContracts(Company $company, Request $request)
    {
        $factoringCompany = FactoringCompany::where('company_id', $company->id)
            ->findOrFail($request->integer('factoring_company_id'));
        $currency = (string) $request->get('currency');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $contracts = $company->factoringContracts()
            ->where('factoring_company_id', $factoringCompany->id)
            ->where('currency', $currency)
            ->when($startDate, fn ($query) => $query->where('contract_end_date', '>=', $startDate))
            ->when($endDate, fn ($query) => $query->where('contract_start_date', '<=', $endDate))
            ->get()
            ->map(fn (FactoringContract $contract) => [
                'id' => $contract->id,
                'label' => $contract->getContractStartDateFormatted().' — '.$contract->getContractEndDateFormatted()
                    .' | '.strtoupper($contract->getCurrency() ?? '')
                    .' | '.$contract->getLimitFormatted(),
            ]);

        return response()->json(['status' => true, 'contracts' => $contracts]);
    }

    /**
     * Builds the raw, already-running-balance-computed rows for the
     * current filters. UNCHANGED from the original controller — same
     * findOrFail/abort_unless guards, same query, same running-balance
     * accumulation (starts at 0, accumulates debit − credit in date-
     * ascending order).
     *
     * Returns null when no rows match. Aborts with 422 (unchanged) if the
     * submitted currency doesn't match the contract's own fixed currency.
     */
    private function fetchStatementRows(Company $company, Request $request): ?array
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $factoringCompany = FactoringCompany::where('company_id', $company->id)
            ->findOrFail($request->integer('factoring_company_id'));
        $contract = FactoringContract::where('company_id', $company->id)
            ->where('factoring_company_id', $factoringCompany->id)
            ->findOrFail($request->integer('factoring_contract_id'));

        abort_unless($contract->currency === $request->get('currency'), 422);

        $statements = FactoringStatement::query()
            ->where('company_id', $company->id)
            ->where('factoring_contract_id', $contract->id)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        if ($statements->isEmpty()) {
            return null;
        }

        $rows = [];
        $runningBalance = 0.0;

        foreach ($statements as $statement) {
            $debit = (float) $statement->debit;
            $credit = (float) $statement->credit;
            $runningBalance = round($runningBalance + $debit - $credit, 2);

            $rows[] = [
                'date' => Carbon::make($statement->date)->format('d-m-Y'),
                'debit' => $debit,
                'credit' => $credit,
                'endBalance' => $runningBalance,
                'comment' => $statement->getComment(),
            ];
        }

        return [
            'rows' => collect($rows),
            'factoringCompanyName' => $factoringCompany->getName(),
            'contractLabel' => $contract->getContractStartDateFormatted().' — '.$contract->getContractEndDateFormatted(),
            'currency' => strtoupper((string) $contract->currency),
        ];
    }

    /**
     * The report itself. Calculation lives in fetchStatementRows() and is
     * UNCHANGED. Real server-side pagination is new — see class docblock.
     */
    public function result(Company $company, Request $request)
    {
        $data = $this->fetchStatementRows($company, $request);
        if (is_null($data)) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }
        $rows = $data['rows'];

        $kpis = [
            'totalDebit' => (float) $rows->sum('debit'),
            'totalCredit' => (float) $rows->sum('credit'),
            'endingBalance' => (float) ($rows->last()['endBalance'] ?? 0),
            'transactionCount' => $rows->count(),
        ];

        $paginator = $this->paginateCollection($rows, 50, $request);

        return \Inertia\Inertia::render('Statements/FactoringStatement/Result', [
            'company' => ['id' => $company->id],
            'currency' => $data['currency'],
            'factoringCompanyName' => $data['factoringCompanyName'],
            'contractLabel' => $data['contractLabel'],
            'kpis' => $kpis,
            'paginator' => $paginator->toArray(),
            'urls' => [
                'backUrl' => route('view.factoring.statement', ['company' => $company->id]),
                'chargesStatementUrl' => route('view.factoring.charges.statement', ['company' => $company->id]),
                'exportUrl' => route('export.factoring.statement', array_merge(['company' => $company->id], $request->only([
                    'start_date', 'end_date', 'factoring_company_id', 'factoring_contract_id', 'currency',
                ]))),
            ],
        ]);
    }

    /**
     * Excel export (project-owner requested) — same filters as result(),
     * reusing fetchStatementRows(). Styled via the new
     * App\Exports\Statements\FactoringStatementExport — no new export
     * library introduced.
     */
    public function exportExcel(Company $company, Request $request)
    {
        $data = $this->fetchStatementRows($company, $request);
        if (is_null($data)) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }

        $headings = ['#', 'Date', 'Debit', 'Credit', 'End Balance', 'Comment'];
        $rows = $data['rows']->values()->map(fn ($row, $index) => [
            '#' => $index + 1,
            'Date' => $row['date'],
            'Debit' => $row['debit'],
            'Credit' => $row['credit'],
            'End Balance' => $row['endBalance'],
            'Comment' => $row['comment'],
        ]);

        $fileNameParts = ['Factoring-Statement', $data['factoringCompanyName'], $data['currency']];
        $fileName = preg_replace('/[^A-Za-z0-9\-]+/', '-', implode('-', $fileNameParts)).'.xlsx';

        return (new FactoringStatementExport($headings, $rows))->download($fileName);
    }
}
