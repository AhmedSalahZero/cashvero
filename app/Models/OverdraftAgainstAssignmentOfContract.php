<?php

namespace App\Models;

use App\Interfaces\Models\Interfaces\IHaveStatement;
use App\Traits\HasBankStatement;
use App\Traits\HasLastStatementAmount;
use App\Traits\HasOutstandingBreakdown;
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
	
	use HasOutstandingBreakdown , IsOverdraft  , HasBankStatement, HasAccumulatedLimit,HasLastStatementAmount;
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
		->where('financial_institution_id',$financialInstitutionId)->get();	
		foreach($overdraftAgainstAssignmentOfContracts as $overdraftAgainstAssignmentOfContract){
			$limitStatement = $overdraftAgainstAssignmentOfContract->overdraftAgainstAssignmentOfContractBankLimits->sortByDesc('full_date')->first() ;
		
			if(($limitStatement && $limitStatement->accumulated_limit >0) || in_array('bank-statement',Request()->segments())){
				$accounts[$overdraftAgainstAssignmentOfContract->{$keyName}] = $overdraftAgainstAssignmentOfContract->account_number;
			}
		}
		
		return  $accounts ;
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
public function isOverdraft():bool 
	{
		return true;
	}
	
}
