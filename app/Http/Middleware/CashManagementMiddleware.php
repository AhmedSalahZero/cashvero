<?php

namespace App\Http\Middleware;

use Closure;

class CashManagementMiddleware
{
   
    public function handle($request, Closure $next)
    {
        return $next($request);
    }
}
