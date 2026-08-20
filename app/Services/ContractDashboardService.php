<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Contract;
use App\Helpers\HDate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Customer Contract Dashboard aggregates.
 *
 * Scope: contracts.model_type = Customer only. (Supplier contracts are
 * the sub-contracts hanging off these — including them would double
 * count.)
 *
 * ── Two different money bases, deliberately kept apart ──────────────
 * A contract's `amount` is its value BEFORE tax, so billing progress
 * has to be measured against the invoice amount before tax:
 *
 *   invoiced  = SUM(customer_invoices.invoice_amount)      ex-VAT
 *   remaining = contracts.amount − invoiced                ex-VAT
 *
 * Receivables are a different question and are measured on the
 * VAT-inclusive figures, which reconcile among themselves:
 *
 *   billed − collected − deductions = uncollected
 *
 * ⚠️ REAL BUG FIXED HERE: these two used to sit in one row as
 * "Invoiced / Collected / Uncollected", so a 52,000 invoice showed
 * 52,000 invoiced next to 59,280 uncollected — the VAT made the row
 * impossible to reconcile. They are now separate sections with their
 * own totals.
 *
 * ⚠️ REAL BUG FIXED HERE: the invoice aggregate joined on
 * contract_code ALONE. A contract's invoices are not guaranteed to be
 * in the contract's currency — the data has EGP invoices posted
 * against a USD contract — and those were summed straight into the
 * contract's own currency. One USD contract worth 1,157,328 reported
 * 1,896,506 invoiced and −739,178 remaining. The aggregate is now
 * keyed by (contract_code, currency) and only the matching currency is
 * counted; anything else is reported under Data Quality rather than
 * silently corrupting the totals.
 *
 * ⚠️ REAL BUG FIXED HERE: cancelled invoices (is_canceled = 1) were
 * counted as billed.
 */
class ContractDashboardService
{
    private const NEAR_EXPIRY_DAYS = 30;

    private const TOP_CUSTOMERS = 10;

    /**
     * How far back the period defaults to when nobody picks a start
     * date.
     */
    private const DEFAULT_PERIOD_YEARS = 2;

    /**
     * Overdue buckets, in days past the due date. The last one is
     * open-ended.
     */
    private const AGING_BUCKETS = [
        'not_due' => [null, 0],
        'd1_30' => [1, 30],
        'd31_60' => [31, 60],
        'd61_90' => [61, 90],
        'd90_plus' => [91, null],
    ];

