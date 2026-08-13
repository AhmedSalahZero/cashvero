<?php

namespace App\Models;

use App\Enums\LcTypes;
use App\Traits\HasBasicStoreRequest;
use App\Traits\HasCompany;
use App\Traits\Models\HasCommissionStatements;
use App\Traits\Models\HasCurrentAccountCreditBankStatement;
use App\Traits\Models\HasDeleteButTriggerChangeOnLastElement;
use App\Traits\Models\HasForeignExchangeGainOrLoss;
use App\Traits\Models\HasLetterOfCreditCashCoverStatements;
use App\Traits\Models\HasLetterOfCreditStatements;
use App\Traits\Models\HasUserComment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int|null $lc_facility_id
 * @property string|null $category_name
 * @property string|null $source هو المكان او الطريقه يعني اللي انت انشاتة بيها وانت عندك ثلاث او اربع زراير دول عباره عن المصدر اللي هو قيمة الكولوم دا
 * @property string $status
 * @property string|null $financed_by_bank_or_self
 * @property string|null $transaction_name
 * @property string|null $transaction_reference
 * @property string|null $transaction_date
 * @property int|null $financial_institution_id
 * @property string|null $cd_or_td_account_type_id
 * @property string|null $cd_or_td_id
 * @property numeric $total_lc_outstanding_balance
 * @property string|null $lc_type
 * @property numeric $lc_type_outstanding_balance
 * @property string|null $lc_code
 * @property int|null $partner_id
 * @property string|null $contract_type
 * @property int|null $contract_id
 * @property int|null $purchase_order_id
 * @property string|null $purchase_order_date
 * @property string|null $issuance_date
 * @property int|null $lc_duration_days
 * @property string|null $due_date
 * @property string|null $payment_date
 * @property int|null $payment_account_number_id
 * @property int|null $payment_account_type_id
 * @property string|null $payment_currency
 * @property int|null $supplier_invoice_id
 * @property numeric $lc_amount
 * @property string|null $lc_currency
 * @property numeric $issuance_fees
 * @property numeric $min_lc_commission_fees
 * @property numeric $cash_cover_rate
 * @property numeric $cash_cover_amount
 * @property string|null $cash_cover_deducted_from_account_type
 * @property string|null $cash_cover_deducted_from_account_id
 * @property int|null $lc_fees_and_commission_account_id
 * @property numeric $lc_commission_rate
 * @property numeric $lc_commission_amount
 * @property string|null $cash_cover_account_number
 * @property int $financing_duration
 * @property int $company_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $lc_cash_cover_currency
 * @property numeric|null $amount_in_main_currency
 * @property numeric|null $exchange_rate
 * @property string|null $user_comment
 * @property numeric $interest_amount
 * @property string|null $interest_currency
 * @property-read \App\Models\Partner|null $beneficiary
 * @property-read \App\Models\Company|null $company
 * @property-read \App\Models\Contract|null $contract
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CurrentAccountBankStatement> $currentAccountBankStatements
 * @property-read int|null $current_account_bank_statements_count
 * @property-read bool|null $current_account_bank_statements_exists
 * @property-read \App\Models\CurrentAccountBankStatement|null $currentAccountCreditBankStatement
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CurrentAccountBankStatement> $currentAccountCreditBankStatements
 * @property-read int|null $current_account_credit_bank_statements_count
 * @property-read bool|null $current_account_credit_bank_statements_exists
 * @property-read \App\Models\CurrentAccountBankStatement|null $currentAccountDebitBankStatement
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CurrentAccountBankStatement> $currentAccountDebitBankStatements
 * @property-read int|null $current_account_debit_bank_statements_count
 * @property-read bool|null $current_account_debit_bank_statements_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CurrentAccountBankStatement> $currentAccountLcInterestCreditBankStatements
 * @property-read int|null $current_account_lc_interest_credit_bank_statements_count
 * @property-read bool|null $current_account_lc_interest_credit_bank_statements_exists
 * @property-read \App\Models\CurrentAccountBankStatement|null $currentAccountPaymentCreditBankStatement
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CurrentAccountBankStatement> $currentAccountPaymentCreditBankStatements
 * @property-read int|null $current_account_payment_credit_bank_statements_count
 * @property-read bool|null $current_account_payment_credit_bank_statements_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LcIssuanceExpense> $expenses
 * @property-read int|null $expenses_count
 * @property-read bool|null $expenses_exists
 * @property-read \App\Models\FinancialInstitution|null $financialInstitutionBank
 * @property-read \App\Models\FinancialInstitutionAccount|null $lcFeesAndCommissionAccount
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LcOverdraftBankStatement> $lcOverdraftBankStatements
 * @property-read int|null $lc_overdraft_bank_statements_count
 * @property-read bool|null $lc_overdraft_bank_statements_exists
 * @property-read \App\Models\LcOverdraftBankStatement|null $lcOverdraftCreditBankStatement
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LetterOfCreditCashCoverStatement> $letterOfCreditCashCoverStatements
 * @property-read int|null $letter_of_credit_cash_cover_statements_count
 * @property-read bool|null $letter_of_credit_cash_cover_statements_exists
 * @property-read \App\Models\LetterOfCreditFacility|null $letterOfCreditFacility
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LetterOfCreditStatement> $letterOfCreditStatements
 * @property-read int|null $letter_of_credit_statements_count
 * @property-read bool|null $letter_of_credit_statements_exists
 * @property-read \App\Models\PurchaseOrder|null $purchaseOrder
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SettlementAllocation> $settlementAllocations
 * @property-read int|null $settlement_allocations_count
 * @property-read bool|null $settlement_allocations_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PaymentSettlement> $settlements
 * @property-read int|null $settlements_count
 * @property-read bool|null $settlements_exists
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereAmountInMainCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereCashCoverAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereCashCoverAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereCashCoverDeductedFromAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereCashCoverDeductedFromAccountType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereCashCoverRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereCategoryName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereCdOrTdAccountTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereCdOrTdId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereContractId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereContractType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereExchangeRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereFinancedByBankOrSelf($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereFinancialInstitutionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereFinancingDuration($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereInterestAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereInterestCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereIssuanceDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereIssuanceFees($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereLcAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereLcCashCoverCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereLcCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereLcCommissionAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereLcCommissionRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereLcCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereLcDurationDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereLcFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereLcFeesAndCommissionAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereLcType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereLcTypeOutstandingBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereMinLcCommissionFees($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance wherePartnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance wherePaymentAccountNumberId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance wherePaymentAccountTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance wherePaymentCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance wherePaymentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance wherePurchaseOrderDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance wherePurchaseOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereSupplierInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereTotalLcOutstandingBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereTransactionDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereTransactionName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereTransactionReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereUserComment($value)
 * @property string|null $cd_or_td_currency
 * @property string|null $lc_fees_and_commission_account_type
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereCdOrTdCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditIssuance whereLcFeesAndCommissionAccountType($value)
 * @property-read \App\Models\Partner|null $supplier
 * @mixin \Eloquent
 */
class LetterOfCreditIssuance extends Model
{
	use HasBasicStoreRequest,HasCompany,HasForeignExchangeGainOrLoss,HasCommissionStatements,HasLetterOfCreditStatements,HasLetterOfCreditCashCoverStatements,HasCurrentAccountCreditBankStatement,HasDeleteButTriggerChangeOnLastElement,HasUserComment;
	const LC_FACILITY = 'lc-facility';
	const AGAINST_TD ='against-td';
	const AGAINST_CD ='against-cd';
	const HUNDRED_PERCENTAGE_CASH_COVER ='hundred-percentage-cash-cover';
	const RUNNING = 'running';
	const PAID = 'paid';
	const OPENING_BALANCE = 'opening-balance';
	const NEW_ISSUANCE = 'new-issuance';
    const LC_FACILITY_BEGINNING_BALANCE = 'lc-facility-beginning-balance';
    const HUNDRED_PERCENTAGE_CASH_COVER_BEGINNING_BALANCE = 'hundred-percentage-cash-cover-beginning-balance';
    const AGAINST_CD_BEGINNING_BALANCE = 'against-cd-beginning-balance';
    const AGAINST_TD_BEGINNING_BALANCE = 'against-td-beginning-balance';
	const FOR_PAID ='for-paid'; // هي الفلوس اللي انت حيطتها بسبب انه عمل تاكيد انه دفع
	const AMOUNT_TO_BE_DECREASED ='amount-to-be-decreased'; // 
    protected $guarded = ['id'];
	
	public function getCategoryName():string 
	{
		return $this->category_name;
	}
	public function isOpeningBalance():bool
	{
		return $this->getCategoryName() == self::OPENING_BALANCE;
	}
	public function lcFeesAndCommissionAccount()
	{
		return $this->belongsTo(FinancialInstitutionAccount::class,'lc_fees_and_commission_account_id','id');
	}
	public function getFeesAndCommissionAccountId():int
	{
		return $this->lcFeesAndCommissionAccount ? $this->lcFeesAndCommissionAccount->id : 0 ;
	}
	public function getFeesAndCommissionAccountTypeId()
	{
		return $this->lc_fees_and_commission_account_type;
	}
	public static function getCategories():array 
	{
		return [
			self::NEW_ISSUANCE=>__('New Issuance'),
			self::OPENING_BALANCE=>__('Opening Balance')
		];
	}
	public static function lcSources()
	{
		return [
			self::LC_FACILITY => __('LC Facility'),
			self::AGAINST_TD => __('Against TD'),
			self::AGAINST_CD => __('Against CD'),
			self::HUNDRED_PERCENTAGE_CASH_COVER=>__('100% Cash Cover')
		];
	}
	public function isRunning()
	{
		return $this->getStatus() === self::RUNNING;
	}
	public function isPaid()
	{
		return $this->getStatus() === self::PAID;
	}

	public function getStatus()
	{
		return $this->status ;
	}
	public function getStatusFormatted()
	{
		return camelizeWithSpace($this->getStatus());
	}
	public function getSource()
	{
		
		return $this->source ?: self::LC_FACILITY ;
	}

	public function isCertificateOfDepositSource()
	{
		$accountTypeId = $this->getCdOrTdAccountTypeId() ;
		$accountType = AccountType::find($accountTypeId);
		return $accountType && $accountType->isCertificateOfDeposit();
	}
	public function getSourceFormatted()
	{
		return self::lcSources()[$this->getSource()];
		
	}
	
	public function getTransactionName()
	{
		return $this->transaction_name;
	}
	public function financialInstitutionBank()
	{
		return $this->belongsTo(FinancialInstitution::class,'financial_institution_id','id') ;
	}
	public function getFinancialInstitutionBankName()
	{
		return $this->financialInstitutionBank ? $this->financialInstitutionBank->getName() : __('N/A') ;
	}

	public function getFinancialInstitutionBankId()
	{
		return $this->financialInstitutionBank ? $this->financialInstitutionBank->id : 0 ;
	}
	public function getLcType()
	{
		return $this->lc_type;
	}
	public function letterOfCreditFacility()
	{
		return $this->belongsTo(LetterOfCreditFacility::class,'lc_facility_id','id');
	}	
	public function getLcFacilityLimit()
	{
		return $this->letterOfCreditFacility ? $this->letterOfCreditFacility->getLimit():0;
	}
	public function getLcFacilityId()
	{
		return $this->letterOfCreditFacility ? $this->letterOfCreditFacility->id:0;
	}
	public function getLcFacilityName()
	{
		return $this->letterOfCreditFacility ? $this->letterOfCreditFacility->getName(): __('N/A');
	}
	public function getTotalLcOutstandingBalance()
	{
		return $this->total_lc_outstanding_balance ?: 0 ;
	}
	public function getTotalLcOutstandingBalanceFormatted()
	{
		return number_format($this->getTotalLcOutstandingBalance());
	}
	public function getLcTypeOutstandingBalance()
	{
		return $this->lc_type_outstanding_balance ?: 0 ;
	}
	public function getLcTypeOutstandingBalanceFormatted()
	{
		return number_format($this->getLcTypeOutstandingBalance());
	}
	public function getLcCode()
	{
		return $this->lc_code ;
	}
	public function beneficiary()
	{
		return $this->belongsTo(Partner::class,'partner_id','id') ;
	}
	public function getBeneficiaryName()
	{
		$beneficiary = $this->beneficiary ;
		return  $beneficiary ? $beneficiary->getName(): 0 ;
	}
	public function getSupplierName()
	{
		return $this->getBeneficiaryName();
	}
	public function supplier():BelongsTo
	{
		return $this->beneficiary();
	}
	public function getBeneficiaryId()
	{
		$beneficiary = $this->beneficiary ;
		return  $beneficiary ? $beneficiary->getId(): 0 ;
	}

	public function contract()
	{
		return $this->belongsTo(Contract::class , 'contract_id','id');
	}
	public function getContractName()
	{
		$contract = $this->contract ;
		return  $contract ? $contract->getName(): 0 ;
	}
	public function getContractId()
	{
		$contract = $this->contract ;
		return  $contract ? $contract->getId(): 0 ;
	}
	public function purchaseOrder()
	{
		return $this->belongsTo(PurchaseOrder::class , 'purchase_order_id','id');
	}
	// public function getPurchaseOrderName()
	// {
	// 	$purchaseOrder = $this->purchaseOrder ;
	// 	return  $purchaseOrder ? $purchaseOrder->getName(): 0 ;
	// }
	public function getPurchaseOrderId()
	{
		$purchaseOrder = $this->purchaseOrder ;
		return  $purchaseOrder ? $purchaseOrder->getId(): 0 ;
	}
	public function getPurchaseOrderDate()
	{
		return $this->purchase_order_date;
	}
	public function getPurchaseOrderDateFormatted()
	{
		$purchaseOrderDate = $this->getPurchaseOrderDate() ;
		return $purchaseOrderDate ? Carbon::make($purchaseOrderDate)->format('d-m-Y'):null ;
	}
	public function setPurchaseOrderDateAttribute($value)
	{
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['purchase_order_date'] =  $value ;
			return ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];

		$this->attributes['purchase_order_date'] = $year.'-'.$month.'-'.$day;
	}
	public function getTransactionDate()
	{
		return $this->transaction_date;
	}
	public function getTransactionDateFormatted()
	{
		$transactionDate = $this->getTransactionDate() ;
		return $transactionDate ? Carbon::make($transactionDate)->format('d-m-Y'):null ;
	}
	public function setTransactionDateAttribute($value)
	{
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['transaction_date'] =  $value ;
			return ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];

		$this->attributes['transaction_date'] = $year.'-'.$month.'-'.$day;
	}
	public function getTransactionReference()
	{
		return $this->transaction_reference ;
	}

	public function getIssuanceDate()
	{
		return $this->issuance_date;
	}
	public function getIssuanceDateFormatted()
	{
		$issuanceDate = $this->getIssuanceDate() ;
		return $issuanceDate ? Carbon::make($issuanceDate)->format('d-m-Y'):null ;
	}
	public function setIssuanceDateAttribute($value)
	{
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['issuance_date'] =  $value ;
			return ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];

		$this->attributes['issuance_date'] = $year.'-'.$month.'-'.$day;
	}

	public function getLcDurationDays()
	{
		return $this->lc_duration_days;
	}

	public function getDueDate()
	{
		return $this->due_date;
	}
	public function getDueDateFormatted()
	{
		$dueDate = $this->getDueDate() ;
		return $dueDate ? Carbon::make($dueDate)->format('d-m-Y'):null ;
	}
	public function setDueDateAttribute($value)
	{
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['due_date'] =  $value ;
			return ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];

		$this->attributes['due_date'] = $year.'-'.$month.'-'.$day;
	}

	public function getLcAmount():float
	{
		return $this->lc_amount ?: 0 ;
	}
	public function getLcAmountFormatted()
	{
		return number_format($this->getLcAmount());
	}
	public function getLcCurrency()
	{
		return $this->lc_currency ;
	}
	public function getCdOrTdCurrencyCurrency()
	{
		return $this->cd_or_td_currency;
	}
	public function getLcCashCoverCurrency()
	{
		return $this->lc_cash_cover_currency ;
	}
	public function getLcCurrentAmount()
	{
		return $this->getLcAmount();
	}
	public function getLcCurrentAmountFormatted()
	{
		return number_format($this->getLcCurrentAmount());
	}
	
	public function getCashCoverRate()
	{
		return $this->cash_cover_rate?:0;
	}
	public function getCashCoverRateFormatted()
	{
		return number_format($this->getCashCoverRate(),1);
	}
	public function getCashCoverAmount()
	{
		return $this->cash_cover_amount ?: 0 ;
	}
	public function getCashCoverAmountFormatted()
	{
		return number_format($this->getCashCoverAmount());
	}
	public function getCashCoverDeductedFromAccountTypeId()
	{
		return $this->cash_cover_deducted_from_account_type;
	}
	public function getCashCoverDeductedFromAccountId()
	{
		return $this->cash_cover_deducted_from_account_id ?: $this->lc_fees_and_commission_account_id;
	}
	// public function getInterestRate()
	// {
	// 	return $this->interest_rate ?: 0;
	// }

	public function getLcCommissionRate()
	{
		return $this->lc_commission_rate ?: 0;
	}
	public function getLcCommissionRateFormatted()
	{
		return number_format($this->getLcCommissionRate(),1);
	}
	public function getLcCommissionAmount()
	{
		return $this->lc_commission_amount ?: 0 ;
	}
	public function getLcCommissionAmountFormatted()
	{
		return number_format($this->getLcCommissionAmount());
	}
	
	// public function getLcCommissionInterval()
	// {
	// 	return $this->lc_commission_interval ;
	// }
	public function letterOfCreditStatements()
	{
		return $this->hasMany(LetterOfCreditStatement::class,'letter_of_credit_issuance_id','id')->orderBy('full_date','desc');
	}
	public function letterOfCreditCashCoverStatements()
	{
		return $this->hasMany(LetterOfCreditCashCoverStatement::class,'letter_of_credit_issuance_id','id')->orderBy('full_date','desc');
	}
	public function lcOverdraftCreditBankStatement()
	{
		return $this->hasOne(LcOverdraftBankStatement::class,'lc_issuance_id','id')->where('is_credit',1)->orderBy('full_date','desc');
	}
	public function lcOverdraftBankStatements()
	{
		return $this->hasMany(LcOverdraftBankStatement::class,'lc_issuance_id','id')->orderBy('full_date','desc');
	}
	
	public function handleLcCreditBankStatement(int $lcFacilityId,string $moneyType ,$limit , string $date , $paidAmount,$source,string $commentEn , string $commentAr)
	{
	
	 	return  $this->lcOverdraftBankStatements()->create([
			'source'=>$source,
			'type'=>$moneyType ,
			'lc_issuance_id'=>$this->id ,
			'lc_facility_id'=>$lcFacilityId,
			'company_id'=>$this->company_id ,
			'date'=>$date,
			'limit'=>$limit,
			'beginning_balance'=>0 ,
			'debit'=>0,
			'credit'=>$paidAmount,
			'comment_en'=>$commentEn ,
			'comment_ar'=>$commentAr
		]);
	

		

	}

	public function currentAccountCreditBankStatement()
	{
		return $this->hasOne(CurrentAccountBankStatement::class,'letter_of_credit_issuance_id','id')->where('is_credit',1);
	}
	public function currentAccountCreditBankStatements()
	{
		return $this->hasMany(CurrentAccountBankStatement::class,'letter_of_credit_issuance_id','id')->where('is_credit',1)->orderBy('full_date','desc');
	}
	
	public function currentAccountPaymentCreditBankStatement()
	{
		return $this->hasOne(CurrentAccountBankStatement::class,'letter_of_credit_issuance_id','id')->where('is_credit',1)->where('type','payment');
	}
	public function currentAccountPaymentCreditBankStatements()
	{
		return $this->hasMany(CurrentAccountBankStatement::class,'letter_of_credit_issuance_id','id')->where('type','payment')->where('is_credit',1)->orderBy('full_date','desc');
	}
	public function currentAccountLcInterestCreditBankStatements()
	{
		return $this->hasMany(CurrentAccountBankStatement::class,'letter_of_credit_issuance_id','id')->where('type','lc_interest')->where('is_credit',1)->orderBy('full_date','desc');
	}
	public function currentAccountDebitBankStatement()
	{
		return $this->hasOne(CurrentAccountBankStatement::class,'letter_of_credit_issuance_id','id')->where('is_debit',1);
	}
	public function currentAccountDebitBankStatements()
	{
		return $this->hasMany(CurrentAccountBankStatement::class,'letter_of_credit_issuance_id','id')->where('is_debit',1)->orderBy('full_date','desc');
	}
	/**
	 * * علشان نجيب الاربعه مع بعض مرة واحدة
	 */
	public function currentAccountBankStatements()
	{
		return $this->hasMany(CurrentAccountBankStatement::class,'letter_of_Credit_issuance_id','id')->orderBy('full_date','desc');
	}
	public function getCdOrTdAccountTypeId()
	{
		return $this->cd_or_td_account_type_id ?:0 ;
	}
	
	public function getCdOrTdId()
	{
		return $this->cd_or_td_id;
	}
	
	/**
	 * * $includeExpenses = false بيتبعت من الـ update بس ، لأن التعديل بيمسح
	 * * الاعتماد ويعمله من الأول والمصاريف بترجع تتربط بالسجل الجديد.
	 * * في الحذف العادي لازم تتمسح معاه وإلا بتفضل يتيمة (مفيش FK على
	 * * lc_issuance_expenses يمسحها)
	 */
	public function deleteAllRelations(bool $includeExpenses = true):self
	{
		if($includeExpenses){
			/**
			 * * الـ deleting hook على LcIssuanceExpense بيمسح حركات الحساب
			 * * الجاري بتاعتها ، فلازم نمسحهم واحد واحد مش بـ query delete
			 */
			$this->expenses->each(function($expense){
				$expense->delete();
			});
		}
		PaymentSettlement::deleteButTriggerChangeOnLastElement($this->settlements);
		CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($this->currentAccountDebitBankStatements);
		CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($this->currentAccountCreditBankStatements);
		CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($this->currentAccountCreditBankStatements()->withoutGlobalScope('only_active')->get());
		CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($this->currentAccountBankStatements);
		LetterOfCreditStatement::deleteButTriggerChangeOnLastElement($this->letterOfCreditStatements);
		LetterOfCreditCashCoverStatement::deleteButTriggerChangeOnLastElement($this->letterOfCreditCashCoverStatements);
		LcOverdraftBankStatement::deleteButTriggerChangeOnLastElement($this->lcOverdraftBankStatements);
		return $this;
	}	
	public function expenses()
	{
		return $this->hasMany(LcIssuanceExpense::class , 'lc_issuance_id','id');
	}
	public function getFinancialInstitutionId()
	{
		return $this->financial_institution_id ;
	}	
	public function getRemainingBalance(float $currentLcAmountInEditMode = 0 )
	{
		$lastBankStatement = $this->lcOverdraftBankStatements->first() ;
		return  $lastBankStatement ? $lastBankStatement->end_balance + $currentLcAmountInEditMode : 0 ;
	}	
	public function getExchangeRate()
	{
		return $this->exchange_rate ?: 1 ;
	}
	public function getLcAmountInMainCurrency()
	{
		return $this->getExchangeRate() * $this->getLcAmount();
	}
	public function getAmountInMainCurrencyFormatted()
	{
		return number_format($this->getLcAmountInMainCurrency());
	}
	public function settlements():HasMany
	{
		return $this->hasMany(PaymentSettlement::class,'letter_of_Credit_issuance_id');
	}
	public function getTdOrCdCurrency(string $source,int $companyId)
	{
		$tdOrCdCurrencyName = null ;
		if($source == LetterOfCreditIssuance::AGAINST_CD){
				$currentCertificateOfDeposit = CertificatesOfDeposit::find($this->cd_or_td_id);
				$tdOrCdCurrencyName = $currentCertificateOfDeposit->getCurrency();
		}
		elseif($source == LetterOfCreditIssuance::AGAINST_TD){
				$currentTimeOfDeposit = TimeOfDeposit::find($this->cd_or_td_id);
				$tdOrCdCurrencyName = $currentTimeOfDeposit->getCurrency();
		}
		return $tdOrCdCurrencyName;
	}	
	
	public function settlementAllocations()
	{
		return $this->hasMany(SettlementAllocation::class,'letter_of_credit_issuance_id','id');
	}
	public function storeNewSettlementAfterDeleteOldOne(SupplierInvoice $supplierInvoice , Company $company)
	{
		$this->settlements->each(function(PaymentSettlement $settlement){
			$settlement->delete();
		});
		$this->settlements()->create([
			'invoice_number'=>$supplierInvoice->getInvoiceNumber(),
			'invoice_id'=>$supplierInvoice->id,
			'partner_id'=>$supplierInvoice->getPartnerId(),
			'supplier_name'=>$supplierInvoice->getSupplierName(),
			'withhold_amount'=>0,
			'company_id'=>$company->id ,
			'settlement_amount'=>$this->getLcAmount()
		]);
	}
	public function storeNewAllocationAfterDeleteOldOne(array $allocations)
	{
		$this->settlementAllocations()->delete();
		$supplierInvoice = SupplierInvoice::find(Request('supplier_invoice_id'));
		$invoiceNumber =$supplierInvoice->getInvoiceNumber();
		foreach($allocations as $index => $allocationArr){
			$partnerId = $allocationArr['partner_id'] ?? 0 ;
			$contractId = $allocationArr['contract_id'] ?? 0 ;
			$allocationAmount = $allocationArr['allocation_amount'] ?? 0 ;
			if($allocationAmount>0){
				$this->settlementAllocations()->create([
					'allocation_amount'=>$allocationAmount,
					'contract_id'=>$contractId,
					'partner_id'=>$partnerId ,
					'invoice_number'=>$invoiceNumber
				]);
			}
		}
	}
	public function getIssuanceFees()
	{
		return $this->issuance_fees ;
	}	
	
	public function getNewPoNumber()
	{
		return $this->purchaseOrder ? $this->purchaseOrder->getNumber() :'';
	}
	public function getContractType()
	{
		return $this->contract_type ;
	}
	public function getPaymentDate()
	{
		return $this->payment_date;
	}
	public function getDate()
	{
		return $this->getPaymentDate();
	}
	public function getReceivingOrPaymentMoneyDateFormatted()
	{
		return Carbon::make($this->getPaymentDate())->format('d-m-Y');
	}
	public function getType()
	{
		return 'lc-settlement';
	}
	public function getNumber()
	{
		return $this->getLcCode();
	}
	public function getAmountInInvoiceCurrency()
	{
		return $this->getLcAmount();
	}
	public function getInvoiceCurrency()
	{
		return $this->getLcCurrency();
	}
	public function getReceivingOrPaymentCurrency()
	{
		return $this->getLcCurrency();
	}
	public function getTotalWithholdAmount()
	{
		return 0;
	}
	public function getTotalWithholdInInvoiceExchangeRate()
	{
		return 0;
	}
	public function getAmountForMainCurrency()
	{
		return $this->getLcAmount() * $this->getExchangeRate();
	}
	public function getFinancialDuration()
	{
		return $this->financing_duration; 
	}
	public function getSupplierInvoiceId()
	{
		return $this->supplier_invoice_id ;
	}
	public function getFinancedBy()
	{
		return $this->financed_by_bank_or_self;
	}
	public function isFinancedByBank()
	{
		return $this->getFinancedBy() == 'bank';
	}
	public function isFinancedBySelf()
	{
		return $this->getFinancedBy() == 'self';
	}
	public function getPaymentAccountNumberId()
	{
		return $this->payment_account_number_id;
	}
	public function getPaymentAccountTypeId()
	{
		return $this->payment_account_type_id;
	}
	public function getPaymentCurrency()
	{
		return $this->payment_currency;
	}
	
	public function storeCurrentAccountPaymentCreditBankStatement(string $date , $credit , int $financialInstitutionAccountId , int $lcAdvancedPaymentHistoryId = 0 ,  $isActive = 1 , ?string $commentEn = null, ?string $commentAr = null , bool $isRenewalFees = false, bool $isCommissionFees = false )
	{
		return $this->currentAccountPaymentCreditBankStatement()->create([
			'type'=>'payment',
			'financial_institution_account_id'=>$financialInstitutionAccountId,
			'company_id'=>$this->company_id ,
			'lc_advanced_payment_history_id'=>$lcAdvancedPaymentHistoryId,
			'is_active'=>$isActive , // is active خاصة بجزئيه ال commission فقط
			'credit'=>$credit,
			'debit'=>0,
			'date'=>$date,
			'comment_en'=>$commentEn,
			'comment_ar'=>$commentAr,
			'is_commission_fees'=>$isCommissionFees
		]);
	}
	
	public function storeCurrentAccountLcInterestPaymentCreditBankStatement(string $date , $credit , int $financialInstitutionAccountId , int $lcAdvancedPaymentHistoryId = 0 ,  $isActive = 1 , ?string $commentEn = null, ?string $commentAr = null , bool $isRenewalFees = false, bool $isCommissionFees = false )
	{
		return $this->currentAccountPaymentCreditBankStatement()->create([
			'type'=>'lc_interest',
			'financial_institution_account_id'=>$financialInstitutionAccountId,
			'company_id'=>$this->company_id ,
			'lc_advanced_payment_history_id'=>$lcAdvancedPaymentHistoryId,
			'is_active'=>$isActive , // is active خاصة بجزئيه ال commission فقط
			'credit'=>$credit,
			'debit'=>0,
			'date'=>$date,
			'comment_en'=>$commentEn,
			'comment_ar'=>$commentAr,
			'is_commission_fees'=>$isCommissionFees
		]);
	}
	
	
	public static function getCommissionAndFeesAtDates(array &$result ,$foreignExchangeRates , $mainFunctionalCurrency,string $dateFieldName , int $companyId, string $startDate , string $endDate , string $currentWeekYear) 
	{
		$lcsTypes = LcTypes::getAll();
		$mainType = 'cash_expenses';
		$rows = DB::table('current_account_bank_statements')->where('current_account_bank_statements.company_id',$companyId)
						->join('financial_institution_accounts','financial_institution_accounts.id','=','current_account_bank_statements.financial_institution_account_id')
						->join('letter_of_credit_issuances','letter_of_credit_issuances.id','=','current_account_bank_statements.letter_of_credit_issuance_id')
						// ->where('financial_institution_accounts.currency',$currency)
						->whereBetween($dateFieldName,[$startDate,$endDate])
						->where('letter_of_credit_issuance_id','>',0)
						->where(function($q){
							$q->where('is_renewal_fees',1)->orWhere('is_commission_fees',1)->orWhere('is_issuance_fees',1);
						})
						->groupByRaw('letter_of_credit_issuances.lc_type,financial_institution_accounts.currency')
						->selectRaw('letter_of_credit_issuances.lc_type as lc_type ,sum(credit) as paid_amount,financial_institution_accounts.currency as currency,'.$dateFieldName)->get();
		

		$subType = __('LCs Commission & Fees');
		foreach($rows as $row){
			$currentCurrency = $row->currency;
				$date = $row->{$dateFieldName};
				$exchangeRate = ForeignExchangeRate::getExchangeRateForCurrencyAndClosestDate($currentCurrency,$mainFunctionalCurrency,$date,$companyId,$foreignExchangeRates);
			$lcType = $lcsTypes[$row->lc_type];
			$currentPaidAmount = $row->paid_amount*$exchangeRate ;
			$result[$mainType][$subType][$lcType]['weeks'][$currentWeekYear] = isset($result[$mainType][$subType][$lcType]['weeks'][$currentWeekYear]) ? $result[$mainType][$subType][$lcType]['weeks'][$currentWeekYear] + $currentPaidAmount :  $currentPaidAmount;
			$result[$mainType][$subType][$lcType]['total'] = isset($result[$mainType][$subType][$lcType]['total']) ? $result[$mainType][$subType][$lcType]['total']  + $currentPaidAmount : $currentPaidAmount;
			$currentTotal = $currentPaidAmount;
			$result[$mainType][$subType]['total'][$currentWeekYear] = isset($result[$mainType][$subType]['total'][$currentWeekYear]) ? $result[$mainType][$subType]['total'][$currentWeekYear] +  $currentTotal : $currentTotal ;
			// $result[$mainType][$subType]['total']['total_of_total'] = isset($result[$mainType][$subType]['total']['total_of_total']) ? $result[$mainType][$subType]['total']['total_of_total'] + $result[$mainType][$subType]['total'][$currentWeekYear] : $result[$mainType][$subType]['total'][$currentWeekYear];
	//		$totalCashOutFlowArray[$currentWeekYear] = isset($totalCashOutFlowArray[$currentWeekYear]) ? $totalCashOutFlowArray[$currentWeekYear] +   $currentTotal : $currentTotal ;
		}
	
	}
	
	public static function getRemainingLcAmountAtDates(array &$result ,$foreignExchangeRates , $mainFunctionalCurrency , int $companyId, string $startDate , string $endDate , string $currentWeekYear) 
	{
		$lcsTypes = LcTypes::getAll();
		$mainType = 'cash_expenses';
		$rows = DB::table('letter_of_credit_issuances')->where('letter_of_credit_issuances.company_id',$companyId)
		->where('status',LetterOfCreditIssuance::RUNNING)
						// ->where('lc_cash_cover_currency',$currency)
						->whereBetween('due_date',[$startDate,$endDate])
						->selectRaw('due_date,transaction_name,letter_of_credit_issuances.lc_type as lc_type ,(amount_in_main_currency - cash_cover_amount) as paid_amount ,lc_cash_cover_currency as currency')->get();
		
		$subType = __('LCs Remaining Amounts');
		foreach($rows as $row){
			$currentCurrency = $row->currency;
				$date = $row->due_date;
				$exchangeRate = ForeignExchangeRate::getExchangeRateAt($currentCurrency,$mainFunctionalCurrency,$date,$companyId,$foreignExchangeRates);
			$lcType = $lcsTypes[$row->lc_type] . ' [ ' . $row->transaction_name . ' ]';
			$currentPaidAmount = $row->paid_amount *$exchangeRate ;
			$result[$mainType][$subType][$lcType]['weeks'][$currentWeekYear] = isset($result[$mainType][$subType][$lcType]['weeks'][$currentWeekYear]) ? $result[$mainType][$subType][$lcType]['weeks'][$currentWeekYear] + $currentPaidAmount :  $currentPaidAmount;
			$result[$mainType][$subType][$lcType]['total'] = isset($result[$mainType][$subType][$lcType]['total']) ? $result[$mainType][$subType][$lcType]['total']  + $currentPaidAmount : $currentPaidAmount;
			$currentTotal = $currentPaidAmount;
			$result[$mainType][$subType]['total'][$currentWeekYear] = isset($result[$mainType][$subType]['total'][$currentWeekYear]) ? $result[$mainType][$subType]['total'][$currentWeekYear] +  $currentTotal : $currentTotal ;
			// $result[$mainType][$subType]['total']['total_of_total'] = isset($result[$mainType][$subType]['total']['total_of_total']) ? $result[$mainType][$subType]['total']['total_of_total'] + $result[$mainType][$subType]['total'][$currentWeekYear] : $result[$mainType][$subType]['total'][$currentWeekYear];
		//	$totalCashOutFlowArray[$currentWeekYear] = isset($totalCashOutFlowArray[$currentWeekYear]) ? $totalCashOutFlowArray[$currentWeekYear] +   $currentTotal : $currentTotal ;
		}
	
	}
	public function getInterestAmount()
	{
		return $this->interest_amount?:0 ; 
	}
	public function getInterestAmountFormatted()
	{
		return number_format($this->getInterestAmount()) ; 
	}
	public function getInterestCurrency()
	{
		return $this->interest_currency ; 
	}
	
}
