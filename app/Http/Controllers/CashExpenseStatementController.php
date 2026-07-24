<?php

namespace App\Http\Controllers;

use App\Exports\Statements\CashExpenseStatementExport;
use App\Models\CashExpenseCategory;
use App\Models\Company;
use App\Traits\GeneralFunctions;
use App\Traits\PaginatesRawCollections;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CashExpenseStatementController
 * ------------------------------------------------------------------
 * Renders the "Cash Expense Statement" report (Statements sidebar
 * section) — a heavy, transaction-by-transaction list of a company's
 * cash expenses, filtered by currency and one or more expense
 * sub-categories, for a chosen date range. Reads straight from
 * `cash_expenses` (joined to its category/sub-category names) —
 * never recalculates anything.
 *
 * Unlike Bank Statement / Safe Statement, this is NOT a running-
 * balance ledger — `cash_expenses` has no beginning_balance/
 * end_balance columns at all, so there is no Beginning/End Balance
 * concept here. It's a filtered expense list with its own two
 * meaningful totals: Paid Amount and Withhold Amount.
 *
 * ⚠️ Real gap found while migrating this page: `cash_expense_statement_form`
 * and `cash_expense_statement_result` — the two Blade views this
 * controller's original index()/result() rendered — do not exist
 * anywhere in the project backup. Whether they were lost before this
 * backup was taken, or this route has simply been broken/unreachable
 * in production for a while, isn't knowable from what's on disk. The
 * filter UI and result columns below were rebuilt from what the
 * controller's own result() query actually reads and filters by
 * (confirmed against the real `cash_expenses` table schema), not
 * copied from a missing template — flagging this explicitly rather
 * than silently presenting it as a normal migration.
 *
 * ⚠️ Also worth noting: the original controller received a flat list
 * of main CATEGORIES for its filter UI (CashExpenseCategory::...->get()
 * ->formattedForSelect(...)), but result()'s query filters by
 * `cash_expense_category_name_id` — SUB-category IDs. A flat main-
 * category list has no way to feed that field. Rebuilt here to eager-
 * load each category's sub-category names and expose the real
 * (category → sub-category) tree the filter needs to actually work.
 *
 * ── Frontend migration status ───────────────────────────────────
 *   - index()       → ✅ Migrated to Inertia (Statements/CashExpenseStatement/Index)
 *   - result()      → ✅ Migrated to Inertia (Statements/CashExpenseStatement/Result).
 *                     Query logic (fetchStatementRows()) is UNCHANGED from the
 *                     original controller. Real server-side pagination (via
 *                     PaginatesRawCollections) and GET instead of POST are new,
 *                     presentation-only — matches Bank/Safe Statement's sibling
 *                     pages. Safe to change HTTP verb/URI here: nothing else in
 *                     the app references result.cash.expense.statement.
 *   - exportExcel() → ✅ New (project-owner requested). Reuses
 *                     fetchStatementRows()/mapStatementRow(). Styled via the new
 *                     App\Exports\Statements\CashExpenseStatementExport (colored
 *                     header, banded rows, formula-based totals row) — same
 *                     shared base class as Bank/Safe Statement's exports.
 */
class CashExpenseStatementController
{
    use GeneralFunctions;
    use PaginatesRawCollections;

    /**
     * Filter form: date range, Currency, and one or more expense
     * sub-categories (grouped under their main category).
     * Renders Statements/CashExpenseStatement/Index.vue.
     */
    public function index(Company $company)
    {
        $categories = CashExpenseCategory::where('company_id', $company->id)
            ->with('cashExpenseCategoryNames')
            ->orderBy('name', 'asc')
            ->get();

        return \Inertia\Inertia::render('Statements/CashExpenseStatement/Index', [
            'company' => ['id' => $company->id],
            'categories' => $categories->map(fn ($category) => [
                'id' => $category->getId(),
                'name' => $category->getName(),
                'subCategories' => $category->cashExpenseCategoryNames->map(fn ($sub) => [
                    'id' => $sub->id,
                    'name' => $sub->getName(),
                ])->values(),
            ])->values(),
            'currencies' => getCurrency(),
            'urls' => [
                'result' => route('result.cash.expense.statement', ['company' => $company->id]),
            ],
        ]);
    }

    /**
     * Builds the raw result set for the current filters. Query logic
     * (company/currency/sub-category/date-range filter on cash_expenses,
     * joined to its category & sub-category names) is UNCHANGED from the
     * original controller — only extracted into its own method so result()
     * and exportExcel() share one query.
     *
     * Returns null when no rows match (caller decides how to respond).
     */
    private function fetchStatementRows(Company $company, Request $request): ?array
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $currency = $request->get('currency');
        $cashExpenseCategoryIds = $request->get('cash_expense_category_name_id', []);

