<?php

namespace App\Http\Controllers;

use App\Exports\Statements\CurrencyTotalsReportExport;
use App\Models\Cheque;
use App\Models\Company;
use App\Models\PayableCheque;
use App\Traits\PaginatesStatementQueries;
use App\Traits\Reports\PrintsReport;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * ChequesReportController
 * ------------------------------------------------------------------
 * "Cheques Report" — every cheque whose due date is on or after a chosen
 * date, either the cheques RECEIVED from customers (Money Received) or the
 * cheques PAYABLE to suppliers (Money Payment) and to leasing companies
 * (installment cheques on contract_loan_schedules), in one or more currencies.
 *
 * Received cheques live in `cheques` (one row per Money Received cheque) and
 * payable cheques in `payable_cheques` (one per Money Payment cheque). Leasing
 * installment cheques are a second payable source. The two payable queries
 * are filtered on their own columns, then unioned, because a select alias
 * cannot be used in WHERE. Both expose the same column aliases so everything
 * after them is shared.
 *
 *   - Currency   is the currency the cheque itself is written in:
 *                receiving_currency / payment_currency, not the invoice's.
 *   - Bank       is the drawee bank for a received cheque (the customer's
 *                bank, as on the Money Received tabs) and the company's own
 *                bank for a payable one: delivery_bank_id on a Money Payment
 *                cheque, drawee_bank_id on a leasing installment. Both are
 *                financial-institution ids.
 *   - Cheques of a Cash Expense are not Money Payments and are not listed.
 *
 * The bank filter lives on the result page and narrows the table, the
 * totals, the Excel export and the printout together.
 */
class ChequesReportController
{
    use PaginatesStatementQueries;
    use PrintsReport;

    public const RECEIVED = 'received';
    public const PAYABLE = 'payable';

    private const ROWS_PER_PAGE = 50;

    /**
     * Only the cheque kinds this user holds the report permission for. Each
     * kind's permission is granted to whoever may open the screen it reads
     * (Money Received / Money Payment), so the report is not a side door.
     *
     * @return array<string, string> value => label
     */
    private function availableTypes(): array
    {
        $types = [];
        if (hasAuthFor('cheques_report_received.view')) {
            $types[self::RECEIVED] = __('Received Cheques');
        }
        if (hasAuthFor('cheques_report_payable.view')) {
            $types[self::PAYABLE] = __('Payable Cheques');
        }

        return $types;
    }

    public function index(Company $company)
    {
        $types = $this->availableTypes();

        return Inertia::render('Statements/ChequesReport/Index', [
            'company' => ['id' => $company->id],
            'types' => collect($types)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'currencies' => collect(getBanksCurrencies())->map(fn ($label, $code) => ['value' => $code, 'label' => strtoupper($label)])->values(),
            'urls' => [
                'result' => route('result.cheques.report', ['company' => $company->id]),
            ],
        ]);
    }

