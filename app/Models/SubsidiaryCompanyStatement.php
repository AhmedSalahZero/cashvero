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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SubsidiaryCompanyStatement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SubsidiaryCompanyStatement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SubsidiaryCompanyStatement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SubsidiaryCompanyStatement whereBeginningBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SubsidiaryCompanyStatement whereCommentAr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SubsidiaryCompanyStatement whereCommentEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SubsidiaryCompanyStatement whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SubsidiaryCompanyStatement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SubsidiaryCompanyStatement whereCredit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SubsidiaryCompanyStatement whereCurrencyName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SubsidiaryCompanyStatement whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SubsidiaryCompanyStatement whereDebit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SubsidiaryCompanyStatement whereEndBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SubsidiaryCompanyStatement whereFullDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SubsidiaryCompanyStatement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SubsidiaryCompanyStatement whereIsCredit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SubsidiaryCompanyStatement whereIsDebit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SubsidiaryCompanyStatement whereMoneyPaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SubsidiaryCompanyStatement whereMoneyReceivedId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SubsidiaryCompanyStatement wherePartnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SubsidiaryCompanyStatement whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class SubsidiaryCompanyStatement extends Model  implements IHaveStatement
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
		$minDate  = $model->date ;
	
		
		/**
		 * * ليه بنستخدم ال 
		 * * min date
		 * * علشان لو عدلنا العنصر الحالي وخلينا التاريخ بتاعه اكبر من التاريخ القديم وقتها العناصر اللي ما بين التاريخ مش هيتعدلوا
		 * * مع انهم كان مفروض يتعدلوا بس انت قولتله عدلي العناصر اللي التاريخ بتاعها اكبر من او يساوي التاريخ الجديد
		 * * ودا غلط مفروض التاريخ الاقل ما بين التاريخ الجديد و القديم للعنصر بحيث دايما يبدا يحدث من عنده
		 */

		 DB::table('subsidiary_company_statements')
		->where('date','>=',$minDate)
		->orderByRaw('date asc , id asc')
		// ->where('financial_institution_account_id',$model->financial_institution_account_id)
		->each(function($subsidiaryCompanyStatement){
			DB::table('subsidiary_company_statements')->where('id',$subsidiaryCompanyStatement->id)->update([
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
				$row = DB::table('temp_deleted_statements')->where('company_id',$model->company_id)->where('table_name','subsidiary_company_statements')->first();
				if($row){
					$model->id = $row->deleted_id;
					DB::table('temp_deleted_statements')->where('company_id',$model->company_id)->where('table_name','subsidiary_company_statements')->delete();
				}
				
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
				// 		DB::table('subsidiary_company_statements')
				// 		->where('full_date','>=',$minDate)
				// 		->orderByRaw('full_date asc , id asc')
				// 		->where('financial_institution_account_id',$model->financial_institution_account_id)->update([
				// 			'updated_at'=>now()
				// 		]);
						
				// 	}
					
				// }
				
			});
			
			static::deleting(function(self $subsidiaryCompanyStatement){
				$subsidiaryCompanyStatement->debit = 0;
				$subsidiaryCompanyStatement->credit = 0;
				$subsidiaryCompanyStatement->save();
				
			});
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

	public function getForeignKeyNamesThatUsedInFilter():array 
	{
		return [
			'currency_name',
			'partner_id'
		];
	}
	
}
