<?php

namespace App\Models;

use App\Helpers\HHelpers;
use App\Helpers\HStr;
use App\Models\Partner;
use App\Traits\HasBasicStoreRequest;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int|null $project_account_id
 * @property int|null $odoo_id
 * @property int|null $parent_id عباره عن انه مربوط بيه
 * @property int|null $overdraft_against_assignment_of_contract_id
 * @property string $status
 * @property string|null $model_type اما Customer or Supplier
 * @property int|null $partner_id
 * @property string|null $name
 * @property string|null $code
 * @property int $company_id
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $start_date
 * @property string|null $end_date
 * @property numeric $amount
 * @property string|null $currency
 * @property numeric|null $exchange_rate
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MoneyPayment> $MoneyPayment
 * @property-read int|null $money_payment_count
 * @property-read bool|null $money_payment_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CashExpense> $cashExpenses
 * @property-read int|null $cash_expenses_count
 * @property-read bool|null $cash_expenses_exists
 * @property-read \App\Models\Partner|null $client
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CustomerInvoice> $customerInvoices
 * @property-read int|null $customer_invoices_count
 * @property-read bool|null $customer_invoices_exists
 * @property-read \App\Models\LendingInformationAgainstAssignmentOfContract|null $lendingInformationForAgainstAssignmentContract
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LetterOfGuaranteeIssuance> $letterOfGuaranteeIssuances
 * @property-read int|null $letter_of_guarantee_issuances_count
 * @property-read bool|null $letter_of_guarantee_issuances_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MoneyReceived> $moneyReceived
 * @property-read int|null $money_received_count
 * @property-read bool|null $money_received_exists
 * @property-read \App\Models\OverdraftAgainstAssignmentOfContract|null $overdraftAgainstAssignmentOfContract
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OverdraftAgainstAssignmentOfContractLimit> $overdraftAgainstAssignmentOfContractLimits
 * @property-read int|null $overdraft_against_assignment_of_contract_limits_count
 * @property-read bool|null $overdraft_against_assignment_of_contract_limits_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PoAllocation> $poAllocations
 * @property-read int|null $po_allocations_count
 * @property-read bool|null $po_allocations_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PurchaseOrder> $purchasesOrders
 * @property-read int|null $purchases_orders_count
 * @property-read bool|null $purchases_orders_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Contract> $relatedContracts
 * @property-read int|null $related_contracts_count
 * @property-read bool|null $related_contracts_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SalesOrder> $salesOrders
 * @property-read int|null $sales_orders_count
 * @property-read bool|null $sales_orders_exists
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Contract newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Contract newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Contract onlyForCompany(int $companyId)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Contract query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Contract whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Contract whereCode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Contract whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Contract whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Contract whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Contract whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Contract whereEndDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Contract whereExchangeRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Contract whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Contract whereModelType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Contract whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Contract whereOdooId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Contract whereOverdraftAgainstAssignmentOfContractId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Contract whereParentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Contract wherePartnerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Contract whereProjectAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Contract whereStartDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Contract whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Contract whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Contract whereUpdatedBy($value)
 * @mixin \Eloquent
 */
class Contract extends Model
{
	use HasBasicStoreRequest;
	protected $guarded = ['id'];
	const RUNNING ='running';
	const RUNNING_AND_AGAINST = 'running_and_against';
	const FINISHED = 'finished';
	
