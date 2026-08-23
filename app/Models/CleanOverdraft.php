<?php

namespace App\Models;

use App\Http\Controllers\CleanOverdraftController;
use App\Interfaces\Models\Interfaces\IHaveStatement;
use App\Traits\HasBankStatement;
use App\Traits\HasLastStatementAmount;
use App\Traits\HasOutstandingBreakdown;
use App\Traits\IsLockableBankAccount;
use App\Traits\IsOverdraft;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * * هو نوع من انواع حسابات التسهيل البنكية (زي القرض يعني بس فية فرق بينهم ) وبيسمى حد جاري مدين بدون ضمان
 * * بدون ضمان يعني مش بياخدوا مقابل قصادة يعني مثلا مش بياخدوا منك شيكات مثلا او بيت .
 * 
 * . الخ علشان كدا اسمه كلين
 * * والفرق بينه وبين القرض ان هنا انت مش ملتزم تسدد مبلغ معين في فتره معين اي لا  يوجد اقساط للدفع
 * * وبناء عليه كل اما قللت التسديد كل اما هينزل عليك فايدة اكبر الشهر الجاي
 * * وعموما في حالة انك مدان للبنك وليكن مثلا لو انت سالف من البنك عشر الالف وسحبت تسعه ونزل عليك فايدة خمس مئة جنية
 * * وقتها ال خمس مئة جنية دول بينسحبوا من حسابك علطول وبالتالي انت ما عتش فاضلك غير خمس مئة مثلا
 *
 * @property int $id
 * @property int|null $financial_institution_id
 * @property int $company_id
 * @property string|null $contract_start_date
 * @property string|null $contract_end_date
 * @property string|null $account_number
 * @property string|null $currency
 * @property string|null $limit
 * @property string|null $outstanding_balance
 * @property string|null $balance_date
 * @property float|null $highest_debt_balance_rate
 * @property float|null $admin_fees_rate
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $to_be_setteled_max_within_days
 * @property string|null $start_settlement_from_bank_statement_date
 * @property string|null $oldest_date
 * @property int|null $origin_update_row_is_debit دلوقت احنا لما بنحدث وليكن ماني ريسيفد .. عايز نعرف ان الرو الاصلي اللي عدلناه كان ماني ريسيفد
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CleanOverdraftBankStatement> $bankStatements
 * @property-read int|null $bank_statements_count
 * @property-read bool|null $bank_statements_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CleanOverdraftBankStatement> $cleanOverdraftBankStatements
 * @property-read int|null $clean_overdraft_bank_statements_count
 * @property-read bool|null $clean_overdraft_bank_statements_exists
 * @property-read \App\Models\Company|null $company
 * @property-read \App\Models\FinancialInstitution|null $financialInstitution
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LendingInformation> $lendingInformation
 * @property-read int|null $lending_information_count
 * @property-read bool|null $lending_information_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\OutstandingBreakdown> $outstandingBreakdowns
 * @property-read int|null $outstanding_breakdowns_count
 * @property-read bool|null $outstanding_breakdowns_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CleanOverdraftRate> $rates
 * @property-read int|null $rates_count
 * @property-read bool|null $rates_exists
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraft newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraft newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraft query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraft whereAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraft whereAdminFeesRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraft whereBalanceDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraft whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraft whereContractEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraft whereContractStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraft whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraft whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraft whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraft whereFinancialInstitutionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraft whereHighestDebtBalanceRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraft whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraft whereLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraft whereOldestDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraft whereOriginUpdateRowIsDebit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraft whereOutstandingBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraft whereStartSettlementFromBankStatementDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraft whereToBeSetteledMaxWithinDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraft whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CleanOverdraft whereUpdatedBy($value)
 * @mixin \Eloquent
 */
class CleanOverdraft extends Model implements IHaveStatement
{
    protected $guarded = ['id'];
	
	use HasOutstandingBreakdown , IsOverdraft  , HasBankStatement, HasLastStatementAmount, IsLockableBankAccount ;
	
