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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroSalesChannel newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroSalesChannel newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroSalesChannel onlyCompany($companyId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroSalesChannel query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroSalesChannel whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroSalesChannel whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroSalesChannel whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroSalesChannel whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroSalesChannel whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CashVeroSalesChannel extends Model
{
	const SALES_CHANNELS = 'sales-channels';
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
