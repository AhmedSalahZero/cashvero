<?php

namespace App\Models;

use App\Models\Company;
use App\Models\MoneyReceived;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string|null $date
 * @property int $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CashInSafeStatement> $cashInSafeStatements
 * @property-read int|null $cash_in_safe_statements_count
 * @property-read bool|null $cash_in_safe_statements_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MoneyReceived> $chequeInSafe
 * @property-read int|null $cheque_in_safe_count
 * @property-read bool|null $cheque_in_safe_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MoneyReceived> $chequeUnderCollections
 * @property-read int|null $cheque_under_collections_count
 * @property-read bool|null $cheque_under_collections_exists
 * @property-read \App\Models\Company $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MoneyPayment> $moneyPayments
 * @property-read int|null $money_payments_count
 * @property-read bool|null $money_payments_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MoneyReceived> $moneyReceived
 * @property-read int|null $money_received_count
 * @property-read bool|null $money_received_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MoneyPayment> $payableCheques
 * @property-read int|null $payable_cheques_count
 * @property-read bool|null $payable_cheques_exists
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OpeningBalance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OpeningBalance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OpeningBalance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OpeningBalance whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OpeningBalance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OpeningBalance whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OpeningBalance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OpeningBalance whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class OpeningBalance extends Model
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
	public function moneyReceived()
	{
		return $this->hasMany(MoneyReceived::class,'opening_balance_id');
	}
	public function moneyPayments()
	{
		return $this->hasMany(MoneyPayment::class,'opening_balance_id');
	}
	
	public function cashInSafeStatements():HasMany
	{
		return $this->hasMany(CashInSafeStatement::class , 'opening_balance_id','id');
	}
	
	public function chequeInSafe():HasMany
	{
		return $this->hasMany(MoneyReceived::class,'opening_balance_id','id')->where('type',MoneyReceived::CHEQUE)->whereHas('cheque',function(Builder $builder){
			$builder->where('status',Cheque::IN_SAFE);
		});
		
	}
	
	public function chequeUnderCollections():HasMany
	{
		return $this->hasMany(MoneyReceived::class,'opening_balance_id','id')->where('type',MoneyReceived::CHEQUE)->whereHas('cheque',function(Builder $builder){
			$builder->where('status',Cheque::UNDER_COLLECTION);
		});
	}
	public function payableCheques():HasMany	
	{
		return $this->hasMany(MoneyPayment::class,'opening_balance_id','id')->where('type',MoneyPayment::PAYABLE_CHEQUE)->whereHas('payableCheque',function(Builder $builder){
			$builder->where('status',PayableCheque::PENDING);
		});
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
