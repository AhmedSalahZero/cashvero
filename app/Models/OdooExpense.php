<?php

namespace App\Models;

use App\Http\Controllers\CashExpenseController;
use App\Http\Requests\StoreCashExpenseRequest;
use App\Services\Api\OdooService;
use App\Traits\HasBasicStoreRequest;
use App\Traits\HasCompany;
use App\Traits\HasCreatedAt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Calculation\DateTimeExcel\Current;
use App\Helpers\HStr;

/**
 * @property int $id
 * @property int $odoo_id
 * @property int $company_id
 * @property string $name
 * @property string $odoo_currency_id
 * @property string $state
 * @property string $payment_state
 * @property int $odoo_employee_id الموظف اللي طلع المصروف دا
 * @property int $total_amount
 * @property int $account_move_ids
 * @property int $journal_id
 * @property int $payment_method_line_id
 * @property string $payment_mode
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $account_number
 * @property string|null $bank_name
 * @property-read \App\Models\CashExpense|null $cashExpense
 * @property-read \App\Models\Company|null $company
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OdooExpense newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OdooExpense newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OdooExpense query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OdooExpense whereAccountMoveIds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OdooExpense whereAccountNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OdooExpense whereBankName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OdooExpense whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OdooExpense whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OdooExpense whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OdooExpense whereJournalId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OdooExpense whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OdooExpense whereOdooCurrencyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OdooExpense whereOdooEmployeeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OdooExpense whereOdooId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OdooExpense wherePaymentMethodLineId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OdooExpense wherePaymentMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OdooExpense wherePaymentState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OdooExpense whereState($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OdooExpense whereTotalAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\OdooExpense whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class OdooExpense extends Model
{
	const APPROVED ='approved';
	use HasCreatedAt,HasBasicStoreRequest,HasCompany;
    protected $guarded = [];
public static function getAllTypes()
	{
		return [
			self::APPROVED,
		];
	}

  
	public function getId(){
		return $this->id ;
	}
	public function getOdooId()
	{
		return $this->odoo_id ;
	}
	public function getName()
	{
		return $this->name ;
	}
	public function employee()
	{
		return Partner::where('is_employee',1)->where('company_id',$this->company_id)->where('odoo_id',$this->odoo_employee_id)->first();
	}	
	public function getTotal()
	{
		return $this->total_amount;
	}
	public function getState()
	{
		return $this->state ;
	}
	public function getPaymentStatus()
	{
		return $this->payment_state ;
	}
	public function getAccountNumber()
	{
		return $this->account_number ;
	}
	public function getBankName()
	{
		return $this->bank_name ;
	}
	public function getJournalId():int 
	{
		return $this->journal_id ;
	}
	public function getPaymentMethodId():int 
	{
		return $this->payment_method_line_id;
	}
	
	public function generateCashExpenseData(string $paymentDate,?int $cashExpenseCategorySubId):array 
	{
		$result = [];
		$company =$this->company;
		$request = new StoreCashExpenseRequest();
		$odooService = new OdooService($company);
		// $accountJournal = $odooService->fetchData('account.account',[],[[['id','=',239]]])[0];
		$accountJournal = $odooService->fetchData('account.journal',[],[[['id','=',$this->journal_id]]])[0];
		$type = null ;
		$accountNumber = null ;
		$deliveryBankId  = null ;
		$deliveryBranchId = null ; 
		$accountTypeId = null ;
			$receiptNumber = null ;
		if($accountJournal['type'] == 'bank'){
			$odooCode = $accountJournal['code'];
			$financialInstitutionAccount = FinancialInstitutionAccount::where('company_id',$company->id)->where('odoo_code',$odooCode)->first();
			$deliveryBankId = $financialInstitutionAccount->getFinancialInstitutionId();
			$accountNumber = $financialInstitutionAccount->getAccountNumber();
			$type = CashExpense::OUTGOING_TRANSFER;
			$accountTypeId = 27 ;// for currentAccount 
		}elseif($accountJournal['type'] == 'cash'){
			$type = CashExpense::CASH_PAYMENT;
			$deliveryBranchId = Branch::getIdFromOdooCode($company->id,$accountJournal['code']);
			$receiptNumber = HStr::generateReceiptNumber($accountJournal['code']);
		}
	
		$request->merge([
			'odoo_id'=>$this->getOdooId(),
			'cash_expense_category_name_id'=>$cashExpenseCategorySubId,
			'payment_date'=>$paymentDate,
			'expense_category_id'=>21 ,
		//	'cash_expense_category_name_id'=>143 ,
			'currency'=>Currency::getNameFromOddoId($this->odoo_currency_id),
			'type'=>$type ,
			'delivery_branch_id'=>$deliveryBranchId,
			'paid_amount'=>[
				$type => $this->getTotal()
			],
			'receipt_number'=>$receiptNumber,
			'exchange_rate'=>[
				$type=>1 
			],
			'delivery_bank_id'=>[
				$type => $deliveryBankId
			],
			'account_type'=>[
				$type=>$accountTypeId, // for current account 
			],
			'account_number'=>[
				$type=>$accountNumber
			],
			
		]);
		(new CashExpenseController)->store($company,$request);
		return $result;
	}
	public function cashExpense()
	{
		return $this->hasOne(CashExpense::class , 'odoo_id','odoo_id');
	}
}
