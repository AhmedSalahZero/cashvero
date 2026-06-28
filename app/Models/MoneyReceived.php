<?php

namespace App\Models;

use App\Interfaces\Models\IHasDebitCurrentAccountStatement;
use App\Models\OpeningBalance;
use App\Services\Api\OdooPayment;
use App\Traits\Models\HasCashInSafe;
use App\Traits\Models\HasDebitCurrentAccountStatement;
use App\Traits\Models\HasDebitOverdraftStatement;
use App\Traits\Models\HasDebitStatements;
use App\Traits\Models\HasForeignExchangeGainOrLoss;
use App\Traits\Models\HasNonCustomerOrSupplier;
use App\Traits\Models\HasPartnerStatement;
use App\Traits\Models\HasReviewedBy;
use App\Traits\Models\HasUserComment;
use App\Traits\Models\IsMoney;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @property PayableCheque $payableCheque
 */
/**
 * @property int $id
 * @property string|null $odoo_reference
 * @property int|null $journal_entry_id
 * @property int|null $account_bank_statement_line_id
 * @property string|null $transaction_type
 * @property int $has_unapplied_or_down_payment
 * @property string|null $odoo_error_message
 * @property int $synced_with_odoo
 * @property int|null $advanced_opening_balance_id
 * @property int|null $odoo_id
 * @property int|null $odoo_move_id
 * @property string $partner_type
 * @property int|null $partner_id partner_id
 * @property int $is_reviewed
 * @property int|null $reviewed_by المشرف اللي حدد انه راجعه
 * @property string $money_type
 * @property string|null $down_payment_type
 * @property string|null $down_payment_settlement_date
 * @property int|null $contract_id في حاله لو كان downpayment اي دفعه مقدمة
 * @property int|null $opening_balance_id
 * @property string|null $type
 * @property string|null $receiving_date
 * @property numeric|null $received_amount
 * @property numeric $received_amount_in_main_currency
 * @property float $total_withhold_amount
 * @property float|null $total_withhold_amount_in_main_currency
 * @property float|null $amount_in_invoice_currency
 * @property string|null $currency
 * @property string|null $receiving_currency
 * @property float|null $exchange_rate
 * @property int|null $user_id
 * @property int|null $company_id
 * @property string|null $comment_ar
 * @property string|null $comment_en
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $user_comment
 * @property-read \App\Models\CashInBank|null $cashInBank
 * @property-read \App\Models\CashInSafe|null $cashInSafe
 * @property-read \App\Models\CashInSafeStatement|null $cashInSafeDebitStatement
 * @property-read \App\Models\Cheque|null $cheque
 * @property-read \App\Models\CleanOverdraftBankStatement|null $cleanOverdraftDebitBankStatement
 * @property-read \App\Models\Company|null $company
 * @property-read \App\Models\Contract|null $contract
 * @property-read \App\Models\CurrentAccountBankStatement|null $currentAccountDebitBankStatement
 * @property-read \App\Models\Partner|null $customer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CustomerInvoice> $customerInvoices
 * @property-read int|null $customer_invoices_count
 * @property-read bool|null $customer_invoices_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DownPaymentSettlement> $downPaymentSettlements
 * @property-read int|null $down_payment_settlements_count
 * @property-read bool|null $down_payment_settlements_exists
 * @property-read \App\Models\EmployeeStatement|null $employeeStatement
 * @property-read \App\Models\FullySecuredOverdraftBankStatement|null $fullySecuredOverdraftDebitBankStatement
 * @property-read \App\Models\IncomingTransfer|null $incomingTransfer
 * @property-read \App\Models\OpeningBalance|null $openingBalance
 * @property-read \App\Models\OtherPartnerStatement|null $otherPartnerStatement
 * @property-read \App\Models\OverdraftAgainstAssignmentOfContractBankStatement|null $overdraftAgainstAssignmentOfContractDebitBankStatement
 * @property-read \App\Models\OverdraftAgainstCommercialPaperBankStatement|null $overdraftAgainstCommercialPaperDebitBankStatement
 * @property-read \App\Models\Partner|null $partner
 * @property-read \App\Models\User|null $reviewedBy
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Settlement> $settlements
 * @property-read int|null $settlements_count
 * @property-read bool|null $settlements_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Settlement> $settlementsForDownPaymentThatComeFromMoneyModel
 * @property-read int|null $settlements_for_down_payment_that_come_from_money_model_count
 * @property-read bool|null $settlements_for_down_payment_that_come_from_money_model_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Settlement> $settlementsForMoneyReceived
 * @property-read int|null $settlements_for_money_received_count
 * @property-read bool|null $settlements_for_money_received_exists
 * @property-read \App\Models\ShareholderStatement|null $shareholderStatement
 * @property-read \App\Models\SubsidiaryCompanyStatement|null $subsidiaryCompanyStatement
 * @property-read \App\Models\TaxStatement|null $taxStatement
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived filterByReceivingDate(?string $startDate = null, ?string $endDate = null)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereAccountBankStatementLineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereAdvancedOpeningBalanceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereAmountInInvoiceCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereCommentAr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereCommentEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereContractId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereDownPaymentSettlementDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereDownPaymentType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereExchangeRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereHasUnappliedOrDownPayment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereIsReviewed($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereJournalEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereMoneyType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereOdooErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereOdooId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereOdooMoveId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereOdooReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereOpeningBalanceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived wherePartnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived wherePartnerType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereReceivedAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereReceivedAmountInMainCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereReceivingCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereReceivingDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereReviewedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereSyncedWithOdoo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereTotalWithholdAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereTotalWithholdAmountInMainCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereTransactionType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereUserComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\MoneyReceived whereUserId($value)
 * @mixin \Eloquent
 */
class MoneyReceived extends Model implements IHasDebitCurrentAccountStatement
{
	use HasCashInSafe,HasDebitCurrentAccountStatement,HasDebitOverdraftStatement,IsMoney,HasForeignExchangeGainOrLoss ,HasDebitStatements,HasPartnerStatement,HasReviewedBy , HasUserComment,HasNonCustomerOrSupplier;

