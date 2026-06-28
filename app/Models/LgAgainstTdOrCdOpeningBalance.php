<?php

namespace App\Models;

use App\Models\Company;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $type CertificateOfDeposit , TimeOfDeposit
 * @property string $currency
 * @property string $lg_type
 * @property int $financial_institution_id
 * @property int $lg_opening_balance_id
 * @property string $lg_end_date
 * @property string $account_type td or cd only
 * @property string|null $account_number td or cd account number
 * @property numeric $amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company|null $company
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LgAgainstTdOrCdOpeningBalance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LgAgainstTdOrCdOpeningBalance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LgAgainstTdOrCdOpeningBalance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LgAgainstTdOrCdOpeningBalance whereAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LgAgainstTdOrCdOpeningBalance whereAccountType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LgAgainstTdOrCdOpeningBalance whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LgAgainstTdOrCdOpeningBalance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LgAgainstTdOrCdOpeningBalance whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LgAgainstTdOrCdOpeningBalance whereFinancialInstitutionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LgAgainstTdOrCdOpeningBalance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LgAgainstTdOrCdOpeningBalance whereLgEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LgAgainstTdOrCdOpeningBalance whereLgOpeningBalanceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LgAgainstTdOrCdOpeningBalance whereLgType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LgAgainstTdOrCdOpeningBalance whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LgAgainstTdOrCdOpeningBalance whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LgAgainstTdOrCdOpeningBalance extends Model
{
    protected $guarded = ['id'];
    protected $table = 'lg_against_td_or_cd_opening_balances';
	public function getId()
	{
		return $this->id;
	}
    public function getLgType()
    {
        return $this->lg_type ;
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
	public function getLgEndDate()
	{
		return $this->lg_end_date;
	}
    public function getEndDate()
	{
		return $this->lg_end_date;
	}
	public function company()
	{
		return $this->belongsTo(Company::class , 'company_id','id');
	}

	// public function lgOpeningBalance()
	// {
	// 	return $this->hasMany(LgOpeningBalance::class , 'lg_opening_balance_id','id');
	// }
	public function setLgEndDateAttribute($value)
	{
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['lg_end_date'] =  $value ;
			return ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		$this->attributes['lg_end_date'] = $year.'-'.$month.'-'.$day;
	}





}
