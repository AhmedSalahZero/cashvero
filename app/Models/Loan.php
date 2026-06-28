<?php

namespace App\Models;

use App\Models\Traits\Scopes\CompanyScope;
use App\Traits\HasBasicStoreRequest;
use App\Traits\HasCollectionOrPaymentStatement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use MathPHP\Finance;

/**
 * @property int $id
 * @property int $company_id
 * @property string|null $section_name
 * @property int|null $is_with_capitalization
 * @property int|null $financial_institution_id
 * @property string|null $model_type
 * @property string|null $loan_type
 * @property string|null $grace_period
 * @property string|null $loan_amount
 * @property string|null $installment_interval
 * @property string|null $start_date
 * @property string|null $end_date
 * @property string|null $period
 * @property string|null $fixedType
 * @property string|null $base_rate
 * @property string|null $margin_rate
 * @property string|null $pricing
 * @property string|null $duration tenor
 * @property string|null $step_down_rate
 * @property string|null $step_up_rate
 * @property string|null $step_up_interval
 * @property string|null $step_down_interval
 * @property string|null $borrowing_rate
 * @property string|null $capitalization_type
 * @property string|null $margin_interest
 * @property string|null $loan_interest
 * @property string|null $min_interest
 * @property string|null $repayment_duration
 * @property string|null $installment_amount
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $interest_interval
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan onlyCurrentCompany(?int $companyId = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan whereBaseRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan whereBorrowingRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan whereCapitalizationType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan whereDuration($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan whereFinancialInstitutionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan whereFixedType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan whereGracePeriod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan whereInstallmentAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan whereInstallmentInterval($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan whereInterestInterval($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan whereIsWithCapitalization($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan whereLoanAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan whereLoanInterest($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan whereLoanType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan whereMarginInterest($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan whereMarginRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan whereMinInterest($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan whereModelType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan wherePeriod($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan wherePricing($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan whereRepaymentDuration($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan whereSectionName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan whereStepDownInterval($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan whereStepDownRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan whereStepUpInterval($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan whereStepUpRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Loan whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Loan extends Model 
{
	use   CompanyScope , HasBasicStoreRequest,HasCollectionOrPaymentStatement;

	protected $guarded = [
		'id'
	];
	
	protected $casts = [
	];
	
	public static function isWithoutCapitalization($loanType):bool 
	{
		return in_array($loanType, Self::graceTypes());
	}
	public static function isWithCapitalization($loanType):bool 
	{
		return Str::contains($loanType,'with_capitalization') ;
	}
	public static function getCapitalizationType($loanType)
	{
		if(Self::isWithoutCapitalization($loanType)){
			return 'without_capitalization';
		}
		if(self::isWithCapitalization($loanType)){
			return 'with_capitalization';
		}
		return null ;
		
	}
	public static function stepUpTypes():array
	{
		return [
			'step-up', 'grace_step-up_with_capitalization', 'grace_step-up_without_capitalization'
		];
	}

	public static function stepDownTypes():array
	{
		return [
			'step-down', 'grace_step-down_with_capitalization', 'grace_step-down_without_capitalization'
		];
	}

	public static function graceTypes():array
	{
		return [
			'grace_step-up_without_capitalization', 'grace_step-down_without_capitalization',
			'grace_period_without_capitalization'
		];
	}

	public static function getStepRate($loanType, $stepUpRate, $stepDownRate):float
	{
		if (!in_array($loanType, array_merge(self::stepDownTypes(), self::stepUpTypes()))) {
			return 0;
		}

		return in_array($loanType, Self::stepUpTypes()) ? $stepUpRate : $stepDownRate;
	}

	public static function getAppliedStepIntervalName($loanType, $stepUpInterval, $stepDownInterval):?string
	{
		return in_array($loanType, Self::stepUpTypes()) ? $stepUpInterval : $stepDownInterval;
	}

	
	// public function acquisition()
	// {
	// 	return $this->belongsTo(Acquisition::class,'acquisition_id','id');
	// }
	public function getStartDate()
	{
		return $this->start_date ; 
	}
	// public function scopeOnlyForSection($query,string $sectionName)
	// {
	// 	return $query->where('section_name',$sectionName);
	// }
	public function getLoanType():string 
	{
		return  $this->loan_type ;
	}
	// public function getPricing():float
	// {
	// 	$baseRate = $this->getBaseRate() ;
	// 	$marginRate = $this->getMarginRate();
		
	// 	return  $baseRate + $marginRate ; 
	// }
	public function getGracePeriod():int 
	{
		return $this->grace_period?:0;
	}
	public function getLoanAmount():float 
	{
		return $this->loan_amount ?:0;
	}
	public function getInstallmentInterval():?string
	{
		return $this->installment_interval ;
	}
	public function getTenor():int 
	{
		return $this->period?:0 ;
	}
	public function getMarginRate()
	{
		return $this->margin_rate?:0 ; 
	}
	public function getBaseRate()
	{
		return $this->base_rate?:0 ;
	}
	public function getStepUpRate()
	{
		return $this->step_up_rate ?:0;
	}
	public function getStepUpIntervalName()
	{
		return $this->step_up_interval  ;
	}
	public function getStepDownRate()
	{
		return $this->step_down_rate?:0 ;
	}
	public function getStepDownIntervalName()
	{
		return $this->step_down_interval ;
	}
	public static function convertFlatRateToDecreasingRate(float $flatRate , int $tenor):float 
	{
		// $tenor       = 13;    // tenor in months
		$present_value = 1;     // Mortgage note of $265,000.00
		$future_value  = 0;
		$beginning     = false;  
		$payment = -(1+(1*$flatRate/12*$tenor))/$tenor;
		return  Finance::rate($tenor, $payment, $present_value, $future_value, $beginning)*12*100;
	}
}
