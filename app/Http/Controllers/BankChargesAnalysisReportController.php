<?php

namespace App\Http\Controllers;

use App\Exports\Statements\BankChargesAnalysisExport;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Services\Reports\BankChargesAnalysisService;
use App\Traits\PaginatesRawCollections;
use App\Traits\Reports\PrintsReport;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Bank Interest, Commissions & Charges Analysis — what the company paid
 * each bank, by facility, over a period. Where every figure comes from is
 * documented on BankChargesAnalysisService.
 *
 * Rows are merged from a dozen sources in PHP, so pagination is over the
 * built collection (PaginatesRawCollections), the same as the Factoring
 * Charges Statement. The summary and the KPIs always cover the whole
 * range; only the detail table is paged.
 */
class BankChargesAnalysisReportController
{
    use PrintsReport;
    use PaginatesRawCollections;

    private const ROWS_PER_PAGE = 50;

    private const FILTERS = ['start_date', 'end_date', 'financial_institution_ids', 'facility_types', 'currencies'];

    public function __construct(private BankChargesAnalysisService $service)
    {
    }

    public function index(Company $company)
    {
        return Inertia::render('Statements/BankChargesAnalysis/Index', [
            'company' => ['id' => $company->id],
            'banks' => FinancialInstitution::with('bank')
                ->onlyForCompany($company->id)
                ->onlyBanks()
                ->get()
                ->map(fn (FinancialInstitution $bank) => ['value' => $bank->id, 'label' => $bank->getName()])
                ->sortBy('label')
                ->values(),
            'facilityTypes' => $this->options(BankChargesAnalysisService::facilityTypes()),
            'currencies' => collect($this->service->availableCurrencies($company))
                ->map(fn (string $currency) => ['value' => $currency, 'label' => $currency])
                ->values(),
            'mainCurrency' => $company->getMainFunctionalCurrency(),
            'urls' => [
                'result' => route('result.bank.charges.analysis.report', ['company' => $company->id]),
            ],
        ]);
    }

    public function result(Company $company, Request $request)
    {
        $report = $this->buildReport($company, $request);
        if ($report['lines']->isEmpty()) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }

        $paginator = $this->paginateCollection($report['lines'], self::ROWS_PER_PAGE, $request);
        $filters = array_merge(['company' => $company->id], $request->only(self::FILTERS));

