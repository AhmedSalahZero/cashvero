<?php

namespace App\Models;

use App\Services\Api\OdooPayment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * * هي عباره عن ال
 * * down payment  Settlements
 * * الخاصة بال money received
 *
 * @property int $id
 * @property int|null $contract_id
 * @property int|null $sales_order_id
 * @property int|null $customer_id
 * @property string|null $down_payment_amount
 * @property numeric $total_down_payment_settlement
 * @property numeric $down_payment_balance
 * @property string|null $currency
 * @property int|null $money_received_id
 * @property int|null $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\MoneyReceived|null $moneyReceived
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DownPaymentSettlement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DownPaymentSettlement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DownPaymentSettlement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DownPaymentSettlement whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DownPaymentSettlement whereContractId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DownPaymentSettlement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DownPaymentSettlement whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DownPaymentSettlement whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DownPaymentSettlement whereDownPaymentAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DownPaymentSettlement whereDownPaymentBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DownPaymentSettlement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DownPaymentSettlement whereMoneyReceivedId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DownPaymentSettlement whereSalesOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DownPaymentSettlement whereTotalDownPaymentSettlement($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\DownPaymentSettlement whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class DownPaymentSettlement extends Model
{
	protected $guarded = ['id'];
	protected $table ='down_payment_settlements';
	
	protected static function booted()
	{
		self::deleting(function (self $downPaymentSettlement): void {
			$moneyReceived = $downPaymentSettlement->moneyReceived;
			$company =$moneyReceived->company;
			if($company->hasOdooIntegrationCredentials()){
				$odooId = $moneyReceived->odoo_id ;
				if($odooId){
					$odooPaymentService = new OdooPayment($company);
					$odooPaymentService->cancelDownPayment($odooId);
				}
			}
		});
		
	}
	
	
	
	public function moneyReceived()
	{
		return $this->belongsTo(MoneyReceived::class , 'money_received_id','id');
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
