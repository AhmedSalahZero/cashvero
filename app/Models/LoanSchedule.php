<?php

namespace App\Models;

use App\Helpers\HArr;
use App\Helpers\HDate;
use App\Traits\StaticBoot;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $medium_term_loan_id
 * @property string|null $date
 * @property numeric|null $beginning_balance
 * @property numeric|null $schedule_payment
 * @property numeric|null $interest_amount
 * @property numeric|null $principle_amount
 * @property numeric|null $end_balance
 * @property numeric $remaining
 * @property string|null $status
 * @property int|null $created_by
 * @property int $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $updated_by
 * @property-read \App\Models\MediumTermLoan|null $mediumTermLoan
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LoanScheduleSettlement> $settlements
 * @property-read int|null $settlements_count
 * @property-read bool|null $settlements_exists
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanSchedule company()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanSchedule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanSchedule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanSchedule query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanSchedule whereBeginningBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanSchedule whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanSchedule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanSchedule whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanSchedule whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanSchedule whereEndBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanSchedule whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanSchedule whereInterestAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanSchedule whereMediumTermLoanId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanSchedule wherePrincipleAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanSchedule whereRemaining($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanSchedule whereSchedulePayment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanSchedule whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanSchedule whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanSchedule whereUpdatedBy($value)
 * @mixin \Eloquent
 */
class LoanSchedule extends Model
{
    use StaticBoot;
    

    protected $guarded = [];


    
    protected $table = 'loan_schedules';
	
    public function scopeCompany($query)
    {
        return $query->where('company_id', request()->company->id?? Request('company_id') );
    }
	
	public function getMediumTermLoanName()
	{
		return $this->mediumTermLoan?->getName() ?? __('N/A');
	}
	public function getMediumTermLoanId()
	{
		return $this->medium_term_loan_id ?? $this->mediumTermLoan?->id;
	}
	public function getDate()
	{
		return $this->date ;
	}
	public function getDateFormatted()
	{
		$date = $this->getDate();
		return isValidScheduleDate($date) ? Carbon::make($date)->format('d-m-Y') : __('N/A'); 
	}

	public function getSettlementDefaultDate(): string
	{
		return getSettlementDefaultDate($this->getDate());
	}
	public function getCurrency()
	{
		return $this->mediumTermLoan?->currency ?? '-';
	}
	public function getBeginningBalance()
	{
		return $this->beginning_balance ?: 0 ;
	}
	public function getBeginningBalanceFormatted()
	{
		return number_format($this->getBeginningBalance())  ;
	}
	public function getSchedulePayment()
	{
		return $this->schedule_payment ?: 0 ;
	}
	public function getSchedulePaymentFormatted()
	{
		return number_format($this->getSchedulePayment())  ;
	}
	public function getInterestAmount()
	{
		return $this->interest_amount ?: 0 ;
	}
	public function getInterestAmountFormatted()
	{
		return number_format($this->getInterestAmount())  ;
	}
	public function getPrincipleAmount()
	{
		return $this->principle_amount ?: 0 ;
	}
	public function getPrincipleAmountFormatted()
	{
		return number_format($this->getPrincipleAmount())  ;
	}
	public function getEndBalance()
	{
		return $this->end_balance ?: 0 ;
	}
	public function getFinancialInstitutionId()
	{
		return $this->mediumTermLoan?->financial_institution_id;
	}
	public function hasMediumTermLoan(): bool
	{
		return $this->medium_term_loan_id && $this->mediumTermLoan !== null;
	}
	public function settlements():HasMany
	{
		return $this->hasMany(LoanScheduleSettlement::class,'loan_schedule_id');
	}
	public function getEndBalanceFormatted()
	{
		return number_format($this->getEndBalance())  ;
	}
	public function mediumTermLoan():BelongsTo
	{
		return $this->belongsTo(MediumTermLoan::class , 'medium_term_loan_id','id');
	}
	public static function getExportableFields():array 
	{
		return [
			'date'=>__('Date'),
			'beginning_balance'=>__('Beginning Balance'),
			'schedule_payment'=>__('Schedule Payment'),
			'interest_amount'=>__('Interest Amount'),
			'principle_amount'=>__('Principle Amount'),
			'end_balance'=>__('End Balance')
		];
	}
	public function getStatusFormatted()
	{
		return $this->status ? snakeToCamel($this->status) : __('N/A');
	}
	public function getRemaining()
	{
		return $this->remaining ?: 0 ;
	}
	public function getRemainingFormatted():string 
	{
		return number_format($this->getRemaining());
	}
	public function getInstallmentNumber()
	{
		if (!$this->mediumTermLoan) {
			return 0;
		}

		return array_keys($this->mediumTermLoan->loanSchedules->sortBy('date')->filter(function(LoanSchedule $loanSchedule){
			return $loanSchedule->schedule_payment > 0;
		})->values()
		->where('id',$this->id)
		->toArray())[0]  + 1 	;
	}
	public static function getLoanInstallmentsAtDates(array &$result ,$foreignExchangeRates,$mainFunctionalCurrency  , int $companyId,array $datesWithWeekNumber,string $endDate) 
	{
		$mainType = 'cash_expenses';
		$rows = DB::table('loan_schedules')->where('loan_schedules.company_id',$companyId)
						->join('medium_term_loans','medium_term_loans.id','=','loan_schedules.medium_term_loan_id')
						// ->where('medium_term_loans.currency',$currency)
						->whereBetween('date',[now()->format('Y-m-d'),$endDate])
						->where('remaining','>',0)
						->selectRaw('medium_term_loans.name as name ,loan_schedules.remaining as paid_amount ,date,medium_term_loans.currency,date')->get();
		$subType = __('Loan Installments');
		foreach($rows as $row){
			$date = $row->date;
			$currentCurrency = $row->currency;
			$exchangeRate  = ForeignExchangeRate::getExchangeRateAt($currentCurrency,$mainFunctionalCurrency,$date,$companyId,$foreignExchangeRates);
			$lcType = $row->name;
			$currentPaidAmount = $row->paid_amount   * $exchangeRate;
			$currentWeekYear =$datesWithWeekNumber[$row->date];
			$result[$mainType][$subType][$lcType]['weeks'][$currentWeekYear] = isset($result[$mainType][$subType][$lcType]['weeks'][$currentWeekYear]) ? $result[$mainType][$subType][$lcType]['weeks'][$currentWeekYear] + $currentPaidAmount :  $currentPaidAmount;
			$result[$mainType][$subType][$lcType]['total'] = isset($result[$mainType][$subType][$lcType]['total']) ? $result[$mainType][$subType][$lcType]['total']  + $currentPaidAmount : $currentPaidAmount;
			$currentTotal = $currentPaidAmount;
			$result[$mainType][$subType]['total'][$currentWeekYear] = isset($result[$mainType][$subType]['total'][$currentWeekYear]) ? $result[$mainType][$subType]['total'][$currentWeekYear] +  $currentTotal : $currentTotal ;
			// $result[$mainType][$subType]['total']['total_of_total'] = isset($result[$mainType][$subType]['total']['total_of_total']) ? $result[$mainType][$subType]['total']['total_of_total'] + $result[$mainType][$subType]['total'][$currentWeekYear] : $result[$mainType][$subType]['total'][$currentWeekYear];
		//	$totalCashOutFlowArray[$currentWeekYear] = isset($totalCashOutFlowArray[$currentWeekYear]) ? $totalCashOutFlowArray[$currentWeekYear] +   $currentTotal : $currentTotal ;
		}
	
	}
	
}