	const CASH_IN_SAFE  = 'cash-in-safe';
	const CASH_IN_BANK  = 'cash-in-bank';
	const INCOMING_TRANSFER  = 'incoming-transfer';
	const CHEQUE  = 'cheque';
	const CHEQUE_UNDER_COLLECTION  = 'cheque-under-collection';
	const CHEQUE_REJECTED  = 'cheque-rejected';
	const CHEQUE_COLLECTED = 'cheque-collected';
	const CHEQUE_COLLECTION_FEES = 'cheque-collection-fees';
	const CONTRACTS_WITH_DOWN_PAYMENTS = 'contracts-with-down-payments';
	const UNAPPLIED_AMOUNTS = 'unapplied-amounts';
	const DOWN_PAYMENT = 'down-payment';
	const INVOICE_SETTLEMENT_WITH_DOWN_PAYMENT = 'invoice-settlement-with-down-payment';
	const CLIENT_NAME ='customer_name';
	const DOWN_PAYMENT_OVER_CONTRACT = 'over_contract' ;
	const DOWN_PAYMENT_GENERAL = 'general' ;
	const SETTLEMENT_OF_OPENING_BALANCE = 'settlement-of-opening-balance' ;
	const RECEIVING_OR_PAYMENT_CURRENCY_COLUMN_NAME='receiving_currency';
	public static function scopeFilterByReceivingDate( $query, ?string $startDate = null, ?string $endDate = null)
	{
		return $query->whereBetween('receiving_date', [$startDate, $endDate]);
	}
	public static function generateComment(self $moneyReceived,string $lang,?string $invoiceNumbers = '',?string $customerName = null)
	{
		$settledInvoiceNumbers = getKeysWithSettlementAmount(Request()->get('settlements',[]),'settlement_amount');

		$settledInvoiceNumbers =  $settledInvoiceNumbers?: $invoiceNumbers;
		$customerName = is_null($customerName) || $customerName ==''  ?$moneyReceived->getCustomerName() : $customerName;
		
		if($moneyReceived->isCheque()){
			$chequeNumber = $moneyReceived->getChequeNumber()?:Request('cheque_number');
			
			if($moneyReceived->isGeneralDownPayment()&&$moneyReceived->isDownPayment()){
				return __('Cheque :name With Number [ :number ] - General Down Payment',['name'=>$customerName,'number'=>$chequeNumber],$lang) ;
			}
			if($moneyReceived->isSettlementOfOpeningBalance()&&$moneyReceived->isDownPayment()){
				return __('Cheque :name With Number [ :number ] - Opening Balance Settlement',['name'=>$customerName,'number'=>$chequeNumber],$lang) ;
			}
			if($moneyReceived->isOverContractDownPayment() && $moneyReceived->isDownPayment()){
				return __('Cheque :name With Number [ :number ] - Contract Down Payment [ :contractName ] [ :contractCode ]',['name'=>$customerName,'number'=>$chequeNumber,
				'contractName'=>$moneyReceived->getContractName(),
			'contractCode'=>$moneyReceived->getContractCode()
			],$lang) ;
				
			}
			if($moneyReceived->isOpenBalance()){
				return __('Opening Balance Cheque From [ :customerName ]' , ['customerName'=>$customerName],$lang);
			}
			if($moneyReceived->isGeneralDownPayment()){
				return __('General Down Payment - Cheque :name With Number [ :number ]',['name'=>$customerName,'number'=>$chequeNumber],$lang) ;
			}
			if($moneyReceived->isSettlementOfOpeningBalance()){
				return __('Opening Balance Settlement - Cheque :name With Number [ :number ]',['name'=>$customerName,'number'=>$chequeNumber],$lang) ;
			}
			if($moneyReceived->isOverContractDownPayment()){
				return __('Down Payment - Cheque :name [ :contractName ] [ :contractCode ] With Number [ :number ]',['name'=>$customerName,'contractName'=>$moneyReceived->getContractName(),'contractCode'=>$moneyReceived->getContractCode(),'number'=>$chequeNumber],$lang) ;
			}
			if($moneyReceived->isInvoiceSettlementWithDownPayment()){
				return __('Cheque :name With Number [ :number ] Settled Invoices [ :numbers ] [ :currency ] | Down Payment - [ :contractName ] [ :contractCode ]',[
					'name'=>$customerName ,
					'number'=>$chequeNumber,
					'numbers'=>$settledInvoiceNumbers ,
					'currency'=>$moneyReceived->getCurrency(),
					'contractName'=>$moneyReceived->getContractName(),
					'contractCode'=>$moneyReceived->getContractCode()
				]);
			}
			if($moneyReceived->getPartnerType()!='is_customer'){
				return __('Cheque :name [ :partnerType ] With Number [ :number ]',['name'=>$customerName,'number'=>$chequeNumber,'partnerType'=>$moneyReceived->getPartnerTypeFormatted()],$lang);
			}
			
			return __('Cheque :name With Number [ :number ] Settled Invoices [ :numbers ] [ :currency ]',['name'=>$customerName,'number'=>$chequeNumber,'numbers'=>$settledInvoiceNumbers,'currency'=>$moneyReceived->getCurrency()],$lang) ;
		}
		if($moneyReceived->isCashInSafe()){
			if($moneyReceived->isOpenBalance()){
				return __('Beginning Balance',[],$lang);
			}
			if($moneyReceived->isInvoiceSettlementWithDownPayment()){
				return __('Cash From :name Settled Invoices [ :numbers ] [ :currency ] | Down Payment - [ :contractName ] [ :contractCode ]',[
					'name'=>$customerName ,
					'numbers'=>$settledInvoiceNumbers ,
					'currency'=>$moneyReceived->getCurrency(),
					'contractName'=>$moneyReceived->getContractName(),
					'contractCode'=>$moneyReceived->getContractCode()
				]);
			}
			if($moneyReceived->getPartnerType()!='is_customer'){
				return __('Cash In Safe From :name [ :partnerType ]',['name'=>$customerName,'partnerType'=>$moneyReceived->getPartnerTypeFormatted()],$lang) ;
			}
			if($moneyReceived->isAdvancedOpeningBalance()){
				return __('Advanced Opening Balance From :name',['name'=>$customerName],$lang) ;
			}
			if($moneyReceived->isGeneralDownPayment()&&$moneyReceived->isDownPayment()){
				return __('Cash In Safe From :name  - General Down Payment',['name'=>$customerName],$lang) ;
			}
			if($moneyReceived->isSettlementOfOpeningBalance()&&$moneyReceived->isDownPayment()){
				return __('Cash In Safe From :name  - Opening Balance Settlement',['name'=>$customerName],$lang) ;
			}
			if($moneyReceived->isOverContractDownPayment() && $moneyReceived->isDownPayment()){
				return __('Cash In Safe From :name  - Contract Down Payment [ :contractName ] [ :contractCode ]',['name'=>$customerName,
			'contractName'=>$moneyReceived->getContractName(),
			'contractCode'=>$moneyReceived->getContractCode()
			],$lang) ;
			}
			if($moneyReceived->isGeneralDownPayment()&&$moneyReceived->isDownPayment()){
				return __('Cash In Safe From :name - General Down Payment',['name'=>$customerName],$lang) ;
			}
			
			return __('Cash In Safe From :name Settled Invoices [ :numbers ]',['name'=>$customerName,'numbers'=>$settledInvoiceNumbers],$lang) ;
		}
		if($moneyReceived->isCashInBank()){
			if($moneyReceived->isInvoiceSettlementWithDownPayment()){
				return __('Bank Deposit From :name Settled Invoices [ :numbers ] [ :currency ] | Down Payment - [ :contractName ] [ :contractCode ]',[
					'name'=>$customerName ,
					'numbers'=>$settledInvoiceNumbers ,
					'currency'=>$moneyReceived->getCurrency(),
					'contractName'=>$moneyReceived->getContractName(),
					'contractCode'=>$moneyReceived->getContractCode()
				]);
			}
			if($moneyReceived->getPartnerType()!='is_customer'){
				return __('Bank Deposit From :name [ :partnerType ]',['name'=>$customerName,'partnerType'=>$moneyReceived->getPartnerTypeFormatted()],$lang) ;
			}
			if($moneyReceived->isGeneralDownPayment()&&$moneyReceived->isDownPayment()){
				return __('Bank Deposit From :name - General Down Payment',['name'=>$customerName],$lang) ;
			}
			if($moneyReceived->isSettlementOfOpeningBalance()&&$moneyReceived->isDownPayment()){
				return __('Bank Deposit From :name - Opening Balance Settlement',['name'=>$customerName],$lang) ;
			}
			if($moneyReceived->isOverContractDownPayment() && $moneyReceived->isDownPayment()){
				return __('Bank Deposit From :name - Contract Down Payment [ :contractName ] [ :contractCode ]',['name'=>$customerName,
			'contractName'=>$moneyReceived->getContractName(),
			'contractCode'=>$moneyReceived->getContractCode()
			],$lang) ;
				
			}
			return __('Bank Deposit From :name Settled Invoices [ :numbers ]',['name'=>$customerName,'numbers'=>$settledInvoiceNumbers],$lang) ;
		}
		if($moneyReceived->isIncomingTransfer()){
			
			if($moneyReceived->isGeneralDownPayment()&&$moneyReceived->isDownPayment()){
				return __('Incoming Transfer :name - General Down Payment',['name'=>$customerName],$lang) ;
			}
			elseif($moneyReceived->isOverContractDownPayment() && $moneyReceived->isDownPayment()){
				return __('Incoming Transfer :name - Contract Down Payment [ :contractName ] [ :contractCode ]',['name'=>$customerName,
			'contractName'=>$moneyReceived->getContractName(),
			'contractCode'=>$moneyReceived->getContractCode()
			],$lang) ;
			}
			elseif($moneyReceived->isGeneralDownPayment()){
				return __('General Down Payment - Incoming Transfer :name',['name'=>$customerName],$lang) ;
			}
			elseif($moneyReceived->isSettlementOfOpeningBalance()&&$moneyReceived->isDownPayment()){
				return __('Incoming Transfer :name - Opening Balance Settlement',['name'=>$customerName],$lang) ;
			}
			elseif($moneyReceived->isSettlementOfOpeningBalance()){
				return __('Opening Balance Settlement - Incoming Transfer :name',['name'=>$customerName],$lang) ;
			}
			elseif($moneyReceived->isOverContractDownPayment()){
				return __('Down Payment - Incoming Transfer :name [ :contractName ] [ :contractCode ]',['name'=>$customerName,'contractName'=>$moneyReceived->getContractName(),'contractCode'=>$moneyReceived->getContractCode()],$lang) ;
			}
			elseif($moneyReceived->isInvoiceSettlementWithDownPayment()){
				return __('Incoming Transfer :name Settled Invoices [ :numbers ] [ :currency ] | Down Payment - [ :contractName ] [ :contractCode ]',[
					'name'=>$customerName ,
					'numbers'=>$settledInvoiceNumbers ,
					'currency'=>$moneyReceived->getCurrency(),
					'contractName'=>$moneyReceived->getContractName(),
					'contractCode'=>$moneyReceived->getContractCode()
				]);
			}
			
			
			
			elseif($moneyReceived->getPartnerType()!='is_customer'){
				return __('Incoming Transfer :name [ :partnerType ]',['name'=>$customerName,'partnerType'=>$moneyReceived->getPartnerTypeFormatted()],$lang) ;
			}
			return __('Incoming Transfer :name Settled Invoices [ :numbers ]',['name'=>$customerName,'numbers'=>$settledInvoiceNumbers],$lang) ;
		}
	}
	protected static function booted()
	{
	
		self::creating(function (self $moneyReceived): void {
			$moneyReceived->comment_en = self::generateComment($moneyReceived,'en');
			$moneyReceived->comment_ar = self::generateComment($moneyReceived,'ar');
		});
		
	}
	public static function getAllTypes()
	{
		return [
			self::CASH_IN_SAFE,
			self::CASH_IN_BANK,
			self::INCOMING_TRANSFER,
			self::CHEQUE,
			self::CHEQUE_UNDER_COLLECTION,
			self::CHEQUE_REJECTED,
			self::CHEQUE_COLLECTED,
			
		];
	}
	
