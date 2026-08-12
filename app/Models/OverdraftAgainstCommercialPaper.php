<?php

namespace App\Models;

use App\Interfaces\Models\Interfaces\IHaveStatement;
use App\Support\LockableAccountSelector;
use App\Traits\HasBankStatement;
use App\Traits\HasLastStatementAmount;
use App\Traits\HasOutstandingBreakdown;
use App\Traits\IsLockableBankAccount;
use App\Traits\IsOverdraft;
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
 * @property numeric $max_lending_limit_per_customer
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $to_be_setteled_max_within_days
 * @property string|null $start_settlement_from_bank_statement_date
 * @property string|null $oldest_date
 * @property int|null $origin_update_row_is_debit دلوقت احنا لما بنحدث وليكن ماني ريسيفد .. عايز نعرف ان الرو الاصلي اللي عدلناه كان ماني ريسيفد
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OverdraftAgainstCommercialPaperBankStatement> $bankStatements
 * @property-read int|null $bank_statements_count
 * @property-read bool|null $bank_statements_exists
 * @property-read \App\Models\FinancialInstitution|null $financialInstitution
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LendingInformation> $lendingInformation
 * @property-read int|null $lending_information_count
 * @property-read bool|null $lending_information_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\OutstandingBreakdown> $outstandingBreakdowns
 * @property-read int|null $outstanding_breakdowns_count
 * @property-read bool|null $outstanding_breakdowns_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OverdraftAgainstCommercialPaperLimit> $overdraftAgainstCommercialPaperBankLimits
 * @property-read int|null $overdraft_against_commercial_paper_bank_limits_count
 * @property-read bool|null $overdraft_against_commercial_paper_bank_limits_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OverdraftAgainstCommercialPaperBankStatement> $overdraftAgainstCommercialPaperBankStatements
 * @property-read int|null $overdraft_against_commercial_paper_bank_statements_count
 * @property-read bool|null $overdraft_against_commercial_paper_bank_statements_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OverdraftAgainstCommercialPaperRate> $rates
 * @property-read int|null $rates_count
 * @property-read bool|null $rates_exists
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaper newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaper newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaper query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaper whereAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaper whereAdminFeesRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaper whereBalanceDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaper whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaper whereContractEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaper whereContractStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaper whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaper whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaper whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaper whereFinancialInstitutionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaper whereHighestDebtBalanceRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaper whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaper whereLimit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaper whereMaxLendingLimitPerCustomer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaper whereOldestDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaper whereOriginUpdateRowIsDebit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaper whereOutstandingBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaper whereStartSettlementFromBankStatementDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaper whereToBeSetteledMaxWithinDays($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaper whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OverdraftAgainstCommercialPaper whereUpdatedBy($value)
 * @mixin \Eloquent
 */
class OverdraftAgainstCommercialPaper extends Model implements IHaveStatement
{
    protected $guarded = ['id'];
	
	use HasOutstandingBreakdown , IsOverdraft , HasBankStatement , HasLastStatementAmount, IsLockableBankAccount;
	
	public function overdraftAgainstCommercialPaperBankStatements()
	{
		return $this->hasMany(OverdraftAgainstCommercialPaperBankStatement::class,'overdraft_against_commercial_paper_id','id');
	}
	public function bankStatements()
	{
		return $this->hasMany(OverdraftAgainstCommercialPaperBankStatement::class , 'overdraft_against_commercial_paper_id','id');
	}	
	public function lendingInformation():HasMany
	{
		return $this->hasMany(LendingInformation::class , 'overdraft_against_commercial_paper_id','id');
	}
	public static function generateForeignKeyFormModelName():string 
	{
		return 'overdraft_against_commercial_paper_id';
	}	
	public static function getBankStatementTableName():string 
	{
		return 'overdraft_against_commercial_paper_bank_statements';
	}
	public static function getWithdrawalTableName():string 
	{
		return 'overdraft_against_commercial_paper_withdrawals';
	}
	public static function getBankStatementIdName():string 
	{
		return 'overdraft_against_commercial_paper_bank_statement_id';
	}
	public static function getTableNameFormatted()
	{
		return __('Overdraft Against Commercial Paper');
	}
	public  function getStatementTableName():string
	 {
		return 'overdraft_against_commercial_paper_bank_statements';	
	}
	public  function getForeignKeyInStatementTable()
	{
		 return 'overdraft_against_commercial_paper_id';
	}
	
	
public static function getCommonQueryForCashDashboard(Company $company , string $currencyName , string $date )
{
	return DB::table('overdraft_against_commercial_papers')
		->where('currency', '=', $currencyName)
		->where('company_id', $company->id)
		->where('contract_start_date', '<=', $date)
		->orderBy('overdraft_against_commercial_papers.id');
}
public static function hasAnyRecord(Company $company,string $currency)
{
	return DB::table('overdraft_against_commercial_papers')->where('company_id',$company->id)->where('currency',$currency)->exists();
}
public static function getCashDashboardDataForFinancialInstitution(array &$totalRoomForEachOverdraftAgainstCommercialPaperId,Company $company , array $overdraftAgainstCommercialPaperIds , string $currencyName , string $date , int $financialInstitutionBankId , &$totalOverdraftAgainstCommercialPaperRoom  ):array 
{
		
			foreach($overdraftAgainstCommercialPaperIds as $overdraftAgainstCommercialPaperId){
				$overdraftAgainstCommercialPaperStatement = DB::table('overdraft_against_commercial_paper_bank_statements')
					->where('overdraft_against_commercial_paper_bank_statements.company_id', $company->id)
					->where('date', '<=', $date)
					->join('overdraft_against_commercial_papers', 'overdraft_against_commercial_paper_bank_statements.overdraft_against_commercial_paper_id', '=', 'overdraft_against_commercial_papers.id')
					->where('overdraft_against_commercial_papers.currency', '=', $currencyName)
					->where('overdraft_against_commercial_paper_id',$overdraftAgainstCommercialPaperId)
					->where('financial_institution_id',$financialInstitutionBankId)
					->orderByRaw('date desc , overdraft_against_commercial_paper_bank_statements.id desc')
					->first();
					
					$overdraftAgainstCommercialPaperRoom = $overdraftAgainstCommercialPaperStatement ? $overdraftAgainstCommercialPaperStatement->room : 0 ;
					$totalOverdraftAgainstCommercialPaperRoom += $overdraftAgainstCommercialPaperRoom ;
					$overdraftAgainstCommercialPaper = OverdraftAgainstCommercialPaper::find($overdraftAgainstCommercialPaperId);
					$financialInstitution = FinancialInstitution::find($financialInstitutionBankId);
					$financialInstitutionName = $financialInstitution->getName();
					if($overdraftAgainstCommercialPaper->financial_institution_id ==$financialInstitution->id ){
						$totalRoomForEachOverdraftAgainstCommercialPaperId[$currencyName][]  = [
							'item'=>$financialInstitutionName ,
							'available_room'=>$overdraftAgainstCommercialPaperRoom,
							'limit'=>$overdraftAgainstCommercialPaperStatement  ? $overdraftAgainstCommercialPaperStatement->limit : 0 ,
							'end_balance'=>$overdraftAgainstCommercialPaperStatement ?  $overdraftAgainstCommercialPaperStatement->end_balance : 0 
						] ;
					}
			}
			
			return $totalRoomForEachOverdraftAgainstCommercialPaperId ;
			
}

public static function getCashDashboardDataForYear(array &$overdraftAgainstCommercialPaperCardData,Builder $overdraftAgainstCommercialPaperCardCommonQuery , Company $company , array $overdraftAgainstCommercialPaperIds , string $currencyName , string $date , int $year ):array 
{
			$outstanding = 0 ;
			$room = 0 ;
			$interestAmount = 0 ;
			foreach($overdraftAgainstCommercialPaperIds as $overdraftAgainstCommercialPaperId){
					$totalRoomForOverdraftAgainstCommercialPaperId = DB::table('overdraft_against_commercial_paper_bank_statements')
					->where('overdraft_against_commercial_paper_bank_statements.company_id', $company->id)
					->where('date', '<=', $date)
					->join('overdraft_against_commercial_papers', 'overdraft_against_commercial_paper_bank_statements.overdraft_against_commercial_paper_id', '=', 'overdraft_against_commercial_papers.id')
					->where('overdraft_against_commercial_papers.currency', '=', $currencyName)
					->where('overdraft_against_commercial_paper_id',$overdraftAgainstCommercialPaperId)
					->orderByRaw('date desc , overdraft_against_commercial_paper_bank_statements.id desc')
					->first();
					$outstanding = $totalRoomForOverdraftAgainstCommercialPaperId ? $outstanding + $totalRoomForOverdraftAgainstCommercialPaperId->end_balance : $outstanding ;
					$room = $totalRoomForOverdraftAgainstCommercialPaperId ? $room + $totalRoomForOverdraftAgainstCommercialPaperId->room : $room ;
					$interestAmount = $interestAmount +  DB::table('overdraft_against_commercial_paper_bank_statements')
					->where('overdraft_against_commercial_paper_bank_statements.company_id', $company->id)
					->whereRaw('year(date) = '.$year)
					->join('overdraft_against_commercial_papers', 'overdraft_against_commercial_paper_bank_statements.overdraft_against_commercial_paper_id', '=', 'overdraft_against_commercial_papers.id')
					->where('overdraft_against_commercial_papers.currency', '=', $currencyName)
					->where('overdraft_against_commercial_paper_id',$overdraftAgainstCommercialPaperId)
					->orderByRaw('date desc , overdraft_against_commercial_paper_bank_statements.id desc')
					->sum('interest_amount');
			}
			$overdraftAgainstCommercialPaperCardData[$currencyName] = [
				'limit' =>  $overdraftAgainstCommercialPaperCardCommonQuery->sum('limit'),
				'outstanding' => $outstanding,
				'room' => $room ,
				'interest_amount'=>$interestAmount
			];
			return $overdraftAgainstCommercialPaperCardData;
}
public function overdraftAgainstCommercialPaperBankLimits()
{
	return $this->hasMany(OverdraftAgainstCommercialPaperLimit::class,'overdraft_against_commercial_paper_id','id');
}
	public static function getAllAccountNumberForCurrency($companyId , $currencyName,$financialInstitutionId,$keyName = 'account_number'):array
	{
		$accounts = [];
		$overdraftAgainstCommercialPapers = self::where('company_id',$companyId)->where('currency',$currencyName)
		->where('financial_institution_id',$financialInstitutionId)->where('is_active', 1)->get();
		if(in_array('money-received',Request()->segments())){
			$accounts = $overdraftAgainstCommercialPapers->pluck('account_number',$keyName)->toArray();

			return LockableAccountSelector::mergeSelectedLockedAccount(
				$accounts,
				static::class,
				$companyId,
				$currencyName,
				$financialInstitutionId,
				$keyName
			);
		}
		foreach($overdraftAgainstCommercialPapers as $overdraftAgainstCommercialPaper){
			$limitStatement = $overdraftAgainstCommercialPaper->overdraftAgainstCommercialPaperBankLimits->sortByDesc('full_date')->first() ;
			if(($limitStatement && $limitStatement->accumulated_limit >0 ) || in_array('bank-statement',Request()->segments()) ){
				$accounts[$overdraftAgainstCommercialPaper->{$keyName}] = $overdraftAgainstCommercialPaper->account_number;
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
		return __('Overdraft Against Commercial Paper');
	}
	public function getCurrencyFormatted()
	{
		return Str::upper($this->getCurrency());
	}
	
	
	
	
	public function rates()
	{
		return $this->hasMany(OverdraftAgainstCommercialPaperRate::class,'overdraft_against_commercial_paper_id','id');
	}
	public static function getBankStatementTableClassName():string 
	{
		return OverdraftAgainstCommercialPaperBankStatement::class ;
	}
	public static function rateFullClassName():string 
	{
		return OverdraftAgainstCommercialPaperRate::class ;
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
			OverdraftAgainstCommercialPaperBankStatement::deleteButTriggerChangeOnLastElement($model->bankStatements);
		});
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

	public static function getLimitTableClassName():string
	{
		return OverdraftAgainstCommercialPaperLimit::class ;
	}
	public function getSmallestLimitTableFullDate()
	{
		
		return $this->overdraftAgainstCommercialPaperBankLimits->min('full_date');
	}	

	/**
	 * Facility Renewal — Phase 3. Same mechanics as the other three
	 * facility types (correct reorder(), missing-Original safety net,
	 * transaction-aware deletion guard), plus this facility's own extra
	 * piece: each chapter owns its own set of lending-rate tiers.
	 */
	public function termsHistories()
	{
		return $this->hasMany(OverdraftAgainstCommercialPaperTermsHistory::class,'overdraft_against_commercial_paper_id','id')->orderBy('effective_date');
	}

	public function getTermsAsOfDate(string $date):?OverdraftAgainstCommercialPaperTermsHistory
	{
		return $this->termsHistories()
			->where('effective_date','<=',$date)
			->reorder('effective_date','desc')
			->orderByDesc('id')
			->first();
	}

	public function getLatestTerms():?OverdraftAgainstCommercialPaperTermsHistory
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
	 * Deliberately different from the other three facility types: a
	 * "transaction" here is a cheque ever having been deposited against
	 * this facility (a row existing in the limits ledger at all) —
	 * there's no debit/credit column on this facility's own bank
	 * statement rows to check the same way, since everything here is
	 * driven by the cheque ledger instead.
	 */
	public function hasAnyTransactions():bool
	{
		return DB::table('overdraft_against_commercial_paper_limits')
			->where('overdraft_against_commercial_paper_id', $this->id)
			->exists();
	}

	public function createOriginalTermsHistory():OverdraftAgainstCommercialPaperTermsHistory
	{
		return $this->termsHistories()->create([
			'company_id' => $this->company_id,
			'effective_date' => $this->contract_start_date,
			'limit' => $this->limit,
			'max_lending_limit_per_customer' => $this->max_lending_limit_per_customer,
			'highest_debt_balance_rate' => $this->highest_debt_balance_rate,
			'admin_fees_rate' => $this->admin_fees_rate,
			'to_be_setteled_max_within_days' => $this->to_be_setteled_max_within_days,
			'contract_end_date' => $this->contract_end_date,
			'is_original' => true,
			'notes' => 'Original facility terms.',
		]);
	}

	/**
	 * $newTiers: array of ['for_commercial_papers_due_within_days' => int, 'lending_rate' => float]
	 * — the renewal's own complete, brand-new tier schedule. Never
	 * touches or deletes the previous chapter's tiers — a cheque
	 * deposited under the old chapter keeps resolving against them
	 * forever, per the client's confirmed rule.
	 */
	public function renew(string $effectiveDate, array $newTerms, array $newTiers, int $userId):OverdraftAgainstCommercialPaperTermsHistory
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

		if (empty($newTiers)) {
			throw new \InvalidArgumentException(
				__('A renewal must include at least one lending-rate tier — the previous chapter\'s tiers only apply to cheques deposited before this renewal.')
			);
		}

		$termsRow = $this->termsHistories()->create([
			'company_id' => $this->company_id,
			'effective_date' => $effectiveDate,
			'limit' => $newTerms['limit'] ?? $previous?->limit ?? $this->limit,
			'max_lending_limit_per_customer' => $newTerms['max_lending_limit_per_customer'] ?? $previous?->max_lending_limit_per_customer ?? $this->max_lending_limit_per_customer,
			'highest_debt_balance_rate' => $newTerms['highest_debt_balance_rate'] ?? $previous?->highest_debt_balance_rate ?? $this->highest_debt_balance_rate,
			'admin_fees_rate' => $newTerms['admin_fees_rate'] ?? $previous?->admin_fees_rate ?? $this->admin_fees_rate,
			'to_be_setteled_max_within_days' => $newTerms['to_be_setteled_max_within_days'] ?? $previous?->to_be_setteled_max_within_days ?? $this->to_be_setteled_max_within_days,
			'contract_end_date' => $newTerms['contract_end_date'] ?? $previous?->contract_end_date ?? $this->contract_end_date,
			'notes' => $newTerms['notes'] ?? null,
			'is_original' => false,
			'created_by' => $userId,
		]);

		foreach ($newTiers as $tier) {
			$termsRow->lendingInformation()->create([
				'overdraft_against_commercial_paper_id' => $this->id,
				'company_id' => $this->company_id,
				'for_commercial_papers_due_within_days' => $tier['for_commercial_papers_due_within_days'],
				'lending_rate' => $tier['lending_rate'],
			]);
		}

		// Only limit/cap/fees/settlement-days/end-date sync to the
		// master record — per the confirmed design, these stay
		// "current, real-time" (the trigger reads them straight off
		// this facility record, same as before). The tier schedule
		// itself is NOT copied here — the trigger resolves it fresh
		// via the correct chapter's own lendingInformation() rows.
		$this->update([
			'limit' => $termsRow->limit,
			'max_lending_limit_per_customer' => $termsRow->max_lending_limit_per_customer,
			'highest_debt_balance_rate' => $termsRow->highest_debt_balance_rate,
			'admin_fees_rate' => $termsRow->admin_fees_rate,
			'to_be_setteled_max_within_days' => $termsRow->to_be_setteled_max_within_days,
			'contract_end_date' => $termsRow->contract_end_date,
		]);

		$this->updateBankStatementsFromDate($effectiveDate);

		return $termsRow;
	}

	/**
	 * Deletes the most recent renewal only — blocked if any cheque was
	 * deposited (a limits-ledger row exists) dated on/after the
	 * renewal's effective date, since removing it would silently change
	 * which tier schedule those cheques resolve against.
	 */
	public function deleteLatestRenewal():void
	{
		$latest = $this->getLatestTerms();

		if (!$latest || $latest->is_original) {
			throw new \InvalidArgumentException(__('There is no renewal to delete — this facility is still on its original terms.'));
		}

		$blockingCheques = DB::table('overdraft_against_commercial_paper_limits')
			->where('overdraft_against_commercial_paper_id', $this->id)
			->where('full_date', '>=', $latest->effective_date)
			->exists();

		if ($blockingCheques) {
			throw new \InvalidArgumentException(
				__('This renewal cannot be deleted because cheques have already been deposited on or after its effective date (:date). Please remove those first.', ['date' => $latest->getEffectiveDateFormatted()])
			);
		}

		$latest->lendingInformation()->delete();
		$latest->delete();

		$newLatest = $this->getLatestTerms();
		$this->update([
			'limit' => $newLatest->limit,
			'max_lending_limit_per_customer' => $newLatest->max_lending_limit_per_customer,
			'highest_debt_balance_rate' => $newLatest->highest_debt_balance_rate,
			'admin_fees_rate' => $newLatest->admin_fees_rate,
			'to_be_setteled_max_within_days' => $newLatest->to_be_setteled_max_within_days,
			'contract_end_date' => $newLatest->contract_end_date,
		]);

		$this->updateBankStatementsFromDate($newLatest->effective_date);
	}
	
	public function updateFirstLimitsTableFromDate()
	{
		$smallestFullDate = $this->getSmallestLimitTableFullDate() ;
		if(is_null($smallestFullDate)){
			return ;
		}
		$firstBankStatementToBeUpdated = (self::getLimitTableClassName())::where(self::generateForeignKeyFormModelName(),$this->id)
		->where('full_date','>=',$smallestFullDate)
		->orderBy('full_date')
		->first();	
		if($firstBankStatementToBeUpdated){
			$firstBankStatementToBeUpdated->update([
				'updated_at'=>now()
			]);
		}
	}
	
	public function isOverdraft():bool 
	{
		return true;
	}
	public function getMaxLendingLimitPerCustomer()
	{
		return $this->max_lending_limit_per_customer?:0;
	}
}
