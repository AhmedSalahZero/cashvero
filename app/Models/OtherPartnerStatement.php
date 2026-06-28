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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OtherPartnerStatement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OtherPartnerStatement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OtherPartnerStatement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OtherPartnerStatement whereBeginningBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OtherPartnerStatement whereCommentAr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OtherPartnerStatement whereCommentEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OtherPartnerStatement whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OtherPartnerStatement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OtherPartnerStatement whereCredit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OtherPartnerStatement whereCurrencyName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OtherPartnerStatement whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OtherPartnerStatement whereDebit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OtherPartnerStatement whereEndBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OtherPartnerStatement whereFullDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OtherPartnerStatement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OtherPartnerStatement whereIsCredit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OtherPartnerStatement whereIsDebit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OtherPartnerStatement whereMoneyPaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OtherPartnerStatement whereMoneyReceivedId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OtherPartnerStatement wherePartnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OtherPartnerStatement whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class OtherPartnerStatement extends Model  implements IHaveStatement
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

		 DB::table('other_partner_statements')
		->where('date','>=',$minDate)
		->orderByRaw('date asc , id asc')
		->each(function($otherPartnerStatement){
			DB::table('other_partner_statements')->where('id',$otherPartnerStatement->id)->update([
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
				$row = DB::table('temp_deleted_statements')->where('company_id',$model->company_id)->where('table_name','other_partner_statements')->first();
				if($row){
					$model->id = $row->deleted_id;
					DB::table('temp_deleted_statements')->where('company_id',$model->company_id)->where('table_name','other_partner_statements')->delete();
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
				// 		DB::table('other_partner_statements')
				// 		->where('full_date','>=',$minDate)
				// 		->orderByRaw('full_date asc , id asc')
				// 		->where('financial_institution_account_id',$model->financial_institution_account_id)->update([
				// 			'updated_at'=>now()
				// 		]);
						
				// 	}
					
				// }
				
			});
			
			static::deleting(function(self $otherPartnerStatement){
				$otherPartnerStatement->debit = 0;
				$otherPartnerStatement->credit = 0;
				$otherPartnerStatement->save();
				
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
