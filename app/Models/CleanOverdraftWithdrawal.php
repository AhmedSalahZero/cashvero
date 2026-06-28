<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


/**
 * @property int $id
 * @property int $clean_overdraft_bank_statement_id
 * @property int $clean_overdraft_id
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
 * @property-read \App\Models\CleanOverdraftBankStatement $bankStatement
 * @property-read \App\Models\CleanOverdraft|null $cleanOverdraft
 * @property-write mixed $withdrawal_date
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraftWithdrawal newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraftWithdrawal newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraftWithdrawal query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraftWithdrawal whereCleanOverdraftBankStatementId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraftWithdrawal whereCleanOverdraftId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraftWithdrawal whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraftWithdrawal whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraftWithdrawal whereDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraftWithdrawal whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraftWithdrawal whereMaxSettlementDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraftWithdrawal whereNetBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraftWithdrawal whereSettlementAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraftWithdrawal whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CleanOverdraftWithdrawal extends Model
{

	
	protected $guarded =[
		'id'
	];
	public function bankStatement()
	{
		return $this->belongsTo(CleanOverdraftBankStatement::class,'clean_overdraft_bank_statement_id','id');
	}
	public function cleanOverdraft()
	{
		return $this->belongsTo(CleanOverdraft::class,'clean_overdraft_id','id');
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