        return Inertia::render('Statements/BankChargesAnalysis/Result', [
            'company' => ['id' => $company->id],
            'startDate' => $request->input('start_date'),
            'endDate' => $request->input('end_date'),
            'filterSummary' => $this->filterSummary($company, $request),
            'displayCurrency' => $report['displayCurrency'],
            'isConverted' => $report['isConverted'],
            'mainCurrency' => $company->getMainFunctionalCurrency(),
            'missingRates' => $report['missingRates'],
            'summary' => $report['summary'],
            'kpis' => $report['kpis'],
            'paginator' => $paginator->toArray(),
            'perPage' => self::ROWS_PER_PAGE,
            'urls' => [
                'backUrl' => route('view.bank.charges.analysis.report', ['company' => $company->id]),
                'exportUrl' => route('export.bank.charges.analysis.report', $filters),
                // Like the export, print covers the whole range, not the page on screen.
                'printUrl' => route('print.bank.charges.analysis.report', $filters),
            ],
        ]);
    }

    public function exportExcel(Company $company, Request $request)
    {
        $payload = $this->reportPayload($company, $request);

        if (! is_array($payload)) {
            return $payload;
        }

        $fileName = preg_replace('/[^A-Za-z0-9\-]+/', '-', implode('-', $payload['fileNameParts'])).'.xlsx';

        return (new BankChargesAnalysisExport($payload['sheets']))->download($fileName);
    }

    /**
     * The facility table with each bank's subtotal under it, and the grand
     * total in the footer — from the same payload the workbook is built from.
     */
    public function print(Company $company, Request $request)
    {
        $payload = $this->reportPayload($company, $request);

        if (! is_array($payload)) {
            return $payload;
        }

        $headings = $payload['sheets']['By Facility']['headings'];

        return $this->renderReportPrint(
            company: $company,
            title: __('Bank Interest, Commissions & Charges Analysis'),
            headings: $headings,
            rows: $payload['printRows'],
            meta: $this->requestMeta($request, $payload['meta']),
            numericHeadings: $this->numericHeadingsFor($headings),
            totals: $payload['printTotals'],
        );
    }

    /**
     * Headings and rows for the WHOLE range, shared by the Excel export and
     * by Print so the two can never disagree.
     *
     * @return array<string, mixed>|\Illuminate\Http\RedirectResponse
     */
    private function reportPayload(Company $company, Request $request)
    {
        $report = $this->buildReport($company, $request);
        if ($report['lines']->isEmpty()) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }

        $isConverted = $report['isConverted'];
        $amountsIn = $report['displayCurrency'];
        $banks = $report['summary']['banks'];
        $grand = $report['summary']['grand'];

        $bankHeadings = ['Bank', 'Interest', 'Commission', 'Charges', 'Total', 'Share %'];
        $bankRows = collect($banks)->map(fn (array $bank) => [
            'Bank' => $bank['bank_name'],
        ] + $this->amountColumns($bank, $grand['total']));

        $facilityHeadings = ['Bank', 'Facility Type', 'Facility', 'Interest', 'Commission', 'Charges', 'Total', 'Share %'];
        $facilityRows = collect();
        $printRows = collect();
        foreach ($banks as $bank) {
            foreach ($bank['facilities'] as $facility) {
                $row = [
                    'Bank' => $bank['bank_name'],
                    'Facility Type' => $facility['facility_type_label'],
                    'Facility' => $facility['facility_label'],
                ] + $this->amountColumns($facility, $grand['total']);
                $facilityRows->push($row);
                $printRows->push($row);
            }
            $printRows->push([
                'Bank' => __('Total').' — '.$bank['bank_name'],
                'Facility Type' => '',
                'Facility' => '',
            ] + $this->amountColumns($bank, $grand['total']));
        }

        $detailHeadings = $isConverted
            ? ['#', 'Date', 'Bank', 'Facility Type', 'Facility', 'Cost Type', 'Item', 'Currency', 'Amount', 'Exchange Rate', 'Amount In Main Currency', 'Comment']
            : ['#', 'Date', 'Bank', 'Facility Type', 'Facility', 'Cost Type', 'Item', 'Currency', 'Amount', 'Comment'];
        $detailRows = $report['lines']->values()->map(function (array $line, int $index) use ($isConverted) {
            $row = [
                '#' => $index + 1,
                'Date' => $line['date'],
                'Bank' => $line['bank_name'],
                'Facility Type' => $line['facility_type_label'],
                'Facility' => $line['facility_label'],
                'Cost Type' => $line['category_label'],
                'Item' => $line['item'],
                'Currency' => $line['currency'],
                'Amount' => $line['amount'],
            ];
            if ($isConverted) {
                $row['Exchange Rate'] = $line['exchange_rate'];
                $row['Amount In Main Currency'] = $line['amount_main'];
            }
            $row['Comment'] = (string) $line['comment'];

            return $row;
        });

        $format = fn (float $value) => number_format($value, 2);
        $filterSummary = $this->filterSummary($company, $request);

        return [
            'sheets' => [
                'By Bank' => ['headings' => $bankHeadings, 'rows' => $bankRows],
                'By Facility' => ['headings' => $facilityHeadings, 'rows' => $facilityRows],
                'Details' => ['headings' => $detailHeadings, 'rows' => $detailRows],
            ],
            'printRows' => $printRows,
            'printTotals' => [
                'Bank' => __('Grand Total'),
                'Interest' => $format($grand['interest']),
                'Commission' => $format($grand['commission']),
                'Charges' => $format($grand['charges']),
                'Total' => $format($grand['total']),
                'Share %' => $grand['total'] != 0 ? '100.0' : '',
            ],
            'meta' => [
                ['label' => __('Amounts In'), 'value' => $amountsIn],
                ['label' => __('Banks'), 'value' => $filterSummary['banks']],
                ['label' => __('Facility Types'), 'value' => $filterSummary['facilityTypes']],
            ],
            'fileNameParts' => ['Bank-Charges-Analysis', $amountsIn, $request->input('start_date'), $request->input('end_date')],
        ];
    }

    /** @return array<string, float> */
    private function amountColumns(array $totals, float $grandTotal): array
    {
        return [
            'Interest' => $totals['interest'],
            'Commission' => $totals['commission'],
            'Charges' => $totals['charges'],
            'Total' => $totals['total'],
            'Share %' => $grandTotal != 0 ? round($totals['total'] / $grandTotal * 100, 1) : 0.0,
        ];
    }

    /** @return array<string, mixed> */
    private function buildReport(Company $company, Request $request): array
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date|before_or_equal:today',
            'financial_institution_ids' => 'nullable|array',
            'financial_institution_ids.*' => ['integer', Rule::exists('financial_institutions', 'id')->where('company_id', $company->id)],
            'facility_types' => 'nullable|array',
            'facility_types.*' => ['string', Rule::in(array_keys(BankChargesAnalysisService::facilityTypes()))],
            'currencies' => 'nullable|array',
            'currencies.*' => ['string', Rule::in($this->service->availableCurrencies($company))],
        ]);

        return $this->service->build(
            $company,
            (string) $request->input('start_date'),
            (string) $request->input('end_date'),
            array_map('intval', (array) $request->input('financial_institution_ids', [])),
            array_values((array) $request->input('facility_types', [])),
            array_values((array) $request->input('currencies', [])),
        );
    }

    /** @return array{banks: string, facilityTypes: string, currencies: string} */
    private function filterSummary(Company $company, Request $request): array
    {
        $bankIds = array_map('intval', (array) $request->input('financial_institution_ids', []));
        $types = (array) $request->input('facility_types', []);
        $currencies = (array) $request->input('currencies', []);

        $bankNames = $bankIds === [] ? [] : FinancialInstitution::with('bank')
            ->onlyForCompany($company->id)
            ->whereIn('id', $bankIds)
            ->get()
            ->map(fn (FinancialInstitution $bank) => $bank->getName())
            ->sort()
            ->values()
            ->all();
        $typeLabels = array_values(array_intersect_key(BankChargesAnalysisService::facilityTypes(), array_flip($types)));

        return [
            'banks' => $bankNames === [] ? __('All Banks') : implode(', ', $bankNames),
            'facilityTypes' => $typeLabels === [] ? __('All Facility Types') : implode(', ', $typeLabels),
            'currencies' => $currencies === [] ? __('All Currencies') : implode(', ', array_map('strtoupper', $currencies)),
        ];
    }

    /** @return array<int, array{value: string, label: string}> */
    private function options(array $map): array
    {
        return collect($map)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values()->all();
    }
}
