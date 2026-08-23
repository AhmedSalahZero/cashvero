<?php

namespace App\Models;

use App\Interfaces\Models\Interfaces\IHaveStatement;
use App\Traits\HasBankStatement;
use App\Traits\HasLastStatementAmount;
use App\Traits\HasOutstandingBreakdown;
use App\Traits\IsLockableBankAccount;
use App\Traits\IsOverdraft;
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
 * @property int|null $cd_or_td_account_type_id هو هو حساب سي دي ولا تي دي
 * @property int $cd_or_td_account_id الاي دي بتاع الحساب اللي اختارة وليكن 5
 * @property numeric|null $cd_or_td_lending_percentage
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
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\FullySecuredOverdraftBankStatement> $bankStatements
 * @property-read int|null $bank_statements_count
 * @property-read bool|null $bank_statements_exists
 * @property-read \App\Models\AccountType|null $cdOrTdAccountType
 * @property-read \App\Models\Company|null $company
 * @property-read \App\Models\FinancialInstitution|null $financialInstitution
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\FullySecuredOverdraftBankStatement> $fullySecuredOverdraftBankStatements
 * @property-read int|null $fully_secured_overdraft_bank_statements_count
 * @property-read bool|null $fully_secured_overdraft_bank_statements_exists
 * @property-read \App\Models\InternalMoneyTransfer|null $internalMoneyTransfer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LendingInformation> $lendingInformation
 * @property-read int|null $lending_information_count
 * @property-read bool|null $lending_information_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\OutstandingBreakdown> $outstandingBreakdowns
 * @property-read int|null $outstanding_breakdowns_count
 * @property-read bool|null $outstanding_breakdowns_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\FullySecuredOverdraftRate> $rates
 * @property-read int|null $rates_count
 * @property-read bool|null $rates_exists
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraft newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraft newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraft query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraft whereAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraft whereAdminFeesRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraft whereBalanceDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraft whereCdOrTdAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraft whereCdOrTdAccountTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraft whereCdOrTdLendingPercentage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraft whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraft whereContractEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraft whereContractStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraft whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraft whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraft whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraft whereFinancialInstitutionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraft whereHighestDebtBalanceRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraft whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraft whereLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraft whereOldestDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraft whereOriginUpdateRowIsDebit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraft whereOutstandingBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraft whereStartSettlementFromBankStatementDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraft whereToBeSetteledMaxWithinDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraft whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\FullySecuredOverdraft whereUpdatedBy($value)
 * @mixin \Eloquent
 */
class FullySecuredOverdraft extends Model implements IHaveStatement
{
    protected $guarded = ['id'];
	use HasOutstandingBreakdown , IsOverdraft ,HasBankStatement, HasLastStatementAmount, IsLockableBankAccount;
	
	public function fullySecuredOverdraftBankStatements()
	{
		return $this->hasMany(FullySecuredOverdraftBankStatement::class,'fully_secured_overdraft_id','id');
	}
	public function bankStatements()
	{
		return $this->hasMany(FullySecuredOverdraftBankStatement::class , 'fully_secured_overdraft_id','id');
	}	
	
	public static function generateForeignKeyFormModelName()
	{
		return 'fully_secured_overdraft_id';
	}	
	public static function getBankStatementTableName()
	{
		return 'fully_secured_overdraft_bank_statements';
	}
	public static function getWithdrawalTableName()
	{
		return 'fully_secured_overdraft_withdrawals';
	}
	public static function getBankStatementIdName():string 
	{
		return 'fully_secured_overdraft_bank_statement_id';
	}
	public static function getTableNameFormatted()
	{
		return __('Fully Secured Overdraft');
	}
	public function internalMoneyTransfer()
	{
		return $this->belongsTo(InternalMoneyTransfer::class,'internal_money_transfer_id','id');
	}	
	public function cdOrTdAccountType()
	{
		return $this->belongsTo(AccountType::class,'cd_or_td_account_type_id','id');
	}
	public function getCdOrTdAccountTypeId()
	{
		return $this->cdOrTdAccountType ? $this->cdOrTdAccountType->id : 0 ; 
	}
	
	public function getCdOrTdId()
	{
		return $this->cd_or_td_account_id;
	}

