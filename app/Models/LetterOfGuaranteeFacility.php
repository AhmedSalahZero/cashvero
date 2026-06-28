<?php

namespace App\Models;

use App\Models\LetterOfGuaranteeFacilityTermAndCondition;
use App\Traits\Models\HasLetterOfGuaranteeStatements;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $name
 * @property int|null $financial_institution_id
 * @property int $company_id
 * @property string|null $contract_start_date
 * @property string|null $contract_end_date
 * @property string|null $currency
 * @property string|null $limit
 * @property string|null $outstanding_date
 * @property numeric $outstanding_amount
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\FinancialInstitution|null $financialInstitution
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LetterOfGuaranteeCashCoverStatement> $letterOfGuaranteeCashCoverStatements
 * @property-read int|null $letter_of_guarantee_cash_cover_statements_count
 * @property-read bool|null $letter_of_guarantee_cash_cover_statements_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LetterOfGuaranteeStatement> $letterOfGuaranteeStatements
 * @property-read int|null $letter_of_guarantee_statements_count
 * @property-read bool|null $letter_of_guarantee_statements_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LetterOfGuaranteeFacilityTermAndCondition> $termAndConditions
 * @property-read int|null $term_and_conditions_count
 * @property-read bool|null $term_and_conditions_exists
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacility newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacility newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacility query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacility whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacility whereContractEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacility whereContractStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacility whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacility whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacility whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacility whereFinancialInstitutionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacility whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacility whereLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacility whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacility whereOutstandingAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacility whereOutstandingDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacility whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\LetterOfGuaranteeFacility whereUpdatedBy($value)
 * @mixin \Eloquent
 */
class LetterOfGuaranteeFacility extends Model
{
	// use HasLetterOfGuaranteeStatements;
    
	protected $guarded = ['id'];
	public function getName()
	{
		return $this->name;
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

	public function getOutstandingDate()
	{
		return $this->outstanding_date;
	}
	public function getOutstandingDateFormatted()
	{
		$outstandingDate = $this->getOutstandingDate() ;
		return $outstandingDate ? Carbon::make($outstandingDate)->format('d-m-Y'):null ;
	}

	public function getLimit()
	{
		return $this->limit ?: 0 ;
	}

	public function getLimitFormatted()
	{
		return number_format($this->getLimit()) ;
	}
	public function getOutstandingAmount()
	{
		return $this->outstanding_amount ?: 0 ;
	}

	public function getOutstandingAmountFormatted()
	{
		return number_format($this->getOutstandingAmount()) ;
	}

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
		return $this->hasMany(LetterOfGuaranteeFacilityTermAndCondition::class , 'letter_of_guarantee_facility_id','id');
	}
    public function termAndConditionForLgType(string $lgType){
        return $this->termAndConditions->where('lg_type',$lgType)->first();
    }
	public function letterOfGuaranteeStatements()
	{
		return $this->hasMany(LetterOfGuaranteeStatement::class,'lg_facility_id','id');
	}
	public function letterOfGuaranteeCashCoverStatements()
	{
		return $this->hasMany(LetterOfGuaranteeCashCoverStatement::class,'lg_facility_id','id');
	}

}
