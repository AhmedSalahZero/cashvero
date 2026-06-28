<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * * هي عباره عن ال
 * * down payment  Settlements
 * * الخاصة بال money Payment
 *
 * @property int $id
 * @property int|null $contract_id
 * @property int|null $purchase_order_id
 * @property int|null $supplier_id
 * @property string|null $down_payment_amount
 * @property numeric $total_down_payment_settlement
 * @property numeric $down_payment_balance
 * @property string|null $currency
 * @property int|null $money_payment_id
 * @property int|null $cash_expense_id
 * @property int|null $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\MoneyPayment|null $moneyPayment
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DownPaymentMoneyPaymentSettlement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DownPaymentMoneyPaymentSettlement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DownPaymentMoneyPaymentSettlement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DownPaymentMoneyPaymentSettlement whereCashExpenseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DownPaymentMoneyPaymentSettlement whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DownPaymentMoneyPaymentSettlement whereContractId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DownPaymentMoneyPaymentSettlement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DownPaymentMoneyPaymentSettlement whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DownPaymentMoneyPaymentSettlement whereDownPaymentAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DownPaymentMoneyPaymentSettlement whereDownPaymentBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DownPaymentMoneyPaymentSettlement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DownPaymentMoneyPaymentSettlement whereMoneyPaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DownPaymentMoneyPaymentSettlement wherePurchaseOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DownPaymentMoneyPaymentSettlement whereSupplierId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DownPaymentMoneyPaymentSettlement whereTotalDownPaymentSettlement($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DownPaymentMoneyPaymentSettlement whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class DownPaymentMoneyPaymentSettlement extends Model
{
	protected $guarded = ['id'];
	protected $table ='down_payment_money_payment_settlements';
	// protected $table ='down_payment_payment_settlements';
	public function moneyPayment()
	{
		return $this->belongsTo(MoneyPayment::class , 'money_payment_id','id');
	}
	

	// public function getAmount()
	// {
	// 	return $this->settlement_amount ;
	// }	
	// public function getWithhold()
	// {
	// 	return $this->withhold_amount ;
	// }		

	// public function getInvoiceNumber()
	// {
	// 	return $this->invoice_number ; 
	// }


	// public function getSettlementAmount()
	// {
	// 	return $this->settlement_amount?:0 ; 
	// }
	// public function getSettlementAmountFormatted()
	// {
	// 	return number_format($this->getSettlementAmount(),0);
	// }
	
	// public function getSettlementDateFormatted()
    // {
    //     $settlementDate = $this->getSettlementDate() ;
    //     if($settlementDate) {
    //         return Carbon::make($settlementDate)->format('d-m-Y');
    //     }
    // }
	
}
