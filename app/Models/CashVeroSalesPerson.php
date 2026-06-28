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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroSalesPerson newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroSalesPerson newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroSalesPerson onlyCompany($companyId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroSalesPerson query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroSalesPerson whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroSalesPerson whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroSalesPerson whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroSalesPerson whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashVeroSalesPerson whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CashVeroSalesPerson extends Model
{
	const SALES_PERSONS = 'sales-persons';
	protected $table ='cash_vero_sales_persons';
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
