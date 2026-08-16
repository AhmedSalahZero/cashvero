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
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MediumTermLoanBankStatement> $mediumTermLoanBankStatements
 * @property-read int|null $medium_term_loan_bank_statements_count
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
	/**
	 * * صف ال
	 * * debit
	 * * اللي بينزل على حساب القرض نفسه بقيمة ال
	 * * principle
	 * * اللي اتسدد من القسط دا (لو القرض كان بيتدفع منه فواتير)
	 */
	public function mediumTermLoanBankStatements()
	{
		return $this->hasMany(MediumTermLoanBankStatement::class,'loan_schedule_settlement_id','id')->orderBy('full_date','desc');
	}
	public function deleteAllRelations()
	{
		CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($this->currentAccountCreditBankStatements);
		LoanStatement::deleteButTriggerChangeOnLastElement($this->loanStatements);
		MediumTermLoanBankStatement::deleteButTriggerChangeOnLastElement($this->mediumTermLoanBankStatements);
	}
	/**
	 * * قيمة ال
	 * * principle
	 * * اللي اتسددت لحد اجمالي مبلغ
	 * * $paidSoFar
	 * * على القسط دا.
	 *
	 * * القاعدة (اتأكدت مع صاحب المشروع 2026-08-16): القسط بيتقسم فايدة و
	 * * principle
	 * * والسداد بيروح للفايدة الاول. فا طالما اللي دفعته لسه ما تخطاش قيمة
	 * * الفايدة يبقي ما سددتش اي جزء من اصل القرض. مثال: قسط 100 الف = 60
	 * * فايدة + 40 اصل .. لو دفعت 50 يبقي كلها فايدة و ال
	 * * principle
	 * * = صفر .. ولو دفعت 80 يبقي 60 فايدة و 20
	 * * principle
	 */
	public static function principlePaidFor(float $paidSoFar , float $interestAmount , float $principleAmount):float
	{
		return max(0, min($paidSoFar - $interestAmount, $principleAmount));
	}
	/**
	 * * قيمة الفايدة اللي اتسددت لحد اجمالي مبلغ
	 * * $paidSoFar
	 * * على القسط دا. الفايدة بتتاخد الاول بالكامل .. فا هي ابسط حاجه:
	 * * اللي دفعته او قيمة الفايدة .. ايهما اقل.
	 */
	public static function interestPaidFor(float $paidSoFar , float $interestAmount):float
	{
		return max(0, min($paidSoFar, $interestAmount));
	}
	/**
	 * * بيقسم الدفعة دي جزئين على حساب القرض:
	 * *   - جزء الفايدة  -> عمود
	 * *     interest_amount
	 * *     .. تسجيل بس، عمره ما بيحرك الرصيد ولا ال
	 * *     room
	 * *     (لان الفايدة مش من اصل القرض)
	 * *   - جزء ال
	 * *     principle
	 * *     -> عمود
	 * *     debit
	 * *     .. ودا اللي بيقلل المسحوب ويرجع يفضي
	 * *     room
	 *
	 * * مهم: بنحسب الفرق بين المسدد بعد الدفعة وقبلها .. مش نسبة من الدفعة ..
	 * * علشان لو القسط اتسدد على اكتر من دفعة تبقي الفايدة اتاخدت كاملة الاول
	 * * ثم يبدا الاصل.
	 *
	 * * وبننزل الحركة حتى لو ال
	 * * principle
	 * * = صفر (يعني الدفعة كلها راحت فوايد) علشان الكشف يورّي الدفعة دي
	 * * برضه — من غير كده الفايدة المدفوعة كانت هتختفي من الكشف.
	 */
	public function handleMediumTermLoanRepayment(int $companyId , string $commentEn , string $commentAr):void
	{
		$loanSchedule = $this->loanSchedule ;
		$mediumTermLoan = $loanSchedule?->mediumTermLoan ;

		/**
		 * * القرض ال
		 * * existing
		 * * ماعندوش حساب اصلا على السيستم .. هو بس بيتسدد اقساط .. فا مفيش
		 * * حاجه ننزلها. (تفصيلة الفايدة/الاصل بتاعته بتظهر برضه في شاشة ال
		 * * MTL Statement
		 * * لانها بتتحسب من جدول الاقساط مباشرة)
		 */
		if (! $mediumTermLoan || ! $mediumTermLoan->isNotConsumedYet()) {
			return ;
		}
		/**
		 * * ولو القرض ما اتسحبش منه اي حاجه من خلال كاش فيرو يبقي مفيش حساب
		 * * ننزل عليه اصلا
		 */
		if (! $mediumTermLoan->hasDrawdowns()) {
			return ;
		}

		$interestAmount  = (float) $loanSchedule->getInterestAmount();
		$principleAmount = (float) $loanSchedule->getPrincipleAmount();

		/**
		 * * اللي اتدفع قبل الدفعة دي على نفس القسط .. مرتب بال
		 * * id
		 * * لان الشاشة اصلا ما بتسمحش بتعديل او حذف غير اخر دفعة
		 */
		$paidBefore = (float) $loanSchedule->settlements()
			->where('id','<',$this->id)
			->sum('amount');
		$paidAfter = $paidBefore + (float) $this->getAmount();

		$principleDebit = self::principlePaidFor($paidAfter,$interestAmount,$principleAmount)
			- self::principlePaidFor($paidBefore,$interestAmount,$principleAmount);
		$interestPaid = self::interestPaidFor($paidAfter,$interestAmount)
			- self::interestPaidFor($paidBefore,$interestAmount);

		if ($principleDebit <= 0 && $interestPaid <= 0) {
			return ;
		}

		$this->mediumTermLoanBankStatements()->create([
			'medium_term_loan_id'=>$mediumTermLoan->id ,
			'company_id'=>$companyId ,
			'type'=>MediumTermLoanBankStatement::INSTALLMENT_REPAYMENT ,
			'date'=>$this->getDate() ,
			'limit'=>$mediumTermLoan->getLimit() ,
			'beginning_balance'=>0 ,
			'debit'=>max(0,$principleDebit) ,
			'credit'=>0 ,
			'interest_amount'=>max(0,$interestPaid) ,
			'comment_en'=>$commentEn ,
			'comment_ar'=>$commentAr ,
		]);
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
