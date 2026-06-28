<?php

namespace App\Models;

use App\Traits\HasBasicStoreRequest;
use App\Traits\Models\HasUserComment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * * هنا عميلة تحويل الاموال من حساب بنك الي حساب خاص بال
 * * letter of credit issuance
 * * عن طريق بسحب كريدت من حساب احطة دبت في حساب اخر
 *
 * @property int $id
 * @property string|null $type
 * @property string|null $transfer_date هو التاريخ اللي اللي هيتم فيه العميله
 * @property int $transfer_days عدد الايام المتوقع فيها اتمام هذه العمليه
 * @property int|null $from_bank_id
 * @property int|null $from_account_type_id
 * @property string|null $from_account_number
 * @property string|null $currency
 * @property int|null $to_letter_of_credit_issuance_id
 * @property numeric $amount مقدار مبلغ التحويل
 * @property int $company_id
 * @property string|null $from_comment_ar
 * @property string|null $to_comment_ar
 * @property string|null $from_comment_en
 * @property string|null $to_comment_en
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $user_comment
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LcOverdraftBankStatement> $LcOverdraftBankStatements
 * @property-read int|null $lc_overdraft_bank_statements_count
 * @property-read bool|null $lc_overdraft_bank_statements_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CurrentAccountBankStatement> $currentAccountBankStatements
 * @property-read int|null $current_account_bank_statements_count
 * @property-read bool|null $current_account_bank_statements_exists
 * @property-read \App\Models\AccountType|null $fromAccountType
 * @property-read \App\Models\FinancialInstitution|null $fromBank
 * @property-read \App\Models\LetterOfCreditIssuance|null $letterOfCreditIssuance
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcSettlementInternalMoneyTransfer newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcSettlementInternalMoneyTransfer newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcSettlementInternalMoneyTransfer query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcSettlementInternalMoneyTransfer whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcSettlementInternalMoneyTransfer whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcSettlementInternalMoneyTransfer whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcSettlementInternalMoneyTransfer whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcSettlementInternalMoneyTransfer whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcSettlementInternalMoneyTransfer whereFromAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcSettlementInternalMoneyTransfer whereFromAccountTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcSettlementInternalMoneyTransfer whereFromBankId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcSettlementInternalMoneyTransfer whereFromCommentAr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcSettlementInternalMoneyTransfer whereFromCommentEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcSettlementInternalMoneyTransfer whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcSettlementInternalMoneyTransfer whereToCommentAr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcSettlementInternalMoneyTransfer whereToCommentEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcSettlementInternalMoneyTransfer whereToLetterOfCreditIssuanceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcSettlementInternalMoneyTransfer whereTransferDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcSettlementInternalMoneyTransfer whereTransferDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcSettlementInternalMoneyTransfer whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcSettlementInternalMoneyTransfer whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcSettlementInternalMoneyTransfer whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LcSettlementInternalMoneyTransfer whereUserComment($value)
 * @mixin \Eloquent
 */
