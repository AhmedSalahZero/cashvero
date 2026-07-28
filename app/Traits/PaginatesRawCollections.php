<?php

namespace App\Traits;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;

/**
 * PaginatesRawCollections
 * ------------------------------------------------------------------
 * Turns an already-fetched, in-memory Collection (e.g. the result of
 * a DB::table()->...->get() call) into a real LengthAwarePaginator,
 * with the current filters re-attached to every page link.
 *
 * ⚠️ Only three reports still need this. Everything else moved to
 * App\Traits\PaginatesStatementQueries, which pages in SQL so a 50-row
 * page no longer costs a full table read. Reach for that one first;
 * this trait is the fallback for row sets that genuinely cannot be
 * expressed as a single query:
 *
 *   - Factoring Statement — each row's end balance is a running total
 *     of every earlier row, computed in PHP. Page 2 has no way to know
 *     page 1's closing balance.
 *   - Factoring Charges Statement — one transaction expands into up to
 *     three charge rows in PHP, then those are sorted and running-
 *     totalled. There is no table to LIMIT.
 *   - Partners Statement — one query per selected partner, paginated
 *     at partner-group level so a page never splits a partner in half.
 *
 * Fixing those three properly means SQL window functions (or storing
 * the balances), which is a bigger change than pagination.
 *
 * IMPORTANT: this is presentation-layer pagination only. It does not
 * change what rows are fetched or how any balance/total is
 * calculated — callers should compute KPI totals (beginning balance,
 * total debit/credit, ending balance) from the FULL collection
 * before calling paginateCollection(), never from just the current
 * page's slice.
 */
trait PaginatesRawCollections
{
    /**
     * @param  Collection  $items    the FULL, already-fetched result set (not yet sliced to a page)
     * @param  int  $perPage
     * @param  Request|null  $request  when given, every filter param except `page` is re-attached
     *                                  to the generated page links (so Next/Prev/page-N never lose
     *                                  the current date range, account, currency, etc.)
     */
    public function paginateCollection(Collection $items, int $perPage, ?Request $request = null): LengthAwarePaginator
    {
        $page = Paginator::resolveCurrentPage('page');
        $total = $items->count();

        $paginator = $this->makePaginator(
            $items->forPage($page, $perPage)->values(),
            $total,
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'pageName' => 'page']
        );

        if ($request) {
            $paginator->appends($request->except('page'));
        }

        return $paginator;
    }

    private function makePaginator($items, $total, $perPage, $currentPage, $options): LengthAwarePaginator
    {
        return Container::getInstance()->makeWith(LengthAwarePaginator::class, compact(
            'items',
            'total',
            'perPage',
            'currentPage',
            'options'
        ));
    }
}
