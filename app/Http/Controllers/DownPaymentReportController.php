<?php

namespace App\Http\Controllers;

use App\Exports\Statements\CurrencyTotalsReportExport;
use App\Models\Company;
use App\Traits\PaginatesStatementQueries;
use App\Traits\Reports\PrintsReport;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * DownPaymentReportController
 * ------------------------------------------------------------------
 * "Down Payment Report" - every down payment received from customers (Money
 * Received) or paid to suppliers (Money Payment) on or after a chosen date,
 * with how much of it has been settled against invoices and what is left.
 *
 * The two kinds come from different tables, so they have their own query
 * (customerQuery()/supplierQuery()) that expose the same column aliases;
 * everything after that is shared.
 *
 *   - Amount     is the down payment in its own (invoice) currency
 *                - `amount_in_invoice_currency`, the figure the Down Payment
 *                Amount Settlement screen works in.
 *   - Settled    is the sum of the settlements recorded against the down
 *                payment, and Balance is Amount - Settled. The same formula
 *                as IsMoney::getTotalSettlementsNetBalanceForDownPayment().
 *   - Balance    is listed even when it is zero: a fully used down payment
 *                is still part of the picture.
 *   - Currency   filters on that same down payment currency.
 *
 * Totals are per currency - adding dollars to pounds would be meaningless.
 */
class DownPaymentReportController
{
    use PaginatesStatementQueries;
    use PrintsReport;

    public const CUSTOMER = 'customer';
    public const SUPPLIER = 'supplier';

    private const ROWS_PER_PAGE = 50;

    /**
     * Only the kinds this user holds the report permission for. Each kind's
     * permission is granted to whoever may open the screen it reads (Money
     * Received / Money Payment), so the report is not a side door.
     *
     * @return array<string, string> value => label
     */
    private function availableTypes(): array
    {
        $types = [];
        if (hasAuthFor('down_payment_report_customer.view')) {
            $types[self::CUSTOMER] = __('Customer Down Payments');
        }
        if (hasAuthFor('down_payment_report_supplier.view')) {
            $types[self::SUPPLIER] = __('Supplier Down Payments');
        }

        return $types;
    }

    public function index(Company $company)
    {
        return Inertia::render('Statements/DownPaymentReport/Index', [
            'company' => ['id' => $company->id],
            'types' => collect($this->availableTypes())->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'currencies' => collect(getBanksCurrencies())->map(fn ($label, $code) => ['value' => $code, 'label' => strtoupper($label)])->values(),
            'urls' => [
                'result' => route('result.down.payment.report', ['company' => $company->id]),
            ],
        ]);
    }

