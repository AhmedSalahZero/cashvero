<?php

namespace App\Http\Controllers;

use App\Exports\Statements\SafeStatementExport;
use App\Models\Branch;
use App\Models\Company;
use App\Support\ShareholderAccounts\AccountNumberLabel;
use App\Traits\GeneralFunctions;
use App\Traits\PaginatesStatementQueries;
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
 *                       1. Real server-side pagination via PaginatesStatementQueries
 *                          (the original Blade page loaded every matching row
 *                          into one client-side DataTable at once — fine for a
 *                          short range, but explicitly called out by the
 *                          project owner as needing "heavy report" handling
 *                          for ranges with hundreds of transactions). Only the
 *                          current page's rows are read from the database;
 *                          the KPI totals come from SQL aggregates over the
 *                          full range.
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
    use PaginatesStatementQueries;

    private const STATEMENT_TABLE = 'cash_in_safe_statements';

    private const ROWS_PER_PAGE = 50;

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

        $freshQuery = fn () => DB::table(self::STATEMENT_TABLE)
            ->where('company_id', $company->id)
            ->where('currency', $currency)
            ->where('branch_id', $branchId)
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $endDate)
            /**
             * * الترتيب بـ full_date مش date: الرصيد الجاري
             * * (beginning_balance/end_balance) بيتسلسل في
             * * CashInSafeStatement بـ 'full_date asc , id asc'، فالترتيب
             * * بالتاريخ من غير وقت كان بيقلب الصفوف اللي في نفس اليوم
             * * ويخلي رصيد آخر صف مش متصل برصيد اللي بعده
             */
            ->orderByRaw('full_date desc , id desc');

        if (! $freshQuery()->exists()) {
            return null;
        }

        $branch = Branch::find($branchId);

        return [
            'query' => $freshQuery,
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
            'comment' => AccountNumberLabel::decorateText(
                (int) ($row->company_id ?? 0),
                $comment ?: getBankStatementComment($row)
            ),
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

        /**
         * Only this page's 50 rows leave the database now. The KPIs below
         * still describe the FULL date range — SUM(debit)/SUM(credit) run
         * as SQL aggregates over the same WHERE clause, and the opening
         * and closing balances are read off the earliest and latest rows,
         * which is the same convention as before (the collection was
         * ordered date desc / id desc, so its last row held the beginning
         * balance and its first row the ending balance).
         */
        $paginator = $this->paginateStatement($data['query'], self::ROWS_PER_PAGE);
        $kpis = $this->ledgerStatementKpis($data['query'], self::STATEMENT_TABLE, $paginator->total(), 'full_date');

        $lang = app()->getLocale();
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

        // The workbook is the whole range, not the page on screen, so the
        // export runs the same query unpaginated.
        $rows = $data['query']()->get()->values()->map(function ($row, $index) use ($lang) {
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
