<?php

namespace App\Models;

use App\Enums\LgTypes;
use App\Http\Controllers\LetterOfGuaranteeIssuanceRenewalDateController;
use App\Models\LgRenewalDateHistory;
use App\Services\Api\LetterOfGuaranteeService;
use App\Traits\HasBasicStoreRequest;
use App\Traits\HasCompany;
use App\Traits\Models\HasCommissionStatements;
use App\Traits\Models\HasCurrentAccountCreditStatement;
use App\Traits\Models\HasLetterOfGuaranteeCashCoverStatements;
use App\Traits\Models\HasLetterOfGuaranteeStatements;
use App\Traits\Models\HasUserComment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int|null $cancel_journal_entry_id
 * @property string|null $cash_cover_fees_reference
 * @property string|null $odoo_issuance_fees_reference
 * @property string|null $odoo_commission_fees_reference
 * @property string|null $issuance_fees_account_bank_statement_odoo_id
 * @property int|null $issuance_fees_journal_entry_id
 * @property string|null $commission_fees_account_bank_statement_odoo_id
 * @property int|null $commission_fees_journal_entry_id
 * @property string|null $odoo_error_message
 * @property int $synced_with_odoo
 * @property int|null $journal_entry_id
 * @property int|null $account_bank_statement_odoo_id
 * @property int|null $lg_facility_id
 * @property string $category_name
 * @property string|null $source هو المكان او الطريقه يعني اللي انت انشاتة بيها وانت عندك ثلاث او اربع زراير دول عباره عن المصدر اللي هو قيمة الكولوم دا
 * @property string $status
 * @property string|null $transaction_name
 * @property string|null $transaction_reference
 * @property string|null $transaction_date
 * @property int|null $financial_institution_id
 * @property string|null $cd_or_td_account_type_id
 * @property string|null $cd_or_td_id
 * @property numeric $total_lg_outstanding_balance
 * @property string|null $lg_type
 * @property numeric $lg_type_outstanding_balance
 * @property string|null $lg_code
 * @property int|null $partner_id
 * @property int|null $contract_id
 * @property int|null $purchase_order_id
 * @property string|null $purchase_order_date
 * @property string|null $issuance_date
 * @property int|null $lg_duration_months
 * @property string|null $renewal_date
 * @property numeric $lg_amount
 * @property string|null $lg_currency
 * @property numeric $issuance_fees
 * @property numeric $min_lg_commission_fees
 * @property numeric $cash_cover_rate
 * @property numeric $cash_cover_amount
 * @property string|null $cash_cover_deducted_from_account_type
 * @property string|null $lg_fees_and_commission_account_type
 * @property string|null $cash_cover_deducted_from_account_id
 * @property string|null $lg_fees_and_commission_account_id
 * @property numeric $lg_commission_rate
 * @property numeric $lg_commission_amount
 * @property string|null $lg_commission_interval
 * @property string|null $cash_cover_account_number
 * @property int $company_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $user_comment
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LetterOfGuaranteeIssuanceAdvancedPaymentHistory> $advancedPaymentHistories
 * @property-read int|null $advanced_payment_histories_count
 * @property-read bool|null $advanced_payment_histories_exists
 * @property-read \App\Models\Partner|null $beneficiary
 * @property-read \App\Models\AccountType|null $cashCoverDeductedFromAccountType
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
 * @property-read \App\Models\FinancialInstitution|null $financialInstitutionBank
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LetterOfGuaranteeCashCoverStatement> $letterOfGuaranteeCashCoverStatements
 * @property-read int|null $letter_of_guarantee_cash_cover_statements_count
 * @property-read bool|null $letter_of_guarantee_cash_cover_statements_exists
 * @property-read \App\Models\LetterOfGuaranteeFacility|null $letterOfGuaranteeFacility
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LetterOfGuaranteeStatement> $letterOfGuaranteeStatements
 * @property-read int|null $letter_of_guarantee_statements_count
 * @property-read bool|null $letter_of_guarantee_statements_exists
 * @property-read \App\Models\FinancialInstitutionAccount|null $lgFeesAndCommissionAccount
 * @property-read \App\Models\SalesOrder|null $purchaseOrder
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LgRenewalDateHistory> $renewalDateHistories
 * @property-read int|null $renewal_date_histories_count
 * @property-read bool|null $renewal_date_histories_exists
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereAccountBankStatementOdooId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereCancelJournalEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereCashCoverAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereCashCoverAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereCashCoverDeductedFromAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereCashCoverDeductedFromAccountType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereCashCoverFeesReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereCashCoverRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereCategoryName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereCdOrTdAccountTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereCdOrTdId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereCommissionFeesAccountBankStatementOdooId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereCommissionFeesJournalEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereContractId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereFinancialInstitutionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereIssuanceDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereIssuanceFees($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereIssuanceFeesAccountBankStatementOdooId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereIssuanceFeesJournalEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereJournalEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereLgAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereLgCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereLgCommissionAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereLgCommissionInterval($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereLgCommissionRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereLgCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereLgDurationMonths($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereLgFacilityId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereLgFeesAndCommissionAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereLgFeesAndCommissionAccountType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereLgType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereLgTypeOutstandingBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereMinLgCommissionFees($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereOdooCommissionFeesReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereOdooErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereOdooIssuanceFeesReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance wherePartnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance wherePurchaseOrderDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance wherePurchaseOrderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereRenewalDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereSource($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereSyncedWithOdoo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereTotalLgOutstandingBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereTransactionDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereTransactionName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereTransactionReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeIssuance whereUserComment($value)
 * @mixin \Eloquent
 */
class LetterOfGuaranteeIssuance extends Model
{
    use HasBasicStoreRequest,HasCommissionStatements,HasLetterOfGuaranteeStatements,HasLetterOfGuaranteeCashCoverStatements,HasCurrentAccountCreditStatement,HasUserComment,HasCompany;
    const OPENING_BALANCE = 'opening-balance';
    const NEW_ISSUANCE = 'new-issuance';
    const LG_FACILITY = 'lg-facility';
    const AGAINST_TD ='against-td';
    const AGAINST_CD ='against-cd';
    const HUNDRED_PERCENTAGE_CASH_COVER ='hundred-percentage-cash-cover';
    const RUNNING = 'running';
    const CANCELLED = 'cancelled';
    const EXPIRED = 'expired';
    const LG_FACILITY_BEGINNING_BALANCE = 'lg-facility-beginning-balance';
    const HUNDRED_PERCENTAGE_CASH_COVER_BEGINNING_BALANCE = 'hundred-percentage-cash-cover-beginning-balance';
    const AGAINST_CD_BEGINNING_BALANCE = 'against-cd-beginning-balance';
    const AGAINST_TD_BEGINNING_BALANCE = 'against-td-beginning-balance';
    
    const FOR_CANCELLATION ='for-cancellation'; // هي الفلوس اللي انت حيطتها بسبب انه عمل الغاء
    const AMOUNT_TO_BE_DECREASED ='amount-to-be-decreased'; //
    protected $guarded = ['id'];
    public static function lgSources()
    {
        return [
            self::LG_FACILITY => __('LG Facility'),
            self::AGAINST_TD => __('Against TD'),
            self::AGAINST_CD => __('Against CD'),
            self::HUNDRED_PERCENTAGE_CASH_COVER=>__('100% Cash Cover')
        ];
    }
    public static function getCategories():array
    {
        return [
            self::NEW_ISSUANCE=>__('New Issuance'),
            self::OPENING_BALANCE=>__('Opening Balance')
        ];
    }
    /**
     * * هل هو opening balance or new issuance
     */
    public function getCategoryName():string
    {
        return $this->category_name;
    }
    public function isOpeningBalance():bool
    {
        return $this->getCategoryName() == self::OPENING_BALANCE;
    }
    public function isRunning()
    {
        return $this->getStatus() === self::RUNNING;
    }
    public function isCancelled()
    {
        return $this->getStatus() === self::CANCELLED;
    }
    public function isExpired()
    {
        return $this->getStatus() == self::EXPIRED && !$this->isCancelled();
    }
    public function getStatus()
    {
        if ($this->status == self::CANCELLED) {
            return $this->status ;
        }
        if ($this->getRenewalDate() <= now()) {
            return self::EXPIRED;
        }
        return $this->status ;
    }
    public function getStatusFormatted()
    {
        return camelizeWithSpace($this->getStatus());
    }
    public function getSource()
    {
        
        return $this->source ?: self::LG_FACILITY ;
    }

    public function isCertificateOfDepositSource()
    {
        $accountTypeId = $this->getCdOrTdAccountTypeId() ;
        $accountType = AccountType::find($accountTypeId);
        return $accountType && $accountType->isCertificateOfDeposit();
    }
    public function getSourceFormatted()
    {
        return self::lgSources()[$this->getSource()];
        
    }
    
    public function getTransactionName()
    {
        return $this->transaction_name;
    }
    public function getFinancialInstitutionId():int
    {
        return $this->financial_institution_id;
    }
    public function financialInstitutionBank()
    {
        return $this->belongsTo(FinancialInstitution::class, 'financial_institution_id', 'id') ;
    }
    public function getFinancialInstitutionBankName()
    {
        return $this->financialInstitutionBank ? $this->financialInstitutionBank->getName() : __('N/A') ;
    }

    public function getFinancialInstitutionBankId()
    {
        return $this->financialInstitutionBank ? $this->financialInstitutionBank->id : 0 ;
    }
    public function getLgType()
    {
        return $this->lg_type;
    }
    public function getLgTypeFormatted()
    {
        return LgTypes::getAll()[$this->getLgType()];
    }
    public function isAdvancedPayment()
    {
        return $this->getLgType() === LgTypes::ADVANCED_PAYMENT_LGS;
    }
    public function getTotalLgOutstandingBalance()
    {
        return $this->total_lg_outstanding_balance ?: 0 ;
    }
    public function getTotalLgOutstandingBalanceFormatted()
    {
        return number_format($this->getTotalLgOutstandingBalance());
    }
    public function getLgTypeOutstandingBalance()
    {
        return $this->lg_type_outstanding_balance ?: 0 ;
    }
    public function getLgTypeOutstandingBalanceFormatted()
    {
        return number_format($this->getLgTypeOutstandingBalance());
    }
    public function getLgCode()
    {
        return $this->lg_code ;
    }
    public function beneficiary()
    {
        return $this->belongsTo(Partner::class, 'partner_id', 'id') ;
    }
    public function getPartnerOdooId()
    {
        return $this->beneficiary ? $this->beneficiary->getOdooId():null ;
    }
    public function getBeneficiaryName()
    {
        $beneficiary = $this->beneficiary ;
        return  $beneficiary ? $beneficiary->getName(): __('N/A');
    }
    public function getBeneficiaryId()
    {
        $beneficiary = $this->beneficiary ;
        return  $beneficiary ? $beneficiary->getId(): 0 ;
    }
    
    public function getBeneficiaryOdooId()
    {
        $beneficiary = $this->beneficiary ;
        return  $beneficiary ? $beneficiary->getOdooId(): 0 ;
    }
    

    public function contract()
    {
        return $this->belongsTo(Contract::class, 'contract_id', 'id');
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
        return $this->belongsTo(SalesOrder::class, 'purchase_order_id', 'id');
    }
    // public function getPurchaseOrderName()
    // {
    //     $purchaseOrder = $this->purchaseOrder ;
    //     return  $purchaseOrder ? $purchaseOrder->getName(): 0 ;
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
        $date = explode('/', $value);
        if (count($date) != 3) {
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
        $date = explode('/', $value);
        if (count($date) != 3) {
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
        $date = explode('/', $value);
        if (count($date) != 3) {
            $this->attributes['issuance_date'] =  $value ;
            return ;
        }
        $month = $date[0];
        $day = $date[1];
        $year = $date[2];

        $this->attributes['issuance_date'] = $year.'-'.$month.'-'.$day;
    }

    public function getLgDurationMonths()
    {
        return $this->lg_duration_months;
    }

    public function getRenewalDate()
    {
        return $this->renewal_date;
    }
    public function getRenewalDateFormatted()
    {
        $renewalDate = $this->getRenewalDate() ;
        return $renewalDate ? Carbon::make($renewalDate)->format('d-m-Y'):null ;
    }
    public function setRenewalDateAttribute($value)
    {
        $date = explode('/', $value);
        if (count($date) != 3) {
            $this->attributes['renewal_date'] =  $value ;
            return ;
        }
        $month = $date[0];
        $day = $date[1];
        $year = $date[2];

        $this->attributes['renewal_date'] = $year.'-'.$month.'-'.$day;
    }

    public function getLgAmount()
    {
        return $this->lg_amount ?: 0 ;
    }
    public function getLgAmountFormatted()
    {
        return number_format($this->getLgAmount());
    }
    public function getLgCurrency()
    {
        return $this->lg_currency ;
    }

    public function getCashCoverRate()
    {
        return $this->cash_cover_rate?:0;
    }
    public function getCashCoverRateFormatted()
    {
        return number_format($this->getCashCoverRate(), 1);
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
    public function cashCoverDeductedFromAccountType()
    {
        return $this->belongsTo(AccountType::class, 'cash_cover_deducted_from_account_type', 'id');
    }
    public function getCashCoverDeductedFromAccountId()
    {
        return $this->cash_cover_deducted_from_account_id ?: $this->lg_fees_and_commission_account_id;
    }
    public function getCommissionFeesAccountId()
    {
        return $this->lg_fees_and_commission_account_id;
    }
    public function isCdOrTd():bool
    {
        return  in_array($this->getCashCoverDeductedFromAccountTypeId(), [28,29]);
    }
    public function getFeesAndCommissionAccountTypeId()
    {
        return $this->lg_fees_and_commission_account_type;
    }
    public function lgFeesAndCommissionAccount()
    {
        return $this->belongsTo(FinancialInstitutionAccount::class, 'lg_fees_and_commission_account_id', 'id');
    }
    public function getFeesAndCommissionAccountId():int
    {
        return $this->lgFeesAndCommissionAccount ? $this->lgFeesAndCommissionAccount->id : 0 ;
    }
    public function getLgCommissionRate()
    {
        return $this->lg_commission_rate ?: 0;
    }
    public function getLgCommissionRateFormatted()
    {
        return number_format($this->getLgCommissionRate(), 1);
    }
    public function getLgCommissionAmount()
    {
        return $this->lg_commission_amount ?: 0 ;
    }
    public function getLgCommissionAmountFormatted()
    {
        return number_format($this->getLgCommissionAmount());
    }
    public function getLgCommissionInterval()
    {
        return $this->lg_commission_interval ;
    }
    public function letterOfGuaranteeStatements()
    {
        return $this->hasMany(LetterOfGuaranteeStatement::class, 'letter_of_guarantee_issuance_id', 'id');
    }
    public function letterOfGuaranteeCashCoverStatements()
    {
        return $this->hasMany(LetterOfGuaranteeCashCoverStatement::class, 'letter_of_guarantee_issuance_id', 'id');
    }
    
    public function currentAccountCreditBankStatement()
    {
        return $this->hasOne(CurrentAccountBankStatement::class, 'letter_of_guarantee_issuance_id', 'id')->where('is_credit', 1);
    }
    public function currentAccountCreditBankStatements()
    {
        return $this->hasMany(CurrentAccountBankStatement::class, 'letter_of_guarantee_issuance_id', 'id')->where('is_credit', 1)->orderBy('full_date', 'desc');
    }
    
    public function currentAccountDebitBankStatement()
    {
        return $this->hasOne(CurrentAccountBankStatement::class, 'letter_of_guarantee_issuance_id', 'id')->where('is_debit', 1);
    }
    public function currentAccountDebitBankStatements()
    {
        return $this->hasMany(CurrentAccountBankStatement::class, 'letter_of_guarantee_issuance_id', 'id')->where('is_debit', 1)->orderBy('full_date', 'desc');
    }
    /**
     * * علشان نجيب الاربعه مع بعض مرة واحدة
     */
    public function currentAccountBankStatements()
    {
        return $this->hasMany(CurrentAccountBankStatement::class, 'letter_of_guarantee_issuance_id', 'id')->orderBy('full_date', 'desc');
    }
    public function getCdOrTdAccountTypeId()
    {
        return $this->cd_or_td_account_type_id ?:0 ;
    }
    
    public function getCdOrTdId()
    {
        return $this->cd_or_td_id;
        // $account = AccountType::find($this->getCdOrTdAccountTypeId());
        // if($account && $account->isCertificateOfDeposit() ){
        // 	return CertificatesOfDeposit::findByAccountNumber( $this->getCdOrTdAccountNumber(),$this->company_id)->id;
        // }
        // if($account && $account->isTimeOfDeposit() ){
        // 	return TimeOfDeposit::findByAccountNumber( $this->getCdOrTdAccountNumber(),$this->company_id )->id;
        // }
        // return 0 ;
    }
    public function advancedPaymentHistories():HasMany
    {
        return $this->hasMany(LetterOfGuaranteeIssuanceAdvancedPaymentHistory::class, 'letter_of_guarantee_issuance_id', 'id');
    }
    public function getLgCurrentAmount()
    {
        return $this->getLgAmount() - $this->advancedPaymentHistories->sum('amount');
    }
    public function getLgCurrentAmountFormatted()
    {
        return number_format($this->getLgCurrentAmount());
    }
    public function isCashCoverCurrentAccount():bool
    {
        return $this->cashCoverDeductedFromAccountType && $this->cashCoverDeductedFromAccountType->isCurrentAccount();
    }
    
    public function deleteAllRelations():self
    {
        

        $company = $this->company;
        
        if ($company->hasOdooIntegrationCredentials()) {
            $odooLetterOfGuaranteeIssuanceService = new LetterOfGuaranteeService($company);
            foreach (['journal_entry_id','commission_fees_journal_entry_id','issuance_fees_journal_entry_id','cancel_journal_entry_id'] as $journalColumnName) {
                $currentJournalEntryId = $this->{$journalColumnName};
                if ($currentJournalEntryId) {
                    $odooLetterOfGuaranteeIssuanceService->unlink($currentJournalEntryId);
                }
                
            }
        }
       
        foreach ($this->advancedPaymentHistories as $advancedPaymentHistory) {
            $advancedPaymentHistory->deleteAllRelations();
        }
        foreach ($this->renewalDateHistories as $renewalDateHistory) {
            (new LetterOfGuaranteeIssuanceRenewalDateController)->destroy($company, $this, $renewalDateHistory);
        }
        LetterOfGuaranteeIssuanceAdvancedPaymentHistory::deleteButTriggerChangeOnLastElement($this->advancedPaymentHistories);
        CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($this->currentAccountDebitBankStatements);
        CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($this->currentAccountCreditBankStatements);
        CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($this->currentAccountCreditBankStatements()->withoutGlobalScope('only_active')->get());
        CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($this->currentAccountBankStatements);
        LetterOfGuaranteeStatement::deleteButTriggerChangeOnLastElement($this->letterOfGuaranteeStatements);
        LetterOfGuaranteeCashCoverStatement::deleteButTriggerChangeOnLastElement($this->letterOfGuaranteeCashCoverStatements);
        return $this;
    }
    public function renewalDateHistories():HasMany
    {
        return $this->hasMany(LgRenewalDateHistory::class, 'letter_of_guarantee_issuance_id', 'id');
    }
    public function renewalFeesCurrentAccountBankStatement(?string $renewalDate)
    {
        return $this->hasOne(CurrentAccountBankStatement::class, 'letter_of_guarantee_issuance_id', 'id')->withoutGlobalScope('only_active')->where('date', $renewalDate)->where('is_renewal_fees', 1)->first();
    }

    
    public function getMinLgCommissionFees():float
    {
        return $this->min_lg_commission_fees;
    }
    public function getRenewalDateBefore(string $date):?string
    {
        $row = $this->renewalDateHistories->where('renewal_date', '<', $date)->sortByDesc('renewal_date')->first() ;
        return  $row ? $row->renewal_date : null;
    }
    public function letterOfGuaranteeFacility()
    {
        return $this->belongsTo(LetterOfGuaranteeFacility::class, 'lg_facility_id', 'id');
    }
    public function getLgFacilityId()
    {
        return $this->letterOfGuaranteeFacility ? $this->letterOfGuaranteeFacility->id:0;
    }
    public function getLgFacilityName()
    {
        return $this->letterOfGuaranteeFacility ? $this->letterOfGuaranteeFacility->getName(): __('N/A');
    }
    public function getIssuanceFees()
    {
        return $this->issuance_fees ;
    }
    public static function getCommissionAndFeesAtDates(array &$result, $foreignExchangeRates, $mainFunctionalCurrency, string $dateFieldName, int $companyId, string $startDate, string $endDate, string $currentWeekYear, ?int $contractId = null):void
    {
        $lgsTypes = LgTypes::getAll();
        $mainType = 'cash_expenses';
        $rows = DB::table('current_account_bank_statements')->where('current_account_bank_statements.company_id', $companyId)
                        ->join('financial_institution_accounts', 'financial_institution_accounts.id', '=', 'current_account_bank_statements.financial_institution_account_id')
                        ->join('letter_of_guarantee_issuances', 'letter_of_guarantee_issuances.id', '=', 'current_account_bank_statements.letter_of_guarantee_issuance_id')
                        // ->where('financial_institution_accounts.currency',$currency)
                        ->whereBetween($dateFieldName, [$startDate,$endDate])
                        ->where('letter_of_guarantee_issuance_id', '>', 0)
                        ->where(function ($q) {
                            $q->where('is_renewal_fees', 1)->orWhere('is_commission_fees', 1)->orWhere('is_issuance_fees', 1);
                        })
                        ->when($contractId, function ($q) use ($contractId) {
                            $q->where('contract_id', $contractId);
                        })
                        ->groupByRaw('letter_of_guarantee_issuances.lg_type,financial_institution_accounts.currency')
                        ->selectRaw('letter_of_guarantee_issuances.lg_type as lg_type ,sum(credit) as paid_amount,financial_institution_accounts.currency as currency,'.$dateFieldName)->get();
        

        $subType = __('LGs Commission & Fees');
        foreach ($rows as $row) {
            
            $currentCurrency = $row->currency;
            $date = $row->{$dateFieldName};
            $exchangeRate = ForeignExchangeRate::getExchangeRateForCurrencyAndClosestDate($currentCurrency, $mainFunctionalCurrency, $date, $companyId, $foreignExchangeRates);
                
            $lgType = $lgsTypes[$row->lg_type];
            $currentPaidAmount = $row->paid_amount*$exchangeRate ;
            $result[$mainType][$subType][$lgType]['weeks'][$currentWeekYear] = isset($result[$mainType][$subType][$lgType]['weeks'][$currentWeekYear]) ? $result[$mainType][$subType][$lgType]['weeks'][$currentWeekYear] + $currentPaidAmount :  $currentPaidAmount;
            $result[$mainType][$subType][$lgType]['total'] = isset($result[$mainType][$subType][$lgType]['total']) ? $result[$mainType][$subType][$lgType]['total']  + $currentPaidAmount : $currentPaidAmount;
            $currentTotal = $currentPaidAmount;
            $result[$mainType][$subType]['total'][$currentWeekYear] = isset($result[$mainType][$subType]['total'][$currentWeekYear]) ? $result[$mainType][$subType]['total'][$currentWeekYear] +  $currentTotal : $currentTotal ;
            // $result[$mainType][$subType]['total']['total_of_total'] = isset($result[$mainType][$subType]['total']['total_of_total']) ? $result[$mainType][$subType]['total']['total_of_total'] + $result[$mainType][$subType]['total'][$currentWeekYear] : $result[$mainType][$subType]['total'][$currentWeekYear];
            // $totalCashOutFlowArray[$currentWeekYear] = isset($totalCashOutFlowArray[$currentWeekYear]) ? $totalCashOutFlowArray[$currentWeekYear] +   $currentTotal : $currentTotal ;
        }
    
    }
    
    public static function getCashCovers(array &$letterOfGuaranteeModelData , array &$result, $foreignExchangeRates, $mainFunctionalCurrency, string $dateFieldName, int $companyId, string $startDate, string $endDate, string $currentWeekYear, ?int $contractId = null)
    {
        $lgsTypes = LgTypes::getAll();
        $mainType = 'customers';
        $commonQuery = DB::table('letter_of_guarantee_cash_cover_statements')
                        ->where('letter_of_guarantee_cash_cover_statements.company_id', $companyId)
                        ->join('letter_of_guarantee_issuances', 'letter_of_guarantee_issuances.id', '=', 'letter_of_guarantee_cash_cover_statements.letter_of_guarantee_issuance_id')
						->join('partners','partners.id','=','letter_of_guarantee_issuances.partner_id')
                        ->whereBetween($dateFieldName, [$startDate,$endDate])
                        ->where('letter_of_guarantee_issuance_id', '>', 0)
                        ->when($contractId, function ($q) use ($contractId) {
                            $q->where('contract_id', $contractId);
                        }) ;
        $commonQueryBase = clone $commonQuery;
        $rows = $commonQuery
        ->groupByRaw('letter_of_guarantee_issuances.lg_type,letter_of_guarantee_cash_cover_statements.currency')
        ->selectRaw('letter_of_guarantee_issuances.lg_type as lg_type ,sum(debit) as total_amount , letter_of_guarantee_cash_cover_statements.currency as currency,'.$dateFieldName)->get();
                    
               $totalCashInFlowKey = __('Total Cash Inflow');         
        $subType = __('Cancelled LGs Cash Cover');
       $allRowsWithoutGrouping = $commonQueryBase->get();
        foreach ($rows as $row) {
            $currentCurrency = $row->currency;
            $date = $row->{$dateFieldName};
            $exchangeRate = ForeignExchangeRate::getExchangeRateAt($currentCurrency, $mainFunctionalCurrency, $date, $companyId, $foreignExchangeRates);
            $lgType = $lgsTypes[$row->lg_type];
            $currentPaidAmount = $row->total_amount *$exchangeRate;
            $result[$mainType][$subType][$lgType]['weeks'][$currentWeekYear] = isset($result[$mainType][$subType][$lgType]['weeks'][$currentWeekYear]) ? $result[$mainType][$subType][$lgType]['weeks'][$currentWeekYear] + $currentPaidAmount :  $currentPaidAmount;
            $result[$mainType][$subType][$lgType]['total'] = isset($result[$mainType][$subType][$lgType]['total']) ? $result[$mainType][$subType][$lgType]['total']  + $currentPaidAmount : $currentPaidAmount;
            $currentTotal = $currentPaidAmount;
            $result[$mainType][$subType]['total'][$currentWeekYear] = isset($result[$mainType][$subType]['total'][$currentWeekYear]) ? $result[$mainType][$subType]['total'][$currentWeekYear] +  $currentTotal : $currentTotal ;
			
			$result['customers'][$totalCashInFlowKey]['total'][$currentWeekYear] = isset($result['customers'][$totalCashInFlowKey]['total'][$currentWeekYear]) ? $result['customers'][$totalCashInFlowKey]['total'][$currentWeekYear] + $currentPaidAmount :$currentPaidAmount;
						
        }
		/**
		 * * دا هنعرضه في البوب اب
		 */
		// $formattedResults = [];
		foreach($allRowsWithoutGrouping as $rowWithoutGrouping){
			 $currentCurrency = $rowWithoutGrouping->currency;
            $date = $rowWithoutGrouping->{$dateFieldName};
			$partnerName = $rowWithoutGrouping->name;
			 $lgCode = $rowWithoutGrouping->lg_code;
			// $lgCode = LetterOfGuaranteeIssuance::find($rowWithoutGrouping->letter_of_guarantee_issuance_id)->getName();
			$lgType = $lgsTypes[$rowWithoutGrouping->lg_type];
            $exchangeRate = ForeignExchangeRate::getExchangeRateAt($currentCurrency, $mainFunctionalCurrency, $date, $companyId, $foreignExchangeRates);
			$currentPaidAmount = $rowWithoutGrouping->debit *$exchangeRate;
			$letterOfGuaranteeModelData[$lgType]['weeks'][$currentWeekYear][] = [
				'amount'=>$currentPaidAmount,
				'lg_code'=>$lgCode,
				'name'=>$partnerName
			];
		}
		
		
    
    }
    
    public function handleIssuanceAndCommissionFeesForOdoo()
    {
        $issuanceDate = $this->getIssuanceDate();
        $company=  $this->company;
        
        if ($this->isOpeningBalance() || !$company->withinIntegrationDate($issuanceDate)) {
            return ;
        }
        $odooSetting = $company->odooSetting;
        $financialInstitutionAccountForCommissionAndFees = FinancialInstitutionAccount::find($this->getCommissionFeesAccountId());
        if (is_null($odooSetting)) {
            return ;
        }
        
        $analytic_distribution = $this->formatAnalysisDistribution() ;
        $odooLetterOfGuaranteeIssuance = new LetterOfGuaranteeService($company);
        $issuanceFees  = $this->getIssuanceFees();
        $fromAccountNumber = $financialInstitutionAccountForCommissionAndFees->getAccountNumber();
        $journalId = $financialInstitutionAccountForCommissionAndFees->financialInstitution->getJournalIdForAccount(27, $fromAccountNumber);
        $accountOdooId = $financialInstitutionAccountForCommissionAndFees->financialInstitution->getOdooIdForAccount(27, $fromAccountNumber);
        $currency = $this->getLgCurrency();
        $odooCurrencyId = Currency::getOdooId($currency);
        $debitOdooAccountId = $odooSetting->getLetterOfGuaranteeIssuanceFeesId();
        $lgType =$this->getLgTypeFormatted();
        $ref = $lgType . ' Issuance Fees';
        $message = $ref;
        if ($issuanceFees > 0) {
            $result = $odooLetterOfGuaranteeIssuance->createLgIssuanceCashCover($issuanceDate, $issuanceFees, $journalId, $odooCurrencyId, $debitOdooAccountId, $accountOdooId, $this->getBeneficiaryOdooId(), $ref, $message, $analytic_distribution);
            $this->issuance_fees_account_bank_statement_odoo_id=$result['account_bank_statement_line_id'];
            $this->issuance_fees_journal_entry_id=$result['journal_entry_id'];
            $this->odoo_issuance_fees_reference=$result['reference'];
            $this->save();
        }
        $commissionFees = $this->getLgCommissionAmount();
        if ($commissionFees > 0) {
            $ref = $lgType . ' Commission Fees';
            $message = $ref;
            $debitOdooAccountId = $odooSetting->getLetterOfGuaranteeCommissionFeesId();
            $result = $odooLetterOfGuaranteeIssuance->createLgIssuanceCashCover($issuanceDate, $commissionFees, $journalId, $odooCurrencyId, $debitOdooAccountId, $accountOdooId, $this->getBeneficiaryOdooId(), $ref, $message, $analytic_distribution);
     //       $this->commission_fees_account_bank_statement_odoo_id=$result['account_bank_statement_line_id'];
            $this->commission_fees_journal_entry_id=$result['journal_entry_id'];
            $this->odoo_commission_fees_reference=$result['reference'];
            $this->save();
        }
    }
    
    public function handleLgIssuanceCashCoverForOdoo()
    {
        $isOpeningBalance = $this->isOpeningBalance();
        $isCdOrTdCashCoverAccount = $this->isCdOrTd();
        $company = $this->company;
        $issuanceDate = $this->getIssuanceDate();
        // new issuance and cd
        //
        
        if ($company->hasOdooIntegrationCredentials() && !$isOpeningBalance && !$isCdOrTdCashCoverAccount && $company->withinIntegrationDate($issuanceDate)) {
            $financialInstitutionAccountForCashCover = FinancialInstitutionAccount::find($this->getCashCoverDeductedFromAccountId());
            if (is_null($financialInstitutionAccountForCashCover)) {
                return;
            }
            $analytic_distribution = $this->formatAnalysisDistribution() ;
            $odooLetterOfGuaranteeIssuance = new LetterOfGuaranteeService($company);
            $fromAccountNumber = $financialInstitutionAccountForCashCover->getAccountNumber();
            $journalId = $financialInstitutionAccountForCashCover->financialInstitution->getJournalIdForAccount(27, $fromAccountNumber);
            $accountOdooId = $financialInstitutionAccountForCashCover->financialInstitution->getOdooIdForAccount(27, $fromAccountNumber);
            $currency = $this->getLgCurrency();
            $odooCurrencyId = Currency::getOdooId($currency);
            $lgType = $this->getLgType();
            $cashCoverAmount = $this->getCashCoverAmount();
            $lgDebitOdooAccountId = FinancialInstitutionAccount::getLetterOfGuaranteeOdooIdFromType($lgType, $company->id);
            
            $ref = $this->generateCashCoverRef();
            $message = $this->generateCashCoverMessage();
            $result = $odooLetterOfGuaranteeIssuance->createLgIssuanceCashCover($issuanceDate, $cashCoverAmount, $journalId, $odooCurrencyId, $lgDebitOdooAccountId, $accountOdooId, $this->getBeneficiaryOdooId(), $ref, $message, $analytic_distribution);
            $this->account_bank_statement_odoo_id=$result['account_bank_statement_line_id'];
            $this->journal_entry_id=$result['journal_entry_id'];
            $this->cash_cover_fees_reference=$result['reference'];
            $this->save();
            
        }
    }
    
    public function generateCashCoverRef():string
    {
        return __('Create Cash Cover ') . ' ' . $this->getLgTypeFormatted();
    }
    public function generateCashCoverMessage():string
    {
        return __('Cash Cover');
    }
    
    
    public function generateIssuanceRef():string
    {
        return __('Create Issuance') . ' ' . $this->getLgTypeFormatted();
    }
    public function generateIssuanceMessage():string
    {
        return __('Cash Cover');
    }
    public function generateCancelRef():string
    {
        return __('Cancel') . ' ' . $this->getLgTypeFormatted() ;
    }
    public function generateCancelMessage():string
    {
        return __('Cash Cover Refund');
    }
    
    public function generateDecreasedRef():string
    {
        return __('Decreased') . ' ' . $this->getLgTypeFormatted() ;
    }
    public function generateDecreasedMessage():string
    {
        return __('Cash Cover Decreased');
    }
    
    public function getOdooReferenceNames():array
    {
		/**
		 * ! odoo_commission_fees_reference should be returned from current_account_bank_statements table
		 */
        $references = [];
        $i = 0;
        foreach ([
        //    'odoo_commission_fees_reference',
            'odoo_issuance_fees_reference',
            'cash_cover_fees_reference'
        ] as $columnName) {
            if ($this->{$columnName}) {
                $i ++;
                $references[] = $i .'-'.$this->{$columnName};
            }
        }
		
        return $references ;
    }
    public function fullyIntegratedWithOdoo()
    {
        return count($this->getOdooReferenceNames());
    }
    public function formatAnalysisDistribution():array
    {
        $contract = $this->contract;
        if (!$contract) {
            return [];
        }
        $result = [];
        $projectAccountId = $contract->project_account_id ;
        if ($projectAccountId) {
            $result[strval($projectAccountId)] =(float)100;
        }
        if (count($result) == 1) {
            $result["-0"] = 0.0;
        }
        return $result;
            
    }
    public function cancelOdooLg($cancellationDate, $cashCoverAmount, $ref, $message, $letterOfGuaranteeIssuanceAdvancedPaymentHistory = null, $journalEntryIdColumnName = 'journal_entry_id')
    {
        $model = $letterOfGuaranteeIssuanceAdvancedPaymentHistory ? $letterOfGuaranteeIssuanceAdvancedPaymentHistory : $this;
        $financialInstitutionId = $this->financial_institution_id ;
        $financialInstitution = FinancialInstitution::find($financialInstitutionId);
        $company = $this->company;
        $isCdOrTdCashCoverAccount = $this->isCdOrTd();
        $odooLetterOfGuaranteeIssuance = null ;
        foreach ([$journalEntryIdColumnName] as $journalColumnName) {
            $currentJournalEntryId = $this->{$journalColumnName};
            $odooLetterOfGuaranteeIssuance = new LetterOfGuaranteeService($company);
            if ($currentJournalEntryId) {
                $odooLetterOfGuaranteeIssuance->unlink($currentJournalEntryId);
            }
        }
        if ($company->hasOdooIntegrationCredentials() && !$isCdOrTdCashCoverAccount && $company->withinIntegrationDate($cancellationDate)) {
            $lgType= $this->getLgType();
            $financialInstitutionAccount = FinancialInstitutionAccount::find($this->getCashCoverDeductedFromAccountId());
            $currency = $financialInstitutionAccount->getCurrency();
            $odooLetterOfGuaranteeIssuance = new LetterOfGuaranteeService($company);
            $fromAccountNumber = $financialInstitutionAccount->getAccountNumber();
            $journalId = $financialInstitution->getJournalIdForAccount(27, $fromAccountNumber);
            $odooCurrencyId = Currency::getOdooId($currency);
            $lgOdooAccountId = FinancialInstitutionAccount::getLetterOfGuaranteeOdooIdFromType($lgType, $company->id);
            $accountOdooId = $financialInstitution->getOdooIdForAccount(27, $fromAccountNumber);
            // $amount = ;
            $result = $odooLetterOfGuaranteeIssuance->createLgCancelCashCover($cancellationDate, $cashCoverAmount, $journalId, $odooCurrencyId, $lgOdooAccountId, $accountOdooId, $this->getBeneficiaryOdooId(), $ref, $message);
            //     $model->account_bank_statement_odoo_id=$result['account_bank_statement_line_id'];
            $model->{$journalEntryIdColumnName}=$result['journal_entry_id'];
            $model->save();
            
        }
    
            
    }
    public function getCancellationAmount()
    {
        $isAdvancedPayment =  $this->isAdvancedPayment() ;
        return  $isAdvancedPayment ? $this->getLgCurrentAmount() :  $this->getLgAmount();
    }
    public function getCashCoverCancellationAmount()
    {
        $isAdvancedPayment =  $this->isAdvancedPayment() ;
        $amount = $this->getCancellationAmount();
        $cashCoverRate = $this->getCashCoverRate() / 100;
        $cashCoverAmount = $isAdvancedPayment ? $amount *$cashCoverRate  : $this->getCashCoverAmount();
        return $cashCoverAmount;
    }
}
