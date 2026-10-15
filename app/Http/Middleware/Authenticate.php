<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Support\Facades\Auth;

class Authenticate extends Middleware
{
    public function handle($request, Closure $next, ...$guards)
    {
        $guard = $guards[0] ?? null;
        $user = $request->user($guard);

        if (
            config('auth.restrict_to_allowed_email', true)
            && $user
            && strcasecmp($user->email, config('auth.allowed_email')) !== 0
        ) {
            Auth::guard($guard)->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }

        return parent::handle($request, $next, ...$guards);
    }

    protected function redirectTo($request)
    {
		return route('login');
        // if (! $request->expectsJson()) {

        // }
    }
}