    /**
     * The report covers a period: everything is judged AS OF its end
     * date, and the activity sections cover the whole span.
     *
     * The two halves mean different things, on purpose:
     *
     *   Cumulative — contract value, invoiced-to-date, remaining,
     *     receivables and their aging. These are a position, so they
     *     count everything up to the END date. Narrowing them to the
     *     period would make "remaining to invoice" wrong, because
     *     invoices raised before the period would silently reappear as
     *     unbilled.
     *
     *   Period — what actually happened between the two dates:
     *     invoiced, collected, and the month-by-month trend.
     *
     * End defaults to today, start to two years before it.
     */
    public function build(Company $company, ?string $startDate = null, ?string $endDate = null): array
    {
        $today = $this->normaliseDate($endDate) ?? Carbon::today()->format('Y-m-d');
        $periodStart = $this->normaliseDate($startDate)
            ?? Carbon::make($today)->subYearsNoOverflow(self::DEFAULT_PERIOD_YEARS)->format('Y-m-d');

        // A start after the end is someone typing, not a request — swap
        // them rather than returning an empty report.
        if ($periodStart > $today) {
            [$periodStart, $today] = [$today, $periodStart];
        }

        $mainCurrency = strtoupper((string) $company->getMainFunctionalCurrency());

        $rows = $this->contractRows($company, $today);

        $currencies = $rows->pluck('currency')->filter()->unique()->sort()->values()->all();

        return [
            'aging' => $this->aging($company, $currencies, $today),
            'agingBuckets' => $this->agingBucketLabels(),
            'period' => $this->period($company, $currencies, $periodStart, $today),
            'trend' => $this->trend($company, $currencies, $periodStart, $today),
            'counts' => $this->counts($rows),
            'currencies' => $currencies,
            'byCurrency' => $this->byCurrency($rows, $currencies),
            'mainCurrency' => $mainCurrency,
            'mainCurrencyTotals' => $this->mainCurrencyTotals($rows, $mainCurrency),
            'alerts' => $this->alerts($rows, $today),
            'topByValue' => $this->topPerCurrency($rows, $currencies, 'value'),
            'topByRemaining' => $this->topPerCurrency($rows, $currencies, 'remaining'),
            'topByCount' => $this->topPerCurrency($rows, $currencies, 'contract_count'),
            'topByUncollected' => $this->topPerCurrency($rows, $currencies, 'uncollected'),
            'dataQuality' => $this->dataQuality($company, $rows, $mainCurrency),
            'details' => $this->details($rows, $currencies, $today),
            'startDate' => $periodStart,
            'endDate' => $today,
            'startDateFormatted' => Carbon::make($periodStart)->format('d/m/Y'),
            'endDateFormatted' => Carbon::make($today)->format('d/m/Y'),
            'isDefaultPeriod' => $this->isDefaultPeriod($periodStart, $today),
            'defaultPeriodYears' => self::DEFAULT_PERIOD_YEARS,
            'nearExpiryDays' => self::NEAR_EXPIRY_DAYS,
        ];
    }

    private function isDefaultPeriod(string $start, string $end): bool
    {
        $today = Carbon::today()->format('Y-m-d');

        return $end === $today
            && $start === Carbon::today()->subYearsNoOverflow(self::DEFAULT_PERIOD_YEARS)->format('Y-m-d');
    }

    /**
     * What actually happened between the two dates, as opposed to where
     * things stand at the end of them.
     *
     * @param  list<string>  $currencies
     */
    private function period(Company $company, array $currencies, string $from, string $to): array
    {
        if ($currencies === []) {
            return [];
        }

        $rows = $this->invoiceActivityQuery($company)
            ->whereDate('i.invoice_date', '>=', $from)
            ->whereDate('i.invoice_date', '<=', $to)
            ->selectRaw('UPPER(c.currency) as currency')
            ->selectRaw('COUNT(*) as invoice_count')
            ->selectRaw('COALESCE(SUM(CAST(i.invoice_amount AS DECIMAL(18,5))), 0) as invoiced')
            ->selectRaw('COALESCE(SUM(i.total_collected_amount), 0) as collected')
            ->selectRaw('COALESCE(SUM(i.total_withhold_amount), 0) as withheld')
            ->groupBy('currency')
            ->get()
            ->keyBy('currency');

        $period = [];
        foreach ($currencies as $currency) {
            $row = $rows->get($currency);

            $period[$currency] = [
                'invoice_count' => (int) ($row->invoice_count ?? 0),
                'invoiced' => (float) ($row->invoiced ?? 0),
                'collected' => (float) ($row->collected ?? 0),
                'withheld' => (float) ($row->withheld ?? 0),
            ];
        }

        return $period;
    }

    /**
     * The join every activity figure is measured over: this company's
     * Customer contracts, their own non-cancelled invoices, matched on
     * currency.
     */
    private function invoiceActivityQuery(Company $company)
    {
        return DB::table('customer_invoices as i')
            ->join('contracts as c', function ($join) {
                $join->on('c.code', '=', 'i.contract_code')
                    ->on('c.company_id', '=', 'i.company_id')
                    ->on(DB::raw('UPPER(i.currency)'), '=', DB::raw('UPPER(c.currency)'));
            })
            ->where('i.company_id', $company->id)
            ->where('c.model_type', 'Customer')
            ->where(function ($query) {
                $query->where('i.is_canceled', 0)->orWhereNull('i.is_canceled');
            });
    }