    /**
     * @return array{type: string, due_date: string, currencies: array<int, string>, bank_id: int|null}
     */
    private function filters(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in([self::RECEIVED, self::PAYABLE])],
            'due_date' => ['required', 'date'],
            'currencies' => ['nullable', 'array'],
            'currencies.*' => ['string', 'max:10'],
            'bank_id' => ['nullable', 'integer'],
        ]);

        abort_unless(array_key_exists($data['type'], $this->availableTypes()), 403);

        return [
            'type' => $data['type'],
            'due_date' => Carbon::parse($data['due_date'])->toDateString(),
            'currencies' => array_values(array_unique($data['currencies'] ?? [])),
            'bank_id' => isset($data['bank_id']) ? (int) $data['bank_id'] : null,
        ];
    }

    /**
     * Cheques received from customers. Column aliases are shared with
     * payableQuery() on purpose.
     */
    private function receivedQuery(Company $company): Builder
    {
        $locale = in_array(app()->getLocale(), ['en', 'ar'], true) ? app()->getLocale() : 'en';

        return DB::table('cheques')
            ->join('money_received', 'money_received.id', '=', 'cheques.money_received_id')
            ->leftJoin('partners', 'partners.id', '=', 'money_received.partner_id')
            ->leftJoin('banks', 'banks.id', '=', 'cheques.drawee_bank_id')
            ->where('money_received.company_id', $company->id)
            ->selectRaw(implode(', ', [
                'cheques.id AS id',
                'partners.name AS partner_name',
                'cheques.cheque_number AS cheque_number',
                'cheques.drawee_bank_id AS bank_id',
                "banks.name_{$locale} AS bank_name",
                'money_received.received_amount AS amount',
                'money_received.receiving_currency AS currency',
                'cheques.due_date AS due_date',
                'cheques.status AS status',
            ]));
    }

    /**
     * Cheques paid to suppliers through Money Payment.
     * `source` keeps a row key unique once leasing installments are unioned in.
     */
    private function payableQuery(Company $company): Builder
    {
        return DB::table('payable_cheques')
            ->join('money_payments', 'money_payments.id', '=', 'payable_cheques.money_payment_id')
            ->leftJoin('partners', 'partners.id', '=', 'money_payments.partner_id')
            ->leftJoin('financial_institutions', 'financial_institutions.id', '=', 'payable_cheques.delivery_bank_id')
            ->leftJoin('banks', 'banks.id', '=', 'financial_institutions.bank_id')
            ->where('money_payments.company_id', $company->id)
            ->selectRaw(implode(', ', [
                'payable_cheques.id AS id',
                'partners.name AS partner_name',
                'payable_cheques.cheque_number AS cheque_number',
                'payable_cheques.delivery_bank_id AS bank_id',
                "COALESCE(NULLIF(banks.view_name, ''), financial_institutions.name) AS bank_name",
                'money_payments.paid_amount AS amount',
                'money_payments.payment_currency AS currency',
                'payable_cheques.due_date AS due_date',
                'payable_cheques.status AS status',
                "'money_payment' AS source",
            ]));
    }

    /**
     * Post-dated installment cheques issued to a leasing company. These are
     * not rows in payable_cheques. The face amount is cheque_amount, so a
     * paid installment still shows the amount that was written on the cheque.
     * Currency is matched case-insensitively because some contracts store egp
     * while the report filter sends EGP.
     */
    private function leasingPayableQuery(Company $company, array $filters, bool $withBank): Builder
    {
        $currencies = array_map(fn ($currency) => strtoupper((string) $currency), $filters['currencies']);

        return DB::table('contract_loan_schedules')
            ->join('leasing_contracts', 'leasing_contracts.id', '=', 'contract_loan_schedules.leasing_contract_id')
            ->join('leasing_companies', 'leasing_companies.id', '=', 'leasing_contracts.leasing_company_id')
            ->leftJoin('financial_institutions', 'financial_institutions.id', '=', 'contract_loan_schedules.drawee_bank_id')
            ->leftJoin('banks', 'banks.id', '=', 'financial_institutions.bank_id')
            ->where('contract_loan_schedules.company_id', $company->id)
            ->where('contract_loan_schedules.cheque_amount', '>', 0)
            ->whereNotNull('contract_loan_schedules.date')
            // A blank or zero due date never qualifies. Comparing against the
            // literal 0000-00-00 is rejected when the server forbids zero dates,
            // and whereDate against the chosen due date already drops them.
            ->whereDate('contract_loan_schedules.date', '>=', $filters['due_date'])
            ->when($currencies !== [], fn ($q) => $q->whereIn(DB::raw('UPPER(leasing_contracts.currency)'), $currencies))
            ->when($withBank && $filters['bank_id'], fn ($q) => $q->where('contract_loan_schedules.drawee_bank_id', $filters['bank_id']))
            ->selectRaw(implode(', ', [
                'contract_loan_schedules.id AS id',
                'leasing_companies.name AS partner_name',
                'contract_loan_schedules.cheque_number AS cheque_number',
                'contract_loan_schedules.drawee_bank_id AS bank_id',
                "COALESCE(NULLIF(banks.view_name, ''), financial_institutions.name) AS bank_name",
                'contract_loan_schedules.cheque_amount AS amount',
                'leasing_contracts.currency AS currency',
                'contract_loan_schedules.date AS due_date',
                "IF(contract_loan_schedules.actual_payment_date IS NULL, 'pending', 'paid') AS status",
                "'leasing' AS source",
            ]));
    }

    /**
     * The WHERE clauses every query shares, written against the real columns
     * (a select alias can't be used in WHERE).
     */
    private function applyFilters(Builder $query, array $filters, bool $withBank): Builder
    {
        $received = $filters['type'] === self::RECEIVED;
        $dueDate = $received ? 'cheques.due_date' : 'payable_cheques.due_date';
        $currency = $received ? 'money_received.receiving_currency' : 'money_payments.payment_currency';
        $bank = $received ? 'cheques.drawee_bank_id' : 'payable_cheques.delivery_bank_id';

        return $query
            ->whereDate($dueDate, '>=', $filters['due_date'])
            ->when($filters['currencies'] !== [], fn ($q) => $q->whereIn($currency, $filters['currencies']))
            ->when($withBank && $filters['bank_id'], fn ($q) => $q->where($bank, $filters['bank_id']));
    }

    /**
     * A fresh builder per call - paginate()/first() mutate what they run on
     * (see PaginatesStatementQueries).
     */
    private function freshQuery(Company $company, array $filters, bool $withBank = true): Builder
    {
        if ($filters['type'] === self::RECEIVED) {
            return $this->applyFilters($this->receivedQuery($company), $filters, $withBank)
                ->orderBy('cheques.due_date')
                ->orderBy('cheques.id');
        }

        // Filter each payable source on its own tables, then sort the union
        // by the shared aliases. applyFilters() cannot run on the union:
        // it names payable_cheques / money_payments columns the leasing
        // half does not have.
        return DB::query()
            ->fromSub(
                $this->applyFilters($this->payableQuery($company), $filters, $withBank)
                    ->unionAll($this->leasingPayableQuery($company, $filters, $withBank)),
                'payable_cheques_report'
            )
            ->orderBy('due_date')
            ->orderBy('id')
            ->orderBy('source');
    }

    /**
     * Count and amount per currency over the WHOLE filtered range - adding
     * different currencies together would be meaningless.
     *
     * @return array{count: int, perCurrency: array<string, float>}
     */
    private function totals(Company $company, array $filters): array
    {
        $rows = DB::query()
            ->fromSub($this->freshQuery($company, $filters)->reorder(), 'cheques_report')
            ->selectRaw('cheques_report.currency AS currency, COUNT(*) AS cheques_count, COALESCE(SUM(cheques_report.amount), 0) AS total')
            ->groupBy('cheques_report.currency')
            ->orderBy('cheques_report.currency')
            ->get();

        $perCurrency = [];
        $count = 0;
        foreach ($rows as $row) {
            $perCurrency[strtoupper((string) ($row->currency ?: '-'))] = round((float) $row->total, 2);
            $count += (int) $row->cheques_count;
        }

        return ['count' => $count, 'perCurrency' => $perCurrency];
    }

    /**
     * The banks that appear in the range BEFORE the bank filter is applied,
     * so choosing one doesn't make the others vanish from the list.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function bankOptions(Company $company, array $filters): array
    {
        return DB::query()
            ->fromSub($this->freshQuery($company, $filters, withBank: false)->reorder(), 'cheques_banks')
            ->selectRaw('cheques_banks.bank_id AS id, MAX(cheques_banks.bank_name) AS name')
            ->whereNotNull('cheques_banks.bank_id')
            ->groupBy('cheques_banks.bank_id')
            ->orderBy('name')
            ->get()
            ->map(fn ($bank) => ['id' => (int) $bank->id, 'name' => $bank->name ?: __('N/A')])
            ->all();
    }

    private function statusLabel(string $type, ?string $status): string
    {
        $labels = $type === self::RECEIVED
            ? [
                Cheque::IN_SAFE => __('In Safe'),
                Cheque::UNDER_COLLECTION => __('Under Collection'),
                Cheque::COLLECTED => __('Collected'),
                Cheque::REJECTED => __('Rejected'),
            ]
            : [
                PayableCheque::PENDING => __('Pending'),
                PayableCheque::PAID => __('Paid'),
            ];

        return $labels[$status] ?? (string) $status;
    }

    private function mapRow(object $row, string $type): array
    {
        return [
            'id' => (int) $row->id,
            'partnerName' => $row->partner_name ?: '-',
            'chequeNumber' => $row->cheque_number,
            'bankName' => $row->bank_name ?: __('N/A'),
            'amount' => (float) $row->amount,
            'currency' => strtoupper((string) $row->currency),
            'dueDate' => $row->due_date ? Carbon::parse($row->due_date)->format('d-m-Y') : null,
            'status' => $row->status,
            'statusLabel' => $this->statusLabel($type, $row->status),
            'source' => isset($row->source) ? (string) $row->source : $type,
        ];
    }

    public function result(Company $company, Request $request)
    {
        $filters = $this->filters($request);
        $type = $filters['type'];

        $paginator = $this->paginateStatement(fn () => $this->freshQuery($company, $filters), self::ROWS_PER_PAGE);
        $paginator->setCollection($paginator->getCollection()->map(fn ($row) => $this->mapRow($row, $type)));
        $totals = $this->totals($company, $filters);

        return Inertia::render('Statements/ChequesReport/Result', [
            'company' => ['id' => $company->id],
            'type' => $type,
            'typeLabel' => $this->availableTypes()[$type],
            'partnerHeading' => $type === self::RECEIVED ? __('Customer Name') : __('Supplier Name'),
            'dueDate' => $filters['due_date'],
            'currencies' => array_map('strtoupper', $filters['currencies']),
            'bankId' => $filters['bank_id'],
            'banks' => $this->bankOptions($company, $filters),
            'kpis' => [
                'count' => $totals['count'],
                'perCurrency' => collect($totals['perCurrency'])->map(fn ($total, $currency) => ['currency' => $currency, 'total' => $total])->values(),
            ],
            'paginator' => $paginator->toArray(),
            'urls' => [
                'index' => route('view.cheques.report', ['company' => $company->id]),
                'result' => route('result.cheques.report', ['company' => $company->id]),
                'exportUrl' => route('export.cheques.report', ['company' => $company->id] + $request->query()),
                'printUrl' => route('print.cheques.report', ['company' => $company->id] + $request->query()),
            ],
        ]);
    }

    /**
     * Headings and rows for the WHOLE filtered range - shared by Excel and
     * Print so both stay unpaginated and agree with each other.
     *
     * @return array{0: array<int, string>, 1: \Illuminate\Support\Collection, 2: array<string, float>}
     */
    private function reportPayload(Company $company, array $filters): array
    {
        $type = $filters['type'];
        $partnerHeading = $type === self::RECEIVED ? 'Customer Name' : 'Supplier Name';
        $headings = ['#', $partnerHeading, 'Cheque Number', 'Bank Name', 'Amount', 'Currency', 'Due Date', 'Status'];

        $rows = $this->freshQuery($company, $filters)->get()->values()->map(function ($row, $index) use ($type, $partnerHeading) {
            $mapped = $this->mapRow($row, $type);

            return [
                '#' => $index + 1,
                $partnerHeading => $mapped['partnerName'],
                'Cheque Number' => $mapped['chequeNumber'],
                'Bank Name' => $mapped['bankName'],
                'Amount' => $mapped['amount'],
                'Currency' => $mapped['currency'],
                'Due Date' => $mapped['dueDate'],
                'Status' => $mapped['statusLabel'],
            ];
        });

        return [$headings, $rows, $this->totals($company, $filters)['perCurrency']];
    }

    private function fileName(array $filters): string
    {
        return 'Cheques-Report-'.$filters['type'].'-'.$filters['due_date'].'.xlsx';
    }

    public function exportExcel(Company $company, Request $request)
    {
        $filters = $this->filters($request);
        [$headings, $rows, $perCurrency] = $this->reportPayload($company, $filters);

        if ($rows->isEmpty()) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }

        $totalsPerCurrency = collect($perCurrency)->map(fn ($total) => ['Amount' => $total])->all();

        return (new CurrencyTotalsReportExport($headings, $rows, ['Amount'], $totalsPerCurrency))
            ->download($this->fileName($filters));
    }

    public function print(Company $company, Request $request)
    {
        $filters = $this->filters($request);
        [$headings, $rows, $perCurrency] = $this->reportPayload($company, $filters);

        if ($rows->isEmpty()) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }

        $bankName = collect($this->bankOptions($company, $filters))->firstWhere('id', $filters['bank_id'])['name'] ?? null;

        $meta = [
            ['label' => __('Type'), 'value' => $this->availableTypes()[$filters['type']]],
            ['label' => __('Due Date From'), 'value' => $filters['due_date']],
            ['label' => __('Currency'), 'value' => implode(', ', array_map('strtoupper', $filters['currencies'])) ?: __('All')],
            ['label' => __('Bank'), 'value' => $bankName],
        ];
        foreach ($perCurrency as $currency => $total) {
            $meta[] = ['label' => __('Total').' '.$currency, 'value' => number_format($total, 2)];
        }

        return $this->renderReportPrint(
            company: $company,
            title: __('Cheques Report'),
            headings: $headings,
            rows: $rows,
            meta: $meta,
            numericHeadings: ['Amount'],
        );
    }
}
