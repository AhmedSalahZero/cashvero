<?php

namespace App\Http\Controllers;

use App\Enums\LgSources;
use App\Enums\LgTypes;
use App\Exports\Statements\LgListReportExport;
use App\Models\Company;
use App\Traits\GeneralFunctions;
use App\Traits\LgListReportRows;
use App\Traits\PaginatesStatementQueries;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * LgByBeneficiaryNameReportController
 * ------------------------------------------------------------------
 * Renders the "LG By Beneficiary Name" report (Statements sidebar
 * section) — a flat list of Letters of Guarantee for one or more
 * beneficiaries (partners) in one currency, filtered by renewal date
 * and status. Reads straight from `letter_of_guarantee_issuances` —
 * never recalculates anything. No running balance here (not a
 * ledger) — just Amount and Cash Cover totals.
 *
 * The Beneficiary multi-select is populated by
 * LetterOfGuaranteeIssuanceController@getBeneficiaryNameByCurrency —
 * an existing, untouched, shared ajax endpoint (also used by the
 * still-Blade LG Issuance create/edit forms) — not duplicated here.
 *
 * ── Frontend migration status ───────────────────────────────────
 *   - index()       → ✅ Migrated to Inertia (Statements/LgByBeneficiaryName/Index)
 *   - result()      → ✅ Migrated to Inertia (Statements/LgByBeneficiaryName/Result).
 *                     Query logic (fetchRows()) is UNCHANGED from the original
 *                     controller. Real server-side pagination (via
 *                     PaginatesStatementQueries, already present in the original —
 *                     kept) plus a grand-total KPI row computed from the FULL
 *                     result set (the original's "Total" row only ever summed
 *                     the current 50-row page, a real gap the same "heavy
 *                     report" fix already applied elsewhere in this project
 *                     resolves here too) are the only changes. GET instead of
 *                     the original's already-GET method — no verb change needed,
 *                     this page's form already submitted via GET.
 *   - exportExcel() → ✅ New (project-owner requested). Reuses
 *                     fetchRows()/mapLgListRow() (via the shared LgListReportRows
 *                     trait — LG By Bank Name uses the exact same row shape).
 *                     Styled via the shared App\Exports\Statements\LgListReportExport.
 */
class LgByBeneficiaryNameReportController
{
    use GeneralFunctions;
    use LgListReportRows;
    use PaginatesStatementQueries;

    private const ROWS_PER_PAGE = 50;

    /**
     * Filter form: Renewal Date, Currency, Beneficiaries (cascading
     * multi-select), Status. Renders Statements/LgByBeneficiaryName/Index.vue.
     */
    public function index(Company $company, Request $request)
    {
        $selectedCurrency = $request->get('currency_name');
        $currencies = DB::table('letter_of_guarantee_issuances')->where('company_id', $company->id)->get()->unique('lg_currency')->pluck('lg_currency', 'lg_currency')->toArray();

        return \Inertia\Inertia::render('Statements/LgByBeneficiaryName/Index', [
            'company' => ['id' => $company->id],
            'currencies' => $currencies,
            'selectedCurrency' => $selectedCurrency,
            'urls' => [
                'result' => route('result.lg.by.beneficiary.name.report', ['company' => $company->id]),
                'beneficiariesByCurrency' => route('get.beneficiary.name.by.currency', ['company' => $company->id]),
            ],
        ]);
    }

