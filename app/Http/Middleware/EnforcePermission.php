<?php

namespace App\Http\Middleware;

use App\Support\Permissions\PermissionResolver;
use App\Support\Permissions\RoutePermissionMap;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * EnforcePermission
 * ==================================================================
 * The backend's final word on authorization.
 *
 * Runs on every web request, looks the current route name up in
 * RoutePermissionMap, and aborts 403 before the controller action is
 * ever reached. This is what makes hiding a button in Vue irrelevant
 * to security: forging the request, replaying it from Postman or
 * editing the JS all hit this check.
 *
 * Deliberately NOT doing the work in controllers — that would mean 94
 * files to edit and 94 places for a future action to be forgotten.
 *
 * Unauthenticated requests pass straight through; `auth` rejects them
 * with a 401/redirect of its own, and duplicating that here would turn
 * every guest into a confusing 403.
 */
class EnforcePermission
{
    /**
     * Route names already logged this request, so a redirect chain
     * doesn't emit the same warning several times.
     *
     * @var array<string, true>
     */
    private static array $logged = [];

    public function handle(Request $request, Closure $next)
    {
        $route = $request->route();
        $routeName = $route?->getName();

        // Unnamed routes can't be mapped by name — nothing to enforce.
        if (! $routeName) {
            return $next($request);
        }

        // Let `auth` own the unauthenticated case.
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if (RoutePermissionMap::isPublic($routeName)) {
            return $next($request);
        }

        $required = RoutePermissionMap::for($routeName);

        if ($required === null) {
            return $this->handleUnmapped($request, $next, $routeName);
        }

        if (! config('permissions.enforce', true)) {
            if (! PermissionResolver::allowsAny($user, $required)) {
                $this->log("permission.would_deny route={$routeName} user={$user->id} needs=".implode('|', $required));
            }

            return $next($request);
        }

        // An array means "any of" — one matching key is enough.
        if (PermissionResolver::allowsAny($user, $required)) {
            return $next($request);
        }

        abort(403, __('You do not have permission to perform this action.'));
    }

    private function handleUnmapped(Request $request, Closure $next, string $routeName)
    {
        $this->log("permission.unmapped_route route={$routeName} uri={$request->path()}");

        if (config('permissions.unmapped', 'allow') === 'deny' && config('permissions.enforce', true)) {
            abort(403, __('You do not have permission to perform this action.'));
        }

        return $next($request);
    }

    private function log(string $message): void
    {
        $level = config('permissions.log_unmapped', 'warning');

        if (! $level || isset(self::$logged[$message])) {
            return;
        }

        self::$logged[$message] = true;
        Log::log($level, $message);
    }
}
