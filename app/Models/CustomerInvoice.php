<?php

namespace App\Models;

use App\Helpers\HArr;
use App\Interfaces\Models\IInvoice;
use App\Traits\Models\HasForecastedProjectCollection;
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
 * @property string|null $customer_code
 * @property string|null $sales_person
 * @property string|null $business_unit
 * @property int $customer_id
 * @property string|null $customer_name
 * @property string|null $business_sector
 * @property string|null $project_name
 * @property string|null $site_name
 * @property string|null $invoice_date
 * @property string|null $sales_order_date
 * @property string|null $sales_order_number
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
 * @property string|null $contracted_collection_days
 * @property string|null $expected_collection_days
 * @property string|null $invoice_due_date
 * @property string|null $invoice_status
 * @property numeric $odoo_collected_amount
 * @property numeric $odoo_collected_amount_in_main_currency
 * @property numeric $excel_collected_amount
 * @property numeric $excel_collected_amount_in_main_currency
 * @property string|null $collected_amount
 * @property float|null $collected_amount_in_main_currency
 * @property numeric $total_collected_amount
 * @property numeric $total_collected_amount_in_main_currency
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
 * @property-read \App\Models\Partner|null $customer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Deduction> $deductions
 * @property-read int|null $deductions_count
 * @property-read bool|null $deductions_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DueDateHistory> $dueDateHistories
 * @property-read int|null $due_date_histories_count
 * @property-read bool|null $due_date_histories_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MoneyReceived> $moneyReceived
 * @property-read int|null $money_received_count
 * @property-read bool|null $money_received_exists
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice company()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice onlyCompany($companyId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice onlyCurrency(string $currency)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice onlyForPartner($partnerId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereBusinessSector($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereBusinessUnit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereCollectedAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereCollectedAmountInMainCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereContractAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereContractCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereContractDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereContractName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereContractedCollectionDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereCustomerCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereCustomerName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereDiscountAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereDiscountAmountInMainCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereExchangeRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereExpectedCollectionDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereInvoiceAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereInvoiceAmountInMainCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereInvoiceDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereInvoiceDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereInvoiceMonth($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereInvoiceNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereInvoiceStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereInvoiceYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereIsCanceled($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereIsPeriodClosed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereNetBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereNetBalanceInMainCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereNetInvoiceAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereNetInvoiceAmountInMainCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereOdooCollectedAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereOdooCollectedAmountInMainCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereOdooId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereOdooWithholdAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereOdooWithholdAmountInMainCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereOpeningBalanceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereProjectName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereSalesOrderDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereSalesOrderNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereSalesPerson($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereSiteName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereTotalCollectedAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereTotalCollectedAmountInMainCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereTotalDeductions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereTotalDeductionsInMainCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereTotalWithholdAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereTotalWithholdAmountInMainCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereVatAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereVatAmountInMainCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereWithholdAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CustomerInvoice whereWithholdAmountInMainCurrency($value)
 * @mixin \Eloquent
 */
class CustomerInvoice extends Model implements IInvoice
{
    use StaticBoot , IsInvoice, HasForecastedProjectCollection;
 
	
	
	const UNAPPLIED_SETTLEMENT_TABLE = 'settlements';
	const CLIENT_NAME_COLUMN_NAME = 'customer_name';
	const CLIENT_ID_COLUMN_NAME = 'customer_id';
	const RECEIVING_OR_PAYMENT_DATE_COLUMN_NAME = 'receiving_date';
	const MONEY_RECEIVED_OR_PAYMENT_TABLE_NAME = 'money_received';
	const MONEY_RECEIVED_OR_PAYMENT_TABLE_FOREIGN_NAME = 'money_received_id';
	const TABLE_NAME = 'customer_invoices';
	const COLLETED_OR_PAID = 'collected';
	const COLLETED_OR_PAID_AMOUNT = 'collected_amount';
	const ODOO_COLLETED_OR_PAID_AMOUNT = 'odoo_collected_amount';
	const ODOO_COLLETED_OR_PAID_AMOUNT_IN_MAIN_CURRENCY = 'odoo_collected_amount_in_main_currency';
	const EXCEL_COLLETED_OR_PAID_AMOUNT = 'excel_collected_amount';
	const EXCEL_COLLETED_OR_PAID_AMOUNT_IN_MAIN_CURRENCY = 'excel_collected_amount_in_main_currency';

	/**
	 * The "Excel Collected Amount" upload template column was renamed
	 * to "Previous Collection" (view-only change, tables_fields.view_name).
	 * This keeps files/templates that still use the old header text
	 * importing correctly — App\Imports\ImportData::resolveImportFieldValue()
	 * checks this before giving up on a column.
	 */
	public static function getImportHeaderAliases(): array
	{
		return [
			self::EXCEL_COLLETED_OR_PAID_AMOUNT => ['Excel Collected Amount', __('Excel Collected Amount')],
		];
	}
	const COLLETED_OR_PAID_AMOUNT_IN_MAIN_CURRENCY = 'collected_amount_in_main_currency';
	const PARTIALLY_COLLECTED_OR_PAID_AND_PAST_DUE = 'partially_collected_and_past_due';
	const MONEY_MODEL_NAME = 'MoneyReceived';
	const IS_CUSTOMER_OR_SUPPLIER = 'is_customer';
	const AGING_CHEQUE_MODEL_NAME = 'Cheque';
	const AGING_CHEQUE_TABLE_NAME = 'cheques';
	const DOWN_PAYMENT_SETTLEMENT_MODEL_NAME ='DownPaymentSettlement';
	const DOWN_PAYMENT_SETTLEMENT_TABLE_NAME ='down_payment_settlements';
	const SO_OR_PO_NUMBER ='sales_order_number';
    protected $guarded = [];

	public function getClientDisplayName()
	{
		return __('Customers');
	}
	public function getCustomerOrSupplierAgingText()
	{
		return __('Customers Invoice Aging');
	}
	public function getAgingTitle()
	{
		return __('Customer Aging Form');
	}
	public function getEffectivenessTitle()
	{
		return __('Collection Effectiveness Index Form');
	}
	public function getEffectivenessText()
	{
		return __('Collection Effectiveness Index');
	}
	public function getBalancesTitle()
	{
		return __('Customer Balances');
	}
	public function getClientNameText()
	{
		return __('Customer Name');
	}
	public function getMoneyModelName()
	{
		return 'MoneyReceived';
	}
	public function getMoneyReceivedOrPaidUrlName()
	{
		return 'create.money.receive';
	}
	public function getMoneyReceivedOrPaidText()
	{
		return __('Money Received');
	}
	public function getCustomerOrSupplierStatementText()
	{
		return __('Customer Statement');
	}
   
	public function getCustomerName()
    {
        return $this->getName() ;
    }
	// do not use this directly use 
    public function moneyReceived()
    {
        return $this->hasMany(MoneyReceived::class, 'customer_id', 'partner_id');
    }
	public function getPartnerId():int
	{
		return $this->customer_id;
	}
	
	public function getCollectedAmountAttribute($val)
    {
        return $val ;
    }
	public function getCustomerId()
    {
        return $this->customer_id ;
    }
   
	
	public function isCollected()
	{
		return $this->getStatus() === 'collected'; 
 	}
	
	
	
	public function getNetBalanceUntil(string $date)
	{
		$invoiceId = $this->getId();
		$partnerId = $this->getCustomerId();
		$netInvoiceAmount = $this->getNetInvoiceAmount();
		$totalWithhold = $this->getWithholdAmount();
		$totalCollected = 0 ;
		$moneyReceives = $this->moneyReceived->where(self::RECEIVING_OR_PAYMENT_DATE_COLUMN_NAME,'<=',$date) ;
		foreach($moneyReceives as $moneyReceived) {
			foreach($moneyReceived->getSettlementsForInvoiceNumber($invoiceId, $partnerId)  as $settlement) {
				$totalCollected += $settlement->getAmount();
			}
		}
		return $netInvoiceAmount - $totalCollected - $totalWithhold;
	}
	

	
	
	
	public function customer()
	{
		return $this->belongsTo(Partner::class,self::CLIENT_ID_COLUMN_NAME,'id');
	}
	public static function formatInvoices(array $invoices,int $inEditMode):array 
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
			$result[$index]['project_name'] = $invoiceArr['project_name'];

			// $result[$index]['collected_amount'] = $inEditMode 	?  (double)$invoiceArr['collected_amount'] - (double) $invoiceArr['settlement_amount']  : (double)$invoiceArr['collected_amount'];
			$result[$index]['collected_amount'] =  $inEditMode 	?  (double)$invoiceArr['total_collected_amount'] - (double) $invoiceArr['settlement_amount']  : (double)$invoiceArr['total_collected_amount'];
			$result[$index]['net_balance'] = $inEditMode ? $invoiceArr['net_balance'] +  $invoiceArr['settlement_amount']  + (double) $invoiceArr['withhold_amount'] : $invoiceArr['net_balance']  ;
			$result[$index]['settlement_amount'] = $inEditMode ? $invoiceArr['settlement_amount'] : 0;
			$result[$index]['withhold_amount'] = $inEditMode ? $invoiceArr['withhold_amount'] : 0;
			// Null-guarded like the rest of the codebase already does
			// (see IsInvoice::getInvoiceDateFormatted()/getDueDateFormatted()).
			// This path skipped that guard, so any invoice with a null
			// date (data-entry gap, e.g. INV/2025/00092) was a fatal
			// "call to format() on null" that crashed the whole endpoint —
			// most visible on edit, since edit mode's broader query (no
			// net_balance > 0 filter) surfaces more such rows than create
			// mode does.
			$result[$index]['invoice_date'] = $invoiceArr['invoice_date'] ? Carbon::make($invoiceArr['invoice_date'])->format('d-m-Y') : null;
			$result[$index]['invoice_due_date'] = $invoiceArr['invoice_due_date'] ? Carbon::make($invoiceArr['invoice_due_date'])->format('d-m-Y') : null;
		}
		// $result is keyed by the original array's index ($index), which
		// skips a number every time the `continue` above fires (e.g. an
		// already-fully-settled invoice). That leaves gaps like
		// [1 => ..., 2 => ...] instead of [0 => ..., 1 => ...] — and
		// PHP's json_encode() turns any non-sequential-from-0 array into
		// a JSON OBJECT ({"1":...,"2":...}) instead of a JSON ARRAY
		// ([...]). The Vue form does `data.invoices.map(...)`, which only
        // works on a real array — so on a JSON object it threw
		// "data.invoices.map is not a function", which is exactly the
		// "Couldn't load invoices" message. array_values() re-numbers
		// the keys from 0 with no gaps, guaranteeing a JSON array.
		return array_values($result);
	}
	public static function hasProjectNameColumn()
	{
		return DB::table('customer_invoices')->where('company_id',getCurrentCompanyId())->where('project_name','!=',null)->count();
	}
	public function getProjectName()
	{
		return $this->project_name ?: '--';
	}

	public static function getSettlementsTemplate(?string $invoiceNumber = null , ?string $dueDateFormatted = null ,?string $invoiceDueDateFormatted = null , ?string $invoiceCurrency = null , $netInvoiceAmountFormatted = 0 , $collectedAmountFormatted = 0,$netBalanceFormatted = 0 , $settlementAmount = 0 ,$withholdAmount = 0 )
	{
		$projectDiv = "";
		$hasProjectNameColumn = self::hasProjectNameColumn();
		if($hasProjectNameColumn){
			$projectDiv = '<div class="col-md-1 width-17">
					<label>'. __('Project Name') .'</label>
					<div class="kt-input-icon">
						<div class="kt-input-icon">
							<div class="input-group date">
								<input readonly class="form-control js-project-name" name="settlements['.$invoiceNumber.'][project_name]" value="">
							</div>
						</div>
					</div>
				</div>';
		}
		return  '
		<div class=" kt-margin-b-10 border-class">
			<div class="form-group row align-items-end">
			
			'. $projectDiv .'
				
				<div class="col-md-1 width-10">
					<label>'. __('Invoice Number') .'</label>
					<div class="kt-input-icon">
						<div class="kt-input-icon">
							<div class="input-group date">
								<input type="hidden" name="settlements['.$invoiceNumber.'][invoice_id]" value="0" class="js-invoice-id">
								<input readonly class="form-control js-invoice-number" data-invoice-id="0" name="settlements['.$invoiceNumber.'][invoice_number]" value="'. $invoiceNumber .'">
							</div>
						</div>
					</div>
				</div>
				<div class="col-md-1 width-9">
					<label>'.__('Invoice Date').'</label>
					<div class="kt-input-icon">
						<div class="input-group date">
							<input name="settlements['.$invoiceNumber.'][invoice_date]" value="'.$dueDateFormatted.'" type="text" class="form-control js-invoice-date" disabled />
						</div>
					</div>
				</div>
				<div class="col-md-1 width-9">
					<label>'.__('Due Date').'</label>
					<div class="kt-input-icon">
						<div class="input-group date">
							<input name="settlements['.$invoiceNumber.'][invoice_due_date]" type="text" value="'.$invoiceDueDateFormatted.'" class="form-control js-invoice-due-date" disabled />
						</div>
					</div>
				</div>
				
				<div class="col-md-1 width-12 common-parent-js">
					<label> '. __('Invoice Amount')  .' [ '. '<span class="currency-span"></span>' .' ] ' .' </label>
					<div class="kt-input-icon">
						<input name="settlements['.$invoiceNumber.'][net_invoice_amount]" value="'.$netInvoiceAmountFormatted.'" type="text" disabled class="form-control js-net-invoice-amount">
					</div>
				</div>
				<div class="col-md-2 width-12">
					<label> '. __('Collected Amount') .'</label>
					<div class="kt-input-icon">
						<input name="settlements['.$invoiceNumber.'][collected_amount]" value="'. $collectedAmountFormatted .'" type="text" disabled class="form-control js-collected-amount">
					</div>
				</div>
		
				<div class="col-md-2 width-12">
					<label> '. __('Net Balance') .' </label>
					<div class="kt-input-icon">
						<input name="settlements['.$invoiceNumber.'][net_balance]" value="'.$netBalanceFormatted.'" type="text" readonly class="form-control js-net-balance">
					</div>
				</div>
		
				<div class="col-md-1 width-9.5">
					<label> '. __('Settlement Amount') .' <span class="text-danger ">*</span> </label>
					<div class="kt-input-icon">
						<input name="settlements['.$invoiceNumber.'][settlement_amount]" value="'.$settlementAmount.'" placeholder="'.__("Settlement Amount").'" type="text" class="form-control js-settlement-amount only-greater-than-or-equal-zero-allowed settlement-amount-class">
					</div>
				</div>
				<div class="col-md-1 width-9.5">
					<label> '. __('Withhold Amount') .' <span class="text-danger ">*</span> </label>
					<div class="kt-input-icon">
						<input name="settlements['.$invoiceNumber.'][withhold_amount]" value="'.$withholdAmount.'" placeholder="'. __('Withhold Amount') .'" type="text" class="form-control js-withhold-amount only-greater-than-or-equal-zero-allowed ">
					</div>
				</div>
		
			</div>
		
		</div>
		' ;
	}
	public static function getCurrencies($id = null)
	{
		return DB::table('customer_invoices')
		->when($id,function($q) use ($id){
			$q->where('id',$id);
		})
		->select('currency')
		->where('currency','!=','')
		->where('company_id',getCurrentCompanyId())
		->orderBy('currency')
		->get()
		->unique('currency')->pluck('currency','currency')->toArray();
	}
	public static function getCustomerInvoicesUnderCollectionAtDatesForContracts(array &$result  , int $companyId   , ?string $contractCode , array $datesWithWeekNumber , string $endDate , ?string $currency = null , ?string $mainFunctionalCurrency = null ):void
	{
		$totalCashInFlowKey = __('Total Cash Inflow');
		$key = __('Customers Invoices') ;
		// Company-wide + main functional currency tab: keep every currency
		// (net_balance_in_main_currency already carries the converted
		// equivalent). Any specific foreign-currency tab, or a single-
		// currency contract, narrows down to that currency's own invoices.
		$showAllCurrenciesConverted = ! $contractCode && $mainFunctionalCurrency !== null && $currency === $mainFunctionalCurrency;
		$items = self::
		where('company_id',$companyId)
		->when($contractCode,function($builder) use ($contractCode){
			$builder->where('contract_code',$contractCode);
		})
		->when(! $showAllCurrenciesConverted && $currency !== null, function($builder) use ($currency){
			$builder->where('currency',$currency);
		})
		->where('net_balance','>',0)
		->
		whereBetween('invoice_due_date',[now()->format('Y-m-d'),$endDate])
		->get();
			foreach($items as $item){
				$sum = $item->net_balance_in_main_currency ; 
				$invoiceNumber = $item->invoice_number . ' [ ' . $item->customer_name . ' ]' ; 
				$currentWeekYear = $datesWithWeekNumber[$item->invoice_due_date] ;
				$projectName = $item->project_name ? '[ ' . $item->getProjectName() . ' ]' : '';
				$invoiceNumber = __('Invoice No.') . ' ' .  $invoiceNumber . $projectName  ;
				$result['customers'][$key][$invoiceNumber]['weeks'][$currentWeekYear] = isset($result['customers'][$key][$invoiceNumber]['weeks'][$currentWeekYear]) ? $result['customers'][$key][$invoiceNumber]['weeks'][$currentWeekYear] + $sum :  $sum;
				$result['customers'][$key][$invoiceNumber]['total'] = isset($result['customers'][$key][$invoiceNumber]['total']) ? $result['customers'][$key][$invoiceNumber]['total']  + $sum : $sum;
				$currentTotal = $sum;
				$result['customers'][$key]['total'][$currentWeekYear] = isset($result['customers'][$key]['total'][$currentWeekYear]) ? $result['customers'][$key]['total'][$currentWeekYear] +  $currentTotal : $currentTotal ;
				$result['customers'][$totalCashInFlowKey]['total'][$currentWeekYear] = isset($result['customers'][$totalCashInFlowKey]['total'][$currentWeekYear]) ? $result['customers'][$totalCashInFlowKey]['total'][$currentWeekYear] + $sum : $sum;

			}
	
	}
	public static function getCashAndBankBalanceAtDate(array &$result  ,$foreignExchangeRates , $mainFunctionalCurrency , string $startDate , string $currentWeekYear    , $companyId = null , ?string $currency = null):void
	{
		/**
		 * 
		 * * في حالة لو مرر العقد فا مش محتاجين عمله لان العقد الواحد مربوط بعملة واحدة
		 */
		$totalCashInFlowKey = __('Total Cash Inflow');
		// Main functional currency tab -> every currency's balance, each
		// converted below. A specific foreign-currency tab -> that
		// currency's own balances only, unconverted.
		$showAllCurrenciesConverted = $currency === null || $currency === $mainFunctionalCurrency;
		
		$currentTypeText = 'Cash & Banks Balance';
		$rows = DB::table('cash_in_safe_statements')
		->where('cash_in_safe_statements.company_id',$companyId)
		->when(! $showAllCurrenciesConverted, function($q) use ($currency) {
			$q->where('cash_in_safe_statements.currency',$currency);
		})
		->where('cash_in_safe_statements.date','<=',$startDate)
		->join('branch','branch.id','=','cash_in_safe_statements.branch_id')
		->orderByRaw('cash_in_safe_statements.date desc , cash_in_safe_statements.id desc')
		->selectRaw('cash_in_safe_statements.branch_id , cash_in_safe_statements.end_balance as received_amount,branch.name,date,cash_in_safe_statements.currency')
		->get()->groupBy(function($q){
			return $q->branch_id.$q->currency;
		})->map(function($result){
			return $result->first();
		})->values();
		foreach($rows as $row){
			$date = $row->date;
			$currentCurrency = $row->currency;
			$exchangeRate  = ForeignExchangeRate::getExchangeRateAt($currentCurrency,$mainFunctionalCurrency,$date,$companyId,$foreignExchangeRates);
			$invoiceNumber =   $row->name  ;
			$amountInExchangeRate = $showAllCurrenciesConverted ? $row->received_amount * $exchangeRate : (float) $row->received_amount;
			 $result['customers'][$currentTypeText][$invoiceNumber]['weeks'][$currentWeekYear] = isset($result['customers'][$currentTypeText][$invoiceNumber]['weeks'][$currentWeekYear]) ? $result['customers'][$currentTypeText][$invoiceNumber]['weeks'][$currentWeekYear]+  $amountInExchangeRate :$amountInExchangeRate;
			$result['customers'][$currentTypeText][$invoiceNumber]['total'] = isset($result['customers'][$currentTypeText][$invoiceNumber]['total']) ? $result['customers'][$currentTypeText][$invoiceNumber]['total']  + $amountInExchangeRate : $amountInExchangeRate;
			$currentTotal =$amountInExchangeRate;
			$result['customers'][$currentTypeText]['total'][$currentWeekYear] = isset($result['customers'][$currentTypeText]['total'][$currentWeekYear]) ? $result['customers'][$currentTypeText]['total'][$currentWeekYear] +  $currentTotal : $currentTotal ;
			$result['customers'][$totalCashInFlowKey]['total'][$currentWeekYear] = isset($result['customers'][$totalCashInFlowKey]['total'][$currentWeekYear]) ? $result['customers'][$totalCashInFlowKey]['total'][$currentWeekYear] + $amountInExchangeRate : $amountInExchangeRate;
			}
		
		$rows = DB::table('current_account_bank_statements')
		->where('current_account_bank_statements.company_id',$companyId)
		->when(! $showAllCurrenciesConverted, function($q) use ($currency) {
			$q->where('financial_institution_accounts.currency',$currency);
		})
		->where('current_account_bank_statements.date','<=',$startDate)
		->join('financial_institution_accounts','financial_institution_accounts.id','=','current_account_bank_statements.financial_institution_account_id')
		->join('financial_institutions','financial_institutions.id','=','financial_institution_accounts.financial_institution_id')
		->join('banks','banks.id','=','financial_institutions.bank_id')
		->orderByRaw('current_account_bank_statements.date desc , current_account_bank_statements.id desc')
		->selectRaw('account_number,financial_institution_accounts.currency,current_account_bank_statements.financial_institution_account_id , current_account_bank_statements.end_balance as received_amount,banks.name_en as name,date')
		->get()
		->groupBy(function($q){
			return $q->account_number.$q->financial_institution_account_id.$q->currency;
		})->map(function($result){
			return $result->first();
		})
		->values()->sortBy('name');
		foreach($rows as $row){
			$date = $row->date;
			$currentCurrency = $row->currency;
			$exchangeRate  = ForeignExchangeRate::getExchangeRateAt($currentCurrency,$mainFunctionalCurrency,$date,$companyId,$foreignExchangeRates);
			
			$invoiceNumber =   $row->name . ' [ ' . $row->account_number . ' ]' . ' [ ' .  $currentCurrency .' ]'   ;
			$amountInExchangeRate = $showAllCurrenciesConverted ? $row->received_amount*$exchangeRate : (float) $row->received_amount ; 
			 $result['customers'][$currentTypeText][$invoiceNumber]['weeks'][$currentWeekYear] = isset($result['customers'][$currentTypeText][$invoiceNumber]['weeks'][$currentWeekYear]) ? $result['customers'][$currentTypeText][$invoiceNumber]['weeks'][$currentWeekYear]+  $amountInExchangeRate :$amountInExchangeRate;
			$result['customers'][$currentTypeText][$invoiceNumber]['total'] = isset($result['customers'][$currentTypeText][$invoiceNumber]['total']) ? $result['customers'][$currentTypeText][$invoiceNumber]['total']  + $amountInExchangeRate : $amountInExchangeRate;
			$currentTotal = $amountInExchangeRate;
			$result['customers'][$currentTypeText]['total'][$currentWeekYear] = isset($result['customers'][$currentTypeText]['total'][$currentWeekYear]) ? $result['customers'][$currentTypeText]['total'][$currentWeekYear] +  $currentTotal : $currentTotal ;
			$result['customers'][$totalCashInFlowKey]['total'][$currentWeekYear] = isset($result['customers'][$totalCashInFlowKey]['total'][$currentWeekYear]) ? $result['customers'][$totalCashInFlowKey]['total'][$currentWeekYear] + $amountInExchangeRate : $amountInExchangeRate;
		}
	
		
	}
	public static function getSettlementAmountUnderDateForSpecificType(array &$result  ,  $foreignExchangeRates , $mainFunctionalCurrency , string $moneyType , string $dateColumnName , string $startDate , string $endDate, ?string $contractCode , string $currentWeekYear , ?string $chequeStatus = null   , $companyId = null):void
	{
		/**
		 * 
		 * * في حالة لو مرر العقد فا مش محتاجين عمله لان العقد الواحد مربوط بعملة واحدة
		 */
		$columnNames = $contractCode ? 'received_amount,name,invoice_number,down_payment_type,receiving_currency,receiving_date' : 'received_amount,name,down_payment_type,receiving_currency,receiving_date';
		$totalCashInFlowKey = __('Total Cash Inflow');
		$currentTypeText = [
			MoneyReceived::INCOMING_TRANSFER => __('Incoming Transfers'),
			MoneyReceived::CHEQUE => $chequeStatus == Cheque::IN_SAFE ? __('Cheques In Safe') : __('Checks Collected'),
			MoneyReceived::CASH_IN_BANK=>__('Bank Deposits'),
			MoneyReceived::CASH_IN_SAFE=>__('Cash Collections')
		][$moneyType];
		
		if($chequeStatus == Cheque::UNDER_COLLECTION){
			$currentTypeText = __('Cheques Under Collection');
		}
		$rows =  DB::table('money_received')
		->where('money_received.company_id',$companyId)
		->when($chequeStatus , function( $builder) use ($chequeStatus){
			$builder->join('cheques','cheques.money_received_id','=','money_received.id')->where('cheques.status',$chequeStatus);
		})
		->join('partners','partners.id','=','money_received.partner_id')
		->where('money_received.type','=',$moneyType)
		->whereBetween($dateColumnName,[$startDate,$endDate])
		->when($contractCode , function($query) use ($contractCode){
			$query->join('settlements','money_received.id','=','settlements.money_received_id')
			->join('customer_invoices','invoice_id','=','customer_invoices.id')
			->where('contract_code',$contractCode)
			->where(function($q){
				$q->where('down_payment_type','=',null)->orWhere('down_payment_type','=','general');
			})
			;
		})
		->selectRaw($columnNames)->get();
		foreach($rows as $row){
			$receivingDate = $row->receiving_date;
			$receivingCurrency = $row->receiving_currency;
			$invoiceNumber =  $contractCode ? $row->invoice_number : $row->name  ;
			// $exchangeRate = 
			$exchangeRate  = ForeignExchangeRate::getExchangeRateAt($receivingCurrency,$mainFunctionalCurrency,$receivingDate,$companyId,$foreignExchangeRates);
		
			$amount  = $row->received_amount  * $exchangeRate;
			 $result['customers'][$currentTypeText][$invoiceNumber]['weeks'][$currentWeekYear] = isset($result['customers'][$currentTypeText][$invoiceNumber]['weeks'][$currentWeekYear]) ? $result['customers'][$currentTypeText][$invoiceNumber]['weeks'][$currentWeekYear]+  $amount :$amount;
			$result['customers'][$currentTypeText][$invoiceNumber]['total'] = isset($result['customers'][$currentTypeText][$invoiceNumber]['total']) ? $result['customers'][$currentTypeText][$invoiceNumber]['total']  + $amount : $amount;
			$currentTotal = $row->received_amount  * $exchangeRate;
			// $currentTotal = $row->received_amount;
			$result['customers'][$currentTypeText]['total'][$currentWeekYear] = isset($result['customers'][$currentTypeText]['total'][$currentWeekYear]) ? $result['customers'][$currentTypeText]['total'][$currentWeekYear] +  $currentTotal : $currentTotal ;
			$result['customers'][$totalCashInFlowKey]['total'][$currentWeekYear] = isset($result['customers'][$totalCashInFlowKey]['total'][$currentWeekYear]) ? $result['customers'][$totalCashInFlowKey]['total'][$currentWeekYear] + $amount :$amount;
		}
		
		
			
	}
	
	public static function getDownPaymentsOverContracts(array &$result  ,$foreignExchangeRates,$mainFunctionalCurrency , string $moneyType , string $dateColumnName , string $startDate , string $endDate, ?int $contractId , string $currentWeekYear , ?string $chequeStatus = null  ,  $companyId = null):void
	{
		/**
		 * 
		 * * في حالة لو مرر العقد فا مش محتاجين عمله لان العقد الواحد مربوط بعملة واحدة
		 */
		$totalCashInFlowKey = __('Total Cash Inflow');
		$currentTypeText = [
			MoneyReceived::INCOMING_TRANSFER => __('Incoming Transfers'),
			MoneyReceived::CHEQUE => $chequeStatus == Cheque::IN_SAFE ? __('Cheques In Safe') : __('Checks Collected'),
			MoneyReceived::CASH_IN_BANK=>__('Bank Deposits'),
			MoneyReceived::CASH_IN_SAFE=>__('Cash Collections')
		][$moneyType];
		
		if($chequeStatus == Cheque::UNDER_COLLECTION){
			$currentTypeText = __('Cheques Under Collection');
		}
		
		$rows = DB::table('money_received')->where('money_received.company_id',$companyId)
		->where('down_payment_type','over_contract')
		->where('money_received.type','=',$moneyType)
		->where('contract_id',$contractId)
		->when($chequeStatus , function( $builder) use ($chequeStatus){
			$builder->join('cheques','cheques.money_received_id','=','money_received.id')->where('cheques.status',$chequeStatus);
		})
		->whereBetween($dateColumnName,[$startDate,$endDate])
		->selectRaw('received_amount,receiving_currency,receiving_date')->get();
		
		
		foreach($rows as $row){
			$receivingCurrency =  $row->receiving_currency;
			$receivingDate = $row->receiving_date;
			$exchangeRate = ForeignExchangeRate::getExchangeRateAt($receivingCurrency,$mainFunctionalCurrency,$receivingDate,$companyId,$foreignExchangeRates);

			$invoiceNumber =  __('Down Payment')  ;
			$amount =$row->received_amount * $exchangeRate ;
			 $result['customers'][$currentTypeText][$invoiceNumber]['weeks'][$currentWeekYear] = isset($result['customers'][$currentTypeText][$invoiceNumber]['weeks'][$currentWeekYear]) ? $result['customers'][$currentTypeText][$invoiceNumber]['weeks'][$currentWeekYear]+  $amount :$amount;
			$result['customers'][$currentTypeText][$invoiceNumber]['total'] = isset($result['customers'][$currentTypeText][$invoiceNumber]['total']) ? $result['customers'][$currentTypeText][$invoiceNumber]['total']  +  $amount : $amount;
			$currentTotal = $row->received_amount * $exchangeRate;
			$result['customers'][$currentTypeText]['total'][$currentWeekYear] = isset($result['customers'][$currentTypeText]['total'][$currentWeekYear]) ? $result['customers'][$currentTypeText]['total'][$currentWeekYear] +  $currentTotal : $currentTotal ;
			$result['customers'][$totalCashInFlowKey]['total'][$currentWeekYear] = isset($result['customers'][$totalCashInFlowKey]['total'][$currentWeekYear]) ? $result['customers'][$totalCashInFlowKey]['total'][$currentWeekYear] + $amount : $amount;
		}
		
			
	}
	
	
	public static function getForecastedProjectCollection(array &$result   , string $startDate , string $endDate , $currency  , $companyId  , array $datesWithWeekNumber , ?int $contractId = null , $foreignExchangeRates = null , ?string $mainFunctionalCurrency = null):void
	{
		// See HasForecastedProjectCollection trait for the full formula
		// and why it's shared with SupplierInvoice::getForecastedProjectCollection.
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
				'main_result_type' => 'customers',
				'result_key' => 'Forecasted Project Collection',
				'invoice_table' => 'customer_invoices',
				'order_relation' => 'salesOrders',
				'order_number_key' => 'so_number',
				'invoice_order_number_column' => 'sales_order_number',
				'down_payment_table' => 'down_payment_settlements',
				'down_payment_order_id_column' => 'sales_order_id',
				'add_to_cash_inflow_total' => true,
				'paid_or_collected_status' => self::COLLETED_OR_PAID,
			]
		);
	}
	
	
	public function getDeleteByDateColumnName()
	{
		return 'invoice_date';
	}
	public static function getProjectionOtherCashIn(array &$result  ,Company $company,int $cashflowReportId,int $isContract ):void
	{
		$currentTypeText = 'Projected Other Cash In Items';
		$totalCashInFlowKey = __('Total Cash Inflow');
		$items = CashProjection::where('company_id',$company->id)->where('is_contract',$isContract)->where('cashflow_report_id',$cashflowReportId)->where('type','in')->get();
		
			foreach($items as $item){
				$name = $item->name ; 
				foreach($item->amounts as $currentWeekYear => $value){
					$result['customers'][$currentTypeText][$name]['weeks'][$currentWeekYear] = isset($result['customers'][$currentTypeText][$name]['weeks'][$currentWeekYear]) ? $result['customers'][$currentTypeText][$name]['weeks'][$currentWeekYear] + $value :  $value;
					$result['customers'][$currentTypeText][$name]['total'] = isset($result['customers'][$currentTypeText][$name]['total']) ? $result['customers'][$currentTypeText][$name]['total']  + $value : $value;
					$currentTotal = $value;
					$result['customers'][$currentTypeText]['total'][$currentWeekYear] = isset($result['customers'][$currentTypeText]['total'][$currentWeekYear]) ? $result['customers'][$currentTypeText]['total'][$currentWeekYear] +  $currentTotal : $currentTotal ;
			            $result['customers'][$totalCashInFlowKey]['total'][$currentWeekYear] = isset($result['customers'][$totalCashInFlowKey]['total'][$currentWeekYear]) ? $result['customers'][$totalCashInFlowKey]['total'][$currentWeekYear] + $value :$value;

				}
			}
	}
	public function getSalesOrderNumber()
	{
		return $this->sales_order_number;
	}
	public function getCollectedOrPaidAmount()
	{
		return $this->collected_amount?:0;
	}
	public function getName()
	{
		return $this->customer_name;
	}
	public function getTotalCollectedOrPaid()
	{
		return (float)$this->total_collected_amount ; 
	}
}
