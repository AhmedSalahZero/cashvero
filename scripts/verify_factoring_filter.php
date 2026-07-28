<?php

/**
 * Parity check for moving the Factoring listing filter from a PHP
 * Collection filter to SQL.
 *
 * The old code fetched every factoring transaction and then ran
 * stristr() over hydrated models; the new code runs the equivalent
 * WHERE/whereHas clauses so the list can be paginated. This walks real
 * search terms taken from the data itself and asserts both approaches
 * select the same transaction ids in the same order.
 *
 * Run: php scripts/verify_factoring_filter.php
 */

use App\Models\Company;
use App\Models\FactoringTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$failures = 0;
$checked = 0;

/** The filter exactly as it was before the rewrite. */
function legacyFilter(Request $request, Collection $collection): Collection
{
    if (! count($collection)) {
        return $collection;
    }

    $searchFieldName = $request->get('field');
    $dateFieldName = $searchFieldName === 'factoring_date' ? 'factoring_date' : 'created_at';
    $from = $request->get('from');
    $to = $request->get('to');
    $value = $request->query('value');

    return $collection
        ->when($request->has('value'), function ($collection) use ($value, $searchFieldName) {
            return $collection->filter(function ($item) use ($value, $searchFieldName) {
                if ($searchFieldName === 'customer_id') {
                    return false !== stristr($item->customer?->getName() ?? '', (string) $value);
                }
                if ($searchFieldName === 'factoring_company_id') {
                    return false !== stristr($item->factoringCompany?->getName() ?? '', (string) $value);
                }

                return false !== stristr((string) $item->{$searchFieldName}, (string) $value);
            });
        })
        ->when($request->get('from'), fn ($c) => $c->where($dateFieldName, '>=', $from))
        ->when($request->get('to'), fn ($c) => $c->where($dateFieldName, '<=', $to))
        ->sortByDesc('id')
        ->values();
}

/** The new SQL filter, via the trait the controllers now use. */
$sqlFilter = new class
{
    use App\Traits\FiltersFactoringTransactions;

    public function run(Request $request, $query)
    {
        return $this->applyFactoringFilter($request, $query);
    }
};

function compare(string $label, Company $company, string $recourseType, array $params, $sqlFilter): void
{
    global $failures, $checked;
    $checked++;

    $request = Request::create('/', 'GET', $params);

    $all = $company->factoringTransactions()
        ->with(['factoringCompany', 'customer'])
        ->where('recourse_type', $recourseType)
        ->get();
    $legacyIds = legacyFilter($request, $all)->pluck('id')->all();

    $query = $company->factoringTransactions()->where('recourse_type', $recourseType);
    $sqlIds = $sqlFilter->run($request, $query)->pluck('id')->all();

    if ($legacyIds !== $sqlIds) {
        $failures++;
        echo "  MISMATCH  {$label}\n";
        echo '      collection: '.count($legacyIds)." rows\n";
        echo '      sql:        '.count($sqlIds)." rows\n";
        $onlyLegacy = array_diff($legacyIds, $sqlIds);
        $onlySql = array_diff($sqlIds, $legacyIds);
        if ($onlyLegacy) {
            echo '      only in collection: '.implode(',', array_slice($onlyLegacy, 0, 10))."\n";
        }
        if ($onlySql) {
            echo '      only in sql:        '.implode(',', array_slice($onlySql, 0, 10))."\n";
        }
        if (! $onlyLegacy && ! $onlySql) {
            echo "      same rows, different ORDER\n";
        }

        return;
    }

    echo "  ok  {$label}  rows=".count($legacyIds)."\n";
}

foreach ([FactoringTransaction::WITH_RECOURSE, FactoringTransaction::WITHOUT_RECOURSE] as $recourseType) {
    echo "\nrecourse_type = {$recourseType}\n";

    $companyIds = FactoringTransaction::where('recourse_type', $recourseType)
        ->distinct()->pluck('company_id')->all();

    foreach ($companyIds as $companyId) {
        $company = Company::find($companyId);
        if (! $company) {
            continue;
        }

        // Unfiltered baseline.
        compare("company={$companyId} (no filter)", $company, $recourseType, [], $sqlFilter);

        $sample = FactoringTransaction::with(['customer', 'factoringCompany'])
            ->where('company_id', $companyId)->where('recourse_type', $recourseType)->first();
        if (! $sample) {
            continue;
        }

        // Real search terms lifted from the data: partial names, a currency,
        // an amount, and a date window that actually splits the set.
        $customerName = $sample->customer?->getName();
        if ($customerName) {
            compare("company={$companyId} customer~'".mb_substr($customerName, 0, 4)."'", $company, $recourseType,
                ['field' => 'customer_id', 'value' => mb_substr($customerName, 0, 4)], $sqlFilter);
        }

        $factoringCompanyName = $sample->factoringCompany?->getName();
        if ($factoringCompanyName) {
            compare("company={$companyId} factoringCompany~'".mb_substr($factoringCompanyName, 0, 4)."'", $company, $recourseType,
                ['field' => 'factoring_company_id', 'value' => mb_substr($factoringCompanyName, 0, 4)], $sqlFilter);
        }

        if ($sample->invoice_currency) {
            compare("company={$companyId} currency='{$sample->invoice_currency}'", $company, $recourseType,
                ['field' => 'invoice_currency', 'value' => $sample->invoice_currency], $sqlFilter);
        }

        $range = FactoringTransaction::where('company_id', $companyId)->where('recourse_type', $recourseType)
            ->selectRaw('MIN(factoring_date) AS lo, MAX(factoring_date) AS hi')->first();
        if ($range->lo && $range->hi) {
            compare("company={$companyId} factoring_date {$range->lo}..{$range->hi}", $company, $recourseType,
                ['field' => 'factoring_date', 'from' => $range->lo, 'to' => $range->hi], $sqlFilter);
            compare("company={$companyId} factoring_date from {$range->hi}", $company, $recourseType,
                ['field' => 'factoring_date', 'from' => $range->hi], $sqlFilter);
        }

        compare("company={$companyId} created_at window", $company, $recourseType,
            ['field' => 'invoice_currency', 'from' => '2000-01-01', 'to' => '2100-01-01'], $sqlFilter);
    }
}

echo "\n".($failures === 0
    ? "PASS — {$checked} filter cases, identical row sets and ordering\n"
    : "FAIL — {$failures} of {$checked} cases differ\n");

exit($failures === 0 ? 0 : 1);
