<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


/**
 * @property int $id
 * @property int $overdraft_against_assignment_of_contract_bank_statement_id
 * @property int $overdraft_against_assignment_of_contract_id
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
 * @property-read \App\Models\OverdraftAgainstAssignmentOfContractBankStatement $bankStatement
 * @property-read \App\Models\OverdraftAgainstAssignmentOfContract|null $overdraftAgainstAssignmentOfContract
 * @property-write mixed $withdrawal_date
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContractWithdrawal newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContractWithdrawal newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContractWithdrawal query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContractWithdrawal whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContractWithdrawal whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContractWithdrawal whereDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContractWithdrawal whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContractWithdrawal whereMaxSettlementDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContractWithdrawal whereNetBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContractWithdrawal whereOverdraftAgainstAssignmentOfContractBankStatementId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContractWithdrawal whereOverdraftAgainstAssignmentOfContractId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContractWithdrawal whereSettlementAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContractWithdrawal whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class OverdraftAgainstAssignmentOfContractWithdrawal extends Model
{

	
	protected $guarded =[
		'id'
	];
	public function bankStatement()
	{
		return $this->belongsTo(OverdraftAgainstAssignmentOfContractBankStatement::class,'overdraft_against_assignment_of_contract_bank_statement_id','id');
	}
	public function overdraftAgainstAssignmentOfContract()
	{
		return $this->belongsTo(OverdraftAgainstAssignmentOfContract::class,'overdraft_against_assignment_of_contract_id','id');
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
