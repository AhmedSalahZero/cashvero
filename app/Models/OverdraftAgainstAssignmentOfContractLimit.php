<?php

namespace App\Models;

use App\Helpers\HDate;
use App\Traits\IsBankStatement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;


/**
 * @property int $id
 * @property int $is_active
 * @property int $company_id
 * @property int $overdraft_against_assignment_of_contract_id
 * @property int $contract_id
 * @property string $full_date هو عباره عن تاريخ ال actual_collection_date if exist , else  , due_date
 * @property numeric $limit
 * @property numeric $accumulated_limit
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Contract|null $contract
 * @property-read \App\Models\OverdraftAgainstAssignmentOfContract|null $overdraftAgainstAssignmentOfContract
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContractLimit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContractLimit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContractLimit query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContractLimit whereAccumulatedLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContractLimit whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContractLimit whereContractId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContractLimit whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContractLimit whereFullDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContractLimit whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContractLimit whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContractLimit whereLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContractLimit whereOverdraftAgainstAssignmentOfContractId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContractLimit whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class OverdraftAgainstAssignmentOfContractLimit extends Model
{
	// use IsBankStatement;
	protected $table ='overdraft_against_assignment_of_contract_limits';
	
	protected $guarded =[
		'id'
	];
	public $oldFullDate = null;
	
	public static function updateNextRows(self $model):string {
		$minDate  =min($model->full_date,$model->getRawOriginal('full_date')) ?: $model->full_date ;
		;
		DB::table('overdraft_against_assignment_of_contracts')->where('id',$model->overdraft_against_assignment_of_contract_id)->update([
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
		->where('full_date','>=',$minDate)
		->orderByRaw('full_date asc  , id asc')
		->where('overdraft_against_assignment_of_contract_id',$model->overdraft_against_assignment_of_contract_id)
		->each(function($odAgainstAssignmentOfContractLimit) use($tableName){
			DB::table($tableName)->where('id',$odAgainstAssignmentOfContractLimit->id)->update([
				'updated_at'=>now(),
			]);
		});
		return $minDate;
	}
		public function getLimitFullDate()
		{
			return DB::table('lending_information_against_assignment_of_contracts')->where('overdraft_against_assignment_of_contract_id',$this->overdraft_against_assignment_of_contract_id)
			->where('contract_id',$this->contract_id)->first()->assignment_date;
		
		}
		// public function getChequeActualCollectionOrDepositDate()
		// {
		// 	if($this->cheque->isCollected()){
		// 		return $this->cheque->chequeActualCollectionDate();
		// 	}
		// 	elseif($this->cheque->isRejected()){
		// 		return $this->cheque->getDueDate();
		// 	}
		// 	return $this->cheque->getDepositDate();
		// }
		public function updateFullDate()
		{
				$this->created_at = now();
				$date = $this->getLimitFullDate()  ;
				$time  = now()->format('H:i:s');
				$fullDateTime = date('Y-m-d H:i:s', strtotime("$date $time")) ;
				/**
				 * * دي علشان لو ليهم نفس التاريخ والوقت بالظبط يزود ثانيه علي التاريخ القديم
				 */
				$fullDateTime = HDate::generateUniqueDateTimeForModel(self::class,'full_date',$fullDateTime,[
					[
						'overdraft_against_assignment_of_contract_id','=',$this->overdraft_against_assignment_of_contract_id ,
					]
				]) ;
				$this->full_date = $fullDateTime;
				return $this->full_date ;
		}
		/**
		 * * خاصة فقط بالجداول اللي ليها جدول لحساب الليمت بشكل منفصل
		 */
		protected static function updateBankStatement(self $overdraftAgainstAssignmentOfContractLimit){
			$firstBankStatementRow = $overdraftAgainstAssignmentOfContractLimit
			->overdraftAgainstAssignmentOfContract
			->overdraftAgainstAssignmentOfContractBankStatements
			->where('full_date','>=',$overdraftAgainstAssignmentOfContractLimit->full_date)
			->sortBy('full_date')
			->first();

			
			/**
			 * @var ?OverdraftAgainstAssignmentOfContractBankStatement $firstBankStatementRow ;
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
				
				
				$isChanged = $model->isDirty('overdraft_against_assignment_of_contract_id') ;
				/**
				 * * دي علشان لو غيرت ال
				 * * overdraft_against_assignment_of_contract_id
				 * * بمعني انه نقل السحبة مثلا من حساب الي حساب اخر .. يبقي هنحتاج نشغل الترجرز علشان الحساب القديم علشان يوزع تاني
				 */
	
				if($isChanged){
					$oldOverdraftAgainstAssignmentOfContractId=$model->getRawOriginal('overdraft_against_assignment_of_contract_id');
					// $oldBankStatementId=$model->getRawOriginal('id');
					// لو ما لقناش اول واحد فوقه هندور علي اول واحد بعدة					
					// $firstBankStatementForOldOverdraftAgainstAssignmentOfContract = self::where('overdraft_against_assignment_of_contract_id',$oldOverdraftAgainstAssignmentOfContractId)->where('id','!=',$oldBankStatementId)->orderBy('id')->first()  ;
					// لو كانت القديمة دي قبل ما تتغير هي الاستيتم الوحيده بعد كدا انت غيرتها بالتالي الحساب القديم دا معتش ليه لزمة فا هنحذف كل السحبات و التسديدات بتاعته
				
						DB::table($tableName)
						->where('full_date','>=',$minDate)
						->orderByRaw('full_date asc , id asc')
						->where('overdraft_against_assignment_of_contract_id',$oldOverdraftAgainstAssignmentOfContractId)->update([
							'updated_at'=>now()
						]);
						
					
					
				}
				self::updateBankStatement($model);
				
			});
			
			static::deleting(function(self $odAgainstAssignmentOfContractLimit){
				$oldDate = null ;

				if($odAgainstAssignmentOfContractLimit->contract_id
				// && Request('receiving_date')||$odAgainstAssignmentOfContractLimit->is_credit && Request('delivery_date')
				){
						$oldDate =$odAgainstAssignmentOfContractLimit->getLimitFullDate();
			
						$time  = now()->format('H:i:s');
						$oldDate = date('Y-m-d H:i:s', strtotime("$oldDate $time")) ;
						$currentDate = $odAgainstAssignmentOfContractLimit->full_date ;
						$odAgainstAssignmentOfContractLimit->full_date = min($oldDate,$currentDate);
				}
				DB::table('overdraft_against_assignment_of_contracts')->where('id',$odAgainstAssignmentOfContractLimit->overdraft_against_assignment_of_contract_id)->update([
					'oldest_date'=>$odAgainstAssignmentOfContractLimit->full_date
				]);
	
				// $odAgainstAssignmentOfContractLimit->limit = -1;
				// $odAgainstAssignmentOfContractLimit->accumulated_limit = 0;
				$odAgainstAssignmentOfContractLimit->save();
				
			});
		}
		
	public function contract()
	{
		return $this->belongsTo(Contract::class,'contract_id','id');
	}
	public function overdraftAgainstAssignmentOfContract()
	{
		return $this->belongsTo(OverdraftAgainstAssignmentOfContract::class,'overdraft_against_assignment_of_contract_id','id');
	}
	public function getId()
	{
		return $this->id ;
	}
	public function getForeignKeyNamesThatUsedInFilter():array 
	{
		return [
			'overdraft_against_assignment_of_contract_id',
		];
	}	
	
}