	public function cleanOverdraftBankStatements()
	{
		return $this->hasMany(CleanOverdraftBankStatement::class,'clean_overdraft_id','id');
	}
	public function bankStatements()
	{
		return $this->hasMany(CleanOverdraftBankStatement::class , 'clean_overdraft_id','id');
	}	
	
	public static function generateForeignKeyFormModelName():string 
	{
		return 'clean_overdraft_id';
	}	
	public static function getBankStatementTableName():string 
	{
		return 'clean_overdraft_bank_statements';
	}
	public static function getWithdrawalTableName():string 
	{
		return 'clean_overdraft_withdrawals';
	}
	public static function getBankStatementIdName():string 
	{
		return 'clean_overdraft_bank_statement_id';
	}
	public static function getTableNameFormatted()
	{
		return __('Clean Overdraft');
	}
	public  function getStatementTableName():string
	 {
		return 'clean_overdraft_bank_statements';	
	}
	public   function getForeignKeyInStatementTable()
	{
		 return 'clean_overdraft_id';
	}
	
	
	public static function getCommonQueryForCashDashboard(Company $company , string $currencyName , string $date )
	{
		return DB::table('clean_overdrafts')
			->where('currency', '=', $currencyName)
			->where('company_id', $company->id)
			->where('contract_start_date', '<=', $date)
			->orderBy('clean_overdrafts.id');
	}
	public static function hasAnyRecord(Company $company,string $currency)
	{
		return DB::table('clean_overdrafts')->where('company_id',$company->id)->where('currency',$currency)->exists();
	}
	public static function getCashDashboardDataForFinancialInstitution(array &$totalRoomForEachCleanOverdraftId,Company $company , array $cleanOverdraftIds , string $currencyName , string $date , int $financialInstitutionBankId , &$totalCleanOverdraftRoom  ):array 
	{
			
				foreach($cleanOverdraftIds as $cleanOverdraftId){
					$cleanOverdraftStatement = DB::table('clean_overdraft_bank_statements')
						->where('clean_overdraft_bank_statements.company_id', $company->id)
						->where('date', '<=', $date)
						->join('clean_overdrafts', 'clean_overdraft_bank_statements.clean_overdraft_id', '=', 'clean_overdrafts.id')
						->where('clean_overdrafts.currency', '=', $currencyName)
						->where('clean_overdraft_id',$cleanOverdraftId)
						->where('financial_institution_id',$financialInstitutionBankId)
						->orderByRaw('date desc , clean_overdraft_bank_statements.id desc')
						->first();
						
						$cleanOverdraftRoom = $cleanOverdraftStatement ? $cleanOverdraftStatement->room : 0 ;
						$totalCleanOverdraftRoom += $cleanOverdraftRoom ;
						$cleanOverdraft = CleanOverdraft::find($cleanOverdraftId);
						$financialInstitution = FinancialInstitution::find($financialInstitutionBankId);
						$financialInstitutionName = $financialInstitution->getName();
						if($cleanOverdraft->financial_institution_id ==$financialInstitution->id ){
							$totalRoomForEachCleanOverdraftId[$currencyName][]  = [
								'item'=>$financialInstitutionName ,
								'available_room'=>$cleanOverdraftRoom,
								'limit'=>$cleanOverdraftStatement  ? $cleanOverdraftStatement->limit : 0 ,
								'end_balance'=>$cleanOverdraftStatement ?  $cleanOverdraftStatement->end_balance : 0 
							] ;
						}
				}
				
				return $totalRoomForEachCleanOverdraftId ;
				
	}
	
