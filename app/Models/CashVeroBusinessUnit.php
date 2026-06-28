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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBusinessUnit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBusinessUnit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBusinessUnit onlyCompany($companyId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBusinessUnit query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBusinessUnit whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBusinessUnit whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBusinessUnit whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBusinessUnit whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroBusinessUnit whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CashVeroBusinessUnit extends Model
{
	const BUSINESS_UNITS = 'business-units';
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
