<?php

namespace App\Http\Controllers;

use App\Exports\Statements\PartnersStatementExport;
use App\Models\Company;
use App\Models\Partner;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;

/**
 * PartnersStatementController
 * ------------------------------------------------------------------
 * Renders the "Partner Statement" report (Statements sidebar section)
 * — a running-balance ledger, like Bank/Safe Statement, but for one or
 * MORE non-bank partners at once (Subsidiary Company, Shareholder,
 * Employee, Other Partner, or Taxes & Insurance), each read from its
 * own dedicated statement table. Grouped by partner, not a single flat
 * ledger — genuinely different shape from Bank/Safe Statement.
 *
 * ⚠️ Two real, confirmed, BLOCKING bugs found and fixed while migrating
 * this page (both mean this report could never have worked, in
 * production, before now):
 *   1. result()'s query filtered `->where('.company_id', $company->id)`
 *      — a literal leading dot before the column name. MySQL has no
 *      column named `.company_id`, so every single request to this
 *      page's query threw a real SQL error. Fixed by removing the stray
 *      dot (`company_id`) — this is a hard typo, not a business-logic
 *      judgment call, so fixed at the root rather than left for a
 *      separate decision.
 *   2. The original Blade view's Comment column called
 *      `\App\Helpers\HNonBanking::getUserCommentFromModel(...)` — that
 *      class does not exist anywhere in this codebase (confirmed via a
 *      project-wide search). Every render of this page's result table
 *      would have fatal-errored on the very first row. Fixed by using
 *      the real, existing `\App\Helpers\HVero::getUserCommentFromModel()`
 *      — the same helper Bank/Safe Statement already use for the exact
 *      same purpose, and clearly the intended target of whatever
 *      rename/copy-paste left `HNonBanking` behind.
 *
 * ── Frontend migration status ───────────────────────────────────
 *   - index()            → ✅ Migrated to Inertia (Statements/PartnersStatement/Index)
 *   - getPartnersByType() → ✅ New. Small JSON lookup powering the Partner
 *                           Type → Partners cascading multi-select, mirroring
 *                           the same cascading-dropdown pattern used elsewhere
 *                           (e.g. BankStatementController@getAccountNumbers).
 *   - result()            → ✅ Migrated to Inertia (Statements/PartnersStatement/Result).
 *                           Real GET instead of POST. Pagination is at the
 *                           PARTNER level (10 groups/page): one SQL query
 *                           finds partners with rows, those IDs are paginated,
 *                           and only the current page's partners are loaded
 *                           via a single whereIn — not N full-table gets.
 *                           KPI totals still cover every selected partner
 *                           with activity (SQL aggregates + last end_balance
 *                           per partner), never just the page on screen.
 *   - exportExcel()       → ✅ New (project-owner requested). Styled via the
 *                           new App\Exports\Statements\PartnersStatementExport
 *                           — a dedicated class (not the shared
 *                           AbstractStatementExport base), since this report's
 *                           grouped-by-many-partners shape and totals don't fit
 *                           that single-ledger contract. Same visual language
 *                           (colors, banding, header style) by hand. Export
 *                           uses one whereIn for all active partners.
 *
 * Pagination note: unlike Bank/Safe/Cash Expense Statement, rows here
 * are naturally grouped by partner (each partner's own transactions
 * must stay together, not be split mid-group across pages). Partner
 * IDs are paginated (10 per page); every rendered partner's full
 * transaction list is always complete, never truncated mid-group.
 */
class PartnersStatementController
{
    use GeneralFunctions;

    private const PARTNERS_PER_PAGE = 10;

    private const PARTNER_TYPES = [
        'is_subsidiary_company' => 'Subsidiary Company',
        'is_shareholder' => 'Shareholder',
        'is_employee' => 'Employee',
        'is_other_partner' => 'Other Partner',
        'is_tax' => 'Taxes & Insurance',
    ];

    private const STATEMENT_TABLE_BY_TYPE = [
        'is_subsidiary_company' => 'subsidiary_company_statements',
        'is_shareholder' => 'shareholder_statements',
        'is_employee' => 'employee_statements',
        'is_other_partner' => 'other_partner_statements',
        'is_tax' => 'tax_statements',
    ];

