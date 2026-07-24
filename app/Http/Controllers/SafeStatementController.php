<?php

namespace App\Http\Controllers;

use App\Exports\Statements\SafeStatementExport;
use App\Models\Branch;
use App\Models\Company;
use App\Traits\GeneralFunctions;
use App\Traits\PaginatesRawCollections;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * SafeStatementController
 * ------------------------------------------------------------------
 * Renders the "Safe Statement" report (Statements sidebar section) —
 * a heavy, transaction-by-transaction ledger for the cash physically
 * held in one Branch/Safe, in one currency, for a chosen date range.
 * The simplest of the Statement reports: unlike Bank Statement, there
 * is only ever one underlying table (cash_in_safe_statements) and no
 * account-type branching, no overdraft-only columns, and no inline-
 * editable rows.
 *
 * Every column (beginning balance, debit, credit, end balance) is
 * already computed and stored per-row elsewhere in the app (the same
 * trigger-driven bank-statement-writing logic Bank Statement reads
 * from) — this controller only reads and presents it.
 *
 * ── Frontend migration status ───────────────────────────────────
 *   - index()       → ✅ Migrated to Inertia (Statements/SafeStatement/Index)
 *   - result()      → ✅ Migrated to Inertia (Statements/SafeStatement/Result).
 *                     The underlying query (fetchStatementRows()) is
 *                     UNCHANGED. Two genuinely new, presentation-only
 *                     things were added:
 *                       1. Real server-side pagination via PaginatesRawCollections
 *                          (the original Blade page loaded every matching row
 *                          into one client-side DataTable at once — fine for a
 *                          short range, but explicitly called out by the
 *                          project owner as needing "heavy report" handling
 *                          for ranges with hundreds of transactions).
 *                       2. The filter form now submits as GET instead of the
 *                          original's POST, purely so the result page's URL
 *                          carries its own filters — matching Bank Statement's
 *                          sibling page, and required for page-N links to work
 *                          as plain, bookmarkable/shareable GETs.
 *                     None of this touches how a row's beginning/debit/credit/
 *                     end balance is calculated — that logic lives entirely in
 *                     the database and is read as-is.
 *   - exportExcel() → ✅ New (project-owner requested). Reuses
 *                     fetchStatementRows()/mapStatementRow() so the workbook
 *                     can never drift from what's on screen. Built on
 *                     Maatwebsite\Excel (already used elsewhere in the app,
 *                     e.g. SalesGatheringController@export) via the new
 *                     App\Exports\Statements\SafeStatementExport, which adds
 *                     real styling (colored header, banded rows, End-Balance
 *                     sign coloring, a formula-based totals row) — no new
 *                     export library introduced.
 */
class SafeStatementController
{
    use GeneralFunctions;
    use PaginatesRawCollections;

    /**
     * Filter form: Branch → Currency → date range.
     * Renders Statements/SafeStatement/Index.vue.
     */
    public function index(Company $company)
    {
        $selectedBranches = Branch::getBranchesForCurrentCompany($company->id);

        return \Inertia\Inertia::render('Statements/SafeStatement/Index', [
            'company' => ['id' => $company->id],
            'branches' => $selectedBranches, // { id: name }
            'currencies' => getCurrency(),
            'urls' => [
                'result' => route('result.safe.statement', ['company' => $company->id]),
            ],
        ]);
    }

    /**
     * Builds the raw result set for the current filters. Query logic
     * (company/currency/branch/date-range filter on cash_in_safe_statements,
     * ordered date desc/id desc) is UNCHANGED from the original controller —
     * only extracted into its own method so result() and exportExcel() share
     * one query instead of running two copies that could drift apart.
     *
     * Returns null when no rows match (caller decides how to respond).
     */
    private function fetchStatementRows(Company $company, Request $request): ?array
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $branchId = $request->get('branch_id');
        $currency = $request->get('currency');

