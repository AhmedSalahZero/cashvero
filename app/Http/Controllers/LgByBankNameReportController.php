<?php

namespace App\Http\Controllers;

use App\Enums\LgSources;
use App\Enums\LgTypes;
use App\Exports\Statements\LgListReportExport;
use App\Models\Company;
use App\Traits\GeneralFunctions;
use App\Traits\LgListReportRows;
use App\Traits\PaginatesRawCollections;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * LgByBankNameReportController
 * ------------------------------------------------------------------
 * Renders the "LG By Bank Name" report (Statements sidebar section) —
 * the mirror of LG By Beneficiary Name: same query/columns/shape, just
 * filtered by bank (financial_institution_id) instead of beneficiary
 * (partner_id). See LgByBeneficiaryNameReportController's docblock for
 * the full shared-shape rationale.
 *
 * The Bank multi-select is populated by
 * LetterOfGuaranteeIssuanceController@getBankNameByCurrency — an
 * existing, untouched, shared ajax endpoint — not duplicated here.
 *
 * ── Frontend migration status ───────────────────────────────────
 *   - index()       → ✅ Migrated to Inertia (Statements/LgByBankName/Index)
 *   - result()      → ✅ Migrated to Inertia (Statements/LgByBankName/Result).
 *                     Query logic (fetchRows()) UNCHANGED. Same grand-total
 *                     KPI fix as LG By Beneficiary Name (see its docblock).
 *   - exportExcel() → ✅ New (project-owner requested). Same shared
 *                     LgListReportRows/LgListReportExport as LG By Beneficiary
 *                     Name — just Bank Name leads instead of Beneficiary Name.
 */
class LgByBankNameReportController
{
    use GeneralFunctions;
    use LgListReportRows;
    use PaginatesRawCollections;

    /**
     * Filter form: Renewal Date, Currency, Banks (cascading multi-select),
     * Status. Renders Statements/LgByBankName/Index.vue.
     */
    public function index(Company $company, Request $request)
    {
        $selectedCurrency = $request->get('currency_name');
        $currencies = DB::table('letter_of_guarantee_issuances')->where('company_id', $company->id)->get()->unique('lg_currency')->pluck('lg_currency', 'lg_currency')->toArray();

        return \Inertia\Inertia::render('Statements/LgByBankName/Index', [
            'company' => ['id' => $company->id],
            'currencies' => $currencies,
            'selectedCurrency' => $selectedCurrency,
            'urls' => [
                'result' => route('result.lg.by.bank.name.report', ['company' => $company->id]),
                'banksByCurrency' => route('get.bank.name.by.currency', ['company' => $company->id]),
            ],
        ]);
    }

