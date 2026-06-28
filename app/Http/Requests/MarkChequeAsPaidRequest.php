<?php

namespace App\Http\Requests;

use App\Models\MoneyPayment;
use App\Rules\AmountCanNotBeGreaterThanEndBalanceAtPaymentDate;
use App\Rules\DateMustBeGreaterThanOrEqualDate;
use Illuminate\Foundation\Http\FormRequest;

class MarkChequeAsPaidRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }


    public function rules()
    {
		$isCashExpense = Request()->route()->getName() == 'cash.expense.payable.cheque.mark.as.paid' ;
		$modelName = $isCashExpense ? 'App\Models\CashExpense' : 'App\Models\MoneyPayment';
		$tableName = $isCashExpense ? 'cash_expenses' : 'money_payments';
		$foreignId = $isCashExpense ? 'cash_expense_id' : 'money_payment_id';
		
		$ids = Request()->get('cheques');
		$ids = is_array($ids) ? $ids :  explode(',',$ids);
		$row = $modelName::whereIn($tableName.'.id',$ids)
		->join('payable_cheques','payable_cheques.'.$foreignId,'=',$tableName.'.id')->orderByDesc('due_date')
		->selectRaw($tableName.'.*,payable_cheques.'.$foreignId.',payable_cheques.due_date')
		->first() ;
		$dueDate = data_get($row, 'due_date');
		
        return [
			'actual_payment_date'=>['required',new DateMustBeGreaterThanOrEqualDate(null,$dueDate,__('Payment Date Must Be Greater Than Or Equal Cheque Due Date'))],
			'amount_can_not_be_greater_than_end_balance_at_payment_date'=>new AmountCanNotBeGreaterThanEndBalanceAtPaymentDate('ACTUAL_PAYMENT_DATE',$row->getAmount(),$row->company,$row->getAccountTypeId(),$row->getAccountNumber(),$row->getFinancialInstitutionId(),$this->actual_payment_date,null),
        ];
    }
}
