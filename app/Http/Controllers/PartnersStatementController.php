<?php

namespace App\Http\Controllers;

use App\Exports\Statements\PartnersStatementExport;
use App\Models\Company;
use App\Models\Partner;
use App\Traits\GeneralFunctions;
use App\Traits\PaginatesRawCollections;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
 *                           Query logic (fetchStatementGroups()) is UNCHANGED
 *                           from the original controller except the two
 *                           confirmed bug fixes above. Real GET instead of
 *                           POST — safe here, since nothing else references
 *                           result.partners.statement except this page's own
 *                           (now-superseded) Blade form.
 *   - exportExcel()       → ✅ New (project-owner requested). Styled via the
 *                           new App\Exports\Statements\PartnersStatementExport
 *                           — a dedicated class (not the shared
 *                           AbstractStatementExport base), since this report's
 *                           grouped-by-many-partners shape and totals don't fit
 *                           that single-ledger contract. Same visual language
 *                           (colors, banding, header style) by hand.
 *
 * Pagination note: unlike Bank/Safe/Cash Expense Statement, rows here
 * are naturally grouped by partner (each partner's own transactions
 * must stay together, not be split mid-group across pages). So
 * PaginatesRawCollections is applied at the PARTNER level (10 partner
 * groups per page), not the individual-row level — a person who
 * selects many partners still gets real server-side pagination, and
 * every rendered partner's full transaction list is always complete,
 * never truncated mid-group.
 */
class PartnersStatementController
{
    use GeneralFunctions;
    use PaginatesRawCollections;

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
     * Builds the raw, grouped-by-partner result set for the current
     * filters. Query per partner (fetchStatementGroups()) is UNCHANGED from
     * the original controller except the confirmed `.company_id` typo fix
     * (see class docblock, bug #1).
     *
     * Returns null when no partner has any matching transactions.
     */
    private function fetchStatementGroups(Company $company, Request $request): ?array
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $partnerType = $request->get('partner_type');
        $currency = $request->get('currency');
        $partnerIds = (array) $request->get('partner_id', []);

        $statementTableName = self::STATEMENT_TABLE_BY_TYPE[$partnerType] ?? null;
        if (! $statementTableName) {
            return null;
        }

        $groups = [];
        foreach ($partnerIds as $partnerId) {
            $partner = Partner::find($partnerId);
            if (! $partner) {
                continue;
            }
            $currentResult = DB::table($statementTableName)
                ->where('company_id', $company->id) // fixed: was '.company_id' (leading-dot typo, see class docblock)
                ->where('currency_name', $currency)
                ->where('partner_id', $partnerId)
                ->where('date', '>=', $startDate)
                ->where('date', '<=', $endDate)
                ->orderByRaw('full_date asc , created_at asc')
                ->get();

            if (count($currentResult)) {
                $groups[] = [
                    'partnerId' => $partner->id,
                    'partnerName' => $partner->getName(),
                    'rows' => $currentResult,
                ];
            }
        }

        if (! count($groups)) {
            return null;
        }

        return ['groups' => $groups, 'currency' => $currency];
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
     * The report itself. Query logic lives in fetchStatementGroups() and
     * is UNCHANGED except the two confirmed bug fixes noted in the class
     * docblock. Pagination is by PARTNER GROUP (10 per page), not by row —
     * see class docblock.
     */
    public function result(Company $company, Request $request)
    {
        $data = $this->fetchStatementGroups($company, $request);
        if (is_null($data)) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }
        $groups = collect($data['groups']);
        $lang = app()->getLocale();

        /**
         * KPI totals computed from the FULL (unpaginated) set of every
         * selected partner's rows — matches the original Blade page's own
         * running totals exactly: Debit/Credit are summed across every row
         * of every partner; the ending-balance total is the SUM of each
         * partner's own most recent (last chronological) row's end_balance
         * — not one single reference, since this report spans many
         * partners/accounts at once, unlike Bank/Safe Statement's single
         * account.
         */
        $totalDebit = 0.0;
        $totalCredit = 0.0;
        $totalEndBalance = 0.0;
        $transactionCount = 0;
        foreach ($groups as $group) {
            $rows = collect($group['rows']);
            $totalDebit += (float) $rows->sum('debit');
            $totalCredit += (float) $rows->sum('credit');
            $totalEndBalance += (float) ($rows->last()->end_balance ?? 0);
            $transactionCount += $rows->count();
        }
        $kpis = [
            'partnerCount' => $groups->count(),
            'transactionCount' => $transactionCount,
            'totalDebit' => $totalDebit,
            'totalCredit' => $totalCredit,
            'totalEndBalance' => $totalEndBalance,
        ];

        $paginator = $this->paginateCollection($groups, 10, $request);
        $paginator->getCollection()->transform(function ($group) use ($lang) {
            $rows = collect($group['rows'])->map(fn ($row) => $this->mapStatementRow($row, $lang))->values();

            return [
                'partnerId' => $group['partnerId'],
                'partnerName' => $group['partnerName'],
                'rows' => $rows,
            ];
        });

        return \Inertia\Inertia::render('Statements/PartnersStatement/Result', [
            'company' => ['id' => $company->id],
            'currency' => $data['currency'],
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
     * Excel export (project-owner requested) — same filters as result(),
     * reusing fetchStatementGroups()/mapStatementRow() so the workbook can
     * never drift from what's on screen. Exports every selected partner's
     * FULL transaction list (not just the currently-viewed page of partner
     * groups). Flattened to one sheet with a "Partner" column (rather than
     * the on-screen collapsible groups), since a flat sheet is more useful
     * for real spreadsheet work (sorting/filtering/pivoting by partner)
     * than nested, hidden groups would be.
     */
    public function exportExcel(Company $company, Request $request)
    {
        $data = $this->fetchStatementGroups($company, $request);
        if (is_null($data)) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }
        $lang = app()->getLocale();

        $headings = ['Partner', '#', 'Date', 'Beginning Balance', 'Debit', 'Credit', 'End Balance', 'Comment'];

        $rows = collect();
        $endingBalanceTotal = 0.0;
        foreach ($data['groups'] as $group) {
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

        $fileNameParts = ['Partners-Statement', strtoupper((string) $data['currency'])];
        $fileName = preg_replace('/[^A-Za-z0-9\-]+/', '-', implode('-', $fileNameParts)).'.xlsx';

        return (new PartnersStatementExport($headings, $rows, $endingBalanceTotal))->download($fileName);
    }
}
