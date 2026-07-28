<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

/**
 * FiltersFactoringTransactions
 * ------------------------------------------------------------------
 * The search/date filter shared by the With Recourse and Without
 * Recourse listing pages, expressed as SQL instead of as a Collection
 * filter.
 *
 * Both pages used to load every factoring transaction the company had
 * ever recorded — with five eager-loaded relations and no date window
 * — and then filter and sort that collection in PHP. The filter itself
 * is unchanged in meaning; it just runs in the database now, which is
 * what makes paginating these pages possible at all. Searching by
 * customer or factoring company name still matches on the related
 * record's name, via whereHas rather than stristr() on a hydrated
 * model.
 */
trait FiltersFactoringTransactions
{
    /**
     * Columns a user is allowed to search on. The field name arrives from
     * the query string and is interpolated into a WHERE clause, so it is
     * matched against this list rather than trusted.
     *
     * @return array<string,string> column => translated label
     */
    protected function factoringSearchFields(): array
    {
        return [
            'factoring_date' => __('Factoring Date'),
            'customer_id' => __('Customer'),
            'factoring_company_id' => __('Factoring Company'),
            'invoice_currency' => __('Invoice Currency'),
            'received_amount' => __('Received Amount'),
        ];
    }

    /**
     * Relation-backed search fields: the request sends an id-shaped field
     * name, but the user is typing a name, so the match happens on the
     * related record.
     *
     * @return array<string,string> field name => relation name
     */
    private function factoringRelationSearchFields(): array
    {
        return [
            'customer_id' => 'customer',
            'factoring_company_id' => 'factoringCompany',
        ];
    }

    /**
     * Callers pass `$company->factoringTransactions()`, which is a HasMany
     * relation rather than an Eloquent Builder — the relation forwards
     * query methods and hands itself back, so it never becomes a Builder.
     * Hence the union type: hinting Builder alone throws a TypeError the
     * moment either listing page is opened.
     *
     * @param  Builder|Relation  $query
     * @return Builder|Relation
     */
    protected function applyFactoringFilter(Request $request, $query)
    {
        $searchField = (string) $request->get('field');
        $value = (string) $request->query('value', '');
        $from = $request->get('from');
        $to = $request->get('to');

        $isKnownField = array_key_exists($searchField, $this->factoringSearchFields());
        // Date filters apply to the factoring date only when that is what
        // is being searched; otherwise they fall back to created_at, which
        // is the behaviour the collection filter had.
        $dateField = $searchField === 'factoring_date' ? 'factoring_date' : 'created_at';
        $relations = $this->factoringRelationSearchFields();

        return $query
            ->when($isKnownField && $request->has('value'), function ($q) use ($searchField, $value, $relations) {
                if (isset($relations[$searchField])) {
                    $q->whereHas($relations[$searchField], fn (Builder $related) => $related->where('name', 'like', '%'.$value.'%'));

                    return;
                }

                $q->where($searchField, 'like', '%'.$value.'%');
            })
            ->when($from, fn ($q) => $q->where($dateField, '>=', $from))
            ->when($to, fn ($q) => $q->where($dateField, '<=', $to))
            ->orderByDesc('id');
    }
}
