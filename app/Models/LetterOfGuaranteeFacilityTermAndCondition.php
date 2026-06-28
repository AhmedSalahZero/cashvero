<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $letter_of_guarantee_facility_id
 * @property int $company_id
 * @property string|null $outstanding_date
 * @property string|null $lg_type
 * @property string|null $outstanding_balance
 * @property string|null $cash_cover_rate
 * @property string|null $commission_rate
 * @property string|null $commission_interval
 * @property numeric $min_commission_fees
 * @property numeric $issuance_fees
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\LetterOfGuaranteeFacility|null $letterOfGuaranteeFacility
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacilityTermAndCondition newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacilityTermAndCondition newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacilityTermAndCondition query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacilityTermAndCondition whereCashCoverRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacilityTermAndCondition whereCommissionInterval($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacilityTermAndCondition whereCommissionRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacilityTermAndCondition whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacilityTermAndCondition whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacilityTermAndCondition whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacilityTermAndCondition whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacilityTermAndCondition whereIssuanceFees($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacilityTermAndCondition whereLetterOfGuaranteeFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacilityTermAndCondition whereLgType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacilityTermAndCondition whereMinCommissionFees($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacilityTermAndCondition whereOutstandingBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacilityTermAndCondition whereOutstandingDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacilityTermAndCondition whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacilityTermAndCondition whereUpdatedBy($value)
 * @mixin \Eloquent
 */
class LetterOfGuaranteeFacilityTermAndCondition extends Model
{
    protected $guarded = ['id'];
	public function getLgType()
	{
		return $this->lg_type;
	}
	public function getLgTypeFormatted()
	{
		return camelizeWithSpace($this->getLgType());
	} 
	public function getOutstandingBalance()
	{
		return $this->outstanding_balance ?: 0 ;
	}
	
	public function getOutstandingDateFormatted()
	{
		$outStandingDate = $this->outstanding_date ;
		return $outStandingDate ? Carbon::make($outStandingDate)->format('d-m-Y'):null ;
	}
	
	public function getCashCoverRate()
	{
		return $this->cash_cover_rate ?: 0 ;
	}
	public function getMinCommissionFees()
	{
		return $this->min_commission_fees ?: 0 ;
	}
	public function getIssuanceFees()
	{
		return $this->issuance_fees ?: 0 ;
	}
	public function getCommissionRate()
	{
		return $this->commission_rate ?: 0 ;
	}
	public function getCommissionInterval()
	{
		return $this->commission_interval  ;
	}
	public function letterOfGuaranteeFacility()
	{
		return $this->belongsTo(LetterOfGuaranteeFacility::class , 'letter_of_guarantee_facility_id','id');
	}
}