    protected $guarded = ['id'];
    protected $table = 'money_received';
    
	public function getType():string 
	{
		return $this->type ;
	}
    public function isCashInSafe()
    {
        return $this->getType() ==self::CASH_IN_SAFE;
    }
	public function isCashInBank()
    {
        return $this->getType() ==self::CASH_IN_BANK;
    }
	public function isCheque()
    {
        return $this->getType() ==self::CHEQUE;
    }
	public function isChequeInSafe()
	{
		return $this->isCheque() && $this->cheque->isInSafe();
	}
	public function isRejectedCheque()
	{
		return $this->isCheque() && $this->cheque->isRejected();
	}
    public function isIncomingTransfer()
    {
        return $this->getType() ==self::INCOMING_TRANSFER;
    }
    
	public function getPartnerName()
	{
		return $this->partner ? $this->partner->getName() : __('N/A') ;
	}
    public function getCustomerName()
    {
		return $this->getPartnerName();
    }
	public function customer()
	{
		return $this->belongsTo(Partner::class,'partner_id','id');
	}
	public function getPartnerId()
	{
		return $this->partner ? $this->partner->id : 0 ;
	}
	public function getPartnerOdooId()
	{
		return $this->partner ? $this->partner->odoo_id : 0 ;
	}
	public function getCustomerId()
	{
		return $this->getPartnerId();
	}
    public function getName()
    {
    	return $this->getCustomerName();
    }
	public function getDate()
	{
		return $this->getReceivingDate();
	}
    public function getReceivingDate()
    {
        return $this->receiving_date;
    }
    public function getCashInSafeReceivingBranchId()
    {
		$cashInSafe = $this->cashInSafe ;
        return  $cashInSafe ? $cashInSafe->getReceivingBranchId() :0;
    }
	public function getAmountInInvoiceCurrency()
    {
        return  $this->amount_in_invoice_currency?:0 ;
    }
	