	public static function getCashDashboardDataForYear(array &$cleanOverdraftCardData,Builder $cleanOverdraftCardCommonQuery , Company $company , array $cleanOverdraftIds , string $currencyName , string $date , int $year ):array 
	{
				$outstanding = 0 ;
				$room = 0 ;
				$interestAmount = 0 ;
				foreach($cleanOverdraftIds as $cleanOverdraftId){
						$totalRoomForCleanOverdraftId = DB::table('clean_overdraft_bank_statements')
						->where('clean_overdraft_bank_statements.company_id', $company->id)
						->where('date', '<=', $date)
						->join('clean_overdrafts', 'clean_overdraft_bank_statements.clean_overdraft_id', '=', 'clean_overdrafts.id')
						->where('clean_overdrafts.currency', '=', $currencyName)
						->where('clean_overdraft_id',$cleanOverdraftId)
						->orderByRaw('date desc , clean_overdraft_bank_statements.id desc')
						->first();
						$outstanding = $totalRoomForCleanOverdraftId ? $outstanding + $totalRoomForCleanOverdraftId->end_balance : $outstanding ;
						$room = $totalRoomForCleanOverdraftId ? $room + $totalRoomForCleanOverdraftId->room : $room ;
						$interestAmount = $interestAmount +  DB::table('clean_overdraft_bank_statements')
						->where('clean_overdraft_bank_statements.company_id', $company->id)
						->whereRaw('year(date) = '.$year)
						->join('clean_overdrafts', 'clean_overdraft_bank_statements.clean_overdraft_id', '=', 'clean_overdrafts.id')
						->where('clean_overdrafts.currency', '=', $currencyName)
						->where('clean_overdraft_id',$cleanOverdraftId)
						->orderByRaw('date desc , clean_overdraft_bank_statements.id desc')
						->sum('interest_amount');
				}
				$cleanOverdraftCardData[$currencyName] = [
					'limit' =>  $cleanOverdraftCardCommonQuery->sum('limit'),
					'outstanding' => $outstanding,
					'room' => $room ,
					'interest_amount'=>$interestAmount
				];
				return $cleanOverdraftCardData;
	}
	public function getType()
	{
		return __('Clean Overdraft');
	}
	public function getCurrencyFormatted()
	{
		return Str::upper($this->getCurrency());
	}
	
	/**
	 * * for rates
	 */
	
	public function rates()
	{
		return $this->hasMany(CleanOverdraftRate::class,'clean_overdraft_id','id');
	}

	/**
	 * Facility Renewal — Phase 1.
	 */
	public function termsHistories()
	{
		return $this->hasMany(CleanOverdraftTermsHistory::class,'clean_overdraft_id','id')->orderBy('effective_date');
	}

	/**
	 * Client-confirmed rule (2026-08-10): editing the facility's basic
	 * terms via the normal Edit screen is only allowed while it's still
	 * on its ORIGINAL, never-renewed terms. The moment a renewal exists,
	 * only Renew (and deleting that renewal) can change limit/rates/
	 * fees/settlement-days/end-date — Edit is locked, so it can never
	 * silently rewrite what a past chapter's terms actually were.
	 */
	public function hasRenewals():bool
	{
		return $this->termsHistories()->count() > 1;
	}
	public function canBeEdited():bool
	{
		return ! $this->hasRenewals();
	}

	/**
	 * Client-confirmed rule (2026-08-10): a facility can't be deleted
	 * while it still has real transactions against it — the user has to
	 * remove those first. Deliberately excludes the zero-amount system
	 * marker rows every facility gets automatically (the 'active-limit'
	 * placeholder, and scheduled-but-never-accrued 'interest' rows) —
	 * only rows where money actually moved (debit or credit > 0) count.
	 */
	/**
	 * * التسهيل ما ينفعش يتحذف طول ما فيه حركات فعلية عليه
	 *
	 * * الرصيد القائم الافتتاحي و فوايد اخر الشهر المشتقة منه مش حركات :
	 * * الاول رقم بيتكتب وقت اعداد التسهيل ، و التاني بيتولد و يتحسب
	 * * اوتوماتيك من ال trigger .. المستخدم عمره ما ادخل ولا واحد فيهم
	 *
	 * * نفس الخط اللي مرسوم بالفعل في ناحية الحساب البنكي : الرصيد
	 * * الافتتاحي لوحده مش حركة
	 *
	 * @see \App\Support\BankStatements\FacilityMovementRows
	 */
	public function hasAnyTransactions():bool
	{
		return \App\Support\BankStatements\FacilityMovementRows::onlyRealMovementIn(
			$this->cleanOverdraftBankStatements()
		)->exists();
	}