	/**
	 * Resolves the currently-linked CD/TD account's own amount, so a
	 * renewal's limit can be recalculated authoritatively server-side
	 * (amount × new percentage) rather than trusting whatever number
	 * the browser computed and sent.
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
	public  function getStatementTableName():string
	 {
		return 'fully_secured_overdraft_bank_statements';	
	}
	public  function getForeignKeyInStatementTable()
	{
		 return 'fully_secured_overdraft_id';
	}
	
	public static function getCommonQueryForCashDashboard(Company $company , string $currencyName , string $date )
	{
		return DB::table('fully_secured_overdrafts')
			->where('currency', '=', $currencyName)
			->where('company_id', $company->id)
			->where('contract_start_date', '<=', $date)
			->orderBy('fully_secured_overdrafts.id');
	}
	public static function hasAnyRecord(Company $company,string $currency)
	{
		return DB::table('fully_secured_overdrafts')->where('company_id',$company->id)
		->where('currency',$currency)
		->exists();
	}
	public static function getCashDashboardDataForFinancialInstitution(array &$totalRoomForEachFullySecuredOverdraftId,Company $company , array $fullySecuredOverdraftIds , string $currencyName , string $date , int $financialInstitutionBankId , &$totalFullySecuredOverdraftRoom  ):array 
	{
			
				foreach($fullySecuredOverdraftIds as $fullySecuredOverdraftId){
					$fullySecuredOverdraftStatement = DB::table('fully_secured_overdraft_bank_statements')
						->where('fully_secured_overdraft_bank_statements.company_id', $company->id)
						->where('date', '<=', $date)
						->join('fully_secured_overdrafts', 'fully_secured_overdraft_bank_statements.fully_secured_overdraft_id', '=', 'fully_secured_overdrafts.id')
						->where('fully_secured_overdrafts.currency', '=', $currencyName)
						->where('fully_secured_overdraft_id',$fullySecuredOverdraftId)
						->where('financial_institution_id',$financialInstitutionBankId)
						->orderByRaw('date desc , fully_secured_overdraft_bank_statements.id desc')
						->first();
						
						$fullySecuredOverdraftRoom = $fullySecuredOverdraftStatement ? $fullySecuredOverdraftStatement->room : 0 ;
						$totalFullySecuredOverdraftRoom += $fullySecuredOverdraftRoom ;
						$fullySecuredOverdraft = FullySecuredOverdraft::find($fullySecuredOverdraftId);
						$financialInstitution = FinancialInstitution::find($financialInstitutionBankId);
						$financialInstitutionName = $financialInstitution->getName();
						if($fullySecuredOverdraft->financial_institution_id ==$financialInstitution->id ){
							$totalRoomForEachFullySecuredOverdraftId[$currencyName][]  = [
								'item'=>$financialInstitutionName ,
								'available_room'=>$fullySecuredOverdraftRoom,
								'limit'=>$fullySecuredOverdraftStatement  ? $fullySecuredOverdraftStatement->limit : 0 ,
								'end_balance'=>$fullySecuredOverdraftStatement ?  $fullySecuredOverdraftStatement->end_balance : 0 
							] ;
						}
				}
				
				return $totalRoomForEachFullySecuredOverdraftId ;
				
	}
	
	public static function getCashDashboardDataForYear(array &$fullySecuredOverdraftCardData,Builder $fullySecuredOverdraftCardCommonQuery , Company $company , array $fullySecuredOverdraftIds , string $currencyName , string $date , int $year ):array 
	{
				$outstanding = 0 ;
				$room = 0 ;
				$interestAmount = 0 ;
				foreach($fullySecuredOverdraftIds as $fullySecuredOverdraftId){
						$totalRoomForFullySecuredOverdraftId = DB::table('fully_secured_overdraft_bank_statements')
						->where('fully_secured_overdraft_bank_statements.company_id', $company->id)
						->where('date', '<=', $date)
						->join('fully_secured_overdrafts', 'fully_secured_overdraft_bank_statements.fully_secured_overdraft_id', '=', 'fully_secured_overdrafts.id')
						->where('fully_secured_overdrafts.currency', '=', $currencyName)
						->where('fully_secured_overdraft_id',$fullySecuredOverdraftId)
						->orderByRaw('date desc , fully_secured_overdraft_bank_statements.id desc')
						->first();
						$outstanding = $totalRoomForFullySecuredOverdraftId ? $outstanding + $totalRoomForFullySecuredOverdraftId->end_balance : $outstanding ;
						$room = $totalRoomForFullySecuredOverdraftId ? $room + $totalRoomForFullySecuredOverdraftId->room : $room ;
						$interestAmount = $interestAmount +  DB::table('fully_secured_overdraft_bank_statements')
						->where('fully_secured_overdraft_bank_statements.company_id', $company->id)
						->whereRaw('year(date) = '.$year)
						->join('fully_secured_overdrafts', 'fully_secured_overdraft_bank_statements.fully_secured_overdraft_id', '=', 'fully_secured_overdrafts.id')
						->where('fully_secured_overdrafts.currency', '=', $currencyName)
						->where('fully_secured_overdraft_id',$fullySecuredOverdraftId)
						->orderByRaw('date desc , fully_secured_overdraft_bank_statements.id desc')
						->sum('interest_amount');
				}
				$fullySecuredOverdraftCardData[$currencyName] = [
					'limit' =>  $fullySecuredOverdraftCardCommonQuery->sum('limit'),
					'outstanding' => $outstanding,
					'room' => $room ,
					'interest_amount'=>$interestAmount
				];
				return $fullySecuredOverdraftCardData;
	}
	
	public function getType()
	{
		return __('Fully Secured Overdraft');
	}	
	public function getCurrencyFormatted()
	{
		return Str::upper($this->getCurrency());
	}
	
	public function rates()
	{
		return $this->hasMany(FullySecuredOverdraftRate::class,'fully_secured_overdraft_id','id');
	}
	public static function getBankStatementTableClassName():string 
	{
		return FullySecuredOverdraftBankStatement::class ;
	}		
	public static function rateFullClassName():string 
	{
		return FullySecuredOverdraftRate::class ;
	}
	public static function boot()
	{
		parent::boot();
		static::created(function(self $model){
			$model->storeRate(
				Request()->get('balance_date'),
				Request()->get('min_interest_rate',0),
				Request()->get('margin_rate'),
				Request()->get('borrowing_rate'),
				Request()->get('interest_rate'),
				$model->company_id
			);
		});
		static::deleting(function(self $model){
			$model->rates()->delete();
			FullySecuredOverdraftBankStatement::deleteButTriggerChangeOnLastElement($model->bankStatements);
		});
	}
	public function company()
	{
		return $this->belongsTo(Company::class,'company_id');
	}

	/**
	 * Facility Renewal — Phase 2. Mirrors CleanOverdraft's implementation
	 * exactly (including every bug found and fixed there — the correct
	 * reorder() usage, the missing-Original safety net in renew(), and
	 * the marker-row label/pinning below).
	 */
	public function termsHistories()
	{
		return $this->hasMany(FullySecuredOverdraftTermsHistory::class,'fully_secured_overdraft_id','id')->orderBy('effective_date');
	}