    public function getReceivedAmount()
    {
        return  $this->received_amount?:0 ;
    }
	public function getAmount()
	{
		return $this->getReceivedAmount();
	}
	public function getAmountInReceivingCurrency()
	{
		return $this->getReceivedAmount();
	}
	public function getChequeDueDate(){
		return $this->cheque ? $this->cheque->getDueDate(): null;
	}
	public function getChequeNumber()
	{
		return $this->cheque ? $this->cheque->getChequeNumber():null;
	}
	public function getReceivedAmountFormatted()
    {
        return number_format($this->getReceivedAmount()) ;
    }
	public function getInvoiceCurrency()
	{
		return $this->getCurrency();
	}
	
	public function getCurrency()
	{
		return $this->currency;
	}
	
	public function getCurrencyFormatted()
	{
		return strtoupper($this->currency);
	}
	public function getCurrencyToReceivingCurrencyFormatted()
	{
		$currency = $this->getCurrency();
		$receivingCurrency = $this->getReceivingCurrency();
		if($currency == $receivingCurrency || is_null($receivingCurrency)){
			return $this->getCurrencyFormatted();
		}
		return $this->getReceivingCurrencyFormatted().'/'.$this->getCurrencyFormatted();
		
	}
	public function getReceivingCurrency()
	{
		return $this->receiving_currency;
	}
	
	public function getReceivingCurrencyFormatted()
	{
		return strtoupper($this->getReceivingCurrency());
	}
	public function getReceivingOrPaymentCurrency()
	{
		return $this->getReceivingCurrency();
	}

	public function getExchangeRate()
	{
		
		return $this->exchange_rate?:1;
	}



    public function getCashInSafeReceiptNumber()
    {
		$cashInSafe = $this->cashInSafe;
        return $cashInSafe ? $cashInSafe->getReceiptNumber() :  null ;
    }

  
	public function getNumber()
	{
		if($this->isCheque()){
			return $this->cheque ? $this->cheque->getChequeNumber() : __('N/A');
		}
		if($this->isCashInSafe()){
			return $this->getCashInSafeReceiptNumber();
		}
		if($this->isIncomingTransfer()){
			return $this->getIncomingTransferAccountNumber();
		}
		if($this->isCashInBank()){
			return $this->getCashInBankAccountNumber();
		}
	}
	

	
	public function getBankName()
	{
		if($this->isCashInSafe()){
			return $this->getCashInSafeBranchName();
		}
		if($this->isCheque()){
			return $this->cheque ? $this->cheque->getDraweeBankName() : __('N/A') ;
		}
		if($this->isIncomingTransfer()){
			return $this->getIncomingTransferReceivingBankName();
		}
		if($this->isCashInBank()){
			return $this->getCashInBankReceivingBankName();
		}
	}
	
	public function incomingTransfer()
	{
		return $this->hasOne(IncomingTransfer::class,'money_received_id','id');
	}

    public function getIncomingTransferReceivingBankId()
    {
		$incomingTransfer = $this->incomingTransfer ;
        return $incomingTransfer ? $incomingTransfer->getReceivingBankId() : 0 ;
    }
	public function incomingTransferReceivingBank():?FinancialInstitution
	{
		$incomingTransfer = $this->incomingTransfer ;
		return $incomingTransfer ? $incomingTransfer->receivingBank : null ;
	}
	public function getIncomingTransferReceivingBankName()
	{
		$incomingTransfer = $this->incomingTransfer ;
        return $incomingTransfer ? $incomingTransfer->getReceivingBankName() : __('N/A') ;
	}
	
	public function cashInBank()
	{
		return $this->hasOne(CashInBank::class,'money_received_id','id');
	}
	public function getCashInBankReceivingBankName()
    {
		$cashInBank = $this->cashInBank ;
        return $cashInBank ? $cashInBank->getReceivingBankName() : 0 ;
    }

    public function getCashInBankReceivingBankId()
    {
		$cashInBank = $this->cashInBank ;
        return $cashInBank ? $cashInBank->getReceivingBankId() : 0 ;
    }
	// public function cashInBankReceivingBank():?FinancialInstitution
	// {
	// 	$cashInBank = $this->cashInBank ;
	// 	return $cashInBank ? $cashInBank->receivingBank: null ;
	// }
	public function getFinancialInstitutionId()
	{
		if($this->isCashInBank()){
			return $this->getCashInBankReceivingBankId();
		}
		if($this->isIncomingTransfer()){
			return $this->getIncomingTransferReceivingBankId();
		}
		if($this->isCheque()){
			return $this->cheque ? $this->cheque->getDrawlBankId() : 0;
		}
	}
	
	// public function getFinancialInstitutionOrBank()
	// {
	// 	if($this->isCashInBank()){
	// 		return $this->cashInBank;
	// 	}
	// 	if($this->isIncomingTransfer()){
	// 		return $this->incomingTransfer;
	// 	}
	// 	// if($this->isCheque()){
	// 	// 	return $this->cheque ? $this->cheque->getDrawlBank() :null;
	// 	// }
	// }
	
	
	