	/**
	 * Deletes the most recent renewal only (mirrors the existing rule
	 * that only the LAST rate-history entry is editable/deletable — same
	 * idea, applied to renewals). Blocked if any real transaction is
	 * dated on/after that renewal's effective date, since removing it
	 * would silently change what terms those transactions are judged
	 * against — the user must remove those transactions first, same
	 * reasoning as the whole-facility deletion guard above.
	 *
	 * On success, the facility's terms revert to whatever the
	 * next-most-recent chapter says — which becomes "Original" again
	 * (and Edit unlocks again) if that was the only renewal.
	 */
	public function deleteLatestRenewal():void
	{
		$latest = $this->getLatestTerms();

		if (!$latest || $latest->is_original) {
			throw new \InvalidArgumentException(__('There is no renewal to delete — this facility is still on its original terms.'));
		}

		$blockingTransactionsExist = $this->cleanOverdraftBankStatements()
			->where('date','>=',$latest->effective_date)
			->where(function($q){ $q->where('debit','>',0)->orWhere('credit','>',0); })
			->exists();

		if ($blockingTransactionsExist) {
			throw new \InvalidArgumentException(
				__('This renewal cannot be deleted because there are transactions dated on or after its effective date (:date). Please remove those transactions first.', ['date' => $latest->getEffectiveDateFormatted()])
			);
		}

		$latest->delete();

		$newLatest = $this->getLatestTerms();
		$this->update([
			'limit' => $newLatest->limit,
			'highest_debt_balance_rate' => $newLatest->highest_debt_balance_rate,
			'admin_fees_rate' => $newLatest->admin_fees_rate,
			'to_be_setteled_max_within_days' => $newLatest->to_be_setteled_max_within_days,
			'contract_end_date' => $newLatest->contract_end_date,
		]);

		$this->updateBankStatementsFromDate($newLatest->effective_date);
	}

	/**
	 * The single source of truth for "what were this facility's terms on
	 * a given date" — used by the renewal screen to show current values,
	 * and mirrored by the SQL trigger (see clean_overdraft_bank_statements.sql)
	 * so the database itself never trusts a blindly-passed 'limit' again.
	 */
	public function getTermsAsOfDate(string $date):?CleanOverdraftTermsHistory
	{
		/**
		 * ⚠️ REAL BUG FIXED HERE (client-flagged, 2026-08-10): the
		 * termsHistories() relationship has orderBy('effective_date')
		 * ASCENDING baked into its definition (see below). Chaining
		 * ->orderByDesc(...) on top of that doesn't replace it — Eloquent
		 * appends it as a second, lower-priority sort key, so the
		 * original ascending order still won — reorder() is required to
		 * actually clear it first.
		 */
		return $this->termsHistories()
			->where('effective_date','<=',$date)
			->reorder('effective_date','desc')
			->orderByDesc('id')
			->first();
	}

	public function getLatestTerms():?CleanOverdraftTermsHistory
	{
		return $this->termsHistories()->reorder('effective_date','desc')->orderByDesc('id')->first();
	}

	/**
	 * Bug fixed (client-flagged, 2026-08-10): the Facilities table's
	 * "Start Date" column was reading the account's true original
	 * contract_start_date even after a renewal — it should read the
	 * CURRENT chapter's own start date instead (the renewal's effective
	 * date, once one exists). Deliberately doesn't touch the actual
	 * contract_start_date column, which other logic elsewhere (e.g.
	 * scheduled end-of-month interest, cash dashboard date filters)
	 * still correctly relies on as the account's true, unchanging origin
	 * — this is a display-only fix.
	 */
	public function getCurrentChapterStartDateFormatted():?string
	{
		$latest = $this->getLatestTerms();
		$date = $latest?->effective_date ?: $this->contract_start_date;
		return $date ? Carbon::make($date)->format('d-m-Y') : null;
	}

