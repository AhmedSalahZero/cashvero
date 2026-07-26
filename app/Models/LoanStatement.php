<?php

namespace App\Models;

use App\Support\StatementCascade;

use App\Helpers\HDate;
use App\Interfaces\Models\Interfaces\IHaveStatement;
use App\Traits\IsBankStatement;
use App\Traits\Models\HasDeleteButTriggerChangeOnLastElement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $financial_institution_account_id
 * @property int $company_id
 * @property int|null $loan_schedule_settlement_id
 * @property int $is_debit
 * @property int $is_credit
 * @property string|null $date
 * @property string|null $full_date
 * @property numeric $beginning_balance
 * @property numeric|null $debit
 * @property numeric|null $credit
 * @property numeric $end_balance
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $comment_en
 * @property string|null $comment_ar
 * @property-read \App\Models\LoanStatement|null $financialInstitutionAccount
 * @property-read \App\Models\LoanScheduleSettlement|null $loanScheduleSettlement
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanStatement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanStatement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanStatement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanStatement whereBeginningBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanStatement whereCommentAr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanStatement whereCommentEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanStatement whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanStatement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanStatement whereCredit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanStatement whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanStatement whereDebit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanStatement whereEndBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanStatement whereFinancialInstitutionAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanStatement whereFullDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanStatement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanStatement whereIsCredit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanStatement whereIsDebit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanStatement whereLoanScheduleSettlementId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LoanStatement whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LoanStatement extends Model  implements IHaveStatement
{
	use HasDeleteButTriggerChangeOnLastElement,IsBankStatement;
    protected $guarded = [
        'id'
    ];
	
	/**
	 * * ال 
	 * * global scope 
	 * * دا خاص بس بجزئيه ال
	 * * commission 
	 * * ما عدا ذالك ملهوش اي لزمة هو والكولوم اللي اسمة
	 * * is_active
	 */
	protected static function boot()
    {
        parent::boot();
        // static::addGlobalScope('only_active',function(Builder $builder){
		// 	$builder->where('is_active',1); 
		// });

    }
	

	public static function updateNextRows(self $model):string 
	{
		$minDate  = $model->date ;
	
		
		/**
		 * * ليه بنستخدم ال 
		 * * min date
		 * * علشان لو عدلنا العنصر الحالي وخلينا التاريخ بتاعه اكبر من التاريخ القديم وقتها العناصر اللي ما بين التاريخ مش هيتعدلوا
		 * * مع انهم كان مفروض يتعدلوا بس انت قولتله عدلي العناصر اللي التاريخ بتاعها اكبر من او يساوي التاريخ الجديد
		 * * ودا غلط مفروض التاريخ الاقل ما بين التاريخ الجديد و القديم للعنصر بحيث دايما يبدا يحدث من عنده
		 */

		 StatementCascade::touchRows(
			DB::table('loan_statements')
		->where('date','>=',$minDate)
		->where('financial_institution_account_id',$model->financial_institution_account_id),
			'date asc , id asc'
		);
		
		return $minDate;

	}
	
		protected static function booted(): void
		{
			static::creating(function(self $model){
				$model->created_at = now();
				$date = $model->date ;
				
				$row = DB::table('temp_deleted_statements')->where('company_id',$model->company_id)->where('table_name','loan_statements')->first();
				
				if($row){
					$model->id = $row->deleted_id;
					DB::table('temp_deleted_statements')->where('company_id',$model->company_id)->where('table_name','loan_statements')->delete();
				}
				
				$time  = now()->format('H:i:s');
				
				$fullDateTime = date('Y-m-d H:i:s', strtotime("$date $time")) ;
				/**
				 * * دي علشان لو ليهم نفس التاريخ والوقت بالظبط يزود ثانيه علي التاريخ القديم
				 */
				$fullDateTime = HDate::generateUniqueDateTimeForModel(self::class,'full_date',$fullDateTime,[
					[
						'company_id','=',$model->company_id ,
					]
				]) ;
				$model->full_date = $fullDateTime;
			});
			
			static::created(function(self $model){
				self::updateNextRows($model);
			});
			
			static::updated(function (self $model) {
				
				$minDate = self::updateNextRows($model);
				
				
				$isChanged = $model->isDirty('financial_institution_account_id') ;
				/**
				 * * دي علشان لو غيرت ال
				 * * financial_institution_account_id
				 * * بمعني انه نقل السحبة مثلا من حساب الي حساب اخر .. يبقي هنحتاج نشغل الترجرز علشان الحساب القديم علشان يوزع تاني
				 */
				if($isChanged){
					$oldAccountIdId=$model->getRawOriginal('financial_institution_account_id');
					$oldBankStatementId=$model->getRawOriginal('id');
					// لو ما لقناش اول واحد فوقه هندور علي اول واحد بعدة					
					$firstBankStatementForOld = self::where('financial_institution_account_id',$oldAccountIdId)->where('id','!=',$oldBankStatementId)->orderBy('id')->first()  ;
					// لو كانت القديمة دي قبل ما تتغير هي الاستيتم الوحيده بعد كدا انت غيرتها بالتالي الحساب القديم دا معتش ليه لزمة فا هنحذف كل السحبات و التسديدات بتاعته
					if(!$firstBankStatementForOld){
						// وتلقائي هيحذف السحوبات settlements
					}else{
						DB::table('loan_statements')
						->where('date','>=',$minDate)
						->orderByRaw('date asc , id asc')
						->where('financial_institution_account_id',$model->financial_institution_account_id)->update([
							'updated_at'=>now()
						]);
						
					}
					
				}
				
			});
			
			static::deleting(function(self $loanStatement){
				// $oldDate = null ;
				// if($loanStatement->is_debit && Request('receiving_date')||$loanStatement->is_credit && Request('delivery_date')){
				// 		$oldDate = Carbon::make(Request('receiving_date',Request('delivery_date')))->format('Y-m-d');
				// 		$time  = now()->format('H:i:s');
				// 		$oldDate = date('Y-m-d H:i:s', strtotime("$oldDate $time")) ;
				// 		$currentDate = $loanStatement->full_date ;
				// 		$loanStatement->full_date = min($oldDate,$currentDate);
				// }
			
				
				$loanStatement->debit = 0;
				$loanStatement->credit = 0;
				$loanStatement->save();
				
			});
		}
		

    // public function moneyReceived()
    // {
    //     return $this->belongsTo(MoneyReceived::class, 'money_received_id', 'id');
    // }
	// public function certificateOfDeposit()
    // {
    //     return $this->belongsTo(CertificatesOfDeposit::class, 'certificate_of_deposit_id', 'id');
    // }
	// public function timeOfDeposit()
    // {
    //     return $this->belongsTo(TimeOfDeposit::class, 'time_of_deposit_id', 'id');
    // }
	// public function letterOfGuaranteeIssuance()
    // {
    //     return $this->belongsTo(LetterOfGuaranteeIssuance::class, 'letter_of_guarantee_issuance_id', 'id');
    // }
	// public function moneyPayment()
    // {
    //     return $this->belongsTo(MoneyPayment::class, 'money_payment_id', 'id');
    // }
	// public function cashExpense()
    // {
    //     return $this->belongsTo(CashExpense::class, 'cash_expense_id', 'id');
    // }
    public function getId()
    {
        return $this->id ;
    }
	
	public function getEndBalance()
	{
		return $this->end_balance ?: 0 ;
	}
	public function getEndBalanceFormatted()
	{
		return number_format($this->getEndBalance()) ;
	}

    public function setDateAttribute($value)
    {
        $date = explode('/', $value);
        if (count($date) != 3) {
            $this->attributes['date'] = $value ;

            return ;
        }
        $month = $date[0];
        $day = $date[1];
        $year = $date[2];

        $this->attributes['date'] = $year . '-' . $month . '-' . $day;
    }
	public function financialInstitutionAccount()
	{
		return $this->belongsTo(self::class,'financial_institution_account_id','id');
	}
	public function loanScheduleSettlement():BelongsTo
	{
		return $this->belongsTo(LoanScheduleSettlement::class,'loan_schedule_settlement_id','id');
	}	
	public function getForeignKeyNamesThatUsedInFilter():array 
	{
	
		return [
			'financial_institution_account_id',
		];
	}	
	
}
