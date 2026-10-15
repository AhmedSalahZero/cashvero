<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * * اسعار الفايده المخصصة لهذا الحساب
 * * لانه في حاله تغيرت لابد من تتبعها لان النهاردا ممكن يكون علي الحساب دا سعر فايده معينه وممكن الشهر الجي يتغير وهكذا
 *
 * @property int $id
 * @property int $financial_institution_account_id
 * @property string|null $start_date
 * @property numeric|null $interest_rate
 * @property numeric|null $min_balance
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\FinancialInstitutionAccount $financialInstitutionAccount
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountInterest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountInterest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountInterest query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountInterest whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountInterest whereFinancialInstitutionAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountInterest whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountInterest whereInterestRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountInterest whereMinBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountInterest whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\AccountInterest whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class AccountInterest extends Model
{
    protected $guarded = ['id'];
	
	public function getId()
	{
		return $this->id ;
	}
		/**
	 * * اقل قيمة لتطبيق الفايدة ( بمعني اقل قيمه في ال البلانس بتاع حسابك لازم يكون قديه قبل ما يمكن تطبيق الفايدة)
	 * * وبالتالي دا شرط لتطبيق الفايده انه يمكن تطبيق الفايدة دي في حالة لو حسابك تخطى مبلغ معين)
	 */
	public function getMinBalance()
	{
		return $this->min_balance ;
	}
	// /**
	//  * * نسبة الفايدة اللي بخدها من الحساب دا ( احيانا بيكون فيه عروض بحيث انك تنشئ حساب وتاخد علي نسبة فايدة كل شهر مثلا)
	//  */
	public function getInterestRate()
    {
        return $this->interest_rate ?: 0 ;
    }
	
	/**
	 * * هو عباره عن التاريخ اللي هيبدا فيه تطبيق هذه الفائدة .. بالتالي بيتم تطبيق هذه الفائده بداية من هذا التاريخ لحد تاريخ بداية العنصر 
	 * * اللي بعدها
	 */
	public function getStartDate()
	{
		return $this->start_date;
	}
	public function getStartDateForSelect()
	{
		$startDate = $this->start_date;
		return $startDate ? Carbon::make($startDate)->format('m/d/Y'):$startDate;
	}


	public function financialInstitutionAccount()
	{
		return $this->belongsTo(FinancialInstitutionAccount::class , 'financial_institution_account_id','id');
	}

}