    /**
     * Builds the raw result set for the current filters. Query logic
     * (company/currency/bank/renewal-date/status filter on
     * letter_of_guarantee_issuances) is UNCHANGED from the original
     * controller.
     *
     * Returns null when no rows match.
     */
    private function fetchRows(Company $company, Request $request): ?array
    {
        $startDate = $request->get('start_date');
        $currencyName = $request->get('currency_name');
        $bankIds = $request->get('bank_id', []);
        $status = $request->get('status', 'running');

        $results = DB::table('letter_of_guarantee_issuances')
            ->where('letter_of_guarantee_issuances.company_id', $company->id)
            ->where('letter_of_guarantee_issuances.lg_currency', $currencyName)
            ->whereIn('letter_of_guarantee_issuances.financial_institution_id', $bankIds)
            /**
             * ⚠️ REAL BUG FIXED HERE (2026-07-25, confirmed with project
             * owner): a Renewal Date used to be required and applied
             * unconditionally, regardless of which Status was chosen —
             * so picking "Running" (which by definition already means
             * "renewal date is in the future," per
             * LetterOfGuaranteeIssuance::getStatus()) still forced the
             * user to enter one anyway, and it silently filtered out any
             * running LG whose renewal date happened to fall before it.
             *
             * "Expired" was never a real stored `status` value at all —
             * getStatus() computes it purely from
             * renewal_date <= now() when status isn't 'cancelled' — so
             * the report never had a way to ask for it. "Cancelled" as
             * its own selectable status, bounded by the real
             * cancellation date, didn't exist either.
             *
             * ⚠️ CORRECTED 2026-07-25 (first pass of this fix wrongly
             * assumed a `cancellation_date` COLUMN on this table — it
             * doesn't exist; confirmed against the live schema, and this
             * also means LetterOfGuaranteeIssuanceController::cancel()'s
             * own `update(['cancellation_date' => ...])` call is a
             * SEPARATE, pre-existing bug that needs its own fix — see
             * the note flagged to the project owner). The real
             * cancellation date lives on the `letter_of_guarantee_statements`
             * row created at the moment of cancellation
             * (type = 'for-cancellation', see
             * HasLetterOfGuaranteeStatements::handleLetterOfGuaranteeStatement()) —
             * joined in below as `cancellation_statement`. Confirmed
             * exactly one such row ever exists per LG (created once by
             * cancel(), only removed on a reversal), so this join can't
             * duplicate rows. Every column reference below is now fully
             * table-qualified — this joined table shares several column
             * names with letter_of_guarantee_issuances (lg_type, source,
             * financial_institution_id), which would otherwise throw an
             * "ambiguous column" SQL error.
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
                            // NULL-safe (2026-07-25, confirmed with project owner): a
                            // cancelled LG can end up with no matching cancellation_statement
                            // row if a previous cancel() attempt threw partway through (see
                            // the transaction fix in LetterOfGuaranteeIssuanceController::cancel()).
                            // Without this, such an LG's cancellation_statement.date is NULL,
                            // and `NULL >= $startDate` is always false in SQL — it silently
                            // never appears in this report, no matter which date is picked or
                            // which lg_type it is. Treat a missing statement as "always include"
                            // rather than hide it.
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
            )->get();

        if (! count($results)) {
            return null;
        }

        return ['results' => $results, 'currency' => $currencyName, 'startDate' => $startDate];
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
        $results = $data['results'];
        $lgsTypes = LgTypes::getAll();
        $lgsSources = LgSources::getAll();

        $kpis = [
            'totalLgAmount' => (float) $results->sum('lg_amount'),
            'totalCashCoverAmount' => (float) $results->sum('cash_cover_amount'),
            'transactionCount' => $results->count(),
        ];

        $paginator = $this->paginateCollection($results, 50, $request);
        $paginator->getCollection()->transform(fn ($row) => $this->mapLgListRow($row, $lgsTypes, $lgsSources));

        return \Inertia\Inertia::render('Statements/LgByBankName/Result', [
            'company' => ['id' => $company->id],
            'currency' => $data['currency'],
            'status' => $request->get('status', 'running'),
            'startDate' => $data['startDate'] ? Carbon::make($data['startDate'])->format('d-m-Y') : null,
            'kpis' => $kpis,
            'paginator' => $paginator->toArray(),
            'urls' => [
                'backUrl' => route('view.lg.by.bank.name.report', ['company' => $company->id]),
                'exportUrl' => route('export.lg.by.bank.name.report', array_merge(['company' => $company->id], $request->only([
                    'start_date', 'currency_name', 'bank_id', 'status',
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

        $headings = ['#', 'Bank Name', 'Beneficiary Name', 'LG Type', 'Transaction Name', 'LG Code', 'Source', 'Amount', 'Renewal Date', 'Cash Cover', 'Commission Rate %'];

        $rows = $data['results']->values()->map(function ($row, $index) use ($lgsTypes, $lgsSources) {
            $mapped = $this->mapLgListRow($row, $lgsTypes, $lgsSources);

            return [
                '#' => $index + 1,
                'Bank Name' => $mapped['financialInstitutionName'],
                'Beneficiary Name' => $mapped['partnerName'],
                'LG Type' => $mapped['lgType'],
                'Transaction Name' => $mapped['transactionName'],
                'LG Code' => $mapped['lgCode'],
                'Source' => $mapped['source'],
                'Amount' => $mapped['lgAmount'],
                'Renewal Date' => $mapped['renewalDate'],
                'Cash Cover' => $mapped['cashCoverAmount'],
                'Commission Rate %' => $mapped['lgCommissionRate'],
            ];
        });

        $fileNameParts = ['LG-By-Bank-Name', strtoupper((string) $data['currency'])];
        $fileName = preg_replace('/[^A-Za-z0-9\-]+/', '-', implode('-', $fileNameParts)).'.xlsx';

        return (new LgListReportExport($headings, $rows))->download($fileName);
    }
}
