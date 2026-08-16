<?php

namespace App\Models;

use App\Traits\HasBasicStoreRequest;
use App\Traits\HasOdooPaymentMethod;
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
class MediumTermLoan extends Model implements \App\Interfaces\Models\ISyncsWithOdooChartOfAccount
{
	/**
	 * * HasOdooPaymentMethod
	 * * دي بتوفر ال
	 * * getOdooInboundTransferPaymentMethodId()
	 * * واخواتها اللي
	 * * FinancialInstitution::getOdooPaymentIds()
	 * * بتنادي عليهم على اي حساب بيتختار في شاشة الدفع لما اودو متوصلة.
	 * * من غيرها السداد بيقع بـ
	 * * "Call to undefined method"
	 * * بدل ما يدي رسالة مفهومة.
	 */
	use HasBasicStoreRequest , HasOdooPaymentMethod ;
	const RUNNING = 'running';

	/**
	 * * القرض اتصرف بالفعل قبل ما الشركة تدخل على كاش فيرو .. مش هينفع تدفع بيه
	 * * فواتير لان مفيش منه حاجه متبقية تتسحب .. هو بس بيتسدد أقساط
	 */
	const CONSUMPTION_EXISTING = 'existing';
	/**
	 * * قرض جديد لسه ما اتصرفش .. البنك هيدفع منه للموردين مباشرة
	 * * وبالتالي بيظهر كحساب في شاشة ال
	 * * Money Payment
	 */
	const CONSUMPTION_NEW = 'new';