class LcSettlementInternalMoneyTransfer extends Model 
{
	use HasBasicStoreRequest , HasUserComment;
	const BANK_TO_LETTER_OF_CREDIT = 'bank-to-letter-of-credit';
	
	
	public static function generateFromAccountComment(self $internalMoneyTransfer,string $lang)
	{
		
		if($internalMoneyTransfer->isBankToLetterOfCredit()){
			return __('From :from Account No :no To Safe',['from'=>$internalMoneyTransfer->getFromBankName(),'no'=>$internalMoneyTransfer->getFromAccountNumber()],$lang) ;
		}
		
		
	}	
	public static function generateToAccountComment(self $internalMoneyTransfer,string $lang)
	{

	
		if($internalMoneyTransfer->isBankToLetterOfCredit()){
			return 'to comment here ' . $lang ; 
			// return __('To :branchName Safe',['branchName'=>$internalMoneyTransfer->getToBranchName()],$lang) ;
		}
		
	}
	protected static function booted()
	{
		self::creating(function (self $lcMoneyTransfer): void {
			$lcMoneyTransfer->from_comment_en = self::generateFromAccountComment($lcMoneyTransfer,'en');
			$lcMoneyTransfer->from_comment_ar = self::generateFromAccountComment($lcMoneyTransfer,'ar');			
			$lcMoneyTransfer->to_comment_en = self::generateToAccountComment($lcMoneyTransfer,'en');
			$lcMoneyTransfer->to_comment_ar = self::generateToAccountComment($lcMoneyTransfer,'ar');
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
			self::BANK_TO_LETTER_OF_CREDIT,
		];
	}
    protected $guarded = ['id'];
	public function getType()
	{
		return $this->type ;
	}

	public function isBankToLetterOfCredit()
	{
		return $this->getType() == self::BANK_TO_LETTER_OF_CREDIT;
	}
	
    // public function getTransferDays()
    // {
    //     return $this->transfer_days ?: 0 ;
    // }
	// public function getReceivingDateFormatted()
	// {
		
	// 	return Carbon::make($this->getTransferDate())->addDays($this->getTransferDays())->format('d-m-Y') ;
	// }
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
	 public function getFromAccountTypeId()
    {
        return $this->fromAccountType ? $this->fromAccountType->getId() : 0;
    }

    public function getFromAccountNumber()
    {
        return $this->from_account_number ;
    }

    public function getCurrency()
    {
        return $this->currency ;
    }
	public function getCurrencyFormatted()
    {
        return $this->getCurrency() ;
    }
    public function getAmount()
    {
        return $this->amount ?: 0;
    }
	
    public function getAmountFormatted()
    {
        return number_format($this->getAmount(), 0);
    }


	
	public function currentAccountBankStatements()
    {
        return $this->hasMany(CurrentAccountBankStatement::class, 'lc_settlement_internal_money_transfer_id', 'id');
    }
	
  
    public function deleteRelations()
    {
		$this->currentAccountBankStatements->each(function (CurrentAccountBankStatement $currentAccountBankStatement) {
			$currentAccountBankStatement->delete();
		});		
		$this->LcOverdraftBankStatements->each(function (LcOverdraftBankStatement $lcOverdraftBankStatement) {
			$lcOverdraftBankStatement->delete();
		});
		// $this->cashInSafeStatements->each(function (CashInSafeStatement $cashInSafeStatement) {
		// 	$cashInSafeStatement->delete();
		// });
		
    }
	/**
	 * * هنا لما بنحول من بنك او الى بنك بغض النظر عن نوع الحساب
	 */
	public function handleBankTransfer(int $companyId , int $fromFinancialInstitutionId , AccountType $fromAccountType , string $fromAccountNumber ,string $transferDate  , $debitAmount , $creditAmount , $commentEn , $commentAr)
	{
		if( $fromAccountType->isCurrentAccount()){
		
			$fromCurrentAccount = FinancialInstitutionAccount::findByAccountNumber($fromAccountNumber,$companyId,$fromFinancialInstitutionId);
			CurrentAccountBankStatement::create([
				'financial_institution_account_id'=>$fromCurrentAccount->id ,
				'lc_settlement_internal_money_transfer_id'=>$this->id  ,
				'company_id'=>$companyId ,
				'date' => $transferDate , 
				'credit'=>$creditAmount,
				'debit'=>$debitAmount,
				'comment_en'=>$commentEn , 
				'comment_ar'=>$commentAr
			]);
		}
		
		
	}
	
	public function LcOverdraftBankStatements()
	{
		return $this->hasMany(LcOverdraftBankStatement::class,'lc_settlement_internal_money_transfer_id','id');
	}
		
	// }
	/**
	 * * دي هتستخدم في الحالتين سواء من او الى
	 */
	public function handleLetterOfCreditTransfer(int $companyId,int $lcFacilityId , $lcFacilityLimit, string $date ,  $debitAmount , $creditAmount , LetterOfCreditIssuance $letterOfCreditIssuance , string $commentEn , string $commentAr )
	{
	

				 return $this->LcOverdraftBankStatements()->create([
					'lc_issuance_id'=>$letterOfCreditIssuance->id  ,
					'lc_facility_id'=>$lcFacilityId,
					'lc_settlement_internal_money_transfer_id'=>$this->id ,
					'source'=>$letterOfCreditIssuance->getSource(),
					'type'=>LcOverdraftBankStatement::LC_OVERDRAFT_MONEY_TRANSFER ,
					'company_id'=>$companyId,
					'date'=>$date,
					'limit'=>$lcFacilityLimit,
					'beginning_balance'=>0 ,
					'debit'=>$debitAmount,
					'credit'=>$creditAmount,
					'comment_en'=>$commentEn,
					'comment_ar'=>$commentAr
				]);
		

	}
	
	public function handleBankToLetterOfCreditTransfer( int $companyId ,int $lcFacilityId,$lcFacilityLimit, AccountType $fromAccountType , string $fromAccountNumber , int $fromFinancialInstitutionId , LetterOfCreditIssuance $letterOfCreditIssuance , string $transferDate , $transferAmount , string $commentEn , string $commentAr)
	{
	
		$this->handleBankTransfer($companyId , $fromFinancialInstitutionId ,  $fromAccountType , $fromAccountNumber , $transferDate ,0, $transferAmount,$commentEn,$commentAr);
		$this->handleLetterOfCreditTransfer($companyId,$lcFacilityId,$lcFacilityLimit,$transferDate,$transferAmount,0,$letterOfCreditIssuance,$commentEn,$commentAr);
	}
	public function letterOfCreditIssuance()
	{
		return $this->belongsTo(LetterOfCreditIssuance::class , 'to_letter_of_credit_issuance_id','id');
	}
	public function getLetterOfCreditIssuanceTransactionName():string 
	{
	
		return $this->letterOfCreditIssuance  ? $this->letterOfCreditIssuance->getTransactionName() : __('N/A');
	}
	
}
