<?php

namespace App\Models;

use App\Models\Company;
use App\Models\MoneyReceived;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $date
 * @property int $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company|null $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CustomerInvoice> $customerInvoices
 * @property-read int|null $customer_invoices_count
 * @property-read bool|null $customer_invoices_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MoneyReceived> $moneyModel
 * @property-read int|null $money_model_count
 * @property-read bool|null $money_model_exists
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerOpeningBalance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerOpeningBalance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerOpeningBalance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerOpeningBalance whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerOpeningBalance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerOpeningBalance whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerOpeningBalance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerOpeningBalance whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CustomerOpeningBalance extends Model
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
	public function customerInvoices()
	{
		return $this->hasMany(CustomerInvoice::class,'opening_balance_id','id');
	}
	public function moneyModel()
	{
		return $this->hasMany(MoneyReceived::class,'advanced_opening_balance_id','id');
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
