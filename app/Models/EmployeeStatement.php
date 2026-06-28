<?php

namespace App\Models;

use App\Helpers\HDate;
use App\Interfaces\Models\Interfaces\IHaveStatement;
use App\Traits\IsBankStatement;
use App\Traits\Models\HasDeleteButTriggerChangeOnLastElement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int $company_id
 * @property string $currency_name
 * @property int $is_debit
 * @property int $is_credit
 * @property string|null $date
 * @property int $partner_id
 * @property int|null $money_received_id
 * @property int|null $money_payment_id
 * @property string|null $full_date
 * @property numeric $beginning_balance
 * @property numeric|null $debit
 * @property numeric|null $credit
 * @property numeric $end_balance
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $comment_en
 * @property string|null $comment_ar
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\EmployeeStatement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\EmployeeStatement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\EmployeeStatement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\EmployeeStatement whereBeginningBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\EmployeeStatement whereCommentAr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\EmployeeStatement whereCommentEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\EmployeeStatement whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\EmployeeStatement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\EmployeeStatement whereCredit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\EmployeeStatement whereCurrencyName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\EmployeeStatement whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\EmployeeStatement whereDebit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\EmployeeStatement whereEndBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\EmployeeStatement whereFullDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\EmployeeStatement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\EmployeeStatement whereIsCredit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\EmployeeStatement whereIsDebit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\EmployeeStatement whereMoneyPaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\EmployeeStatement whereMoneyReceivedId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\EmployeeStatement wherePartnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\EmployeeStatement whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class EmployeeStatement extends Model  implements IHaveStatement
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
		$minDate  = $model->full_date ;
	
		
		/**
		 * * ليه بنستخدم ال 
		 * * min date
		 * * علشان لو عدلنا العنصر الحالي وخلينا التاريخ بتاعه اكبر من التاريخ القديم وقتها العناصر اللي ما بين التاريخ مش هيتعدلوا
		 * * مع انهم كان مفروض يتعدلوا بس انت قولتله عدلي العناصر اللي التاريخ بتاعها اكبر من او يساوي التاريخ الجديد
		 * * ودا غلط مفروض التاريخ الاقل ما بين التاريخ الجديد و القديم للعنصر بحيث دايما يبدا يحدث من عنده
		 */

		 DB::table('employee_statements')
		->where('full_date','>=',$minDate)
		->orderByRaw('full_date asc , id asc')
		// ->where('financial_institution_account_id',$model->financial_institution_account_id)
		->each(function($employeeStatement){
			DB::table('employee_statements')->where('id',$employeeStatement->id)->update([
				'updated_at'=>now()
			]);
		});
		
		return $minDate;

	}
	
		protected static function booted(): void
		{
			static::creating(function(self $model){
				$model->created_at = now();
				$date = $model->date ;
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
				
				
				// $isChanged = $model->isDirty('financial_institution_account_id') ;
				/**
				 * * دي علشان لو غيرت ال
				 * * financial_institution_account_id
				 * * بمعني انه نقل السحبة مثلا من حساب الي حساب اخر .. يبقي هنحتاج نشغل الترجرز علشان الحساب القديم علشان يوزع تاني
				 */
				// if($isChanged){
				// 	$oldAccountIdId=$model->getRawOriginal('financial_institution_account_id');
				// 	$oldBankStatementId=$model->getRawOriginal('id');
				// 	// لو ما لقناش اول واحد فوقه هندور علي اول واحد بعدة					
				// 	$firstBankStatementForOld = self::where('financial_institution_account_id',$oldAccountIdId)->where('id','!=',$oldBankStatementId)->orderBy('id')->first()  ;
				// 	// لو كانت القديمة دي قبل ما تتغير هي الاستيتم الوحيده بعد كدا انت غيرتها بالتالي الحساب القديم دا معتش ليه لزمة فا هنحذف كل السحبات و التسديدات بتاعته
				// 	if(!$firstBankStatementForOld){
				// 		// وتلقائي هيحذف السحوبات settlements
				// 	}else{
				// 		DB::table('employee_statements')
				// 		->where('full_date','>=',$minDate)
				// 		->orderByRaw('full_date asc , id asc')
				// 		->where('financial_institution_account_id',$model->financial_institution_account_id)->update([
				// 			'updated_at'=>now()
				// 		]);
						
				// 	}
					
				// }
				
			});
			
			static::deleting(function(self $employeeStatement){
				// $oldDate = null ;
				// if($employeeStatement->is_debit && Request('receiving_date')||$employeeStatement->is_credit && Request('delivery_date')){
				// 		$oldDate = Carbon::make(Request('receiving_date',Request('delivery_date')))->format('Y-m-d');
				// 		$time  = now()->format('H:i:s');
				// 		$oldDate = date('Y-m-d H:i:s', strtotime("$oldDate $time")) ;
				// 		$currentDate = $employeeStatement->full_date ;
				// 		$employeeStatement->full_date = min($oldDate,$currentDate);
				// }
			
				
				$employeeStatement->debit = 0;
				$employeeStatement->credit = 0;
				$employeeStatement->save();
				
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
	public function getForeignKeyNamesThatUsedInFilter():array 
	{
		return [
			'currency_name',
			'partner_id'
		];
	}
	
}
