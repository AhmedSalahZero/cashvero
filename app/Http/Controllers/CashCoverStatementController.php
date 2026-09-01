<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Traits\PaginatesStatementQueries;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
            ],
        ]);
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
