<?php

namespace App\Http\Middleware;

use Closure;

class CheckIfAccountExpired
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
		if($request->user() && $request->user()->AccountExpired()){
			Auth()->logout();
			return redirect()->route('login');
		}
        return $next($request);
    }
}
