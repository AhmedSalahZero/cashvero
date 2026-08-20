<?php

namespace App\Http\Controllers;

use App\Enums\LcTypes;
use App\Enums\LgTypes;
use App\Exports\Statements\LgLcStatementExport;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\LcOverdraftBankStatement;
use App\Models\LetterOfCreditFacility;
use App\Models\LetterOfCreditIssuance;
use App\Models\LetterOfGuaranteeIssuance;
use App\Support\ShareholderAccounts\AccountNumberLabel;
use App\Traits\GeneralFunctions;
use App\Traits\PaginatesStatementQueries;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * LGLCSBanktatementController
 * ------------------------------------------------------------------
 * Renders the "LG & LC Statement" report (Statements sidebar section)
 * — a running-balance ledger like Bank Statement, but for one of THREE
 * different underlying statement tables depending on Report Type:
 *   - LetterOfCreditIssuance → letter_of_credit_statements   (filtered
 *     by currency + bank)
 *   - LetterOfGuaranteeIssuance → letter_of_guarantee_statements (same)
 *   - LCOverdraft → lc_overdraft_bank_statements (filtered by a specific
 *     LC Facility instead of currency/bank — an LC Facility's overdraft
 *     is currency/bank-agnostic in a way the other two aren't)
 * Every column is already computed and stored per-row elsewhere in the
 * app — this controller only reads and presents it. Simpler than Bank
 * Statement: no Reviewed column, no inline-editable rows. Limit/Room
 * columns only exist for the LCOverdraft report type.
 *
 * Two cascading dropdowns power the form:
 *   - Report Type → Type/Source: getLgOrLcType() (UNCHANGED, existing)
 *   - Bank → LC Facility: LetterOfCreditFacilityController::
 *     getLcFacilityBasedOnFinancialInstitution() (UNCHANGED, existing,
 *     shared with other LC pages) — only relevant when Report Type is
 *     LCOverdraft.
 *
 * ⚠️ Confirmed dead code, not carried over: the original index() built
 * `$accountTypes = AccountType::onlyCashAccounts()->get()` and an
 * `accountType` request value, passed to the Blade view — but the
 * actual form (`lg_lc_statement_form.blade.php`) never renders an
 * Account Type field anywhere. Confirmed unused before dropping it;
 * no behavior change.
 *
 * ── Frontend migration status ───────────────────────────────────
 *   - index()          → ✅ Migrated to Inertia (Statements/LgLcStatement/Index)
 *   - getLgOrLcType()  → UNCHANGED — existing ajax endpoint, still used as-is.
 *   - result()          → ✅ Migrated to Inertia (Statements/LgLcStatement/Result).
 *                        Query logic (fetchStatementData()) UNCHANGED. Real
 *                        server-side pagination is NEW — the original never
 *                        paginated this report at all (every matching row was
 *                        sent to the Blade view at once), a genuine "heavy
 *                        report" gap the project owner asked to be fixed
 *                        project-wide. GET instead of the original's POST —
 *                        safe: nothing else references result.lg.lc.bank.statement
 *                        except this page's own (now-superseded) Blade form.
 *   - exportExcel()    → ✅ New (project-owner requested). Styled via the new
 *                        App\Exports\Statements\LgLcStatementExport — a thin
 *                        subclass of the shared AbstractStatementExport (its
 *                        defaults already match this report's exact column
 *                        set: Limit/Beginning Balance/Debit/Credit/Room/End
 *                        Balance).
 */
class LGLCSBanktatementController
{
    use GeneralFunctions;
    use PaginatesStatementQueries;

    private const ROWS_PER_PAGE = 50;

    private const STATEMENT_TABLE_BY_TYPE = [
        'LetterOfCreditIssuance' => 'letter_of_credit_statements',
        'LetterOfGuaranteeIssuance' => 'letter_of_guarantee_statements',
        'LCOverdraft' => 'lc_overdraft_bank_statements',
    ];

    /**
     * ⚠️ عمود الترتيب لازم يبقى نفس العمود اللي التريجر بيبني بيه سلسلة الأرصدة،
     * وإلا الصفوف تتعرض بترتيب مخالف للـ beginning/end balance المخزّنة.
     * letter_of_credit_statements و letter_of_guarantee_statements بيربطوا بالصف
     * السابق بـ full_date، أما lc_overdraft_bank_statements فبـ date.
     */
    private const ORDER_COLUMN_BY_TABLE = [
        'letter_of_credit_statements' => 'full_date',
        'letter_of_guarantee_statements' => 'full_date',
        'lc_overdraft_bank_statements' => 'date',
    ];

    private const TYPE_COLUMN_BY_REPORT_TYPE = [
        'LetterOfCreditIssuance' => 'lc_type',
        'LetterOfGuaranteeIssuance' => 'lg_type',
    ];

    /**
     * Filter form: date range, Currency, Bank, Report Type (cascades
     * Type/Source), and — only for LCOverdraft — LC Facility (cascades
     * from Bank). Renders Statements/LgLcStatement/Index.vue.
     */
    public function index(Company $company)
    {
        $financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get();

        return \Inertia\Inertia::render('Statements/LgLcStatement/Index', [
            'company' => ['id' => $company->id],
            'financialInstitutionBanks' => $financialInstitutionBanks->map(fn ($bank) => [
                'id' => $bank->id,
                'name' => $bank->getName(),
            ])->values(),
            'currencies' => getCurrency(),
            'reportTypes' => [
                'LetterOfCreditIssuance' => __('Letter Of Credit Bank Statement'),
                'LetterOfGuaranteeIssuance' => __('Letter Of Guarantee Bank Statement'),
                'LCOverdraft' => __('Letter Of Credit Overdraft Bank Statement'),
            ],
            'urls' => [
                'result' => route('result.lg.lc.bank.statement', ['company' => $company->id]),
                'lgOrLcTypes' => route('get.lc.or.lg.types', ['company' => $company->id]),
                'lcFacilitiesByBank' => route('get.lc.facility.based.on.financial.institution', ['company' => $company->id]),
            ],
        ]);
    }

    /**
     * UNCHANGED — existing ajax endpoint powering the Type/Source cascade.
     */
    public function getLgOrLcType(Request $request, Company $company)
    {
        $modelName = $request->get('lcOrLg');

        $types = [
            'LetterOfCreditIssuance' => LcTypes::getAll(),
            'LetterOfGuaranteeIssuance' => LgTypes::getAll(),
            'LCOverdraft' => [],
        ][$modelName];

        $sources = [
            'LetterOfCreditIssuance' => LetterOfCreditIssuance::lcSources(),
            'LetterOfGuaranteeIssuance' => LetterOfGuaranteeIssuance::lgSources(),
            'LCOverdraft' => [],
        ][$modelName];

        return response()->json([
            'types' => $types,
            'sources' => $sources,
        ]);
    }

    /**
     * Builds the raw result set for the current filters. Which table gets
     * queried, and which filters apply, depends entirely on Report Type —
     * this branching is UNCHANGED from the original controller.
     *
     * Returns null when no rows match.
     */
    private function fetchStatementData(Company $company, Request $request): ?array
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $financialInstitutionId = $request->get('financial_institution_id');
        $financialInstitution = FinancialInstitution::find($financialInstitutionId);
        $lcFacilityId = $request->get('lc_facility_id');
        $financialInstitutionName = $financialInstitution ? $financialInstitution->getName() : null;

        $letterOfCreditFacility = LetterOfCreditFacility::find($lcFacilityId);
        $letterOfCreditFacilityName = $letterOfCreditFacility ? $letterOfCreditFacility->getName() : null;
        $currencyName = $request->get('currency');
        $reportType = $request->get('report_type');

        $statementTableName = self::STATEMENT_TABLE_BY_TYPE[$reportType] ?? null;
        if (! $statementTableName) {
            return null;
        }
        $isLcOverdraftBankStatement = $statementTableName == 'lc_overdraft_bank_statements';
        $lcTypeOrLgTypeColumnName = self::TYPE_COLUMN_BY_REPORT_TYPE[$reportType] ?? null;
        $orderColumnName = self::ORDER_COLUMN_BY_TABLE[$statementTableName];

        $source = $request->get('source');
        $type = $request->get('type');

        $freshQuery = fn () => DB::table($statementTableName)
            ->where($statementTableName.'.company_id', $company->id)
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $endDate)
            ->when(! $isLcOverdraftBankStatement, function ($q) use ($currencyName) {
                $q->where('currency', $currencyName);
            })
            ->when(! $isLcOverdraftBankStatement, function ($q) use ($financialInstitutionId) {
                $q->where('financial_institution_id', $financialInstitutionId);
            })
            ->when($source, function ($q) use ($source) {
                $q->where('source', $source);
            })
            ->when($isLcOverdraftBankStatement, function ($q) use ($lcFacilityId) {
                $q->where('lc_facility_id', $lcFacilityId);
            })
            ->when($lcTypeOrLgTypeColumnName, function ($q) use ($lcTypeOrLgTypeColumnName, $type) {
                $q->where($lcTypeOrLgTypeColumnName, $type);
            })
            ->orderByRaw($statementTableName.'.'.$orderColumnName.' desc , '.$statementTableName.'.id desc');

        if (! $freshQuery()->exists()) {
            return null;
        }

        $sourceLabel = [
            'LetterOfCreditIssuance' => LetterOfCreditIssuance::lcSources(),
            'LetterOfGuaranteeIssuance' => LetterOfGuaranteeIssuance::lgSources(),
            'LCOverdraft' => LcOverdraftBankStatement::getSources(),
        ][$reportType][$source] ?? null;

        $typeLabel = [
            'LetterOfCreditIssuance' => LcTypes::getAll(),
            'LetterOfGuaranteeIssuance' => LgTypes::getAll(),
        ][$reportType][$type] ?? null;

        return [
            'query' => $freshQuery,
            'statementTable' => $statementTableName,
            /**
             * * نفس العمود اللي الاستعلام بيرتّب بيه فوق (full_date لكشوف
             * * الـ LC/LG و date لكشف الـ LC Overdraft) — الكروت لازم تقرا
             * * أول/آخر صف بنفس الترتيب وإلا هتجيب رصيد صف تاني خالص
             */
            'orderColumn' => $orderColumnName,
            'isLcOverdraftBankStatement' => $isLcOverdraftBankStatement,
            'financialInstitutionName' => $financialInstitutionName,
            'letterOfCreditFacilityName' => $letterOfCreditFacilityName,
            'currencyName' => $currencyName,
            'sourceLabel' => $sourceLabel,
            'typeLabel' => $typeLabel,
        ];
    }

    /**
     * Shapes one raw statement row into the plain array both the on-screen
     * table (via result()) and the Excel export (via exportExcel()) read
     * from. Comment comes straight from the row's own comment_en/comment_ar
     * — no join-based lookup helper, matching the original Blade exactly
     * (no getBankStatementComment()/reviewed fallback on this report).
     */
    private function mapStatementRow($row, string $lang, bool $isLcOverdraftBankStatement): array
    {
        return [
            'id' => $row->id,
            'date' => Carbon::make($row->date)->format('d-m-Y'),
            'limit' => $isLcOverdraftBankStatement ? (float) ($row->limit ?? 0) : null,
            'beginningBalance' => (float) ($row->beginning_balance ?? 0),
            'debit' => (float) ($row->debit ?? 0),
            'credit' => (float) ($row->credit ?? 0),
            'endBalance' => (float) ($row->end_balance ?? 0),
            'room' => $isLcOverdraftBankStatement ? (float) ($row->room ?? 0) : null,
            'comment' => AccountNumberLabel::decorateText(
                (int) ($row->company_id ?? 0),
                isset($row->{'comment_'.$lang}) ? $row->{'comment_'.$lang} : '-'
            ),
        ];
    }

    /**
     * The report itself. Query logic lives in fetchStatementData() and is
     * UNCHANGED. Real server-side pagination is new — see class docblock.
     */
    public function result(Company $company, Request $request)
    {
        $data = $this->fetchStatementData($company, $request);
        if (is_null($data)) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }
        $isLcOverdraftBankStatement = $data['isLcOverdraftBankStatement'];

        // Range-wide KPIs via SQL aggregates; only this page's rows are read.
        $paginator = $this->paginateStatement($data['query'], self::ROWS_PER_PAGE);
        $kpis = $this->ledgerStatementKpis($data['query'], $data['statementTable'], $paginator->total(), $data['orderColumn']);

        $lang = app()->getLocale();
        $paginator->getCollection()->transform(fn ($row) => $this->mapStatementRow($row, $lang, $isLcOverdraftBankStatement));

        return \Inertia\Inertia::render('Statements/LgLcStatement/Result', [
            'company' => ['id' => $company->id],
            'currency' => $data['currencyName'],
            'isLcOverdraftBankStatement' => $isLcOverdraftBankStatement,
            'financialInstitutionName' => $data['financialInstitutionName'],
            'letterOfCreditFacilityName' => $data['letterOfCreditFacilityName'],
            'sourceLabel' => $data['sourceLabel'],
            'typeLabel' => $data['typeLabel'],
            'kpis' => $kpis,
            'paginator' => $paginator->toArray(),
            'urls' => [
                'backUrl' => route('view.lg.lc.bank.statement', ['company' => $company->id]),
                'exportUrl' => route('export.lg.lc.bank.statement', array_merge(['company' => $company->id], $request->only([
                    'start_date', 'end_date', 'currency', 'financial_institution_id', 'report_type', 'source', 'type', 'lc_facility_id',
                ]))),
            ],
        ]);
    }

    /**
     * Excel export (project-owner requested) — same filters as result(),
     * reusing fetchStatementData()/mapStatementRow(). Styled via the shared
     * App\Exports\Statements\LgLcStatementExport — no new export library
     * introduced.
     */
    public function exportExcel(Company $company, Request $request)
    {
        $data = $this->fetchStatementData($company, $request);
        if (is_null($data)) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }
        $isLcOverdraftBankStatement = $data['isLcOverdraftBankStatement'];
        $lang = app()->getLocale();

        $headings = ['#', 'Date'];
        if ($isLcOverdraftBankStatement) {
            $headings[] = 'Limit';
        }
        array_push($headings, 'Beginning Balance', 'Debit', 'Credit', 'End Balance');
        if ($isLcOverdraftBankStatement) {
            $headings[] = 'Room';
        }
        $headings[] = 'Comment';

        // The workbook is the whole range, not the page on screen, so the
        // export runs the same query unpaginated.
        $rows = $data['query']()->get()->values()->map(function ($row, $index) use ($lang, $isLcOverdraftBankStatement) {
            $mapped = $this->mapStatementRow($row, $lang, $isLcOverdraftBankStatement);

            $line = ['#' => $index + 1, 'Date' => $mapped['date']];
            if ($isLcOverdraftBankStatement) {
                $line['Limit'] = $mapped['limit'];
            }
            $line['Beginning Balance'] = $mapped['beginningBalance'];
            $line['Debit'] = $mapped['debit'];
            $line['Credit'] = $mapped['credit'];
            $line['End Balance'] = $mapped['endBalance'];
            if ($isLcOverdraftBankStatement) {
                $line['Room'] = $mapped['room'];
            }
            $line['Comment'] = $mapped['comment'];

            return $line;
        });

        $fileNameParts = ['LG-LC-Statement', $data['financialInstitutionName'] ?: $data['letterOfCreditFacilityName'], strtoupper((string) $data['currencyName'])];
        $fileName = preg_replace('/[^A-Za-z0-9\-]+/', '-', implode('-', array_filter($fileNameParts))).'.xlsx';

        return (new LgLcStatementExport($headings, $rows))->download($fileName);
    }
}
