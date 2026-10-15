<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


/**
 * @property int $id
 * @property int $fully_secured_overdraft_bank_statement_id
 * @property int $fully_secured_overdraft_id
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
 * @property-read \App\Models\FullySecuredOverdraftBankStatement $bankStatement
 * @property-read \App\Models\FullySecuredOverdraft|null $fullySecuredOverdraft
 * @property-write mixed $withdrawal_date
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftWithdrawal newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftWithdrawal newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftWithdrawal query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftWithdrawal whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftWithdrawal whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftWithdrawal whereDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftWithdrawal whereFullySecuredOverdraftBankStatementId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftWithdrawal whereFullySecuredOverdraftId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftWithdrawal whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftWithdrawal whereMaxSettlementDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftWithdrawal whereNetBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftWithdrawal whereSettlementAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraftWithdrawal whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class FullySecuredOverdraftWithdrawal extends Model
{

	
	protected $guarded =[
		'id'
	];
	public function bankStatement()
	{
		return $this->belongsTo(FullySecuredOverdraftBankStatement::class,'fully_secured_overdraft_bank_statement_id','id');
	}
	public function fullySecuredOverdraft()
	{
		return $this->belongsTo(FullySecuredOverdraft::class,'fully_secured_overdraft_id','id');
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
