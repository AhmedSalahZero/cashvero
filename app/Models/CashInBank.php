<?php

namespace App\Models;

use App\Traits\Models\IsCashInBank;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $money_received_id
 * @property int|null $receiving_bank_id
 * @property string|null $account_type
 * @property string|null $account_number
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $company_id
 * @property-read \App\Models\AccountType|null $accountType
 * @property-read \App\Models\MoneyReceived $moneyReceived
 * @property-read \App\Models\FinancialInstitution|null $receivingBank
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInBank newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInBank newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInBank query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInBank whereAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInBank whereAccountType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInBank whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInBank whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInBank whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInBank whereMoneyReceivedId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInBank whereReceivingBankId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashInBank whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CashInBank extends Model
{

    protected $guarded = ['id'];
	
	public function moneyReceived()
	{
		return $this->belongsTo(MoneyReceived::class,'money_received_id');
	}
	public function receivingBank():?BelongsTo{
		return $this->belongsTo(FinancialInstitution::class,'receiving_bank_id','id');
	}
	
	public function getReceivingBankId()
	{
		$bank = $this->receivingBank;
		return $bank ? $bank->id : 0 ;
	}
	public function getReceivingBankName()
	{
		$bank = $this->receivingBank;
		return $bank ? $bank->getName() : __('N/A') ;
	}
	// public function getReceiptNumber()
	// {
	// 	return $this->receipt_number ;
	// }
	public function accountType()
	{
		return $this->belongsTo(AccountType::class,'account_type','id');
	}
	public function getAccountTypeId()
	{
		$accountType = $this->accountType; 
		return $accountType ? $accountType->id : 0 ; 
	}
	public function getAccountTypeName()
	{
		$accountType = $this->accountType; 
		return $accountType ? $accountType->getName() : __('N/A') ; 
	}
	public function getAccountNumber()
	{
		return $this->account_number;
	}
	
	
}
