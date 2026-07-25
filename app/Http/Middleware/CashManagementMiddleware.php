<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

/**
 * ⚠️ REAL BUG FIXED HERE (2026-07-24 audit, Stage 5):
 * This middleware was previously a complete no-op (`return $next($request)`),
 * while routes declared `->middleware('isCashManagement')` implied real
 * protection. It now requires an authenticated user who either is a
 * Super Admin or has access to the CashVero system on at least one of
 * their companies — matching `User::hasAccessToSystems([CASH_VERO])`.
 *
 * Soft-deleted users (deleted_at set) are rejected here even if a
 * stale session still resolves the account.
 */
class CashManagementMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user || $user->deleted_at) {
            abort(403);
        }

        if (! $user->hasAccessToSystems([CASH_VERO])) {
            abort(403);
        }

        return $next($request);
    }
}
