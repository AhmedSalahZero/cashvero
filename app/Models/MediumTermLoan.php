<?php

namespace App\Models;

use App\Traits\HasBasicStoreRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $company_id
 * @property int $financial_institution_id
 * @property string $status
 * @property string|null $name
 * @property string|null $start_date
 * @property string|null $end_date
 * @property string $currency
 * @property numeric $limit
 * @property numeric $paid_amount
 * @property numeric $outstanding_amount
 * @property string|null $account_number
 * @property numeric $borrowing_rate
 * @property numeric $margin_rate
 * @property int|null $duration tenor (duration in months)
 * @property string $installment_payment_interval
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\FinancialInstitution|null $financialInstitution
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LoanSchedule> $loanSchedules
 * @property-read int|null $loan_schedules_count
 * @property-read bool|null $loan_schedules_exists
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MediumTermLoan newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MediumTermLoan newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MediumTermLoan query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MediumTermLoan whereAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MediumTermLoan whereBorrowingRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MediumTermLoan whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MediumTermLoan whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MediumTermLoan whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MediumTermLoan whereDuration($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MediumTermLoan whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MediumTermLoan whereFinancialInstitutionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MediumTermLoan whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MediumTermLoan whereInstallmentPaymentInterval($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MediumTermLoan whereLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MediumTermLoan whereMarginRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MediumTermLoan whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MediumTermLoan whereOutstandingAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MediumTermLoan wherePaidAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MediumTermLoan whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MediumTermLoan whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MediumTermLoan whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class MediumTermLoan extends Model 
{
	use HasBasicStoreRequest ;
	const RUNNING = 'running';

	public static function getAllTypes()
	{
		return [
			self::RUNNING,
		];
	}
    protected $guarded = ['id'];
	
	public function getName()
	{
		return $this->name ;
	}
    public function getStartDate()
    {
        return $this->start_date ?: 0 ;
    }
	public function getStartDateFormatted()
	{
		
		return Carbon::make($this->getStartDate())->format('d-m-Y') ;
	}
    public function setStartDateAttribute($value)
    {
        if (!$value) {
            return null ;
        }
        $date = explode('/', $value);
        if (count($date) != 3) {
            $this->attributes['start_date'] = $value;

            return  ;
        }
        $month = $date[0];
        $day = $date[1];
        $year = $date[2];
        $this->attributes['start_date'] = $year . '-' . $month . '-' . $day;
    }
	
	public function getEndDate()
    {
        return $this->end_date ?: 0 ;
    }
	public function getEndDateFormatted()
	{
		return Carbon::make($this->getEndDate())->format('d-m-Y') ;
	}
    public function setEndDateAttribute($value)
    {
        if (!$value) {
            return null ;
        }
        $date = explode('/', $value);
        if (count($date) != 3) {
            $this->attributes['end_date'] = $value;

            return  ;
        }
        $month = $date[0];
        $day = $date[1];
        $year = $date[2];
        $this->attributes['end_date'] = $year . '-' . $month . '-' . $day;
    }
	
	public function getCurrency()
	{
		return $this->currency ;
	}
	public function getCurrencyFormatted()
	{
		return __($this->getCurrency());
	}
	public function getAccountNumber()
	{
		return $this->account_number;
	}	
	public function financialInstitution():BelongsTo
	{
		return $this->belongsTo(FinancialInstitution::class ,'financial_institution_id','id');
	}
	public function getFinancialInstitutionName()
	{
		$financialInstitution = $this->financialInstitution ;
		return  $financialInstitution ? $financialInstitution->getName()  : __('N/A');
	}
	public function getBorrowingRate()
	{
		return $this->borrowing_rate ?: 0 ;
	}
	public function getBorrowingRateFormatted()
	{
		return number_format($this->getBorrowingRate(),2) . ' %';
	}
	public function getMarginRate()
	{
		return $this->margin_rate ?: 0 ;
	}
	public function getMarginRateFormatted()
	{
		return number_format($this->getMarginRate(),2) . ' %';
	}
	public function getInterestRate()
	{
		return $this->getMarginRate() + $this->getBorrowingRate();
	}
	public function getDuration()
	{
		return $this->duration ;
	}
	public function getDurationFormatted()
	{
		return $this->getDuration() . ' ' . __('Months');
	}
	public function getPaymentInstallmentInterval()
	{
		return $this->installment_payment_interval ;
	}
	public function getPaymentInstallmentIntervalFormatted()
	{
		return  str_to_upper($this->getPaymentInstallmentInterval());
		
	}
	public function loanSchedules():HasMany
	{
		return $this->hasMany(LoanSchedule::class,'medium_term_loan_id','id');
	}
    public function deleteRelations()
    {
		$this->loanSchedules->each(function(LoanSchedule $loanSchedule){
			$loanSchedule->delete();
		});
    }
	public function getLimit()
	{
		return $this->limit?:0 ;
	}
	public function getLimitFormatted()
	{
		return number_format($this->getLimit());
	}
	public function getLoanOutstanding()
	{
		return $this->outstanding_amount ?: 0 ;
	}
	public function getLoanOutstandingFormatted()
	{
		return number_format($this->getLoanOutstanding());
	}
	public function getNextInstallmentDateAndAmount(string $date):array 
	{
		$schedules = $this->relationLoaded('loanSchedules')
			? $this->loanSchedules
			: $this->loanSchedules()->get();

		$nextInstallment = $schedules
			->filter(fn ($schedule) => $schedule->date >= $date)
			->sortBy('date')
			->first();

		$amountFormatted  = $nextInstallment ? $nextInstallment->getSchedulePaymentFormatted() : 0 ;
		$dateFormatted =  $nextInstallment ? $nextInstallment->getDateFormatted() : null ;
		return [
			'amount_formatted'=>$amountFormatted ,
			'date_formatted'=>$dateFormatted
		];
	}	
	public function getTotalPastDueRemaining()
	{
		$pastDueItems = $this->getLoanPastDuesDetailsArray();
		return array_sum(array_column($pastDueItems,'remaining'));
	}
	public function getTotalPastDueRemainingFormatted()
	{
		return number_format($this->getTotalPastDueRemaining());
	}
	public function getLoanPastDuesDetailsArray():array 
	{
		if ($this->relationLoaded('loanSchedules')) {
			return $this->loanSchedules
				->whereIn('status', ['past_due', 'partially_paid_and_past_due'])
				->map(fn ($schedule) => $schedule->only(['date', 'schedule_payment', 'remaining']))
				->values()
				->all();
		}

		return  $this->loanSchedules()->whereIn('status',['past_due','partially_paid_and_past_due'])->get(['date','schedule_payment','remaining'])->toArray();
	}
}
