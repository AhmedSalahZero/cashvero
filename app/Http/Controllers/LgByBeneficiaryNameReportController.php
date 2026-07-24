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
 *                     PaginatesRawCollections, already present in the original —
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
    use PaginatesRawCollections;

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
        $status = $request->get('status');

        $results = DB::table('letter_of_guarantee_issuances')
            ->where('letter_of_guarantee_issuances.company_id', $company->id)
            ->where('lg_currency', $currencyName)
            ->whereIn('partner_id', $partnerIds)
            ->when($status == 'running', function ($q) {
                $q->where('status', 'running');
            })
            ->where('renewal_date', '>=', $startDate)
            ->join('partners', 'partners.id', '=', 'letter_of_guarantee_issuances.partner_id')
            ->join('financial_institutions', 'financial_institutions.id', '=', 'letter_of_guarantee_issuances.financial_institution_id')
            ->join('banks', 'banks.id', '=', 'financial_institutions.bank_id')
            ->selectRaw(
                'letter_of_guarantee_issuances.id as id , partner_id , partners.name as partner_name , lg_type , transaction_name,lg_code, source ,banks.name_en as financial_institution_name , lg_amount , case when status = \'cancelled\' then \'cancelled\' else (DATE_FORMAT(renewal_date,\'%d-%m-%Y\')) end as renewal_date , cash_cover_amount,lg_commission_rate '
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

        // Grand totals from the FULL result set (see class docblock — the
        // original's own "Total" row only ever summed the current page).
        $kpis = [
            'totalLgAmount' => (float) $results->sum('lg_amount'),
            'totalCashCoverAmount' => (float) $results->sum('cash_cover_amount'),
            'transactionCount' => $results->count(),
        ];

        $paginator = $this->paginateCollection($results, 50, $request);
        $paginator->getCollection()->transform(fn ($row) => $this->mapLgListRow($row, $lgsTypes, $lgsSources));

        return \Inertia\Inertia::render('Statements/LgByBeneficiaryName/Result', [
            'company' => ['id' => $company->id],
            'currency' => $data['currency'],
            'startDate' => Carbon::make($data['startDate'])->format('d-m-Y'),
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

        $rows = $data['results']->values()->map(function ($row, $index) use ($lgsTypes, $lgsSources) {
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