    /**
     * Accepts either a d/m/Y date-picker value (the format every other
     * dashboard filter posts) or a plain Y-m-d one. An unparseable
     * value falls back to today rather than throwing a 500 at someone
     * who hand-edited the query string.
     */
    private function normaliseDate(?string $date): ?string
    {
        $date = trim((string) $date);

        if ($date === '') {
            return null;
        }

        try {
            $normalised = HDate::formatDateFromDatePicker($date);

            return Carbon::make($normalised)?->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return Collection<int, array>
     */
    private function contractRows(Company $company, string $asOf): Collection
    {
        /*
         * Keyed by code AND currency: an invoice only counts towards a
         * contract when it is denominated in that contract's currency.
         * Cancelled invoices never count.
         *
         * invoice_amount / net_balance / net_invoice_amount are stored
         * as strings, hence the explicit CAST.
         */
        $invoiceAgg = DB::table('customer_invoices')
            ->selectRaw('contract_code')
            ->selectRaw('UPPER(currency) as invoice_currency')
            ->selectRaw('COUNT(*) as invoice_count')
            ->selectRaw('COALESCE(SUM(CAST(invoice_amount AS DECIMAL(18,5))), 0) as invoiced_amount')
            ->selectRaw('COALESCE(SUM(CAST(net_invoice_amount AS DECIMAL(18,5))), 0) as billed_amount')
            ->selectRaw('COALESCE(SUM(total_collected_amount), 0) as collected_amount')
            ->selectRaw('COALESCE(SUM(total_deductions), 0) as deductions_amount')
            ->selectRaw('COALESCE(SUM(total_withhold_amount), 0) as withheld_amount')
            ->selectRaw('COALESCE(SUM(CAST(net_balance AS DECIMAL(18,5))), 0) as uncollected_amount')
            ->selectRaw('COALESCE(SUM(CAST(invoice_amount_in_main_currency AS DECIMAL(18,5))), 0) as invoiced_main')
            ->selectRaw('COALESCE(SUM(total_collected_amount_in_main_currency), 0) as collected_main')
            ->selectRaw('COALESCE(SUM(CAST(net_balance_in_main_currency AS DECIMAL(18,5))), 0) as uncollected_main')
            ->where('company_id', $company->id)
            ->where(function ($query) {
                $query->where('is_canceled', 0)->orWhereNull('is_canceled');
            })
            ->whereNotNull('contract_code')
            ->where('contract_code', '!=', '')
            // Nothing invoiced after the as-of date has happened yet.
            ->whereDate('invoice_date', '<=', $asOf)
            ->groupBy('contract_code', 'invoice_currency');

        $contracts = Contract::query()
            ->with(['client:id,name'])
            ->leftJoinSub($invoiceAgg, 'invoice_agg', function ($join) {
                $join->on('invoice_agg.contract_code', '=', 'contracts.code')
                    ->on('invoice_agg.invoice_currency', '=', DB::raw('UPPER(contracts.currency)'));
            })
            ->where('contracts.company_id', $company->id)
            ->where('contracts.model_type', 'Customer')
            ->select([
                'contracts.*',
                'invoice_agg.invoice_count',
                'invoice_agg.invoiced_amount',
                'invoice_agg.billed_amount',
                'invoice_agg.collected_amount',
                'invoice_agg.deductions_amount',
                'invoice_agg.withheld_amount',
                'invoice_agg.uncollected_amount',
                'invoice_agg.invoiced_main',
                'invoice_agg.collected_main',
                'invoice_agg.uncollected_main',
            ])
            ->get();

        return $contracts->map(fn (Contract $contract) => $this->mapContractRow($contract, $asOf))->values();
    }

    private function mapContractRow(Contract $contract, string $today): array
    {
        $amount = (float) $contract->getAmount();
        $invoiced = (float) ($contract->invoiced_amount ?? 0);
        $currency = strtoupper((string) ($contract->getCurrency() ?: ''));
        $endDate = $contract->getEndDate();

        $isOpen = in_array($contract->status, [Contract::RUNNING, Contract::RUNNING_AND_AGAINST], true);
        $isExpired = $isOpen && $endDate && $endDate < $today;

        return [
            'id' => $contract->id,
            'partner_id' => $contract->partner_id,
            'customer_name' => $contract->client?->getName() ?? __('N/A'),
            'code' => $contract->getCode(),
            'name' => $contract->getName(),
            'currency' => $currency,
            'amount' => $amount,

            // billing progress — ex-VAT, comparable with the contract value
            'invoice_count' => (int) ($contract->invoice_count ?? 0),
            'invoiced' => $invoiced,
            'remaining' => $amount - $invoiced,

            // receivables — VAT-inclusive, reconcile among themselves
            'billed' => (float) ($contract->billed_amount ?? 0),
            'collected' => (float) ($contract->collected_amount ?? 0),
            'withheld' => (float) ($contract->withheld_amount ?? 0),
            'deductions' => (float) ($contract->deductions_amount ?? 0),
            'uncollected' => (float) ($contract->uncollected_amount ?? 0),

            // the company's own currency, using the rate each invoice recorded
            'invoiced_main' => (float) ($contract->invoiced_main ?? 0),
            'collected_main' => (float) ($contract->collected_main ?? 0),
            'uncollected_main' => (float) ($contract->uncollected_main ?? 0),

            'status' => $contract->status,
            'is_expired' => $isExpired,
            'status_label' => $isExpired ? __('Expired') : $this->statusLabel($contract->status),
            'start_date' => $contract->start_date,
            'end_date' => $endDate,
            'end_date_formatted' => $contract->getEndDateFormatted(),
            'exchange_rate' => (float) ($contract->exchange_rate ?? 0),
        ];
    }

    /**
     * @param  Collection<int, array>  $rows
     */
    private function counts(Collection $rows): array
    {
        return [
            'total' => $rows->count(),
            // "Running" here means open AND still inside its end date —
            // the status column alone says running for contracts that
            // ended years ago, which is what Expired separates out.
            'running' => $rows->where('status', Contract::RUNNING)->where('is_expired', false)->count(),
            'running_and_against' => $rows->where('status', Contract::RUNNING_AND_AGAINST)->where('is_expired', false)->count(),
            'expired' => $rows->where('is_expired', true)->count(),
            'finished' => $rows->where('status', Contract::FINISHED)->count(),
            'not_invoiced' => $rows->where('invoice_count', 0)->count(),
            'over_billed' => $rows->filter(fn (array $row) => $row['remaining'] < 0)->count(),
        ];
    }

    /**
     * @param  Collection<int, array>  $rows
     * @param  list<string>  $currencies
     */
    private function byCurrency(Collection $rows, array $currencies): array
    {
        $byCurrency = [];

        foreach ($currencies as $currency) {
            $currencyRows = $rows->where('currency', $currency);

            $value = (float) $currencyRows->sum('amount');
            $invoiced = (float) $currencyRows->sum('invoiced');
            $billed = (float) $currencyRows->sum('billed');
            $collected = (float) $currencyRows->sum('collected');

            $byCurrency[$currency] = [
                'contract_count' => $currencyRows->count(),
                'value' => $value,
                'invoiced' => $invoiced,
                'remaining' => (float) $currencyRows->sum('remaining'),
                'utilization' => $value > 0 ? round(($invoiced / $value) * 100, 2) : 0.0,
                'billed' => $billed,
                'collected' => $collected,
                'withheld' => (float) $currencyRows->sum('withheld'),
                'deductions' => (float) $currencyRows->sum('deductions'),
                'uncollected' => (float) $currencyRows->sum('uncollected'),
                'collection_rate' => $billed > 0 ? round(($collected / $billed) * 100, 2) : 0.0,
                /*
                 * ⚠️ REAL BUG FIXED HERE: withholding tax was missing
                 * from this identity, so every invoice the customer had
                 * withheld tax on looked like it did not add up — 10 of
                 * 197 invoices, and a 38,737.50 "unexplained" gap that
                 * was withholding tax to the last piastre. The customer
                 * pays it to the tax authority rather than to us: it is
                 * not collected, but it does clear the receivable.
                 *
                 *   billed − collected − withheld − deductions = uncollected
                 *
                 * A gap that survives THIS identity really is an invoice
                 * whose own columns disagree, and is published rather
                 * than absorbed into a total that does not balance.
                 */
                'reconciliation_gap' => round(
                    $billed
                        - $collected
                        - (float) $currencyRows->sum('withheld')
                        - (float) $currencyRows->sum('deductions')
                        - (float) $currencyRows->sum('uncollected'),
                    2
                ),
            ];
        }

        return $byCurrency;
    }

    /**
     * One set of figures across every currency, in the company's own
     * currency.
     *
     * The invoice side is taken from the *_in_main_currency columns the
     * invoices already carry, so it uses the rate that was actually
     * recorded when each invoice was raised.
     *
     * Contract VALUE is different: it can only be converted with
     * contracts.exchange_rate, and that column is not trustworthy in
     * practice (the data has EGP contracts stamped with a rate of 50,
     * and USD contracts stamped with 1). Rather than publish a
     * confident wrong number, contracts whose rate cannot be believed
     * are excluded from the converted value and counted, so the page
     * can say so.
     *
     * @param  Collection<int, array>  $rows
     */
    private function mainCurrencyTotals(Collection $rows, string $mainCurrency): array
    {
        $value = 0.0;
        $unconvertible = 0;

        foreach ($rows as $row) {
            $rate = $this->trustworthyRate($row, $mainCurrency);

            if ($rate === null) {
                $unconvertible++;

                continue;
            }

            $value += $row['amount'] * $rate;
        }

        return [
            'value' => $value,
            'value_unconvertible_count' => $unconvertible,
            'invoiced' => (float) $rows->sum('invoiced_main'),
            'collected' => (float) $rows->sum('collected_main'),
            'uncollected' => (float) $rows->sum('uncollected_main'),
        ];
    }

    /**
     * A contract in the company's own currency converts at 1. Anything
     * else needs a rate that is present, positive, and not the default
     * 1 that means "nobody set it".
     */
    private function trustworthyRate(array $row, string $mainCurrency): ?float
    {
        if ($row['currency'] === $mainCurrency) {
            return 1.0;
        }

        $rate = $row['exchange_rate'];

        if ($rate <= 0 || abs($rate - 1.0) < 0.0000001) {
            return null;
        }

        return $rate;
    }

    /**
     * @param  Collection<int, array>  $rows
     */
    private function alerts(Collection $rows, string $asOf): array
    {
        return [
            'past_end_date_count' => $rows->where('is_expired', true)->count(),
            'ending_soon_count' => $this->endingSoon($rows, $asOf)->count(),
            'not_invoiced_count' => $rows->where('invoice_count', 0)->count(),
            'over_billed_count' => $rows->filter(fn (array $row) => $row['remaining'] < 0)->count(),
        ];
    }

    /**
     * @param  Collection<int, array>  $rows
     * @return Collection<int, array>
     */
    private function endingSoon(Collection $rows, string $asOf): Collection
    {
        $today = $asOf;
        $cutoff = Carbon::make($asOf)->addDays(self::NEAR_EXPIRY_DAYS)->format('Y-m-d');

        return $rows->filter(fn (array $row) => in_array($row['status'], [Contract::RUNNING, Contract::RUNNING_AND_AGAINST], true)
            && $row['end_date']
            && $row['end_date'] >= $today
            && $row['end_date'] <= $cutoff)->values();
    }

    /**
     * Things the numbers cannot be trusted about, surfaced instead of
     * hidden. Every one of these is a row the user can go and fix.
     *
     * @param  Collection<int, array>  $rows
     */
    private function dataQuality(Company $company, Collection $rows, string $mainCurrency): array
    {
        /*
         * Invoices posted against a contract in a DIFFERENT currency.
         * They are excluded from every total above — this is where they
         * are accounted for.
         */
        $mismatched = DB::table('customer_invoices as i')
            ->join('contracts as c', function ($join) {
                $join->on('c.code', '=', 'i.contract_code')
                    ->on('c.company_id', '=', 'i.company_id');
            })
            ->where('i.company_id', $company->id)
            ->where('c.model_type', 'Customer')
            ->where(function ($query) {
                $query->where('i.is_canceled', 0)->orWhereNull('i.is_canceled');
            })
            ->whereRaw('UPPER(COALESCE(i.currency, "")) <> UPPER(COALESCE(c.currency, ""))')
            ->select([
                'i.id',
                'i.invoice_number',
                'i.invoice_date',
                'i.currency as invoice_currency',
                'i.invoice_amount',
                'c.code as contract_code',
                'c.name as contract_name',
                'c.currency as contract_currency',
            ])
            ->orderBy('c.code')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $rateIssues = $rows
            ->filter(fn (array $row) => $this->trustworthyRate($row, $mainCurrency) === null)
            ->values()
            ->all();

        /*
         * Invoices whose own columns do not add up:
         * net_invoice_amount − collected − withheld − deductions ≠ net_balance.
         * Their gap is what makes a currency's Collections row fail to
         * reconcile, so it is named here instead of left as a mystery.
         */
        $unbalanced = DB::table('customer_invoices as i')
            ->join('contracts as c', function ($join) {
                $join->on('c.code', '=', 'i.contract_code')
                    ->on('c.company_id', '=', 'i.company_id')
                    ->on(DB::raw('UPPER(i.currency)'), '=', DB::raw('UPPER(c.currency)'));
            })
            ->where('i.company_id', $company->id)
            ->where('c.model_type', 'Customer')
            ->where(function ($query) {
                $query->where('i.is_canceled', 0)->orWhereNull('i.is_canceled');
            })
            ->whereRaw('ABS(CAST(i.net_invoice_amount AS DECIMAL(18,5)) - i.total_collected_amount - i.total_withhold_amount - i.total_deductions - CAST(i.net_balance AS DECIMAL(18,5))) > 0.01')
            ->select([
                'i.id',
                'i.invoice_number',
                'i.currency',
                'i.net_invoice_amount',
                'i.total_collected_amount',
                'i.total_withhold_amount',
                'i.total_deductions',
                'i.net_balance',
                'c.code as contract_code',
            ])
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $withoutCode = $rows
            ->filter(fn (array $row) => trim((string) $row['code']) === '')
            ->values()
            ->all();

        return [
            'mismatched_currency_invoices' => $mismatched,
            'mismatched_currency_count' => count($mismatched),
            'exchange_rate_issues' => $rateIssues,
            'exchange_rate_issue_count' => count($rateIssues),
            'contracts_without_code' => $withoutCode,
            'contracts_without_code_count' => count($withoutCode),
            'unbalanced_invoices' => $unbalanced,
            'unbalanced_invoice_count' => count($unbalanced),
        ];
    }

    /**
     * @param  Collection<int, array>  $rows
     * @param  list<string>  $currencies
     */
    private function topPerCurrency(Collection $rows, array $currencies, string $sortKey): array
    {
        $top = [];

        foreach ($currencies as $currency) {
            $top[$currency] = $this->topCustomers($rows->where('currency', $currency), $sortKey, self::TOP_CUSTOMERS);
        }

        return $top;
    }

    /**
     * @param  Collection<int, array>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function topCustomers(Collection $rows, string $sortKey, int $limit): array
    {
        return $rows
            ->groupBy('partner_id')
            ->map(function (Collection $group) {
                $first = $group->first();

                return [
                    'partner_id' => $first['partner_id'],
                    'customer_name' => $first['customer_name'],
                    'contract_count' => $group->count(),
                    'value' => (float) $group->sum('amount'),
                    'invoiced' => (float) $group->sum('invoiced'),
                    'remaining' => (float) $group->sum('remaining'),
                    'collected' => (float) $group->sum('collected'),
                    'uncollected' => (float) $group->sum('uncollected'),
                ];
            })
            /*
             * Value breaks ties on contract_count so "who do I have the
             * most contracts with" does not come back in an arbitrary
             * order when several customers have the same number.
             */
            ->sortByDesc(fn (array $customer) => [$customer[$sortKey], $customer['value']])
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array>  $rows
     * @param  list<string>  $currencies
     */
    private function details(Collection $rows, array $currencies, string $asOf): array
    {
        $details = [
            'all' => $rows->values()->all(),
            'running' => $rows->where('status', Contract::RUNNING)->where('is_expired', false)->values()->all(),
            'running_and_against' => $rows->where('status', Contract::RUNNING_AND_AGAINST)->where('is_expired', false)->values()->all(),
            'expired' => $rows->where('is_expired', true)->values()->all(),
            'finished' => $rows->where('status', Contract::FINISHED)->values()->all(),
            'past_end_date' => $rows->where('is_expired', true)->values()->all(),
            'ending_soon' => $this->endingSoon($rows, $asOf)->all(),
            'not_invoiced' => $rows->where('invoice_count', 0)->values()->all(),
            'over_billed' => $rows->filter(fn (array $row) => $row['remaining'] < 0)->values()->all(),
            'by_currency' => [],
        ];

        foreach ($currencies as $currency) {
            $details['by_currency'][$currency] = $rows->where('currency', $currency)->values()->all();
        }

        return $details;
    }

    /**
     * Receivables split by how overdue they are, on the as-of date.
     *
     * The amount aged is net_balance — what is still outstanding on the
     * invoice — bucketed by how far invoice_due_date has passed. An
     * invoice not yet due sits in its own bucket rather than being
     * counted as current-and-fine alongside something 89 days late.
     *
     * Only invoices matching their contract's currency are aged, for
     * the same reason they are the only ones counted anywhere else.
     *
     * @param  list<string>  $currencies
     */
    private function aging(Company $company, array $currencies, string $asOf): array
    {
        if ($currencies === []) {
            return [];
        }

        /*
         * DATEDIFF(asOf, due) is days PAST due: positive means overdue,
         * zero or negative means not due yet.
         */
        $rows = DB::table('customer_invoices as i')
            ->join('contracts as c', function ($join) {
                $join->on('c.code', '=', 'i.contract_code')
                    ->on('c.company_id', '=', 'i.company_id')
                    ->on(DB::raw('UPPER(i.currency)'), '=', DB::raw('UPPER(c.currency)'));
            })
            ->where('i.company_id', $company->id)
            ->where('c.model_type', 'Customer')
            ->where(function ($query) {
                $query->where('i.is_canceled', 0)->orWhereNull('i.is_canceled');
            })
            ->whereDate('i.invoice_date', '<=', $asOf)
            ->selectRaw('UPPER(c.currency) as currency')
            ->selectRaw('CASE
                WHEN i.invoice_due_date IS NULL OR DATEDIFF(?, i.invoice_due_date) <= 0 THEN ?
                WHEN DATEDIFF(?, i.invoice_due_date) <= 30 THEN ?
                WHEN DATEDIFF(?, i.invoice_due_date) <= 60 THEN ?
                WHEN DATEDIFF(?, i.invoice_due_date) <= 90 THEN ?
                ELSE ? END as bucket', [$asOf, 'not_due', $asOf, 'd1_30', $asOf, 'd31_60', $asOf, 'd61_90', 'd90_plus'])
            ->selectRaw('COUNT(*) as invoice_count')
            ->selectRaw('COALESCE(SUM(CAST(i.net_balance AS DECIMAL(18,5))), 0) as amount')
            ->groupBy('currency', 'bucket')
            ->get();

        $aging = [];
        foreach ($currencies as $currency) {
            $aging[$currency] = [];
            foreach (array_keys(self::AGING_BUCKETS) as $bucket) {
                $aging[$currency][$bucket] = ['amount' => 0.0, 'invoice_count' => 0];
            }
            $aging[$currency]['total'] = ['amount' => 0.0, 'invoice_count' => 0];
            $aging[$currency]['overdue_total'] = ['amount' => 0.0, 'invoice_count' => 0];
        }

        foreach ($rows as $row) {
            $currency = $row->currency;

            if (! isset($aging[$currency])) {
                continue;
            }

            $amount = (float) $row->amount;
            $count = (int) $row->invoice_count;

            $aging[$currency][$row->bucket] = ['amount' => $amount, 'invoice_count' => $count];
            $aging[$currency]['total']['amount'] += $amount;
            $aging[$currency]['total']['invoice_count'] += $count;

            if ($row->bucket !== 'not_due') {
                $aging[$currency]['overdue_total']['amount'] += $amount;
                $aging[$currency]['overdue_total']['invoice_count'] += $count;
            }
        }

        return $aging;
    }

    /**
     * @return array<string, string>
     */
    private function agingBucketLabels(): array
    {
        return [
            'not_due' => __('Not Due Yet'),
            'd1_30' => __('1–30 Days'),
            'd31_60' => __('31–60 Days'),
            'd61_90' => __('61–90 Days'),
            'd90_plus' => __('Over 90 Days'),
        ];
    }

    /**
     * Invoicing and collection month by month across the chosen
     * period, so the page shows the rate of work rather than only the
     * standing balance.
     *
     * Every month in the window is present even when nothing happened
     * in it — a gap in a trend line is not the same as a zero, and the
     * chart should show the difference.
     *
     * @param  list<string>  $currencies
     */
    private function trend(Company $company, array $currencies, string $from, string $to): array
    {
        if ($currencies === []) {
            return [];
        }

        $end = Carbon::make($to)->endOfMonth();
        $start = Carbon::make($from)->startOfMonth();

        $rows = $this->invoiceActivityQuery($company)
            ->whereDate('i.invoice_date', '>=', $from)
            ->whereDate('i.invoice_date', '<=', $to)
            ->selectRaw('UPPER(c.currency) as currency')
            ->selectRaw("DATE_FORMAT(i.invoice_date, '%Y-%m') as month")
            ->selectRaw('COUNT(*) as invoice_count')
            ->selectRaw('COALESCE(SUM(CAST(i.invoice_amount AS DECIMAL(18,5))), 0) as invoiced')
            ->selectRaw('COALESCE(SUM(i.total_collected_amount), 0) as collected')
            ->groupBy('currency', 'month')
            ->get()
            ->groupBy('currency');

        $months = [];
        for ($cursor = $start->copy(); $cursor->lessThanOrEqualTo($end); $cursor->addMonthNoOverflow()) {
            $months[] = $cursor->format('Y-m');
        }

        $trend = [];
        foreach ($currencies as $currency) {
            $byMonth = ($rows[$currency] ?? collect())->keyBy('month');

            $trend[$currency] = array_map(function (string $month) use ($byMonth) {
                $row = $byMonth->get($month);

                return [
                    'month' => $month,
                    'label' => Carbon::createFromFormat('Y-m-d', $month.'-01')->format('M Y'),
                    'invoice_count' => (int) ($row->invoice_count ?? 0),
                    'invoiced' => (float) ($row->invoiced ?? 0),
                    'collected' => (float) ($row->collected ?? 0),
                ];
            }, $months);
        }

        return $trend;
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            Contract::RUNNING => __('Running'),
            Contract::RUNNING_AND_AGAINST => __('Running & Against'),
            Contract::FINISHED => __('Finished'),
            default => $status,
        };
    }
}