	public function overdraftAgainstAssignmentOfContractLimits()
    {
        return $this->hasMany(OverdraftAgainstAssignmentOfContractLimit::class, 'contract_id', 'id');
    }
	public function deleteOverdraftAgainstAssignmentOfContractsLimits()
    {
        $this->overdraftAgainstAssignmentOfContractLimits->each(function ($overdraftAgainstAssignmentOfContractLimit) {
            $overdraftAgainstAssignmentOfContractLimit->update(['is_active' => 0]);
            DB::table('overdraft_against_assignment_of_contract_limits')->where('id', $overdraftAgainstAssignmentOfContractLimit->id)->delete();
			self::deleteLimitUpdateRowFromStatement($overdraftAgainstAssignmentOfContractLimit);
        });
    }
	public function handleOverdraftAgainstAssignmentOfContractLimit(): void
    {
       
        // $accountType = AccountType::find($this->getAccountType());
        $overdraftAgainstAssignmentOfContract = $this->overdraftAgainstAssignmentOfContract;
		//  OverdraftAgainstAssignmentOfContract::where('account_number', $this->getAccountNumber())->first();
		$companyId = $this->company_id ;
        if (
			// $accountType && $accountType->isOverdraftAgainstAssignmentOfContractAccount() &&
		
		 $overdraftAgainstAssignmentOfContract) {
            $currentLimitRow = $this->overdraftAgainstAssignmentOfContractLimits()->create([
                'company_id' => $companyId,
                'overdraft_against_assignment_of_contract_id' => $overdraftAgainstAssignmentOfContract->id
            ]);
			
			
			$limitRow = DB::table('overdraft_against_assignment_of_contract_limits')->where('overdraft_against_assignment_of_contract_id',$overdraftAgainstAssignmentOfContract->id)->orderByDesc('full_date')->first();
			
			$accumulatedLimit = $limitRow->accumulated_limit;
			$date = Carbon::make($currentLimitRow->full_date)->format('Y-m-d');
			$contractId = $overdraftAgainstAssignmentOfContract->id;
		
				OverdraftAgainstAssignmentOfContractBankStatement::create([
					'type'=>'limit_update',
					'is_debit'=>1 ,
					'is_credit'=>0 ,
					'priority'=>3 ,
					'company_id' => $companyId,
					'overdraft_against_assignment_of_contract_id' => $contractId,
					'debit'=>0,
					'credit'=>0,
					'limit'=>$accumulatedLimit,
					'date'=>$date,
					'overdraft_against_assignment_of_contract_limit_id'=>$currentLimitRow->id,
					'comment_en'=>__('Limit Update'),
					'comment_ar'=>__('Limit Update',[],'ar'),
				]);
				
        }
    }
	public function isRunning()
	{
		return $this->status == self::RUNNING;
	}
	public function isRunningAndAgainst()
	{
		return $this->status == self::RUNNING_AND_AGAINST;
	}
	public function isFinished()
	{
		return $this->status == self::FINISHED;
	}
	public static function boot()
    {
        parent::boot();
        // self::saving(function($model){
		// 	$model->duration = $model->duration * 365/12;
		// 	$model->end_date = $model->start_date && $model->duration ? Carbon::make($model->start_date)->addDays($model->duration)->format('Y-m-d') : null;  
        // });
		
		
		static::updated(
            function (self $model) {
                $oldStatus = $model->getRawOriginal('status');
       
                /**
                 * * في حالة لو رجعته من
                 * * finished to be running and against
                 */
                if ($model->isRunningAndAgainst() && $oldStatus == self::FINISHED) {
                    $negativeOverdraftAgainstAssignmentOfContractLimit = $model->overdraftAgainstAssignmentOfContractLimits->where('limit', '<', 0)->first();
                    $negativeOverdraftAgainstAssignmentOfContractLimit ? $negativeOverdraftAgainstAssignmentOfContractLimit->update(['is_active' => 0]) : null ;
                    $negativeOverdraftAgainstAssignmentOfContractLimit ? DB::table('overdraft_against_assignment_of_contract_limits')->where('id', $negativeOverdraftAgainstAssignmentOfContractLimit->id)->delete() : null ;
					$negativeOverdraftAgainstAssignmentOfContractLimit ? self::deleteLimitUpdateRowFromStatement($negativeOverdraftAgainstAssignmentOfContractLimit) : null ;
                    return ;
                }
                /**
                 * * في حالة لو بقى
                 * * finished 
                 */
                if ($model->isFinished()) {
                    /**
                     * * هنضيف رو جديد بنفس القيمة ولكن بالسالب
                     */

                    $model->handleOverdraftAgainstAssignmentOfContractLimit();

                    return ;
                }

                if ($model->isRunning() ) {
                    $model->deleteOverdraftAgainstAssignmentOfContractsLimits();
                    return ;
                }
                /**
                 * * في حالة لو هو عدل شيك تحت التحصيل وفي نفس الوقت غير نوع الاكونت لاي اكونت تاني غير
                 * * overdraft against assignment of contract
                 */
                // if ($model->isRunningAndAgainst() && $currentAccountType && !$currentAccountType->isOverdraftAgainstAssignmentOfContractAccount()) {
					
                //     $model->deleteOverdraftAgainstAssignmentOfContractsLimits();
                //     return ;
                // }

                /**
                 * * في حالة لو هو عدل شيك تحت التحصيل وفي نفس الوقت غير نوع الاكونت ل
                 * * overdraft against assignment of contract
                 * * وكان عدد ال
                 * * assignment of contract limits
                 * * صفر يبقي هو اكيد كان جي من نوع تاني غير ال
                 * * overdraft against commercial assignment of contract
                 * *
                 */
                // if ($model->isRunningAndAgainst() && $currentAccountType && $currentAccountType->isOverdraftAgainstAssignmentOfContractAccount() && !$model->overdraftAgainstAssignmentOfContractLimits->count() && $oldAccountType && !$oldAccountType->isOverdraftAgainstAssignmentOfContractAccount()) {
					
                //     $model->handleOverdraftAgainstAssignmentOfContractLimit();

                //     return ;
                // }
                /**
                 * * في حالة لو غير رقم الحساب ال
                 * * overdraft against assignment of contract
                 * * وحطها في حساب تاني حتى لو كانت بنك مختلف
                 */
                // if ($model->isRunningAndAgainst() && $oldAccountType && $oldAccountType->isOverdraftAgainstAssignmentOfContractAccount() && $currentAccountType && $currentAccountType->isOverdraftAgainstAssignmentOfContractAccount() && $currentAccountNumber != $oldAccountNumber) {
					
                //     $model->overdraftAgainstAssignmentOfContractLimits->each(function ($overdraftAgainstAssignmentOfContract) use ($model, $currentAccountNumber) {
                //         $overdraftAgainstAssignmentOfContract->update([
                //             'overdraft_against_assignment_of_contract_id' => DB::table('overdraft_against_assignment_of_contracts')->where('company_id', $model->company_id)->where('account_number', $currentAccountNumber)->first()->id,
                //         ]);
                //     });

                //     return ;
                // }
                /**
                 * * في حالة لو هو في الخزنة اول مرة وبالتالي مفيش
                 * * limits
                 */
                if ($model->isRunningAndAgainst() 
				// && $currentAccountType->isOverdraftAgainstAssignmentOfContractAccount()
			 	&& !$model->overdraftAgainstAssignmentOfContractLimits->count()) {
					
                    $model->handleOverdraftAgainstAssignmentOfContractLimit();
                    return ;
                }
				
		
                $overdraftAgainstAssignmentOfContractLimit = $model->overdraftAgainstAssignmentOfContractLimits->sortBy('full_date')->first() ;
                $overdraftAgainstAssignmentOfContractLimit ? $overdraftAgainstAssignmentOfContractLimit->update(['updated_at' => now(), 'full_date' => $fullDate = $overdraftAgainstAssignmentOfContractLimit->updateFullDate()]) : null;
				$overdraftAgainstAssignmentOfContractLimit ? self::updateLimitUpdateRowFromStatement($overdraftAgainstAssignmentOfContractLimit,$fullDate) : null;

            }
        );


        static::deleted(
            function (self $model) {
				$model->detachRelatedContracts();
                $model->deleteOverdraftAgainstAssignmentOfContractsLimits();
            }
        );
		

    }
	public function getId()
	{
		return $this->id ;
	}
	public function client()
	{
		return $this->belongsTo(Partner::class,'partner_id','id');
	}
	public function getClientName()
	{
		return $this->client ? $this->client->getName() :__('N/A');
	}
	public function getClientId()
	{
		return $this->client ? $this->client->id :0;
	}
	public function getName()
	{
		return $this->name ;
	}
	public function getCode()
	{
		return $this->code ;
	}
	public function getStartDate()
	{
		return $this->start_date; 
	}
	public function getStartDateFormatted()
	{
		$date = $this->getStartDate() ;
		return $date ? Carbon::make($date)->format('d-m-Y'):null ;
	}
	public function setStartDateAttribute($value)
	{
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['start_date'] =  $value ;
			return ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		
		$this->attributes['start_date'] = $year.'-'.$month.'-'.$day;
	}
	
