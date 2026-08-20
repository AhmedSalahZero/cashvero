<?php

namespace App\Http\Controllers;

use App\Exports\Statements\CashExpenseStatementExport;
use App\Models\CashExpenseCategory;
use App\Models\Company;
use App\Support\ShareholderAccounts\AccountNumberLabel;
use App\Traits\GeneralFunctions;
use App\Traits\PaginatesStatementQueries;
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
 *                     PaginatesStatementQueries) and GET instead of POST are new,
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
    use PaginatesStatementQueries;

    private const STATEMENT_TABLE = 'cash_expenses';

    private const ROWS_PER_PAGE = 50;

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

        $freshQuery = fn () => DB::table(self::STATEMENT_TABLE)
            ->where('cash_expenses.company_id', $company->id)
            ->where('currency', $currency)
            ->where('payment_date', '>=', $startDate)
            ->where('payment_date', '<=', $endDate)
            ->whereIn('cash_expense_category_name_id', $cashExpenseCategoryIds)
            // `payment_date` alone is not a stable sort: same-day expenses
            // could swap places between page 1 and page 2 and be shown twice
            // or not at all. The id tiebreaker makes the order total.
            ->orderByRaw('payment_date asc, cash_expenses.id asc')
            ->join('cash_expense_category_names', 'cash_expense_category_names.id', '=', 'cash_expenses.cash_expense_category_name_id')
            ->join('cash_expense_categories', 'cash_expense_categories.id', '=', 'cash_expense_category_names.cash_expense_category_id')
            ->selectRaw('cash_expenses.*,cash_expense_category_names.name as sub_category_name , cash_expense_categories.name as main_category_name');

        if (! $freshQuery()->exists()) {
            return null;
        }

        return ['query' => $freshQuery, 'currency' => $currency];
    }

    /**
     * Shapes one raw cash_expenses row into the plain array both the
     * on-screen table (via result()) and the Excel export (via
     * exportExcel()) read from.
     *
     * Feature (client requested, 2026-08-15): dropped Supplier Name,
     * Withhold Amount, Amount In Paying Currency, and Reviewed — the
     * client no longer wants these on this report. Added Currency, and
     * (conditionally, only when the filtered currency isn't the
     * company's main functional currency) Equivalent In Main Currency,
     * computed as paidAmount * exchangeRate — cash_expenses has no
     * dedicated "amount in main currency" column, so this mirrors the
     * same multiplication the DB triggers use elsewhere in the app for
     * the equivalent conversion.
     */
    private function mapStatementRow($row, string $lang): array
    {
        return [
            'id' => $row->id,
            'date' => Carbon::make($row->payment_date)->format('d-m-Y'),
            'mainCategoryName' => $row->main_category_name,
            'subCategoryName' => $row->sub_category_name,
            'currency' => $row->currency,
            'paidAmount' => (float) ($row->paid_amount ?? 0),
            'exchangeRate' => (float) ($row->exchange_rate ?? 1),
            'equivalentInMainCurrency' => (float) ($row->paid_amount ?? 0) * (float) ($row->exchange_rate ?? 1),
            'comment' => AccountNumberLabel::decorateText(
                (int) ($row->company_id ?? 0),
                $row->{'comment_'.$lang} ?? null
            ),
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
        // Totals stay range-wide; they are SQL SUMs over the same WHERE
        // clause now rather than sums of a fully hydrated collection.
        $paginator = $this->paginateStatement($data['query'], self::ROWS_PER_PAGE);
        $sums = $this->statementSums($data['query'], [
            'total_paid' => self::STATEMENT_TABLE.'.paid_amount',
        ]);

        $kpis = [
            'totalPaidAmount' => $sums['total_paid'],
            'transactionCount' => $paginator->total(),
        ];

        $lang = app()->getLocale();
        $paginator->getCollection()->transform(fn ($row) => $this->mapStatementRow($row, $lang));

        // Feature (client requested, 2026-08-15): the filtered currency is
        // fixed for the whole report (one currency per view, same as
        // before), so whether to show Exchange Rate / Equivalent In Main
        // Currency at all is decided once here rather than per row —
        // main-currency amounts always have exchange_rate = 1, so those
        // columns would show nothing useful.
        $isMainCurrency = strtoupper((string) $data['currency']) === strtoupper($company->getMainFunctionalCurrency());

        return \Inertia\Inertia::render('Statements/CashExpenseStatement/Result', [
            'company' => ['id' => $company->id],
            'currency' => $data['currency'],
            'isMainCurrency' => $isMainCurrency,
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
        $isMainCurrency = strtoupper((string) $data['currency']) === strtoupper($company->getMainFunctionalCurrency());

        $headings = ['#', 'Date', 'Main Category', 'Sub Category', 'Currency', 'Paid Amount'];
        if (! $isMainCurrency) {
            $headings[] = 'Exchange Rate';
            $headings[] = 'Equivalent In Main Currency';
        }
        $headings[] = 'Comment';

        // The workbook is the whole range, not the page on screen, so the
        // export runs the same query unpaginated.
        $rows = $data['query']()->get()->values()->map(function ($row, $index) use ($lang, $isMainCurrency) {
            $mapped = $this->mapStatementRow($row, $lang);

            $out = [
                '#' => $index + 1,
                'Date' => $mapped['date'],
                'Main Category' => $mapped['mainCategoryName'],
                'Sub Category' => $mapped['subCategoryName'],
                'Currency' => $mapped['currency'],
                'Paid Amount' => $mapped['paidAmount'],
            ];
            if (! $isMainCurrency) {
                $out['Exchange Rate'] = $mapped['exchangeRate'];
                $out['Equivalent In Main Currency'] = $mapped['equivalentInMainCurrency'];
            }
            $out['Comment'] = trim(($mapped['comment'] ?? '').' '.($mapped['userComment'] ?? ''));

            return $out;
        });

        $fileNameParts = ['Cash-Expense-Statement', strtoupper((string) $data['currency'])];
        $fileName = preg_replace('/[^A-Za-z0-9\-]+/', '-', implode('-', $fileNameParts)).'.xlsx';

        return (new CashExpenseStatementExport($headings, $rows))->download($fileName);
    }
}
