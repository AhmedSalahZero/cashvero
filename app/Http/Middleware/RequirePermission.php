<?php

namespace App\Http\Middleware;

use App\Support\Permissions\PermissionResolver;
use Closure;
use Illuminate\Http\Request;

/**
 * RequirePermission
 * ==================================================================
 * Explicit per-route guard: `->middleware('permission:role.update')`.
 *
 * EnforcePermission already covers every route through the central
 * map, so this is for the cases where being explicit at the route
 * definition reads better — new admin routes, or a route whose
 * requirement should be obvious to anyone reading routes/web.php.
 *
 * Several keys mean "any of":
 *   ->middleware('permission:customer_contract.view,supplier_contract.view')
 */
class RequirePermission
{
    public function handle(Request $request, Closure $next, string ...$keys)
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if (! PermissionResolver::allowsAny($user, $keys)) {
            abort(403, __('You do not have permission to perform this action.'));
        }

        return $next($request);
    }
}