	public function getEndDate()
	{
		return $this->end_date ;
	}
	public function getEndDateFormatted()
	{
		$date = $this->getEndDate() ;
		return $date ? Carbon::make($date)->format('d-m-Y'):null ;
	}
	public function setEndDateAttribute($value)
	{
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['end_date'] =  $value ;
			return ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		
		$this->attributes['end_date'] = $year.'-'.$month.'-'.$day;
	}
	public function getAmount()
	{
		return $this->amount?:0 ;
	}
	public function getAmountFormatted()
	{
		return number_format($this->getAmount(),0);
	}
	public function getAmountWithCurrency()
	{
		return $this->getAmountFormatted() . ' ' . $this->getCurrency();
	}
	public function getCurrency()
	{
		return $this->currency;
	}
	public function salesOrders():HasMany
	{
		return $this->hasMany(SalesOrder::class,'contract_id','id');
	}
	public function purchasesOrders():HasMany
	{
		return $this->hasMany(PurchaseOrder::class,'contract_id','id');
	}
	public function poAllocations():HasMany
	{
		return $this->hasMany(PoAllocation::class,'contract_id','id');
	}
	public function forCustomer():bool
	{
		return $this->model_type === 'Customer';
	}
	public function forSupplier():bool
	{
		return $this->model_type === 'Supplier';
	}
	/**
	 * * اما 
	 * *sales order or purchase order
	 */
	public function getOrders()
	{
		return $this->forSupplier() ? $this->purchasesOrders : $this->salesOrders ;
	}
	