	/**
	 * * For Money Received Only
	 */
	/**
	 * * كل كل ال settlements
	 * * ودا بيشمل في حاله مثلا لو كان عندك
	 * * money received with down payment
	 * * فا هتجيب كل ال settlements
	 * * سواء اللي اتعملت مع ال money received
	 * * او اللي اتعملت مع ال down payment 
	 * * الخاصه بيها 
	 * * خلي بالك ان الاتنين مع بعض سواء ال 
	 * * money received or its down payment
	 * * وبالتالي الاتنين ليهم نفس الاي دي
	 */
    public function settlements():HasMany
    {
        return $this->hasMany(Settlement::class, 'money_received_id', 'id');
    }
	/**
	 * * هتفرق عن اللي فاتت بس في حاله ال
	 * * money received with down payment
	 * * دي هتجيب بس اللي اتعملت في ال 
	 * * money received 
	 * * نفسها
	 * * اما في باقي ال
	 * * types مش هتفرق لان مش بينزل معاهم داون بيمنت
	 */
	public function settlementsForMoneyReceived():HasMany
    {
        return $this->hasMany(Settlement::class, 'money_received_id', 'id')->where('is_from_down_payment',0);
    }
	public function settlementsForDownPaymentThatComeFromMoneyModel():HasMany
    {
        return $this->hasMany(Settlement::class, 'money_received_id', 'id')->where('is_from_down_payment',1);
    }
	/**
	 * * For Down Payment Only
	 */
    public function downPaymentSettlements():HasMany
    {
        return $this->hasMany(DownPaymentSettlement::class, 'money_received_id', 'id');
    }
    public function customerInvoices():HasMany
    {
        return $this->hasMany(CustomerInvoice::class, 'partner_id', 'customer_id');
    }
	
    public function getSettlementsForInvoiceNumber(int $invoiceId, int $partnerId,?bool $isFromDownPayment = null):Collection
    {
		$settlements = $this->settlements ;
		if($isFromDownPayment == true){
			$settlements = $this->settlementsForDownPaymentThatComeFromMoneyModel;
		}
	
		if($isFromDownPayment == false){
			$settlements = $this->settlementsForMoneyReceived;
		}
        return $settlements->where('invoice_id', $invoiceId)->where('partner_id', $partnerId) ;
    }
	public function sumSettlementsForInvoice(int $invoiceId, int $partnerId,?bool $isFromDownPayment =null):float{
	
		return $this->getSettlementsForInvoiceNumber($invoiceId,$partnerId,$isFromDownPayment)
		->sum('settlement_amount');
		
		
	}
	public function sumWithholdAmountForInvoice($invoiceId, int $partnerId,?bool $isFromDownPayment =null):float{
		return $this->getSettlementsForInvoiceNumber($invoiceId,$partnerId,$isFromDownPayment)->sum('withhold_amount');
	}
    public function getReceivingDateFormatted()
    {
        $receivingDate = $this->getReceivingDate() ;
        if($receivingDate) {
            return Carbon::make($receivingDate)->format('d-m-Y');
        }
    }
	public function setReceivingDateAttribute($value)
	{
		if(is_object($value)){
			return $value ;
		}
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['receiving_date'] = $value;
			return  ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		$this->attributes['receiving_date'] = $year.'-'.$month.'-'.$day;
		
	}
	
	public static function getUniqueBanks( $banks):array{
		$uniqueBanksIds = [];
		foreach($banks as $bankId){
				$uniqueBanksIds[$bankId] = $bankId;
		}
		return $uniqueBanksIds; 
	}
	
	public static function getDrawlBanksForCurrentCompany(int $companyId){

		$cheques = self::where('company_id',$companyId)->has('cheque')->with('cheque')->get()->pluck('cheque.drawee_bank_id')->toArray();
		$cheques=Cheque::where('company_id',$companyId)->pluck('drawee_bank_id')->toArray();
		
		$allBanks = Bank::get();
		$banks = self::getUniqueBanks($cheques);
	
		$banksFormatted = [];
		foreach($banks as $bankId){
			$bank = $allBanks->where('id',$bankId)->first() ;
			if($bank){
				$banksFormatted[$bankId] = $bank->getViewName() ;
			}
		}
		return $banksFormatted; 
	}
	
	
	


	public function getBranchId():int
    {
		$receivingBranch = $this->cashInSafeReceivingBranch();
		return $receivingBranch? $receivingBranch->id:0;
    }
	public function cashInSafeReceivingBranch():?Branch
	{
		$cashInSafe = $this->cashInSafe;
		return $cashInSafe ? $cashInSafe->receivingBranch : null ;
	}
	public function getCashInSafeBranchName():?string
	{
		$cashInSafe = $this->cashInSafe;

		return $cashInSafe ? $cashInSafe->getReceivingBranchName() : null ;
	}
	public function getCashBranchOdooId()
	{
		$cashInSafe = $this->cashInSafe;

		return $cashInSafe ? $cashInSafe->getBankOdooId() : null ;
	}
	public function getCashBranchJournalId()
	{
		$cashInSafe = $this->cashInSafe;

		return $cashInSafe ? $cashInSafe->getBankJournalId() : null ;
	}
	public function getChequeDepositDate()
	{
		$cheque = $this->cheque;
		return $cheque ? $cheque->getDepositDate() : null;
	}
	public function getChequeDepositDateFormattedForDatePicker()
	{
		$date = $this->getChequeDepositDate();
		return $date ? Carbon::make($date)->format('m/d/Y') : null;
	}
	public function getChequeDepositDateFormatted()
	{
		$date = $this->getChequeDepositDate();
		return $date ? Carbon::make($date)->format('d-m-Y') : null;
	}
	
	
	
	
	/** drawl_bank_id
	**	هو البنك اللي بنسحب منه الشيك وليكن مثلا شخص اداني شيك معين وقتها بروح اسحبه من هذا 
	**	البنك فا شرط يكون من البنوك بتاعتي علشان البنك بتاعي يتواصل مع بنك ال
	**	drawee بعدين يحطلي الفلوس بتاعته في حسابي
	*/		 
	
	public function getChequeDrawlBankName()
	{
		return $this->cheque ? $this->cheque->getDrawlBankName() : __('N/A') ;
	}
	public function getChequeDrawlBankId()
	{
		return $this->cheque ? $this->cheque->getDrawlBankId() : 0 ;
	}
	
	public function getChequeAccountType()
	{
		$cheque = $this->cheque ;
		return $cheque ? $cheque->getAccountType() : null ;
	}
	
	public function getChequeAccountNumber()
	{
		$cheque = $this->cheque ;
		return $cheque ? $cheque->getAccountNumber() : null ;
	}
	
	public function cashInSafe():HasOne
	{
		return $this->hasOne(CashInSafe::class,'money_received_id','id');
	}
	
	public function cheque():HasOne
	{
		return $this->hasOne(Cheque::class,'money_received_id','id');
	}
		
