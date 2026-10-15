<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * * هو عباره عن الكاش اللي بدفعه للمورد
 *
 * @property int $id
 * @property string $company_id
 * @property int|null $money_payment_id
 * @property int|null $cash_expense_id
 * @property int|null $delivery_branch_id
 * @property string|null $receipt_number
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Branch|null $deliveryBranch
 * @property-read \App\Models\MoneyPayment|null $moneyPayment
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashPayment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashPayment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashPayment query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashPayment whereCashExpenseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashPayment whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashPayment whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashPayment whereDeliveryBranchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashPayment whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashPayment whereMoneyPaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashPayment whereReceiptNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CashPayment whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CashPayment extends Model
{

    protected $guarded = ['id'];
	
	public function moneyPayment()
	{
		return $this->belongsTo(MoneyPayment::class,'money_payment_id');
	}
	public function getBankOdooId():?int
	{
		$branch = $this->deliveryBranch;
		return $branch ? $branch->odoo_id : 0 ;
	}
	public function getBankJournalId():?int
	{
		$branch = $this->deliveryBranch;
		return $branch ? $branch->journal_id : 0 ;
	}
	public function deliveryBranch(){
		return $this->belongsTo(Branch::class,'delivery_branch_id','id');
	}
	public function getDeliveryBranchId()
	{
		$branch = $this->deliveryBranch;
		return $branch ? $branch->id : 0 ;
	}
	public function getDeliveryBranchName()
	{
		$branch = $this->deliveryBranch;
		return $branch ? $branch->getName() : 0 ;
	}
	public function getReceiptNumber()
	{
		return $this->receipt_number ;
	}
	
}
