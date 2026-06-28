<?php

namespace App\Models;

use App\Traits\HasBasicStoreRequest;
use App\Traits\HasCreatedAt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;


/**
 * @property int $id
 * @property string $name
 * @property int $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBusinessSector newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBusinessSector newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBusinessSector onlyCompany($companyId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBusinessSector query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBusinessSector whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBusinessSector whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBusinessSector whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBusinessSector whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBusinessSector whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CashVeroBusinessSector extends Model
{
	const BUSINESS_SECTORS = 'business-sectors';
	use HasCreatedAt,HasBasicStoreRequest;

    protected $dates = [
    ];


    protected $guarded = [];


  
	public function getId(){
		return $this->id ;
	}
	public function getName()
	{
		return $this->name ;
	}
	
	public function scopeOnlyCompany(Builder $query,$companyId){
		return $query->where('company_id',$companyId);
	}
	
}
