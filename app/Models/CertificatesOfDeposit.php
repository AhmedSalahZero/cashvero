<?php

namespace App\Models;

use App\Interfaces\Models\IHasDebitCurrentAccountStatement;
use App\Models\FinancialInstitutionAccount;
use App\Traits\HasCompany;
use App\Traits\HasDepositAccount;
use App\Traits\HasLastStatementAmount;
use App\Traits\HasPeriodicInterest;
use App\Traits\Models\HasBlockedAgainst;
use App\Traits\Models\HasCreditStatements;
use App\Traits\Models\HasDebitCurrentAccountStatement;
use App\Traits\Models\HasDebitStatements;
use App\Traits\Models\HasDeleteOdoo;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * * توفر شهادات الإيداع ) (CDsللمدخر ين طر يقة لكسب معدل فائدة أعلى على مدخراتك مقابل الموافقة على حجز
 * *    أموالك لفترة زمنية محددة - مع الحفاظ على أموالك آمنة بفضل حمايتها من البنك المركزي
 *
 * @property int $id
 * @property string|null $store_break_journal_entry_id
 * @property string|null $inbound_break_odoo_reference
 * @property int $is_at_maturity
 * @property int|null $inbound_break_journal_entry_id
 * @property int|null $outbound_break_journal_entry_id
 * @property int|null $break_account_bank_statement_line_id
 * @property int|null $break_journal_entry_id
 * @property int|null $renewal_account_bank_statement_line_id
 * @property int|null $renewal_journal_entry_id
 * @property int|null $interest_account_bank_statement_line_id
 * @property int|null $interest_journal_entry_id
 * @property int|null $maturity_account_bank_statement_line_id
 * @property int|null $maturity_journal_entry_id
 * @property int|null $store_account_bank_statement_line_id
 * @property int|null $store_journal_entry_id
 * @property int|null $inbound_journal_entry_id
 * @property int|null $outbound_journal_entry_id
 * @property int|null $deducted_from_account_id
 * @property int|null $odoo_id
 * @property string|null $odoo_code
 * @property string $status
 * @property int $financial_institution_id
 * @property string|null $account_number
 * @property numeric|null $amount
 * @property string|null $currency
 * @property numeric $interest_rate
 * @property numeric $interest_amount
 * @property numeric|null $actual_interest_amount
 * @property string|null $deposit_date
 * @property string|null $start_date
 * @property string|null $end_date
 * @property string|null $maturity_amount_added_to_account_id
 * @property int|null $company_id
 * @property int|null $created_by
 * @property int|null $update_by
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $break_date هو عباره عن التاريخ اللي قررت فية تكسر شهادة الايداع
 * @property numeric|null $break_interest_amount عباره عن الفايدة اللي نزلت علي الحساب بسبب كسرك الشهادة
 * @property numeric|null $break_charge_amount عبارة عن رسوم ادارية بسبب كسر الشهادة
 * @property-read \App\Models\Company|null $company
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
 * @property-read \App\Models\FinancialInstitution|null $financialInstitution
 * @property-read \App\Models\FullySecuredOverdraft|null $fullySecuredCleanOverdraft
 * @property-read \App\Models\LetterOfGuaranteeIssuance|null $letterOfGuaranteeIssuance
 * @property-read \App\Models\FinancialInstitutionAccount|null $maturityAmountAddedToAccount
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereActualInterestAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereBreakAccountBankStatementLineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereBreakChargeAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereBreakDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereBreakInterestAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereBreakJournalEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereDeductedFromAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereDepositDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereFinancialInstitutionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereInboundBreakJournalEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereInboundBreakOdooReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereInboundJournalEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereInterestAccountBankStatementLineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereInterestAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereInterestJournalEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereInterestRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereIsAtMaturity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereMaturityAccountBankStatementLineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereMaturityAmountAddedToAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereMaturityJournalEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereOdooCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereOdooId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereOutboundBreakJournalEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereOutboundJournalEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereRenewalAccountBankStatementLineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereRenewalJournalEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereStoreAccountBankStatementLineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereStoreBreakJournalEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereStoreJournalEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereUpdateBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereUpdatedAt($value)
 * @property string|null $inbound_odoo_reference
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CertificatesOfDeposit whereInboundOdooReference($value)
 * @mixin \Eloquent
 */
class CertificatesOfDeposit extends Model implements IHasDebitCurrentAccountStatement
{
    use HasDebitStatements,HasDebitCurrentAccountStatement,HasCreditStatements,HasBlockedAgainst,HasLastStatementAmount,HasDepositAccount,HasDeleteOdoo,HasCompany,HasPeriodicInterest ;
    protected $guarded = ['id'];
    const RUNNING = 'running';
    const MATURED = 'matured';
    const BROKEN = 'broken';
    public static function getAllTypes()
    {
        return [
            self::RUNNING,
            self::MATURED,
            self::BROKEN
        ];
    }

    public function getStatus()
    {
        return $this->status ;
    }
    public function isRunning()
    {
        return $this->getStatus() === self::RUNNING;
    }
    /**
     * * معناه انها خلص استوفيت وهتاخد قيمة الفايدة وقيمة الشهادة
     */
    public function isMatured()
    {
        return $this->getStatus() === self::MATURED;
    }
    /**
     * * معناه انك قررت تكسرها قبل فتره انتهائها وبالتالي هتاخد قيمتها بس هتدفع فايدة ورسوم الخ
     */
    public function isBroken()
    {
        return $this->getStatus() === self::BROKEN;
    }

    public function getStartDate()
    {
        return $this->start_date;
    }
    public function getStartDateFormatted()
    {
        $startDate = $this->start_date ;
        return $startDate ? Carbon::make($startDate)->format('d-m-Y'):null ;
    }
    public function getDepositDate()
    {
        return $this->deposit_date;
    }
    public function getDepositDateFormatted()
    {
        $depositDate = $this->deposit_date ;
        return $depositDate ? Carbon::make($depositDate)->format('d-m-Y'):null ;
    }
    /**
     * * تاريخ كسر شهادة الايداع
     */
    public function getBreakDate()
    {
        return $this->break_date;
    }
    public function getBreakDateFormatted()
    {
        $breakDate = $this->break_date ;
        return $breakDate ? Carbon::make($breakDate)->format('d-m-Y'):null ;
    }

    /**
     * * تاريخ استحقاق الايداع بس مش شرط يكون هو دا الفعلي لو التاريخ دا كان يوم جمعه مثلا فاهيكون اجازة
     */
    public function getEndDate()
    {
        return $this->end_date;
    }
    /**
     * * لما يتم تاكيد العمليه وقتها الفلوس الخاصة بالوديعه دي هتنزل علي انهي حساب ؟
     */
    public function getMaturityAmountAddedToAccountId():?int
    {
        return (int)$this->maturity_amount_added_to_account_id ;
    }
    public function getMaturityAmountAddedToAccountNumber()
    {
        return $this->maturityAmountAddedToAccount ? $this->maturityAmountAddedToAccount->getAccountNumber() : null ;
    }
    public function maturityAmountAddedToAccount():BelongsTo
    {
        return $this->belongsTo(FinancialInstitutionAccount::class, 'maturity_amount_added_to_account_id', 'id');
    }

    public function getEndDateFormatted()
    {
        $endDate = $this->getEndDate() ;
        return $endDate ? Carbon::make($endDate)->format('d-m-Y'):null ;
    }
    public function getAccountNumber()
    {
        return $this->account_number ;
    }

    public function getAmount()
    {
        return $this->amount ;
    }
    public function getAmountFormatted()
    {
        $amount = $this->getAmount();
        return number_format($amount) ;
    }

    public function getInterestRate()
    {
        return $this->interest_rate?:0;
    }

    public function getInterestRateFormatted()
    {
        return $this->getInterestRate() .' %';
    }

    public function getInterestAmount()
    {
        return $this->interest_amount?:0;
    }

    public function getInterestAmountFormatted()
    {
        $interestAmount = $this->getInterestAmount();
        return number_format($interestAmount, 0);
    }

    public function getBreakInterestAmount()
    {
        return $this->break_interest_amount?:0;
    }

    public function getBreakInterestAmountFormatted()
    {
        return number_format($this->getBreakInterestAmount(), 0);
    }
    public function getBreakChargeAmount()
    {
        return $this->break_charge_amount?:0;
    }

    public function getBreakChargeAmountFormatted()
    {
        return number_format($this->getBreakChargeAmount(), 0);
    }

    public function getActualInterestAmount()
    {
        return $this->actual_interest_amount ?:0;
    }

    public function getActualInterestAmountFormatted()
    {
        return number_format($this->getActualInterestAmount(), 0);
    }

    public function getCurrency()
    {
        return $this->currency ;
    }
    public function financialInstitution()
    {
        return $this->belongsTo(FinancialInstitution::class, 'financial_institution_id', 'id');
    }


    public function currentAccountDebitBankStatement()
    {
        return $this->hasOne(CurrentAccountBankStatement::class, 'certificate_of_deposit_id', 'id')->where('is_debit', 1);
    }
    public function currentAccountDebitBankStatements()
    {
        return $this->hasMany(CurrentAccountBankStatement::class, 'certificate_of_deposit_id', 'id')->where('is_debit', 1)->orderBy('full_date', 'desc');
    }


    public function currentAccountCreditBankStatement()
    {
        return $this->hasOne(CurrentAccountBankStatement::class, 'certificate_of_deposit_id', 'id')->where('is_credit', 1);
    }
    public function currentAccountCreditBankStatements()
    {
        return $this->hasMany(CurrentAccountBankStatement::class, 'certificate_of_deposit_id', 'id')->where('is_credit', 1)->orderBy('full_date', 'desc');
    }
    /**
     * * علشان نجيب الاتنين مع بعض مرة واحدة
     */
    public function currentAccountBankStatements()
    {
        return $this->hasMany(CurrentAccountBankStatement::class, 'certificate_of_deposit_id', 'id')->orderBy('full_date', 'desc');
    }
    public function isDueTodayOrGreater()
    {
        $endDate = $this->getEndDate() ;
        return  $endDate && Carbon::make($endDate)->greaterThanOrEqualTo(now());
    }

    public static function getAllAccountNumberForCurrency($companyId, $currencyName, $financialInstitutionId, $keyName='account_number'):array
    {

        return self::where('company_id', $companyId)->where('currency', $currencyName)
        ->where('financial_institution_id', $financialInstitutionId)
        ->where('status', CertificatesOfDeposit::RUNNING)
        ->pluck('account_number', $keyName)->toArray();
    }
    public static function findByAccountNumber(string $accountNumber, int $companyId)
    {
        return self::where('company_id', $companyId)->where('account_number', $accountNumber)->first();
    }
    public function fullySecuredCleanOverdraft()
    {
        $cdAccount = AccountType::onlyCdAccounts()->first();
        return $this->hasOne(FullySecuredOverdraft::class, 'cd_or_td_account_id', 'id')
        ->where('cd_or_td_account_type_id', $cdAccount->id);
    }
    public function getType()
    {
        return __('Certificate Of Deposit');
    }
    public function getCurrencyFormatted()
    {
        return Str::upper($this->getCurrency());
    }
    public function getLastAmountFormatted()
    {
        return number_format($this->amount) ;
    }
    public function letterOfGuaranteeIssuance()
    {
        $cdAccount = AccountType::onlyCdAccounts()->first();
        return $this->hasOne(LetterOfGuaranteeIssuance::class, 'cash_cover_deducted_from_account_id', 'id')
        ->where('cash_cover_deducted_from_account_type', $cdAccount->id);
    }

    public function getOdooCode()
    {
        return $this->odoo_code;
    }
    
    public function getOdooId():int
    {
        if (is_null($this->odoo_id)) {
            throw new \Exception('Odoo Code For Time Of Deposit ' . $this->getAccountNumber() . ' Not Found');
        }
        return $this->odoo_id;
    }
    // public function getJournalId():?int
    // {
    // 	return $this->journal_id ;
    // }
    public function deleteOdooRelations($isBreakOrApplyDeposit)
    {
        $this->deleteOdoo($isBreakOrApplyDeposit);
    }
	public function getCreateReference():string
	{
		return __('Create Certificate Of Deposit');
	}
	public function getBreakReference():string
	{
		return __('Collect Certificate Of Deposit');
	}
	public function getBreakColumns():array
	{
		return ['store_break_journal_entry_id'];
	}
	
	public function getAccountTypeId():int
	{
		return 29;
	}
	
	public function getDepositDateOrBreakDate():string
    {
        if ($this->isBroken()) {
            return $this->getBreakDate();
        }
        return $this->getDepositDate();
    }
	
}
