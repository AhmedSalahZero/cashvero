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
 * Why this exists: several Statement reports (Bank Statement, Safe
 * Statement, and — as they're migrated next — Factoring/LG-LC/Cash
 * Expense/Partners/Withdrawal Statements) build their result set as a
 * plain Collection rather than an Eloquent query, because the real
 * table/columns queried differ per account type, branch, or currency
 * (decided at runtime). Laravel's Model::paginate() doesn't apply to
 * an already-fetched Collection, so this does the same job by hand —
 * exactly what BankStatementController's original private
 * paginate()/paginator() pair already did. Extracted here instead of
 * copy-pasted per controller, since every Statement page needs the
 * identical behavior (§3.8 of the roadmap: shared bug class, fix
 * once).
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
