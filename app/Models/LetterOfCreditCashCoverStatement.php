<?php

namespace App\Models;

use App\Support\StatementCascade;

use App\Helpers\HDate;
use App\Traits\IsBankStatement;
use App\Traits\Models\HasDeleteButTriggerChangeOnLastElement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property string|null $type
 * @property string $source هو المكان او الطريقه يعني اللي انت انشاتة بيها وانت عندك ثلاث او اربع زراير دول عباره عن المصدر اللي هو قيمة الكولوم دا
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
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\CashExpense|null $cashExpense
 * @property-read \App\Models\MoneyPayment|null $moneyPayment
 * @property-read \App\Models\MoneyReceived|null $moneyReceived
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditCashCoverStatement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditCashCoverStatement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditCashCoverStatement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditCashCoverStatement whereBeginningBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditCashCoverStatement whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditCashCoverStatement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditCashCoverStatement whereCredit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditCashCoverStatement whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditCashCoverStatement whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditCashCoverStatement whereDebit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditCashCoverStatement whereEndBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditCashCoverStatement whereFinancialInstitutionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditCashCoverStatement whereFullDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditCashCoverStatement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditCashCoverStatement whereIsCredit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditCashCoverStatement whereIsDebit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditCashCoverStatement whereLcFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditCashCoverStatement whereLcType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditCashCoverStatement whereLetterOfCreditIssuanceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditCashCoverStatement whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditCashCoverStatement whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditCashCoverStatement whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LetterOfCreditCashCoverStatement extends Model
{
	use HasDeleteButTriggerChangeOnLastElement,IsBankStatement;
    protected $guarded = [
        'id'
    ];

	public static function updateNextRows(LetterOfCreditCashCoverStatement $model):string 
	{
		$minDate = $model->full_date ;
	
		
		/**
		 * * ليه بنستخدم ال 
		 * * min date
		 * * علشان لو عدلنا العنصر الحالي وخلينا التاريخ بتاعه اكبر من التاريخ القديم وقتها العناصر اللي ما بين التاريخ مش هيتعدلوا
		 * * مع انهم كان مفروض يتعدلوا بس انت قولتله عدلي العناصر اللي التاريخ بتاعها اكبر من او يساوي التاريخ الجديد
		 * * ودا غلط مفروض التاريخ الاقل ما بين التاريخ الجديد و القديم للعنصر بحيث دايما يبدا يحدث من عنده
		 */
		 StatementCascade::touchRows(
			DB::table('letter_of_credit_cash_cover_statements')
		->where('full_date','>=',$minDate)
		->where('financial_institution_id',$model->financial_institution_id)
		->where('source',$model->source)
		->where('lc_facility_id',$model->lc_facility_id)
		->where('lc_type',$model->lc_type),
			'full_date asc , id asc'
		);
		
		return $minDate;

	}
		protected static function booted(): void
		{
			static::creating(function(LetterOfCreditCashCoverStatement $model){
				$model->created_at = now();
				$date = $model->date ;
				$time  = now()->format('H:i:s');
				$row = DB::table('temp_deleted_statements')->where('company_id',$model->company_id)->where('table_name','letter_of_credit_cash_cover_statements')->first();
				if($row){
					$model->id = $row->deleted_id;
					DB::table('temp_deleted_statements')->where('company_id',$model->company_id)->where('table_name','letter_of_credit_cash_cover_statements')->delete();
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
			
			static::created(function(LetterOfCreditCashCoverStatement $model){
				self::updateNextRows($model);
			});
			
			static::updated(function (LetterOfCreditCashCoverStatement $model) {
				
				$minDate = self::updateNextRows($model);
				
				$lcFacilityIsChanged = $model->isDirty('lc_facility_id') ;
				$financialInstitutionIsChanged = $model->isDirty('financial_institution_id') ;
				$sourceIsChanged = $model->isDirty('source') ;
				$lcTypeIsChange = $model->isDirty('lc_type') ;
		// 		->where('financial_institution_id',$model->financial_institution_id)
		// ->where('source',$model->source)
		
				/**
				 * * دي علشان لو غيرت ال
				 * * lc_facility_id
				 * * بمعني انه نقل السحبة مثلا من حساب الي حساب اخر .. يبقي هنحتاج نشغل الترجرز علشان الحساب القديم علشان يوزع تاني
				 */
				if($lcFacilityIsChanged ||$lcTypeIsChange || $financialInstitutionIsChanged || $sourceIsChanged ){
					$oldLcFacilityId=$model->getRawOriginal('lc_facility_id');
					$oldSource=$model->getRawOriginal('source');
					$financialInstitutionId=$model->getRawOriginal('financial_institution_id');
					$oldLcType=$model->getRawOriginal('lc_type');
					$oldStatementId =$model->getRawOriginal('id');
					// لو ما لقناش اول واحد فوقه هندور علي اول واحد بعدة					
					$firstBankStatementForOld = LetterOfCreditCashCoverStatement::
					where('financial_institution_id',$financialInstitutionId)->
					where('lc_facility_id',$oldLcFacilityId)->
					where('source',$oldSource)->
					where('lc_type',$oldLcType)
					->where('id','!=',$oldStatementId )->orderBy('id')->first()  ;
					// لو كانت القديمة دي قبل ما تتغير هي الاستيتم الوحيده بعد كدا انت غيرتها بالتالي الحساب القديم دا معتش ليه لزمة فا هنحذف كل السحبات و التسديدات بتاعته
					if(!$firstBankStatementForOld){
						// وتلقائي هيحذف السحوبات settlements
					}else{
						DB::table('letter_of_credit_cash_cover_statements')
						->where('full_date','>=',$minDate)
						->orderByRaw('full_date asc , id asc')
						->where('lc_facility_id',$model->lc_facility_id)
						->where('lc_type',$model->lc_type)
						->where('financial_institution_id',$model->financial_institution_id)
						->where('source',$model->source)
						
						->update([
							'updated_at'=>now()
						]);
						
					}
					
				}
				
			});
			
			static::deleting(function(LetterOfCreditCashCoverStatement $letterOfCreditCashCoverStatement){
				$oldDate = null ;
				if($letterOfCreditCashCoverStatement->is_debit && Request('payment_date')||$letterOfCreditCashCoverStatement->is_credit && Request('issuance_date')){
						$oldDate = Carbon::make(Request('payment_date',Request('issuance_date')))->format('Y-m-d');
						// $time  = now()->format('H:i:s');
						// $oldDate = date('Y-m-d H:i:s', strtotime("$oldDate $time")) ;
						$currentDate = $letterOfCreditCashCoverStatement->date ;
						$letterOfCreditCashCoverStatement->date = min($oldDate,$currentDate);
				}
				$letterOfCreditCashCoverStatement->debit = 0;
				$letterOfCreditCashCoverStatement->credit = 0;
				$letterOfCreditCashCoverStatement->save();
				
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
	public function getForeignKeyNamesThatUsedInFilter():array 
	{
		return [
			'lc_facility_id',
			'financial_institution_id',
			'source',
			'lc_type'
		];
	}		
	
}