    /**
     * Builds the raw result set for the current filters. Query logic
     * (company/currency/beneficiary/renewal-date/status filter on
     * letter_of_guarantee_issuances, joined to partner/bank names) is
     * UNCHANGED from the original controller.
     *
     * Returns null when no rows match.
     */
    private function fetchRows(Company $company, Request $request): ?array
    {
        $startDate = $request->get('start_date');
        $currencyName = $request->get('currency_name');
        $partnerIds = $request->get('beneficiary_id', []);
        $status = $request->get('status', 'running');

        $freshQuery = fn () => DB::table('letter_of_guarantee_issuances')
            ->where('letter_of_guarantee_issuances.company_id', $company->id)
            ->where('letter_of_guarantee_issuances.lg_currency', $currencyName)
            ->whereIn('letter_of_guarantee_issuances.partner_id', $partnerIds)
            /**
             * ⚠️ REAL BUG FIXED HERE (2026-07-25), CORRECTED same day —
             * same fix, same rationale, same correction, as
             * LgByBankNameReportController::fetchRows(). See that
             * controller's docblock for the full explanation, including
             * why cancellation_date isn't a real column and every
             * reference below is fully table-qualified.
             */
            ->leftJoin('letter_of_guarantee_statements as cancellation_statement', function ($join) {
                $join->on('cancellation_statement.letter_of_guarantee_issuance_id', '=', 'letter_of_guarantee_issuances.id')
                    ->where('cancellation_statement.type', '=', 'for-cancellation');
            })
            ->where(function ($q) use ($status, $startDate) {
                if ($status === 'running') {
                    $q->where('letter_of_guarantee_issuances.status', '!=', 'cancelled')
                        ->where('letter_of_guarantee_issuances.renewal_date', '>', now());
                } elseif ($status === 'expired') {
                    $q->where('letter_of_guarantee_issuances.status', '!=', 'cancelled')
                        ->where('letter_of_guarantee_issuances.renewal_date', '<=', now());
                } elseif ($status === 'cancelled') {
                    $q->where('letter_of_guarantee_issuances.status', 'cancelled')
                        ->where(function ($dateQ) use ($startDate) {
                            // NULL-safe (2026-07-25, confirmed with project owner) — see
                            // LgByBankNameReportController::fetchRows() for the full
                            // explanation: a cancelled LG with no matching
                            // cancellation_statement row (e.g. a previous cancel()
                            // attempt that threw partway through) must not be silently
                            // hidden from this report regardless of date or lg_type.
                            $dateQ->where('cancellation_statement.date', '>=', $startDate)
                                ->orWhereNull('cancellation_statement.date');
                        });
                } else {
                    // 'all' — every running/expired LG, plus cancelled
                    // ones from the chosen date onward (or with no
                    // statement row at all — see the NULL-safe note above).
                    $q->where('letter_of_guarantee_issuances.status', '!=', 'cancelled')
                        ->orWhere(function ($cancelledQ) use ($startDate) {
                            $cancelledQ->where('letter_of_guarantee_issuances.status', 'cancelled')
                                ->where(function ($dateQ) use ($startDate) {
                                    $dateQ->where('cancellation_statement.date', '>=', $startDate)
                                        ->orWhereNull('cancellation_statement.date');
                                });
                        });
                }
            })
            ->join('partners', 'partners.id', '=', 'letter_of_guarantee_issuances.partner_id')
            ->join('financial_institutions', 'financial_institutions.id', '=', 'letter_of_guarantee_issuances.financial_institution_id')
            ->join('banks', 'banks.id', '=', 'financial_institutions.bank_id')
            ->selectRaw(
                'letter_of_guarantee_issuances.id as id , letter_of_guarantee_issuances.partner_id as partner_id , partners.name as partner_name , letter_of_guarantee_issuances.lg_type as lg_type , letter_of_guarantee_issuances.transaction_name as transaction_name, letter_of_guarantee_issuances.lg_code as lg_code, letter_of_guarantee_issuances.source as source ,banks.name_en as financial_institution_name , letter_of_guarantee_issuances.lg_amount as lg_amount , case when letter_of_guarantee_issuances.status = \'cancelled\' then \'cancelled\' else (DATE_FORMAT(letter_of_guarantee_issuances.renewal_date,\'%d-%m-%Y\')) end as renewal_date , letter_of_guarantee_issuances.cash_cover_amount as cash_cover_amount, letter_of_guarantee_issuances.lg_commission_rate as lg_commission_rate , case when letter_of_guarantee_issuances.status = \'cancelled\' then \'cancelled\' when letter_of_guarantee_issuances.renewal_date <= NOW() then \'expired\' else \'running\' end as lg_status '
            )
            // Without an ORDER BY, MySQL is free to return these rows in any
            // order it likes, and it does not have to pick the same one
            // twice. That is harmless for a single fetch but fatal for
            // LIMIT/OFFSET paging: the same LG could appear on two pages
            // while another never appears at all. Ordering by id makes the
            // sequence total and stable.
            ->orderBy('letter_of_guarantee_issuances.id');

        if (! $freshQuery()->exists()) {
            return null;
        }

        return ['query' => $freshQuery, 'currency' => $currencyName, 'startDate' => $startDate];
    }

