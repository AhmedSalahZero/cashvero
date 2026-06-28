<?php

namespace App\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $currency
 * @property string $lc_type
 * @property int $financial_institution_id
 * @property int $lc_opening_balance_id
 * @property string $lc_expiry_date
 * @property string $current_account_number
 * @property numeric $amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company|null $company
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcHundredPercentageCashCoverOpeningBalance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcHundredPercentageCashCoverOpeningBalance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcHundredPercentageCashCoverOpeningBalance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcHundredPercentageCashCoverOpeningBalance whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcHundredPercentageCashCoverOpeningBalance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcHundredPercentageCashCoverOpeningBalance whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcHundredPercentageCashCoverOpeningBalance whereCurrentAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcHundredPercentageCashCoverOpeningBalance whereFinancialInstitutionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcHundredPercentageCashCoverOpeningBalance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcHundredPercentageCashCoverOpeningBalance whereLcExpiryDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcHundredPercentageCashCoverOpeningBalance whereLcOpeningBalanceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcHundredPercentageCashCoverOpeningBalance whereLcType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcHundredPercentageCashCoverOpeningBalance whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LcHundredPercentageCashCoverOpeningBalance extends Model
{
    protected $guarded = ['id'];
    protected $table = 'lc_hundred_percentage_cash_cover_opening_balances';
	public function getId()
	{
		return $this->id;
	}
    public function getLcType()
    {
        return $this->lc_type ;
    }
    /**
     * * رقم الحساب الجاري
     */
    public function getCurrentAccountNumber()
    {
        return $this->current_account_number ;
    }
    public function getAmount()
    {
        return $this->amount ?:0;
    }
    public function getAmountFormatted()
    {
        return number_format($this->getAmount());
    }
	public function getLcExpiryDate()
	{
		return $this->lc_expiry_date;
	}
    public function getExpiryDate()
	{
		return $this->lc_expiry_date;
	}
    public function getCurrency(){
        return $this->currency;
    }
	public function company()
	{
		return $this->belongsTo(Company::class , 'company_id','id');
	}

	// public function lcOpeningBalance()
	// {
	// 	return $this->hasMany(LcOpeningBalance::class , 'lc_opening_balance_id','id');
	// }
	public function setLcExpiryDateAttribute($value)
	{
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['lc_expiry_date'] =  $value ;
			return ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];

		$this->attributes['lc_expiry_date'] = $year.'-'.$month.'-'.$day;
	}





}
