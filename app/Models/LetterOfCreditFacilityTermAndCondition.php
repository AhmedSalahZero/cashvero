<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $letter_of_credit_facility_id
 * @property int $company_id
 * @property string|null $lc_type
 * @property string|null $cash_cover_rate
 * @property string|null $commission_rate
 * @property string|null $commission_interval
 * @property numeric $min_commission_fees
 * @property numeric $issuance_fees
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\LetterOfCreditFacility|null $letterOfCreditFacility
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacilityTermAndCondition newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacilityTermAndCondition newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacilityTermAndCondition query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacilityTermAndCondition whereCashCoverRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacilityTermAndCondition whereCommissionInterval($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacilityTermAndCondition whereCommissionRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacilityTermAndCondition whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacilityTermAndCondition whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacilityTermAndCondition whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacilityTermAndCondition whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacilityTermAndCondition whereIssuanceFees($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacilityTermAndCondition whereLcType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacilityTermAndCondition whereLetterOfCreditFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacilityTermAndCondition whereMinCommissionFees($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacilityTermAndCondition whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacilityTermAndCondition whereUpdatedBy($value)
 * @mixin \Eloquent
 */
class LetterOfCreditFacilityTermAndCondition extends Model
{
    protected $guarded = ['id'];
	public function getLcType()
	{
		return $this->lc_type;
	}
	public function getLcTypeFormatted()
	{
		return camelizeWithSpace($this->getLcType());
	} 
	// public function getOutstandingBalance()
	// {
	// 	return $this->outstanding_balance ?: 0 ;
	// }
	public function getMinCommissionFees()
	{
		return $this->min_commission_fees ?: 0 ;
	}
	public function getIssuanceFees()
	{
		return $this->issuance_fees ?: 0 ;
	}
	// public function getOutstandingDateFormatted()
	// {
	// 	$outStandingDate = $this->outstanding_date ;
	// 	return $outStandingDate ? Carbon::make($outStandingDate)->format('d-m-Y'):null ;
	// }
	
	public function getCashCoverRate()
	{
		return $this->cash_cover_rate ?: 0 ;
	}
	public function getCommissionRate()
	{
		return $this->commission_rate ?: 0 ;
	}
	public function getCommissionInterval()
	{
		return $this->commission_interval  ;
	}
	public function letterOfCreditFacility()
	{
		return $this->belongsTo(LetterOfCreditFacility::class , 'letter_of_credit_facility_id','id');
	}
}
