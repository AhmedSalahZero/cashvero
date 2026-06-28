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
}
