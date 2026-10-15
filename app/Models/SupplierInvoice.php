<?php

namespace App\Models;

use App\Helpers\HArr;
use App\Interfaces\Models\IInvoice;
use App\Traits\Models\HasForecastedProjectCollection;
use App\Traits\Models\IsInvoice;
use App\Traits\StaticBoot;
use Carbon\Carbon;
use App\Traits\Models\CannotBeDeletedWhileSettled;
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
 * @property numeric $excel_paid_amount
 * @property numeric $excel_paid_amount_in_main_currency
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
    use StaticBoot , IsInvoice, HasForecastedProjectCollection, CannotBeDeletedWhileSettled;
    
    
    protected $dates = [
		
    ];
	
	const UNAPPLIED_SETTLEMENT_TABLE = 'paymentSettlements';
	const CLIENT_NAME_COLUMN_NAME = 'supplier_name';
	const CLIENT_ID_COLUMN_NAME = 'supplier_id';
	const RECEIVED_OR_PAYMENT_AMOUNT = 'paid_amount';
	
	const RECEIVING_OR_PAYMENT_DATE_COLUMN_NAME = 'delivery_date';
	const MONEY_RECEIVED_OR_PAYMENT_TABLE_NAME = 'money_payments';
	const MONEY_RECEIVED_OR_PAYMENT_TABLE_FOREIGN_NAME = 'money_payment_id';
	const TABLE_NAME = 'supplier_invoices';
	const COLLETED_OR_PAID = 'paid';
	const COLLETED_OR_PAID_AMOUNT = 'paid_amount';
	const ODOO_COLLETED_OR_PAID_AMOUNT = 'odoo_paid_amount';
	const ODOO_COLLETED_OR_PAID_AMOUNT_IN_MAIN_CURRENCY = 'odoo_paid_amount_in_main_currency';
	const EXCEL_COLLETED_OR_PAID_AMOUNT = 'excel_paid_amount';
	const EXCEL_COLLETED_OR_PAID_AMOUNT_IN_MAIN_CURRENCY = 'excel_paid_amount_in_main_currency';

	/**
	 * The "Excel Paid Amount" upload template column was renamed to
	 * "Previous Payments" (view-only change, tables_fields.view_name).
	 * This keeps files/templates that still use the old header text
	 * importing correctly — App\Imports\ImportData::resolveImportFieldValue()
	 * checks this before giving up on a column.
	 */
	public static function getImportHeaderAliases(): array
	{
		return [
			self::EXCEL_COLLETED_OR_PAID_AMOUNT => ['Excel Paid Amount', __('Excel Paid Amount')],
		];
	}
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
			$result[$index]['invoice_date'] = $invoiceArr['invoice_date'] ? Carbon::make($invoiceArr['invoice_date'])->format('d-m-Y') : null;
			$result[$index]['invoice_due_date'] = $invoiceArr['invoice_due_date'] ? Carbon::make($invoiceArr['invoice_due_date'])->format('d-m-Y') : null;
			$result[$index]['settlement_allocations'] = $inEditMode ? $moneyPayment->settlementAllocations->where('invoice_id',$invoiceArr['id'])->map(function(SettlementAllocation $settlementAllocation){
				// contract_id is nullable — most settlements (anything not
				// tied to a Letter of Credit/contract) have no contract at
				// all, so ->contract is null here far more often than not.
				// Calling ->getCode()/->getAmountWithCurrency() on it
				// unconditionally was a fatal "call to member function on
				// null" on every single edit that included such a row —
				// which is effectively every normal supplier payment edit.
				$settlementAllocation->contract_code = $settlementAllocation->contract?->getCode();
				$settlementAllocation->contract_amount = $settlementAllocation->contract?->getAmountWithCurrency();
				return $settlementAllocation;
			}) : [];
			
			
			
		}
		// Same fix as CustomerInvoice::formatInvoices() — $result keeps
		// gaps in its keys every time `continue` skips a row, so
		// json_encode() would send a JSON OBJECT instead of a JSON
		// ARRAY, breaking the Vue form's data.invoices.map(...) with
		// "Couldn't load invoices". array_values() re-numbers from 0.
		return array_values($result);
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
	public static function getSupplierInvoicesUnderCollectionAtDates(array &$result  , int $companyId ,array $datesWithWeekNumber,string $startDate,string $endDate , ?string $currency = null , ?string $mainFunctionalCurrency = null ):void
	{
		$key = __('Suppliers Invoices') ;
		// This method is only ever called for the Company (non-contract)
		// report. Main functional currency tab -> keep every currency
		// (net_balance_in_main_currency is already the converted
		// equivalent); a specific foreign-currency tab -> that currency only.
		/**
		 * * كل صفوف التقرير على مستوى الشركة و بالعملة الوظيفية دايما .
		 * * اختيار العملة وظيفته يفلتر العقود بس — مش يضيّق باقي الصفوف
		 * * و لا يغيّر وحدة العرض .
		 */
		$items = self::where('company_id',$companyId)
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
	/**
	 * * صف "Suppliers Invoices" في تقرير كاش فلو العقد .
	 *
	 * * فاتورة المورد بتحمل كود عقد **المورد** ، مش كود عقد العميل اللي
	 * * التقرير معروض عليه . فلازم نوصل لأوامر الشراء بتاعة عقد العميل
	 * * الأول و بعدين نجيب فواتيرها .
	 *
	 * * و فيه طريقتين بيترابطوا بيهم — و الدالة دي كانت بتعرف واحدة بس :
	 * *
	 * *   1. po_allocations : الربط الصريح الاختياري (مودال "Allocate"
	 * *      على أمر الشراء) . أمر شراء واحد ممكن يتقسّم على أكتر من عقد
	 * *      عميل ، فكل فاتورة بتتحسب مضروبة في allocation_percentage .
	 * *
	 * *   2. contracts.parent_id : الربط **العادي** — عقد مورد متعمل تحت
	 * *      عقد العميل ، و ده اللي بيظهر في بوب اب "عقود الموردين" .
	 * *      العقد ده بيخص عقد العميل لوحده ، فوزنه 100% .
	 *
	 * * الطريقة التانية كانت ناقصة خالص : عقد عميل بـ 10,000 تحته عقد
	 * * مورد بـ 5,000 و عليه فاتورة بـ 5,000 لسه ما اتدفعتش ، كان بيطلع
	 * * الصف فاضي . و في نفس الوقت صف "Forecasted Project Payment" —
	 * * اللي بيمشي على parent_id فعلاً — بينزل لصفر لأن الفاتورة اتخصمت
	 * * منه . فالـ 5,000 كانت بتختفي من التقرير بالكامل .
	 *
	 * * المفروض : الفاتورة تظهر هنا بقيمتها في ميعاد استحقاقها ، و الفرق
	 * * بين قيمة العقد و الفاتورة يفضل في صف التوقّعات (عقد بـ 10,000
	 * * عليه فاتورة بـ 7,000 → 7,000 هنا و 3,000 هناك) .
	 *
	 * @param  int|null  $contractId  عقد العميل المعروض — من غيره ما ينفعش
	 *                                نوصل لعقود الموردين الأبناء
	 */
	public static function getSupplierInvoicesForPoUnderCollectionAtDates(array &$result  , int $companyId ,array $datesWithWeekNumber,string $startDate,string $endDate  , $poAllocations  , &$pastDueSupplierInvoicesForContracts = []  , ?int $contractId = null ):void
	{
		$allocatedPurchaseOrderIds = [];

		foreach($poAllocations as $poAllocation){
			$allocatedPurchaseOrderIds[] = (int) $poAllocation->purchase_order_id;

			self::addSupplierInvoicesOfPurchaseOrder(
				$result, $companyId, $datesWithWeekNumber, $startDate, $endDate,
				$poAllocation->code, $poAllocation->po_number,
				$poAllocation->allocation_percentage / 100,
				$pastDueSupplierInvoicesForContracts
			);
		}

		if(!$contractId){
			return ;
		}

		// * أوامر الشراء بتاعة عقود الموردين الأبناء . أمر الشراء اللي
		// * مربوط بالطريقتين بيتعدّ مرة واحدة و صف الـ allocation بيكسب
		// * (هو اللي شايل النسبة) — نفس القاعدة بالظبط اللي في
		// * HasForecastedProjectCollection .
		$childSupplierContracts = Contract::where('company_id',$companyId)
			->where('parent_id',$contractId)
			->where('model_type','Supplier')
			->with('purchasesOrders')
			->get();

		foreach($childSupplierContracts as $supplierContract){
			foreach($supplierContract->purchasesOrders as $purchaseOrder){
				if(in_array((int) $purchaseOrder->id, $allocatedPurchaseOrderIds, true)){
					continue;
				}

				self::addSupplierInvoicesOfPurchaseOrder(
					$result, $companyId, $datesWithWeekNumber, $startDate, $endDate,
					$supplierContract->getCode(), $purchaseOrder->po_number,
					1.0,
					$pastDueSupplierInvoicesForContracts
				);
			}
		}
	}

	/**
	 * * فواتير أمر شراء واحد في صف "Suppliers Invoices" .
	 *
	 * * مشتركة بين مسار po_allocations (بوزن allocation_percentage) و
	 * * مسار عقود الموردين الأبناء (بوزن 100%) . الفواتير المتأخرة
	 * * بتتشال من هنا و بتروح لصف "Suppliers Past Due Invoices" .
	 *
	 * * مفيش فلتر عملة : net_balance_in_main_currency أصلاً محوّل
	 * * للعملة الرئيسية ، و عقد المورد بيفوتر بعملته هو اللي مش شرط
	 * * تكون عملة عقد العميل .
	 */
	private static function addSupplierInvoicesOfPurchaseOrder(array &$result , int $companyId , array $datesWithWeekNumber , string $startDate , string $endDate , string $supplierContractCode , ?string $purchaseOrderNumber , float $weight , &$pastDueSupplierInvoicesForContracts):void
	{
		$key = __('Suppliers Invoices') ;

		if($weight <= 0 || !$purchaseOrderNumber){
			return ;
		}

		$items = self::where('company_id',$companyId)
		->where('net_balance','>',0)
		->where('contract_code',$supplierContractCode)
		->where('purchases_order_number',$purchaseOrderNumber)
		->whereBetween('invoice_due_date',[$startDate,$endDate])
		->get();

		foreach($items as $item){
			$invoiceDueDate = Carbon::make($item->invoice_due_date) ;
			if($invoiceDueDate->lessThan(now())){
				$pastDueSupplierInvoicesForContracts[] = $item ;
				continue;
			}

			if(!isset($datesWithWeekNumber[$item->invoice_due_date])){
				continue;
			}

			$sum = $item->net_balance_in_main_currency * $weight ;
			$currentWeekYear = $datesWithWeekNumber[$item->invoice_due_date] ;
			$invoiceNumber = $item->invoice_number . ' [ ' . $item->supplier_name . ' ]' ;
			$invoiceNumber = __('Invoice No.') . ' ' .  $invoiceNumber;
			$result['suppliers'][$key][$invoiceNumber]['weeks'][$currentWeekYear] = isset($result['suppliers'][$key][$invoiceNumber]['weeks'][$currentWeekYear]) ? $result['suppliers'][$key][$invoiceNumber]['weeks'][$currentWeekYear] + $sum :  $sum;
			$result['suppliers'][$key][$invoiceNumber]['total'] = isset($result['suppliers'][$key][$invoiceNumber]['total']) ? $result['suppliers'][$key][$invoiceNumber]['total']  + $sum : $sum;
			$result['suppliers'][$key]['total'][$currentWeekYear] = isset($result['suppliers'][$key]['total'][$currentWeekYear]) ? $result['suppliers'][$key]['total'][$currentWeekYear] +  $sum : $sum ;
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
	public static function getForecastedProjectCollection(array &$result  , string $startDate , string $endDate , $currency  , $companyId  , array $datesWithWeekNumber , ?int $contractId = null , $foreignExchangeRates = null , ?string $mainFunctionalCurrency = null , ?\Illuminate\Support\Collection $poAllocations = null):void
	{
		// See HasForecastedProjectCollection trait for the full formula
		// and why it's shared with CustomerInvoice::getForecastedProjectCollection.
		static::computeForecastedProjectCollection(
			$result,
			$startDate,
			$endDate,
			$currency,
			$companyId,
			$datesWithWeekNumber,
			$contractId,
			$foreignExchangeRates,
			$mainFunctionalCurrency,
			[
				'main_result_type' => 'suppliers',
				'result_key' => 'Forecasted Suppliers Contract Payments',
				'invoice_table' => 'supplier_invoices',
				'order_relation' => 'purchasesOrders',
				'order_number_key' => 'po_number',
				'invoice_order_number_column' => 'purchases_order_number',
				'down_payment_table' => 'down_payment_money_payment_settlements',
				'down_payment_order_id_column' => 'purchase_order_id',
				'add_to_cash_inflow_total' => false,
				'paid_or_collected_status' => self::COLLETED_OR_PAID,
				'invoice_settled_column' => 'total_paid_amount',
				'invoice_settled_main_column' => 'total_paid_amount_in_main_currency',
				'down_payment_money_table' => 'money_payments',
				'down_payment_money_id_column' => 'money_payment_id',
				'down_payment_money_date_column' => 'delivery_date',
			],
			$poAllocations
		);
	}
	/**
	 * * "Forecasted Project Payment" — الوش التاني لـ "Forecasted
	 * * Project Collection": اللي لسه متوقع يتدفع على عقود الموردين
	 * * الشايلينها تحت عقد العميل اللي التقرير اتعمل عليه.
	 *
	 * * اتكتبت من جديد ٢٠٢٦-٠٩. كانت بتقرا salesOrders بتاعة عقد
	 * * العميل نفسه و بتسمّي كل صف فرعي باسم العميل ، فالصف كان نسخة
	 * * شبه طبق الأصل من "Forecasted Project Collection" بس على ناحية
	 * * المصروفات — نفس مبلغ المشروع متحسوب مرتين. دلوقتي بتمشي على
	 * * contracts.parent_id لعقود الموردين (اللي شاشة العقود بتعرضها
	 * * تحت "Supplier Contracts") و بتقرا أوامر الشراء بتاعتهم ، فالصف
	 * * الفرعي بيسمّي المورّد و بيشيل الرصيد المتبقي من أمر الشراء.
	 *
	 * * و باستخدام computeForecastedProjectCollection() المشتركة رجع
	 * * كمان إجمالي الصف الأب لكل فترة ، و ده كان متعلّق عليه كومنت في
	 * * النسخة القديمة — فصف "Forecasted Project Payment" كان بيفضل
	 * * صفر مهما كان تحته أبناء بقيم ، و معاه Total Cash Outflow و
	 * * Net Cash و Accumulated لأنهم بيجمعوا إجماليات الصفوف الأب.
	 */
	public static function getForecastedProjectPayment(array &$result   , string $startDate , string $endDate , $currency  , $companyId  , array $datesWithWeekNumber ,?int $contractId = null , $foreignExchangeRates = null , ?string $mainFunctionalCurrency = null):void
	{
		static::computeForecastedProjectCollection(
			$result,
			$startDate,
			$endDate,
			$currency,
			$companyId,
			$datesWithWeekNumber,
			$contractId,
			$foreignExchangeRates,
			$mainFunctionalCurrency,
			[
				'main_result_type' => 'suppliers',
				'result_key' => 'Forecasted Project Payment',
				'invoice_table' => 'supplier_invoices',
				'order_relation' => 'purchasesOrders',
				'order_number_key' => 'po_number',
				'invoice_order_number_column' => 'purchases_order_number',
				'down_payment_table' => 'down_payment_money_payment_settlements',
				'down_payment_order_id_column' => 'purchase_order_id',
				'add_to_cash_inflow_total' => false,
				'paid_or_collected_status' => self::COLLETED_OR_PAID,
				'invoice_settled_column' => 'total_paid_amount',
				'invoice_settled_main_column' => 'total_paid_amount_in_main_currency',
				'down_payment_money_table' => 'money_payments',
				'down_payment_money_id_column' => 'money_payment_id',
				'down_payment_money_date_column' => 'delivery_date',
				'contract_scope' => 'supplier_children',
			]
		);
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
