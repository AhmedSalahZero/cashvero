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
    /**
     * Facility Renewal — Phase 6 note: once a facility has been
     * renewed, termAndConditions() can hold more than one row per
     * lc_type (one per chapter). This must resolve to the CURRENT
     * chapter's row — sorting by id desc first (a renewal's rows are
     * always inserted after the chapter they supersede) so a stale
     * pre-renewal rate is never handed to a new LC Issuance.
     */
    public function termAndConditionForLcType(string $lcType){
        return $this->termAndConditions->sortByDesc('id')->first(fn ($tc) => $tc->lc_type === $lcType);
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

	/**
	 * * بتولد صف فايدة آخر كل شهر في كشف الـ LC Overdraft
	 * * (type = interest , interest_type = end_of_month) لكل شهر ما بين
	 * * بداية ونهاية التعاقد. الصف بينزل بـ credit = 0 والـ trigger هو اللي
	 * * بيملّي القيمة من مجموع interest_amount بتاع الشهر.
	 * *
	 * * نفس المنطق بالظبط بتاع الأنواع التانية في IsOverdraft ، والفرق إن
	 * * كشف الـ LC محتاج source و lc_issuance_id كمان. بنستخدم 0 للـ issuance
	 * * لأن الفايدة على مديونية التسهيل كلها مش على اعتماد بعينه
	 * *
	 * * ملحوظة: بنولّد للـ source بتاع lc-facility بس ، لأن الاعتمادات
	 * * المضمونة بشهادة/وديعة بتتسجّل بـ lc_facility_id = 0 ومالهاش تسهيل
	 * * ليه سعر فايدة
	 */
	public function handleEndOfMonthInterestForOverdraft(?string $contractStartDate , ?string $contractEndDate , int $companyId):void
	{
		if(!$contractStartDate || !$contractEndDate){
			return ;
		}

		$source = LetterOfCreditIssuance::LC_FACILITY ;
		$interestText = 'interest';
		$interestTypeText = 'end_of_month';

		$contractStartDateAsCarbon = Carbon::make($contractStartDate);
		$contractEndDateAsCarbon = Carbon::make($contractEndDate);

		/**
		 * * copy() مهمة: endOfMonth() بتعدّل الكائن نفسه
		 */
		$isLastDayOfMonth = $contractStartDateAsCarbon->copy()->endOfMonth()->isSameDay($contractStartDateAsCarbon);

		$dates = generateDatesBetweenTwoDatesWithoutOverflow($contractStartDateAsCarbon->copy(),$contractEndDateAsCarbon);
		$countDates = count($dates);

		$baseQuery = fn() => LcOverdraftBankStatement::where('company_id',$companyId)
			->where('lc_facility_id',$this->id)
			->where('source',$source)
			->where('type',$interestText)
			->where('interest_type',$interestTypeText);

		/**
		 * * لو التعاقد اتقصّر ، الصفوف اللي بقت بره المدة تتشال
		 */
		$baseQuery()->where('date','>',$contractEndDateAsCarbon->format('Y-m-d'))->delete();

		foreach($dates as $index => $dateAsString){
			if($index == 0 && $isLastDayOfMonth){
				continue;
			}
			$isLastLoop = $index == $countDates - 1 ;
			$currentEndOfMonthDate = $isLastLoop
				? $contractEndDateAsCarbon->format('Y-m-d')
				: Carbon::make($dateAsString)->endOfMonth()->format('Y-m-d');

			if($baseQuery()->where('date',$currentEndOfMonthDate)->exists()){
				continue;
			}

			LcOverdraftBankStatement::create([
				'company_id'=>$companyId,
				'lc_facility_id'=>$this->id,
				'lc_issuance_id'=>0,
				'source'=>$source,
				'priority'=>1,
				'type'=>$interestText,
				'date'=>$currentEndOfMonthDate,
				'limit'=>$this->getLimit(),
				'debit'=>0,
				'credit'=>0,
				'interest_type'=>$interestTypeText,
				'comment_en'=>__('End Of Month Interest'),
				'comment_ar'=>__('End Of Month Interest'),
			]);
		}

		$this->settleOverdraftInterestRows($companyId,$source);
	}

	/**
	 * * الصفوف بتتعمل بـ credit = 0 ، واللي بيملّي القيمة هو الـ before update
	 * * trigger (البلوك بتاع interest_type = end_of_month) مش الـ insert.
	 * * فبنلمس الصفوف بالترتيب عشان التريجر يحسب:
	 * *   المرة الأولى → بيملّي الـ credit
	 * *   المرة التانية → بيعيد حساب الأرصدة بالـ credit الجديد
	 * * (نفس سلوك الـ statement cascade في باقي الكشوف)
	 */
	protected function settleOverdraftInterestRows(int $companyId , string $source):void
	{
		$baseQuery = fn() => \Illuminate\Support\Facades\DB::table('lc_overdraft_bank_statements')
			->where('company_id',$companyId)
			->where('lc_facility_id',$this->id)
			->where('source',$source);

		foreach([1,2] as $pass){
			\App\Support\StatementCascade::touchRows($baseQuery(),'date asc , priority asc , id asc');
		}
	}

	/**
	 * Facility Renewal — Phase 6 (LC Facility). A hybrid of the two
	 * patterns already used elsewhere: the flat "Financing Terms &
	 * Conditions" fields renew like Fully Secured Overdraft's do
	 * (including the CD/TD-lending-percentage recalculation when
	 * this LC Facility is Fully Secured), and the per-LC-type rate
	 * matrix renews like LG Facility's does — a renewal writes a
	 * brand-new 3-row matrix tagged to the new chapter, and the old
	 * chapter's rows are left untouched as history.
	 */
	public function termsHistories()
	{
		return $this->hasMany(LetterOfCreditFacilityTermsHistory::class,'letter_of_credit_facility_id','id')->orderBy('effective_date');
	}

	public function getTermsAsOfDate(string $date):?LetterOfCreditFacilityTermsHistory
	{
		return $this->termsHistories()
			->where('effective_date','<=',$date)
			->reorder('effective_date','desc')
			->orderByDesc('id')
			->first();
	}

	public function getLatestTerms():?LetterOfCreditFacilityTermsHistory
	{
		return $this->termsHistories()->reorder('effective_date','desc')->orderByDesc('id')->first();
	}

	public function getCurrentChapterStartDateFormatted():?string
	{
		$latest = $this->getLatestTerms();
		$date = $latest?->effective_date ?: $this->contract_start_date;
		return $date ? Carbon::make($date)->format('d-m-Y') : null;
	}

	public function hasRenewals():bool
	{
		return $this->termsHistories()->count() > 1;
	}

	/**
	 * A "transaction" here means an LC has ever been issued against
	 * this facility (same idea as LG Facility's hasAnyTransactions()).
	 */
	public function hasAnyTransactions():bool
	{
		return \Illuminate\Support\Facades\DB::table('letter_of_credit_issuances')
			->where('lc_facility_id', $this->id)
			->exists();
	}

	/**
	 * Resolves the currently-linked CD/TD account's own amount, so a
	 * Fully Secured LC's renewed limit can be recalculated
	 * authoritatively server-side (amount × new percentage) — mirrors
	 * FullySecuredOverdraft::getLinkedCdOrTdAmount() exactly.
	 */
	public function getLinkedCdOrTdAmount():float
	{
		$accountType = AccountType::find($this->getCdOrTdAccountTypeId());
		if (!$accountType || !$this->getCdOrTdId()) {
			return 0;
		}
		$modelClass = '\\App\\Models\\'.$accountType->getModelName();
		$record = $modelClass::find($this->getCdOrTdId());
		return $record ? (float) $record->getAmount() : 0;
	}

	public function createOriginalTermsHistory():LetterOfCreditFacilityTermsHistory
	{
		$termsRow = $this->termsHistories()->create([
			'company_id' => $this->company_id,
			'effective_date' => $this->contract_start_date,
			'limit' => $this->limit,
			'cd_or_td_lending_percentage' => $this->cd_or_td_lending_percentage,
			'borrowing_rate' => $this->borrowing_rate,
			'bank_margin_rate' => $this->bank_margin_rate,
			'interest_rate' => $this->interest_rate,
			'min_interest_rate' => $this->min_interest_rate,
			'highest_debt_balance_rate' => $this->highest_debt_balance_rate,
			'admin_fees_rate' => $this->admin_fees_rate,
			'contract_end_date' => $this->contract_end_date,
			'is_original' => true,
			'notes' => 'Original facility terms.',
		]);

		/**
		 * Backfill safety net: an LC Facility created before this
		 * migration ran (or one whose term_and_conditions rows never
		 * got a terms_history_id for some other reason) gets its
		 * existing matrix tagged onto this brand-new Original chapter
		 * rather than left orphaned.
		 */
		$this->termAndConditions()->whereNull('terms_history_id')->update(['terms_history_id' => $termsRow->id]);

		return $termsRow;
	}

	/**
	 * $newTermAndConditions: array of 3 rows, one per LC type (Sight
	 * LC, Deferred, Cash Against Document) — a renewal's own
	 * complete, brand-new rate matrix. Never touches or deletes the
	 * previous chapter's rows — an LC already issued keeps its own
	 * already-snapshotted rate forever regardless, but the old matrix
	 * stays visible as history (same reasoning as LG Facility).
	 */
	public function renew(string $effectiveDate, array $newTerms, array $newTermAndConditions, int $userId):LetterOfCreditFacilityTermsHistory
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
				__('A renewal date must be after the current contract end date (:date).', ['date' => Carbon::make($currentEndDate)->format('d-m-Y')])
			);
		}

		if (empty($newTerms['contract_end_date'])) {
			throw new \InvalidArgumentException(
				__('A renewal must include a new contract end date — the previous end date can no longer apply once the renewal starts after it.')
			);
		}

		if (empty($newTermAndConditions)) {
			throw new \InvalidArgumentException(
				__('A renewal must include the Term & Conditions for all 3 LC types — the previous chapter\'s rates only apply to LCs issued before this renewal.')
			);
		}

		/**
		 * Same rule as FullySecuredOverdraft::renew(): if this LC
		 * Facility is Fully Secured, the limit is never trusted from
		 * the browser — it's always recalculated here from the
		 * linked CD/TD account's own amount × whichever lending
		 * percentage is in force (the new one if given, else the
		 * previous chapter's).
		 */
		$lendingPercentage = $newTerms['cd_or_td_lending_percentage'] ?? $previous?->cd_or_td_lending_percentage ?? $this->cd_or_td_lending_percentage;
		if ($this->isFullySecured()) {
			$linkedAmount = $this->getLinkedCdOrTdAmount();
			$calculatedLimit = round($linkedAmount * (float) $lendingPercentage / 100, 2);
		} else {
			$calculatedLimit = $newTerms['limit'] ?? $previous?->limit ?? $this->limit;
		}

		$borrowingRate = $newTerms['borrowing_rate'] ?? $previous?->borrowing_rate ?? $this->borrowing_rate;
		$bankMarginRate = $newTerms['bank_margin_rate'] ?? $previous?->bank_margin_rate ?? $this->bank_margin_rate;

		$termsRow = $this->termsHistories()->create([
			'company_id' => $this->company_id,
			'effective_date' => $effectiveDate,
			'limit' => $calculatedLimit,
			'cd_or_td_lending_percentage' => $lendingPercentage,
			'borrowing_rate' => $borrowingRate,
			'bank_margin_rate' => $bankMarginRate,
			// Interest Rate is always Borrowing Rate + Bank Margin
			// Rate (never independently typed) — same rule the
			// original Create/Edit form enforces client-side.
			'interest_rate' => (float) $borrowingRate + (float) $bankMarginRate,
			'min_interest_rate' => $newTerms['min_interest_rate'] ?? $previous?->min_interest_rate ?? $this->min_interest_rate,
			'highest_debt_balance_rate' => $newTerms['highest_debt_balance_rate'] ?? $previous?->highest_debt_balance_rate ?? $this->highest_debt_balance_rate,
			'admin_fees_rate' => $newTerms['admin_fees_rate'] ?? $previous?->admin_fees_rate ?? $this->admin_fees_rate,
			'contract_end_date' => $newTerms['contract_end_date'] ?? $previous?->contract_end_date ?? $this->contract_end_date,
			'notes' => $newTerms['notes'] ?? null,
			'is_original' => false,
			'created_by' => $userId,
		]);

		foreach ($newTermAndConditions as $row) {
			$termsRow->termAndConditions()->create(array_merge($row, [
				'letter_of_credit_facility_id' => $this->id,
				'company_id' => $this->company_id,
			]));
		}

		$this->update([
			'limit' => $termsRow->limit,
			'cd_or_td_lending_percentage' => $termsRow->cd_or_td_lending_percentage,
			'borrowing_rate' => $termsRow->borrowing_rate,
			'bank_margin_rate' => $termsRow->bank_margin_rate,
			'interest_rate' => $termsRow->interest_rate,
			'min_interest_rate' => $termsRow->min_interest_rate,
			'highest_debt_balance_rate' => $termsRow->highest_debt_balance_rate,
			'admin_fees_rate' => $termsRow->admin_fees_rate,
			'contract_end_date' => $termsRow->contract_end_date,
		]);

		return $termsRow;
	}

	/**
	 * Deletes the most recent renewal only — blocked if any LC has
	 * been issued (dated on/after the renewal's effective date)
	 * under it, and reverts the facility's live fields (both the
	 * flat rates AND the matrix) to whatever the next-most-recent
	 * chapter says.
	 */
	public function deleteLatestRenewal():void
	{
		$latest = $this->getLatestTerms();

		if (!$latest || $latest->is_original) {
			throw new \InvalidArgumentException(__('There is no renewal to delete — this facility is still on its original terms.'));
		}

		$blockingTransactionsExist = \Illuminate\Support\Facades\DB::table('letter_of_credit_issuances')
			->where('lc_facility_id', $this->id)
			->where('issuance_date', '>=', $latest->effective_date)
			->exists();

		if ($blockingTransactionsExist) {
			throw new \InvalidArgumentException(
				__('This renewal cannot be deleted because there are LCs issued on or after its effective date (:date). Please remove those first.', ['date' => $latest->getEffectiveDateFormatted()])
			);
		}

		$latest->delete();

		$newLatest = $this->getLatestTerms();
		$this->update([
			'limit' => $newLatest->limit,
			'cd_or_td_lending_percentage' => $newLatest->cd_or_td_lending_percentage,
			'borrowing_rate' => $newLatest->borrowing_rate,
			'bank_margin_rate' => $newLatest->bank_margin_rate,
			'interest_rate' => $newLatest->interest_rate,
			'min_interest_rate' => $newLatest->min_interest_rate,
			'highest_debt_balance_rate' => $newLatest->highest_debt_balance_rate,
			'admin_fees_rate' => $newLatest->admin_fees_rate,
			'contract_end_date' => $newLatest->contract_end_date,
		]);
	}

}
