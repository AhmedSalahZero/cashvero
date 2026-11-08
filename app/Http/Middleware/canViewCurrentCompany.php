<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;

class canViewCurrentCompany
{
    /**
     * A user may only work inside companies they belong to.
     *
     * The company number is read from TWO places:
     *   1. the second part of the address (/en/148/...), as before; and
     *   2. the {company} value of the matched route, because a few screens carry the company at
     *      the END of the address (/en/roles/all/148, /en/user-permissions/edit/5/148) where the
     *      first check never looked (security test SEC-30).
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);   // guests are stopped by the "auth" middleware, not here
        }

        $requested = [];

        $segment = $request->segment(2);

        if (is_numeric($segment)) {
            $requested[] = (int) $segment;
        }

        $routeCompany = $request->route()?->parameter('company');

        if ($routeCompany instanceof Company) {
            $requested[] = (int) $routeCompany->id;
        } elseif (is_numeric($routeCompany)) {
            $requested[] = (int) $routeCompany;
        }

        if ($requested === []) {
            return $next($request);
        }

        $allowed = $user->companies->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach (array_unique($requested) as $companyId) {
            if (! in_array($companyId, $allowed, true)) {
                abort(403);
            }
        }

        return $next($request);
    }
}
