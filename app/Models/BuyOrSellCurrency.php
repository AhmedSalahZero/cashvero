<?php

namespace App\Models;
use App\Models\FullySecuredOverdraft;
use App\Traits\HasBasicStoreRequest;
use App\Traits\HasCompany;
use App\Traits\Models\HasDeleteOdoo;
use App\Traits\Models\HasOdooMoneyTransfer;
use App\Traits\Models\HasUserComment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * * هنا لو معايا عملة ورايح اغيرها وليكن مثلا من البنك وحطها في حسابي الجاري
 *
 * @property int $id
 * @property string|null $outbound_odoo_reference
 * @property string|null $inbound_odoo_reference
 * @property string|null $synced_with_odoo
 * @property string|null $odoo_error_message
 * @property int|null $inbound_journal_entry_id
 * @property int|null $outbound_journal_entry_id
 * @property int|null $outbound_account_bank_statement_odoo_id
 * @property int|null $inbound_account_bank_statement_odoo_id
 * @property string|null $type
 * @property string|null $transaction_date هو التاريخ اللي اللي هيتم فيه العميله
 * @property string|null $currency_to_sell
 * @property string|null $currency_to_buy
 * @property numeric|null $currency_to_sell_amount
 * @property numeric|null $exchange_rate
 * @property numeric|null $currency_to_buy_amount
 * @property int|null $from_bank_id
 * @property int|null $from_account_type_id
 * @property string|null $from_account_number
 * @property int $to_bank_id بنوكي
 * @property int|null $to_account_type_id
 * @property string|null $to_account_number
 * @property int|null $to_branch_id
 * @property int|null $from_branch_id
 * @property int $company_id
 * @property string|null $buy_comment_ar
 * @property string|null $buy_comment_en
 * @property string|null $sell_comment_ar
 * @property string|null $sell_comment_en
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $user_comment
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CashInSafeStatement> $cashInSafeStatements
 * @property-read int|null $cash_in_safe_statements_count
 * @property-read bool|null $cash_in_safe_statements_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CleanOverdraftBankStatement> $cleanOverdraftBankStatements
 * @property-read int|null $clean_overdraft_bank_statements_count
 * @property-read bool|null $clean_overdraft_bank_statements_exists
 * @property-read \App\Models\Company $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CurrentAccountBankStatement> $currentAccountBankStatements
 * @property-read int|null $current_account_bank_statements_count
 * @property-read bool|null $current_account_bank_statements_exists
 * @property-read \App\Models\AccountType|null $fromAccountType
 * @property-read \App\Models\FinancialInstitution|null $fromBank
 * @property-read \App\Models\Branch|null $fromBranch
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\FullySecuredOverdraftBankStatement> $fullySecuredOverdraftBankStatements
 * @property-read int|null $fully_secured_overdraft_bank_statements_count
 * @property-read bool|null $fully_secured_overdraft_bank_statements_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OverdraftAgainstAssignmentOfContractBankStatement> $overdraftAgainstAssignmentOfContractBankStatements
 * @property-read int|null $overdraft_against_assignment_of_contract_bank_statements_count
 * @property-read bool|null $overdraft_against_assignment_of_contract_bank_statements_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OverdraftAgainstCommercialPaperBankStatement> $overdraftAgainstCommercialPaperBankStatements
 * @property-read int|null $overdraft_against_commercial_paper_bank_statements_count
 * @property-read bool|null $overdraft_against_commercial_paper_bank_statements_exists
 * @property-read \App\Models\AccountType|null $toAccountType
 * @property-read \App\Models\FinancialInstitution|null $toBank
 * @property-read \App\Models\Branch|null $toBranch
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereBuyCommentAr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereBuyCommentEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereCurrencyToBuy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereCurrencyToBuyAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereCurrencyToSell($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereCurrencyToSellAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereExchangeRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereFromAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereFromAccountTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereFromBankId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereFromBranchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereInboundAccountBankStatementOdooId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereInboundJournalEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereInboundOdooReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereOdooErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereOutboundAccountBankStatementOdooId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereOutboundJournalEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereOutboundOdooReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereSellCommentAr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereSellCommentEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereSyncedWithOdoo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereToAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereToAccountTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereToBankId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereToBranchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereTransactionDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency whereUserComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\BuyOrSellCurrency filterByTransactionDate($startDate, $endDate)
 * @mixin \Eloquent
 */
class BuyOrSellCurrency extends Model
{
	use HasBasicStoreRequest , HasUserComment,HasOdooMoneyTransfer,HasDeleteOdoo,HasCompany;
	const BANK_TO_BANK = 'bank-to-bank';
	const BANK_TO_SAFE = 'bank-to-safe';
	const SAFE_TO_BANK = 'safe-to-bank';
	const SAFE_TO_SAFE = 'safe-to-safe';
	public static function scopeFilterByTransactionDate($query, $startDate, $endDate)
	{
		return $query->whereBetween('transaction_date', [$startDate, $endDate]);
	}
	public static function generateBuyAccountComment(self $buyOrSellCurrency,string $lang)
	{
		if($buyOrSellCurrency->isBankToBank() ){
			return __('Sold :amount :currency From :from Account No :no',['from'=>$buyOrSellCurrency->getFromBankName(),'no'=>$buyOrSellCurrency->getFromAccountNumber(),'amount'=>$buyOrSellCurrency->getAmountToSellFormatted(),'currency'=>$buyOrSellCurrency->getCurrencyToSellFormatted()],$lang) ;
		}
		if($buyOrSellCurrency->isBankToSafe()){
			return __('Sold :amount :currency From :from Account No :no To Safe',['from'=>$buyOrSellCurrency->getFromBankName(),'no'=>$buyOrSellCurrency->getFromAccountNumber(),'amount'=>$buyOrSellCurrency->getAmountToSellFormatted(),'currency'=>$buyOrSellCurrency->getCurrencyToSellFormatted()],$lang) ;
		}
		if($buyOrSellCurrency->isSafeToBank()){
			return __('Sold :amount :currency From :branchName Safe',['branchName'=>$buyOrSellCurrency->getFromBranchName(),'amount'=>$buyOrSellCurrency->getAmountToSellFormatted(),'currency'=>$buyOrSellCurrency->getCurrencyToSellFormatted()],$lang) ;
		}
		if($buyOrSellCurrency->isSafeToSafe()){
			return __('Sold :amount :currency From :branchName Safe',['branchName'=>$buyOrSellCurrency->getFromBranchName(),'amount'=>$buyOrSellCurrency->getAmountToSellFormatted(),'currency'=>$buyOrSellCurrency->getCurrencyToSellFormatted()],$lang) ;
		}
	}	
	public static function generateSellAccountComment(self $buyOrSellCurrency,string $lang)
	{
		if($buyOrSellCurrency->isBankToBank()  ){
			return __('Sold For :amount :currency To :to Account No :no',['to'=>$buyOrSellCurrency->getToBankName(),'no'=>$buyOrSellCurrency->getToAccountNumber(),'amount'=>$buyOrSellCurrency->getAmountToBuyFormatted(),'currency'=>$buyOrSellCurrency->getCurrencyToBuyFormatted() ],$lang) ;
		}
		if($buyOrSellCurrency->isBankToSafe()){
			return __('Sold For :amount :currency To :branchName Safe',['branchName'=>$buyOrSellCurrency->getToBranchName(),'amount'=>$buyOrSellCurrency->getAmountToBuyFormatted(),'currency'=>$buyOrSellCurrency->getCurrencyToBuyFormatted()],$lang) ;
		}
		if($buyOrSellCurrency->isSafeToBank()){
			return __('Sold For :amount :currency To :to Account No :no',['to'=>$buyOrSellCurrency->getToBankName(),'no'=>$buyOrSellCurrency->getToAccountNumber(),'amount'=>$buyOrSellCurrency->getAmountToBuyFormatted(),'currency'=>$buyOrSellCurrency->getCurrencyToBuyFormatted()],$lang) ;
		}
		if($buyOrSellCurrency->isSafeToSafe()){
			return __('Sold For :amount :currency To :branchName Safe',['branchName'=>$buyOrSellCurrency->getToBranchName(),'amount'=>$buyOrSellCurrency->getAmountToBuyFormatted(),'currency'=>$buyOrSellCurrency->getCurrencyToBuyFormatted()],$lang) ;
		}
	}
	protected static function booted()
	{
		self::creating(function (self $buyOrSellCurrency): void {
			$buyOrSellCurrency->buy_comment_en = self::generateBuyAccountComment($buyOrSellCurrency,'en');
			$buyOrSellCurrency->buy_comment_ar = self::generateBuyAccountComment($buyOrSellCurrency,'ar');			
			$buyOrSellCurrency->sell_comment_en = self::generateSellAccountComment($buyOrSellCurrency,'en');
			$buyOrSellCurrency->sell_comment_ar = self::generateSellAccountComment($buyOrSellCurrency,'ar');
		});
	}
	public function isBankToBank()
	{
		return $this->getType() == self::BANK_TO_BANK;
	}
	public function isBankToSafe()
	{
		return $this->getType() == self::BANK_TO_SAFE;
	}
	public function isSafeToBank()
	{
		return $this->getType() == self::SAFE_TO_BANK;
	}
	public function isSafeToSafe()
	{
		return $this->getType() == self::SAFE_TO_SAFE;
	}
	
	public static function getAllTypes()
	{
		return [
			self::BANK_TO_BANK => __('Bank To Bank'),
			self::BANK_TO_SAFE => __('Bank To Safe'),
			self::SAFE_TO_BANK => __('Safe To Bank'),
			self::SAFE_TO_SAFE => __('Safe To Safe')
		];
	}
    protected $guarded = ['id'];
	public function getType()
	{
		return $this->type;
	}
   
    public function setTransactionDateAttribute($value)
    {
        if (!$value) {
            return null ;
        }
        $date = explode('/', $value);
        if (count($date) != 3) {
            $this->attributes['transaction_date'] = $value;

            return  ;
        }
        $month = $date[0];
        $day = $date[1];
        $year = $date[2];
        $this->attributes['transaction_date'] = $year . '-' . $month . '-' . $day;
    }
	
	public function getTransferDate()
    {
        return $this->getTransactionDate() ;
    }

    public function getTransferDateFormatted()
    {
        $transferDate = $this->getTransferDate() ;

        return $transferDate ? Carbon::make($transferDate)->format('d-m-Y') : null ;
    }
	
    public function getTransactionDate()
    {
        return $this->transaction_date ;
    }

    public function getTransactionDateFormatted()
    {
        $transactionDate = $this->getTransactionDate() ;

        return $transactionDate ? Carbon::make($transactionDate)->format('d-m-Y') : null ;
    }

    public function fromBank()
    {
        return $this->belongsTo(FinancialInstitution::class, 'from_bank_id', 'id');
    }

    public function getFromBankName()
    {
        return $this->fromBank ? $this->fromBank->getName() : __('N/A');
    }

    public function getFromBankId()
    {
        return $this->fromBank ? $this->fromBank->id : 0;
    }

    public function fromAccountType()
    {
        return $this->belongsTo(AccountType::class, 'from_account_type_id');
    }

    public function getFromAccountTypeName()
    {
        return $this->fromAccountType ? $this->fromAccountType->getName() : __('N/A');
    } 
	 public function getFromAccountTypeId()
    {
        return $this->fromAccountType ? $this->fromAccountType->getId() : 0;
    }

    public function getFromAccountNumber()
    {
        return $this->from_account_number ;
    }

    public function getCurrencyToBuy()
    {
        return $this->currency_to_buy ;
    }
	public function getCurrencyToBuyFormatted()
    {
        return $this->getCurrencyToBuy() ;
    }
	public function getCurrencyToSell()
    {
        return $this->currency_to_sell ;
    }
	public function getCurrencyToSellFormatted()
    {
        return $this->getCurrencyToSell() ;
    }
	public function getExchangeRate()
	{
		return $this->exchange_rate;
	}
	public function getExchangeRateWithItsReverse():string 
	{
		return number_format($this->getExchangeRate(),4) . ' <br> <hr> ' . number_format(1/$this->getExchangeRate(),4);
	}
    public function getAmountToSell()
    {
        return number_unformat($this->currency_to_sell_amount ?: 0);
    }
	public function getPaidAmount()
	{
		return $this->getAmountToSell();
	}
	public function getAmount()
	{
		return $this->getAmountToSell();; 
	}
	public function getAccountNumber()
	{
		return $this->getFromAccountNumber();
	} 
	public function getAccountTypeId()
	{
		return $this->getFromAccountTypeId();
	}
    public function getAmountToSellFormatted()
    {
	
        return number_format($this->getAmountToSell(), 0);
    }
	
	public function getAmountToBuy()
    {
        return number_unformat($this->currency_to_buy_amount ?: 0);
    }
	public function getAmountInCurrency()
	{
		return $this->getAmountToBuy();
	}
	public function getAmountInMainCurrency()
	{
		return $this->getAmountToSell();
	}
    public function getAmountToBuyFormatted()
    {
        return number_format($this->getAmountToBuy(), 0);
    }

    public function toBank()
    {
        return $this->belongsTo(FinancialInstitution::class, 'to_bank_id', 'id');
    }
	public function getToBankId()
    {
        return $this->toBank ? $this->toBank->id : 0;
    }
    public function getToBankName()
    {
        return $this->toBank ? $this->toBank->getName() : __('N/A');
    }

    public function toAccountType()
    {
        return $this->belongsTo(AccountType::class, 'to_account_type_id');
    }
	public function getToAccountTypeId()
    {
        return $this->toAccountType ? $this->toAccountType->getId() : 0;
    }
    public function getToAccountTypeName()
    {
        return $this->toAccountType ? $this->toAccountType->getName() : __('N/A');
    }

    public function getToAccountNumber()
    {
        return $this->to_account_number ;
    }
	public function currentAccountBankStatements()
    {
        return $this->hasMany(CurrentAccountBankStatement::class, 'buy_or_sell_currency_id', 'id');
    }
    public function cleanOverdraftBankStatements()
    {
        return $this->hasMany(CleanOverdraftBankStatement::class, 'buy_or_sell_currency_id', 'id');
    }
	public function fullySecuredOverdraftBankStatements()
    {
        return $this->hasMany(FullySecuredOverdraftBankStatement::class, 'buy_or_sell_currency_id', 'id');
    }
	public function overdraftAgainstCommercialPaperBankStatements()
    {
        return $this->hasMany(OverdraftAgainstCommercialPaperBankStatement::class, 'buy_or_sell_currency_id', 'id');
    }
	public function overdraftAgainstAssignmentOfContractBankStatements()
    {
        return $this->hasMany(OverdraftAgainstAssignmentOfContractBankStatement::class, 'buy_or_sell_currency_id', 'id');
    }
	public function cashInSafeStatements():HasMany
	{
		return $this->hasMany(CashInSafeStatement::class,'buy_or_sell_currency_id','id');
	}
    public function deleteRelations()
    {
		$this->deleteOdoo(false);
        $this->cleanOverdraftBankStatements->each(function (CleanOverdraftBankStatement $cleanOverdraftBankStatement) {
			$cleanOverdraftBankStatement->delete();
		});
		$this->fullySecuredOverdraftBankStatements->each(function (FullySecuredOverdraftBankStatement $fullySecuredOverdraftBankStatement) {
			$fullySecuredOverdraftBankStatement->delete();
		});
		$this->overdraftAgainstCommercialPaperBankStatements->each(function (OverdraftAgainstCommercialPaperBankStatement $overdraftAgainstCommercialPaperBankStatement) {
			$overdraftAgainstCommercialPaperBankStatement->delete();
		});
		$this->overdraftAgainstAssignmentOfContractBankStatements->each(function (OverdraftAgainstAssignmentOfContractBankStatement $odAgainstAssignmentOfContractBankStatement) {
			$odAgainstAssignmentOfContractBankStatement->delete();
		});
		$this->currentAccountBankStatements->each(function (CurrentAccountBankStatement $currentAccountBankStatement) {
			$currentAccountBankStatement->delete();
		});
		$this->cashInSafeStatements->each(function (CashInSafeStatement $cashInSafeStatement) {
			$cashInSafeStatement->delete();
		});
		
    }
	/**
	 * * هنا لما بنحول من بنك او الى بنك بغض النظر عن نوع الحساب
	 */
	public function handleBankTransfer(int $companyId , int $fromFinancialInstitutionId , AccountType $fromAccountType , string $fromAccountNumber ,string $transactionDate  , $debitAmount , $creditAmount)
	{
		if( $fromAccountType->isCurrentAccount()){
			
			$fromCurrentAccount = FinancialInstitutionAccount::findByAccountNumber($fromAccountNumber,$companyId,$fromFinancialInstitutionId);
			CurrentAccountBankStatement::create([
				'financial_institution_account_id'=>$fromCurrentAccount->id ,
				'buy_or_sell_currency_id'=>$this->id  ,
				'company_id'=>$companyId ,
				'date' => $transactionDate , 
				'credit'=>$creditAmount,
				'debit'=>$debitAmount
			]);
		}
		
		
		if( $fromAccountType->isCleanOverdraftAccount()){
			/**
			 * @var CleanOverdraft $fromCleanOverdraft
			 */

			$fromCleanOverdraft = CleanOverdraft::findByAccountNumber($fromAccountNumber,$companyId,$fromFinancialInstitutionId);
			CleanOverdraftBankStatement::create([
				'type'=>CleanOverdraftBankStatement::MONEY_TRANSFER ,
				'clean_overdraft_id'=>$fromCleanOverdraft->id ,
				'buy_or_sell_currency_id'=>$this->id ,
				'company_id'=>$companyId ,
				'date' => $transactionDate , 
				'limit' =>$fromCleanOverdraft->getLimit(),
				'credit'=>$creditAmount,
				'debit'=>$debitAmount
			]);
		}
		if( $fromAccountType->isFullySecuredOverdraftAccount()){
			/**
			 * @var FullySecuredOverdraft $fromFullySecuredOverdraft
			 */

			 $fromFullySecuredOverdraft = FullySecuredOverdraft::findByAccountNumber($fromAccountNumber,$companyId,$fromFinancialInstitutionId);
			FullySecuredOverdraftBankStatement::create([
				'type'=>FullySecuredOverdraftBankStatement::MONEY_TRANSFER ,
				'fully_secured_overdraft_id'=>$fromFullySecuredOverdraft->id ,
				'buy_or_sell_currency_id'=>$this->id ,
				'company_id'=>$companyId ,
				'date' => $transactionDate , 
				'limit' =>$fromFullySecuredOverdraft->getLimit(),
				'credit'=>$creditAmount,
				'debit'=>$debitAmount
			]);
		}
		
		if( $fromAccountType->isOverdraftAgainstCommercialPaperAccount()){
			/**
			 * @var OverdraftAgainstCommercialPaper $fromOverdraftAgainstCommercialPaper
			 */

			 $fromOverdraftAgainstCommercialPaper = OverdraftAgainstCommercialPaper::findByAccountNumber($fromAccountNumber,$companyId,$fromFinancialInstitutionId);
			OverdraftAgainstCommercialPaperBankStatement::create([
				'type'=>OverdraftAgainstCommercialPaperBankStatement::MONEY_TRANSFER ,
				'overdraft_against_commercial_paper_id'=>$fromOverdraftAgainstCommercialPaper->id ,
				'buy_or_sell_currency_id'=>$this->id ,
				'company_id'=>$companyId ,
				'date' => $transactionDate , 
				'limit' =>$fromOverdraftAgainstCommercialPaper->getLimit(),
				'credit'=>$creditAmount,
				'debit'=>$debitAmount
			]);
		}
		
		if( $fromAccountType->isOverdraftAgainstAssignmentOfContractAccount()){
			/**
			 * @var OverdraftAgainstAssignmentOfContract $fromOverdraftAgainstAssignmentOfContract
			 */

			 $fromOverdraftAgainstAssignmentOfContract = OverdraftAgainstAssignmentOfContract::findByAccountNumber($fromAccountNumber,$companyId,$fromFinancialInstitutionId);
			OverdraftAgainstAssignmentOfContractBankStatement::create([
				'type'=>OverdraftAgainstAssignmentOfContractBankStatement::MONEY_TRANSFER ,
				'overdraft_against_assignment_of_contract_id'=>$fromOverdraftAgainstAssignmentOfContract->id ,
				'buy_or_sell_currency_id'=>$this->id ,
				'company_id'=>$companyId ,
				'date' => $transactionDate , 
				'limit' =>$fromOverdraftAgainstAssignmentOfContract->getLimit(),
				'credit'=>$creditAmount,
				'debit'=>$debitAmount
			]);
		}
		
		
	}
	
		
		
	// }
	/**
	 * * دي هتستخدم في الحالتين سواء من او الى
	 */
	public function handleSafeTransfer(int $companyId, string $date ,  $debitAmount , $creditAmount , int $branchId , string $currencyName , string $exchangeRate )
	{
	
				$this->cashInSafeStatements()->create([
					'type'=>CashInSafeStatement::MONEY_TRANSFER,
					'branch_id'=>$branchId ,
					'currency'=>$currencyName ,
					'exchange_rate'=>$exchangeRate,
					'company_id'=>$companyId ,
					'date'=>$date ,
					'debit'=>$debitAmount ,
					'credit'=> $creditAmount 
				]);
	}
	public function handleBankToBankTransfer( int $companyId , AccountType $fromAccountType , string $fromAccountNumber , int $fromFinancialInstitutionId , AccountType $toAccountType , string $toAccountNumber , int $toFinancialInstitutionId , string $transactionDate , string $receivingDate, $transferFromAmount,$transferToAmount = null )
	{
		$this->handleBankTransfer($companyId , $fromFinancialInstitutionId ,  $fromAccountType , $fromAccountNumber , $transactionDate , 0,$transferFromAmount);
		$this->handleBankTransfer($companyId , $toFinancialInstitutionId , $toAccountType , $toAccountNumber ,$receivingDate , $transferToAmount,0);
	}
	public function handleBankToSafeTransfer( int $companyId , AccountType $fromAccountType , string $fromAccountNumber , int $fromFinancialInstitutionId , int $toBranchId ,string $currencyToBuyName , string $transactionDate , $transferFromAmount,$transferToAmount)
	{
		$this->handleBankTransfer($companyId , $fromFinancialInstitutionId ,  $fromAccountType , $fromAccountNumber , $transactionDate ,0, $transferFromAmount);
		$this->handleSafeTransfer($companyId,$transactionDate,$transferToAmount,0,$toBranchId ,$currencyToBuyName,1);
	}
	public function handleSafeToBankTransfer( int $companyId , AccountType $toAccountType , string $toAccountNumber , int $toFinancialInstitutionId , int $fromBranchId , string $currencyToSellName , string $transactionDate , $transferFromAmount,$transferToAmount)
	{
		$this->handleSafeTransfer($companyId,$transactionDate,0,$transferFromAmount,$fromBranchId ,$currencyToSellName,1);
		$this->handleBankTransfer($companyId , $toFinancialInstitutionId ,  $toAccountType , $toAccountNumber , $transactionDate , $transferToAmount,0);
	}
	public function handleSafeToSafeTransfer( int $companyId , int $fromBranchId , string $currencyToBuyName , int $toBranchId , string $currencyToSellName , $exchangeRate , string $transactionDate , $transferFromAmount,$transferToAmount)
	{
		$this->handleSafeTransfer($companyId,$transactionDate,0,$transferFromAmount,$fromBranchId ,$currencyToSellName,1);
		$this->handleSafeTransfer($companyId,$transactionDate,$transferToAmount,0,$toBranchId ,$currencyToBuyName,$exchangeRate);
	}
	public function fromBranch()
	{
		return $this->belongsTo(Branch::class,'from_branch_id','id');
	}
	public function getFromBranchName()
	{
		return $this->fromBranch ? $this->fromBranch->getName()  : __('N/A');  
	}
	public function getFromBranchId()
	{
		return $this->fromBranch ? $this->fromBranch->id  :0;  
	}
	public function toBranch()
	{
		return $this->belongsTo(Branch::class,'to_branch_id','id');
	}
	public function getToBranchName()
	{
		return $this->toBranch ? $this->toBranch->getName()  : __('N/A');  
	}
	public function getToBranchId()
	{
		return $this->toBranch ? $this->toBranch->id  :0;  
	}

	
	public function fullyIntegratedWithOdoo():bool
	{
		return !$this->hasOdooError() && count($this->getOdooReferenceNames()) ;
	}
	public function getOdooReferenceNames():array 
	{
		$result = [];
		foreach([
			'outbound_odoo_reference',
			'inbound_odoo_reference'
		] as $referenceColumnName){
			if($this->{$referenceColumnName}){
				$result[] = $this->{$referenceColumnName};
			}
		}
		
		
		return $result;
	}
	public function hasOdooError():bool
	{
		return !$this->synced_with_odoo && $this->odoo_error_message ;
	}
	public function getBreakColumns():array
	{
		return [];
	}
	
}