	/**
	 * Records a renewal: a new dated row of terms. Anything left null
	 * simply carries forward the previous chapter's value (per the design
	 * brief — the user only enters what actually changed).
	 *
	 * Deliberately does NOT touch account_number, and does NOT create a
	 * new clean_overdrafts row — this facility keeps its identity. That's
	 * what makes the "account number already exists" validation moot for
	 * renewals: nothing new is ever inserted into clean_overdrafts.
	 *
	 * Also deliberately does NOT rewrite any existing bank statement rows'
	 * stored 'limit' column. Older rows keep whatever was true when they
	 * were created. Going forward, both the insert and update triggers
	 * look the correct value up from clean_overdraft_terms_histories by
	 * date — see the trigger SQL — so this single insert is enough to
	 * make every future (and correctly-dated backdated) calculation pick
	 * up the new terms automatically.
	 */
	/**
	 * ⚠️ REAL BUG FIXED HERE (client-flagged, 2026-08-11): brand-new
	 * facilities created after the renewal feature shipped never got
	 * this "chapter one" row — only facilities that already existed at
	 * migration time were backfilled with one. Without it, a facility's
	 * FIRST-EVER renewal becomes its ONLY terms-history row, so
	 * hasRenewals() (which checks for more than one row) wrongly reports
	 * "never renewed" even though a real renewal just happened — exactly
	 * what caused the Archived Facilities tab to stay empty and the
	 * Renew popup to show the wrong current end date. Called from
	 * store() now, immediately after a facility is first created.
	 */
	public function createOriginalTermsHistory():CleanOverdraftTermsHistory
	{
		return $this->termsHistories()->create([
			'company_id' => $this->company_id,
			'effective_date' => $this->contract_start_date,
			'limit' => $this->limit,
			'highest_debt_balance_rate' => $this->highest_debt_balance_rate,
			'admin_fees_rate' => $this->admin_fees_rate,
			'to_be_setteled_max_within_days' => $this->to_be_setteled_max_within_days,
			'contract_end_date' => $this->contract_end_date,
			'is_original' => true,
			'notes' => 'Original facility terms.',
		]);
	}