	public function isChequeUnderCollection():bool
	{
		return $this->cheque && $this->cheque->getStatus() == Cheque::UNDER_COLLECTION;
	}
	public function getTotalWithholdAmount():float 
	{
		return $this->total_withhold_amount ?: 0 ;
	}
	public function getIncomingTransferAccountTypeId(){
		$incomingTransfer = $this->incomingTransfer;
		return $incomingTransfer ? $incomingTransfer->getAccountTypeId() : 0 ;
	}
	public function getIncomingTransferAccountTypeName(){
		$incomingTransfer = $this->incomingTransfer;
		return $incomingTransfer ? $incomingTransfer->getAccountTypeName() : 0 ;
	}
	public function getIncomingTransferAccountNumber(){
		$incomingTransfer = $this->incomingTransfer;
		return $incomingTransfer ? $incomingTransfer->getAccountNumber() : 0 ;
	}
	public function getCashInBankAccountTypeId(){
		$cashInBank = $this->cashInBank;
		return $cashInBank ? $cashInBank->getAccountTypeId() : 0 ;
	}
	public function getCashInBankAccountTypeName(){
		$cashInBank = $this->cashInBank;
		return $cashInBank ? $cashInBank->getAccountTypeName() : 0 ;
	}
	public function getCashInBankAccountNumber(){
		$cashInBank = $this->cashInBank;
		return $cashInBank ? $cashInBank->getAccountNumber() : 0 ;
	}

	public function cleanOverdraftDebitBankStatement():HasOne
	{
		return $this->hasOne(CleanOverdraftBankStatement::class,'money_received_id','id');
	}
	
	public function fullySecuredOverdraftDebitBankStatement():HasOne
	{
		return $this->hasOne(FullySecuredOverdraftBankStatement::class,'money_received_id','id');
	}
	
	public function overdraftAgainstCommercialPaperDebitBankStatement():HasOne
	{
		return $this->hasOne(OverdraftAgainstCommercialPaperBankStatement::class,'money_received_id','id');
	}
	public function overdraftAgainstAssignmentOfContractDebitBankStatement():HasOne
	{
		return $this->hasOne(OverdraftAgainstAssignmentOfContractBankStatement::class,'money_received_id','id');
	}
	public function cashInSafeDebitStatement():HasOne
	{
		return $this->hasOne(CashInSafeStatement::class,'money_received_id','id');
	}
	
	
	
	
	
	
	
	public function currentAccountDebitBankStatement():HasOne
	{
		return $this->hasOne(CurrentAccountBankStatement::class,'money_received_id','id');
	}
		
	public function openingBalance():BelongsTo
	{
		return $this->belongsTo(OpeningBalance::class,'opening_balance_id');
	}
	public function isOpenBalance():bool
	{
		return $this->opening_balance_id !== null ;
	}
	public function getChequeClearanceDays():int
	{
		return $this->cheque && $this->cheque->clearance_days ? $this->cheque->clearance_days : 0 ;
	}
	
	public function isInvoiceSettlementWithDownPayment():bool
	{
		return $this->getMoneyType() == self::INVOICE_SETTLEMENT_WITH_DOWN_PAYMENT;
	}
	public function getMoneyType()
	{
		return $this->money_type; 
	}
	public function getMoneyTypeFormatted()
	{
		$moneyType = $this->getMoneyType();
		$partnerType = $this->getPartnerType();
		if($this->isOpenBalance()){
			return __('From Opening');
		}
		
		if($moneyType == 'money-received'){
			$moneyType = 'invoice-settlement';
		}
		if($moneyType == self::INVOICE_SETTLEMENT_WITH_DOWN_PAYMENT){
			$moneyType = __('Invoice Settlement & Down Payment');
		}

		if($partnerType != 'is_customer'){
			return __('Money Received From [ :partnerType ]',['partnerType'=>$this->getPartnerTypeFormatted()]);	
		}
		return camelizeWithSpace($moneyType)  ;
	}
	public function getContractId()
	{
		return $this->contract_id;
	}
	public function contract()
	{
		return $this->belongsTo(Contract::class,'contract_id','id');
	}
	public function getContractName()
	{
		return $this->contract ? $this->contract->getName() : __('General');
	}
	public function getContractCode()
	{
		return $this->contract ? $this->contract->getCode() : '-';
	}
	
	public function deleteRelations()
	{
		$OdooPaymentService = null;
		if($this->company->hasOdooIntegrationCredentials()){
			$OdooPaymentService = new OdooPayment($this->company);
		}
		$this->unlinkNonCustomerOrSupplierOdooExpense();
		$oldType = $this->getType();
		 if ($this->account_bank_statement_line_id) {
            $OdooPaymentService->unlinkBankCollection($this->account_bank_statement_line_id);
        }
		$this->settlements->each(function($settlement) use($OdooPaymentService){
			if ($settlement->account_bank_statement_line_id) {
            $OdooPaymentService->unlinkBankCollection($settlement->account_bank_statement_line_id);
        }
		elseif($settlement->odoo_move_id && $settlement->invoice && $settlement->invoice->odoo_id){
			$OdooPaymentService->unlink($settlement->odoo_move_id);
		}
		elseif($settlement->odoo_id){
			$OdooPaymentService->cancelPayments($settlement->odoo_id);
		}
		
			// if($settlement->odoo_move_id){
			// 	$OdooPaymentService->unlink($settlement->odoo_move_id);
			// }
			$settlement->delete();
		});
	
		$this->incomingTransfer ? $this->incomingTransfer->delete() :null ;
		$this->cashInBank ? $this->cashInBank->delete() :null ;
		$this->cashInSafe ? $this->cashInSafe->delete() :null ;
		$this->cheque ? $this->cheque->delete() :null ;
		// $this->cleanOverdraftCreditBankStatement ? $this->cleanOverdraftCreditBankStatement->delete() :null ;
		// $this->fullySecuredOverdraftCreditBankStatement ? $this->fullySecuredOverdraftCreditBankStatement->delete() :null ;
		// $this->cashInSafeCreditStatement ? $this->cashInSafeCreditStatement->delete() :null ;
		$this->cleanOverdraftDebitBankStatement ? $this->cleanOverdraftDebitBankStatement->delete() :null ;
		$this->fullySecuredOverdraftDebitBankStatement ? $this->fullySecuredOverdraftDebitBankStatement->delete() :null ;
		$this->overdraftAgainstCommercialPaperDebitBankStatement ? $this->overdraftAgainstCommercialPaperDebitBankStatement->delete() :null ;
		$this->overdraftAgainstAssignmentOfContractDebitBankStatement ? $this->overdraftAgainstAssignmentOfContractDebitBankStatement->delete() :null ;
		$this->cashInSafeDebitStatement ? $this->cashInSafeDebitStatement->delete() :null ;
		$this->currentAccountDebitBankStatement ? $this->currentAccountDebitBankStatement->delete() :null ;
		// $this->currentAccountCreditBankStatement ? $this->currentAccountCreditBankStatement->delete() :null ;
		// $this->overdraftAgainstAssignmentOfContractCreditBankStatement ? $this->overdraftAgainstAssignmentOfContractCreditBankStatement->delete() :null ;
		// $this->overdraftAgainstCommercialPaperCreditBankStatement ? $this->overdraftAgainstCommercialPaperCreditBankStatement->delete() :null ;
		// $this->$oldTypeRelationName ? $this->$oldTypeRelationName->delete() : null;
		
		$this->downPaymentSettlements->each(function($downPaymentSettlement){
			$downPaymentSettlement->delete();
		});
		
		
		$this->deletePartnerStatement();
	}
	public function getCurrentStatement()
	{
		if($this->cleanOverdraftDebitBankStatement){
			return $this->cleanOverdraftDebitBankStatement ;
		}
		if($this->fullySecuredOverdraftDebitBankStatement){
			return $this->fullySecuredOverdraftDebitBankStatement;
		}
		if($this->overdraftAgainstCommercialPaperDebitBankStatement){
			return $this->overdraftAgainstCommercialPaperDebitBankStatement;
		}	
		if($this->overdraftAgainstAssignmentOfContractDebitBankStatement){
			return $this->overdraftAgainstAssignmentOfContractDebitBankStatement;
		}
		if($this->cashInSafeDebitStatement){
			return $this->cashInSafeDebitStatement;
		}
		if($this->currentAccountDebitBankStatement){
			return $this->currentAccountDebitBankStatement ;
		}
		
		
	}

	
	/**
	 * * دا عباره عن التاريخ اللي هنستخدمة في ال
	 * * statements 
	 * * سواء بانك او كاش الخ
	 */
	public function getStatementDate()
	{
		if($this->isCheque()){
			return $this->getChequeDueDate();
		}
		return $this->getReceivingDate();
	}
	// public function overdraftAgainstCommercialPaperCreditBankStatement()
	// {
	// 	return $this->hasOne(OverdraftAgainstCommercialPaperBankStatement::class,'money_received_id','id');
	// }
	// public function currentAccountCreditBankStatement()
	// {
	// 	return $this->hasOne(CurrentAccountBankStatement::class,'money_received_id','id');
	// }
	
