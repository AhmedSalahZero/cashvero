<?php

namespace App\Models;

use App\Helpers\HDate;
use App\Interfaces\Models\Interfaces\IHaveStatement;
use App\Traits\IsBankStatement;
use App\Traits\Models\HasDeleteButTriggerChangeOnLastElement;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * @property int $id
 * @property int|null $is_break_interest
 * @property int $is_period_cd_or_td_interest
 * @property string|null $type
 * @property int $is_beginning_balance
 * @property int $is_renewal_fees
 * @property int $is_commission_fees
 * @property int $is_issuance_fees
 * @property int $is_td_renewal
 * @property int $is_active الكولوم دا انا ضفته علشان ال commission اللي بتنزل كل ثلاث شهور مثلا .. فا لو لسه ميعاد الكومشن ما جاش يبقي هنعتبر الرو دا اكنه مش موجود اصلا ولما يجي ميعادة هنعدل الكولوم دا وهنحط بواحد علشان يدخل معايا في الحسبة بتاعتي
 * @property int $financial_institution_account_id
 * @property int $company_id
 * @property int $money_received_id
 * @property int $money_payment_id
 * @property int|null $cash_expense_id
 * @property int|null $buy_or_sell_currency_id
 * @property int|null $internal_money_transfer_id
 * @property int|null $lc_settlement_internal_money_transfer_id
 * @property int|null $time_of_deposit_id
 * @property int|null $letter_of_guarantee_issuance_id
 * @property int|null $lg_renewal_date_history_id
 * @property int|null $letter_of_credit_issuance_id
 * @property int|null $lg_advanced_payment_history_id
 * @property int|null $lc_advanced_payment_history_id
 * @property int|null $lc_issuance_expense_id
 * @property int|null $certificate_of_deposit_id
 * @property int|null $loan_schedule_settlement_id
 * @property int $is_debit
 * @property int $is_credit
 * @property string|null $date
 * @property string|null $full_date
 * @property numeric $beginning_balance
 * @property numeric|null $debit
 * @property numeric|null $credit
 * @property numeric $end_balance
 * @property string|null $interest_type
 * @property numeric $interest_rate_annually
 * @property numeric $interest_rate_daily
 * @property int $days_count
 * @property numeric $interest_amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $comment_en
 * @property string|null $comment_ar
 * @property int|null $interest_account_bank_statement_odoo_id
 * @property int|null $interest_journal_entry_id
 * @property string|null $interest_odoo_reference
 * @property-read \App\Models\CashExpense|null $cashExpense
 * @property-read \App\Models\CertificatesOfDeposit|null $certificateOfDeposit
 * @property-read \App\Models\CurrentAccountBankStatement|null $financialInstitutionAccount
 * @property-read \App\Models\InternalMoneyTransfer|null $internalMoneyTransfer
 * @property-read \App\Models\LcIssuanceExpense|null $lcIssuanceExpense
 * @property-read \App\Models\LetterOfGuaranteeIssuanceAdvancedPaymentHistory|null $letterOfGuaranteeAdvancedPaymentHistory
 * @property-read \App\Models\LetterOfGuaranteeIssuance|null $letterOfGuaranteeIssuance
 * @property-read \App\Models\LoanScheduleSettlement|null $loanScheduleSettlement
 * @property-read \App\Models\MoneyPayment|null $moneyPayment
 * @property-read \App\Models\MoneyReceived|null $moneyReceived
 * @property-read \App\Models\TimeOfDeposit|null $timeOfDeposit
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereBeginningBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereBuyOrSellCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereCashExpenseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereCertificateOfDepositId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereCommentAr($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereCommentEn($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereCredit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereDaysCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereDebit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereEndBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereFinancialInstitutionAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereFullDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereInterestAccountBankStatementOdooId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereInterestAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereInterestJournalEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereInterestOdooReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereInterestRateAnnually($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereInterestRateDaily($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereInterestType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereInternalMoneyTransferId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereIsBeginningBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereIsBreakInterest($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereIsCommissionFees($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereIsCredit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereIsDebit($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereIsIssuanceFees($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereIsPeriodCdOrTdInterest($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereIsRenewalFees($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereIsTdRenewal($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereLcAdvancedPaymentHistoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereLcIssuanceExpenseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereLcSettlementInternalMoneyTransferId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereLetterOfCreditIssuanceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereLetterOfGuaranteeIssuanceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereLgAdvancedPaymentHistoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereLgRenewalDateHistoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereLoanScheduleSettlementId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereMoneyPaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereMoneyReceivedId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereTimeOfDepositId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereUpdatedAt($value)
 * @property int|null $lg_commission_fees_journal_entry_id
 * @property string|null $odoo_lg_commission_fees_reference
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereLgCommissionFeesJournalEntryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\CurrentAccountBankStatement whereOdooLgCommissionFeesReference($value)
 * @mixin \Eloquent
 */
class CurrentAccountBankStatement extends Model  implements IHaveStatement
{
	const DEDUCTED_FOR_CURRENT_ACCOUNT = 'deducted-for-deposit';
	
	use HasDeleteButTriggerChangeOnLastElement,IsBankStatement;
    protected $guarded = [
        'id'
    ];
	
	/**
	 * * ال 
	 * * global scope 
	 * * دا خاص بس بجزئيه ال
	 * * commission 
	 * * ما عدا ذالك ملهوش اي لزمة هو والكولوم اللي اسمة
	 * * is_active
	 */
	protected static function boot()
    {
        parent::boot();
        static::addGlobalScope('only_active',function(Builder $builder){
			$builder->where('current_account_bank_statements.is_active',1); 
		});

    }
	

	public static function updateNextRows(CurrentAccountBankStatement $model):string 
	{
		$minDate  = $model->date ;
		
		
		/**
		 * * ليه بنستخدم ال 
		 * * min date
		 * * علشان لو عدلنا العنصر الحالي وخلينا التاريخ بتاعه اكبر من التاريخ القديم وقتها العناصر اللي ما بين التاريخ مش هيتعدلوا
		 * * مع انهم كان مفروض يتعدلوا بس انت قولتله عدلي العناصر اللي التاريخ بتاعها اكبر من او يساوي التاريخ الجديد
		 * * ودا غلط مفروض التاريخ الاقل ما بين التاريخ الجديد و القديم للعنصر بحيث دايما يبدا يحدث من عنده
		 */

		 DB::table('current_account_bank_statements')
		->where('date','>=',$minDate)
		->orderByRaw('date asc , id asc')
		->where('financial_institution_account_id',$model->financial_institution_account_id)
		->each(function($currentAccountBankStatement){
			DB::table('current_account_bank_statements')->where('id',$currentAccountBankStatement->id)->update([
				'updated_at'=>now()
			]);
		});
		
		return $minDate;

	}
	
		protected static function booted(): void
		{
			static::updating(function(CurrentAccountBankStatement $model){
				if($model->interest_type != 'end_of_month'){
					$model->handleEndOfMonthInterestForCurrentAccountStatement($model->date,$model->company_id);
				}
			});
			static::creating(function(CurrentAccountBankStatement $model){
				if($model->interest_type != 'end_of_month'){
					$model->handleEndOfMonthInterestForCurrentAccountStatement($model->date,$model->company_id);
				}
				$model->created_at = now();
				$date = $model->date ;
				$time  = now()->format('H:i:s');
				$row = DB::table('temp_deleted_statements')->where('company_id',$model->company_id)->where('table_name','current_account_bank_statements')->first();
				
				if($row){
					$model->id = $row->deleted_id;
					DB::table('temp_deleted_statements')->where('company_id',$model->company_id)->where('table_name','current_account_bank_statements')->delete();
				}
				$fullDateTime = date('Y-m-d H:i:s', strtotime("$date $time")) ;
				/**
				 * * دي علشان لو ليهم نفس التاريخ والوقت بالظبط يزود ثانيه علي التاريخ القديم
				 */
				$fullDateTime = HDate::generateUniqueDateTimeForModel(self::class,'full_date',$fullDateTime,[
					[
						'company_id','=',$model->company_id ,
					]
				]) ;
				$model->full_date = $fullDateTime;
			});
			
			static::created(function(CurrentAccountBankStatement $model){
				#:Handle End Of Month Here 
				if($model->is_beginning_balance){
					$model->handleEndOfMonthInterestForCurrentAccountStatement($model->date,$model->company_id);
				}
				self::updateNextRows($model);
			});
			
			static::updated(function (CurrentAccountBankStatement $model) {
				if($model->is_beginning_balance){
					$model->handleEndOfMonthInterestForCurrentAccountStatement($model->date,$model->company_id);
				}
				$minDate = self::updateNextRows($model);
				
				
				$isChanged = $model->isDirty('financial_institution_account_id') ;
				/**
				 * * دي علشان لو غيرت ال
				 * * financial_institution_account_id
				 * * بمعني انه نقل السحبة مثلا من حساب الي حساب اخر .. يبقي هنحتاج نشغل الترجرز علشان الحساب القديم علشان يوزع تاني
				 */
				if($isChanged){
					$oldAccountIdId=$model->getRawOriginal('financial_institution_account_id');
					$oldBankStatementId=$model->getRawOriginal('id');
					// لو ما لقناش اول واحد فوقه هندور علي اول واحد بعدة					
					$firstBankStatementForOld = CurrentAccountBankStatement::where('financial_institution_account_id',$oldAccountIdId)->where('id','!=',$oldBankStatementId)->orderBy('id')->first()  ;
					// لو كانت القديمة دي قبل ما تتغير هي الاستيتم الوحيده بعد كدا انت غيرتها بالتالي الحساب القديم دا معتش ليه لزمة فا هنحذف كل السحبات و التسديدات بتاعته
					if(!$firstBankStatementForOld){
						// وتلقائي هيحذف السحوبات settlements
					}else{
						DB::table('current_account_bank_statements')
						->where('date','>=',$minDate)
						->orderByRaw('date asc , id asc')
						->where('financial_institution_account_id',$model->financial_institution_account_id)->update([
							'updated_at'=>now()
						]);
						
					}
					
				}
				
			});
			
			static::deleting(function(CurrentAccountBankStatement $currentAccountBankStatement){
				$oldDate = null ;
				if($currentAccountBankStatement->is_debit && Request('receiving_date')||$currentAccountBankStatement->is_credit && Request('delivery_date')){
						$oldDate = Carbon::make(Request('receiving_date',Request('delivery_date')))->format('Y-m-d');
						// $time  = now()->format('H:i:s');
						// $oldDate = date('Y-m-d H:i:s', strtotime("$oldDate $time")) ;
						$currentDate = $currentAccountBankStatement->date ;
						$currentAccountBankStatement->date = min($oldDate,$currentDate);
				}
			
				
				$currentAccountBankStatement->debit = 0;
				$currentAccountBankStatement->credit = 0;
				$currentAccountBankStatement->save();
				
			});
		}
		

    public function moneyReceived()
    {
        return $this->belongsTo(MoneyReceived::class, 'money_received_id', 'id');
    }
	public function certificateOfDeposit()
    {
        return $this->belongsTo(CertificatesOfDeposit::class, 'certificate_of_deposit_id', 'id');
    }
	public function timeOfDeposit()
    {
        return $this->belongsTo(TimeOfDeposit::class, 'time_of_deposit_id', 'id');
    }
	public function letterOfGuaranteeIssuance()
    {
        return $this->belongsTo(LetterOfGuaranteeIssuance::class, 'letter_of_guarantee_issuance_id', 'id');
    }
	public function moneyPayment()
    {
        return $this->belongsTo(MoneyPayment::class, 'money_payment_id', 'id');
    }
	public function cashExpense()
    {
        return $this->belongsTo(CashExpense::class, 'cash_expense_id', 'id');
    }
    public function getId()
    {
        return $this->id ;
    }
	
	public function getEndBalance()
	{
		return $this->end_balance ?: 0 ;
	}
	public function getEndBalanceFormatted()
	{
		return number_format($this->getEndBalance()) ;
	}

    public function setDateAttribute($value)
    {
        $date = explode('/', $value);
        if (count($date) != 3) {
            $this->attributes['date'] = $value ;

            return ;
        }
        $month = $date[0];
        $day = $date[1];
        $year = $date[2];

        $this->attributes['date'] = $year . '-' . $month . '-' . $day;
    }
	public function financialInstitutionAccount()
	{
		return $this->belongsTo(CurrentAccountBankStatement::class,'financial_institution_account_id','id');
	}
	public function internalMoneyTransfer()
	{
		return $this->belongsTo(InternalMoneyTransfer::class,'internal_money_transfer_id','id');
	}
	public function letterOfGuaranteeAdvancedPaymentHistory():BelongsTo
	{
		return $this->belongsTo(LetterOfGuaranteeIssuanceAdvancedPaymentHistory::class,'lg_advanced_payment_history_id','id');
	}	
	public function lcIssuanceExpense():BelongsTo
	{
		return $this->belongsTo(LcIssuanceExpense::class,'lc_issuance_expense_id','id');
	}	
	public function loanScheduleSettlement():BelongsTo
	{
		return $this->belongsTo(LoanScheduleSettlement::class,'loan_schedule_settlement_id','id');
	}	
	// public static function updateNonActiveDaily(Company $company)
	// {

	// 	DB::table('current_account_bank_statements')
	// 	->where('company_id',$company->id)
	// 	->where('is_active',0)
	// 	->where('full_date','<=',now())
	// 	->orderByRaw('full_date asc , id asc')
	// 	->each(function($currentAccountBankStatementRow){
	// 		$letterOfGuaranteeIssuanceId = $currentAccountBankStatementRow->letter_of_guarantee_issuance_id;
			
	// 		$letterOfGuaranteeIssuance = DB::table('letter_of_guarantee_issuances')
	// 		->where('id',$letterOfGuaranteeIssuanceId)
	// 		->first();
			
	// 		$commissionRate = $letterOfGuaranteeIssuance->lg_commission_rate; 
			
	// 		$totalPaid = DB::table('lg_issuance_advanced_payment_histories')
	// 		->where('letter_of_guarantee_issuance_id',$letterOfGuaranteeIssuanceId)
	// 		->where('date' ,'<=' , $currentAccountBankStatementRow->full_date)
	// 		->sum('amount');
			
	// 		DB::table('current_account_bank_statements')->where('id',$currentAccountBankStatementRow->id)
	// 		->update([
	// 			'is_active'=>1 ,
	// 			'credit'=> ($letterOfGuaranteeIssuance->lg_amount - $totalPaid) * $commissionRate
	// 		]);
	// 		/**
	// 	 * * هنبدا نعمل ابديت من اول الرو اللي تاريخه اصغر حاجه في اللي كانوا محتاجين يتعدلوا
	// 	 * * وبالتالي هيتعدل هو وكل اللي تحتة
	// 	 */
	// 		CurrentAccountBankStatement::updateNextRows(CurrentAccountBankStatement::find($currentAccountBankStatementRow->id));
			
	// 	});
	// }
	public static function generateRefundLgCashCoverComment($lang,string $customerName,?string $transactionName,?string $lgCode):string 
	{
		$transactionName = is_null($transactionName) ? '-' : $transactionName ;
		$lgCode = is_null($lgCode) ? '-' : $lgCode ;
		return __('Refund LG Cash Cover [ :customerName ] [ :transactionName ] [ :lgCode ]',['customerName'=>$customerName,'transactionName'=>$transactionName,'lgCode'=>$lgCode],$lang) ;
	}	
	/**
	 * * دول اسماء العواميد اللي بنفرق باستخدامهم بين كل رو والتاني في الحسابات في التريجر
	 * * يعني مثلا لما اجي اجيب العنصر اللي قبلي هجيبه بناء علي انهي شروط 
	 */
	public function getForeignKeyNamesThatUsedInFilter():array 
	{
		return [
			'financial_institution_account_id'
		];
	}
	
	
		/**
	 * * دا مش محدود بتواريخ بدايه ونهايه زي اللي فوق
	 */
	public  function handleEndOfMonthInterestForCurrentAccountStatement(string $statementDate , int $companyId)
	{
	
		$foreignKeyColumnName = 'financial_institution_account_id'; // clean_overdraft_id for clean_overdrafts for example
		$fullBankStatement = FinancialInstitutionAccount::getBankStatementTableClassName();
		
		$statementStartDateAsCarbon = Carbon::make($statementDate)->startOfYear();
		
		
		$statementEndDateAsCarbon= $statementStartDateAsCarbon->copy()->endOfYear();
		
		$dates = generateDatesBetweenTwoDatesWithoutOverflow($statementStartDateAsCarbon,$statementEndDateAsCarbon) ;
	//	$countDates = count($dates);
		$interestText = 'interest';
	//	$interestTypeText = 'end_of_month';
	//	$fullBankStatement::where('company_id',$companyId)->where('type',$interestText)->where($foreignKeyColumnName,$this->id)->where('interest_type',$interestTypeText)->where('date','>',$contractEndDate)->delete();
		$beginningBalanceRow = $fullBankStatement::where('company_id',$companyId)->where('is_beginning_balance',1)->where('financial_institution_account_id',$this->financial_institution_account_id)->first();
		$financialInstitutionAccount = FinancialInstitutionAccount::find($this->financial_institution_account_id);
		$balanceDate = $financialInstitutionAccount->balance_date;
		$syncedYears = $financialInstitutionAccount->synced_end_of_month_years;
		$syncedYears = (array)$syncedYears;
			
		$currentYear = Carbon::make($statementDate)->format('Y');
		if(in_array($currentYear,$syncedYears) || !$beginningBalanceRow){
			return ;
		}
		
		foreach($dates as $index => $dateAsString){
			$currentEndOfMonthDate =  Carbon::make($dateAsString)->endOfMonth()->format('Y-m-d');
			if(Carbon::make($currentEndOfMonthDate)->lessThanOrEqualTo(Carbon::make($beginningBalanceRow->date))){
				continue ; 
			}
			if( Carbon::make($currentEndOfMonthDate)->equalTo(Carbon::make($balanceDate))){
				continue;
			}
			// if($index == 0 && $isLastDayOfMonth){
			// 	continue;
			// }
	//		$isLastLoop = $index == $countDates -1;
	
			
			// $isExist = $fullBankStatement::where('company_id',$companyId)->where($foreignKeyColumnName,$this->id)->where('type',$interestText)->where('interest_type',$interestTypeText)->where('date',$currentEndOfMonthDate)->first();
			// if(!$isExist){
				$data = [
				'company_id'=>$companyId,
				$foreignKeyColumnName=>$this->financial_institution_account_id ,
				'priority'=>1 ,
				'type'=>$interestText,
				'date'=>$currentEndOfMonthDate,
			//	'limit'=>$this->limit ,
				'debit'=>0,
				'beginning_balance'=>0,
				'credit'=>0 ,
				'interest_type'=>'end_of_month',
				'comment_en'=>__('End Of Month Interest'),
				'comment_ar'=>__('End Of Month Interest'),
			] ; 
	
				unset($data['priority']);
			
			 $fullBankStatement::create($data);
			// }
			
		}
		$syncedYears[] = $currentYear;
		$financialInstitutionAccount->update([
			'synced_end_of_month_years'=>$syncedYears
		]);
		
		
	}
		public function fullyIntegratedWithOdoo()
	{
		return count($this->getOdooReferenceNames());
	}
	public function getOdooReferenceNames():array 
	{
		$result = [];
		foreach([
			'interest_odoo_reference',
		] as $referenceColumnName){
			if($this->{$referenceColumnName}){
				$result[] = $this->{$referenceColumnName};
			}
		}
		return $result;
	}	
}