    /**
     * @return array{type: string, from_date: string, currencies: array<int, string>}
     */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in([self::CUSTOMER, self::SUPPLIER])],
            'from_date' => ['required', 'date'],
            'currencies' => ['nullable', 'array'],
            'currencies.*' => ['string', 'max:10'],
        ]);

        abort_unless(array_key_exists($data['type'], $this->availableTypes()), 403);

        return [
            'type' => $data['type'],
            'from_date' => Carbon::parse($data['from_date'])->toDateString(),
            'currencies' => array_values(array_unique($data['currencies'] ?? [])),
        ];
    }

    /**
     * The settled amount per down payment, as a derived table to join. The
     * settlement amount is stored as text, hence the CAST.
     */
    private function settledPerDownPayment(string $settlementsTable, string $foreignKey): Builder
    {
        return DB::table($settlementsTable)
            ->selectRaw("{$foreignKey} AS down_payment_id, COALESCE(SUM(CAST(NULLIF(settlement_amount, '') AS DECIMAL(20,2))), 0) AS settled")
            ->groupBy($foreignKey);
    }

    /**
     * Down payments received from customers.
     */
    private function customerQuery(Company $company): Builder
    {
        return DB::table('money_received')
            ->join('partners', 'partners.id', '=', 'money_received.partner_id')
            ->leftJoin('contracts', 'contracts.id', '=', 'money_received.contract_id')
            ->leftJoinSub($this->settledPerDownPayment('settlements', 'money_received_id'), 'dp_settled', 'dp_settled.down_payment_id', '=', 'money_received.id')
            ->where('money_received.company_id', $company->id)
            ->where('money_received.money_type', 'down-payment')
            ->selectRaw(implode(', ', [
                'money_received.id AS id',
                'partners.name AS partner_name',
                'money_received.receiving_date AS money_date',
                'contracts.name AS contract_name',
                'COALESCE(money_received.amount_in_invoice_currency, 0) AS amount',
                'COALESCE(dp_settled.settled, 0) AS settled',
                '(COALESCE(money_received.amount_in_invoice_currency, 0) - COALESCE(dp_settled.settled, 0)) AS balance',
                'money_received.currency AS currency',
            ]));
    }

    /**
     * Down payments paid to suppliers.
     */
    private function supplierQuery(Company $company): Builder
    {
        return DB::table('money_payments')
            ->join('partners', 'partners.id', '=', 'money_payments.partner_id')
            ->leftJoin('contracts', 'contracts.id', '=', 'money_payments.contract_id')
            ->leftJoinSub($this->settledPerDownPayment('payment_settlements', 'money_payment_id'), 'dp_settled', 'dp_settled.down_payment_id', '=', 'money_payments.id')
            ->where('money_payments.company_id', $company->id)
            ->where('money_payments.money_type', 'down-payment')
            ->selectRaw(implode(', ', [
                'money_payments.id AS id',
                'partners.name AS partner_name',
                'money_payments.delivery_date AS money_date',
                'contracts.name AS contract_name',
                'COALESCE(money_payments.amount_in_invoice_currency, 0) AS amount',
                'COALESCE(dp_settled.settled, 0) AS settled',
                '(COALESCE(money_payments.amount_in_invoice_currency, 0) - COALESCE(dp_settled.settled, 0)) AS balance',
                'money_payments.currency AS currency',
            ]));
    }

    /**
     * A fresh builder per call - paginate()/first() mutate what they run on
     * (see PaginatesStatementQueries).
     */
    private function freshQuery(Company $company, array $filters): Builder
    {
        $customer = $filters['type'] === self::CUSTOMER;
        $table = $customer ? 'money_received' : 'money_payments';
        $dateColumn = $customer ? 'money_received.receiving_date' : 'money_payments.delivery_date';

        return ($customer ? $this->customerQuery($company) : $this->supplierQuery($company))
            ->whereDate($dateColumn, '>=', $filters['from_date'])
            ->when($filters['currencies'] !== [], fn ($q) => $q->whereIn("{$table}.currency", $filters['currencies']))
            ->orderBy($dateColumn)
            ->orderBy("{$table}.id");
    }

    /**
     * Count, and amount / settled / balance per currency, over the WHOLE
     * filtered range.
     *
     * @return array{count: int, perCurrency: array<string, array{amount: float, settled: float, balance: float}>}
     */
    private function totals(Company $company, array $filters): array
    {
        $rows = DB::query()
            ->fromSub($this->freshQuery($company, $filters)->reorder(), 'down_payments_report')
            ->selectRaw('down_payments_report.currency AS currency, COUNT(*) AS down_payments_count, SUM(down_payments_report.amount) AS amount, SUM(down_payments_report.settled) AS settled, SUM(down_payments_report.balance) AS balance')
            ->groupBy('down_payments_report.currency')
            ->orderBy('down_payments_report.currency')
            ->get();

        $perCurrency = [];
        $count = 0;
        foreach ($rows as $row) {
            $perCurrency[strtoupper((string) ($row->currency ?: '-'))] = [
                'amount' => round((float) $row->amount, 2),
                'settled' => round((float) $row->settled, 2),
                'balance' => round((float) $row->balance, 2),
            ];
            $count += (int) $row->down_payments_count;
        }

        return ['count' => $count, 'perCurrency' => $perCurrency];
    }

    private function mapRow(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'partnerName' => $row->partner_name ?: '-',
            'date' => $row->money_date ? Carbon::parse($row->money_date)->format('d-m-Y') : null,
            'contractName' => $row->contract_name ?: '-',
            'amount' => round((float) $row->amount, 2),
            'settled' => round((float) $row->settled, 2),
            'balance' => round((float) $row->balance, 2),
            'currency' => strtoupper((string) $row->currency),
        ];
    }

    public function result(Company $company, Request $request)
    {
        $filters = $this->filters($request);
        $type = $filters['type'];

        $paginator = $this->paginateStatement(fn () => $this->freshQuery($company, $filters), self::ROWS_PER_PAGE);
        $paginator->setCollection($paginator->getCollection()->map(fn ($row) => $this->mapRow($row)));

        $totals = $this->totals($company, $filters);

        return Inertia::render('Statements/DownPaymentReport/Result', [
            'company' => ['id' => $company->id],
            'type' => $type,
            'typeLabel' => $this->availableTypes()[$type],
            'partnerHeading' => $type === self::CUSTOMER ? __('Customer Name') : __('Supplier Name'),
            'fromDate' => $filters['from_date'],
            'currencies' => array_map('strtoupper', $filters['currencies']),
            'kpis' => [
                'count' => $totals['count'],
                'perCurrency' => collect($totals['perCurrency'])->map(fn ($sums, $currency) => ['currency' => $currency] + $sums)->values(),
            ],
            'paginator' => $paginator->toArray(),
            'urls' => [
                'index' => route('view.down.payment.report', ['company' => $company->id]),
                'exportUrl' => route('export.down.payment.report', ['company' => $company->id] + $request->query()),
                'printUrl' => route('print.down.payment.report', ['company' => $company->id] + $request->query()),
            ],
        ]);
    }

    /**
     * Headings and rows for the WHOLE filtered range - shared by Excel and
     * Print so both stay unpaginated and agree with each other.
     *
     * @return array{0: array<int, string>, 1: \Illuminate\Support\Collection, 2: array<string, array<string, float>>}
     */
    private function reportPayload(Company $company, array $filters): array
    {
        $partnerHeading = $filters['type'] === self::CUSTOMER ? 'Customer Name' : 'Supplier Name';
        $headings = ['#', $partnerHeading, 'Date', 'Contract', 'Down Payment Amount', 'Settled Amount', 'Balance', 'Currency'];

        $rows = $this->freshQuery($company, $filters)->get()->values()->map(function ($row, $index) use ($partnerHeading) {
            $mapped = $this->mapRow($row);

            return [
                '#' => $index + 1,
                $partnerHeading => $mapped['partnerName'],
                'Date' => $mapped['date'],
                'Contract' => $mapped['contractName'],
                'Down Payment Amount' => $mapped['amount'],
                'Settled Amount' => $mapped['settled'],
                'Balance' => $mapped['balance'],
                'Currency' => $mapped['currency'],
            ];
        });

        $totalsPerCurrency = collect($this->totals($company, $filters)['perCurrency'])
            ->map(fn ($sums) => [
                'Down Payment Amount' => $sums['amount'],
                'Settled Amount' => $sums['settled'],
                'Balance' => $sums['balance'],
            ])->all();

        return [$headings, $rows, $totalsPerCurrency];
    }

    public function exportExcel(Company $company, Request $request)
    {
        $filters = $this->filters($request);
        [$headings, $rows, $totalsPerCurrency] = $this->reportPayload($company, $filters);

        if ($rows->isEmpty()) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }

        return (new CurrencyTotalsReportExport($headings, $rows, ['Down Payment Amount', 'Settled Amount', 'Balance'], $totalsPerCurrency))
            ->download('Down-Payment-Report-'.$filters['type'].'-'.$filters['from_date'].'.xlsx');
    }

    public function print(Company $company, Request $request)
    {
        $filters = $this->filters($request);
        [$headings, $rows, $totalsPerCurrency] = $this->reportPayload($company, $filters);

        if ($rows->isEmpty()) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }

        $meta = [
            ['label' => __('Type'), 'value' => $this->availableTypes()[$filters['type']]],
            ['label' => __('From Date'), 'value' => $filters['from_date']],
            ['label' => __('Currency'), 'value' => implode(', ', array_map('strtoupper', $filters['currencies'])) ?: __('All')],
        ];
        foreach ($totalsPerCurrency as $currency => $sums) {
            $meta[] = [
                'label' => __('Total').' '.$currency,
                'value' => number_format($sums['Down Payment Amount'], 2).' / '.number_format($sums['Settled Amount'], 2).' / '.number_format($sums['Balance'], 2),
            ];
        }

        return $this->renderReportPrint(
            company: $company,
            title: __('Down Payment Report'),
            headings: $headings,
            rows: $rows,
            meta: $meta,
            numericHeadings: ['Down Payment Amount', 'Settled Amount', 'Balance'],
        );
    }
}
