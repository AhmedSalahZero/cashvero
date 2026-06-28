<?php
namespace App\Models\Traits\Scopes;

use Illuminate\Database\Eloquent\Builder;

trait CompanyScope
{
        public function scopeOnlyCurrentCompany(Builder $builder , ?int $companyId = null) 
        {
            $builder->where('company_id',$companyId ?: getCurrentCompanyId());
        }
        
} 
