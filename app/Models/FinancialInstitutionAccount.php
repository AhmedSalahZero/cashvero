<?php

namespace App\Models;

use App\Enums\LgTypes;
use App\Helpers\HArr;
use App\Models\AccountInterest;
use App\OdooSetting;
use App\Traits\HasBankStatement;
use App\Traits\HasCompany;
use App\Traits\HasLastStatementAmount;
use App\Traits\HasOdooPaymentMethod;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $journal_id
 * @property int|null $odoo_id
 * @property string|null $odoo_outbound_cheque_payment_method_id
 * @property string|null $odoo_inbound_cheque_payment_method_id
 * @property string|null $odoo_outbound_transfer_payment_method_id
 * @property string|null $odoo_inbound_transfer_payment_method_id
 * @property string|null $odoo_code
 * @property int $is_active
 * @property int|null $financial_institution_id
 * @property string|null $balance_date
 * @property string|null $account_number
 * @property string|null $currency
 * @property float|null $balance_amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $iban
 * @property numeric|null $exchange_rate
 * @property int|null $company_id
 * @property array<array-key, mixed>|null $synced_end_of_month_years لو عمل حركة مثلا في الفين خمسة وعشرين بنروح ننزل في السنه كاملة صفوف علشان ال
 * 			end of month interest 
 * 			ففي الكولوم دا هنسجل ان الفين خمسه وعشرين موجودة علشان ما نروحش ننزلهم تاني
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AccountInterest> $accountInterests
 * @property-read int|null $account_interests_count
 * @property-read bool|null $account_interests_exists
 * @property-read \App\Models\Company|null $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CurrentAccountBankStatement> $currentAccountBankStatements
 * @property-read int|null $current_account_bank_statements_count
 * @property-read bool|null $current_account_bank_statements_exists
 * @property-read \App\Models\FinancialInstitution|null $financialInstitution
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitutionAccount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitutionAccount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitutionAccount query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitutionAccount whereAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitutionAccount whereBalanceAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitutionAccount whereBalanceDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitutionAccount whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitutionAccount whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitutionAccount whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitutionAccount whereExchangeRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitutionAccount whereFinancialInstitutionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitutionAccount whereIban($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitutionAccount whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitutionAccount whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitutionAccount whereJournalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitutionAccount whereOdooCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitutionAccount whereOdooId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitutionAccount whereOdooInboundChequePaymentMethodId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitutionAccount whereOdooInboundTransferPaymentMethodId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitutionAccount whereOdooOutboundChequePaymentMethodId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitutionAccount whereOdooOutboundTransferPaymentMethodId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitutionAccount whereSyncedEndOfMonthYears($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FinancialInstitutionAccount whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class FinancialInstitutionAccount extends Model
{
	const NUMBER_OF_YEARS_FOR_INTEREST_IN_CURRENT_STATEMENT = 1 ;
	use HasLastStatementAmount ,HasCompany,HasOdooPaymentMethod,HasBankStatement;
		protected $casts = [
			'synced_end_of_month_years'=>'array'
		];
		public static function boot()
	{
		parent::boot();
		static::deleting(function(self $model){
			$model->accountInterests()->delete(); // accountInterests == rates
			CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($model->currentAccountBankStatements);
		});
	}
	
    protected $guarded = ['id'];
	
    public function financialInstitution()
    {
        return $this->belongsTo(FinancialInstitution::class, 'financial_institution_id', 'id');
    }
	public function getFinancialInstitutionId():int
	{
		return $this->financialInstitution->id; 
	}
	public function getFinancialInstitutionName():string
	{
		return $this->financialInstitution->getName(); 
	}
	/**
	 * * رقم الحساب ( رقم الفيزا مثلا)
	 */
    public function getAccountNumber()
    {
        return $this->account_number ;
    }
	
	

    /**
     * *رقم الحساب البنكى الدولى International Bank Account Number وبذلك فهو يعبر عن رقم حسابك البنكى اثناء التحويلات البنكية الدولية وهذا الرقم يتم الحصول علية لكل الحسابات البنكية فى أغلب الدول حول العالم.
     **	ولذلك لا يعتبر رقم الايبان رقم جديد لحسابك ولكن هو شكل وصيغة مختلفة لرقم الحساب ليتم التعرف علية دوليا بسهولة وبالتالى يساعد فى سرعة وسهولة ** التحويلات البنكية الدولية وتجنب العديد من الاخطاء التى قد تحدث وتتسبب فى تأخير وصول الدفعات والحوالات البنكية.
     * *
     * *
     */
    public function getIban()
    {
        return $this->iban ;
    }
	
	// public function getMinBalance()
	// {
	// 	return $this->min_balance?:0 ;
	// }

	// public function getInterestRate()
    // {
    //     return $this->interest_rate ?: 0 ;
    // }

    // public function getMainCurrency()
    // {
    //     return $this->main_currency ;
    // }
	public function getBalanceDate()
	{
		return $this->balance_date;
	}
	public function getBalanceDateFormatted()
	{
		$balanceDate = $this->getBalanceDate();
		return $balanceDate ? Carbon::make($balanceDate)->format('d-m-Y') : null;
	}
	public function getBalanceDateForSelect()
	{
		$balanceDate = $this->getBalanceDate();
		return $balanceDate ? Carbon::make($balanceDate)->format('m/d/Y'):$balanceDate;
	}
	/**
	 * * اجمالي الفلوس اللي معايا في الحساب دا
	 */
    public function getBalanceAmount()
    {
        return $this->balance_amount ?: 0 ;
    }
	public function getBalanceAmountFormatted()
	{
		return number_format($this->getBalanceAmount() , 0) ; 
	}
		// /**
		//  * * نسب الفايدة اللي بخدها من الحساب دا ( احيانا بيكون فيه عروض بحيث انك تنشئ حساب وتاخد علي نسبة فايدة كل شهر مثلا)
		//  */
	public function accountInterests():HasMany
	{
		return $this->hasMany(AccountInterest::class , 'financial_institution_account_id','id');
	}

	public function getExchangeRate()
    {
        return $this->exchange_rate ?: 1 ;
    }
	
	
	/**
	 * * هو اول حساب بيدخلة اليوزر وبيكون دايما مصري لان ما ينفعش تنشئ حساب دولاري مثلا من غير الحساب المصري
	 */
    // public function isMainAccount():bool
	// {
	// 	return (bool)$this->is_main_account;
	// }
	
	// public function isMainAccountFormatted():string 
	// {
	// 	return $this->isMainAccount() ? __('Yes') : __('No');
	// }
	
	public function getCertificatesOfDeposits():HasMany
	{
		return $this->hasMany(CertificatesOfDeposit::class,'maturity_amount_added_to_account_id','id');
	}
	

    public function getId()
    {
        return $this->id ;
    }

    public function getCurrency()
    {
        return $this->currency;
    }
	public function getCurrencyFormatted()
	{
		return Str::upper($this->getCurrency());
	}
	public function getType()
	{
		return __('Current');
	}
	public function isActive():bool
	{
		return (bool)$this->is_active;
	}
	public static function getAllCurrentAccountCurrenciesForCompany(int $companyId,array $exceptCurrenciesNames = []){
		$currencies = getCurrenciesForSuppliersAndCustomers($companyId);
		return HArr::removeKeyFromArrayByValue($currencies,$exceptCurrenciesNames);
	}
	public static function getAllAccountNumberForCurrency($companyId , $currencyName,$financialInstitutionId , string $keyName = 'account_number' , $onlyActiveAccounts = true ):array
	{
		$allAccounts = Request()->has('allAccounts') &&  Request()->get('allAccounts') === 'true' ;
		return self::where('company_id',$companyId)
		->when(!$allAccounts,function(Builder $builder) use ($onlyActiveAccounts){
			$builder->where('financial_institution_accounts.is_active',$onlyActiveAccounts);
		})
		->where('financial_institution_id',$financialInstitutionId)
		->where('currency',$currencyName)->pluck('account_number',$keyName)->toArray();		
	}
	
	public static function findByAccountNumber($accountNumber,int $companyId,int $financialInstitutionId)
	{
		return self::where('company_id',$companyId)->where('account_number',$accountNumber)->where('financial_institution_id',$financialInstitutionId)->first();
	}
	public function currentAccountBankStatements()
	{
		return $this->hasMany(CurrentAccountBankStatement::class,'financial_institution_account_id','id');
	}
	
	public  function getStatementTableName():string
	{
	   return 'current_account_bank_statements';	
   }	
   public  function getForeignKeyInStatementTable()
   {
		return 'financial_institution_account_id';
   }
 
	public static function getLastAmountFormatted(int $companyId , string $currencyName , int $financialInstitutionId , $accountNumber ) 
	{
	
		$row = 	DB::table(self::getBankStatementTableName())
                ->join('financial_institution_accounts', 'financial_institution_account_id', '=', 'financial_institution_accounts.id')
                ->where('financial_institution_accounts.company_id', $companyId)
                ->where('currency', $currencyName)
				->where('account_number',$accountNumber)
                ->where('financial_institution_accounts.financial_institution_id', '=', $financialInstitutionId)
                ->orderBy(self::getBankStatementTableName().'.full_date', 'desc')
                ->limit(1)
                ->first();
		return $row ? number_format($row->end_balance,2) : 0;
	}	
	public static function getBankStatementTableName()
	{
		return 'current_account_bank_statements';
	}
	public function getOpeningBalanceFromCurrentAccountBankStatement()
	{
		return $this->currentAccountBankStatements->where('is_beginning_balance',1)->first();
	}
	public function getOpeningBalanceDate():string
	{
		return $this->balance_date;
	}
	public function getAmount(string $currencyName , string $accountNumber,int $financialInstitutionId , int $companyId)
	{
		$row = 	DB::table(self::getBankStatementTableName())
                ->join('financial_institution_accounts', 'financial_institution_account_id', '=', 'financial_institution_accounts.id')
                ->where('financial_institution_accounts.company_id', $companyId)
                ->where('currency', $currencyName)
				->where('account_number',$accountNumber)
                ->where('financial_institution_accounts.financial_institution_id', '=', $financialInstitutionId)
                ->orderBy(self::getBankStatementTableName().'.full_date', 'desc')
                ->limit(1)
                ->first();
		return $row ? number_format($row->end_balance) : 0;
	}
	public function getOdooCode():?string 
	{
		return $this->odoo_code ;
	}
	public function getOdooId():?int 
	{
		return $this->odoo_id ;
	}
	public function getJournalId():?int 
	{
		return $this->journal_id ;
	}
	public static function getLetterOfGuaranteeOdooIdFromType(string $lgType,int $companyId):int
	{
		if($lgType == LgTypes::BID_BOND){
			$row = OdooSetting::where('company_id',$companyId)->whereNotNull('bid_lg_cash_cover_id')->first();
			if($row){
				return $row->bid_lg_cash_cover_id ;
			}
		}
		if($lgType == LgTypes::FINAL_LGS){
			$row = OdooSetting::where('company_id',$companyId)->whereNotNull('final_lg_cash_cover_id')->first();
			if($row){
				return $row->final_lg_cash_cover_id ;
			}
		}
		if($lgType == LgTypes::ADVANCED_PAYMENT_LGS){
			$row = OdooSetting::where('company_id',$companyId)->whereNotNull('advanced_lg_cash_cover_id')->first();
			if($row){
				return $row->advanced_lg_cash_cover_id ;
			}
		}
		if($lgType == LgTypes::PERFORMANCE_LG){
			$row = OdooSetting::where('company_id',$companyId)->whereNotNull('performance_lg_cash_cover_id')->first();
			if($row){
				return $row->performance_lg_cash_cover_id ;
			}
		}
		return 0 ;
	}
	public static function getBankStatementTableClassName():string 
	{
		return CurrentAccountBankStatement::class ;
	}
	public static function generateForeignKeyFormModelName():string 
	{
		return 'financial_institution_account_id';
	}	
	
	public function isOverdraft():bool 
	{
		return false;
	}
	
}
