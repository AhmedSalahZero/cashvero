<?php

namespace App\Models;

use App\Support\StatementCascade;

use App\Helpers\HDate;
use App\Traits\IsBankStatement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;


/**
 * @property int $id
 * @property int $is_active
 * @property int $company_id
 * @property int $overdraft_against_commercial_paper_id
 * @property int $cheque_id
 * @property string $full_date هو عباره عن تاريخ ال actual_collection_date if exist , else  , due_date
 * @property numeric $limit
 * @property numeric $accumulated_limit
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Cheque|null $cheque
 * @property-read \App\Models\OverdraftAgainstCommercialPaper|null $overdraftAgainstCommercialPaper
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperLimit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperLimit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperLimit query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperLimit whereAccumulatedLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperLimit whereChequeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperLimit whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperLimit whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperLimit whereFullDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperLimit whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperLimit whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperLimit whereLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperLimit whereOverdraftAgainstCommercialPaperId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperLimit whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class OverdraftAgainstCommercialPaperLimit extends Model
{
	// use IsBankStatement;
	protected $table ='overdraft_against_commercial_paper_limits';
	
	protected $guarded =[
		'id'
	];
	public $oldFullDate = null;
	
	public function cheque():BelongsTo
	{
		return $this->belongsTo(Cheque::class , 'cheque_id','id');
	}
	public static function updateNextRows(self $model):string {
		$minDate  =min($model->full_date,$model->getRawOriginal('full_date')) ?: $model->full_date ;
		;
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

		 StatementCascade::touchRows(
			DB::table($tableName)
		->where('full_date','>=',$minDate)
		->where('overdraft_against_commercial_paper_id',$model->overdraft_against_commercial_paper_id),
			'full_date asc  , id asc'
		);
		return $minDate;
	}
		public function getChequeActualCollectionOrDepositDate()
		{
			if($this->cheque->isCollected()){
				return $this->cheque->chequeActualCollectionDate();
			}
			elseif($this->cheque->isRejected()){
				return $this->cheque->getDueDate();
			}
			return $this->cheque->getDepositDate();
		}
		public function updateFullDate()
		{
				$this->created_at = now();
				$date = $this->getChequeActualCollectionOrDepositDate()  ;
				$time  = now()->format('H:i:s');
				$fullDateTime = date('Y-m-d H:i:s', strtotime("$date $time")) ;
				/**
				 * * دي علشان لو ليهم نفس التاريخ والوقت بالظبط يزود ثانيه علي التاريخ القديم
				 */
				$fullDateTime = HDate::generateUniqueDateTimeForModel(self::class,'full_date',$fullDateTime,[
					[
						'overdraft_against_commercial_paper_id','=',$this->overdraft_against_commercial_paper_id ,
					]
				]) ;
				$this->full_date = $fullDateTime;
				return $this->full_date ;
		}
		/**
		 * * خاصة فقط بالجداول اللي ليها جدول لحساب الليمت بشكل منفصل
		 */
		protected static function updateBankStatement(self $overdraftAgainstCommercialPaperLimit){
			
			$firstBankStatementRow = $overdraftAgainstCommercialPaperLimit
			->overdraftAgainstCommercialPaper
			->overdraftAgainstCommercialPaperBankStatements
			->where('full_date','>=',$overdraftAgainstCommercialPaperLimit->full_date)
			->sortBy('full_date')
			->first();
			/**
			 * @var OverdraftAgainstCommercialPaperBankStatement|null $firstBankStatementRow ;
			 */
			 $firstBankStatementRow ? $firstBankStatementRow->update(['updated_at'=>now()]) : null ;
			 
			 
		}
		protected static function booted(): void
		{
			static::creating(function(self $model){
				$model->updateFullDate();
			});
			
			static::created(function(self $model){
				self::updateNextRows($model);
				self::updateBankStatement($model);
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
					// $oldBankStatementId=$model->getRawOriginal('id');
					// لو ما لقناش اول واحد فوقه هندور علي اول واحد بعدة					
					// $firstBankStatementForOldOverdraftAgainstCommercialPaper = self::where('overdraft_against_commercial_paper_id',$oldOverdraftAgainstCommercialPaperId)->where('id','!=',$oldBankStatementId)->orderBy('id')->first()  ;
					// لو كانت القديمة دي قبل ما تتغير هي الاستيتم الوحيده بعد كدا انت غيرتها بالتالي الحساب القديم دا معتش ليه لزمة فا هنحذف كل السحبات و التسديدات بتاعته
				
						DB::table($tableName)
						->where('full_date','>=',$minDate)
						->orderByRaw('full_date asc , id asc')
						->where('overdraft_against_commercial_paper_id',$oldOverdraftAgainstCommercialPaperId)->update([
							'updated_at'=>now()
						]);
						
					
					
				}
				
				
				self::updateBankStatement($model);
				
				
			});
			
			static::deleting(function(self $overdraftAgainstCommercialPaperLimit){
				$oldDate = null ;

				if($overdraftAgainstCommercialPaperLimit->cheque_id
				// && Request('receiving_date')||$overdraftAgainstCommercialPaperLimit->is_credit && Request('delivery_date')
				){
						$oldDate =$overdraftAgainstCommercialPaperLimit->getChequeActualCollectionOrDepositDate();
			
						$time  = now()->format('H:i:s');
						$oldDate = date('Y-m-d H:i:s', strtotime("$oldDate $time")) ;
						$currentDate = $overdraftAgainstCommercialPaperLimit->full_date ;
						$overdraftAgainstCommercialPaperLimit->full_date = min($oldDate,$currentDate);
				}
				DB::table('overdraft_against_commercial_papers')->where('id',$overdraftAgainstCommercialPaperLimit->overdraft_against_commercial_paper_id)->update([
					'oldest_date'=>$overdraftAgainstCommercialPaperLimit->full_date
				]);
	
				// $overdraftAgainstCommercialPaperLimit->limit = -1;
				// $overdraftAgainstCommercialPaperLimit->accumulated_limit = 0;
				$overdraftAgainstCommercialPaperLimit->save();
				Cheque::deleteLimitUpdateRowFromStatement($overdraftAgainstCommercialPaperLimit);
				
			});
		}
		
	
	public function overdraftAgainstCommercialPaper()
	{
		return $this->belongsTo(OverdraftAgainstCommercialPaper::class,'overdraft_against_commercial_paper_id','id');
	}
	public function getId()
	{
		return $this->id ;
	}
	public function getForeignKeyNamesThatUsedInFilter():array 
	{
		return [
			'overdraft_against_commercial_paper_id',
		];
	}	
	
}