	public function renew(string $effectiveDate, array $newTerms, int $userId):CleanOverdraftTermsHistory
	{
		/**
		 * Defensive safety net (2026-08-11): if this facility somehow
		 * still has zero terms-history rows when Renew is used — an
		 * older facility created before the store() fix above, or one
		 * brought in through a different path like company data import —
		 * backfill its Original chapter right here rather than letting
		 * the same bug resurface. Cheap and harmless if it's already
		 * there (won't be called in that case).
		 */
		if ($this->termsHistories()->count() === 0) {
			$this->createOriginalTermsHistory();
		}

		$previous = $this->getLatestTerms();

		if ($previous && $effectiveDate <= $previous->effective_date) {
			throw new \InvalidArgumentException(
				__('A renewal date must be after the facility\'s most recent renewal date (:date).', ['date' => $previous->getEffectiveDateFormatted()])
			);
		}

		/**
		 * Real gap fixed here (client-flagged, 2026-08-10): the check
		 * above only guarded against two renewals landing on/before the
		 * same date — it never checked the renewal against the CURRENT
		 * CONTRACT PERIOD itself. Without this, a renewal could start
		 * while the existing contract still had months left to run.
		 * The comparison is always against the latest chapter's end
		 * date (originally the facility's own contract_end_date, and
		 * after any renewal, that renewal's new end date) — so this
		 * naturally cascades correctly across any number of renewals.
		 */
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

		$termsRow = $this->termsHistories()->create([
			'company_id' => $this->company_id,
			'effective_date' => $effectiveDate,
			'limit' => $newTerms['limit'] ?? $previous?->limit ?? $this->limit,
			'highest_debt_balance_rate' => $newTerms['highest_debt_balance_rate'] ?? $previous?->highest_debt_balance_rate ?? $this->highest_debt_balance_rate,
			'admin_fees_rate' => $newTerms['admin_fees_rate'] ?? $previous?->admin_fees_rate ?? $this->admin_fees_rate,
			'to_be_setteled_max_within_days' => $newTerms['to_be_setteled_max_within_days'] ?? $previous?->to_be_setteled_max_within_days ?? $this->to_be_setteled_max_within_days,
			'contract_end_date' => $newTerms['contract_end_date'] ?? $previous?->contract_end_date ?? $this->contract_end_date,
			'notes' => $newTerms['notes'] ?? null,
			'is_original' => false,
			'created_by' => $userId,
		]);

		// Keep the convenience columns on `clean_overdrafts` itself in
		// sync with "the latest chapter", since some existing code (e.g.
		// CleanOverdraftController::store()/update(), the dashboard
		// summary queries) still reads $cleanOverdraft->limit directly
		// for brand-new, present-day rows. The trigger is what makes
		// backdated/old rows correct regardless of what this column says.
		$this->update([
			'limit' => $termsRow->limit,
			'highest_debt_balance_rate' => $termsRow->highest_debt_balance_rate,
			'admin_fees_rate' => $termsRow->admin_fees_rate,
			'to_be_setteled_max_within_days' => $termsRow->to_be_setteled_max_within_days,
			'contract_end_date' => $termsRow->contract_end_date,
		]);

		// Force every already-posted statement on/after the renewal date
		// to recalculate, so anyone looking at the facility today sees
		// up-to-date room/interest immediately rather than only after the
		// next unrelated transaction happens to touch that date range.
		$this->updateBankStatementsFromDate($effectiveDate);

		return $termsRow;
	}
	public static function getBankStatementTableClassName():string 
	{
		return CleanOverdraftBankStatement::class ;
	}
	public static function rateFullClassName():string 
	{
		return CleanOverdraftRate::class ;
	}	
	public static function boot()
	{
		parent::boot();
		static::created(function(self $model){
			$model->storeRate(
				Request()->get('balance_date'),
				Request()->get('min_interest_rate'),
				Request()->get('margin_rate'),
				Request()->get('borrowing_rate'),
				Request()->get('interest_rate'),
				$model->company_id
			);
		});
		static::deleting(function(self $model){
			$model->rates()->delete();
			CleanOverdraftBankStatement::deleteButTriggerChangeOnLastElement($model->bankStatements);
		});
	}
	
	public function company()
	{
		return $this->belongsTo(Company::class,'company_id');
	}
	public function updateLimitRaw()
	{
		$data = [
			'type'=>'active-limit',
			'is_debit'=>1 ,
			'is_credit'=> 0 ,
			'priority'=>3,
			'company_id'=>$this->company->id ,
			'date'=>$this->contract_start_date ,
			'limit'=>$this->limit ,
			'debit'=>0,
			'credit'=>0,
			/**
			 * Client-directed rework (2026-08-11): relabeled from a bare
			 * '-' to something a Bank Statement reader can actually make
			 * sense of — this row isn't a transaction at all, it's the
			 * one-time marker that establishes the facility's starting
			 * limit (created once, at the original facility's setup;
			 * renewals never create another one of these).
			 */
			'comment_en'=>__('Facility Limit Set'),
			'comment_ar'=>__('Facility Limit Set',[],'ar'),
		];
		$row = $this->cleanOverdraftBankStatements()->where('type','active-limit')->first();
		if($row){
			$row->update($data);
		}else{
			$row = $this->cleanOverdraftBankStatements()->create($data);
		}
		/**
		 * Client-directed rework (2026-08-11): pin this row to the very
		 * start of its date rather than whenever it happened to be
		 * created or last saved. The Bank Statement sorts newest-first
		 * by full_date (date + time), so without this, this
		 * non-transaction marker could land ABOVE same-day real
		 * transactions purely by coincidence of when someone clicked
		 * Save — reading confusingly like "mid-day, the room reset to
		 * the full limit." Pinning it to 00:00:00 guarantees it always
		 * sorts as the earliest entry of its date, i.e. the true
		 * starting point of the account.
		 */
		$row->update(['full_date' => $row->date.' 00:00:00']);
		
	}
	public function isOverdraft():bool 
	{
		return true;
	}
	
