<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Blocks cross-company IDOR on route-model binding.
 *
 * After SubstituteBindings resolves models, every bound Eloquent model that
 * has a company_id attribute must match the {company} route parameter.
 */
class EnsureRouteModelsBelongToCompany
{
    public function handle(Request $request, Closure $next)
    {
        $company = $request->route('company');

        if ($company instanceof Company) {
            $companyId = (int) $company->id;
        } elseif (is_numeric($company)) {
            $companyId = (int) $company;
        } else {
            return $next($request);
        }

        foreach ($request->route()->parameters() as $name => $parameter) {
            if ($name === 'company' || ! $parameter instanceof Model) {
                continue;
            }

            if (! array_key_exists('company_id', $parameter->getAttributes())) {
                continue;
            }

            if ((int) $parameter->getAttribute('company_id') !== $companyId) {
                abort(404);
            }
        }

        return $next($request);
    }
}
