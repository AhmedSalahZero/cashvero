<?php
namespace App\Traits;

use App\Models\Company;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait HasCompany
{
	public function company():BelongsTo
    {
		return $this->belongsTo(Company::class , 'company_id','id');
	}

	/**
	 * Scope route-model binding to the current {company} when present.
	 * Defense in depth alongside EnsureRouteModelsBelongToCompany middleware.
	 */
	public function resolveRouteBinding($value, $field = null)
	{
		$field = $field ?: $this->getRouteKeyName();
		$query = static::where($field, $value);

		$company = request()->route('company');
		if ($company instanceof Company) {
			$query->where($this->getTable().'.company_id', $company->id);
		} elseif (is_numeric($company)) {
			$query->where($this->getTable().'.company_id', (int) $company);
		}

		return $query->first();
	}
}
