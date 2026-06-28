<?php

namespace App\Models;

use App\Helpers\HDate;
use App\Traits\IsBankStatement;
use App\Traits\Models\HasDeleteButTriggerChangeOnLastElement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string|null $type
 * @property int|null $branch_id
 * @property string|null $currency
 * @property numeric $exchange_rate
 * @property int $is_debit
 * @property int $is_credit
 * @property int $company_id
 * @property int $money_received_id
 * @property int|null $money_payment_id
 * @property int|null $cash_expense_id
 * @property int|null $buy_or_sell_currency_id
 * @property int|null $internal_money_transfer_id
 * @property int|null $opening_balance_id
 * @property string|null $date
 * @property string|null $full_date
 * @property numeric $beginning_balance
 * @property numeric|null $debit
 * @property numeric|null $credit
 * @property numeric $end_balance
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Branch|null $branch
 * @property-read \App\Models\CashExpense|null $cashExpense
 * @property-read \App\Models\OpeningBalance|null $cashInSafes
 * @property-read \App\Models\InternalMoneyTransfer|null $internalMoneyTransfer
 * @property-read \App\Models\MoneyPayment|null $moneyPayment
 * @property-read \App\Models\MoneyReceived|null $moneyReceived
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafeStatement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafeStatement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafeStatement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafeStatement whereBeginningBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafeStatement whereBranchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafeStatement whereBuyOrSellCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafeStatement whereCashExpenseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafeStatement whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafeStatement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafeStatement whereCredit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafeStatement whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafeStatement whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafeStatement whereDebit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafeStatement whereEndBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafeStatement whereExchangeRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafeStatement whereFullDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafeStatement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafeStatement whereInternalMoneyTransferId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafeStatement whereIsCredit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafeStatement whereIsDebit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafeStatement whereMoneyPaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafeStatement whereMoneyReceivedId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafeStatement whereOpeningBalanceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafeStatement whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInSafeStatement whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CashInSafeStatement extends Model
{
	use HasDeleteButTriggerChangeOnLastElement,IsBankStatement ;
	const MONEY_TRANSFER  = 'money-transfer';
	
    protected $guarded = [
        'id'
    ];

	public static function updateNextRows(CashInSafeStatement $model):string 
	{
		$minDate  = $model->date ;
		// $minDate  = $model->full_date ;
	
		
		/**
		 * * ليه بنستخدم ال 
		 * * min date
		 * * علشان لو عدلنا العنصر الحالي وخلينا التاريخ بتاعه اكبر من التاريخ القديم وقتها العناصر اللي ما بين التاريخ مش هيتعدلوا
		 * * مع انهم كان مفروض يتعدلوا بس انت قولتله عدلي العناصر اللي التاريخ بتاعها اكبر من او يساوي التاريخ الجديد
		 * * ودا غلط مفروض التاريخ الاقل ما بين التاريخ الجديد و القديم للعنصر بحيث دايما يبدا يحدث من عنده
		 */

		 DB::table('cash_in_safe_statements')
		->where('date','>=',$minDate)
		->orderByRaw('date asc , id asc')
		->where('branch_id',$model->branch_id)
		->each(function($cashInSafeStatement){
			DB::table('cash_in_safe_statements')->where('id',$cashInSafeStatement->id)->update([
				'updated_at'=>now()
			]);
		});
		
		return $minDate;

	}
		protected static function booted(): void
		{
			static::creating(function(CashInSafeStatement $model){
	
				$model->created_at = now();
				$date = $model->date ;
				$time  = now()->format('H:i:s');
				$row = DB::table('temp_deleted_statements')->where('company_id',$model->company_id)->where('table_name','cash_in_safe_statements')->first();
				
				if($row){
					$model->id = $row->deleted_id;
					DB::table('temp_deleted_statements')->where('company_id',$model->company_id)->where('table_name','cash_in_safe_statements')->delete();
				}
			
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
			
			static::created(function(CashInSafeStatement $model){
				self::updateNextRows($model);
			});
			
			static::updated(function (CashInSafeStatement $model) {
				
				$minDate = self::updateNextRows($model);
				
				
				$isChanged = $model->isDirty('branch_id') ;
				/**
				 * * دي علشان لو غيرت ال
				 * * branch_id
				 * * بمعني انه نقل السحبة مثلا من حساب الي حساب اخر .. يبقي هنحتاج نشغل الترجرز علشان الحساب القديم علشان يوزع تاني
				 */
				if($isChanged){
					$oldAccountIdId=$model->getRawOriginal('branch_id');
					$oldBankStatementId=$model->getRawOriginal('id');
					// لو ما لقناش اول واحد فوقه هندور علي اول واحد بعدة					
					$firstBankStatementForOld = CashInSafeStatement::where('branch_id',$oldAccountIdId)->where('id','!=',$oldBankStatementId)->orderBy('id')->first()  ;
					// لو كانت القديمة دي قبل ما تتغير هي الاستيتم الوحيده بعد كدا انت غيرتها بالتالي الحساب القديم دا معتش ليه لزمة فا هنحذف كل السحبات و التسديدات بتاعته
					if(!$firstBankStatementForOld){
						// وتلقائي هيحذف السحوبات settlements
					}else{
						DB::table('cash_in_safe_statements')
						->where('date','>=',$minDate)
						->orderByRaw('date asc , id asc')
						->where('branch_id',$model->branch_id)->update([
							'updated_at'=>now()
						]);
						
					}
					
				}
				
			});
			
			static::deleting(function(CashInSafeStatement $cashInSafeStatement){
				$oldDate = null ;
				if($cashInSafeStatement->is_debit && Request('receiving_date')||$cashInSafeStatement->is_credit && Request('delivery_date')){
						$oldDate = Carbon::make(Request('receiving_date',Request('delivery_date')))->format('Y-m-d');
						$time  = now()->format('H:i:s');
						$oldDate = date('Y-m-d H:i:s', strtotime("$oldDate $time")) ;
						$currentDate = $cashInSafeStatement->full_date ;
						$cashInSafeStatement->full_date = min($oldDate,$currentDate);
				}
			
				
				$cashInSafeStatement->debit = 0;
				$cashInSafeStatement->credit = 0;
				$cashInSafeStatement->save();
				
			});
		}

    public function moneyReceived()
    {
        return $this->belongsTo(MoneyReceived::class, 'money_received_id', 'id');
    }
	public function moneyPayment()
    {
        return $this->belongsTo(MoneyPayment::class, 'money_payment_id', 'id');
    }
	public function cashExpense()
		{
			return $this->belongsTo(CashExpense::class, 'cash_expense_id', 'id');
		}

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
	public function getDebitAmount()
	{
		return $this->debit ?: 0 ;
	}
	public function getCurrency()
	{
		return $this->currency ; 
	}
	public function cashInSafes()
	{
		return $this->belongsTo(OpeningBalance::class,'opening_balance_id','id') ;
	}
	public function getExchangeRate()
	{
		
		return $this->exchange_rate ?:1 ;
	}
	public function internalMoneyTransfer()
	{
		return $this->belongsTo(InternalMoneyTransfer::class,'internal_money_transfer_id','id');
	}
	public function branch()
	{
		return $this->belongsTo(Branch::class,'branch_id','id');
	}
	
	public function getBranchId():int 
	{
		return $this->branch_id?:0 ;
	}
	/**
	 * * دول اسماء العواميد اللي بنفرق باستخدامهم بين كل رو والتاني في الحسابات في التريجر
	 * * يعني مثلا لما اجي اجيب العنصر اللي قبلي هجيبه بناء علي انهي شروط 
	 */
	public function getForeignKeyNamesThatUsedInFilter():array 
	{
		return [
			'financial_institution_account_id'
		];
	}
	public static function getCurrencies(int $companyId,array $exceptArr):array 
	{
		return self::where('company_id',$companyId)->whereNotIn('currency',$exceptArr)->pluck('currency')->toArray();
	}
}