	// public function cashInSafeCreditStatement()
	// {
	// 	return $this->hasOne(CashInSafeStatement::class,'money_received_id','id');
	// }
	// public function overdraftAgainstAssignmentOfContractCreditBankStatement()
	// {
	// 	return $this->hasOne(OverdraftAgainstAssignmentOfContractBankStatement::class,'money_received_id','id');
	// }
	// public static function getChequesCollectedUnderDates(int $companyId , string $startDate , string $endDate,string $currency,string $chequeStatus,string $dateColumnName ) 
	// {
	// 	return  DB::table('money_received')
	// 	->where('type',MoneyReceived::CHEQUE)
	// 	->where('money_received.currency',$currency)
	// 	->join('cheques','cheques.money_received_id','=','money_received.id')
	// 	->where('money_received.company_id',$companyId)
	// 	->whereBetween('cheques.'.$dateColumnName,[$startDate,$endDate])
	// 	->where('cheques.status',$chequeStatus)
	// 	->sum('received_amount');
	// }
	// public static function getIncomingTransferUnderDates(int $companyId , string $startDate , string $endDate,string $currency,$customerName = null , $contractCode = null) 
	// {
	// 	$isContract = $customerName && $contractCode ;
	// 	$sumColumnName = $isContract ? 'settlement_amount' : 'received_amount' ;
		
	// 		return  DB::table('money_received')
	// 	->where('type',MoneyReceived::INCOMING_TRANSFER)
	// 	->where('money_received.company_id',$companyId)
	// 	->where('money_received.currency',$currency)
	// 	->join('incoming_transfers','incoming_transfers.money_received_id','=','money_received.id')
	// 	->where('money_received.company_id',$companyId)
	// 	->whereBetween('money_received.receiving_date',[$startDate,$endDate])
	// 	->when($isContract , function(Builder $builder) use ($contractCode){
	// 		$builder->join('customer_invoices','customer_invoices.customer_id' ,'=','money_received.partner_id')
	// 		->where('customer_invoices.contract_code',$contractCode)
	// 		->join('settlements',function(Builder $builder){
	// 			$builder->on('money_received.id','=','settlements.money_received_id')
	// 			->on('settlements.invoice_id','customer_invoices.id');
	// 		})
	// 		;
	// 	})
	// 	->sum($sumColumnName);
	// }
	public static function getBankDepositsUnderDates(int $companyId , string $startDate , string $endDate,string $currency) 
	{
		return  DB::table('money_received')
	
		->where('type',MoneyReceived::CASH_IN_BANK)
		->where('money_received.currency',$currency)
		->join('cash_in_banks','cash_in_banks.money_received_id','=','money_received.id')
		->where('money_received.company_id',$companyId)
		->whereBetween('money_received.receiving_date',[$startDate,$endDate])
		->sum('received_amount');
	}
	public static function getCashInSafeUnderDates(int $companyId , string $startDate , string $endDate,string $currency) 
	{
		
		return  DB::table('money_received')
		->where('type',MoneyReceived::CASH_IN_SAFE)
		->where('money_received.currency',$currency)
		->join('cash_in_safes','cash_in_safes.money_received_id','=','money_received.id')
		->where('money_received.company_id',$companyId)
		->whereBetween('money_received.receiving_date',[$startDate,$endDate])
		->sum('received_amount');
	}
	/**
	 * * هنا لو معاك 
	 * * down payment
	 * * وعايز تعرف ال
	 * * ماني ريسيفد 
	 * * اللي تم انشائها معاها
	 */
	// public function moneyReceived()
	// {
	// 	return $this->belongsTo(MoneyReceived::class,'money_received_id','id');
	// }
	/**
	 * * هنا لو معاك 
	 * * ماني ريسيفد وعايز تعرف ال
	 * * down payment
	 * * اللي تم انشائها معاها
	 */
	// public function downPayment()
	// {
	// 	return $this->hasOne(MoneyReceived::class,'money_received_id','id');
	// }
	public  function getForeignKeyName()
	{
		return 'money_received_id';
	}  
	public function getChequeDraweeBankId():int
	{
		return $this->cheque ? $this->cheque->getDraweeBankId() : 0 ;
	}
	public function storeNewSalesOrdersAmounts(array $salesOrdersAmounts,?int $contractId,?int $customerId,int $companyId,$receivedAmount = null)
	{
		if(!count($salesOrdersAmounts)){
			$salesOrdersAmounts[] = [
				'received_amount'=>$receivedAmount,
				'company_id'=>$companyId,
				'contract_id'=>null ,
				'down_payment_amount'=>$receivedAmount,
				'currency'=>$this->getReceivingCurrency(),
				'sales_order_id'=>null
			];
		}
		foreach($salesOrdersAmounts as $salesOrderReceivedAmountArr)
		{
			if(isset($salesOrderReceivedAmountArr['received_amount'])&&$salesOrderReceivedAmountArr['received_amount'] > 0){
				$isDownPaymentOverContract = $this->isDownPaymentOverContract();
				$downPaymentAmount = $salesOrderReceivedAmountArr['received_amount'];
				$salesOrderReceivedAmountArr['company_id'] = $companyId;
				$dataArr = array_merge(
					$salesOrderReceivedAmountArr ,
					[
						'contract_id'=>$contractId,
						'customer_id'=>$customerId,
						'down_payment_amount'=>$downPaymentAmount,
						'currency'=>$isDownPaymentOverContract ? $this->currency : $this->getReceivingCurrency()
					],
					[
						'sales_order_id'=>$salesOrderReceivedAmountArr['sales_order_id'] == -1 ? null : $salesOrderReceivedAmountArr['sales_order_id'],
						'down_payment_balance'=>$downPaymentAmount
					]
				) ;
				
				$this->downPaymentSettlements()->create($dataArr);
			}
		}
	}
	public function getAccountTypeId()
	{
		if($this->isIncomingTransfer()){
			return $this->incomingTransfer->getAccountTypeId();
		}
		if($this->isCheque()){
			return $this->cheque->getAccountTypeId();
		}
		if($this->isCashInBank()){
			return $this->cashInBank->getAccountTypeId();
		}
		return null ;
		// throw new \Exception('Custom Exception .. getAccountTypeId .. This Method Is Only For Incoming Transfer Or Payable Cheque');
	}
	
