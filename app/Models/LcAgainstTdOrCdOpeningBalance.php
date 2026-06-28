<?php

namespace App\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $type CertificateOfDeposit , TimeOfDeposit
 * @property string $currency
 * @property string $lc_type
 * @property int $financial_institution_id
 * @property int $lc_opening_balance_id
 * @property string $lc_end_date
 * @property string $account_type td or cd only
 * @property string|null $account_number td or cd account number
 * @property numeric $amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company|null $company
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcAgainstTdOrCdOpeningBalance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcAgainstTdOrCdOpeningBalance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcAgainstTdOrCdOpeningBalance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcAgainstTdOrCdOpeningBalance whereAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcAgainstTdOrCdOpeningBalance whereAccountType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcAgainstTdOrCdOpeningBalance whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcAgainstTdOrCdOpeningBalance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcAgainstTdOrCdOpeningBalance whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcAgainstTdOrCdOpeningBalance whereFinancialInstitutionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcAgainstTdOrCdOpeningBalance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcAgainstTdOrCdOpeningBalance whereLcEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcAgainstTdOrCdOpeningBalance whereLcOpeningBalanceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcAgainstTdOrCdOpeningBalance whereLcType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcAgainstTdOrCdOpeningBalance whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcAgainstTdOrCdOpeningBalance whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LcAgainstTdOrCdOpeningBalance extends Model
{
    protected $guarded = ['id'];
    protected $table = 'lc_against_td_or_cd_opening_balances';
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
    public function getAccountNumber()
    {
        return $this->account_number ;
    }
    public function getCurrency(){
        return $this->currency;
    }
    public function getAccountType(){
        return $this->account_type;
    }
    public function getAmount()
    {
        return $this->amount ?:0;
    }
    public function getAmountFormatted()
    {
        return number_format($this->getAmount());
    }
	public function getLcEndDate()
	{
		return $this->lc_end_date;
	}
    public function getEndDate()
	{
		return $this->lc_end_date;
	}
	public function company()
	{
		return $this->belongsTo(Company::class , 'company_id','id');
	}

	// public function lcOpeningBalance()
	// {
	// 	return $this->hasMany(LcOpeningBalance::class , 'lc_opening_balance_id','id');
	// }
	public function setLcEndDateAttribute($value)
	{
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['lc_end_date'] =  $value ;
			return ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		$this->attributes['lc_end_date'] = $year.'-'.$month.'-'.$day;
	}





}
