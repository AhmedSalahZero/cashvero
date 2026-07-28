<?php

namespace App\Http\Controllers;

use App\Exports\Statements\WithdrawalStatementExport;
use App\Models\AccountType;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Traits\GeneralFunctions;
use App\Traits\PaginatesStatementQueries;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * WithdrawalsSettlementReportController
 * ------------------------------------------------------------------
 * Renders the "Withdrawal Statement" report (Statements sidebar
 * section, labeled "Withdrawals Settlement Report" in the original) —
 * a list of overdraft withdrawals and how much of each is still
 * outstanding ("Balance"/net_balance) vs. settled, for one or more
 * banks + one overdraft account type + currency, over a date range.
 * Not a running-balance ledger like Bank/Safe/Partner Statement — no
 * Beginning/End Balance or Debit/Credit columns here at all.
 *
 * ⚠️ This controller's result() is ALSO linked from the still-Blade
 * Cash Forecast dashboard (resources/views/admin/dashboard/forecast.blade.php)
 * as a plain `<form method="post">` — genuinely still in active use
 * from a second entry point, not just this page's own form. Its route
 * name/URI/HTTP verb (POST, `result.withdrawals.settlement.report`)
 * is therefore left completely UNCHANGED here, unlike Bank/Safe/Cash
 * Expense/Partners Statement's sibling result routes (which were
 * safely switched to GET since nothing else referenced them). Inertia
 * responds to a plain, non-Inertia browser POST the same way it
 * responds to any fresh page visit — a full HTML page boot — so the
 * Cash Forecast dashboard's existing link keeps working exactly as
 * before, now landing on this same modernized report.
 *
 * refreshReport() (the small ajax preview used by the Cash Forecast
 * dashboard's own "Withdrawal dues" widget) is UNCHANGED and untouched
 * — it has nothing to do with this page.
 *
 * ── Frontend migration status ───────────────────────────────────
 *   - index()        → ✅ Migrated to Inertia (Statements/WithdrawalStatement/Index)
 *   - result()        → ✅ Migrated to Inertia (Statements/WithdrawalStatement/Result).
 *                      Query logic (fetchWithdrawals()/getOverdraftWithdrawals())
 *                      is UNCHANGED. Pagination via PaginatesStatementQueries is new
 *                      (project-owner requested heavy-report handling; the
 *                      original had none at all — same gap Safe Statement had).
 *                      Since the shared route stays POST, pagination "page N"
 *                      links resubmit the same filters via POST with `page` in
 *                      the query string (Laravel's paginator reads `page` from
 *                      the query string regardless of HTTP verb) — see
 *                      Result.vue.
 *                      The original never redirected on empty results (no
 *                      "No Data Found" guard) — preserved exactly; an empty
 *                      state renders in the table instead.
 *   - exportExcel()   → ✅ New (project-owner requested). Styled via the new
 *                      App\Exports\Statements\WithdrawalStatementExport — same
 *                      shared AbstractStatementExport base as Bank/Safe/Cash
 *                      Expense Statement, with its own numeric/summable/
 *                      conditional-color column overrides for this report's
 *                      real columns (Withdrawal/Settlement Amount, Balance).
 *   - refreshReport() → UNCHANGED. Belongs to the Cash Forecast dashboard, not
 *                      this page.
 */
class WithdrawalsSettlementReportController
{
    const NUMBER_OF_INTERNAL_MONTHS = 6;
    use GeneralFunctions;
    use PaginatesStatementQueries;

    private const ROWS_PER_PAGE = 50;

    /**
     * Filter form: date range, Banks (multi-select), Account Type, Currency.
     * Renders Statements/WithdrawalStatement/Index.vue.
     */
    public function index(Company $company)
    {
        $financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyHasOverdrafts()->get();
        $accountTypes = AccountType::onlyOverdraftsAccounts()->where('id', '!=', 32)->get();

        return \Inertia\Inertia::render('Statements/WithdrawalStatement/Index', [
            'company' => ['id' => $company->id],
            'financialInstitutionBanks' => $financialInstitutionBanks->map(fn ($bank) => [
                'id' => $bank->id,
                'name' => $bank->getName(),
            ])->values(),
            'accountTypes' => $accountTypes->map(fn ($type) => [
                'id' => $type->id,
                'name' => $type->getName(),
            ])->values(),
            'currencies' => getBanksCurrencies(),
            'urls' => [
                // Same POST route the Cash Forecast dashboard also submits to
                // directly — see class docblock. Left completely unchanged.
                'result' => route('result.withdrawals.settlement.report', ['company' => $company->id]),
            ],
        ]);
    }

    /**
     * UNCHANGED — ajax preview used by the Cash Forecast dashboard's own
     * "Withdrawal dues" widget. Not related to this Statement page.
     */
    public function refreshReport(Company $company, Request $request)
    {
        $accountTypeId = $request->get('accountTypeId');
        $currencyName = $request->get('currencyName');
        $startDate = $request->get('withdrawalStartDate');
        $endDate = $request->get('withdrawalEndDate');
        $financialInstitutionIds = $company->financialInstitutions->pluck('id')->toArray();
        $overdraftWithdrawals = $this->getOverdraftWithdrawalsWithoutStartDate($startDate, $endDate, $currencyName, $accountTypeId, $company->id, $financialInstitutionIds);

        $overdraftWithdrawals = $overdraftWithdrawals->take(6);

        return response()->json([
            'status' => true,
            'data' => $overdraftWithdrawals,
        ]);
    }

    /**
     * UNCHANGED business logic — which overdraft withdrawals match the
     * given filters. Read by both result() (this page) and
     * getOverdraftWithdrawalsWithoutStartDate() (the Cash Forecast widget).
     */
    /**
     * The same query as getOverdraftWithdrawals() below, handed back as a
     * factory instead of a fetched Collection so result() and exportExcel()
     * can paginate and aggregate it in SQL. Every call returns a new
     * builder — paginate()/first()/sum() each mutate the one they run on.
     *
     * Also returns the two table names the caller needs to qualify columns
     * with: `credit` lives on the bank statement table while
     * `settlement_amount` and `net_balance` live on the withdrawals table,
     * and this query joins five tables.
     *
     * @return array{query: callable, withdrawalsTable: string, bankStatementTable: string}
     */
    protected function overdraftWithdrawalsQuery(string $startDate, string $endDate, string $currency, int $accountTypeId, int $companyId, array $financialInstitutionIds): array
    {
        $accountType = AccountType::find($accountTypeId);
        $fullClassName = ('\App\Models\\'.$accountType->model_name);
        $overdraftIds = $fullClassName::findByFinancialInstitutionIds($financialInstitutionIds);
        $foreignKeyName = $fullClassName::generateForeignKeyFormModelName();
        $withdrawalsTableName = $fullClassName::getWithdrawalTableName();
        $bankStatementTableName = $fullClassName::getBankStatementTableName();
        $bankStatementIdName = $fullClassName::getBankStatementIdName();

        $tableName = (new $fullClassName)->getTable();

        $freshQuery = fn () => DB::table($withdrawalsTableName)
            ->join($bankStatementTableName, $bankStatementIdName, '=', $bankStatementTableName.'.id')
            ->join($tableName, $bankStatementTableName.'.'.$foreignKeyName, '=', $tableName.'.id')
            ->join('financial_institutions', 'financial_institutions.id', '=', $tableName.'.financial_institution_id')
            ->join('banks', 'banks.id', '=', 'financial_institutions.bank_id')
            ->where($bankStatementTableName.'.company_id', $companyId)
            ->whereIn($bankStatementTableName.'.'.$foreignKeyName, $overdraftIds)
            ->whereBetween($bankStatementTableName.'.date', [$startDate, $endDate])
            ->where('currency', $currency)
            // `due_date` alone is not a stable sort: withdrawals sharing a
            // due date could swap places between page 1 and page 2 and be
            // shown twice or not at all. The id tiebreaker makes it total.
            ->orderByRaw('due_date asc, '.$withdrawalsTableName.'.id asc');

        return [
            'query' => $freshQuery,
            'withdrawalsTable' => $withdrawalsTableName,
            'bankStatementTable' => $bankStatementTableName,
        ];
    }

    /**
     * Fetched form, kept for the Cash Forecast widget below, which filters
     * and reshapes the rows as a Collection rather than paging them.
     */
    protected function getOverdraftWithdrawals(string $startDate, string $endDate, string $currency, int $accountTypeId, int $companyId, array $financialInstitutionIds)
    {
        return $this->overdraftWithdrawalsQuery($startDate, $endDate, $currency, $accountTypeId, $companyId, $financialInstitutionIds)['query']()->get();
    }

    /** UNCHANGED — used only by the Cash Forecast widget's refreshReport(). */
    protected function getOverdraftWithdrawalsWithoutStartDate(string $startDate, string $endDate, string $currency, int $accountTypeId, int $companyId, array $financialInstitutionIds)
    {
        return $this->getOverdraftWithdrawals($startDate, $endDate, $currency, $accountTypeId, $companyId, $financialInstitutionIds)->where('net_balance', '>', 0)->values();
    }

    /**
     * Shapes one raw withdrawal row into the plain array both the
     * on-screen table (via result()) and the Excel export (via
     * exportExcel()) read from.
     */
    private function mapWithdrawalRow($row, string $tableNameFormatted): array
    {
        return [
            'id' => $row->id ?? null,
            'bankName' => $row->name_en,
            'accountType' => $tableNameFormatted,
            'accountNumber' => $row->account_number,
            'withdrawalDate' => Carbon::make($row->date)->format('d-m-Y'),
            'withdrawalAmount' => (float) ($row->credit ?? 0),
            'settlementAmount' => (float) ($row->settlement_amount ?? 0),
            'balance' => (float) ($row->net_balance ?? 0),
            'dueDate' => $row->due_date ? Carbon::make($row->due_date)->format('d-m-Y') : null,
        ];
    }

    /**
     * The report itself. Query logic (getOverdraftWithdrawals()) is
     * UNCHANGED. Real server-side pagination is new — see class docblock.
     * Matches the original's behavior of never redirecting on empty
     * results (renders an empty state instead).
     */
    public function result(Company $company, Request $request)
    {
        $startDate = $request->get('withdrawal_start_date', $request->get('start_date'));
        $endDate = $request->get('withdrawal_end_date', $request->get('end_date'));
        $currency = $request->get('currency');
        $financialInstitutionIds = $request->get('financial_institution_ids', []);
        $accountTypeId = $request->get('account_type');
        $accountType = AccountType::find($accountTypeId);
        $fullClassName = ('\App\Models\\'.$accountType->model_name);
        $tableNameFormatted = $fullClassName::getTableNameFormatted();

        $withdrawals = $this->overdraftWithdrawalsQuery($startDate, $endDate, $currency, $accountTypeId, $company->id, $financialInstitutionIds);

        // Totals still cover the whole filtered range — they are SQL SUMs
        // over the same WHERE clause instead of sums of a hydrated
        // collection, so only this page's 50 rows leave the database.
        $paginator = $this->paginateStatement($withdrawals['query'], self::ROWS_PER_PAGE);
        $sums = $this->statementSums($withdrawals['query'], [
            'total_withdrawal' => $withdrawals['bankStatementTable'].'.credit',
            'total_settlement' => $withdrawals['withdrawalsTable'].'.settlement_amount',
            'total_outstanding' => $withdrawals['withdrawalsTable'].'.net_balance',
        ]);

        $kpis = [
            'totalWithdrawalAmount' => $sums['total_withdrawal'],
            'totalSettlementAmount' => $sums['total_settlement'],
            'totalOutstandingBalance' => $sums['total_outstanding'],
            'transactionCount' => $paginator->total(),
        ];

        $paginator->getCollection()->transform(fn ($row) => $this->mapWithdrawalRow($row, $tableNameFormatted));

        return \Inertia\Inertia::render('Statements/WithdrawalStatement/Result', [
            'company' => ['id' => $company->id],
            'tableNameFormatted' => $tableNameFormatted,
            'kpis' => $kpis,
            'paginator' => $paginator->toArray(),
            // Resubmitted on every "go to page N" click (see Result.vue) —
            // needed because the shared POST route can't use plain GET
            // pagination links (see class docblock).
            'filters' => [
                'withdrawal_start_date' => $startDate,
                'withdrawal_end_date' => $endDate,
                'currency' => $currency,
                'financial_institution_ids' => $financialInstitutionIds,
                'account_type' => $accountTypeId,
            ],
            'urls' => [
                'backUrl' => route('view.withdrawals.settlement.report', ['company' => $company->id]),
                'resultUrl' => route('result.withdrawals.settlement.report', ['company' => $company->id]),
                'exportUrl' => route('export.withdrawals.settlement.report', array_merge(['company' => $company->id], [
                    'withdrawal_start_date' => $startDate,
                    'withdrawal_end_date' => $endDate,
                    'currency' => $currency,
                    'financial_institution_ids' => $financialInstitutionIds,
                    'account_type' => $accountTypeId,
                ])),
            ],
        ]);
    }

    /**
     * Excel export (project-owner requested) — same filters as result(),
     * reusing getOverdraftWithdrawals()/mapWithdrawalRow(). Styled via the
     * shared AbstractStatementExport base — no new export library
     * introduced.
     */
    public function exportExcel(Company $company, Request $request)
    {
        $startDate = $request->get('withdrawal_start_date', $request->get('start_date'));
        $endDate = $request->get('withdrawal_end_date', $request->get('end_date'));
        $currency = $request->get('currency');
        $financialInstitutionIds = $request->get('financial_institution_ids', []);
        $accountTypeId = $request->get('account_type');
        $accountType = AccountType::find($accountTypeId);
        $fullClassName = ('\App\Models\\'.$accountType->model_name);
        $tableNameFormatted = $fullClassName::getTableNameFormatted();

        $overdraftWithdrawals = $this->getOverdraftWithdrawals($startDate, $endDate, $currency, $accountTypeId, $company->id, $financialInstitutionIds);

        $headings = ['#', 'Bank Name', 'Account Type', 'Account Number', 'Withdrawal Date', 'Withdrawal Amount', 'Settlement Amount', 'Balance', 'Due Date'];

        $rows = $overdraftWithdrawals->values()->map(function ($row, $index) use ($tableNameFormatted) {
            $mapped = $this->mapWithdrawalRow($row, $tableNameFormatted);

            return [
                '#' => $index + 1,
                'Bank Name' => $mapped['bankName'],
                'Account Type' => $mapped['accountType'],
                'Account Number' => $mapped['accountNumber'],
                'Withdrawal Date' => $mapped['withdrawalDate'],
                'Withdrawal Amount' => $mapped['withdrawalAmount'],
                'Settlement Amount' => $mapped['settlementAmount'],
                'Balance' => $mapped['balance'],
                'Due Date' => $mapped['dueDate'],
            ];
        });

        $fileNameParts = ['Withdrawal-Statement', $tableNameFormatted, strtoupper((string) $currency)];
        $fileName = preg_replace('/[^A-Za-z0-9\-]+/', '-', implode('-', $fileNameParts)).'.xlsx';

        return (new WithdrawalStatementExport($headings, $rows))->download($fileName);
    }
}
