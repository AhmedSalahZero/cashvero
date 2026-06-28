<?php

namespace App\Models;

use App\Interfaces\Models\Interfaces\IHaveStatement;
use App\Traits\HasBankStatement;
use App\Traits\HasLastStatementAmount;
use App\Traits\HasOutstandingBreakdown;
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
	use HasOutstandingBreakdown , IsOverdraft ,HasBankStatement, HasLastStatementAmount;
	
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
			'comment_en'=>__('Limit'),
			'comment_ar'=>__('Limit',[],'ar'),
		];
		$row = $this->fullySecuredOverdraftBankStatements()->where('type','active-limit')->first();
		if($row){
			$row->update($data);
		}else{
			$this->fullySecuredOverdraftBankStatements()->create($data);
		}
		
	}
	public function isOverdraft():bool 
	{
		return true;
	}
	
}