	public function getTermsAsOfDate(string $date):?FullySecuredOverdraftTermsHistory
	{
		return $this->termsHistories()
			->where('effective_date','<=',$date)
			->reorder('effective_date','desc')
			->orderByDesc('id')
			->first();
	}

	public function getLatestTerms():?FullySecuredOverdraftTermsHistory
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

	public function hasAnyTransactions():bool
	{
		return $this->fullySecuredOverdraftBankStatements()
			->where(function($q){ $q->where('debit','>',0)->orWhere('credit','>',0); })
			->exists();
	}

	public function createOriginalTermsHistory():FullySecuredOverdraftTermsHistory
	{
		return $this->termsHistories()->create([
			'company_id' => $this->company_id,
			'effective_date' => $this->contract_start_date,
			'limit' => $this->limit,
			'cd_or_td_lending_percentage' => $this->cd_or_td_lending_percentage,
			'highest_debt_balance_rate' => $this->highest_debt_balance_rate,
			'admin_fees_rate' => $this->admin_fees_rate,
			'to_be_setteled_max_within_days' => $this->to_be_setteled_max_within_days,
			'contract_end_date' => $this->contract_end_date,
			'is_original' => true,
			'notes' => 'Original facility terms.',
		]);
	}

	public function renew(string $effectiveDate, array $newTerms, int $userId):FullySecuredOverdraftTermsHistory
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

		/**
		 * Client-flagged (2026-08-11): a renewal can restate the CD/TD
		 * lending percentage, same as the original facility form — and
		 * the limit is recalculated from it authoritatively here
		 * (linked account's own amount × the new percentage), never
		 * trusted from whatever the browser computed and sent. If no
		 * new percentage is given, the previous chapter's percentage
		 * carries forward and the limit is recalculated from THAT,
		 * so the limit always stays in step with the percentage that's
		 * actually in force — it's never independently typed.
		 */
		$lendingPercentage = $newTerms['cd_or_td_lending_percentage'] ?? $previous?->cd_or_td_lending_percentage ?? $this->cd_or_td_lending_percentage;
		$linkedAmount = $this->getLinkedCdOrTdAmount();
		$calculatedLimit = round($linkedAmount * (float) $lendingPercentage / 100, 2);

