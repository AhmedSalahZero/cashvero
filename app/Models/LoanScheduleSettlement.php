<?php

namespace App\Models;

use App\Traits\Models\HasCreditStatements;
use App\Traits\Models\HasDeleteButTriggerChangeOnLastElement;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $company_id
 * @property string $current_account_number
 * @property int $loan_schedule_id
 * @property string $date
 * @property numeric $amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\CurrentAccountBankStatement|null $currentAccountCreditBankStatement
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CurrentAccountBankStatement> $currentAccountCreditBankStatements
 * @property-read int|null $current_account_credit_bank_statements_count
 * @property-read bool|null $current_account_credit_bank_statements_exists
 * @property-read \App\Models\LoanSchedule|null $loanSchedule
 * @property-read \App\Models\CurrentAccountBankStatement|null $loanStatement
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LoanStatement> $loanStatements
 * @property-read int|null $loan_statements_count
 * @property-read bool|null $loan_statements_exists
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanScheduleSettlement company()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanScheduleSettlement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanScheduleSettlement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanScheduleSettlement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanScheduleSettlement whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanScheduleSettlement whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanScheduleSettlement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanScheduleSettlement whereCurrentAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanScheduleSettlement whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanScheduleSettlement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanScheduleSettlement whereLoanScheduleId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanScheduleSettlement whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LoanScheduleSettlement extends Model
{
	use HasCreditStatements,HasDeleteButTriggerChangeOnLastElement;
    

    protected $guarded = [];



	public static function boot()
	{
		
		parent::boot();
		static::created(function(self $loanScheduleSettlement){
			$loanScheduleSettlement->updateLoanScheduleRemaining();
		});
		static::deleted(function(self $loanScheduleSettlement){
			$loanScheduleSettlement->updateLoanScheduleRemaining();
		});
		static::updated(function(self $loanScheduleSettlement){
			$loanScheduleSettlement->updateLoanScheduleRemaining();
		});
	}
	public function updateLoanScheduleRemaining()
	{
		$loanSchedule = $this->loanSchedule ;
		$totalSettlement = 0 ;
		foreach($loanSchedule->settlements as $settlement){
			$totalSettlement+= $settlement->getAmount();
		}
		$totalLoanScheduleAmount = $loanSchedule->getSchedulePayment();
		$loanSchedule->update([
			'remaining'=>$totalLoanScheduleAmount - $totalSettlement 
		]);
		
	}
	
    public function scopeCompany($query)
    {
        return $query->where('company_id', request()->company->id?? Request('company_id') );
    }
	public function getAmount()
	{
		return $this->amount ;
	}
	public function getAmountFormatted()
	{
		return number_format($this->getAmount());
	}
	public function getCurrentAccountNumber()
	{
		return $this->current_account_number ;
	}
	public function getDate()
	{
		return $this->date;
	}
	public function getDateFormatted()
	{
		$date = $this->getDate();
		return  $date ? Carbon::make($date)->format('d-m-Y') : __('N/A') ;
	}
	public function setDateAttribute($value)
	{
		if(is_object($value)){
			return $value ;
		}
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['date'] = $value;
			return  ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		$this->attributes['date'] = $year.'-'.$month.'-'.$day;
		
	}
	public function getAccountNumber()
	{
		return $this->current_account_number ;
	}
	public function getPaidAmount()
	{
		return $this->getAmount();
	}
	public function getAccountTypeId(): int
	{
		return AccountType::onlyCurrentAccount()->first()->id;
	}
	public function loanSchedule()
	{
		return $this->belongsTo(LoanSchedule::class,'loan_schedule_id','id');
	}
	public function currentAccountCreditBankStatement()
	{
		return $this->hasOne(CurrentAccountBankStatement::class,'loan_schedule_settlement_id','id')->where('is_credit',1);
	}
	public function currentAccountCreditBankStatements()
	{
		return $this->hasMany(CurrentAccountBankStatement::class,'loan_schedule_settlement_id','id')->where('is_credit',1)->orderBy('full_date','desc');
	}
	public function loanStatement()
	{
		return $this->hasOne(CurrentAccountBankStatement::class,'loan_schedule_settlement_id','id');
	}
	public function loanStatements()
	{
		return $this->hasMany(LoanStatement::class,'loan_schedule_settlement_id','id')->orderBy('full_date','desc');
	}
	public function deleteAllRelations()
	{
		CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($this->currentAccountCreditBankStatements);
		LoanStatement::deleteButTriggerChangeOnLastElement($this->loanStatements);
	}
	public function handleLoanStatement(int $companyId , int $financialInstitutionId  , string $accountNumber,string $date , $debitAmount , string $commentEn , string $commentAr)
	{
		$financialInstitutionAccount = FinancialInstitutionAccount::findByAccountNumber($accountNumber,$companyId,$financialInstitutionId);
		$this->loanStatements()->create([
			'financial_institution_account_id'=>$financialInstitutionAccount->id ,
			'company_id'=>$companyId , 
			'is_debit'=>1 ,
			'is_credit'=> 0 ,
			'date'=>$date ,
			'debit'=>$debitAmount,
			'comment_en'=>$commentEn ,
			'comment_ar'=>$commentAr
		]);
	}
}