	/**
	 * *  دا محدود بتاريخ بدايه ونهايه وبالتالي مش هيحصل حركات خارجهم
	 */
	public function handleEndOfMonthInterestForContractStatements(string $contractStartDate , string $contractEndDate , int $companyId)
	{
		$foreignKeyColumnName = self::generateForeignKeyFormModelName(); // clean_overdraft_id for clean_overdrafts for example
		$fullBankStatement = self::getBankStatementTableClassName();
		
		$contractStartDateAsCarbon = Carbon::make($contractStartDate);
		
		$isLastDayOfMonth = $contractStartDateAsCarbon->isSameDay($contractStartDateAsCarbon->endOfMonth());
		
		$contractEndDateAsCarbon= Carbon::make($contractEndDate);
		
		$dates = generateDatesBetweenTwoDatesWithoutOverflow($contractStartDateAsCarbon,$contractEndDateAsCarbon) ;
		$countDates = count($dates);
		$interestText = 'interest';
		$interestTypeText = 'end_of_month';
		$fullBankStatement::where('company_id',$companyId)->where('type',$interestText)->where($foreignKeyColumnName,$this->id)->where('interest_type',$interestTypeText)->where('date','>',$contractEndDate)->delete();
		/**
		 * ⚠️ REAL BUG FIXED HERE: النص التاني من نفس القاعدة كان ناقص.
		 * * السطر اللي فوق بيشيل الشهور اللي بقت بعد نهاية التعاقد ، لكن
		 * * لما تاريخ البداية يتأخر لقدام (يناير ← اغسطس مثلا) الشهور اللي
		 * * بقت قبل البداية كانت بتفضل قاعدة في كشف الحساب للابد
		 *
		 * * بنشيل الفاضي بس — اي صف اتملي بفايدة فعلية بيفضل ، لان ده فلوس
		 * * حقيقية اتحسبت مش placeholder
		 *
		 * * الحذف جماعي زي السطر اللي فوق : الصفوف دي كلها debit/credit = 0
		 * * فمفيش رصيد بيتغير و مفيش داعي لسلسلة اعادة الحساب
		 *
		 * @see \App\Support\BankStatements\GeneratedMonthEndInterestRows
		 */
		$staleBeforeStart = $fullBankStatement::where('company_id',$companyId)
			->where('type',$interestText)
			->where($foreignKeyColumnName,$this->id)
			->where('date','<',$contractStartDate);
		\App\Support\BankStatements\GeneratedMonthEndInterestRows::onlyUntouchedIn(
			$staleBeforeStart,
			(new $fullBankStatement)->getTable()
		)->delete();
		foreach($dates as $index => $dateAsString){
			if($index == 0 && $isLastDayOfMonth){
				continue;
			}
			$isLastLoop = $index == $countDates -1;
			$currentEndOfMonthDate = $isLastLoop ? Carbon::make($contractEndDate)->format('Y-m-d') : Carbon::make($dateAsString)->endOfMonth()->format('Y-m-d');
			$isExist = $fullBankStatement::where('company_id',$companyId)->where($foreignKeyColumnName,$this->id)->where('type',$interestText)->where('interest_type',$interestTypeText)->where('date',$currentEndOfMonthDate)->first();
			if(!$isExist){
				$data = [
				'company_id'=>$companyId,
				$foreignKeyColumnName=>$this->id ,
				'priority'=>1 ,
				'type'=>$interestText,
				'date'=>$currentEndOfMonthDate,
				'limit'=>$this->limit ,
				'credit'=>0 ,
				'interest_type'=>'end_of_month',
				'comment_en'=>__('End Of Month Interest'),
				'comment_ar'=>__('End Of Month Interest'),
			] ; 
			// if($this instanceof FinancialInstitutionAccount){
			// 	unset($data['priority']);
			// }
			 $fullBankStatement::create($data);
			}
			
		}
	}
	
	
	
}