    /**
     * Filter form: Partner Type → Partners (multi-select, cascading) →
     * Currency → date range. Renders Statements/PartnersStatement/Index.vue.
     */
    public function index(Company $company)
    {
        $partnerTypes = collect(self::PARTNER_TYPES)->map(fn ($label, $key) => ['value' => $key, 'title' => __($label)])->values();

        return \Inertia\Inertia::render('Statements/PartnersStatement/Index', [
            'company' => ['id' => $company->id],
            'partnerTypes' => $partnerTypes,
            'currencies' => getCurrency(),
            'urls' => [
                'result' => route('result.partners.statement', ['company' => $company->id]),
                'partnersByType' => route('partners.statement.partners.by.type', ['company' => $company->id]),
            ],
        ]);
    }

    /**
     * JSON lookup for the Partners multi-select, once a Partner Type is
     * chosen. $partnerType is validated against the same fixed whitelist
     * used everywhere else on this page — never passed straight into a
     * dynamic where() column name unchecked.
     */
    public function getPartnersByType(Company $company, Request $request)
    {
        $partnerType = $request->get('partner_type');
        if (! array_key_exists($partnerType, self::PARTNER_TYPES)) {
            return response()->json(['status' => true, 'data' => []]);
        }

        $partners = Partner::onlyForCompany($company->id)->where($partnerType, 1)->get();

        return response()->json([
            'status' => true,
            'data' => $partners->map(fn ($p) => ['id' => $p->id, 'name' => $p->getName()])->values(),
        ]);
    }

    /**
     * Shared filter resolution for result() and exportExcel(). Returns null
     * when the partner type is unknown or no partner_id list was sent.
     *
     * @return array{table: string, currency: string, startDate: mixed, endDate: mixed, partnerIds: array<int>}|null
     */
    private function resolveFilters(Company $company, Request $request): ?array
    {
        $partnerType = $request->get('partner_type');
        $statementTableName = self::STATEMENT_TABLE_BY_TYPE[$partnerType] ?? null;
        if (! $statementTableName) {
            return null;
        }

        $partnerIds = array_values(array_filter(
            array_map('intval', (array) $request->get('partner_id', [])),
            fn (int $id) => $id > 0
        ));

        if (! count($partnerIds)) {
            return null;
        }

        return [
            'table' => $statementTableName,
            'currency' => $request->get('currency'),
            'startDate' => $request->get('start_date'),
            'endDate' => $request->get('end_date'),
            'partnerIds' => $partnerIds,
            'companyId' => $company->id,
        ];
    }

    /**
     * Base query for one statement table under the current filters.
     * Callers add select/order/group as needed — never share one builder
     * across aggregate + fetch (paginate/sum mutate the instance).
     *
     * @param  array{table: string, currency: mixed, startDate: mixed, endDate: mixed, partnerIds: array<int>, companyId: int}  $filters
     * @param  array<int>|null  $limitPartnerIds  when set, further restrict to this page's partners
     */
    private function statementQuery(array $filters, ?array $limitPartnerIds = null)
    {
        $partnerIds = $limitPartnerIds ?? $filters['partnerIds'];

        return DB::table($filters['table'])
            ->where('company_id', $filters['companyId'])
            ->where('currency_name', $filters['currency'])
            ->whereIn('partner_id', $partnerIds)
            ->where('date', '>=', $filters['startDate'])
            ->where('date', '<=', $filters['endDate']);
    }