	public function getAccountNumber()
	{
		if($this->isIncomingTransfer()){
			return $this->incomingTransfer->getAccountNumber();
		}
		if($this->isCheque()){
			return $this->cheque->getAccountNumber();
		}
		if($this->isCashInBank()){
			return $this->cashInBank->getAccountNumber();
		}
		return null ;
		// throw new \Exception('Custom Exception .. getAccountNumber .. This Method Is Only For Incoming Transfer Or Payable Cheque');
	}	
	public function getBankAccountOdooId():int
	{
		$financialInstitution = $this->getFinancialInstitution();
		
		return $financialInstitution->getOdooIdForAccount($this->getAccountTypeId(),$this->getAccountNumber());
	}
	public function getBankAccountJournalId():int
	{
	
		$financialInstitution = $this->getFinancialInstitution();
		
		return $financialInstitution->getJournalIdForAccount($this->getAccountTypeId(),$this->getAccountNumber());
	}
	// public function cleanOverdraftCreditBankStatement()
	// {
	// 	return $this->hasOne(CleanOverdraftBankStatement::class,'money_received_id','id');
	// }	
	// public function fullySecuredOverdraftCreditBankStatement()
	// {
	// 	return $this->hasOne(FullySecuredOverdraftBankStatement::class,'money_received_id','id');
	// }
	public function getCustomerOrSupplier():string 
	{
		return 'customer';
	}
	/**
	 * * هنا هنديله ال جورنال اي دي ونحاول نعرف هو بنك ولا كاش
	 */
	public static function getMoneyTypeFromJournalId(int $journalId , int $companyId):array 
	{
		$account = DB::table('financial_institution_accounts')->where('journal_id',$journalId)->where('company_id',$companyId)->first();
		if($account){
			return [
				'type'=>self::INCOMING_TRANSFER,
				// 'id'=>$account->id ,
				'odoo_id'=>$account->odoo_id ,
				'account_type_id'=>27,
				'account_number'=>$account->account_number,
				'financial_institution_id'=>$account->financial_institution_id
			];
		}
		$branch = DB::table('branch')->where('company_id',$companyId)->where('journal_id',$journalId)->first();
		if($branch){
			return [
				'type'=>self::CASH_IN_SAFE,
				'branch_id'=>$branch->id,
				'odoo_id'=>$branch->odoo_id 
			];
		}
		throw new Exception('No Journal Id Found Please Edit Your Bank / Branch To Add Odoo Code');
		
	}
	
	public function getOdooIdWithRefOfTransaction():array
	{
		$transactionType = $this->getTransactionType();
		$odooSettings = $this->company->odooSetting;
		if($transactionType == 'refund-custody'){
			return [
				'id'=>$odooSettings->getCustodyAccountId() ,
				'ref'=>__('Refund Custody Received From'), 
			];
		}
		if($transactionType == 'pay-loan'){
			return  [
				'id'=>$odooSettings->getEmployeeLoanAccountId() ,
				'ref'=>__('Loan Received From'), 
			];
		}
		if($transactionType == 'funding-from'&& $this->getPartnerType() == 'is_subsidiary_company'){
			return [
				'id'=>$this->partner->dueToChartOfAccountNumberId(),
				'ref'=>__('Funding From')
			];
		}		
		if($transactionType == 'funding-from' && $this->getPartnerType() == 'is_shareholder'){
			return [
				'id'=>$odooSettings->getShareholderAccount(),
				'ref'=>__('Funding From')
			];
		}
		// if($transactionType == 'dividend-payment' && $this->getPartnerType() == 'is_shareholder'){
		// 	return [
		// 		'id'=>$odooSettings->getDividendPaymentAccount(),
		// 		'ref'=>__('Dividend Payment To')
		// 	];
		// }
		if($transactionType == 'insurance-from' ){
			return [
				'id'=>$odooSettings->getInsuranceToAccount(),
				'ref'=>__('Insurance From')
			];
		}
		 return [
			'id'=>$odooSettings->advances_from_customers_id,
			'ref'=>'Down Payment'
		];
		
	//	throw New Exception('Transaction Type ' . $transactionType . ' Does Not Have Account Id');
		
	}
	public function isChequeOrChequePayment():bool
	{
		return $this->isCheque();
	}
	public function isChequeAndNotCustomerOrSupplier()
	{
		return $this->isChequeOrChequePayment() && (!in_array($this->getPartnerType(),['is_customer','is_supplier']));
	}
	
	
}
