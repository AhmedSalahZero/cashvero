<?php

namespace App\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $date
 * @property int $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company|null $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MoneyPayment> $moneyModel
 * @property-read int|null $money_model_count
 * @property-read bool|null $money_model_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SupplierInvoice> $supplierInvoices
 * @property-read int|null $supplier_invoices_count
 * @property-read bool|null $supplier_invoices_exists
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierOpeningBalance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierOpeningBalance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierOpeningBalance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierOpeningBalance whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierOpeningBalance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierOpeningBalance whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierOpeningBalance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierOpeningBalance whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class SupplierOpeningBalance extends Model
{
    protected $guarded = ['id'];
	const OPEN_BALANCE  = 'opening-balance';
	public function getId()
	{
		return $this->id;
	}
	public function getDate()
	{
		return $this->date; 
	}
	public function company()
	{
		return $this->belongsTo(Company::class , 'company_id','id');
	}
	public function supplierInvoices()
	{
		return $this->hasMany(SupplierInvoice::class,'opening_balance_id','id');
		
	}
	public function moneyModel()
	{
		return $this->hasMany(MoneyPayment::class,'advanced_opening_balance_id','id');
	}
	public function setDateAttribute($value)
	{
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['date'] =  $value ;
			return ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		
		$this->attributes['date'] = $year.'-'.$month.'-'.$day;
	}


	
	
	
}