        $results = DB::table('cash_in_safe_statements')
            ->where('company_id', $company->id)
            ->where('currency', $currency)
            ->where('branch_id', $branchId)
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $endDate)
            ->orderByRaw('date desc , id desc')
            ->get();

        if (! count($results)) {
            return null;
        }

        $branch = Branch::find($branchId);

        return [
            'results' => $results,
            'branchName' => $branch ? $branch->name : null,
            'currency' => $currency,
        ];
    }

    /**
     * Shapes one raw statement row (stdClass from the query above) into the
     * plain array both the on-screen table (via result()) and the Excel
     * export (via exportExcel()) read from.
     */
    private function mapStatementRow($row, string $lang): array
    {
        $reviewedArr = getBankStatementReviewed($row);

        $comment = $row->{'comment_'.$lang} ?? null;
        if (is_null($comment) && isset($row->type) && $row->type === 'opening-balance') {
            $comment = __('Opening Balance');
        }

        return [
            'id' => $row->id,
            'date' => \Carbon\Carbon::make($row->date)->format('d-m-Y'),
            'beginningBalance' => (float) ($row->beginning_balance ?? 0),
            'debit' => (float) ($row->debit ?? 0),
            'credit' => (float) ($row->credit ?? 0),
            'endBalance' => (float) ($row->end_balance ?? 0),
            'reviewedText' => getReviewedText($reviewedArr),
            'comment' => $comment ?: getBankStatementComment($row),
            'userComment' => \App\Helpers\HVero::getUserCommentFromModel($row),
        ];
    }

    /**
     * The report itself. Query logic lives in fetchStatementRows() and is
     * UNCHANGED from the original controller. Pagination + KPI/row shaping
     * for Vue are new — see class docblock.
     */
    public function result(Company $company, Request $request)
    {
        $data = $this->fetchStatementRows($company, $request);
        if (is_null($data)) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }
        $results = $data['results'];

        /**
         * KPI totals — computed from the FULL result set, before
         * pagination. $results is ordered date desc / id desc, so the
         * first row is the most recent movement (its end_balance is the
         * range's ending balance) and the last row is the earliest
         * movement (its beginning_balance is the range's beginning
         * balance) — identical convention to Bank Statement's KPIs.
         */
        $kpis = [
            'beginningBalance' => (float) ($results->last()->beginning_balance ?? 0),
            'endingBalance' => (float) ($results->first()->end_balance ?? 0),
            'totalDebit' => (float) $results->sum('debit'),
            'totalCredit' => (float) $results->sum('credit'),
            'transactionCount' => $results->count(),
        ];

        $lang = app()->getLocale();
        $paginator = $this->paginateCollection($results, 50, $request);
        $paginator->getCollection()->transform(fn ($row) => $this->mapStatementRow($row, $lang));

        return \Inertia\Inertia::render('Statements/SafeStatement/Result', [
            'company' => ['id' => $company->id],
            'currency' => $data['currency'],
            'branchName' => $data['branchName'],
            'kpis' => $kpis,
            'paginator' => $paginator->toArray(),
            'urls' => [
                'backUrl' => route('view.safe.statement', ['company' => $company->id]),
                // Same filters already in this request, resolved server-side —
                // matches the no-Ziggy, pre-resolved-URL convention already
                // used everywhere else in this app.
                'exportUrl' => route('export.safe.statement', array_merge(['company' => $company->id], $request->only([
                    'start_date', 'end_date', 'branch_id', 'currency',
                ]))),
            ],
        ]);
    }

    /**
     * Excel export (project-owner requested) — same filters as result(),
     * reusing fetchStatementRows()/mapStatementRow() so the workbook can
     * never drift from what's on screen. Exports the FULL date range in one
     * file (not just the currently-viewed page). Built on the same
     * Maatwebsite\Excel ExportData class SalesGatheringController@export
     * already uses — no new export library introduced.
     */
    public function exportExcel(Company $company, Request $request)
    {
        $data = $this->fetchStatementRows($company, $request);
        if (is_null($data)) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }
        $lang = app()->getLocale();

        $headings = ['#', 'Date', 'Beginning Balance', 'Debit', 'Credit', 'End Balance', 'Reviewed', 'Comment'];

        $rows = $data['results']->values()->map(function ($row, $index) use ($lang) {
            $mapped = $this->mapStatementRow($row, $lang);

            return [
                '#' => $index + 1,
                'Date' => $mapped['date'],
                'Beginning Balance' => $mapped['beginningBalance'],
                'Debit' => $mapped['debit'],
                'Credit' => $mapped['credit'],
                'End Balance' => $mapped['endBalance'],
                'Reviewed' => $mapped['reviewedText'],
                'Comment' => trim($mapped['comment'].' '.$mapped['userComment']),
            ];
        });

        $fileNameParts = ['Safe-Statement', $data['branchName'], strtoupper((string) $data['currency'])];
        $fileName = preg_replace('/[^A-Za-z0-9\-]+/', '-', implode('-', $fileNameParts)).'.xlsx';

        return (new SafeStatementExport($headings, $rows))->download($fileName);
    }
}