        $results = DB::table('cash_expenses')
            ->where('cash_expenses.company_id', $company->id)
            ->where('currency', $currency)
            ->where('payment_date', '>=', $startDate)
            ->where('payment_date', '<=', $endDate)
            ->whereIn('cash_expense_category_name_id', $cashExpenseCategoryIds)
            ->orderByRaw('payment_date asc')
            ->join('cash_expense_category_names', 'cash_expense_category_names.id', '=', 'cash_expenses.cash_expense_category_name_id')
            ->join('cash_expense_categories', 'cash_expense_categories.id', '=', 'cash_expense_category_names.cash_expense_category_id')
            ->selectRaw('cash_expenses.*,cash_expense_category_names.name as sub_category_name , cash_expense_categories.name as main_category_name')
            ->get();

        if (! count($results)) {
            return null;
        }

        return ['results' => $results, 'currency' => $currency];
    }

    /**
     * Shapes one raw cash_expenses row into the plain array both the
     * on-screen table (via result()) and the Excel export (via
     * exportExcel()) read from. Reviewed/Comment come straight off the
     * row's own is_reviewed/reviewed_by/comment_en/comment_ar/user_comment
     * columns — no join-based lookup helper needed here (unlike Bank/Safe
     * Statement, whose statement tables only carry a foreign key to
     * whichever record actually holds those fields).
     */
    private function mapStatementRow($row, string $lang): array
    {
        $reviewedArr = ['is_reviewed' => $row->is_reviewed ?? null];

        return [
            'id' => $row->id,
            'date' => Carbon::make($row->payment_date)->format('d-m-Y'),
            'mainCategoryName' => $row->main_category_name,
            'subCategoryName' => $row->sub_category_name,
            'supplierName' => $row->supplier_name,
            'paidAmount' => (float) ($row->paid_amount ?? 0),
            'withholdAmount' => (float) ($row->total_withhold_amount ?? 0),
            'amountInPayingCurrency' => (float) ($row->amount_in_paying_currency ?? 0),
            'exchangeRate' => (float) ($row->exchange_rate ?? 1),
            'reviewedText' => getReviewedText($reviewedArr),
            'comment' => $row->{'comment_'.$lang} ?? null,
            'userComment' => $row->user_comment ?? null,
        ];
    }

    /**
     * The report itself. Query logic lives in fetchStatementRows() and is
     * UNCHANGED from the original controller.
     */
    public function result(Company $company, Request $request)
    {
        $data = $this->fetchStatementRows($company, $request);
        if (is_null($data)) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }
        $results = $data['results'];

        $kpis = [
            'totalPaidAmount' => (float) $results->sum('paid_amount'),
            'totalWithholdAmount' => (float) $results->sum('total_withhold_amount'),
            'transactionCount' => $results->count(),
        ];

        $lang = app()->getLocale();
        $paginator = $this->paginateCollection($results, 50, $request);
        $paginator->getCollection()->transform(fn ($row) => $this->mapStatementRow($row, $lang));

        return \Inertia\Inertia::render('Statements/CashExpenseStatement/Result', [
            'company' => ['id' => $company->id],
            'currency' => $data['currency'],
            'kpis' => $kpis,
            'paginator' => $paginator->toArray(),
            'urls' => [
                'backUrl' => route('view.cash.expense.statement', ['company' => $company->id]),
                'exportUrl' => route('export.cash.expense.statement', array_merge(['company' => $company->id], $request->only([
                    'start_date', 'end_date', 'currency', 'cash_expense_category_name_id',
                ]))),
            ],
        ]);
    }

    /**
     * Excel export (project-owner requested) — same filters as result(),
     * reusing fetchStatementRows()/mapStatementRow(). Built on the shared
     * App\Exports\Statements\AbstractStatementExport styling base — no new
     * export library introduced.
     */
    public function exportExcel(Company $company, Request $request)
    {
        $data = $this->fetchStatementRows($company, $request);
        if (is_null($data)) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }
        $lang = app()->getLocale();

        $headings = ['#', 'Date', 'Main Category', 'Sub Category', 'Supplier Name', 'Paid Amount', 'Withhold Amount', 'Amount In Paying Currency', 'Exchange Rate', 'Reviewed', 'Comment'];

        $rows = $data['results']->values()->map(function ($row, $index) use ($lang) {
            $mapped = $this->mapStatementRow($row, $lang);

            return [
                '#' => $index + 1,
                'Date' => $mapped['date'],
                'Main Category' => $mapped['mainCategoryName'],
                'Sub Category' => $mapped['subCategoryName'],
                'Supplier Name' => $mapped['supplierName'],
                'Paid Amount' => $mapped['paidAmount'],
                'Withhold Amount' => $mapped['withholdAmount'],
                'Amount In Paying Currency' => $mapped['amountInPayingCurrency'],
                'Exchange Rate' => $mapped['exchangeRate'],
                'Reviewed' => $mapped['reviewedText'],
                'Comment' => trim(($mapped['comment'] ?? '').' '.($mapped['userComment'] ?? '')),
            ];
        });

        $fileNameParts = ['Cash-Expense-Statement', strtoupper((string) $data['currency'])];
        $fileName = preg_replace('/[^A-Za-z0-9\-]+/', '-', implode('-', $fileNameParts)).'.xlsx';

        return (new CashExpenseStatementExport($headings, $rows))->download($fileName);
    }
}
