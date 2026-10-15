<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


/**
 * @property int $id
 * @property int $lc_overdraft_bank_statement_id
 * @property int|null $lc_facility_id
 * @property int $company_id
 * @property int $max_settlement_days
 * @property string $due_date تاريخ الاستحقاق وهو عباره عن جدول التاريخ 
 * 			date
 * 			من جدول ال 
 * 			bank statement
 * 			زائد ال
 * 			max_settlement_days
 * @property numeric $settlement_amount
 * @property numeric $net_balance
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\CleanOverdraftBankStatement|null $bankStatement
 * @property-read \App\Models\LetterOfCreditIssuance|null $lcIssuance
 * @property-write mixed $withdrawal_date
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftWithdrawal newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftWithdrawal newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftWithdrawal query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftWithdrawal whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftWithdrawal whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftWithdrawal whereDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftWithdrawal whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftWithdrawal whereLcFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftWithdrawal whereLcOverdraftBankStatementId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftWithdrawal whereMaxSettlementDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftWithdrawal whereNetBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftWithdrawal whereSettlementAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcOverdraftWithdrawal whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class LcOverdraftWithdrawal extends Model
{

	
	protected $guarded =[
		'id'
	];
	public function bankStatement()
	{
		return $this->belongsTo(CleanOverdraftBankStatement::class,'clean_overdraft_bank_statement_id','id');
	}
	public function lcIssuance()
	{
		return $this->belongsTo(LetterOfCreditIssuance::class,'lc_issuance_id','id');
	}
	public function getId()
	{
		return $this->id ;
	}
	public function setWithdrawalDateAttribute($value)
	{
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['withdrawal_date'] =  $value ;
			return ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		
		$this->attributes['withdrawal_date'] = $year.'-'.$month.'-'.$day;
	}
	
	public function setDueDateAttribute($value)
	{
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['due_date'] =  $value ;
			return ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		
		$this->attributes['due_date'] = $year.'-'.$month.'-'.$day;
	}
	
	
	
	
	
}
