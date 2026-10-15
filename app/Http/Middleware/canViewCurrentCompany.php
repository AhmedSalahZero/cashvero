<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class canViewCurrentCompany
{
 
    public function handle(Request $request, Closure $next)
    {


        // $currentCompanyId = 'test';
        $currentCompanyId = Request()->segment(2);
        if(!is_numeric($currentCompanyId) || in_array( $currentCompanyId ,$request->user()->companies->pluck('id')->toArray()) ){
            // return $next($request);
            return $next($request);
            
        }

        return abort(403);
        
        
    }
}
