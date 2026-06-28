<?php

namespace App\Models;

use App\Models\LetterOfCreditFacilityTermAndCondition;
use App\Traits\Models\HasLetterOfCreditCashCoverStatements;
use App\Traits\Models\HasLetterOfCreditStatements;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $type هل هو عادي ولا فولي سيكيورد
 * @property string|null $name
 * @property int|null $financial_institution_id
 * @property int $company_id
 * @property string|null $contract_start_date
 * @property string|null $contract_end_date
 * @property string|null $currency
 * @property string|null $cd_or_td_currency
 * @property string|null $limit
 * @property string|null $financing_duration
 * @property int|null $cd_or_td_account_type_id
 * @property int|null $cd_or_td_id
 * @property numeric|null $cd_or_td_amount
 * @property string|null $cd_or_td_interest
 * @property string|null $cd_or_td_lending_percentage
 * @property string|null $borrowing_rate
 * @property string|null $bank_margin_rate
 * @property string|null $interest_rate
 * @property string|null $min_interest_rate
 * @property string|null $highest_debt_balance_rate
 * @property string|null $admin_fees_rate
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $oldest_date
 * @property-read \App\Models\FinancialInstitution|null $financialInstitution
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LcOverdraftBankStatement> $lcOverdraftBankStatements
 * @property-read int|null $lc_overdraft_bank_statements_count
 * @property-read bool|null $lc_overdraft_bank_statements_exists
 * @property-read \App\Models\LcOverdraftBankStatement|null $lcOverdraftCreditBankStatement
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LetterOfCreditCashCoverStatement> $letterOfCreditCashCoverStatements
 * @property-read int|null $letter_of_credit_cash_cover_statements_count
 * @property-read bool|null $letter_of_credit_cash_cover_statements_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LetterOfCreditStatement> $letterOfCreditStatements
 * @property-read int|null $letter_of_credit_statements_count
 * @property-read bool|null $letter_of_credit_statements_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LetterOfCreditFacilityTermAndCondition> $termAndConditions
 * @property-read int|null $term_and_conditions_count
 * @property-read bool|null $term_and_conditions_exists
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacility newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacility newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacility query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacility whereAdminFeesRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacility whereBankMarginRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacility whereBorrowingRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacility whereCdOrTdAccountTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacility whereCdOrTdAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacility whereCdOrTdCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacility whereCdOrTdId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacility whereCdOrTdInterest($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacility whereCdOrTdLendingPercentage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacility whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacility whereContractEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacility whereContractStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacility whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacility whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacility whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacility whereFinancialInstitutionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacility whereFinancingDuration($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacility whereHighestDebtBalanceRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacility whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacility whereInterestRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacility whereLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacility whereMinInterestRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacility whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacility whereOldestDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacility whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacility whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfCreditFacility whereUpdatedBy($value)
 * @mixin \Eloquent
 */
class LetterOfCreditFacility extends Model
{
	use HasLetterOfCreditStatements , HasLetterOfCreditCashCoverStatements;
    
	protected $guarded = ['id'];
	CONST UNSECURED ='unsecured';
	CONST FULLY_SECURED ='fully-secured';
	
	public function getName()
	{
		return $this->name ?: __('N/A');
	}
	public function getContractStartDate()
	{
		return $this->contract_start_date;
	}
	public function getContractStartDateFormatted()
	{
		$contractStartDate = $this->contract_start_date ;
		return $contractStartDate ? Carbon::make($contractStartDate)->format('d-m-Y'):null ;
	}
	public function getContractEndDate()
	{
		return $this->contract_end_date;
	}
	public function getContractEndDateFormatted()
	{
		$contractEndDate = $this->getContractEndDate() ;
		return $contractEndDate ? Carbon::make($contractEndDate)->format('d-m-Y'):null ;
	}

	// public function getOutstandingDate()
	// {
	// 	return $this->outstanding_date;
	// }
	public function getBorrowingRate()
	{
		 return $this->borrowing_rate ;
	}
	// public function getOutstandingDateFormatted()
	// {
	// 	$outstandingDate = $this->getOutstandingDate() ;
	// 	return $outstandingDate ? Carbon::make($outstandingDate)->format('d-m-Y'):null ;
	// }

	public function getLimit()
	{
		return $this->limit ?: 0 ;
	}

	public function getLimitFormatted()
	{
		return number_format($this->getLimit()) ;
	}
	// public function getOutstandingAmount()
	// {
	// 	return $this->outstanding_amount ?: 0 ;
	// }

	// public function getOutstandingAmountFormatted()
	// {
	// 	return number_format($this->getOutstandingAmount()) ;
	// }

	public function getCurrency()
	{
		return $this->currency ;
	}
	public function financialInstitution()
	{
		return $this->belongsTo(FinancialInstitution::class , 'financial_institution_id','id');
	}
	public function termAndConditions()
	{
		return $this->hasMany(LetterOfCreditFacilityTermAndCondition::class , 'letter_of_credit_facility_id','id');
	}
    public function termAndConditionForLcType(string $lcType){
        return $this->termAndConditions->where('lc_type',$lcType)->first();
    }
	public function letterOfCreditStatements()
	{
		return $this->hasMany(LetterOfCreditStatement::class,'lc_facility_id','id');
	}
	public function letterOfCreditCashCoverStatements()
	{
		return $this->hasMany(LetterOfCreditCashCoverStatement::class,'lc_facility_id','id');
	}
	public function getType()
	{
		return $this->type;
	}
	public static function getTypes()
	{
		return [
			self::UNSECURED=>__('Unsecured'),
			self::FULLY_SECURED=>__('Fully Secured'),
		];
	}
	public function isUnsecured()
	{
		return $this->type == self::UNSECURED;
	}
	public function isFullySecured()
	{
		return $this->type == self::FULLY_SECURED;
	}
	public function getCdOrTdAccountTypeId()
	{
		return $this->cd_or_td_account_type_id; 
	}
	public function getCdOrTdId()
	{
		return $this->cd_or_td_id;
	}
	public function lcOverdraftCreditBankStatement()
	{
		return $this->hasOne(LcOverdraftBankStatement::class,'lc_facility_id','id')->where('is_credit',1)->orderBy('full_date','desc');
	}
	public function lcOverdraftBankStatements()
	{
		return $this->hasMany(LcOverdraftBankStatement::class,'lc_facility_id','id')->orderBy('full_date','desc');
	}
	
}
