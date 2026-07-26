<?php

namespace App\Models;

use App\Support\StatementCascade;

use App\Helpers\HDate;
use App\Traits\IsBankStatement;
use App\Traits\Models\HasDeleteButTriggerChangeOnLastElement;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;


/**
 * @property int $id
 * @property string $type وليكن مثلا beginning_balance,incoming_transfer,cheque_payment  , etc
 * @property int $is_debit
 * @property int $is_credit
 * @property int $priority عباره عن اولويه التسديد بمعني لما يحين وقت التسديد مين هيتسدد الاول لان الفؤائد بتسدد الاول
 * @property int $fully_secured_overdraft_id
 * @property int $money_received_id
 * @property int|null $money_payment_id
 * @property int|null $cash_expense_id
 * @property int|null $internal_money_transfer_id
 * @property int|null $buy_or_sell_currency_id
 * @property int $company_id
 * @property string $date
 * @property numeric $limit
 * @property numeric $beginning_balance
 * @property numeric|null $debit
 * @property numeric|null $credit
 * @property numeric $end_balance
 * @property numeric $room
 * @property string $interest_type الفايدة اما بتنزل بعد كل سحبة او ايداع او بتنزل بشكل اوتوماتك اخر كل شهر
 * @property numeric $interest_rate_annually
 * @property numeric $interest_rate_daily
 * @property int $days_count
 * @property numeric $interest_amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $full_date دا هنستخدمة علشان نرتب بيه ونجيب ال الرو السابق بناء علي التاريخ و الوقت
 * @property string|null $comment_en
 * @property string|null $comment_ar
 * @property string|null $outstanding_withdrawal_date
 * @property-read \App\Models\CashExpense|null $cashExpense
 * @property-read \App\Models\FullySecuredOverdraft|null $fullySecuredOverdraft
 * @property-read \App\Models\MoneyPayment|null $moneyPayment
 * @property-read \App\Models\MoneyReceived|null $moneyReceived
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\FullySecuredOverdraftWithdrawal> $withdrawals
 * @property-read int|null $withdrawals_count
 * @property-read bool|null $withdrawals_exists
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement whereBeginningBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement whereBuyOrSellCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement whereCashExpenseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement whereCommentAr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement whereCommentEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement whereCredit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement whereDaysCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement whereDebit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement whereEndBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement whereFullDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement whereFullySecuredOverdraftId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement whereInterestAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement whereInterestRateAnnually($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement whereInterestRateDaily($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement whereInterestType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement whereInternalMoneyTransferId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement whereIsCredit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement whereIsDebit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement whereLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement whereMoneyPaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement whereMoneyReceivedId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement whereOutstandingWithdrawalDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement whereRoom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftBankStatement whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class FullySecuredOverdraftBankStatement extends Model
{
	use IsBankStatement,HasDeleteButTriggerChangeOnLastElement;
	protected $guarded =[
		'id'
	];
	
	const MONEY_TRANSFER  = 'money-transfer';
	public $oldFullDate = null;
	public static function updateNextRows(self $model):string 
	{
		$minDate  = $model->date ;
		
		DB::table('fully_secured_overdrafts')->where('id',$model->fully_secured_overdraft_id)->update([
			'oldest_date'=>$minDate,
		]);
		
		/**
		 * * ليه بنستخدم ال 
		 * * min date
		 * * علشان لو عدلنا العنصر الحالي وخلينا التاريخ بتاعه اكبر من التاريخ القديم وقتها العناصر اللي ما بين التاريخ مش هيتعدلوا
		 * * مع انهم كان مفروض يتعدلوا بس انت قولتله عدلي العناصر اللي التاريخ بتاعها اكبر من او يساوي التاريخ الجديد
		 * * ودا غلط مفروض التاريخ الاقل ما بين التاريخ الجديد و القديم للعنصر بحيث دايما يبدا يحدث من عنده
		 */
		$tableName = (new self)->getTable();
		 StatementCascade::touchRows(
			DB::table($tableName)
		->where('date','>=',$minDate)
		->where('fully_secured_overdraft_id',$model->fully_secured_overdraft_id),
			'date asc , priority asc , id asc'
		);
		
		return $minDate;

	}
		protected static function booted(): void
		{
			static::creating(function(self $model){
				$model->created_at = now();
				$date = $model->date ;
				$time  = now()->format('H:i:s');
				$fullDateTime = date('Y-m-d H:i:s', strtotime("$date $time")) ;
				
				
				$row = DB::table('temp_deleted_statements')->where('company_id',$model->company_id)->where('table_name','fully_secured_overdraft_bank_statements')->first();
				
				if($row){
					$model->id = $row->deleted_id;
					DB::table('temp_deleted_statements')->where('company_id',$model->company_id)->where('table_name','fully_secured_overdraft_bank_statements')->delete();
				}
				
				/**
				 * * دي علشان لو ليهم نفس التاريخ والوقت بالظبط يزود ثانيه علي التاريخ القديم
				 */
				$fullDateTime = HDate::generateUniqueDateTimeForModel(self::class,'full_date',$fullDateTime,[
					[
						'fully_secured_overdraft_id','=',$model->fully_secured_overdraft_id ,
					]
				]) ;
				$model->full_date = $fullDateTime;
			});
			
			static::created(function(self $model){
				self::updateNextRows($model);
			});
			
			static::updated(function (self $model) {
				
				$minDate = self::updateNextRows($model);
				
				
				$isChanged = $model->isDirty('fully_secured_overdraft_id') ;
				/**
				 * * دي علشان لو غيرت ال
				 * * fully_secured_overdraft_id
				 * * بمعني انه نقل السحبة مثلا من حساب الي حساب اخر .. يبقي هنحتاج نشغل الترجرز علشان الحساب القديم علشان يوزع تاني
				 */
				if($isChanged){
					$oldFullySecuredOverdraftId=$model->getRawOriginal('fully_secured_overdraft_id');
					$oldBankStatementId=$model->getRawOriginal('id');
					// لو ما لقناش اول واحد فوقه هندور علي اول واحد بعدة					
					$firstBankStatementForOldFullySecuredOverdraft = self::where('fully_secured_overdraft_id',$oldFullySecuredOverdraftId)->where('id','!=',$oldBankStatementId)->orderBy('id')->first()  ;
					// لو كانت القديمة دي قبل ما تتغير هي الاستيتم الوحيده بعد كدا انت غيرتها بالتالي الحساب القديم دا معتش ليه لزمة فا هنحذف كل السحبات و التسديدات بتاعته
					if(!$firstBankStatementForOldFullySecuredOverdraft){
						FullySecuredOverdraftWithdrawal::where('fully_secured_overdraft_id',$oldFullySecuredOverdraftId)->delete();
						// وتلقائي هيحذف السحوبات settlements
					}else{
						DB::table((new self)->getTable())
						->where('full_date','>=',$minDate)
						->orderByRaw('full_date asc , priority asc , id asc')
						->where('fully_secured_overdraft_id',$model->fully_secured_overdraft_id)->update([
							'updated_at'=>now()
						]);
						
					}
					
				}
				
			});
			
			static::deleting(function(self $fullySecuredOverdraftBankStatement){
				$oldDate = null ;
				if($fullySecuredOverdraftBankStatement->is_debit && Request('receiving_date')||$fullySecuredOverdraftBankStatement->is_credit && Request('delivery_date')){
						$oldDate = Carbon::make(Request('receiving_date',Request('delivery_date')))->format('Y-m-d');
						// $time  = now()->format('H:i:s');
						// $oldDate = date('Y-m-d H:i:s', strtotime("$oldDate $time")) ;
						$currentDate = $fullySecuredOverdraftBankStatement->date ;
						$fullySecuredOverdraftBankStatement->date = min($oldDate,$currentDate);
				}
				DB::table('fully_secured_overdrafts')->where('id',$fullySecuredOverdraftBankStatement->fully_secured_overdraft_id)->update([
					'oldest_date'=>$fullySecuredOverdraftBankStatement->date,
					// 'origin_update_row_is_debit'=>$fullySecuredOverdraftBankStatement->is_debit
				]);
				
				$fullySecuredOverdraftBankStatement->debit = 0;
				$fullySecuredOverdraftBankStatement->credit = 0;
				$fullySecuredOverdraftBankStatement->save();
				
			});
		}
		
	public function moneyReceived()
	{
		return $this->belongsTo(MoneyReceived::class,'money_received_id','id');
	}
	public function moneyPayment()
	{
		return $this->belongsTo(MoneyPayment::class,'money_payment_id','id');
	}
	public function cashExpense()
	{
		return $this->belongsTo(CashExpense::class,'cash_expense_id','id');
	}
	public function withdrawals()
	{
		return $this->hasMany(FullySecuredOverdraftWithdrawal::class,'fully_secured_overdraft_bank_statement_id','id');
	}
	public function fullySecuredOverdraft()
	{
		return $this->belongsTo(FullySecuredOverdraft::class,'fully_secured_overdraft_id','id');
	}
	public function getId()
	{
		return $this->id ;
	}
	public function setDateAttribute($value)
	{
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['date'] =  $value ;
			return ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		
		$this->attributes['date'] = $year.'-'.$month.'-'.$day;
	}
	public function getForeignKeyNamesThatUsedInFilter():array 
	{
		return [
			'fully_secured_overdraft_id'
		];
	}		
	
}
