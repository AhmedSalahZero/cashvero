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

	/**
	 * Facility Renewal — Phase 5 (final facility type). Simplest of
	 * all five — no interest, no settlement days, no auto-calculated
	 * limit. Each LG issuance already snapshots its own rate at the
	 * moment it's issued (confirmed against the actual code before
	 * building this), so — unlike the ODA types — nothing here needs
	 * date-aware lookups for calculation correctness. This is purely
	 * about keeping the facility's own dated terms, and never letting
	 * a renewal silently overwrite the previous chapter's Term &
	 * Conditions history.
	 */
	public function termsHistories()
	{
		return $this->hasMany(LetterOfGuaranteeFacilityTermsHistory::class,'letter_of_guarantee_facility_id','id')->orderBy('effective_date');
	}

	public function getTermsAsOfDate(string $date):?LetterOfGuaranteeFacilityTermsHistory
	{
		return $this->termsHistories()
			->where('effective_date','<=',$date)
			->reorder('effective_date','desc')
			->orderByDesc('id')
			->first();
	}

	public function getLatestTerms():?LetterOfGuaranteeFacilityTermsHistory
	{
		return $this->termsHistories()->reorder('effective_date','desc')->orderByDesc('id')->first();
	}

	public function getCurrentChapterStartDateFormatted():?string
	{
		$latest = $this->getLatestTerms();
		$date = $latest?->effective_date ?: $this->contract_start_date;
		return $date ? \Carbon\Carbon::make($date)->format('d-m-Y') : null;
	}

	public function hasRenewals():bool
	{
		return $this->termsHistories()->count() > 1;
	}

	/**
	 * A "transaction" here means an LG has ever been issued against
	 * this facility.
	 */
	public function hasAnyTransactions():bool
	{
		return \Illuminate\Support\Facades\DB::table('letter_of_guarantee_issuances')
			->where('lg_facility_id', $this->id)
			->exists();
	}

	public function createOriginalTermsHistory():LetterOfGuaranteeFacilityTermsHistory
	{
		return $this->termsHistories()->create([
			'company_id' => $this->company_id,
			'effective_date' => $this->contract_start_date,
			'limit' => $this->limit,
			'contract_end_date' => $this->contract_end_date,
			'is_original' => true,
			'notes' => 'Original facility terms.',
		]);
	}

	/**
	 * $newTermAndConditions: array of 4 rows, one per LG type — a
	 * renewal's own complete, brand-new rate matrix. Never touches or
	 * deletes the previous chapter's rows — an LG already issued keeps
	 * its own already-snapshotted rate forever regardless, but the
	 * client explicitly wants the old matrix visible as history too
	 * (e.g. "Final LG commission used to be 0.2%, now it's 0.4%").
	 */
	public function renew(string $effectiveDate, array $newTerms, array $newTermAndConditions, int $userId):LetterOfGuaranteeFacilityTermsHistory
	{
		if ($this->termsHistories()->count() === 0) {
			$this->createOriginalTermsHistory();
		}

		$previous = $this->getLatestTerms();

		if ($previous && $effectiveDate <= $previous->effective_date) {
			throw new \InvalidArgumentException(
				__('A renewal date must be after the facility\'s most recent renewal date (:date).', ['date' => $previous->getEffectiveDateFormatted()])
			);
		}

		$currentEndDate = $previous?->contract_end_date ?: $this->contract_end_date;
		if ($currentEndDate && $effectiveDate <= $currentEndDate) {
			throw new \InvalidArgumentException(
				__('A renewal date must be after the current contract end date (:date).', ['date' => \Carbon\Carbon::make($currentEndDate)->format('d-m-Y')])
			);
		}

		if (empty($newTerms['contract_end_date'])) {
			throw new \InvalidArgumentException(
				__('A renewal must include a new contract end date — the previous end date can no longer apply once the renewal starts after it.')
			);
		}

		if (empty($newTermAndConditions)) {
			throw new \InvalidArgumentException(
				__('A renewal must include the Term & Conditions for all 4 LG types — the previous chapter\'s rates only apply to LGs issued before this renewal.')
			);
		}

		$termsRow = $this->termsHistories()->create([
			'company_id' => $this->company_id,
			'effective_date' => $effectiveDate,
			'limit' => $newTerms['limit'] ?? $previous?->limit ?? $this->limit,
			'contract_end_date' => $newTerms['contract_end_date'] ?? $previous?->contract_end_date ?? $this->contract_end_date,
			'notes' => $newTerms['notes'] ?? null,
			'is_original' => false,
			'created_by' => $userId,
		]);

		foreach ($newTermAndConditions as $row) {
			$termsRow->termAndConditions()->create(array_merge($row, [
				'letter_of_guarantee_facility_id' => $this->id,
				'company_id' => $this->company_id,
				'outstanding_balance' => 0,
			]));
		}

		$this->update([
			'limit' => $termsRow->limit,
			'contract_end_date' => $termsRow->contract_end_date,
		]);

		return $termsRow;
	}

	/**
	 * Deletes the most recent renewal only — blocked if any LG has
	 * been issued (dated on/after the renewal's effective date) under
	 * it, since removing it would silently change what a reader sees
	 * as "the terms in force" at that point in history — even though,
	 * per the confirmed rule, no already-issued LG's own numbers would
	 * actually change (they're already locked in on the issuance
	 * itself). Same conservative guard as the ODA facilities regardless.
	 */
	public function deleteLatestRenewal():void
	{
		$latest = $this->getLatestTerms();

		if (!$latest || $latest->is_original) {
			throw new \InvalidArgumentException(__('There is no renewal to delete — this facility is still on its original terms.'));
		}

		$blockingIssuances = \Illuminate\Support\Facades\DB::table('letter_of_guarantee_issuances')
			->where('lg_facility_id', $this->id)
			->where('issuance_date', '>=', $latest->effective_date)
			->exists();

		if ($blockingIssuances) {
			throw new \InvalidArgumentException(
				__('This renewal cannot be deleted because LGs have already been issued on or after its effective date (:date). Please remove those first.', ['date' => $latest->getEffectiveDateFormatted()])
			);
		}

		$latest->termAndConditions()->delete();
		$latest->delete();

		$newLatest = $this->getLatestTerms();
		$this->update([
			'limit' => $newLatest->limit,
			'contract_end_date' => $newLatest->contract_end_date,
		]);
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
