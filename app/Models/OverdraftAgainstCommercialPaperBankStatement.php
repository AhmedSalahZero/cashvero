<?php

namespace App\Models;

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
 * @property int $overdraft_against_commercial_paper_id
 * @property int $money_received_id
 * @property int|null $money_payment_id
 * @property int|null $cash_expense_id
 * @property int|null $internal_money_transfer_id
 * @property int|null $buy_or_sell_currency_id
 * @property int $company_id
 * @property int|null $overdraft_against_commercial_paper_limit_id
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
 * @property-read \App\Models\InternalMoneyTransfer|null $internalMoneyTransfer
 * @property-read \App\Models\MoneyPayment|null $moneyPayment
 * @property-read \App\Models\MoneyReceived|null $moneyReceived
 * @property-read \App\Models\OverdraftAgainstCommercialPaper|null $overdraftAgainstCommercialPaper
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OverdraftAgainstCommercialPaperWithdrawal> $withdrawals
 * @property-read int|null $withdrawals_count
 * @property-read bool|null $withdrawals_exists
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement whereBeginningBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement whereBuyOrSellCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement whereCashExpenseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement whereCommentAr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement whereCommentEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement whereCredit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement whereDaysCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement whereDebit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement whereEndBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement whereFullDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement whereInterestAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement whereInterestRateAnnually($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement whereInterestRateDaily($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement whereInterestType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement whereInternalMoneyTransferId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement whereIsCredit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement whereIsDebit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement whereLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement whereMoneyPaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement whereMoneyReceivedId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement whereOutstandingWithdrawalDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement whereOverdraftAgainstCommercialPaperId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement whereOverdraftAgainstCommercialPaperLimitId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement whereRoom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperBankStatement whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class OverdraftAgainstCommercialPaperBankStatement extends Model
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
		DB::table('overdraft_against_commercial_papers')->where('id',$model->overdraft_against_commercial_paper_id)->update([
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
		 DB::table($tableName)
		->where('date','>=',$minDate)
		->orderByRaw('date asc , priority asc , id asc')
		->where('overdraft_against_commercial_paper_id',$model->overdraft_against_commercial_paper_id)
		->each(function($overdraftAgainstCommercialPaperBankStatement) use($tableName){
			DB::table($tableName)->where('id',$overdraftAgainstCommercialPaperBankStatement->id) 
			->update([
				'updated_at'=>now(),
				// 'credit'=>0 
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
				
				$row = DB::table('temp_deleted_statements')->where('company_id',$model->company_id)->where('table_name','overdraft_against_commercial_paper_bank_statements')->first();
				
				if($row){
					$model->id = $row->deleted_id;
					DB::table('temp_deleted_statements')->where('company_id',$model->company_id)->where('table_name','overdraft_against_commercial_paper_bank_statements')->delete();
				}
				
				/**
				 * * دي علشان لو ليهم نفس التاريخ والوقت بالظبط يزود ثانيه علي التاريخ القديم
				 */
				$fullDateTime = HDate::generateUniqueDateTimeForModel(self::class,'full_date',$fullDateTime,[
					[
						'overdraft_against_commercial_paper_id','=',$model->overdraft_against_commercial_paper_id ,
					]
				]) ;
				$model->full_date = $fullDateTime;
			});
			
			static::created(function(self $model){
				self::updateNextRows($model);
			});
			
			static::updated(function (self $model) {
				$tableName = (new self)->getTable();
				$minDate = self::updateNextRows($model);
				
				
				$isChanged = $model->isDirty('overdraft_against_commercial_paper_id') ;
				/**
				 * * دي علشان لو غيرت ال
				 * * overdraft_against_commercial_paper_id
				 * * بمعني انه نقل السحبة مثلا من حساب الي حساب اخر .. يبقي هنحتاج نشغل الترجرز علشان الحساب القديم علشان يوزع تاني
				 */
				if($isChanged){
					$oldOverdraftAgainstCommercialPaperId=$model->getRawOriginal('overdraft_against_commercial_paper_id');
					$oldBankStatementId=$model->getRawOriginal('id');
					// لو ما لقناش اول واحد فوقه هندور علي اول واحد بعدة					
					$firstBankStatementForOldOverdraftAgainstCommercialPaper = self::where('overdraft_against_commercial_paper_id',$oldOverdraftAgainstCommercialPaperId)->where('id','!=',$oldBankStatementId)->orderBy('id')->first()  ;
					// لو كانت القديمة دي قبل ما تتغير هي الاستيتم الوحيده بعد كدا انت غيرتها بالتالي الحساب القديم دا معتش ليه لزمة فا هنحذف كل السحبات و التسديدات بتاعته
					if(!$firstBankStatementForOldOverdraftAgainstCommercialPaper){
						OverdraftAgainstCommercialPaperWithdrawal::where('overdraft_against_commercial_paper_id',$oldOverdraftAgainstCommercialPaperId)->delete();
						// وتلقائي هيحذف السحوبات settlements
					}else{
						DB::table($tableName)
						->where('date','>=',$minDate)
						->orderByRaw('date asc , priority asc , id asc')
						->where('overdraft_against_commercial_paper_id',$model->overdraft_against_commercial_paper_id)->update([
							'updated_at'=>now()
						]);
						
					}
					
				}
				
			});
			
			static::deleting(function(self $overdraftAgainstCommercialPaperBankStatement){
				$oldDate = null ;
				if($overdraftAgainstCommercialPaperBankStatement->is_debit && Request('receiving_date')||$overdraftAgainstCommercialPaperBankStatement->is_credit && Request('delivery_date')){
						$oldDate = Carbon::make(Request('receiving_date',Request('delivery_date')))->format('Y-m-d');
						// $time  = now()->format('H:i:s');
						// $oldDate = date('Y-m-d H:i:s', strtotime("$oldDate $time")) ;
						$currentDate = $overdraftAgainstCommercialPaperBankStatement->date ;
						$overdraftAgainstCommercialPaperBankStatement->date = min($oldDate,$currentDate);
				}
				DB::table('overdraft_against_commercial_papers')->where('id',$overdraftAgainstCommercialPaperBankStatement->overdraft_against_commercial_paper_id)->update([
					'oldest_date'=>$overdraftAgainstCommercialPaperBankStatement->full_date
				]);
				
				$overdraftAgainstCommercialPaperBankStatement->debit = 0;
				$overdraftAgainstCommercialPaperBankStatement->credit = 0;
				$overdraftAgainstCommercialPaperBankStatement->save();
				
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
		return $this->hasMany(OverdraftAgainstCommercialPaperWithdrawal::class,'overdraft_against_commercial_paper_bank_statement_id','id');
	}
	public function overdraftAgainstCommercialPaper()
	{
		return $this->belongsTo(OverdraftAgainstCommercialPaper::class,'overdraft_against_commercial_paper_id','id');
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
	
	public function internalMoneyTransfer()
	{
		return $this->belongsTo(InternalMoneyTransfer::class,'internal_money_transfer_id','id');
	}
	public function getForeignKeyNamesThatUsedInFilter():array 
	{
		return [
			'overdraft_against_commercial_paper_id',
		];
	}	
		
}
