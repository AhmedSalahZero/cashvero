<?php

namespace App\Models;

use App\Traits\Models\HasDeleteButTriggerChangeOnLastElement;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $time_of_deposit_id
 * @property string|null $expiry_date تاريخ الانتهاء هنحتاجه هنا علشان نجيب بيه ال start date القديمه
 * @property string $renewal_date تاريخ التجديد
 * @property numeric $interest_rate
 * @property int $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property numeric $interest_amount
 * @property-read \App\Models\Company|null $company
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\TdRenewalDateHistory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\TdRenewalDateHistory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\TdRenewalDateHistory query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\TdRenewalDateHistory whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\TdRenewalDateHistory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\TdRenewalDateHistory whereExpiryDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\TdRenewalDateHistory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\TdRenewalDateHistory whereInterestAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\TdRenewalDateHistory whereInterestRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\TdRenewalDateHistory whereRenewalDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\TdRenewalDateHistory whereTimeOfDepositId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\TdRenewalDateHistory whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class TdRenewalDateHistory extends Model
{
	use HasDeleteButTriggerChangeOnLastElement;

	protected $guarded = [
		'id'
	];
	public function company()
	{
		return $this->belongsTo(Company::class , 'company_id','id');
	}

	
	public function getExpiryDate()
    {
        return $this->expiry_date ;
    }
	public function getExpiryDateFormatted()
    {
		$expiryDate = $this->getExpiryDate() ;
        return $expiryDate ? Carbon::make($expiryDate)->format('d-m-Y') : null   ;
    }
	public function setExpiryDateAttribute($value)
	{
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['expiry_date'] =  $value ;
			return ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		
		$this->attributes['expiry_date'] = $year.'-'.$month.'-'.$day;
	}
	
	public function getRenewalDate()
    {
        return $this->renewal_date ;
    }
	public function getRenewalDateFormatted()
    {
		$renewalDate = $this->getRenewalDate() ;
        return $renewalDate ? Carbon::make($renewalDate)->format('d-m-Y') : null   ;
    }
	public function setRenewalDateAttribute($value)
	{
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['renewal_date'] =  $value ;
			return ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		
		$this->attributes['renewal_date'] = $year.'-'.$month.'-'.$day;
	}
	public function getRenewalDateFormattedForDatePicker()
	{
		$date = $this->getRenewalDate();
		return $date ? Carbon::make($date)->format('m/d/Y') : null;
	}
	public function getDuration()
	{
		return Carbon::make($this->getRenewalDate())->diffInDays($this->getExpiryDate());
	}
	public function getInterestRate()
	{
		return $this->interest_rate ?: 0 ;
	}
	public function getInterestRateFormatted()
	{
		return number_format($this->getInterestRate())  ;
	}
	// public function getFeesAmount()
	// {
	// 	return $this->fees_amount ;
	// }
	// public function getFeesAmountFormatted()
	// {
	// 	$amount = $this->getFeesAmount();
	// 	return number_format($amount) ;
	// }
	// public function commissionCurrentBankStatements():HasMany
	// {
	// 	return $this->hasMany(CurrentAccountBankStatement::class,'lg_renewal_date_history_id','id');
	// }
	
}
