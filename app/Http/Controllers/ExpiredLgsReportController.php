<?php

namespace App\Http\Controllers;

use App\Enums\LgSources;
use App\Enums\LgTypes;
use App\Exports\Statements\CurrencyTotalsReportExport;
use App\Models\Company;
use App\Models\LetterOfGuaranteeIssuance;
use App\Traits\LgListReportRows;
use App\Traits\Reports\PrintsReport;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Expired LGs — every Letter of Guarantee whose expiry (renewal_date) has
 * passed as of TODAY and that was never cancelled. Sits under LG By Bank
 * Name in the Statements menu and shares its row shape (LgListReportRows).
 *
 * The date is not a filter: it is always today, set on the server, so the
 * report cannot be pointed at another day. The filters are four
 * multi-selects — currencies, banks, LG types and LG sources. When more
 * than one currency is picked the result shows one tab per currency, each
 * with its own totals; the export and the printout keep one totals row per
 * currency for the same reason (amounts in different currencies never add up).
 *
 * "Expired" means the same thing everywhere in the app:
 * status != cancelled AND renewal_date <= today
 * (LetterOfGuaranteeIssuance::getStatus(), LgByBankNameReportController).
 */
class ExpiredLgsReportController
{
    use LgListReportRows;
    use PrintsReport;

    public function index(Company $company)
    {
        $base = DB::table('letter_of_guarantee_issuances')->where('letter_of_guarantee_issuances.company_id', $company->id);

        $currencies = (clone $base)->whereNotNull('lg_currency')->distinct()->orderBy('lg_currency')->pluck('lg_currency');

        // Every bank the company has LGs with — not per currency, since
        // several currencies can be picked at once.
        $banks = (clone $base)
            ->join('financial_institutions', 'financial_institutions.id', '=', 'letter_of_guarantee_issuances.financial_institution_id')
            ->join('banks', 'banks.id', '=', 'financial_institutions.bank_id')
            ->distinct()
            ->orderBy('banks.name_en')
            ->get(['financial_institutions.id', 'banks.name_en']);

        return Inertia::render('Statements/ExpiredLgs/Index', [
            'company' => ['id' => $company->id],
            'today' => now()->format('Y-m-d'),
            'currencies' => $currencies->map(fn ($currency) => ['value' => $currency, 'label' => strtoupper($currency)])->values(),
            'banks' => $banks->map(fn ($bank) => ['value' => (int) $bank->id, 'label' => $bank->name_en])->values(),
            'lgTypes' => collect(LgTypes::getAll())->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'lgSources' => collect(LgSources::getAll())->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'urls' => [
                'result' => route('result.expired.lgs.report', ['company' => $company->id]),
            ],
        ]);
    }

    /**
     * @return array{currencies: list<string>, bank_id: list<int>, lg_types: list<string>, sources: list<string>}
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'currencies' => ['required', 'array', 'min:1'],
            'currencies.*' => ['string', 'max:10'],
            'bank_id' => ['required', 'array', 'min:1'],
            'bank_id.*' => ['integer'],
            'lg_types' => ['required', 'array', 'min:1'],
            'lg_types.*' => ['string'],
            'sources' => ['required', 'array', 'min:1'],
            'sources.*' => ['string'],
        ]);

        return [
            'currencies' => array_values($validated['currencies']),
            'bank_id' => array_map('intval', $validated['bank_id']),
            'lg_types' => array_values($validated['lg_types']),
            'sources' => array_values($validated['sources']),
        ];
    }

    /**
     * Every expired LG matching the filters, ordered by currency then by
     * expiry — the whole set, since the tabs, the export and the print all
     * need every row.
     */
    private function fetchRows(Company $company, array $filters): Collection
    {
        return DB::table('letter_of_guarantee_issuances')
            ->where('letter_of_guarantee_issuances.company_id', $company->id)
            ->where('letter_of_guarantee_issuances.status', '!=', LetterOfGuaranteeIssuance::CANCELLED)
            // Always today — never a date sent by the client.
            ->whereDate('letter_of_guarantee_issuances.renewal_date', '<=', now()->format('Y-m-d'))
            ->whereIn('letter_of_guarantee_issuances.lg_currency', $filters['currencies'])
            ->whereIn('letter_of_guarantee_issuances.financial_institution_id', $filters['bank_id'])
            ->whereIn('letter_of_guarantee_issuances.lg_type', $filters['lg_types'])
            // source is nullable; an empty one means an LG-facility LG (LetterOfGuaranteeIssuance::getSource()).
            ->whereIn(DB::raw("COALESCE(letter_of_guarantee_issuances.source, '".LetterOfGuaranteeIssuance::LG_FACILITY."')"), $filters['sources'])
            ->leftJoin('partners', 'partners.id', '=', 'letter_of_guarantee_issuances.partner_id')
            ->join('financial_institutions', 'financial_institutions.id', '=', 'letter_of_guarantee_issuances.financial_institution_id')
            ->join('banks', 'banks.id', '=', 'financial_institutions.bank_id')
            ->selectRaw(
                'letter_of_guarantee_issuances.id as id, partners.name as partner_name, letter_of_guarantee_issuances.lg_type as lg_type, '
                .'letter_of_guarantee_issuances.transaction_name as transaction_name, letter_of_guarantee_issuances.lg_code as lg_code, '
                ."COALESCE(letter_of_guarantee_issuances.source, '".LetterOfGuaranteeIssuance::LG_FACILITY."') as source, "
                .'banks.name_en as financial_institution_name, letter_of_guarantee_issuances.lg_amount as lg_amount, '
                ."DATE_FORMAT(letter_of_guarantee_issuances.renewal_date, '%d-%m-%Y') as renewal_date, "
                .'DATEDIFF(CURDATE(), letter_of_guarantee_issuances.renewal_date) as expired_since_days, '
                .self::CASH_COVER_SQL.' as cash_cover_amount, letter_of_guarantee_issuances.is_activated as is_activated, '
                ."letter_of_guarantee_issuances.lg_commission_rate as lg_commission_rate, 'expired' as lg_status, "
                .'letter_of_guarantee_issuances.lg_currency as lg_currency'
            )
            ->orderBy('letter_of_guarantee_issuances.lg_currency')
            ->orderBy('letter_of_guarantee_issuances.renewal_date')
            ->orderBy('letter_of_guarantee_issuances.id')
            ->get();
    }

