<?php

namespace App\Models;

use App\Services\Api\OdooPayment;
use App\Traits\Models\HasDeleteButTriggerChangeOnLastElement;
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
 * @property int|null $money_payment_id
 * @property int|null $cash_expense_id
 * @property int $letter_of_credit_issuance_id
 * @property int|null $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company|null $company
 * @property-read \App\Models\SupplierInvoice|null $invoice
 * @property-read \App\Models\LetterOfCreditIssuance|null $letterOfCreditIssuance
 * @property-read \App\Models\MoneyReceived|null $moneyPayment
 * @property-read \App\Models\SupplierInvoice|null $supplierInvoice
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PaymentSettlement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PaymentSettlement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PaymentSettlement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PaymentSettlement whereAccountBankStatementLineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PaymentSettlement whereCashExpenseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PaymentSettlement whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PaymentSettlement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PaymentSettlement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PaymentSettlement whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PaymentSettlement whereIsFromDownPayment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PaymentSettlement whereLetterOfCreditIssuanceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PaymentSettlement whereMoneyPaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PaymentSettlement whereOdooId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PaymentSettlement whereOdooMoveId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PaymentSettlement whereOdooReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PaymentSettlement whereOdooReferenceName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PaymentSettlement wherePartnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PaymentSettlement whereSettlementAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PaymentSettlement whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PaymentSettlement whereWithholdAmount($value)
 * @mixin \Eloquent
 */
class PaymentSettlement extends Model
{
	use HasDeleteButTriggerChangeOnLastElement ,  IsSettlement;
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
	public function moneyPayment()
	{
		return $this->belongsTo(MoneyReceived::class , 'money_payment_id','id');
	}
	
	public function supplierInvoice()
	{
		return $this->belongsTo(SupplierInvoice::class , 'invoice_id','id');
	}
	public function invoice():BelongsTo
	{
		return $this->supplierInvoice();
	}

	public function letterOfCreditIssuance()
	{
		return $this->belongsTo(LetterOfCreditIssuance::class ,'letter_of_credit_issuance_id');
	}
	public function getMoney(){
		$id = $this->money_payment_id;
			return MoneyPayment::find($id);
	}
}
