<?php

namespace App\Models;


use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * * دا الشيك اللي بدفعه للموردين
 *
 * @property int $id
 * @property int|null $company_id
 * @property string|null $cheque_number
 * @property string $status
 * @property int|null $money_payment_id
 * @property int|null $cash_expense_id
 * @property int|null $delivery_bank_id هو البنك اللي انا طلعت منة الشيك للمورد وبالتالي لازم يكون من بنوكي
 * @property string $account_type نوع الحساب اللي هسحب منة الشيك علشان ادية للمورد
 * @property string|null $account_number رقم الحساب اللي هسحب منة الشيك علشان ادية للمورد
 * @property string|null $due_date هو تاريخ استحقاق الشيك .. يعني اقدر اسحبة امتة
 * @property string|null $delivery_date هو تاريخ الي اديت فيه الشيك للمورد
 * @property string|null $actual_payment_date هو تاريخ التسليم الفعلي لان لازم ياكد
 * @property numeric $account_balance دي اجمالي اللي معايا في الحساب بعد اما الشيك مثلا انسحب ودي احنا اللي بنجسبها افتراضيا
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\AccountType|null $accountType
 * @property-read \App\Models\CashExpense|null $cashExpenses
 * @property-read \App\Models\FinancialInstitution|null $deliveryBank
 * @property-read \App\Models\FinancialInstitution|null $financialInstitution
 * @property-read \App\Models\MoneyPayment|null $moneyPayment
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PayableCheque newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PayableCheque newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PayableCheque query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PayableCheque whereAccountBalance($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PayableCheque whereAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PayableCheque whereAccountType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PayableCheque whereActualPaymentDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PayableCheque whereCashExpenseId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PayableCheque whereChequeNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PayableCheque whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PayableCheque whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PayableCheque whereDeliveryBankId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PayableCheque whereDeliveryDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PayableCheque whereDueDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PayableCheque whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PayableCheque whereMoneyPaymentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PayableCheque whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\PayableCheque whereUpdatedAt($value)
 * @property-read \App\Models\CashExpense|null $cashExpense
 * @mixin \Eloquent
 */

class PayableCheque extends Model
{
	// protected $with = [
	// 	'deliveryBank',
	// 	'accountType',
	// 	'moneyPayment',
	// 	'cashExpense',
	// 	'financialInstitution.bank'
	// ];
	const PENDING = 'pending';
	const PAID = 'paid';
		 
    protected $guarded = ['id'];
	public function moneyPayment():BelongsTo
	{
		return $this->belongsTo(MoneyPayment::class,'money_payment_id');
	}
	public function cashExpense():BelongsTo
	{
		return $this->belongsTo(CashExpense::class,'cash_expense_id');
	}
	public static function getChequeTypesForAging():array
	{
		return [
			self::PENDING,
		];
	}
	public function isPending():bool
	{
		return $this->getStatus() == self::PENDING;
	}
	public function isPaid():bool
	{
		return $this->getStatus() == self::PAID;
	}
	public function getDeliveryDate()
	{
		return $this->delivery_date ; 
	}
	public function getDeliveryDateFormatted():string|null
	{
		$deliveryDate = $this->getDeliveryDate();
		return $deliveryDate ? Carbon::make($deliveryDate)->format('d-m-Y'): null ;
	}
	public function setDeliveryDateAttribute($value)
	{
		if(!$value){
			return null ;
		}
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['delivery_date'] = $value;
			return  ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		$this->attributes['delivery_date'] = $year.'-'.$month.'-'.$day;
		
	}
	/**
	 * * هو البنك اللي انا باخد الشيك واسحبة منة وبالتالي لازم يكون من بنوكي
	 */
	public function deliveryBank():BelongsTo
	{
		return $this->belongsTo(FinancialInstitution::class , 'delivery_bank_id','id');
	}
	public function getDeliveryBankId():int
	{
		$bank = $this->deliveryBank ;
		return $bank  ? $bank->id : 0 ;
	}
	
	public function getDeliveryBankName():string
	{
		$deliveryBank = $this->deliveryBank ;
		return $deliveryBank  ? $deliveryBank->getName() :__('N/A') ;
	}
	public function getChequeNumber()
	{
		return $this->cheque_number ;
	}
	public function getNumber()
	{
		return $this->getChequeNumber();
	}
	
	public function setActualPaymentDateAttribute($value)
	{
		if(!$value){
			return null ;
		}
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['actual_payment_date'] = $value;
			return  ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		$this->attributes['actual_payment_date'] = $year.'-'.$month.'-'.$day;
	}
	
	public function getStatus()
	{
		return $this->status ;
	}
	
	public function getStatusFormatted()
	{
		return snakeToCamel($this->getStatus());
	}
	public function getDueDate()
	{
		return $this->due_date;
	}
	public function getDueDateFormatted()
	{
		$dueDate = $this->getDueDate();
		return  $dueDate ? Carbon::make($dueDate)->format('d-m-Y') : null ;
	}
	public function setDueDateAttribute($value)
	{
		$date = explode('/',$value);
		if(count($date) != 3){
			$this->attributes['due_date'] =  $value ;
			return ;
		}
		$month = $date[0];
		$day = $date[1];
		$year = $date[2];
		
		$this->attributes['due_date'] = $year.'-'.$month.'-'.$day;
	}
	
	/**
	 * * هنعرفه ان كان مستحق الدفع ولا لا كا استرنج مش بولين
	 */
	public function getDueStatus():bool 
	{
		$dueDate = $this->getDueDate();
		return !Carbon::make($dueDate)->greaterThan(now());
	}
	/**
	 * * هنعرفه ان كان مستحق الدفع ولا لا كا استرنج مش بولين
	 */
	public function getDueStatusFormatted():array 
	{
		if($this->getDueStatus()){
			return [
				'status'=>__('Due') ,
				'color'=>'red'
			];
		}
		return [
			'status'=>__('Not Due Yet'),
			'color'=>'green'
		];
		
	}
	
	
		/**
	 * * هو عباره عن رقم الحساب اللي انا سحبت منة الشيك علشان ادية للمورد
	 */
	public function getAccountNumber()
	{
		return $this->account_number;
	}
	public function getAccountBalance()
	{
		return $this->account_balance ;
	}

	
	public function chequeAccountBalance()
	{
		return $this->account_balance?:0 ;
	}
	

	public function chequeActualPaymentDate()
	{
		return $this->actual_payment_date ;
	}
	public function chequeActualPaymentDateFormatted()
	{
		$date  = $this->chequeActualPaymentDate() ;
		return $date ? Carbon::make($date)->format('d-m-Y') : null ;
	}
	public function accountType()
	{
		return $this->belongsTo(AccountType::class,'account_type','id');
	}
	public function getAccountType()
	{
		return $this->account_type ;
	}
	public function getDueAfterDays()
	{
		$secondDate = null ;
		if($this->moneyPayment){
			$secondDate = $this->moneyPayment->getDeliveryDate() ;
		}
		if($this->cashExpense){
			$secondDate = $this->cashExpense->getPaymentDate() ;	
		}
		if(is_null($secondDate)){
			return '-';
		}
		
		$firstDate = Carbon::make($this->getDueDate());
		$secondDate = Carbon::make($secondDate);
		return getDiffBetweenTwoDatesInDays($firstDate , $secondDate);
	}
	public function getPaymentBankName()
	{

		return $this->financialInstitution->bank->getViewName();
	}
	public function financialInstitution()
	{
		return $this->belongsTo(FinancialInstitution::class , 'delivery_bank_id','id');
	}
	public function getAccountTypeId()
	{
		$accountType = $this->accountType; 
		return $accountType ? $accountType->id : 0 ; 
	}
	public function getAccountTypeName()
	{
		$accountType = $this->accountType; 
		return $accountType ? $accountType->getName() : __('N/A') ; 
	}



}
