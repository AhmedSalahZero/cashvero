<?php

namespace App\Models;

use App\Interfaces\Models\Interfaces\IHaveStatement;
use App\Support\LockableAccountSelector;
use App\Traits\HasBankStatement;
use App\Traits\HasLastStatementAmount;
use App\Traits\HasOutstandingBreakdown;
use App\Traits\IsLockableBankAccount;
use App\Traits\IsOverdraft;
use App\Traits\Models\HasAccumulatedLimit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
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
 * @property numeric $max_lending_limit_per_contract
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $to_be_setteled_max_within_days
 * @property string|null $start_settlement_from_bank_statement_date
 * @property string|null $oldest_date
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OverdraftAgainstAssignmentOfContractBankStatement> $bankStatements
 * @property-read int|null $bank_statements_count
 * @property-read bool|null $bank_statements_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Contract> $contracts
 * @property-read int|null $contracts_count
 * @property-read bool|null $contracts_exists
 * @property-read \App\Models\FinancialInstitution|null $financialInstitution
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LendingInformationAgainstAssignmentOfContract> $lendingInformation
 * @property-read int|null $lending_information_count
 * @property-read bool|null $lending_information_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\OutstandingBreakdown> $outstandingBreakdowns
 * @property-read int|null $outstanding_breakdowns_count
 * @property-read bool|null $outstanding_breakdowns_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OverdraftAgainstAssignmentOfContractLimit> $overdraftAgainstAssignmentOfContractBankLimits
 * @property-read int|null $overdraft_against_assignment_of_contract_bank_limits_count
 * @property-read bool|null $overdraft_against_assignment_of_contract_bank_limits_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OverdraftAgainstAssignmentOfContractBankStatement> $overdraftAgainstAssignmentOfContractBankStatements
 * @property-read int|null $overdraft_against_assignment_of_contract_bank_statements_count
 * @property-read bool|null $overdraft_against_assignment_of_contract_bank_statements_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OverdraftAgainstAssignmentOfContractRate> $rates
 * @property-read int|null $rates_count
 * @property-read bool|null $rates_exists
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContract newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContract newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContract query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContract whereAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContract whereAdminFeesRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContract whereBalanceDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContract whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContract whereContractEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContract whereContractStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContract whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContract whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContract whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContract whereFinancialInstitutionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContract whereHighestDebtBalanceRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContract whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContract whereLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContract whereMaxLendingLimitPerContract($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContract whereOldestDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContract whereOutstandingBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContract whereStartSettlementFromBankStatementDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContract whereToBeSetteledMaxWithinDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContract whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstAssignmentOfContract whereUpdatedBy($value)
 * @mixin \Eloquent
 */
class OverdraftAgainstAssignmentOfContract extends Model implements IHaveStatement
{
    protected $guarded = ['id'];
	
	use HasOutstandingBreakdown , IsOverdraft  , HasBankStatement, HasAccumulatedLimit,HasLastStatementAmount, IsLockableBankAccount;
	public function rates()
	{
		return $this->hasMany(OverdraftAgainstAssignmentOfContractRate::class,'overdraft_against_assignment_of_contract_id','id');
	}
	
	public static function rateFullClassName():string 
	{
		return OverdraftAgainstAssignmentOfContractRate::class ;
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
		static::updated(function(OverdraftAgainstAssignmentOfContract $overdraftAgainstAssignmentOfContract){
			$overdraftAgainstAssignmentOfContract->triggerChangeOnContracts();
		});
		static::deleting(function(self $model){
			$model->rates()->delete();
			OverdraftAgainstAssignmentOfContractBankStatement::deleteButTriggerChangeOnLastElement($model->bankStatements);
		});
		static::deleted(function(OverdraftAgainstAssignmentOfContract $overdraftAgainstAssignmentOfContract){
			$overdraftAgainstAssignmentOfContract->overdraftAgainstAssignmentOfContractBankStatements->each(function($overdraftAgainstAssignmentOfContractBankStatement){
				$overdraftAgainstAssignmentOfContractBankStatement->delete();
			});
			$overdraftAgainstAssignmentOfContract->overdraftAgainstAssignmentOfContractBankLimits->each(function($overdraftAgainstAssignmentOfContractBankLimit){
				$overdraftAgainstAssignmentOfContractBankLimit->delete();
			});
		});
	}
	public function overdraftAgainstAssignmentOfContractBankLimits()
	{
		return $this->hasMany(OverdraftAgainstAssignmentOfContractLimit::class,'overdraft_against_assignment_of_contract_id','id');
	}
	public function overdraftAgainstAssignmentOfContractBankStatements()
	{
		return $this->hasMany(OverdraftAgainstAssignmentOfContractBankStatement::class,'overdraft_against_assignment_of_contract_id','id');
	}
	public function bankStatements()
	{
		return $this->hasMany(OverdraftAgainstAssignmentOfContractBankStatement::class , 'overdraft_against_assignment_of_contract_id','id');
	}	
	public function lendingInformation():HasMany
	{
		return $this->hasMany(LendingInformationAgainstAssignmentOfContract::class , 'overdraft_against_assignment_of_contract_id','id');
	}
	public static function generateForeignKeyFormModelName():string 
	{
		return 'overdraft_against_assignment_of_contract_id';
	}	
	public static function getBankStatementTableName():string 
	{
		return 'overdraft_against_assignment_of_contract_bank_statements';
	}
	public static function getWithdrawalTableName():string 
	{
		return 'overdraft_against_assignment_of_contract_withdrawals';
	}
	public static function getBankStatementIdName():string 
	{
		return 'overdraft_against_assignment_of_contract_bank_statement_id';
	}
	public static function getTableNameFormatted()
	{
		return __('Overdraft Against Assignment Of Contract');
	}
	public  function getStatementTableName():string
	 {
		return 'overdraft_against_assignment_of_contract_bank_statements';	
	}
	public  function getForeignKeyInStatementTable()
	{
		 return 'overdraft_against_assignment_of_contract_id';
	}
	public function contracts():HasMany
	{
		return $this->hasMany(Contract::class , 'overdraft_against_assignment_of_contract_id','id');
	}
	
	
	public function triggerChangeOnContracts()
	{
		
		$this->contracts->each(function(Contract $contract){
			$contract->update([
				'updated_at'=>now()
			]);
		
	});
	}
	public static function getAllAccountNumberForCurrency($companyId , $currencyName,$financialInstitutionId,$keyName='account_number'):array
	{
		$accounts = [];
		$overdraftAgainstAssignmentOfContracts = self::where('company_id',$companyId)->where('currency',$currencyName)
		->where('financial_institution_id',$financialInstitutionId)->where('is_active', 1)->get();
		foreach($overdraftAgainstAssignmentOfContracts as $overdraftAgainstAssignmentOfContract){
			$limitStatement = $overdraftAgainstAssignmentOfContract->overdraftAgainstAssignmentOfContractBankLimits->sortByDesc('full_date')->first() ;

			if(($limitStatement && $limitStatement->accumulated_limit >0) || in_array('bank-statement',Request()->segments())){
				$accounts[$overdraftAgainstAssignmentOfContract->{$keyName}] = $overdraftAgainstAssignmentOfContract->account_number;
			}
		}

		return LockableAccountSelector::mergeSelectedLockedAccount(
			$accounts,
			static::class,
			$companyId,
			$currencyName,
			$financialInstitutionId,
			$keyName
		);
	}	
	public function getType()
	{
		return __('Overdraft Against Contract Assignment');
	}	
	public function getCurrencyFormatted()
	{
		return Str::upper($this->getCurrency());
	}
	public static function getBankStatementTableClassName():string 
	{
		return OverdraftAgainstAssignmentOfContractBankStatement::class ;
	}
	public function getSmallestLimitTableFullDate()
	{
		return $this->overdraftAgainstAssignmentOfContractBankLimits->min('full_date');
	}	
	public static function hasAnyRecord(Company $company,string $currency)
{
	return DB::table('overdraft_against_assignment_of_contracts')->where('company_id',$company->id)->where('currency',$currency)->exists();
}
public static function getCommonQueryForCashDashboard(Company $company , string $currencyName , string $date )
{
	return DB::table('overdraft_against_assignment_of_contracts')
		->where('currency', '=', $currencyName)
		->where('company_id', $company->id)
		->where('contract_start_date', '<=', $date)
		->orderBy('overdraft_against_assignment_of_contracts.id');
}


public static function getCashDashboardDataForFinancialInstitution(array &$totalRoomForEachOverdraftAgainstAssignmentOfContractId,Company $company , array $overdraftAgainstAssignmentOfContractIds , string $currencyName , string $date , int $financialInstitutionBankId , &$totalOverdraftAgainstAssignmentOfContractRoom  ):array 
{
		
			foreach($overdraftAgainstAssignmentOfContractIds as $overdraftAgainstAssignmentOfContractId){
				$overdraftAgainstAssignmentOfContractStatement = DB::table('overdraft_against_assignment_of_contract_bank_statements')
					->where('overdraft_against_assignment_of_contract_bank_statements.company_id', $company->id)
					->where('date', '<=', $date)
					->join('overdraft_against_assignment_of_contracts', 'overdraft_against_assignment_of_contract_bank_statements.overdraft_against_assignment_of_contract_id', '=', 'overdraft_against_assignment_of_contracts.id')
					->where('overdraft_against_assignment_of_contracts.currency', '=', $currencyName)
					->where('overdraft_against_assignment_of_contract_id',$overdraftAgainstAssignmentOfContractId)
					->where('financial_institution_id',$financialInstitutionBankId)
					->orderByRaw('date desc , overdraft_against_assignment_of_contract_bank_statements.id desc')
					->first();
					
					$overdraftAgainstAssignmentOfContractRoom = $overdraftAgainstAssignmentOfContractStatement ? $overdraftAgainstAssignmentOfContractStatement->room : 0 ;
					$totalOverdraftAgainstAssignmentOfContractRoom += $overdraftAgainstAssignmentOfContractRoom ;
					$overdraftAgainstAssignmentOfContract = OverdraftAgainstAssignmentOfContract::find($overdraftAgainstAssignmentOfContractId);
					$financialInstitution = FinancialInstitution::find($financialInstitutionBankId);
					$financialInstitutionName = $financialInstitution->getName();
					if($overdraftAgainstAssignmentOfContract->financial_institution_id ==$financialInstitution->id ){
						$totalRoomForEachOverdraftAgainstAssignmentOfContractId[$currencyName][]  = [
							'item'=>$financialInstitutionName ,
							'available_room'=>$overdraftAgainstAssignmentOfContractRoom,
							'limit'=>$overdraftAgainstAssignmentOfContractStatement  ? $overdraftAgainstAssignmentOfContractStatement->limit : 0 ,
							'end_balance'=>$overdraftAgainstAssignmentOfContractStatement ?  $overdraftAgainstAssignmentOfContractStatement->end_balance : 0 
						] ;
					}
			}
			
			return $totalRoomForEachOverdraftAgainstAssignmentOfContractId ;
			
}


public static function getCashDashboardDataForYear(array &$overdraftAgainstAssignmentOfContractCardData,Builder $overdraftAgainstAssignmentOfContractCardCommonQuery , Company $company , array $overdraftAgainstAssignmentOfContractIds , string $currencyName , string $date , int $year ):array 
{
			$outstanding = 0 ;
			$room = 0 ;
			$interestAmount = 0 ;
			foreach($overdraftAgainstAssignmentOfContractIds as $overdraftAgainstAssignmentOfContractId){
					$totalRoomForOverdraftAgainstAssignmentOfContractId = DB::table('overdraft_against_assignment_of_contract_bank_statements')
					->where('overdraft_against_assignment_of_contract_bank_statements.company_id', $company->id)
					->where('date', '<=', $date)
					->join('overdraft_against_assignment_of_contracts', 'overdraft_against_assignment_of_contract_bank_statements.overdraft_against_assignment_of_contract_id', '=', 'overdraft_against_assignment_of_contracts.id')
					->where('overdraft_against_assignment_of_contracts.currency', '=', $currencyName)
					->where('overdraft_against_assignment_of_contract_id',$overdraftAgainstAssignmentOfContractId)
					->orderByRaw('date desc , overdraft_against_assignment_of_contract_bank_statements.id desc')
					->first();
					$outstanding = $totalRoomForOverdraftAgainstAssignmentOfContractId ? $outstanding + $totalRoomForOverdraftAgainstAssignmentOfContractId->end_balance : $outstanding ;
					$room = $totalRoomForOverdraftAgainstAssignmentOfContractId ? $room + $totalRoomForOverdraftAgainstAssignmentOfContractId->room : $room ;
					$interestAmount = $interestAmount +  DB::table('overdraft_against_assignment_of_contract_bank_statements')
					->where('overdraft_against_assignment_of_contract_bank_statements.company_id', $company->id)
					->whereRaw('year(date) = '.$year)
					->join('overdraft_against_assignment_of_contracts', 'overdraft_against_assignment_of_contract_bank_statements.overdraft_against_assignment_of_contract_id', '=', 'overdraft_against_assignment_of_contracts.id')
					->where('overdraft_against_assignment_of_contracts.currency', '=', $currencyName)
					->where('overdraft_against_assignment_of_contract_id',$overdraftAgainstAssignmentOfContractId)
					->orderByRaw('date desc , overdraft_against_assignment_of_contract_bank_statements.id desc')
					->sum('interest_amount');
			}
			$overdraftAgainstAssignmentOfContractCardData[$currencyName] = [
				'limit' =>  $overdraftAgainstAssignmentOfContractCardCommonQuery->sum('limit'),
				'outstanding' => $outstanding,
				'room' => $room ,
				'interest_amount'=>$interestAmount
			];
			return $overdraftAgainstAssignmentOfContractCardData;
}
	/**
	 * Client-requested (2026-08-11): End Of Month Interest, wired up to
	 * match Clean Overdraft's exact mechanism — this model's trigger
	 * already had the calculation logic for `interest_type =
	 * 'end_of_month'` rows, nothing ever actually created those rows.
	 * See FullySecuredOverdraft's copy of this method for the full
	 * explanation; this is the same verbatim mechanism.
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

public function isOverdraft():bool 
	{
		return true;
	}

	/**
	 * Facility Renewal — Phase 4. Simpler than Commercial Paper's: no
	 * tier schedule to tag — this facility's lending rate is already
	 * locked per-contract at assignment time (see the standalone
	 * rate-lookup fix), so a renewal here only ever changes the
	 * facility's own overall terms.
	 */
	public function termsHistories()
	{
		return $this->hasMany(OverdraftAgainstAssignmentOfContractTermsHistory::class,'overdraft_against_assignment_of_contract_id','id')->orderBy('effective_date');
	}

	public function getTermsAsOfDate(string $date):?OverdraftAgainstAssignmentOfContractTermsHistory
	{
		return $this->termsHistories()
			->where('effective_date','<=',$date)
			->reorder('effective_date','desc')
			->orderByDesc('id')
			->first();
	}

	public function getLatestTerms():?OverdraftAgainstAssignmentOfContractTermsHistory
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
	 * Same idea as Commercial Paper: a "transaction" here means a
	 * contract has ever been assigned to this facility (a row exists
	 * in the limits ledger) — not a debit/credit column check.
	 */
	public function hasAnyTransactions():bool
	{
		return DB::table('overdraft_against_assignment_of_contract_limits')
			->where('overdraft_against_assignment_of_contract_id', $this->id)
			->exists();
	}

	public function createOriginalTermsHistory():OverdraftAgainstAssignmentOfContractTermsHistory
	{
		return $this->termsHistories()->create([
			'company_id' => $this->company_id,
			'effective_date' => $this->contract_start_date,
			'limit' => $this->limit,
			'max_lending_limit_per_contract' => $this->max_lending_limit_per_contract,
			'highest_debt_balance_rate' => $this->highest_debt_balance_rate,
			'admin_fees_rate' => $this->admin_fees_rate,
			'to_be_setteled_max_within_days' => $this->to_be_setteled_max_within_days,
			'contract_end_date' => $this->contract_end_date,
			'is_original' => true,
			'notes' => 'Original facility terms.',
		]);
	}

	public function renew(string $effectiveDate, array $newTerms, int $userId):OverdraftAgainstAssignmentOfContractTermsHistory
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

		$termsRow = $this->termsHistories()->create([
			'company_id' => $this->company_id,
			'effective_date' => $effectiveDate,
			'limit' => $newTerms['limit'] ?? $previous?->limit ?? $this->limit,
			'max_lending_limit_per_contract' => $newTerms['max_lending_limit_per_contract'] ?? $previous?->max_lending_limit_per_contract ?? $this->max_lending_limit_per_contract,
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
			'max_lending_limit_per_contract' => $termsRow->max_lending_limit_per_contract,
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

		$blockingContracts = DB::table('overdraft_against_assignment_of_contract_limits')
			->where('overdraft_against_assignment_of_contract_id', $this->id)
			->where('full_date', '>=', $latest->effective_date)
			->exists();

		if ($blockingContracts) {
			throw new \InvalidArgumentException(
				__('This renewal cannot be deleted because contracts have already been assigned on or after its effective date (:date). Please remove those first.', ['date' => $latest->getEffectiveDateFormatted()])
			);
		}

		$latest->delete();

		$newLatest = $this->getLatestTerms();
		$this->update([
			'limit' => $newLatest->limit,
			'max_lending_limit_per_contract' => $newLatest->max_lending_limit_per_contract,
			'highest_debt_balance_rate' => $newLatest->highest_debt_balance_rate,
			'admin_fees_rate' => $newLatest->admin_fees_rate,
			'to_be_setteled_max_within_days' => $newLatest->to_be_setteled_max_within_days,
			'contract_end_date' => $newLatest->contract_end_date,
		]);

		$this->updateBankStatementsFromDate($newLatest->effective_date);
	}
	
}
