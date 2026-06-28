<?php

namespace App\Models;

use App\Helpers\HDate;
use App\Models\LcSettlementInternalMoneyTransfer;
use App\Traits\IsBankStatement;
use App\Traits\Models\HasDeleteButTriggerChangeOnLastElement;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;


/**
 * @property int $id
 * @property string $source
 * @property string $type وليكن مثلا beginning_balance,incoming_transfer,cheque_payment  , etc
 * @property int $is_debit
 * @property int $is_credit
 * @property int $priority عباره عن اولويه التسديد بمعني لما يحين وقت التسديد مين هيتسدد الاول لان الفؤائد بتسدد الاول
 * @property int|null $lc_facility_id
 * @property int $lc_issuance_id
 * @property int|null $lc_settlement_internal_money_transfer_id
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
 * @property string|null $outstanding_withdrawal_date
 * @property string|null $comment_en
 * @property string|null $comment_ar
 * @property-read \App\Models\LetterOfCreditIssuance|null $lcIssuance
 * @property-read \App\Models\LcOverdraftBankStatement|null $lcOverdraftCreditBankStatement
 * @property-read \App\Models\LcSettlementInternalMoneyTransfer|null $lcSettlementInternalMoneyTransfer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LcOverdraftWithdrawal> $withdrawals
 * @property-read int|null $withdrawals_count
 * @property-read bool|null $withdrawals_exists
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftBankStatement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftBankStatement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftBankStatement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftBankStatement whereBeginningBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftBankStatement whereCommentAr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftBankStatement whereCommentEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftBankStatement whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftBankStatement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftBankStatement whereCredit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftBankStatement whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftBankStatement whereDaysCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftBankStatement whereDebit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftBankStatement whereEndBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftBankStatement whereFullDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftBankStatement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftBankStatement whereInterestAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftBankStatement whereInterestRateAnnually($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftBankStatement whereInterestRateDaily($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftBankStatement whereInterestType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftBankStatement whereIsCredit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftBankStatement whereIsDebit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftBankStatement whereLcFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftBankStatement whereLcIssuanceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftBankStatement whereLcSettlementInternalMoneyTransferId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftBankStatement whereLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftBankStatement whereOutstandingWithdrawalDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftBankStatement wherePriority($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftBankStatement whereRoom($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftBankStatement whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftBankStatement whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftBankStatement whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LcOverdraftBankStatement extends Model
{
	use HasDeleteButTriggerChangeOnLastElement,IsBankStatement ;
	protected $guarded =[
		'id'
	];
	const LC_OVERDRAFT_MONEY_TRANSFER  = 'lc-overdraft-money-transfer';
	public static function getSources()
	{
		return [
			'lc-facility'=>__('LC Facility')
		];
	}
	public $oldFullDate = null;
	public static function updateNextRows(self $model):string 
	{
		$minDate  = $model->full_date ;
		DB::table('letter_of_credit_facilities')->where('id',$model->lc_facility_id)->update([
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
		->where($tableName.'.lc_facility_id',$model->lc_facility_id)
		->each(function($lcOverdraftBankStatement) use($tableName){
			DB::table($tableName)->where('id',$lcOverdraftBankStatement->id)->update([
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
				
				$row = DB::table('temp_deleted_statements')->where('company_id',$model->company_id)->where('table_name','lc_overdraft_bank_statements')->first();
				
				if($row){
					$model->id = $row->deleted_id;
					DB::table('temp_deleted_statements')->where('company_id',$model->company_id)->where('table_name','lc_overdraft_bank_statements')->delete();
				}
				
				/**
				 * * دي علشان لو ليهم نفس التاريخ والوقت بالظبط يزود ثانيه علي التاريخ القديم
				 */
				$fullDateTime = HDate::generateUniqueDateTimeForModel(self::class,'full_date',$fullDateTime,[
					[
						'lc_facility_id','=',$model->lc_facility_id ,
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
				$isChanged = $model->isDirty('lc_facility_id') ;
				/**
				 * * دي علشان لو غيرت ال
				 * * lc_facility_id
				 * * بمعني انه نقل السحبة مثلا من حساب الي حساب اخر .. يبقي هنحتاج نشغل الترجرز علشان الحساب القديم علشان يوزع تاني
				 */
				if($isChanged){
					$oldLcOverdraftId=$model->getRawOriginal('lc_facility_id');
					$oldBankStatementId=$model->getRawOriginal('id');
					// لو ما لقناش اول واحد فوقه هندور علي اول واحد بعدة					
					$firstBankStatementForOldLcOverdraft = self::where('lc_facility_id',$oldLcOverdraftId)->where('id','!=',$oldBankStatementId)->orderBy('id')->first()  ;
					// لو كانت القديمة دي قبل ما تتغير هي الاستيتم الوحيده بعد كدا انت غيرتها بالتالي الحساب القديم دا معتش ليه لزمة فا هنحذف كل السحبات و التسديدات بتاعته
					if(!$firstBankStatementForOldLcOverdraft){
						LcOverdraftWithdrawal::where('lc_facility_id',$oldLcOverdraftId)->delete();
						// وتلقائي هيحذف السحوبات settlements
					}else{
						DB::table($tableName)
						->where('date','>=',$minDate)
						->orderByRaw('date asc , priority asc , id asc')
						->where('lc_facility_id',$model->lc_facility_id)->update([
							'updated_at'=>now()
						]);
						
					}
					
				}
				
			});
			
			static::deleting(function(self $lcOverdraftBankStatement){
				$oldDate = null ;

				
				if($lcOverdraftBankStatement->is_debit && Request('receiving_date')||$lcOverdraftBankStatement->is_credit && Request('delivery_date')){
						$oldDate = Carbon::make(Request('receiving_date',Request('delivery_date')))->format('Y-m-d');
						// $time  = now()->format('H:i:s');
						// $oldDate = date('Y-m-d H:i:s', strtotime("$oldDate $time")) ;
						$currentDate = $lcOverdraftBankStatement->date ;
						$lcOverdraftBankStatement->date = min($oldDate,$currentDate);
				}
				DB::table('letter_of_credit_facilities')->where('id',$lcOverdraftBankStatement->lc_facility_id)->update([
					'oldest_date'=>$lcOverdraftBankStatement->date,
					// 'origin_update_row_is_debit'=>$lcOverdraftBankStatement->is_debit
				]);
				
				$lcOverdraftBankStatement->debit = 0;
				$lcOverdraftBankStatement->credit = 0;
				
				$lcOverdraftBankStatement->save();
				
			});
		}
		

	public function withdrawals()
	{
		return $this->hasMany(LcOverdraftWithdrawal::class,'lc_overdraft_bank_statement_id','id');
	}
	public function lcIssuance()
	{
		return $this->belongsTo(LetterOfCreditIssuance::class,'lc_issuance_id','id');
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

	
	public function lcSettlementInternalMoneyTransfer()
	{
		return $this->belongsTo(LcSettlementInternalMoneyTransfer::class,'lc_settlement_internal_money_transfer_id','id');
	}
	public function lcOverdraftCreditBankStatement()
	{
		return $this->hasOne(LcOverdraftBankStatement::class,'money_payment_id','lc_issuance_id');
	}

	public function getForeignKeyNamesThatUsedInFilter():array 
	{
		return [
			'lc_facility_id'
		];
	}	
	
	
}