    /**
     * Selected partners that actually have rows in range, in the same
     * order the request listed them (so page 1 is stable across reloads).
     *
     * @param  array{table: string, currency: mixed, startDate: mixed, endDate: mixed, partnerIds: array<int>, companyId: int}  $filters
     * @return array<int>
     */
    private function activePartnerIds(array $filters): array
    {
        $withRows = $this->statementQuery($filters)
            ->distinct()
            ->pluck('partner_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $withRowsSet = array_flip($withRows);

        return array_values(array_filter(
            $filters['partnerIds'],
            fn (int $id) => isset($withRowsSet[$id])
        ));
    }

    /**
     * KPI totals over every selected partner with activity — not the
     * current page. Debit/credit/count are plain SUMs; ending balance is
     * the sum of each partner's chronologically last end_balance, which
     * is what taking last() of a full_date-asc collection used to mean.
     *
     * @param  array{table: string, currency: mixed, startDate: mixed, endDate: mixed, partnerIds: array<int>, companyId: int}  $filters
     * @param  array<int>  $activePartnerIds
     * @return array{partnerCount: int, transactionCount: int, totalDebit: float, totalCredit: float, totalEndBalance: float}
     */
    private function statementKpis(array $filters, array $activePartnerIds): array
    {
        $partnerCount = count($activePartnerIds);
        if ($partnerCount === 0) {
            return [
                'partnerCount' => 0,
                'transactionCount' => 0,
                'totalDebit' => 0.0,
                'totalCredit' => 0.0,
                'totalEndBalance' => 0.0,
            ];
        }

        $sums = $this->statementQuery($filters, $activePartnerIds)
            ->selectRaw('COALESCE(SUM(debit), 0) AS total_debit, COALESCE(SUM(credit), 0) AS total_credit, COUNT(*) AS transaction_count')
            ->first();

        // Table name is whitelist-only (STATEMENT_TABLE_BY_TYPE); values are bound.
        $table = $filters['table'];
        $placeholders = implode(',', array_fill(0, count($activePartnerIds), '?'));
        $ranked = DB::select(
            'SELECT COALESCE(SUM(end_balance), 0) AS total_end_balance FROM (
                SELECT end_balance,
                    ROW_NUMBER() OVER (
                        PARTITION BY partner_id
                        ORDER BY full_date DESC, created_at DESC, id DESC
                    ) AS rn
                FROM '.$table.'
                WHERE company_id = ?
                  AND currency_name = ?
                  AND partner_id IN ('.$placeholders.')
                  AND date >= ?
                  AND date <= ?
            ) ranked WHERE rn = 1',
            array_merge(
                [$filters['companyId'], $filters['currency']],
                $activePartnerIds,
                [$filters['startDate'], $filters['endDate']]
            )
        );

        return [
            'partnerCount' => $partnerCount,
            'transactionCount' => (int) ($sums->transaction_count ?? 0),
            'totalDebit' => (float) ($sums->total_debit ?? 0),
            'totalCredit' => (float) ($sums->total_credit ?? 0),
            'totalEndBalance' => (float) ($ranked[0]->total_end_balance ?? 0),
        ];
    }

    /**
     * Load statement rows for the given partners (one query), group them
     * preserving $partnerIds order, and attach partner names in one batch.
     *
     * @param  array{table: string, currency: mixed, startDate: mixed, endDate: mixed, partnerIds: array<int>, companyId: int}  $filters
     * @param  array<int>  $partnerIds
     * @return array<int, array{partnerId: int, partnerName: string|null, rows: \Illuminate\Support\Collection}>
     */
    private function buildGroups(array $filters, array $partnerIds): array
    {
        if (! count($partnerIds)) {
            return [];
        }

        $rows = $this->statementQuery($filters, $partnerIds)
            ->orderBy('partner_id')
            ->orderByRaw('full_date asc, created_at asc, id asc')
            ->get()
            ->groupBy('partner_id');

        $partners = Partner::whereIn('id', $partnerIds)->get()->keyBy('id');

        $groups = [];
        foreach ($partnerIds as $partnerId) {
            $partnerRows = $rows->get($partnerId);
            if (! $partnerRows || ! count($partnerRows)) {
                continue;
            }
            $partner = $partners->get($partnerId);
            $groups[] = [
                'partnerId' => $partnerId,
                'partnerName' => $partner ? $partner->getName() : null,
                'rows' => $partnerRows->values(),
            ];
        }

        return $groups;
    }

    /**
     * Shapes one raw statement row into the plain array both the on-screen
     * table (via result()) and the Excel export (via exportExcel()) read
     * from. No Reviewed column on this report (confirmed from the
     * original's real, active markup — a Reviewed column exists only in
     * a large, already-dead, commented-out draft further down the same
     * Blade file, never in what actually renders).
     */
    private function mapStatementRow($row, string $lang): array
    {
        return [
            'id' => $row->id,
            'date' => Carbon::make($row->date)->format('d-m-Y'),
            'beginningBalance' => (float) ($row->beginning_balance ?? 0),
            'debit' => (float) ($row->debit ?? 0),
            'credit' => (float) ($row->credit ?? 0),
            'endBalance' => (float) ($row->end_balance ?? 0),
            'comment' => (isset($row->{'comment_'.$lang}) ? $row->{'comment_'.$lang} : null) ?: getBankStatementComment($row),
            'userComment' => \App\Helpers\HVero::getUserCommentFromModel($row), // fixed: was \App\Helpers\HNonBanking (class doesn't exist), see class docblock
        ];
    }

