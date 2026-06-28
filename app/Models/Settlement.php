<?php

namespace App\Models;

use App\Services\Api\OdooPayment;
use App\Traits\Models\IsSettlement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $odoo_id
 * @property int|null $odoo_move_id
 * @property string|null $odoo_reference
 * @property int|null $account_bank_statement_line_id
 * @property string|null $odoo_reference_name
 * @property int $is_from_down_payment
 * @property int|null $invoice_id
 * @property int $partner_id
 * @property string|null $withhold_amount
 * @property string|null $settlement_amount
 * @property int|null $money_received_id
 * @property int|null $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company|null $company
 * @property-read \App\Models\CustomerInvoice|null $customerInvoice
 * @property-read \App\Models\CustomerInvoice|null $invoice
 * @property-read \App\Models\MoneyReceived|null $moneyReceived
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Settlement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Settlement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Settlement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Settlement whereAccountBankStatementLineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Settlement whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Settlement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Settlement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Settlement whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Settlement whereIsFromDownPayment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Settlement whereMoneyReceivedId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Settlement whereOdooId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Settlement whereOdooMoveId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Settlement whereOdooReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Settlement whereOdooReferenceName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Settlement wherePartnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Settlement whereSettlementAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Settlement whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Settlement whereWithholdAmount($value)
 * @mixin \Eloquent
 */
class Settlement extends Model
{
	use IsSettlement;
	protected $guarded = ['id'];
	protected static function booted()
	{
	
		self::deleting(function (self $settlement): void {
			$company =$settlement->company;
			if($company->hasOdooIntegrationCredentials()){
				if($settlement->odoo_id){
					$odooPaymentService = new OdooPayment($company);
					$odooPaymentService->cancelPayments($settlement->odoo_id);
				}
			}
		});
	}
	public function moneyReceived()
	{
		return $this->belongsTo(MoneyReceived::class , 'money_received_id','id');
	}
	
	public function customerInvoice()
	{
		return $this->belongsTo(CustomerInvoice::class , 'invoice_id','id');
	}
	public function invoice():BelongsTo
	{
		return $this->customerInvoice();
	}
	public function getMoney()
	{
	     	$id = $this->money_received_id ;
			return MoneyReceived::find($id);		
	}
	
	
}
