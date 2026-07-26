<?php

namespace App\Models;

use App\Support\StatementCascade;

use App\Enums\LcTypes;
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
 * @property int $letter_of_credit_issuance_id
 * @property int|null $lc_facility_id
 * @property string $lc_type
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditStatement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditStatement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditStatement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditStatement whereBeginningBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditStatement whereCdOrTdId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditStatement whereCommentAr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditStatement whereCommentEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditStatement whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditStatement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditStatement whereCredit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditStatement whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditStatement whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditStatement whereDebit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditStatement whereEndBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditStatement whereFinancialInstitutionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditStatement whereFullDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditStatement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditStatement whereIsCredit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditStatement whereIsDebit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditStatement whereLcFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditStatement whereLcType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditStatement whereLetterOfCreditIssuanceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditStatement whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditStatement whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditStatement whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LetterOfCreditStatement extends Model
{
	use HasDeleteButTriggerChangeOnLastElement,IsBankStatement;
    protected $guarded = [
        'id'
    ];

	public static function updateNextRows(LetterOfCreditStatement $model):string 
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
			DB::table('letter_of_credit_statements')
		->where('date','>=',$minDate)
		->where('financial_institution_id',$model->financial_institution_id)
		->where('source',$model->source)
		->where('lc_facility_id',$model->lc_facility_id)
		->where('cd_or_td_id',$model->cd_or_td_id)
		->where('lc_type',$model->lc_type),
			'date asc , id asc'
		);
		
		return $minDate;

	}
		protected static function booted(): void
		{
			static::creating(function(LetterOfCreditStatement $model){
				$model->created_at = now();
				$date = $model->date ;
				$time  = now()->format('H:i:s');
				
				$fullDateTime = date('Y-m-d H:i:s', strtotime("$date $time")) ;
				
				$row = DB::table('temp_deleted_statements')->where('company_id',$model->company_id)->where('table_name','letter_of_credit_statements')->first();
				if($row){
					$model->id = $row->deleted_id;
					DB::table('temp_deleted_statements')->where('company_id',$model->company_id)->where('table_name','letter_of_credit_statements')->delete();
				}
				
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
			
			static::created(function(LetterOfCreditStatement $model){
				self::updateNextRows($model);
			});
			
			static::updated(function (LetterOfCreditStatement $model) {
				
				$minDate = self::updateNextRows($model);
				
				
				$lcFacilityIsChanged = $model->isDirty('lc_facility_id') ;
				$lcCdOrTdIdIsChanged = $model->isDirty('cd_or_td_id') ;
				$financialInstitutionIsChanged = $model->isDirty('financial_institution_id') ;
				$sourceIsChanged = $model->isDirty('source') ;
				$lcTypeIsChange = $model->isDirty('lc_type') ;
		
				/**
				 * * دي علشان لو غيرت ال
				 * * lc_facility_id
				 * * بمعني انه نقل السحبة مثلا من حساب الي حساب اخر .. يبقي هنحتاج نشغل الترجرز علشان الحساب القديم علشان يوزع تاني
				 */
				if($lcFacilityIsChanged ||$lcTypeIsChange || $financialInstitutionIsChanged || $sourceIsChanged || $lcCdOrTdIdIsChanged ){
					$oldLcFacilityId=$model->getRawOriginal('lc_facility_id');
					$oldSource=$model->getRawOriginal('source');
					$oldCdOrTdId=$model->getRawOriginal('cd_or_td_id');
					$financialInstitutionId=$model->getRawOriginal('financial_institution_id');
					$oldLcType=$model->getRawOriginal('lc_type');
					$oldStatementId =$model->getRawOriginal('id');
					// لو ما لقناش اول واحد فوقه هندور علي اول واحد بعدة					
					$firstBankStatementForOld = LetterOfCreditStatement::
					where('financial_institution_id',$financialInstitutionId)->
					where('lc_facility_id',$oldLcFacilityId)->
					where('cd_or_td_id',$oldCdOrTdId)->
					where('source',$oldSource)->
					
					where('lc_type',$oldLcType)
					->where('id','!=',$oldStatementId )->orderBy('id')->first()  ;
					// لو كانت القديمة دي قبل ما تتغير هي الاستيتم الوحيده بعد كدا انت غيرتها بالتالي الحساب القديم دا معتش ليه لزمة فا هنحذف كل السحبات و التسديدات بتاعته
					if(!$firstBankStatementForOld){
						// وتلقائي هيحذف السحوبات settlements
					}else{
						DB::table('letter_of_credit_statements')
						->where('date','>=',$minDate)
						->orderByRaw('date asc , id asc')
						->where('lc_facility_id',$model->lc_facility_id)
						->where('cd_or_td_id',$model->cd_or_td_id)
						->where('lc_type',$model->lc_type)
						->where('financial_institution_id',$model->financial_institution_id)
						->where('source',$model->source)
						
						->update([
							'updated_at'=>now()
						]);
						
					}
					
				}
				
			});
			
			static::deleting(function(LetterOfCreditStatement $letterOfCreditStatement){
				$oldDate = null ;
				if($letterOfCreditStatement->is_debit && Request('payment_date')||$letterOfCreditStatement->is_credit && Request('issuance_date')){
						$oldDate = Carbon::make(Request('payment_date',Request('issuance_date')))->format('Y-m-d');
						// $time  = now()->format('H:i:s');
						// $oldDate = date('Y-m-d H:i:s', strtotime("$oldDate $time")) ;
						$currentDate = $letterOfCreditStatement->date ;
						$letterOfCreditStatement->date = min($oldDate,$currentDate);
				}
				$letterOfCreditStatement->debit = 0;
				$letterOfCreditStatement->credit = 0;
				$letterOfCreditStatement->save();
				
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
	public function getLetterOfCreditIssuance()
	{
		return $this->belongsTo(LetterOfCreditIssuance::class,'letter_of_credit_issuance_id','id');
	} 
	public function getLetterOfCreditFacility()
	{
		return $this->belongsTo(LetterOfCreditIssuance::class,'lc_facility_id','id');
	} 

	// public function cashInSafes()
	// {
	// 	return $this->belongsTo(OpeningBalance::class,'opening_balance_id','id') ;
	// }
	// public function getExchangeRate()
	// {
	// 	return $this->exchange_rate ?:1 ;
	// }
	
	
	public static function getTotalOutstandingBalanceForAllTypes(int $lcFacilityId , int $companyId , int $financialInstitutionId,string $currencyName , $debug=false):float 
	{
		$totalLastOutstandingBalanceOfFourTypes = 0 ;
		foreach(LcTypes::getAll() as $lcTypeId => $lcTypeNameFormatted){	
			foreach(LetterOfCreditIssuance::lcSources() as $currentSourceId => $currentSourceTitle){
				if($currentSourceId != LetterOfCreditIssuance::LC_FACILITY ){
					continue ;
				}
				
				$letterOfCreditStatement = DB::table((new self)->getTable())
					->where('company_id',$companyId)
					->where('financial_institution_id',$financialInstitutionId)
					->where('currency',$currencyName)
					->where('lc_facility_id',$lcFacilityId)
					->where('lc_type',$lcTypeId)
					->where('source',$currentSourceId)
					->orderByRaw('date desc , id desc')
					->first();
					$letterOfCreditStatementEndBalance = $letterOfCreditStatement ? $letterOfCreditStatement->end_balance : 0 ;
					$totalLastOutstandingBalanceOfFourTypes += $letterOfCreditStatementEndBalance;
					
					
				
			}
			
		}
		return abs($totalLastOutstandingBalanceOfFourTypes) ; 
	}
	public static function getDashboardDataForFinancialInstitution(int $companyId , int $financialInstitutionId,string $currencyName,string $lcType)
	{
			$letterOfCreditStatement = DB::table((new self)->getTable())
			->where('company_id',$companyId)
			->where('financial_institution_id',$financialInstitutionId)
			->where('currency',$currencyName)
			->where('lc_type',$lcType)
			->orderByRaw('date desc,id desc')
			->first();
			$letterOfCreditStatementEndBalance = $letterOfCreditStatement ? $letterOfCreditStatement->end_balance : 0 ;
			return abs($letterOfCreditStatementEndBalance);
	} 
	public static function getDashboardOutstandingPerTypeFormattedData(array &$charts , Company $company  , string $currencyName , string $date , string $lcTypeId , ?string $source , $selectedFinancialInstitutionBankIds ):void
	{
		$lcTitle = LcTypes::getAll()[$lcTypeId] ;
		$sources = $source ? [$source => $source] : LetterOfCreditIssuance::lcSources();
		
		$totalEndBalance = 0 ;
		foreach($sources as $source => $sourceTitle){
			foreach($selectedFinancialInstitutionBankIds as $currentFinancialInstitutionId){
				$rowPerType  = DB::table((new self)->getTable())
						->where('company_id',$company->id )
						->where('financial_institution_id',$currentFinancialInstitutionId)
						->where('currency',$currencyName)
						->where('date','<=',$date)
						->where('lc_type',$lcTypeId)
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
		
				$charts['outstanding_per_lc_type'][$currencyName][] = ['type'=>$lcTitle , 'outstanding'=>$totalEndBalance] ;
	}
	public static function getDashboardOutstandingPerFinancialInstitutionFormattedData(array &$charts , Company $company  , string $currencyName , string $date , int $financialInstitutionId , string $financialInstitutionName , ?string $source , $lcTypes):void
	{
		$sources = $source ? [$source => $source] : LetterOfCreditIssuance::lcSources();
		$totalEndBalance = 0 ;
		foreach($sources as $currentSourceId => $sourceTitle){
		foreach($lcTypes as $currentLcTypeId => $currentLcTypeTitle){
		$rowPerType  = DB::table((new self)->getTable())
		->where('company_id',$company->id )
		->where('financial_institution_id',$financialInstitutionId)
		->where('currency',$currencyName)
		->where('date','<=',$date)
		->where('source',$currentSourceId)
		->where('lc_type',$currentLcTypeId)
		->orderByRaw('date desc,id desc')
		->first();
		
		if(!$rowPerType){
			continue;
		}
		$totalEndBalance += abs($rowPerType->end_balance) ;
		}
		}
		$charts['lc_outstanding_per_financial_institution'][$currencyName][] = ['type'=>$financialInstitutionName , 'outstanding'=>$totalEndBalance] ;
	}
	public static function getDashboardOutstandingTableFormattedData(array &$tablesData , Company $company  , string $currencyName , string $date , int $financialInstitutionId,string $lcTypeId , string $financialInstitutionName ,  $lastLetterOfCreditFacility , ?string $source ):void
	{
		$allSources = $source ? [$source => $source] : LetterOfCreditIssuance::lcSources() ; ;
		foreach($allSources as $currentSourceId => $currentSourceTitle){
			$rowPerType  = DB::table((new self)->getTable())
				->where('company_id',$company->id )
				->where('financial_institution_id',$financialInstitutionId)
				->where('currency',$currencyName)
				->where('date','<=',$date)
				->where('source',$currentSourceId)
				->where('lc_type',$lcTypeId)
				->orderByRaw('date desc,id desc')
				->first();
				if(!$rowPerType){continue ;}
				$currentOutstandingBalance = abs($rowPerType->end_balance) ;
				$currentLimit = $lastLetterOfCreditFacility ? $lastLetterOfCreditFacility->limit : 0 ;
				$tablesData['lc_outstanding_for_table'][$currencyName][] = ['financial_institution_name'=>$financialInstitutionName , 'outstanding'=>$currentOutstandingBalance , 'source'=>LetterOfCreditIssuance::lcSources()[$rowPerType->source] , 'type'=>LcTypes::getAll()[$rowPerType->lc_type] , 'limit'=>$currentLimit , 'cash_cover'=>LetterOfCreditStatement::getTotalCashCoverForAllTypes($lastLetterOfCreditFacility->id,$company->id,$financialInstitutionId,$currencyName,$lcTypeId,$currentSourceId)] ;
			
		}
		}
	public static function getTotalCashCoverForAllTypes(int $lcFacilityId,int $companyId , int $financialInstitutionId,string $currency , ?string $type = null , ?string $source = null):float 
	{
		$totalLastCashCoverOfFourTypes = 0 ;
		// foreach(LcTypes::getAll() as $lcTypeId => $lcTypeNameFormatted){
			$letterOfCreditCashCover = DB::table('letter_of_credit_cash_cover_statements')
			->where('company_id',$companyId)
			->where('currency',$currency)
			->where('financial_institution_id',$financialInstitutionId)
			->where('lc_facility_id',$lcFacilityId)
			->when($type , function(Builder $builder) use ($type){
				$builder->where('lc_type',$type);
			})
			->when($source , function(Builder $builder) use ($source){
				$builder->where('source',$source);
			})
			->orderByRaw('date desc,id desc')
			->first();
			
			$letterOfCreditCashCoverEndBalance = $letterOfCreditCashCover ? $letterOfCreditCashCover->end_balance : 0 ;
			$totalLastCashCoverOfFourTypes += $letterOfCreditCashCoverEndBalance;
		return abs($totalLastCashCoverOfFourTypes) ; 
	}
	public function getForeignKeyNamesThatUsedInFilter():array 
	{
		return [
			'lc_facility_id',
			'cd_or_td_id',
			'financial_institution_id',
			'source',
			'lc_type'
		];
	}		
	
}