		$termsRow = $this->termsHistories()->create([
			'company_id' => $this->company_id,
			'effective_date' => $effectiveDate,
			'limit' => $calculatedLimit,
			'cd_or_td_lending_percentage' => $lendingPercentage,
			'highest_debt_balance_rate' => $newTerms['highest_debt_balance_rate'] ?? $previous?->highest_debt_balance_rate ?? $this->highest_debt_balance_rate,
			'admin_fees_rate' => $newTerms['admin_fees_rate'] ?? $previous?->admin_fees_rate ?? $this->admin_fees_rate,
			'to_be_setteled_max_within_days' => $newTerms['to_be_setteled_max_within_days'] ?? $previous?->to_be_setteled_max_within_days ?? $this->to_be_setteled_max_within_days,
			'contract_end_date' => $newTerms['contract_end_date'] ?? $previous?->contract_end_date ?? $this->contract_end_date,
			'notes' => $newTerms['notes'] ?? null,
			'is_original' => false,
			'created_by' => $userId,
		]);

		$this->update([
			'limit' => $termsRow->limit,
			'cd_or_td_lending_percentage' => $termsRow->cd_or_td_lending_percentage,
			'highest_debt_balance_rate' => $termsRow->highest_debt_balance_rate,
			'admin_fees_rate' => $termsRow->admin_fees_rate,
			'to_be_setteled_max_within_days' => $termsRow->to_be_setteled_max_within_days,
			'contract_end_date' => $termsRow->contract_end_date,
		]);

		$this->updateBankStatementsFromDate($effectiveDate);

		return $termsRow;
	}

	public function deleteLatestRenewal():void
	{
		$latest = $this->getLatestTerms();

		if (!$latest || $latest->is_original) {
			throw new \InvalidArgumentException(__('There is no renewal to delete — this facility is still on its original terms.'));
		}

		$blockingTransactionsExist = $this->fullySecuredOverdraftBankStatements()
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
			'cd_or_td_lending_percentage' => $newLatest->cd_or_td_lending_percentage,
			'highest_debt_balance_rate' => $newLatest->highest_debt_balance_rate,
			'admin_fees_rate' => $newLatest->admin_fees_rate,
			'to_be_setteled_max_within_days' => $newLatest->to_be_setteled_max_within_days,
			'contract_end_date' => $newLatest->contract_end_date,
		]);

		$this->updateBankStatementsFromDate($newLatest->effective_date);
	}

	/**
	 * Client-requested (2026-08-11): End Of Month Interest, wired up to
	 * match Clean Overdraft's exact mechanism. This model's trigger
	 * already had the calculation logic for `interest_type =
	 * 'end_of_month'` rows — nothing ever actually created those rows
	 * in the first place. This generates one scheduled placeholder row
	 * per month-end within the contract period; the trigger fills in
	 * the real amount from the account's actual balance history the
	 * moment each row is inserted. Verbatim copy of
	 * CleanOverdraft::handleEndOfMonthInterestForContractStatements() —
	 * it's already generic (built entirely from this model's own
	 * generateForeignKeyFormModelName()/getBankStatementTableClassName()),
	 * so nothing facility-specific needed changing.
	 */
	public function handleEndOfMonthInterestForContractStatements(string $contractStartDate , string $contractEndDate , int $companyId)
	{
		$foreignKeyColumnName = self::generateForeignKeyFormModelName();
		$fullBankStatement = self::getBankStatementTableClassName();

		$contractStartDateAsCarbon = \Carbon\Carbon::make($contractStartDate);

		$isLastDayOfMonth = $contractStartDateAsCarbon->isSameDay($contractStartDateAsCarbon->endOfMonth());

		$contractEndDateAsCarbon= \Carbon\Carbon::make($contractEndDate);

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
			$currentEndOfMonthDate = $isLastLoop ? \Carbon\Carbon::make($contractEndDate)->format('Y-m-d') : \Carbon\Carbon::make($dateAsString)->endOfMonth()->format('Y-m-d');
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
				 $fullBankStatement::create($data);
			}

		}
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
			'comment_en'=>__('Facility Limit Set'),
			'comment_ar'=>__('Facility Limit Set',[],'ar'),
		];
		$row = $this->fullySecuredOverdraftBankStatements()->where('type','active-limit')->first();
		if($row){
			$row->update($data);
		}else{
			$row = $this->fullySecuredOverdraftBankStatements()->create($data);
		}
		$row->update(['full_date' => $row->date.' 00:00:00']);
	}
	public function isOverdraft():bool 
	{
		return true;
	}
	
}
