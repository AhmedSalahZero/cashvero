<?php

namespace App\Models;

use App\Helpers\HArr;
use App\Interfaces\Models\IInvoice;
use App\Traits\Models\IsInvoice;
use App\Traits\StaticBoot;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int|null $odoo_id
 * @property int $company_id
 * @property int|null $opening_balance_id
 * @property string|null $supplier_code
 * @property string|null $sales_person
 * @property int $supplier_id
 * @property string|null $supplier_name
 * @property string|null $business_sector
 * @property string|null $project_name
 * @property string|null $site_name
 * @property string|null $invoice_date
 * @property string|null $invoice_month
 * @property int|null $invoice_year
 * @property string|null $invoice_number
 * @property string|null $invoice_amount
 * @property string $currency
 * @property numeric $exchange_rate
 * @property float|null $invoice_amount_in_main_currency
 * @property string|null $vat_amount
 * @property float|null $vat_amount_in_main_currency
 * @property numeric $odoo_withhold_amount
 * @property numeric $odoo_withhold_amount_in_main_currency
 * @property string|null $withhold_amount
 * @property float|null $withhold_amount_in_main_currency
 * @property numeric $total_withhold_amount
 * @property numeric $total_withhold_amount_in_main_currency
 * @property string|null $net_invoice_amount
 * @property float|null $net_invoice_amount_in_main_currency
 * @property string|null $contracted_payment_days
 * @property string|null $invoice_due_date
 * @property string|null $invoice_status
 * @property numeric $odoo_paid_amount
 * @property numeric $odoo_paid_amount_in_main_currency
 * @property string|null $paid_amount
 * @property float|null $paid_amount_in_main_currency
 * @property numeric $total_paid_amount
 * @property numeric $total_paid_amount_in_main_currency
 * @property numeric $total_deductions
 * @property numeric $total_deductions_in_main_currency
 * @property string|null $net_balance
 * @property float|null $net_balance_in_main_currency
 * @property int|null $is_period_closed
 * @property int|null $is_canceled
 * @property string|null $contract_date
 * @property string|null $contract_code
 * @property string|null $contract_name
 * @property numeric|null $contract_amount
 * @property \Illuminate\Support\Carbon $created_at
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property numeric|null $discount_amount
 * @property numeric|null $discount_amount_in_main_currency
 * @property string|null $purchases_order_number
 * @property string|null $purchases_order_date
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Deduction> $deductions
 * @property-read int|null $deductions_count
 * @property-read bool|null $deductions_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DueDateHistory> $dueDateHistories
 * @property-read int|null $due_date_histories_count
 * @property-read bool|null $due_date_histories_exists
 * @property-read \App\Models\PaymentSettlement|null $letterOfCreditIssuancePaymentSettlements
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MoneyPayment> $moneyPayment
 * @property-read int|null $money_payment_count
 * @property-read bool|null $money_payment_exists
 * @property-read \App\Models\Partner|null $supplier
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice company()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice onlyCompany($companyId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice onlyCurrency(string $currency)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice onlyForPartner($partnerId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereBusinessSector($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereContractAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereContractCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereContractDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereContractName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereContractedPaymentDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereDiscountAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereDiscountAmountInMainCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereExchangeRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereInvoiceAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereInvoiceAmountInMainCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereInvoiceDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereInvoiceDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereInvoiceMonth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereInvoiceNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereInvoiceStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereInvoiceYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereIsCanceled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereIsPeriodClosed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereNetBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereNetBalanceInMainCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereNetInvoiceAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereNetInvoiceAmountInMainCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereOdooId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereOdooPaidAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereOdooPaidAmountInMainCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereOdooWithholdAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereOdooWithholdAmountInMainCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereOpeningBalanceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice wherePaidAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice wherePaidAmountInMainCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereProjectName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice wherePurchasesOrderDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice wherePurchasesOrderNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereSalesPerson($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereSiteName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereSupplierCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereSupplierId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereSupplierName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereTotalDeductions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereTotalDeductionsInMainCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereTotalPaidAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereTotalPaidAmountInMainCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereTotalWithholdAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereTotalWithholdAmountInMainCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereVatAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereVatAmountInMainCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereWithholdAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\SupplierInvoice whereWithholdAmountInMainCurrency($value)
 * @mixin \Eloquent
 */
class SupplierInvoice extends Model implements IInvoice
{
    use StaticBoot , IsInvoice;
    
    
    protected $dates = [
		
    ];
	
	const UNAPPLIED_SETTLEMENT_TABLE = 'paymentSettlements';
	const CLIENT_NAME_COLUMN_NAME = 'supplier_name';
	const CLIENT_ID_COLUMN_NAME = 'supplier_id';
	const JS_FILE = 'money-payment.js';
	const RECEIVED_OR_PAYMENT_AMOUNT = 'paid_amount';
	
	const RECEIVING_OR_PAYMENT_DATE_COLUMN_NAME = 'delivery_date';
	const MONEY_RECEIVED_OR_PAYMENT_TABLE_NAME = 'money_payments';
	const MONEY_RECEIVED_OR_PAYMENT_TABLE_FOREIGN_NAME = 'money_payment_id';
	const TABLE_NAME = 'supplier_invoices';
	const COLLETED_OR_PAID = 'paid';
	const COLLETED_OR_PAID_AMOUNT = 'paid_amount';
	const ODOO_COLLETED_OR_PAID_AMOUNT = 'odoo_paid_amount';
	const ODOO_COLLETED_OR_PAID_AMOUNT_IN_MAIN_CURRENCY = 'odoo_paid_amount_in_main_currency';
	const COLLETED_OR_PAID_AMOUNT_IN_MAIN_CURRENCY = 'paid_amount_in_main_currency';
	const PARTIALLY_COLLECTED_OR_PAID_AND_PAST_DUE = 'partially_paid_and_past_due';
	const MONEY_MODEL_NAME = 'MoneyPayment';
	const IS_CUSTOMER_OR_SUPPLIER = 'is_supplier';
	const AGING_CHEQUE_MODEL_NAME = 'PayableCheque';
	const AGING_CHEQUE_TABLE_NAME = 'payable_cheques';
	const DOWN_PAYMENT_SETTLEMENT_MODEL_NAME ='DownPaymentMoneyPaymentSettlement';
	const DOWN_PAYMENT_SETTLEMENT_TABLE_NAME ='down_payment_money_payment_settlements';
	const SO_OR_PO_NUMBER ='purchases_order_number';
    protected $guarded = [];
	
	public function getClientDisplayName()
	{
		return __('Suppliers');
	}
	
	public function getCustomerOrSupplierAgingText()
	{
		return __('Suppliers Invoice Aging');
	}
	public function getAgingTitle()
	{
		return __('Supplier Aging Form');
	}
	public function getEffectivenessTitle()
	{
		return __('Payment Effectiveness Index Form');
	}
	public function getEffectivenessText()
	{
		return __('Payment Effectiveness Index');
	}
	public function getBalancesTitle()
	{
		return __('Supplier Balances');
	}
	public function getClientNameText()
	{
		return __('Supplier Name');
	}
	public function getMoneyReceivedOrPaidUrlName()
	{
		return 'create.money.payment';
	}
	public function getMoneyReceivedOrPaidText()
	{
		return __('Money Payments');
	}
	public function getCustomerOrSupplierStatementText()
	{
		return __('Supplier Statement');
	}
   
	public function getSupplierName()
    {
        return $this->getName() ;
    }
	public function getName()
	{
		return $this->supplier_name;
	}
	// do not use this directly use 
    public function moneyPayment()
    {
        return $this->hasMany(MoneyPayment::class, 'supplier_id', 'partner_id');
    }
	public function getPaidAmountAttribute($val)
    {
        return $val ;
    }
	public function getSupplierId()
    {
        return $this->supplier_id ;
    }
   
	
	public function isPaid()
	{
		return $this->getStatus() === self::COLLETED_OR_PAID; 
 	}
	
	 public static function hasProjectNameColumn()
	 {
		 return DB::table('supplier_invoices')->where('company_id',getCurrentCompanyId())->where('project_name','!=',null)->count();
	 }

	public function getNetBalanceUntil(string $date)
	{
		$invoiceId = $this->getId();
		$partnerId = $this->getSupplierId();
		$netInvoiceAmount = $this->getNetInvoiceAmount();
		$totalWithhold = $this->getWithholdAmount();
		$totalPaid = 0 ;
		$payments = $this->moneyPayment->where(self::RECEIVING_OR_PAYMENT_DATE_COLUMN_NAME,'<=',$date) ;
		foreach($payments as $moneyPayment) {
			foreach($moneyPayment->getSettlementsForInvoiceNumber($invoiceId, $partnerId)  as $settlement) {
				$totalPaid += $settlement->getAmount();
			}
		}
		return $netInvoiceAmount - $totalPaid - $totalWithhold;
	}
	


	
	public function supplier()
	{
		return $this->belongsTo(Partner::class,self::CLIENT_ID_COLUMN_NAME,'id');
	}
	public function getPartnerId():int
	{
		return $this->supplier_id;
	}	
	public static function formatInvoices(array $invoices,int $inEditMode,$moneyPayment):array 
	{
		$result = [];
		foreach($invoices as $index=>$invoiceArr){
			if($inEditMode && $invoiceArr['settlement_amount'] == 0 && $invoiceArr['net_balance'] == 0 ){
				continue ;
			}
			$result[$index]['id'] = $invoiceArr['id'];
			$result[$index]['invoice_number'] = $invoiceArr['invoice_number'];
			$result[$index]['currency'] = $invoiceArr['currency'];
			$result[$index]['net_invoice_amount'] = $invoiceArr['net_invoice_amount'];
			$currentSettlementAmount = $invoiceArr['settlement_amount'] ?? 0 ;
			$currentSettlementAmount = (double) $currentSettlementAmount ;
			$result[$index]['paid_amount'] = $inEditMode 	?  (double)$invoiceArr['total_paid_amount'] - $currentSettlementAmount  : (double)$invoiceArr['total_paid_amount'];
			$result[$index]['net_balance'] = $inEditMode ? $invoiceArr['net_balance'] +  $currentSettlementAmount  + (double) $invoiceArr['withhold_amount'] : $invoiceArr['net_balance']  ;
			$result[$index]['settlement_amount'] = $inEditMode ? $currentSettlementAmount : 0;
			$result[$index]['withhold_amount'] = $inEditMode ? $invoiceArr['withhold_amount'] : 0;
			$result[$index]['invoice_date'] = Carbon::make($invoiceArr['invoice_date'])->format('d-m-Y');
			$result[$index]['invoice_due_date'] = Carbon::make($invoiceArr['invoice_due_date'])->format('d-m-Y');
			$result[$index]['settlement_allocations'] = $inEditMode ? $moneyPayment->settlementAllocations->where('invoice_id',$invoiceArr['id'])->map(function(SettlementAllocation $settlementAllocation){
				$settlementAllocation->contract_code = $settlementAllocation->contract->getCode();
				$settlementAllocation->contract_amount = $settlementAllocation->contract->getAmountWithCurrency();
				return $settlementAllocation;
			}) : [];
			
			
			
		}
		return $result;
	}
	// public static function getSettlementsTemplate()
	// {
	// 	return '
	// 	<div class=" kt-margin-b-10 border-class">
	// 	<div class="form-group row align-items-end">

	// 		<div class="col-md-1 width-10">
	// 			<label> '. __('Invoice Number') .' </label>
	// 			<div class="kt-input-icon">
	// 				<div class="kt-input-icon">
	// 					<div class="input-group date">
	// 						<input type="hidden" name="settlements[][invoice_id]" value="0" class="js-invoice-id">
	// 						<input readonly class="form-control js-invoice-number" data-invoice-id="0" name="settlements[][invoice_number]" value="0">
	// 					</div>
	// 				</div>
	// 			</div>
	// 		</div>


	// 		<div class="col-md-1 width-9">
	// 			<label>'.__('Invoice Date').'</label>
	// 			<div class="kt-input-icon">
	// 				<div class="input-group date">
	// 					<input name="settlements[][invoice_date]" type="text" class="form-control js-invoice-date" disabled />
						
	// 				</div>
	// 			</div>
	// 		</div>
			
	// 		<div class="col-md-1 width-9">
	// 			<label>'.__('Due Date').'</label>
	// 			<div class="kt-input-icon">
	// 				<div class="input-group date">
	// 					<input name="settlements[][invoice_due_date]" type="text" class="form-control js-invoice-due-date" disabled />
						
	// 				</div>
	// 			</div>
	// 		</div>
			

	// 		<div class="col-md-1 width-8">
	// 			<label>'.__('Currency').' </label>
	// 			<div class="kt-input-icon">
	// 				<input name="settlements[][currency]" type="text" disabled class="form-control js-currency">
	// 			</div>
	// 		</div>

	// 		<div class="col-md-1 width-12">
	// 			<label> '.__('Net Invoice Amount').' </label>
	// 			<div class="kt-input-icon">
	// 				<input name="settlements[][net_invoice_amount]" type="text" disabled class="form-control js-net-invoice-amount">
	// 			</div>
	// 		</div>


	// 		<div class="col-md-2 width-12">
	// 			<label> '. __('Paid Amount') .' </label>
	// 			<div class="kt-input-icon">
	// 				<input name="settlements[][paid_amount]" type="text" disabled class="form-control js-paid-amount">
	// 			</div>
	// 		</div>

	// 		<div class="col-md-2 width-12">
	// 			<label> '. __('Net Balance') .' </label>
	// 			<div class="kt-input-icon">
	// 				<input name="settlements[][net_balance]" type="text" readonly class="form-control js-net-balance">
	// 			</div>
	// 		</div>



	// 		<div class="col-md-2 width-12">
	// 			<label> '. __('Settlement Amount') .' <span class="text-danger ">*</span></label>
	// 			<div class="kt-input-icon">
	// 				<input name="settlements[][settlement_amount]" placeholder="'.__('Settlement Amount').'" type="text" class="form-control js-settlement-amount only-greater-than-or-equal-zero-allowed settlement-amount-class">
	// 			</div>
	// 		</div>
	// 		<div class="col-md-2 width-12">
	// 			<label>'. __('Withhold Amount') .' <span class="text-danger ">*</span> </label>
	// 			<div class="kt-input-icon">
	// 				<input name="settlements[][withhold_amount]" placeholder="'.__('Withhold Amount').'" type="text" class="form-control js-withhold-amount only-greater-than-or-equal-zero-allowed ">
	// 			</div>
	// 		</div>

	// 	</div>

	// 	</div>
		
	// 	';
	// }

	public static function getCurrencies():array 
	{
		return DB::table('supplier_invoices')
		->select('currency')
		->where('currency','!=','')
		->where('company_id',getCurrentCompanyId())
		->get()
		->unique('currency')->pluck('currency','currency')->toArray();
	}
	public static function getSupplierInvoicesUnderCollectionAtDates(array &$result  , int $companyId ,array $datesWithWeekNumber,string $startDate,string $endDate  ):void
	{
		$key = __('Suppliers Invoices') ;
		$items = self::where('company_id',$companyId)
		// ->where('currency',$currency)
		->where('net_balance','>',0)
	
		->whereBetween('invoice_due_date',[$startDate,$endDate])->get();
		
		foreach($items as $item){
			$sum = $item->net_balance_in_main_currency ; 
			$currentWeekYear = $datesWithWeekNumber[$item->invoice_due_date] ;
			$invoiceNumber = $item->invoice_number . ' [ ' . $item->supplier_name . ' ]' ; 
			$invoiceNumber = __('Invoice No.') . ' ' .  $invoiceNumber;
			$result['suppliers'][$key][$invoiceNumber]['weeks'][$currentWeekYear] = isset($result['suppliers'][$key][$invoiceNumber]['weeks'][$currentWeekYear]) ? $result['suppliers'][$key][$invoiceNumber]['weeks'][$currentWeekYear] + $sum :  $sum;
			$result['suppliers'][$key][$invoiceNumber]['total'] = isset($result['suppliers'][$key][$invoiceNumber]['total']) ? $result['suppliers'][$key][$invoiceNumber]['total']  + $sum : $sum;
			$currentTotal = $sum;
			$result['suppliers'][$key]['total'][$currentWeekYear] = isset($result['suppliers'][$key]['total'][$currentWeekYear]) ? $result['suppliers'][$key]['total'][$currentWeekYear] +  $currentTotal : $currentTotal ;
		}
	
	}
	public static function getSupplierInvoicesForPoUnderCollectionAtDates(array &$result  , int $companyId ,array $datesWithWeekNumber,string $startDate,string $endDate  , $poAllocations  , &$pastDueSupplierInvoicesForContracts = []  ):void
	{
		$key = __('Suppliers Invoices') ;
	
		foreach($poAllocations as $poAllocation){
			$purchaseOrderNumber = $poAllocation->po_number;
			$supplierContractCode = $poAllocation->code;
			$allocationPercentage = $poAllocation->allocation_percentage / 100;

			$items = self::where('company_id',$companyId)
			// ->where('currency',$currency)
			->where('net_balance','>',0)
			->where('contract_code',$supplierContractCode)
			->where('purchases_order_number',$purchaseOrderNumber)
			->whereBetween('invoice_due_date',[$startDate,$endDate])
			->get();
		
			foreach($items as $item){
				$invoiceDueDate = $item->invoice_due_date ;
				$invoiceDueDate = Carbon::make($invoiceDueDate);
				if($invoiceDueDate->lessThan(now())){
					$pastDueSupplierInvoicesForContracts[] = $item ;
				}else{
					$sum = $item->net_balance_in_main_currency * $allocationPercentage ; 
					$currentWeekYear = $datesWithWeekNumber[$item->invoice_due_date] ;
					$invoiceNumber = $item->invoice_number . ' [ ' . $item->supplier_name . ' ]' ; 
					$invoiceNumber = __('Invoice No.') . ' ' .  $invoiceNumber;
					$result['suppliers'][$key][$invoiceNumber]['weeks'][$currentWeekYear] = isset($result['suppliers'][$key][$invoiceNumber]['weeks'][$currentWeekYear]) ? $result['suppliers'][$key][$invoiceNumber]['weeks'][$currentWeekYear] + $sum :  $sum;
					$result['suppliers'][$key][$invoiceNumber]['total'] = isset($result['suppliers'][$key][$invoiceNumber]['total']) ? $result['suppliers'][$key][$invoiceNumber]['total']  + $sum : $sum;
					$currentTotal = $sum;
					$result['suppliers'][$key]['total'][$currentWeekYear] = isset($result['suppliers'][$key]['total'][$currentWeekYear]) ? $result['suppliers'][$key]['total'][$currentWeekYear] +  $currentTotal : $currentTotal ;
				
				}
				
				
		  }
		
		}
		
		
		
	
	}
	public function letterOfCreditIssuancePaymentSettlements()
	{
		return $this->hasOne(PaymentSettlement::class,'invoice_id','id')->where('letter_of_credit_issuance_id','!=',null);
	}
	public function getDeleteByDateColumnName()
	{
		return 'invoice_date';
	}
	public static function getForecastedProjectCollection(array &$result  , string $startDate , string $endDate , $currency  , $companyId  , array $datesWithWeekNumber , ?int $contractId = null):void
	{
		/**
		 * 
		 * * في حالة لو مرر العقد فا مش محتاجين عمله لان العقد الواحد مربوط بعملة واحدة
		 */
		$key = 'Forecasted Suppliers Contract Payments';

		
		$contracts = Contract::where('company_id',$companyId)
		->where('end_date','<=',$endDate)
		->where('currency',$currency)
		->when($contractId,function($query) use ($contractId){
			$query->where('id',$contractId);
		})
		->with('purchasesOrders')->get();
		
		$contractWithPurchaseOrders = [];
		foreach($contracts as $contract){
			foreach($contract->purchasesOrders as $purchaseOrder){
				$contractWithPurchaseOrders[$contract->id][$purchaseOrder->id] = [
					'contract'=>$contract ,
					'purchase_orders'=>HArr::getLatestNonZeroExecutionKeys($purchaseOrder->toArray())
				];
			}
		}
		
		foreach($contractWithPurchaseOrders as $contractId => $contractWithSos){
			foreach($contractWithSos as $soId => $ContractWithSoArr){
				$contract = $ContractWithSoArr['contract'];
				$soArr = $ContractWithSoArr['purchase_orders'];
				$soEndDate = $soArr['end_date'];
				$soCollectionDays = $soArr['collection_days'];
				$currentSoCollectionDays = Carbon::make($soEndDate)->addDays($soCollectionDays);
				$isBetweenViewInterval = $currentSoCollectionDays->between($startDate,$endDate);
				if(!$isBetweenViewInterval){
					continue;
				}
				$currentSoCollectionDaysFormatted = $currentSoCollectionDays->format('Y-m-d');
				$currentWeekYear =$datesWithWeekNumber[$currentSoCollectionDaysFormatted];
				$purchaseOrderAmount = $soArr['amount'];
				$contractCode = $contract->getCode();
				$contractName = $contract->getName();
				$poNumber = $soArr['po_number']; 
				$customerName = $contract->getClientName();
				$currentInvoiceAmount = DB::table('supplier_invoices')->where('company_id',$companyId)->where('currency',$currency)->where('purchases_order_number',$poNumber)->where('contract_code',$contractCode)->sum('invoice_amount');
				
				$salesOrderDownPayments = DB::table('down_payment_money_payment_settlements')->where('company_id',$companyId)->where('purchase_order_id',$soId)->where('contract_id',$contractId)->sum('down_payment_amount');
				$purchaseOrderNetPayments = $salesOrderDownPayments - $currentInvoiceAmount;
				
				$purchaseOrderNetBalance = 0 ;
				if($purchaseOrderNetPayments > $purchaseOrderAmount){
					$purchaseOrderNetBalance = 0;	
				}else{
					$purchaseOrderNetBalance = $purchaseOrderAmount - $purchaseOrderNetPayments;
				}
				$invoiceNumber =   $customerName . '-' . $contractName  ;
				$result['suppliers'][$key][$invoiceNumber]['weeks'][$currentWeekYear] = isset($result['suppliers'][$key][$invoiceNumber]['weeks'][$currentWeekYear]) ? $result['suppliers'][$key][$invoiceNumber]['weeks'][$currentWeekYear]+  $purchaseOrderNetBalance :$purchaseOrderNetBalance;
				$result['suppliers'][$key][$invoiceNumber]['total'] = isset($result['suppliers'][$key][$invoiceNumber]['total']) ? $result['suppliers'][$key][$invoiceNumber]['total']  + $purchaseOrderNetBalance : $purchaseOrderNetBalance;
				// $currentTotal = $purchaseOrderNetBalance;
				// $result['suppliers'][$key]['total'][$currentWeekYear] = isset($result['suppliers'][$key]['total'][$currentWeekYear]) ? $result['suppliers'][$key]['total'][$currentWeekYear] +  $currentTotal : $currentTotal ;
				$result['suppliers'][$key]['total'][$currentWeekYear] = isset($result['suppliers'][$key]['total'][$currentWeekYear]) ? $result['suppliers'][$key]['total'][$currentWeekYear] + $purchaseOrderNetBalance : $purchaseOrderNetBalance;
			}
		}
	}
	public static function getForecastedProjectPayment(array &$result   , string $startDate , string $endDate , $currency  , $companyId  , array $datesWithWeekNumber ,?int $contractId = null):void
	{
		/**
		 * 
		 * * في حالة لو مرر العقد فا مش محتاجين عمله لان العقد الواحد مربوط بعملة واحدة
		 */
		// $totalCashInFlowKey = __('Total Cash Inflow');
		
		$key =  'Forecasted Project Payment';
		$contracts = Contract::where('company_id',$companyId)
		->where('end_date','>=',now()->format('Y-m-d'))
		->where('end_date','<=',$endDate)
		->where('currency',$currency)
		->when($contractId,function($query) use ($contractId){
			$query->where('id',$contractId);
		})
		// ->where('end_date','<=',now()->format('Y-m-d'))
		->with('salesOrders')->get();
		$contractWithSalesOrders = [];
		foreach($contracts as $contract){
			foreach($contract->salesOrders as $salesOrder){
				$contractWithSalesOrders[$contract->id][$salesOrder->id] = [
					'contract'=>$contract ,
					'sales_orders'=>HArr::getLatestNonZeroExecutionKeys($salesOrder->toArray())
				];
			}
		}
		
		foreach($contractWithSalesOrders as $contractId => $contractWithSos){
			foreach($contractWithSos as $soId => $ContractWithSoArr){
				$contract = $ContractWithSoArr['contract'];
				$soArr = $ContractWithSoArr['sales_orders'];
				$soEndDate = $soArr['end_date'];
				$soCollectionDays = $soArr['collection_days'];
				$currentSoCollectionDays = Carbon::make($soEndDate)->addDays($soCollectionDays);
				$isBetweenViewInterval = $currentSoCollectionDays->between($startDate,$endDate);
				if(!$isBetweenViewInterval){
					continue;
				}
				$currentSoCollectionDaysFormatted = $currentSoCollectionDays->format('Y-m-d');
				$currentWeekYear =$datesWithWeekNumber[$currentSoCollectionDaysFormatted];
				$salesOrderAmount = $soArr['amount'];
				$contractCode = $contract->getCode();
				$contractName = $contract->getName();
				$soNumber = $soArr['so_number']; 
				$customerName = $contract->getClientName();
				$currentInvoiceAmount = DB::table('customer_invoices')->where('company_id',$companyId)->where('currency',$currency)->where('sales_order_number',$soNumber)->where('contract_code',$contractCode)->sum('invoice_amount');
				$salesOrderNetBalance = 0 ;
				if($currentInvoiceAmount > $salesOrderAmount){
					$salesOrderNetBalance = 0;	
				}else{
					$salesOrderNetBalance = $salesOrderAmount - $currentInvoiceAmount;
				}
				$invoiceNumber =   $customerName . '-' . $contractName  ;
				$result['suppliers'][$key][$invoiceNumber]['weeks'][$currentWeekYear] = isset($result['suppliers'][$key][$invoiceNumber]['weeks'][$currentWeekYear]) ? $result['suppliers'][$key][$invoiceNumber]['weeks'][$currentWeekYear]+  $salesOrderNetBalance :$salesOrderNetBalance;
				$result['suppliers'][$key][$invoiceNumber]['total'] = isset($result['suppliers'][$key][$invoiceNumber]['total']) ? $result['suppliers'][$key][$invoiceNumber]['total']  + $salesOrderNetBalance : $salesOrderNetBalance;
				// $currentTotal = $salesOrderNetBalance;
				// $result['suppliers'][$key]['total'][$currentWeekYear] = isset($result['suppliers'][$key]['total'][$currentWeekYear]) ? $result['suppliers'][$key]['total'][$currentWeekYear] +  $currentTotal : $currentTotal ;
				// $result['suppliers'][$totalCashOutFlowKey]['total'][$currentWeekYear] = isset($result['suppliers'][$totalCashInFlowKey]['total'][$currentWeekYear]) ? $result['suppliers'][$totalCashInFlowKey]['total'][$currentWeekYear] + $salesOrderNetBalance : $salesOrderNetBalance;
				
				
			}
		}

		
			
	}
	
public function getPurchasesOrderNumber()
	{
		return $this->purchases_order_number;
	}	
	public function getProjectName()
	{
		return $this->project_name ?: '--';
	}
	
	public function getCollectedOrPaidAmount()
	{
		return $this->paid_amount?:0;
	}
	
	public function getTotalCollectedOrPaid()
	{
		return (float)$this->total_paid_amount ; 
	}
	
}