    /**
     * The report itself. Partner IDs with activity are paginated (10 per
     * page); only that page's ledgers leave the database. KPIs still
     * describe every selected partner with rows in the date range.
     */
    public function result(Company $company, Request $request)
    {
        $filters = $this->resolveFilters($company, $request);
        if (is_null($filters)) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }

        $activePartnerIds = $this->activePartnerIds($filters);
        if (! count($activePartnerIds)) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }

        $page = max(1, (int) Paginator::resolveCurrentPage('page'));
        $perPage = self::PARTNERS_PER_PAGE;
        $totalPartners = count($activePartnerIds);
        $pagePartnerIds = array_slice($activePartnerIds, ($page - 1) * $perPage, $perPage);

        $lang = app()->getLocale();
        $groups = collect($this->buildGroups($filters, $pagePartnerIds))->map(function (array $group) use ($lang) {
            return [
                'partnerId' => $group['partnerId'],
                'partnerName' => $group['partnerName'],
                'rows' => collect($group['rows'])->map(fn ($row) => $this->mapStatementRow($row, $lang))->values(),
            ];
        });

        $paginator = (new LengthAwarePaginator(
            $groups,
            $totalPartners,
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']
        ))->withQueryString();

        $kpis = $this->statementKpis($filters, $activePartnerIds);

        return \Inertia\Inertia::render('Statements/PartnersStatement/Result', [
            'company' => ['id' => $company->id],
            'currency' => $filters['currency'],
            'kpis' => $kpis,
            'paginator' => $paginator->toArray(),
            'urls' => [
                'backUrl' => route('view.partners.statement', ['company' => $company->id]),
                'exportUrl' => route('export.partners.statement', array_merge(['company' => $company->id], $request->only([
                    'start_date', 'end_date', 'partner_type', 'currency', 'partner_id',
                ]))),
            ],
        ]);
    }

    /**
     * Excel export — same filters as result(), one whereIn for every
     * partner with activity (full range, not the current page). Flattened
     * to one sheet with a Partner column for sorting/filtering/pivoting.
     */
    public function exportExcel(Company $company, Request $request)
    {
        $filters = $this->resolveFilters($company, $request);
        if (is_null($filters)) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }

        $activePartnerIds = $this->activePartnerIds($filters);
        if (! count($activePartnerIds)) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }

        $lang = app()->getLocale();
        $groups = $this->buildGroups($filters, $activePartnerIds);

        $headings = ['Partner', '#', 'Date', 'Beginning Balance', 'Debit', 'Credit', 'End Balance', 'Comment'];

        $rows = collect();
        $endingBalanceTotal = 0.0;
        foreach ($groups as $group) {
            $groupRows = collect($group['rows']);
            $endingBalanceTotal += (float) ($groupRows->last()->end_balance ?? 0);

            foreach ($groupRows->values() as $index => $row) {
                $mapped = $this->mapStatementRow($row, $lang);
                $rows->push([
                    'Partner' => $group['partnerName'],
                    '#' => $index + 1,
                    'Date' => $mapped['date'],
                    'Beginning Balance' => $mapped['beginningBalance'],
                    'Debit' => $mapped['debit'],
                    'Credit' => $mapped['credit'],
                    'End Balance' => $mapped['endBalance'],
                    'Comment' => trim(($mapped['comment'] ?? '').' '.($mapped['userComment'] ?? '')),
                ]);
            }
        }

        $fileNameParts = ['Partners-Statement', strtoupper((string) $filters['currency'])];
        $fileName = preg_replace('/[^A-Za-z0-9\-]+/', '-', implode('-', $fileNameParts)).'.xlsx';

        return (new PartnersStatementExport($headings, $rows, $endingBalanceTotal))->download($fileName);
    }
}