	public function letterOfGuaranteeIssuances():HasMany
	{
		return $this->hasMany(LetterOfGuaranteeIssuance::class , 'contract_id','id');
	}
	public function scopeOnlyForCompany(Builder $builder , int $companyId)
	{
		return $builder->where('company_id',$companyId);
	}	
	public function getExchangeRate():float
	{
		return $this->exchange_rate ?: 1 ;
	}
	public static function getForParentAndCurrency(int $partnerId , string $currencyName):Collection
	{
		return self::where('partner_id',$partnerId)->where('currency',$currencyName)->get();
	}	
	public function lendingInformationForAgainstAssignmentContract():HasOne
	{
		return $this->hasOne(LendingInformationAgainstAssignmentOfContract::class,'contract_id','id');
	}
	
	public function overdraftAgainstAssignmentOfContract():BelongsTo
	{
		return $this->belongsTo(OverdraftAgainstAssignmentOfContract::class , 'overdraft_against_assignment_of_contract_id','id');
	}
	/**
	 * * عباره عن العقود اللي مربوطة بيها 
	 * * بحيث لو هو عقد عميل هيكون مربوط باكثر من عقد من الموردين
	 */
	public function relatedContracts():HasMany
	{
		return $this->hasMany(Contract::class , 'parent_id');
	}	
	public function relateWithContracts(array $contractsToBeRelated):void
	{
		$ids = Arr::pluck($contractsToBeRelated,'contract_id');
	
		Contract::whereIn('id',$ids)->update([
			'parent_id'=>$this->id 
		]);
	}
	public function detachRelatedContracts():void
	{
		$this->relatedContracts()->update([
			'parent_id'=>null 
		]);
	}
	public function syncWithContracts(array $contractsToBeRelated):void
	{
		$this->detachRelatedContracts();
		$this->relateWithContracts($contractsToBeRelated);
	}
	public function cashExpenses():BelongsToMany
	{
		return $this->belongsToMany(CashExpense::class ,'cash_expense_contract','contract_id','cash_expense_id')
		->withTimestamps()
		->withPivot(['amount','cash_expense_id'])
		;
	}
	public function getCashExpensePerCategoryName(array &$result,string $moneyType,string $dateFieldName,string $startDate , string $endDate ,string $currentWeekYear , string $currencyName , ?string $chequeStatus = null ):void
	{
		foreach($this->cashExpenses as $cashExpense){
			/**
			 * @var CashExpense $cashExpense
			 */
			$currentAllocationAmount = DB::table('cash_expense_contract')
			->where('contract_id',$this->id)
			->join('cash_expenses','cash_expenses.id','=','cash_expense_contract.cash_expense_id')
			->where('cash_expenses.type',$moneyType)
			->where('currency',$currencyName)
			// ->where('cash_expense_category_name_id',$cashExpense->getCashExpenseCategoryNameId())
			->whereBetween($dateFieldName,[$startDate,$endDate])
			->when($moneyType == CashExpense::PAYABLE_CHEQUE , function( $builder) use ($chequeStatus){
				$builder->join('payable_cheques','payable_cheques.cash_expense_id','=','cash_expenses.id')
				->where('payable_cheques.status',$chequeStatus)
				;
			})
			->where('cash_expenses.id',$cashExpense->id)
			->sum('cash_expense_contract.amount');
		
			
				$categoryName = $cashExpense->cashExpenseCategoryName ;
			 $categoryName = $cashExpense->getExpenseCategoryName() ;
			 $categoryNameName = $cashExpense->getExpenseName() ;
			$result['cash_expenses'][$categoryName][$categoryNameName]['weeks'][$currentWeekYear] = isset($result['cash_expenses'][$categoryName][$categoryNameName]['weeks'][$currentWeekYear]) ? $result['cash_expenses'][$categoryName][$categoryNameName]['weeks'][$currentWeekYear] + $currentAllocationAmount :  $currentAllocationAmount;
			$result['cash_expenses'][$categoryName][$categoryNameName]['total'] = isset($result['cash_expenses'][$categoryName][$categoryNameName]['total']) ? $result['cash_expenses'][$categoryName][$categoryNameName]['total']  + $currentAllocationAmount : $currentAllocationAmount;
			$currentTotal = $currentAllocationAmount;
			$result['cash_expenses'][$categoryName]['total'][$currentWeekYear] = isset($result['cash_expenses'][$categoryName]['total'][$currentWeekYear]) ? $result['cash_expenses'][$categoryName]['total'][$currentWeekYear] +  $currentTotal : $currentTotal ;
			// $result['cash_expenses'][$categoryName]['total']['total_of_total'] = isset($result['cash_expenses'][$categoryName]['total']['total_of_total']) ? $result['cash_expenses'][$categoryName]['total']['total_of_total'] +   $currentAllocationAmount : $currentAllocationAmount ; 	
			// $totalCashOutFlowArray[$currentWeekYear] = isset($totalCashOutFlowArray[$currentWeekYear]) ? $totalCashOutFlowArray[$currentWeekYear] +   $currentTotal : $currentTotal ;

	}
	
	
		
	}
	public function moneyReceived() // downpayments
	{
		return $this->hasMany(MoneyReceived::class,'contract_id','id')->whereIn('money_type',[MoneyReceived::DOWN_PAYMENT
		,MoneyReceived::INVOICE_SETTLEMENT_WITH_DOWN_PAYMENT
	]);
	}
	public function MoneyPayment() // downpayments
	{
		return $this->hasMany(MoneyPayment::class,'contract_id','id')->whereIn('money_type',[
			MoneyPayment::DOWN_PAYMENT,
			MoneyPayment::INVOICE_SETTLEMENT_WITH_DOWN_PAYMENT
		]);
	}
	public static function generateRandomContract(int $companyId , string $partnerName,string $startDate , string $modelType):string 
	{
		$prefix = $modelType == 'Customer' ? 'c-' : 's-';
		$startDate = Carbon::make($startDate)->format('Y-m-d');
		$startDateMonth = explode('-',$startDate)[1];
		$startDateYear = explode('-',$startDate)[0];
		$partnerNameItems = explode(' ',$partnerName);
		$randomNumbers = HHelpers::generateCodeOfLength(4,true);
		$partnerNameChar = '';
		foreach($partnerNameItems as $partnerNameItem){
			$partnerNameChar.=mb_substr($partnerNameItem, 0, 1, 'utf8') ;
		}
		$partnerNameChar = HStr::replaceSpecialCharacters($partnerNameChar);
		$code = $prefix . $startDateMonth.'-'.$startDateYear.'-'.$partnerNameChar.'-'.$randomNumbers ;
		if(Contract::where('code',$code)->where('company_id',$companyId)->exists()){
			return self::generateRandomContract($companyId,$partnerName,$startDate,$modelType);
		}
		return $code ;
	}
	public function customerInvoices()
	{
		return $this->hasMany(CustomerInvoice::class,'contract_code','code')->where('company_id',$this->company_id);
	}
	public static function deleteLimitUpdateRowFromStatement($overdraftAgainstAssignmentOfContractLimit)
	{
		$paperId = $overdraftAgainstAssignmentOfContractLimit->overdraft_against_assignment_of_contract_id;
		$row =  OverdraftAgainstAssignmentOfContractBankStatement::where('type', 'limit_update')->where('overdraft_against_assignment_of_contract_limit_id',$overdraftAgainstAssignmentOfContractLimit->id)->where('overdraft_against_assignment_of_contract_id',$paperId)->first();
		if($row){
			$row->delete();
		}
		
	}
	public static function updateLimitUpdateRowFromStatement($overdraftAgainstAssignmentOfContractLimit,$fullDate)
	{
		DB::table('overdraft_against_assignment_of_contract_bank_statements')->where('type', 'limit_update')->where('overdraft_against_assignment_of_contract_limit_id',$overdraftAgainstAssignmentOfContractLimit->id)->where('overdraft_against_assignment_of_contract_id',$overdraftAgainstAssignmentOfContractLimit->overdraft_against_assignment_of_contract_id)->update([
			'date'=>Carbon::make($fullDate)->format('Y-m-d'),
			'full_date'=>$fullDate
		]);
	}
	
		
	
}
