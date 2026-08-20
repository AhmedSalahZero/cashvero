<?php

namespace App\Http\Controllers;

use App\Exports\Statements\FactoringChargesStatementExport;
use App\Models\Company;
use App\Models\FactoringCompany;
use App\Models\FactoringContract;
use App\Models\FactoringTransaction;
use App\Support\ShareholderAccounts\AccountNumberLabel;
use App\Traits\GeneralFunctions;
use App\Traits\PaginatesRawCollections;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * FactoringChargesStatementController
 * ------------------------------------------------------------------
 * Renders the "Factoring Charges Statement" report — the second tab
 * on the Factoring Statement page (Statements sidebar section, via
 * Factoring Statement's own "Factoring Charges Statement" tab). Lists
 * every charge (Factoring Interest, Other Charges, Uncollected
 * Invoice Charges on rejected with-recourse transactions) for a
 * Factoring Company in one currency — across ALL its contracts, or
 * scoped to one specific contract — with a running total. Reads from
 * `factoring_transactions`, not `factoring_statements` — a genuinely
 * different source table from its sibling Factoring Statement report.
 * The row-building logic (buildChargeRows()/buildRowComment()/
 * dateInRange()) is UNCHANGED, deliberately.
 *
 * ⚠️ Factoring Contract is OPTIONAL here (unlike Factoring Statement,
 * where a contract is required) — leaving it blank means "all
 * contracts for this company in this currency," confirmed from the
 * original form's "All Contracts" placeholder option and the
 * controller's own nullable handling. Preserved exactly.
 *
 * ── Frontend migration status ───────────────────────────────────
 *   - index()        → ✅ Migrated to Inertia (Statements/FactoringChargesStatement/Index)
 *   - getCurrencies() → ✅ Same query-param-instead-of-path-segment change as
 *                      FactoringStatementController@getCurrencies (see its
 *                      docblock for the full rationale) — business logic
 *                      UNCHANGED.
 *   - getContracts()  → ✅ Same change as getCurrencies(). UNCHANGED business logic.
 *   - result()        → ✅ Migrated to Inertia (Statements/FactoringChargesStatement/Result).
 *                      Row-building (fetchChargeRows(), buildChargeRows(),
 *                      buildRowComment(), dateInRange()) is UNCHANGED. Real
 *                      server-side pagination is new — the original sent every
 *                      matching charge row to the Blade view at once.
 *   - exportExcel()   → ✅ New (project-owner requested). Reuses fetchChargeRows()
 *                      so the workbook can never drift from what's on screen.
 *                      Styled via the new
 *                      App\Exports\Statements\FactoringChargesStatementExport.
 */
class FactoringChargesStatementController
{
    use GeneralFunctions;
    use PaginatesRawCollections;

    /**
     * Filter form: date range, Factoring Company → Currency → Factoring
     * Contract (optional — blank means every contract). Renders
     * Statements/FactoringChargesStatement/Index.vue.
     */
    public function index(Company $company)
    {
        return \Inertia\Inertia::render('Statements/FactoringChargesStatement/Index', [
            'company' => ['id' => $company->id],
            'factoringCompanies' => $company->factoringCompanies()->orderBy('name')->get()->map(fn ($fc) => [
                'id' => $fc->id,
                'name' => $fc->getName(),
            ])->values(),
            'urls' => [
                'result' => route('result.factoring.charges.statement', ['company' => $company->id]),
                'currencies' => route('factoring.charges.statement.currencies', ['company' => $company->id]),
                'contracts' => route('factoring.charges.statement.contracts', ['company' => $company->id]),
                'statementUrl' => route('view.factoring.statement', ['company' => $company->id]),
            ],
        ]);
    }

    /**
     * UNCHANGED business logic — only reads factoring_company_id from a
     * query parameter now instead of a route-bound model (matches
     * FactoringStatementController@getCurrencies).
     */
    public function getCurrencies(Company $company, Request $request)
    {
        $factoringCompany = FactoringCompany::where('company_id', $company->id)
            ->findOrFail($request->integer('factoring_company_id'));

        $currencies = $company->factoringContracts()
            ->where('factoring_company_id', $factoringCompany->id)
            ->pluck('currency')
            ->unique()
            ->filter()
            ->mapWithKeys(function ($currency) {
                $allCurrencies = getCurrencies();

                return [$currency => $allCurrencies[$currency] ?? strtoupper($currency)];
            });

        return response()->json(['status' => true, 'currencies' => $currencies]);
    }

    /**
     * UNCHANGED business logic — same query-parameter change as
     * getCurrencies() above.
     */
    public function getContracts(Company $company, Request $request)
    {
        $factoringCompany = FactoringCompany::where('company_id', $company->id)
            ->findOrFail($request->integer('factoring_company_id'));
        $currency = (string) $request->get('currency');
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');

        $contracts = $company->factoringContracts()
            ->where('factoring_company_id', $factoringCompany->id)
            ->where('currency', $currency)
            ->when($startDate, fn ($query) => $query->where('contract_end_date', '>=', $startDate))
            ->when($endDate, fn ($query) => $query->where('contract_start_date', '<=', $endDate))
            ->get()
            ->map(fn (FactoringContract $contract) => [
                'id' => $contract->id,
                'label' => $contract->getContractStartDateFormatted().' — '.$contract->getContractEndDateFormatted()
                    .' | '.strtoupper($contract->getCurrency() ?? '')
                    .' | '.$contract->getLimitFormatted(),
            ]);

        return response()->json(['status' => true, 'contracts' => $contracts]);
    }

    /**
     * Builds the raw, already-running-total-computed charge rows for the
     * current filters. UNCHANGED from the original controller — same
     * validation, same query, same buildChargeRows()/running-total
     * accumulation.
     *
     * Returns null when no charge rows match.
     */
    private function fetchChargeRows(Company $company, Request $request): ?array
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'factoring_company_id' => 'required|integer',
            'currency' => 'required|string',
            'factoring_contract_id' => 'nullable|integer',
        ]);

        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $currency = $request->get('currency');
        $contractId = $request->integer('factoring_contract_id') ?: null;

        $factoringCompany = FactoringCompany::where('company_id', $company->id)
            ->findOrFail($request->integer('factoring_company_id'));

        $contract = null;
        if ($contractId) {
            $contract = FactoringContract::where('company_id', $company->id)
                ->where('factoring_company_id', $factoringCompany->id)
                ->findOrFail($contractId);

            abort_unless($contract->currency === $currency, 422);
        }

        $transactions = FactoringTransaction::query()
            ->with(['customer', 'customerInvoice', 'factoringContract'])
            ->where('company_id', $company->id)
            ->where('factoring_company_id', $factoringCompany->id)
            ->where('invoice_currency', $currency)
            ->when($contractId, fn ($query) => $query->where('factoring_contract_id', $contractId))
            ->get();

        $rows = $this->buildChargeRows($transactions, $startDate, $endDate);

        if (empty($rows)) {
            return null;
        }

        $runningTotal = 0.0;
        foreach ($rows as &$row) {
            $runningTotal = round($runningTotal + $row['amount'], 2);
            $row['running_total'] = $runningTotal;
            $row['date'] = Carbon::make($row['raw_date'])->format('d-m-Y');
            unset($row['raw_date'], $row['sort_order']);
        }
        unset($row);

        return [
            'rows' => collect($rows),
            'factoringCompanyName' => $factoringCompany->getName(),
            'contractLabel' => $contract
                ? $contract->getContractStartDateFormatted().' — '.$contract->getContractEndDateFormatted()
                : __('All Contracts'),
            'currency' => strtoupper((string) $currency),
            'startDate' => $startDate,
            'endDate' => $endDate,
        ];
    }

    /** UNCHANGED — which charge rows exist for a set of transactions, per the class docblock. */
    protected function buildChargeRows($transactions, string $startDate, string $endDate): array
    {
        $rows = [];

        foreach ($transactions as $transaction) {
            $comment = $this->buildRowComment($transaction);

            if (
                (float) $transaction->factoring_interest_amount > 0
                && $this->dateInRange($transaction->factoring_date, $startDate, $endDate)
            ) {
                $rows[] = [
                    'raw_date' => $transaction->factoring_date,
                    'factoring_transaction_id' => $transaction->id,
                    'sort_order' => 1,
                    'charge_type' => __('Factoring Interest'),
                    'amount' => (float) $transaction->factoring_interest_amount,
                    'comment' => $comment,
                ];
            }

            if (
                (float) $transaction->other_charges > 0
                && $this->dateInRange($transaction->factoring_date, $startDate, $endDate)
            ) {
                $rows[] = [
                    'raw_date' => $transaction->factoring_date,
                    'factoring_transaction_id' => $transaction->id,
                    'sort_order' => 2,
                    'charge_type' => __('Other Charges'),
                    'amount' => (float) $transaction->other_charges,
                    'comment' => $comment,
                ];
            }

            if (
                $transaction->recourse_type === FactoringTransaction::WITH_RECOURSE
                && $transaction->isRejected()
                && (float) $transaction->uncollected_invoice_charges > 0
                && $transaction->rejection_date
                && $this->dateInRange($transaction->rejection_date, $startDate, $endDate)
            ) {
                $rows[] = [
                    'raw_date' => $transaction->rejection_date,
                    'factoring_transaction_id' => $transaction->id,
                    'sort_order' => 3,
                    'charge_type' => __('Uncollected Invoices Charges'),
                    'amount' => (float) $transaction->uncollected_invoice_charges,
                    'comment' => $comment,
                ];
            }
        }

        usort($rows, function (array $a, array $b) {
            $dateCompare = strcmp($a['raw_date'], $b['raw_date']);
            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            $transactionCompare = $a['factoring_transaction_id'] <=> $b['factoring_transaction_id'];
            if ($transactionCompare !== 0) {
                return $transactionCompare;
            }

            return $a['sort_order'] <=> $b['sort_order'];
        });

        return $rows;
    }

    /** UNCHANGED — per the class docblock. */
    protected function buildRowComment(FactoringTransaction $transaction): string
    {
        $invoiceNumber = $transaction->customerInvoice?->invoice_number ?? '';
        $customerName = $transaction->customer?->getName() ?? '';
        $contract = $transaction->factoringContract;
        $contractLabel = $contract
            ? $contract->getContractStartDateFormatted().' — '.$contract->getContractEndDateFormatted()
            : '';
        $recourseLabel = $transaction->recourse_type === FactoringTransaction::WITH_RECOURSE
            ? __('With Recourse')
            : __('Without Recourse');

        return (string) AccountNumberLabel::decorateText(
            (int) ($transaction->company_id ?? 0),
            __('Invoice #:invoice | Customer: :customer | Contract: :contract | :recourse', [
                'invoice' => $invoiceNumber,
                'customer' => $customerName,
                'contract' => $contractLabel,
                'recourse' => $recourseLabel,
            ])
        );
    }

    /** UNCHANGED — per the class docblock. */
    protected function dateInRange(?string $date, string $startDate, string $endDate): bool
    {
        if (! $date) {
            return false;
        }

        $value = Carbon::make($date)->format('Y-m-d');

        return $value >= $startDate && $value <= $endDate;
    }

    /**
     * The report itself. Row-building lives in fetchChargeRows() and is
     * UNCHANGED. Real server-side pagination is new — see class docblock.
     */
    public function result(Company $company, Request $request)
    {
        $data = $this->fetchChargeRows($company, $request);
        if (is_null($data)) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }
        $rows = $data['rows'];

        $kpis = [
            'totalCharges' => (float) $rows->sum('amount'),
            'endingRunningTotal' => (float) ($rows->last()['running_total'] ?? 0),
            'transactionCount' => $rows->count(),
        ];

        $paginator = $this->paginateCollection($rows, 50, $request);

        return \Inertia\Inertia::render('Statements/FactoringChargesStatement/Result', [
            'company' => ['id' => $company->id],
            'currency' => $data['currency'],
            'factoringCompanyName' => $data['factoringCompanyName'],
            'contractLabel' => $data['contractLabel'],
            'startDate' => $data['startDate'],
            'endDate' => $data['endDate'],
            'kpis' => $kpis,
            'paginator' => $paginator->toArray(),
            'urls' => [
                'backUrl' => route('view.factoring.charges.statement', ['company' => $company->id]),
                'statementUrl' => route('view.factoring.statement', ['company' => $company->id]),
                'exportUrl' => route('export.factoring.charges.statement', array_merge(['company' => $company->id], $request->only([
                    'start_date', 'end_date', 'factoring_company_id', 'factoring_contract_id', 'currency',
                ]))),
            ],
        ]);
    }

    /**
     * Excel export (project-owner requested) — same filters as result(),
     * reusing fetchChargeRows(). Styled via the new
     * App\Exports\Statements\FactoringChargesStatementExport — no new
     * export library introduced.
     */
    public function exportExcel(Company $company, Request $request)
    {
        $data = $this->fetchChargeRows($company, $request);
        if (is_null($data)) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }

        $headings = ['#', 'Date', 'Charge Type', 'Amount', 'Running Total', 'Comment'];
        $rows = $data['rows']->values()->map(fn ($row, $index) => [
            '#' => $index + 1,
            'Date' => $row['date'],
            'Charge Type' => $row['charge_type'],
            'Amount' => $row['amount'],
            'Running Total' => $row['running_total'],
            'Comment' => $row['comment'],
        ]);

        $fileNameParts = ['Factoring-Charges-Statement', $data['factoringCompanyName'], $data['currency']];
        $fileName = preg_replace('/[^A-Za-z0-9\-]+/', '-', implode('-', $fileNameParts)).'.xlsx';

        return (new FactoringChargesStatementExport($headings, $rows))->download($fileName);
    }
}
