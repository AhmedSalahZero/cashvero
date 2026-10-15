<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Traits\PaginatesStatementQueries;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Exports\Statements\CashCoverStatementExport;
use App\Traits\Reports\PrintsReport;

/**
 * CashCoverStatementController
 * ------------------------------------------------------------------
 * The cash cover ledger for letters of guarantee and letters of credit —
 * the money the bank freezes when an instrument is issued, and releases
 * when it is settled or cancelled.
 *
 * It answers a question the LG & LC Bank Statement cannot: that report
 * shows the instruments themselves, while this one shows the CASH held
 * against them, which is what actually leaves the company's usable
 * balance.
 *
 * Shaped exactly like LGLCSBanktatementController: a filter screen, a
 * paginated result with range-wide KPIs, and the same reusable
 * pagination/KPI helpers — so the two reports behave identically.
 *
 * The two cash-cover tables have the same columns apart from the
 * instrument foreign key, so one query serves both; only the table name
 * and the type/source columns differ.
 */
class CashCoverStatementController
{
    use PrintsReport;

    use PaginatesStatementQueries;

    private const ROWS_PER_PAGE = 50;

    /** Which instrument's cover to read, and where it lives. */
    private const SOURCES = [
        'LetterOfGuarantee' => [
            'table' => 'letter_of_guarantee_cash_cover_statements',
            'label' => 'Letter Of Guarantee',
            'typeColumn' => 'lg_type',
        ],
        'LetterOfCredit' => [
            'table' => 'letter_of_credit_cash_cover_statements',
            'label' => 'Letter Of Credit',
            'typeColumn' => 'lc_type',
        ],
    ];

    public function index(Company $company)
    {
        $banks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get();

        return \Inertia\Inertia::render('Statements/CashCoverStatement/Index', [
            'company' => ['id' => $company->id],
            'instrumentTypes' => collect(self::SOURCES)
                ->map(fn ($config, $key) => ['value' => $key, 'label' => __($config['label'])])
                ->values(),
            /**
             * "All banks" is a real option, not a convenience: cover for one
             * instrument type is often spread across several banks, and the
             * total frozen across all of them is the figure that matters for
             * cash planning.
             */
            'banks' => $banks->map(fn ($bank) => ['value' => $bank->id, 'name' => $bank->getName()])->values(),
            'currencies' => collect(getCurrencies())->map(fn ($label, $code) => ['value' => $code, 'label' => $label])->values(),
            'urls' => [
                'result' => route('result.cash.cover.statement', ['company' => $company->id]),
            ],
        ]);
    }

    public function result(Company $company, Request $request)
    {
        $data = $this->fetchData($company, $request);

        if (is_null($data)) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }

        $paginator = $this->paginateStatement($data['query'], self::ROWS_PER_PAGE);
        $kpis = $this->ledgerStatementKpis($data['query'], $data['table'], $paginator->total());

        $paginator->getCollection()->transform(fn ($row) => $this->mapRow($row, $data['typeColumn']));