	public static function getAllTypes()
	{
		return [
			self::RUNNING,
		];
	}
	public static function getConsumptionStatusesForSelect():array
	{
		return [
			['value'=>self::CONSUMPTION_EXISTING , 'title'=>__('Existing (already drawn — repayment only)')],
			['value'=>self::CONSUMPTION_NEW , 'title'=>__('New (not consumed yet — can pay suppliers)')],
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

	/* ------------------------------------------------------------------
	 * Medium Term Loan as a SOURCE OF PAYMENT
	 *
	 * Everything below exists so the loan can be picked as the paying
	 * account on the Money Payment screen, exactly like a Clean Overdraft.
	 * The contract these methods satisfy is the informal one every other
	 * payable account model already implements (findByAccountNumber,
	 * getAllAccountNumberForCurrency, getStatementTableName,
	 * getForeignKeyInStatementTable) — see
	 * MoneyReceivedController::updateNetBalanceBasedOnAccountNumber() and
	 * MoneyPaymentController::getAccountNumbersForAccountType(), both of
	 * which resolve the model dynamically from AccountType::model_name.
	 * ------------------------------------------------------------------ */

	public function getConsumptionStatus()
	{
		return $this->consumption_status ?: self::CONSUMPTION_EXISTING ;
	}
	/**
	 * * لسه ما اتصرفش .. يعني ينفع ندفع بيه فواتير
	 */
	public function isNotConsumedYet():bool
	{
		return $this->getConsumptionStatus() === self::CONSUMPTION_NEW ;
	}
	/**
	 * * ال
	 * * ActiveFinancialInstitutionAccountRule
	 * * بتنادي الميثود دي على اي موديل حساب بيتختار في شاشة الدفع.
	 * * القرض ماعندوش قفل زي الحسابات البنكية .. اللي يمنعه من الاستخدام
	 * * هو بس انه يكون اتصرف بالفعل .. فا دي حماية تانية على السيرفر لو
	 * * حد بعت قرض
	 * * existing
	 * * في الريكويست
	 */
	public function isActive():bool
	{
		return $this->isNotConsumedYet();
	}
	/**
	 * * ال
	 * * FinancialInstitution::getOpeningBalanceForAccount()
	 * * بتنادي الميثود دي على اي حساب مش
	 * * FinancialInstitutionAccount
	 * * علشان تعرف من امتى الحساب بقى شغال .. وبعدين الرولز بتمنع اي دفعة
	 * * بتاريخ اقدم من كده
	 * * (ReceivingOrPaymentDateRule / DateMustBeGreaterThanOrEqualDate).
	 *
	 * * التسهيلات التانية عندها عمود
	 * * contract_start_date
	 * * (شوف IsOverdraft) .. القرض متوسط الاجل المقابل بتاعه هو
	 * * start_date
	 * * وهو نفس المعنى بالظبط: من امتى التعاقد مع البنك على القرض ده بدأ.
	 */
	/**
	 * * الميثودز التلاتة دول بينادوا عليهم من
	 * * FinancialInstitution::getJournalIdForAccount() / getOdooIdForAccount()
	 * * على اي حساب بيتختار في شاشة الدفع .. وهي نفس اللي التسهيلات التانية
	 * * بتاخدها من
	 * @see \App\Traits\IsOverdraft
	 * * (مش بنعمل
	 * * use
	 * * للترايت لان باقيها -- سعر الفايدة والسحوبات وايام السداد -- ملهوش
	 * * معنى في قرض متوسط الاجل)
	 */
	public function getOdooCode():?string
	{
		return $this->odoo_code ;
	}
	public function getOdooId()
	{
		return $this->odoo_id ;
	}
	public function getJournalId()
	{
		return $this->journal_id ;
	}
	public function getFinancialInstitutionId()
	{
		return $this->financial_institution_id ;
	}
	public function getContractStartDate()
	{
		return $this->start_date ;
	}
	public function getContractStartDateFormatted()
	{
		$startDate = $this->getContractStartDate();
		return $startDate ? Carbon::make($startDate)->format('d-m-Y') : null ;
	}
	/**
	 * * ونهاية التعاقد مقابلها
	 * * end_date
	 * * — مضافة مع اختها علشان الاتنين بيتنادوا مع بعض في اماكن تانية
	 */
	public function getContractEndDate()
	{
		return $this->end_date ;
	}
	public function getContractEndDateFormatted()
	{
		$endDate = $this->getContractEndDate();
		return $endDate ? Carbon::make($endDate)->format('d-m-Y') : null ;
	}
	public function bankStatements():HasMany
	{
		return $this->hasMany(MediumTermLoanBankStatement::class,'medium_term_loan_id','id');
	}
	/**
	 * * هل اتسحب من القرض دا اي حاجه فعلا (يعني اتدفع بيه فاتورة)
	 * * لو ايوه يبقي مينفعش نرجع نغير ال
	 * * consumption_status
	 * * تاني لان دا هيخلي حركات موجودة بلا معنى
	 */
	public function hasDrawdowns():bool
	{
		return $this->bankStatements()->where('credit','>',0)->exists();
	}
	/**
	 * * الرو المتاح دلوقتي = الحد ناقص المسحوب
	 * * وبيزيد تاني كل ما يتسدد جزء من ال
	 * * principle
	 * * (قرار صاحب المشروع 2026-08-16)
	 */
	public function getAvailableRoomAt(?string $date = null)
	{
		if (! $this->isNotConsumedYet()) {
			return 0 ;
		}

		/**
		 * @var MediumTermLoanBankStatement|null $lastRow
		 */
		$lastRow = $this->bankStatements()
			->when($date, fn ($q) => $q->where('date','<=',$date))
			->orderByRaw('full_date desc , id desc')
			->first();

		/**
		 * * لسه مافيش اي حركة على القرض يبقي المتاح هو قيمة القرض كلها
		 */
		return $lastRow ? (float) $lastRow->getRoom() : (float) $this->getLimit() ;
	}
	public function getAvailableRoomAtFormatted(?string $date = null)
	{
		return number_format($this->getAvailableRoomAt($date));
	}
	/**
	 * * القروض اللي تنفع تتدفع منها فواتير: لازم تكون
	 * * new
	 * * وليها رقم حساب وبنفس العملة والبنك
	 */
	public static function getAllAccountNumberForCurrency($companyId , $currencyName , $financialInstitutionId , string $keyName = 'account_number'):array
	{
		return self::where('company_id',$companyId)
			->where('currency',$currencyName)
			->where('financial_institution_id',$financialInstitutionId)
			->where('consumption_status',self::CONSUMPTION_NEW)
			->whereNotNull('account_number')
			->where('account_number','!=','')
			->pluck('account_number',$keyName)
			->toArray();
	}
	public static function getAllAccountIdForCurrency($companyId , $currencyName , $financialInstitutionId):array
	{
		return self::getAllAccountNumberForCurrency($companyId,$currencyName,$financialInstitutionId,'id');
	}
	public static function findByAccountNumber($accountNumber , int $companyId , int $financialInstitutionId)
	{
		return self::where('company_id',$companyId)
			->where('account_number',$accountNumber)
			->where('financial_institution_id',$financialInstitutionId)
			->first();
	}
	public function getStatementTableName():string
	{
		return 'medium_term_loan_bank_statements';
	}
	public function getForeignKeyInStatementTable()
	{
		return 'medium_term_loan_id';
	}
	public function getAccountTypeId():int
	{
		return (int) (AccountType::onlyMediumTermLoan()->first()->id ?? 0);
	}
	public static function getTableNameFormatted()
	{
		return __('Medium Term Loan');
	}

	/* ------------------------------------------------------------------
	 * MTL Statement — الفايدة/الأصل: المستحق مقابل المدفوع
	 *
	 * المطلوب (صاحب المشروع 2026-08-16): "لازم نفصل بين الفايدة اللي انا
	 * كاتبها والفايدة اللي انا دافعها" — يعني القسط المكتوب في الجدول حاجة
	 * واللي اتدفع فعلا حاجة تانية، والاتنين لازم يبانوا.
	 *
	 * بيتحسب من جدول الأقساط + التسويات مباشرة (مش من حركات حساب القرض)
	 * علشان يشتغل برضه مع القروض ال
	 * existing
	 * اللي ماعندهاش حساب أصلا — هي بتتسدد أقساط زي أي قرض.
	 * ------------------------------------------------------------------ */

	/**
	 * * تفصيلة كل قسط: المستحق فايدة/أصل، والمدفوع من كل واحد، والمتبقي.
	 *
	 * @return array{rows: list<array<string,mixed>>, totals: array<string,float>}
	 */
	public function getRepaymentBreakdown():array
	{
		$schedules = $this->loanSchedules()
			->with('settlements')
			->orderBy('date')
			->orderBy('id')
			->get();

		$rows = [];
		$totals = [
			'interest_due'=>0.0 , 'interest_paid'=>0.0 , 'interest_remaining'=>0.0 ,
			'principle_due'=>0.0 , 'principle_paid'=>0.0 , 'principle_remaining'=>0.0 ,
			'installment_due'=>0.0 , 'installment_paid'=>0.0 , 'installment_remaining'=>0.0 ,
		];

		$number = 0 ;
		foreach ($schedules as $schedule) {
			/**
			 * @var LoanSchedule $schedule
			 */
			$interestDue  = (float) $schedule->getInterestAmount();
			$principleDue = (float) $schedule->getPrincipleAmount();
			$installment  = (float) $schedule->getSchedulePayment();

			/**
			 * * الصفوف اللي ملهاش قسط (لو الاكسل فيه سطور فاضية) ما تتعدش
			 */
			if ($installment <= 0 && $interestDue <= 0 && $principleDue <= 0) {
				continue ;
			}
			$number++ ;

			$paid = (float) $schedule->settlements->sum('amount');
			/**
			 * * نفس قاعدة التوزيع بالظبط اللي بتتطبق وقت السداد: الفايدة الاول
			 * @see LoanScheduleSettlement::interestPaidFor()
			 * @see LoanScheduleSettlement::principlePaidFor()
			 */
			$interestPaid  = LoanScheduleSettlement::interestPaidFor($paid,$interestDue);
			$princiblePaid = LoanScheduleSettlement::principlePaidFor($paid,$interestDue,$principleDue);

			$rows[] = [
				'number'=>$number ,
				'date_formatted'=>$schedule->getDateFormatted() ,
				'raw_date'=>$schedule->getDate() ,
				'status'=>$schedule->getStatusFormatted() ,
				'installment_due'=>$installment ,
				'installment_paid'=>$paid ,
				'installment_remaining'=>max(0,$installment - $paid) ,
				'interest_due'=>$interestDue ,
				'interest_paid'=>$interestPaid ,
				'interest_remaining'=>max(0,$interestDue - $interestPaid) ,
				'principle_due'=>$principleDue ,
				'principle_paid'=>$princiblePaid ,
				'principle_remaining'=>max(0,$principleDue - $princiblePaid) ,
			];

			$totals['interest_due'] += $interestDue ;
			$totals['interest_paid'] += $interestPaid ;
			$totals['interest_remaining'] += max(0,$interestDue - $interestPaid) ;
			$totals['principle_due'] += $principleDue ;
			$totals['principle_paid'] += $princiblePaid ;
			$totals['principle_remaining'] += max(0,$principleDue - $princiblePaid) ;
			$totals['installment_due'] += $installment ;
			$totals['installment_paid'] += $paid ;
			$totals['installment_remaining'] += max(0,$installment - $paid) ;
		}

		return ['rows'=>$rows , 'totals'=>$totals];
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