    /**
     * The report itself. Query logic lives in fetchRows() and is UNCHANGED
     * from the original controller.
     */
    public function result(Company $company, Request $request)
    {
        $data = $this->fetchRows($company, $request);
        if (is_null($data)) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }
        $lgsTypes = LgTypes::getAll();
        $lgsSources = LgSources::getAll();

        // Grand totals still cover the FULL result set (see class docblock —
        // the original's own "Total" row only ever summed the current page).
        // They are SQL SUMs over the same WHERE clause now, so a large
        // report no longer has to be read into PHP just to be added up.
        $paginator = $this->paginateStatement($data['query'], self::ROWS_PER_PAGE);
        $sums = $this->statementSums($data['query'], [
            'total_lg_amount' => 'letter_of_guarantee_issuances.lg_amount',
            'total_cash_cover' => 'letter_of_guarantee_issuances.cash_cover_amount',
        ]);

        $kpis = [
            'totalLgAmount' => $sums['total_lg_amount'],
            'totalCashCoverAmount' => $sums['total_cash_cover'],
            'transactionCount' => $paginator->total(),
        ];

        $paginator->getCollection()->transform(fn ($row) => $this->mapLgListRow($row, $lgsTypes, $lgsSources));

        return \Inertia\Inertia::render('Statements/LgByBeneficiaryName/Result', [
            'company' => ['id' => $company->id],
            'currency' => $data['currency'],
            'status' => $request->get('status', 'running'),
            'startDate' => $data['startDate'] ? Carbon::make($data['startDate'])->format('d-m-Y') : null,
            'kpis' => $kpis,
            'paginator' => $paginator->toArray(),
            'urls' => [
                'backUrl' => route('view.lg.by.beneficiary.name.report', ['company' => $company->id]),
                'exportUrl' => route('export.lg.by.beneficiary.name.report', array_merge(['company' => $company->id], $request->only([
                    'start_date', 'currency_name', 'beneficiary_id', 'status',
                ]))),
            ],
        ]);
    }

    /**
     * Excel export (project-owner requested) — same filters as result(),
     * reusing fetchRows()/mapLgListRow(). Styled via the shared
     * App\Exports\Statements\LgListReportExport — no new export library
     * introduced.
     */
    public function exportExcel(Company $company, Request $request)
    {
        $data = $this->fetchRows($company, $request);
        if (is_null($data)) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }
        $lgsTypes = LgTypes::getAll();
        $lgsSources = LgSources::getAll();

        $headings = ['#', 'Beneficiary Name', 'LG Type', 'Transaction Name', 'LG Code', 'Source', 'Bank Name', 'Amount', 'Renewal Date', 'Cash Cover', 'Commission Rate %'];

        // The workbook is the whole report, not the page on screen.
        $rows = $data['query']()->get()->values()->map(function ($row, $index) use ($lgsTypes, $lgsSources) {
            $mapped = $this->mapLgListRow($row, $lgsTypes, $lgsSources);

            return [
                '#' => $index + 1,
                'Beneficiary Name' => $mapped['partnerName'],
                'LG Type' => $mapped['lgType'],
                'Transaction Name' => $mapped['transactionName'],
                'LG Code' => $mapped['lgCode'],
                'Source' => $mapped['source'],
                'Bank Name' => $mapped['financialInstitutionName'],
                'Amount' => $mapped['lgAmount'],
                'Renewal Date' => $mapped['renewalDate'],
                'Cash Cover' => $mapped['cashCoverAmount'],
                'Commission Rate %' => $mapped['lgCommissionRate'],
            ];
        });

        $fileNameParts = ['LG-By-Beneficiary-Name', strtoupper((string) $data['currency'])];
        $fileName = preg_replace('/[^A-Za-z0-9\-]+/', '-', implode('-', $fileNameParts)).'.xlsx';

        return (new LgListReportExport($headings, $rows))->download($fileName);
    }
}