        return \Inertia\Inertia::render('Statements/CashCoverStatement/Result', [
            'company' => ['id' => $company->id],
            'instrumentLabel' => $data['instrumentLabel'],
            'bankName' => $data['bankName'],
            'currency' => $data['currency'],
            'startDate' => $data['startDate'],
            'endDate' => $data['endDate'],
            'kpis' => $kpis,
            'paginator' => $paginator->toArray(),
            'urls' => [
                'backUrl' => route('view.cash.cover.statement', ['company' => $company->id]),
                // Print carries the same filters and covers the whole range,
                // not the page on screen.
                'exportUrl' => route('export.cash.cover.statement', array_merge(
                    ['company' => $company->id],
                    $request->except(['page'])
                )),
                'printUrl' => route('print.cash.cover.statement', array_merge(
                    ['company' => $company->id],
                    $request->except(['page'])
                )),
            ],
        ]);
    }

    /**
     * The headings and the rows for the WHOLE range.
     *
     * Shared by Print and by the Excel export, the same way every other
     * statement in this family shares one — so the two can never show
     * different numbers, and neither is page-bound.
     *
     * The figures stay RAW here: a workbook needs real numbers for its
     * SUM formulas and number formats. The print sheet formats them at
     * render instead.
     *
     * @return array{0: array<int, string>, 1: \Illuminate\Support\Collection, 2: array<int, string|null>}|\Illuminate\Http\RedirectResponse
     */
    private function reportPayload(Company $company, Request $request)
    {
        $data = $this->fetchData($company, $request);

        if (is_null($data)) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }

        $headings = ['#', 'Date', 'Type', 'Source', 'Movement', 'Debit', 'Credit', 'End Balance'];

        $rows = $data['query']()->get()->values()->map(function ($row, $index) use ($data) {
            $mapped = $this->mapRow($row, $data['typeColumn']);

            return [
                '#' => $index + 1,
                'Date' => $mapped['date'],
                'Type' => $mapped['type'],
                'Source' => $mapped['source'],
                'Movement' => $mapped['movement'],
                'Debit' => $mapped['debit'],
                'Credit' => $mapped['credit'],
                'End Balance' => $mapped['end_balance'],
            ];
        });

        return [$headings, $rows, [
            'Cash-Cover-Statement',
            $data['instrumentLabel'],
            $data['bankName'],
            strtoupper((string) $data['currency']),
        ]];
    }

    /**
     * Excel export — this report was the only statement in the family
     * built without one, which read as a missing button rather than a
     * deliberate omission.
     */
    public function exportExcel(Company $company, Request $request)
    {
        $payload = $this->reportPayload($company, $request);

        if (! is_array($payload)) {
            return $payload;
        }

        [$headings, $rows, $fileNameParts] = $payload;
        $fileName = preg_replace('/[^A-Za-z0-9\-]+/', '-', implode('-', array_filter($fileNameParts))).'.xlsx';

        return (new CashCoverStatementExport($headings, $rows))->download($fileName);
    }

    /**
     * Print — the whole statement, every page of it.
     */
    public function print(Company $company, Request $request)
    {
        $payload = $this->reportPayload($company, $request);

        if (! is_array($payload)) {
            return $payload;
        }

        [$headings, $rows] = $payload;

        return $this->renderReportPrint(
            company: $company,
            title: __('Cash Cover Statement'),
            headings: $headings,
            rows: $rows,
            meta: $this->requestMeta($request),
            numericHeadings: $this->numericHeadingsFor($headings),
        );
    }

    /**
     * @return array{query: callable, table: string, typeColumn: string, instrumentLabel: string, bankName: string, currency: string, startDate: string, endDate: string}|null
     */
    private function fetchData(Company $company, Request $request): ?array
    {
        $instrument = $request->get('instrument_type');

        if (! array_key_exists($instrument, self::SOURCES)) {
            return null;
        }

        $config = self::SOURCES[$instrument];
        $table = $config['table'];

        $currency = (string) $request->get('currency');
        $startDate = (string) $request->get('start_date');
        $endDate = (string) $request->get('end_date');
        $bankId = $request->get('financial_institution_id');

        if (! $currency || ! $startDate || ! $endDate) {
            return null;
        }

        /**
         * A closure rather than a builder: the KPI helpers run their own
         * aggregates over the same filters, and a builder reused after
         * ->paginate() would carry that call's limit and offset with it.
         */
        $query = function () use ($table, $company, $currency, $startDate, $endDate, $bankId) {
            return DB::table($table)
                ->where("{$table}.company_id", $company->id)
                ->where("{$table}.currency", $currency)
                ->whereBetween("{$table}.date", [$startDate, $endDate])
                // An empty bank means every bank — the whole point of the
                // "All Banks" option, so it must not become a NULL filter.
                ->when($bankId, fn ($q) => $q->where("{$table}.financial_institution_id", $bankId))
                ->orderBy("{$table}.full_date")
                ->orderBy("{$table}.id");
        };

        if (! $query()->exists()) {
            return null;
        }

        $bankName = __('All Banks');

        if ($bankId) {
            $bank = FinancialInstitution::onlyForCompany($company->id)->find($bankId);
            $bankName = $bank ? $bank->getName() : __('All Banks');
        }

        return [
            'query' => $query,
            'table' => $table,
            'typeColumn' => $config['typeColumn'],
            'instrumentLabel' => __($config['label']),
            'bankName' => $bankName,
            'currency' => $currency,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];
    }

    private function mapRow($row, string $typeColumn): array
    {
        return [
            'date' => $row->date ? \Carbon\Carbon::parse($row->date)->format('d-m-Y') : null,
            'type' => $row->{$typeColumn} ? __($row->{$typeColumn}) : null,
            'source' => $row->source ? __($row->source) : null,
            'movement' => $row->type ? __($row->type) : null,
            'debit' => (float) $row->debit,
            'credit' => (float) $row->credit,
            'beginning_balance' => (float) $row->beginning_balance,
            'end_balance' => (float) $row->end_balance,
        ];
    }
}
