<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;


/**
 * @property int $id
 * @property int $overdraft_against_commercial_paper_bank_statement_id
 * @property int $overdraft_against_commercial_paper_id
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
 * @property-read \App\Models\OverdraftAgainstCommercialPaperBankStatement $bankStatement
 * @property-read \App\Models\OverdraftAgainstCommercialPaper|null $overdraftAgainstCommercialPaper
 * @property-write mixed $withdrawal_date
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperWithdrawal newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperWithdrawal newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperWithdrawal query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperWithdrawal whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperWithdrawal whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperWithdrawal whereDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperWithdrawal whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperWithdrawal whereMaxSettlementDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperWithdrawal whereNetBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperWithdrawal whereOverdraftAgainstCommercialPaperBankStatementId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperWithdrawal whereOverdraftAgainstCommercialPaperId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperWithdrawal whereSettlementAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaperWithdrawal whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class OverdraftAgainstCommercialPaperWithdrawal extends Model
{

	
	protected $guarded =[
		'id'
	];
	public function bankStatement()
	{
		return $this->belongsTo(OverdraftAgainstCommercialPaperBankStatement::class,'overdraft_against_commercial_paper_bank_statement_id','id');
	}
	public function overdraftAgainstCommercialPaper()
	{
		return $this->belongsTo(OverdraftAgainstCommercialPaper::class,'overdraft_against_commercial_paper_id','id');
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