    /**
     * The rows grouped per currency, each with its own totals.
     *
     * @return array<string, array{rows: list<array<string, mixed>>, totalLgAmount: float, totalCashCover: float, count: int}>
     */
    private function groupByCurrency(Collection $rows): array
    {
        $lgTypes = LgTypes::getAll();
        $lgSources = LgSources::getAll();

        return $rows->groupBy('lg_currency')->map(function (Collection $currencyRows) use ($lgTypes, $lgSources) {
            $mapped = $currencyRows->map(fn ($row) => $this->mapLgListRow($row, $lgTypes, $lgSources)
                + ['expiredSinceDays' => (int) $row->expired_since_days])->values();

            return [
                'rows' => $mapped->all(),
                'totalLgAmount' => (float) $mapped->sum('lgAmount'),
                'totalCashCover' => (float) $mapped->sum('cashCoverAmount'),
                'count' => $mapped->count(),
            ];
        })->all();
    }

    public function result(Company $company, Request $request)
    {
        $filters = $this->filters($request);
        $groups = $this->groupByCurrency($this->fetchRows($company, $filters));

        if ($groups === []) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }

        $query = ['company' => $company->id] + $filters;

        return Inertia::render('Statements/ExpiredLgs/Result', [
            'company' => ['id' => $company->id],
            'today' => now()->format('d-m-Y'),
            'groups' => $groups,
            'urls' => [
                'backUrl' => route('view.expired.lgs.report', ['company' => $company->id]),
                'exportUrl' => route('export.expired.lgs.report', $query),
                'printUrl' => route('print.expired.lgs.report', $query),
            ],
        ]);
    }

    /**
     * The headings, the rows and the per-currency totals for the WHOLE
     * report — shared by the Excel export and Print so the two can never
     * disagree, and neither is paginated.
     *
     * @return array{0: array<int, string>, 1: Collection, 2: array<string, array<string, float>>}
     */
    private function reportPayload(Company $company, Request $request): array
    {
        $groups = $this->groupByCurrency($this->fetchRows($company, $this->filters($request)));

        $headings = ['#', 'Currency', 'Bank Name', 'Beneficiary Name', 'LG Type', 'Transaction Name', 'LG Code', 'Source', 'Amount', 'Expiry Date', 'Expired Since (Days)', 'Cash Cover'];

        $rows = collect();
        $totalsPerCurrency = [];
        foreach ($groups as $currency => $group) {
            foreach ($group['rows'] as $mapped) {
                $rows->push([
                    '#' => $rows->count() + 1,
                    'Currency' => strtoupper($currency),
                    'Bank Name' => $mapped['financialInstitutionName'],
                    'Beneficiary Name' => $mapped['partnerName'],
                    'LG Type' => $mapped['lgType'],
                    'Transaction Name' => $mapped['transactionName'],
                    'LG Code' => $this->lgCodeForExport($mapped),
                    'Source' => $mapped['source'],
                    'Amount' => $mapped['lgAmount'],
                    'Expiry Date' => $mapped['renewalDate'],
                    'Expired Since (Days)' => $mapped['expiredSinceDays'],
                    'Cash Cover' => $mapped['cashCoverAmount'],
                ]);
            }
            $totalsPerCurrency[strtoupper($currency)] = ['Amount' => $group['totalLgAmount'], 'Cash Cover' => $group['totalCashCover']];
        }

        return [$headings, $rows, $totalsPerCurrency];
    }

    public function exportExcel(Company $company, Request $request)
    {
        [$headings, $rows, $totalsPerCurrency] = $this->reportPayload($company, $request);

        if ($rows->isEmpty()) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }

        return (new CurrencyTotalsReportExport($headings, $rows, ['Amount', 'Cash Cover'], $totalsPerCurrency))
            ->download('Expired-LGs-'.now()->format('Y-m-d').'.xlsx');
    }

    public function print(Company $company, Request $request)
    {
        [$headings, $rows, $totalsPerCurrency] = $this->reportPayload($company, $request);

        if ($rows->isEmpty()) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }

        $meta = [
            ['label' => __('Date'), 'value' => now()->format('d-m-Y')],
            ['label' => __('Currency'), 'value' => implode(', ', array_keys($totalsPerCurrency))],
        ];
        foreach ($totalsPerCurrency as $currency => $totals) {
            $meta[] = ['label' => __('Total Amount').' '.$currency, 'value' => number_format($totals['Amount'], 2)];
            $meta[] = ['label' => __('Total Cash Cover').' '.$currency, 'value' => number_format($totals['Cash Cover'], 2)];
        }

        return $this->renderReportPrint(
            company: $company,
            title: __('Expired LGs'),
            headings: $headings,
            rows: $rows,
            meta: $meta,
            numericHeadings: ['Amount', 'Cash Cover'],
        );
    }
}
