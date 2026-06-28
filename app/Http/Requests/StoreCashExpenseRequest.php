<?php

namespace App\Http\Requests;

use App\Models\CashExpense;
use App\Models\FinancialInstitution;
use App\Rules\AmountCanNotBeGreaterThanEndBalanceAtPaymentDate;
use App\Rules\DateMustBeGreaterThanOrEqualDate;
use App\Rules\SettlementPlusWithoutCanNotBeGreaterNetBalance;
use App\Rules\UniqueChequeNumberRule;
use App\Rules\UniqueReceiptNumberForReceivingBranchRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCashExpenseRequest extends FormRequest 
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

	protected function prepareForValidation():array 
	{
		$paidAmounts = $this->paid_amount ;
		$paidAmounts = collect($paidAmounts)->map(function($item){
			return number_unformat($item);
		})->toArray();
		
		$this->merge([
			'paid_amount'=>$paidAmounts,
			'unapplied_amount'=>number_unformat($this->get('unapplied_amount'))
		]);
		return [];
	}
	

	
	 
	 
    public function rules()
    {
		$type = $this->type ; 
		
		$financialInstitution = null ;
		$accountTypeId = $this->input('account_type.'.$type);
		$accountNumber = $this->input('account_number.'.$type);
		$financialInstitutionId = $this->input('delivery_bank_id.'.$type);
		$openingBalanceDate = null;
		if($financialInstitutionId && $accountTypeId && $accountNumber ){
			$financialInstitution = FinancialInstitution::find($financialInstitutionId);
			$openingBalanceDate =$financialInstitution->getOpeningBalanceForAccount($accountTypeId,$accountNumber); 
		}
		
        return [
			'type'=>'required',
			'delivery_branch_id'=>$type == CashExpense::CASH_PAYMENT  ? ['required','not_in:-1'] : [],
			'paid_amount.'.$type => ['required','gt:0'],
			'account_type.'.$type => $accountTypeValidation =  $type == CashExpense::OUTGOING_TRANSFER || $type == CashExpense::PAYABLE_CHEQUE ? 'required' : 'sometimes',
			'account_number.'.$type=>$accountTypeValidation,
			'unapplied_amount'=>'sometimes|gte:0',
			'net_balance_rules'=>new SettlementPlusWithoutCanNotBeGreaterNetBalance($this->get('settlements',[])),
			
			// 'cheque_number'=>$type == CashExpense::PAYABLE_CHEQUE ? ['required',new UniqueChequeNumberRule(Request()->input('delivery_bank_id.payable_cheque'),Request()->get('current_cheque_id'),__('Cheque Number Already Exist'))] : [],
			'due_date'=>$type == CashExpense::PAYABLE_CHEQUE ? ['required',new DateMustBeGreaterThanOrEqualDate(null,$openingBalanceDate , __('Cheque Due Date Must Be Greater Than Or Equal Account Opening Date') )]:[],
			
			'cheque_number'=>$type == CashExpense::PAYABLE_CHEQUE ? ['required',new UniqueChequeNumberRule(Request()->input('delivery_bank_id.payable_cheque'),Request()->get('current_cheque_id'),__('Cheque Number Already Exist'))] : [],
			'receipt_number'=>$type== CashExpense::CASH_PAYMENT ? ['required',new UniqueReceiptNumberForReceivingBranchRule('cash_payments',$this->delivery_branch_id?:0,$this->cash_id,__('Receipt Number For This Branch Already Exist'))] : [],
			'amount_can_not_be_greater_than_end_balance_at_payment_date'=>new AmountCanNotBeGreaterThanEndBalanceAtPaymentDate($type,$this->input('paid_amount.'.$type),$this->route('company'),$this->input('account_type.'.$type),$this->input('account_number.'.$type),$financialInstitutionId,$this->payment_date,$this->delivery_branch_id),
        ];
    }
	public function messages()
	{
		$type = $this->type ; 
		return [
		
			'account_type.'.$type.'.required' => __('Please Select Account Type') ,
			'unapplied_amount.gte'=>__('Invalid Unapplied Amount'),
			
			'type.required'=>__('Please Select Money Type'),
			'paid_amount.'.$type.'.required'=>__('Please Enter Paid Amount'),
			'paid_amount.'.$type.'.gt'=>__('Paid Amount Must Be Greater Than Zero'),
			'delivery_branch_id.not_in'=>__('Please Enter New Branch Name'),
			'due_date.required'=>__('Cheque Due Date Is Required'),
			'delivery_date.required'=>__('Please Select Payment Date'),
			'cheque_number.required'=>__('Please Insert Cheque Number'),
			
		];
	}
	
}
