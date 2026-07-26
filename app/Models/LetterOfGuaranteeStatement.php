<?php

namespace App\Models;

use App\Support\StatementCascade;

use App\Enums\LgTypes;
use App\Helpers\HDate;
use App\Traits\IsBankStatement;
use App\Traits\Models\HasDeleteButTriggerChangeOnLastElement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string|null $type
 * @property string $source هو المكان او الطريقه يعني اللي انت انشاتة بيها وانت عندك ثلاث او اربع زراير دول عباره عن المصدر اللي هو قيمة الكولوم دا
 * @property int $cd_or_td_id
 * @property int $financial_institution_id
 * @property int $letter_of_guarantee_issuance_id
 * @property int|null $lg_facility_id
 * @property string $lg_type
 * @property int|null $lg_advanced_payment_history_id
 * @property string|null $currency
 * @property int $is_debit
 * @property int $is_credit
 * @property int $company_id
 * @property string|null $date
 * @property string|null $full_date
 * @property numeric $beginning_balance
 * @property numeric|null $debit
 * @property numeric|null $credit
 * @property numeric $end_balance
 * @property string|null $comment_ar
 * @property string|null $comment_en
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\CashExpense|null $cashExpense
 * @property-read \App\Models\MoneyPayment|null $moneyPayment
 * @property-read \App\Models\MoneyReceived|null $moneyReceived
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeStatement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeStatement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeStatement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeStatement whereBeginningBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeStatement whereCdOrTdId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeStatement whereCommentAr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeStatement whereCommentEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeStatement whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeStatement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeStatement whereCredit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeStatement whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeStatement whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeStatement whereDebit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeStatement whereEndBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeStatement whereFinancialInstitutionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeStatement whereFullDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeStatement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeStatement whereIsCredit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeStatement whereIsDebit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeStatement whereLetterOfGuaranteeIssuanceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeStatement whereLgAdvancedPaymentHistoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeStatement whereLgFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeStatement whereLgType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeStatement whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeStatement whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeStatement whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LetterOfGuaranteeStatement extends Model
{
	use HasDeleteButTriggerChangeOnLastElement,IsBankStatement;
    protected $guarded = [
        'id'
    ];
	public static function generateIssuanceComment(string $lang,string $customerName,?string $transactionName,?string $lgCode)
	{
		$transactionName = is_null($transactionName) ? '-' : $transactionName ;
		$lgCode = is_null($lgCode) ? '-' : $lgCode ;
		return __('Issuance [ :customerName ] [ :transactionName ] [ :lgCode ]',['customerName'=>$customerName ,'transactionName'=>$transactionName ,'lgCode'=>$lgCode],$lang);
	}
	public static function generateCancelComment(string $lang,string $lgType,string $customerName,?string $transactionName,?string $lgCode)
	{
		$transactionName = is_null($transactionName) ? '-' : $transactionName ;
		$lgCode = is_null($lgCode) ? '-' : $lgCode ;
		return __('Canceled [ :customerName ] [ :lgType ] [ :transactionName ] [ :lgCode ]',['customerName'=>$customerName ,'lgType'=>$lgType ,'transactionName'=>$transactionName ,'lgCode'=>$lgCode],$lang);
	}
	public static function generateAdvancedPaymentLgComment(string $lang,string $customerName,?string $transactionName,?string $lgCode)
	{
		$transactionName = is_null($transactionName) ? '-' : $transactionName ;
		$lgCode = is_null($lgCode) ? '-' : $lgCode ;
		return __('Decreased Amount [ :customerName ] [ :transactionName ] [ :lgCode ]',['customerName'=>$customerName ,'transactionName'=>$transactionName ,'lgCode'=>$lgCode],$lang);
	}
	public static function updateNextRows(LetterOfGuaranteeStatement $model):string 
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
			DB::table((new self)->getTable())
		->where('date','>=',$minDate)
		->where('financial_institution_id',$model->financial_institution_id)
		->where('source',$model->source)
		->where('lg_facility_id',$model->lg_facility_id)
		->where('cd_or_td_id',$model->cd_or_td_id)
		->where('lg_type',$model->lg_type),
			'date asc , id asc'
		);
		
		return $minDate;

	}
	
		protected static function booted(): void
		{
			static::creating(function(LetterOfGuaranteeStatement $model){
				$model->created_at = now();
				$date = $model->date ;
				$time  = now()->format('H:i:s');
				$row = DB::table('temp_deleted_statements')->where('company_id',$model->company_id)->where('table_name','letter_of_guarantee_statements')->first();
				
				if($row){
					$model->id = $row->deleted_id;
					DB::table('temp_deleted_statements')->where('company_id',$model->company_id)->where('table_name','letter_of_guarantee_statements')->delete();
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
			
			static::created(function(LetterOfGuaranteeStatement $model){
				self::updateNextRows($model);
			});
			
			static::updated(function (LetterOfGuaranteeStatement $model) {
				
				$minDate = self::updateNextRows($model);
				
				
				$lgFacilityIsChanged = $model->isDirty('lg_facility_id') ;
				$lgCdOrTdIdIsChanged = $model->isDirty('cd_or_td_id') ;
				$financialInstitutionIsChanged = $model->isDirty('financial_institution_id') ;
				$sourceIsChanged = $model->isDirty('source') ;
				$lgTypeIsChange = $model->isDirty('lg_type') ;
		// 		->where('financial_institution_id',$model->financial_institution_id)
		// ->where('source',$model->source)
		
				/**
				 * * دي علشان لو غيرت ال
				 * * lg_facility_id
				 * * بمعني انه نقل السحبة مثلا من حساب الي حساب اخر .. يبقي هنحتاج نشغل الترجرز علشان الحساب القديم علشان يوزع تاني
				 */
				if($lgFacilityIsChanged ||$lgTypeIsChange || $financialInstitutionIsChanged || $sourceIsChanged || $lgCdOrTdIdIsChanged ){
					$oldLgFacilityId=$model->getRawOriginal('lg_facility_id');
					$oldSource=$model->getRawOriginal('source');
					$oldCdOrTdId=$model->getRawOriginal('cd_or_td_id');
					$financialInstitutionId=$model->getRawOriginal('financial_institution_id');
					$oldLgType=$model->getRawOriginal('lg_type');
					$oldStatementId =$model->getRawOriginal('id');
					// لو ما لقناش اول واحد فوقه هندور علي اول واحد بعدة					
					$firstBankStatementForOld = LetterOfGuaranteeStatement::
					where('financial_institution_id',$financialInstitutionId)->
					where('lg_facility_id',$oldLgFacilityId)->
					where('cd_or_td_id',$oldCdOrTdId)->
					where('source',$oldSource)->
					
					where('lg_type',$oldLgType)
					->where('id','!=',$oldStatementId )->orderBy('id')->first()  ;
					// لو كانت القديمة دي قبل ما تتغير هي الاستيتم الوحيده بعد كدا انت غيرتها بالتالي الحساب القديم دا معتش ليه لزمة فا هنحذف كل السحبات و التسديدات بتاعته
					if(!$firstBankStatementForOld){
						// وتلقائي هيحذف السحوبات settlements
					}else{
						DB::table((new self)->getTable())
						->where('date','>=',$minDate)
						->orderByRaw('date asc , id asc')
						->where('lg_facility_id',$model->lg_facility_id)
						->where('cd_or_td_id',$model->cd_or_td_id)
						->where('lg_type',$model->lg_type)
						->where('financial_institution_id',$model->financial_institution_id)
						->where('source',$model->source)
						
						->update([
							'updated_at'=>now()
						]);
						
					}
					
				}
				
			});
			
			static::deleting(function(LetterOfGuaranteeStatement $letterOfGuaranteeStatement){
				$oldDate = null ;
				if($letterOfGuaranteeStatement->is_debit && Request('cancellation_date')||$letterOfGuaranteeStatement->is_credit && Request('issuance_date')){
						$oldDate = Carbon::make(Request('cancellation_date',Request('issuance_date')))->format('Y-m-d');
						// $time  = now()->format('H:i:s');
						// $oldDate = date('Y-m-d H:i:s', strtotime("$oldDate $time")) ;
						$currentDate = $letterOfGuaranteeStatement->date ;
						$letterOfGuaranteeStatement->date = min($oldDate,$currentDate);
				}
				$letterOfGuaranteeStatement->debit = 0;
				$letterOfGuaranteeStatement->credit = 0;
				$letterOfGuaranteeStatement->save();
				
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
	public function getLetterOfGuaranteeIssuance()
	{
		return $this->belongsTo(LetterOfGuaranteeIssuance::class,'letter_of_guarantee_issuance_id','id');
	} 
	public function getLetterOfGuaranteeFacility()
	{
		return $this->belongsTo(LetterOfGuaranteeIssuance::class,'lg_facility_id','id');
	} 
	public static function getTotalOutstandingBalanceForAllTypes(int $lgFacilityId , int $companyId , int $financialInstitutionId,string $currencyName):float 
	{
		$totalLastOutstandingBalanceOfFourTypes = 0 ;
		foreach(LgTypes::getAll() as $lgTypeId => $lgTypeNameFormatted){	
			foreach(LetterOfGuaranteeIssuance::lgSources() as $currentSourceId => $currentSourceTitle){
				if($currentSourceId != LetterOfGuaranteeIssuance::LG_FACILITY ){
					continue ;
				}
				$letterOfGuaranteeStatement = DB::table((new self)->getTable())
					->where('company_id',$companyId)
					->where('financial_institution_id',$financialInstitutionId)
					->where('lg_facility_id',$lgFacilityId)
					->where('currency',$currencyName)
					->where('lg_type',$lgTypeId)
					->where('source',$currentSourceId)
					->orderByRaw('date desc,id desc')
					->first();
					$letterOfGuaranteeStatementEndBalance = $letterOfGuaranteeStatement ? $letterOfGuaranteeStatement->end_balance : 0 ;
					
					$totalLastOutstandingBalanceOfFourTypes += $letterOfGuaranteeStatementEndBalance;
			}
			
		}
		return abs($totalLastOutstandingBalanceOfFourTypes) ; 
	}
	public static function getDashboardDataForFinancialInstitution(int $companyId , int $financialInstitutionId,string $currencyName,string $lgType)
	{
			$letterOfGuaranteeStatement = DB::table((new self)->getTable())
			->where('company_id',$companyId)
			->where('financial_institution_id',$financialInstitutionId)
			->where('currency',$currencyName)
			->where('lg_type',$lgType)
			->orderByRaw('date desc,id desc')
			->first();
			$letterOfGuaranteeStatementEndBalance = $letterOfGuaranteeStatement ? $letterOfGuaranteeStatement->end_balance : 0 ;
			return abs($letterOfGuaranteeStatementEndBalance);
	} 
	public static function getDashboardOutstandingPerTypeFormattedData(array &$charts , Company $company  , string $currencyName , string $date , string $lgTypeId , ?string $source , $selectedFinancialInstitutionBankIds ):void
	{
		$lgTitle = LgTypes::getAll()[$lgTypeId] ;
		$sources = $source ? [$source => $source] : LetterOfGuaranteeIssuance::lgSources();
		
		$totalEndBalance = 0 ;
		foreach($sources as $source => $sourceTitle){
			foreach($selectedFinancialInstitutionBankIds as $currentFinancialInstitutionId){
				$rowPerType  = DB::table((new self)->getTable())
						->where('company_id',$company->id )
						->where('financial_institution_id',$currentFinancialInstitutionId)
						->where('currency',$currencyName)
						->where('date','<=',$date)
						->where('lg_type',$lgTypeId)
						->when($source , function(Builder $builder) use ($source){
							$builder->where('source',$source);
						})
						->orderByRaw('date desc,id desc')
						->first();
						if(!$rowPerType){
							continue;
						}
						$totalEndBalance += abs($rowPerType->end_balance) ;
					}
		}
		
				$charts['outstanding_per_lg_type'][$currencyName][] = ['type'=>$lgTitle , 'outstanding'=>$totalEndBalance] ;
	}
	public static function getDashboardOutstandingPerFinancialInstitutionFormattedData(array &$charts , Company $company  , string $currencyName , string $date , int $financialInstitutionId , string $financialInstitutionName , ?string $source , $lgTypes):void
	{
		$sources = $source ? [$source => $source] : LetterOfGuaranteeIssuance::lgSources();
		$totalEndBalance = 0 ;
		foreach($sources as $currentSourceId => $sourceTitle){
		foreach($lgTypes as $currentLgTypeId => $currentLgTypeTitle){
		$rowPerType  = DB::table((new self)->getTable())
		->where('company_id',$company->id )
		->where('financial_institution_id',$financialInstitutionId)
		->where('currency',$currencyName)
		->where('date','<=',$date)
		->where('source',$currentSourceId)
		->where('lg_type',$currentLgTypeId)
		->orderByRaw('date desc,id desc')
		->first();
		
		if(!$rowPerType){
			continue;
		}
		$totalEndBalance += abs($rowPerType->end_balance) ;
		}
		}
		$charts['lg_outstanding_per_financial_institution'][$currencyName][] = ['type'=>$financialInstitutionName , 'outstanding'=>$totalEndBalance] ;
	}
	public static function getDashboardOutstandingTableFormattedData(array &$tablesData , Company $company  , string $currencyName , string $date , int $financialInstitutionId,string $lgTypeId , string $financialInstitutionName ,  $lastLetterOfGuaranteeFacility , ?string $source ):void
	{
		$allSources = $source ? [$source => $source] : LetterOfGuaranteeIssuance::lgSources() ; ;
		foreach($allSources as $currentSourceId => $currentSourceTitle){
			$rowPerType  = DB::table((new self)->getTable())
				->where('company_id',$company->id )
				->where('financial_institution_id',$financialInstitutionId)
				->where('currency',$currencyName)
				->where('date','<=',$date)
				->where('source',$currentSourceId)
				->where('lg_type',$lgTypeId)
				->orderByRaw('date desc,id desc')
				->first();
				if(!$rowPerType){continue ;}
				$currentOutstandingBalance = abs($rowPerType->end_balance) ;
				$currentLimit = $lastLetterOfGuaranteeFacility ? $lastLetterOfGuaranteeFacility->limit : 0 ;
				$tablesData['lg_outstanding_for_table'][$currencyName][] = ['financial_institution_name'=>$financialInstitutionName , 'outstanding'=>$currentOutstandingBalance , 'source'=>LetterOfGuaranteeIssuance::lgSources()[$rowPerType->source] , 'type'=>LgTypes::getAll()[$rowPerType->lg_type] , 'limit'=>$currentLimit , 'cash_cover'=>LetterOfGuaranteeStatement::getTotalCashCoverForAllTypes($lastLetterOfGuaranteeFacility->id,$company->id,$financialInstitutionId,$currencyName,$lgTypeId,$currentSourceId)] ;
			
		}
		}
	public static function getTotalCashCoverForAllTypes(int $lgFacilityId, int $companyId , int $financialInstitutionId,string $currency , ?string $type = null , ?string $source = null):float 
	{
		$totalLastCashCoverOfFourTypes = 0 ;
		foreach(LgTypes::getAll() as $lgTypeId => $lgTypeNameFormatted){
			$letterOfGuaranteeCashCover = DB::table('letter_of_guarantee_cash_cover_statements')
			->where('company_id',$companyId)
			->where('currency',$currency)
			->where('financial_institution_id',$financialInstitutionId)
			->where('lg_facility_id',$lgFacilityId)
			->where('lg_type',$lgTypeId)
			->when($type , function(Builder $builder) use ($type){
				$builder->where('lg_type',$type);
			})
			->when($source , function(Builder $builder) use ($source){
				$builder->where('source',$source);
			})
			->orderByRaw('date desc,id desc')
			->first();
			$letterOfGuaranteeCashCoverEndBalance = $letterOfGuaranteeCashCover ? $letterOfGuaranteeCashCover->end_balance : 0 ;
			$totalLastCashCoverOfFourTypes += $letterOfGuaranteeCashCoverEndBalance;
		}
		return abs($totalLastCashCoverOfFourTypes) ; 
	}
	public function getForeignKeyNamesThatUsedInFilter():array 
	{
	
		return [
			'lg_facility_id',
			'cd_or_td_id',
			'financial_institution_id',
			'source',
			'lg_type'
		];
	}	
	
}
