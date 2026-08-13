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
 * @property int $id
 * @property string|null $odoo_error_message
 * @property int|null $synced_with_odoo
 * @property int|null $inbound_journal_entry_id
 * @property string|null $inbound_odoo_reference
 * @property int|null $outbound_journal_entry_id
 * @property string|null $outbound_odoo_reference
 * @property int|null $outbound_account_bank_statement_odoo_id
 * @property int|null $inbound_account_bank_statement_odoo_id
 * @property string|null $type
 * @property string|null $transfer_date هو التاريخ اللي اللي هيتم فيه العميله
 * @property int $transfer_days عدد الايام المتوقع فيها اتمام هذه العمليه
 * @property int|null $from_bank_id
 * @property int|null $to_bank_id
 * @property numeric $amount مقدار مبلغ التحويل
 * @property int|null $from_account_type_id
 * @property string|null $from_account_number
 * @property string|null $currency
 * @property int|null $to_account_type_id
 * @property string|null $to_account_number
 * @property string|null $cheque_number
 * @property int|null $from_branch_id
 * @property int|null $to_branch_id
 * @property int $company_id
 * @property string|null $from_comment_ar
 * @property string|null $from_comment_en
 * @property string|null $to_comment_ar
 * @property string|null $to_comment_en
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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer filterByTransferDate($startDate, $endDate)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereChequeNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereFromAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereFromAccountTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereFromBankId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereFromBranchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereFromCommentAr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereFromCommentEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereInboundAccountBankStatementOdooId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereInboundJournalEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereInboundOdooReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereOdooErrorMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereOutboundAccountBankStatementOdooId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereOutboundJournalEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereOutboundOdooReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereSyncedWithOdoo($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereToAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereToAccountTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereToBankId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereToBranchId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereToCommentAr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereToCommentEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereTransferDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereTransferDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\InternalMoneyTransfer whereUserComment($value)
 * @mixin \Eloquent
 */
class InternalMoneyTransfer extends Model 
{
	use HasBasicStoreRequest ,HasUserComment,HasCompany,HasOdooMoneyTransfer,HasDeleteOdoo;
	const BANK_TO_BANK = 'bank-to-bank';
	const BANK_TO_SAFE = 'bank-to-safe';
	const SAFE_TO_BANK = 'safe-to-bank';
	const SAFE_TO_SAFE = 'safe-to-safe';
	
	public function scopeFilterByTransferDate($query, $startDate, $endDate)
	{
		return $query->whereBetween('transfer_date', [$startDate, $endDate]);
	}
	public static function generateFromAccountComment(self $internalMoneyTransfer,string $lang)
	{
		if($internalMoneyTransfer->isBankToBank() ){
			return __('From :from Account No :no',['from'=>$internalMoneyTransfer->getFromBankName(),'no'=>$internalMoneyTransfer->getFromAccountNumber()],$lang) ;
		}
		if($internalMoneyTransfer->isBankToSafe()){
			return __('From :from Account No :no To Safe',['from'=>$internalMoneyTransfer->getFromBankName(),'no'=>$internalMoneyTransfer->getFromAccountNumber()],$lang) ;
		}
		if($internalMoneyTransfer->isSafeToBank()){
			return __('From :branchName Safe',['branchName'=>$internalMoneyTransfer->getFromBranchName()],$lang) ;
		}
		
	}	
	public static function generateToAccountComment(self $internalMoneyTransfer,string $lang)
	{

		if($internalMoneyTransfer->isBankToBank()  ){
			return __('To :to Account No :no',['to'=>$internalMoneyTransfer->getToBankName(),'no'=>$internalMoneyTransfer->getToAccountNumber()],$lang) ;
		}
		if($internalMoneyTransfer->isBankToSafe()){
			return __('To :branchName Safe',['branchName'=>$internalMoneyTransfer->getToBranchName()],$lang) ;
		}
		if($internalMoneyTransfer->isSafeToBank()){
			return __('To :to Account No :no',['to'=>$internalMoneyTransfer->getToBankName(),'no'=>$internalMoneyTransfer->getToAccountNumber()],$lang) ;
			
		}
	}
	protected static function booted()
	{
		self::creating(function (InternalMoneyTransfer $internalMoneyTransfer): void {
			$internalMoneyTransfer->from_comment_en = self::generateFromAccountComment($internalMoneyTransfer,'en');
			$internalMoneyTransfer->from_comment_ar = self::generateFromAccountComment($internalMoneyTransfer,'ar');			
			$internalMoneyTransfer->to_comment_en = self::generateToAccountComment($internalMoneyTransfer,'en');
			$internalMoneyTransfer->to_comment_ar = self::generateToAccountComment($internalMoneyTransfer,'ar');
		});
	}
	// public function isCredit()
	// {
	// 	return (bool) $this->is_credit ;
	// }
	// public function isDebit()
	// {
	// 	return (bool) $this->is_debit ;
	// }
	
	public static function getAllTypes()
	{
		return [
			self::BANK_TO_BANK,
			self::BANK_TO_SAFE,
			self::SAFE_TO_BANK
		];
	}
    protected $guarded = ['id'];
	public function getType()
	{
		return $this->type ;
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
	
    public function getTransferDays()
    {
        return $this->transfer_days ?: 0 ;
    }
	public function getReceivingDateFormatted()
	{
		
		return Carbon::make($this->getTransferDate())->addDays($this->getTransferDays())->format('d-m-Y') ;
	}
    public function setTransferDateAttribute($value)
    {
        if (!$value) {
            return null ;
        }
        $date = explode('/', $value);
        if (count($date) != 3) {
            $this->attributes['transfer_date'] = $value;

            return  ;
        }
        $month = $date[0];
        $day = $date[1];
        $year = $date[2];
        $this->attributes['transfer_date'] = $year . '-' . $month . '-' . $day;
    }

    public function getTransferDate()
    {
        return $this->transfer_date ;
    }

    public function getTransferDateFormatted()
    {
        $transferDate = $this->getTransferDate() ;

        return $transferDate ? Carbon::make($transferDate)->format('d-m-Y') : null ;
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
	public function getAccountNumber()
	{
		return $this->getFromAccountNumber();
	}
	 public function getFromAccountTypeId()
    {
        return $this->fromAccountType ? $this->fromAccountType->getId() : 0;
    }
	public function getAccountTypeId()
	{
		return $this->getFromAccountTypeId();
	}

    public function getFromAccountNumber()
    {
        return $this->from_account_number ;
    }

    public function getCurrency()
    {
        return $this->currency ;
    }
	// public function getCurrencyInMainName()
    // {
    //     return $this->getCurrency() ;
    // }
	public function getCurrencyFormatted()
    {
        return $this->getCurrency() ;
    }
    public function getAmount()
    {
        return $this->amount ?: 0;
    }
	public function getAmountInCurrency()
	{
		return $this->getAmount();
	}
	public function getAmountInMainCurrency()
	{
		return $this->getAmount();
	}
	public function getPaidAmount()
	{
		return $this->getAmount();
	}
    public function getAmountFormatted()
    {
        return number_format($this->getAmount(), 0);
    }

    public function toBank()
    {
        return $this->belongsTo(FinancialInstitution::class, 'to_bank_id', 'id');
    }

    public function getToBankName()
    {
        return $this->toBank ? $this->toBank->getName() : __('N/A');
    }
	public function getToBankId()
    {
        return $this->toBank ? $this->toBank->id : 0;
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
        return $this->hasMany(CurrentAccountBankStatement::class, 'internal_money_transfer_id', 'id');
    }
    public function cleanOverdraftBankStatements()
    {
        return $this->hasMany(CleanOverdraftBankStatement::class, 'internal_money_transfer_id', 'id');
    }
	public function fullySecuredOverdraftBankStatements()
    {
        return $this->hasMany(FullySecuredOverdraftBankStatement::class, 'internal_money_transfer_id', 'id');
    }
	public function overdraftAgainstCommercialPaperBankStatements()
    {
		return $this->hasMany(OverdraftAgainstCommercialPaperBankStatement::class, 'internal_money_transfer_id', 'id');
    }
	public function overdraftAgainstAssignmentOfContractBankStatements()
    {
		return $this->hasMany(OverdraftAgainstAssignmentOfContractBankStatement::class, 'internal_money_transfer_id', 'id');
    }
	public function cashInSafeStatements():HasMany
	{
		return $this->hasMany(CashInSafeStatement::class,'internal_money_transfer_id','id');
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
	public function handleBankTransfer(int $companyId , int $fromFinancialInstitutionId , AccountType $fromAccountType , string $fromAccountNumber ,string $transferDate  , $debitAmount , $creditAmount)
	{
		if( $fromAccountType->isCurrentAccount()){
			
			$fromCurrentAccount = FinancialInstitutionAccount::findByAccountNumber($fromAccountNumber,$companyId,$fromFinancialInstitutionId);
			CurrentAccountBankStatement::create([
				'financial_institution_account_id'=>$fromCurrentAccount->id ,
				'internal_money_transfer_id'=>$this->id  ,
				'company_id'=>$companyId ,
				'date' => $transferDate , 
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
				'internal_money_transfer_id'=>$this->id ,
				'company_id'=>$companyId ,
				'date' => $transferDate , 
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
				'internal_money_transfer_id'=>$this->id ,
				'company_id'=>$companyId ,
				'date' => $transferDate , 
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
				'internal_money_transfer_id'=>$this->id ,
				'company_id'=>$companyId ,
				'date' => $transferDate , 
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
				'internal_money_transfer_id'=>$this->id ,
				'company_id'=>$companyId ,
				'date' => $transferDate , 
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
	public function handleBankToBankTransfer( int $companyId , AccountType $fromAccountType , string $fromAccountNumber , int $fromFinancialInstitutionId , AccountType $toAccountType , string $toAccountNumber , int $toFinancialInstitutionId , string $transferDate , string $receivingDate, $transferAmount)
	{
		$this->handleBankTransfer($companyId , $fromFinancialInstitutionId ,  $fromAccountType , $fromAccountNumber , $transferDate , 0,$transferAmount);
		$this->handleBankTransfer($companyId , $toFinancialInstitutionId , $toAccountType , $toAccountNumber ,$receivingDate , $transferAmount,0);
	}
	public function handleBankToSafeTransfer( int $companyId , AccountType $fromAccountType , string $fromAccountNumber , int $fromFinancialInstitutionId , int $toBranchId , string $currencyName , string $transferDate , $transferAmount)
	{
		$this->handleBankTransfer($companyId , $fromFinancialInstitutionId ,  $fromAccountType , $fromAccountNumber , $transferDate ,0, $transferAmount);
		$this->handleSafeTransfer($companyId,$transferDate,$transferAmount,0,$toBranchId ,$currencyName,1);
	}
	public function handleSafeToBankTransfer( int $companyId , AccountType $toAccountType , string $toAccountNumber , int $toFinancialInstitutionId , int $fromBranchId , string $currencyName , string $transferDate , $transferAmount)
	{
		$this->handleSafeTransfer($companyId,$transferDate,0,$transferAmount,$fromBranchId ,$currencyName,1);
		$this->handleBankTransfer($companyId , $toFinancialInstitutionId ,  $toAccountType , $toAccountNumber , $transferDate , $transferAmount,0);
	}
	public function handleSafeToSafeTransfer( int $companyId , int $toBranchId , int $fromBranchId , string $currencyName , string $transferDate , $transferAmount)
	{
		$this->handleSafeTransfer($companyId,$transferDate,0,$transferAmount,$fromBranchId ,$currencyName,1);
		$this->handleSafeTransfer($companyId,$transferDate,$transferAmount,0,$toBranchId ,$currencyName,1);
	}
	public function fromBranch()
	{
		return $this->belongsTo(Branch::class,'from_branch_id','id');
	}
	public function getBranchId()
	{
		return $this->getFromBranchId();
	}
	public function getFromBranchName()
	{
		return $this->fromBranch ? $this->fromBranch->getName()  : __('N/A');  
	}
	public function getFromBranchId()
	{
		return $this->fromBranch ? $this->fromBranch->id  : 0;  
	}
	public function toBranch()
	{
		return $this->belongsTo(Branch::class,'to_branch_id','id');
	}
	public function getToBranchId()
	{
		return $this->to_branch_id;
	}

	public function getToBranchName()
	{
		return $this->toBranch ? $this->toBranch->getName()  : __('N/A');  
	}
	
	public function getChequeNumber()
	{
		return $this->cheque_number ; 
	}
	
	public function hasOdooError():bool
	{
		return !$this->synced_with_odoo && $this->odoo_error_message ;
	}
	/**
	 * Missing getter — hasOdooError() existed but nothing ever exposed
	 * the actual message. Added per audit, 2026-08-13, alongside the
	 * matching Vue error icon.
	 */
	public function getOdooError()
	{
		if ($this->hasOdooError()) {
			return $this->odoo_error_message;
		}
		return '';
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
	
	public function getBreakColumns():array
	{
		return [];
	}	
}
