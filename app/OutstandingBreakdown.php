<?php

namespace App;

use App\Models\CleanOverdraft;
use App\Models\Company;
use App\Models\FullySecuredOverdraft;
use App\Models\OverdraftAgainstAssignmentOfContract;
use App\Models\OverdraftAgainstCommercialPaper;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * * هو عباره عن التقسيمة الخاصة بال
 * *clean overdraft
 * * outstanding balance
 * * او اي نوع تاني خاص بالتسهيلات
 * * بمعني انك لما بتحط ال
 * * الفلوس اللي انت سحبتها من الحساب لحد لحظه فتح حسابك علي كاش فيرو .
 * 
 * .سحبت قديه يوم قديه وقديه يوم قديه وهكذا
 * * بمعني ان مجموع القيم لازم يساوي ال
 * * outstanding balance in clean overdraft
 *
 * @property int $id
 * @property string $settlement_date
 * @property numeric $amount
 * @property int $model_id وليكن مثلا clean_overdraft_id
 * @property string $model_type
 * @property int $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\CleanOverdraft|null $cleanOverdraft
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\FullySecuredOverdraft|null $fullySecuredOverdraft
 * @property-read \App\Models\OverdraftAgainstAssignmentOfContract|null $overdraftAgainstAssignmentOfContract
 * @property-read \App\Models\OverdraftAgainstCommercialPaper|null $overdraftAgainstCommercialPaper
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OutstandingBreakdown newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OutstandingBreakdown newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OutstandingBreakdown query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OutstandingBreakdown whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OutstandingBreakdown whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OutstandingBreakdown whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OutstandingBreakdown whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OutstandingBreakdown whereModelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OutstandingBreakdown whereModelType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OutstandingBreakdown whereSettlementDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\OutstandingBreakdown whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class OutstandingBreakdown extends Model
{
    protected $guarded = ['id'];
	public function getId()
	{
		return $this->id;
	}
	public function company()
	{
		return $this->belongsTo(Company::class , 'company_id','id');
	}
	public function getSettlementDate()
	{
		return $this->settlement_date;
	}
	public function getSettlementDateForSelect()
	{
		$settlementDate = $this->getSettlementDate();
		return $settlementDate ? Carbon::make($settlementDate)->format('m/d/Y'):$settlementDate;
	}
	public function setSettlementDateAttribute($value)
	{
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['settlement_date'] =  $value ;
			return ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		$this->attributes['settlement_date'] = $year.'-'.$month.'-'.$day;
	}
	
	public function getAmount()
	{
		return $this->amount?:0 ;
	}
	public function cleanOverdraft()
	{
		return $this->belongsTo(CleanOverdraft::class,'model_id','id')->where('model_type',CleanOverdraft::class);
	}
	public function overdraftAgainstCommercialPaper()
	{
		return $this->belongsTo(OverdraftAgainstCommercialPaper::class,'model_id','id')->where('model_type',OverdraftAgainstCommercialPaper::class);
	}
	public function overdraftAgainstAssignmentOfContract()
	{
		return $this->belongsTo(OverdraftAgainstAssignmentOfContract::class,'model_id','id')->where('model_type',OverdraftAgainstAssignmentOfContract::class);
	}
	public function fullySecuredOverdraft()
	{
		return $this->belongsTo(FullySecuredOverdraft::class,'model_id','id')->where('model_type',FullySecuredOverdraft::class);
	}
	
}
