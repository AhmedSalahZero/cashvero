<?php

namespace App\Http\Requests;

use App\Models\FinancialInstitution;
use App\Models\MoneyPayment;
use App\Rules\AmountCanNotBeGreaterThanEndBalanceAtPaymentDate;
use App\Rules\AtLeaseOneSettlementMustBeExist;
use App\Rules\ContractDownPaymentRule;
use App\Rules\DateMustBeGreaterThanOrEqualDate;
use App\Rules\ReceivingOrPaymentDateRule;
use App\Rules\SettlementPlusWithoutCanNotBeGreaterNetBalance;
use App\Rules\UnappliedAmountForContractAsDownPaymentRule;
use App\Rules\UniqueChequeNumberRule;
use App\Rules\UniqueReceiptNumberForReceivingBranchRule;
use App\Rules\ValidAllocationsRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMoneyPaymentRequest extends FormRequest 
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
		
		
		$additionalData = [];
		if($this->down_payment_type == MoneyPayment::DOWN_PAYMENT_GENERAL || $this->down_payment_type == MoneyPayment::SETTLEMENT_OF_OPENING_BALANCE){
			$additionalData = [
				'contract_id'=>null,
				'purchases_orders_amounts'=>[],
				'settlements'=>[],
			];
		}
		$this->merge(array_merge([
			'paid_amount'=>$paidAmounts,
			'unapplied_amount'=>number_unformat($this->get('unapplied_amount'))
		] , $additionalData));
		
		return [];
	}
	

   
    public function rules()
    {
		$companyId = getCurrentCompanyId();
		$type = $this->type ; 
		$paidAmount = $this->{'paid_amount.'.$type };
		$partnerType = $this->partner_type;
		
		
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
			'supplier_id'=>'required',
			'type'=>'required',
			'delivery_branch_id'=>$type == MoneyPayment::CASH_PAYMENT  ? ['required','not_in:-1'] : [],
			'paid_amount.'.$type => ['required','gt:0'],
			'account_type.'.$type => $accountTypeValidation = $type == MoneyPayment::OUTGOING_TRANSFER || $type == MoneyPayment::PAYABLE_CHEQUE ? 'required' : 'sometimes',
			'account_number.'.$type=>$accountTypeValidation,
			'delivery_date'=>['required',new ReceivingOrPaymentDateRule($companyId,$type,[MoneyPayment::OUTGOING_TRANSFER],[MoneyPayment::CASH_PAYMENT],$financialInstitutionId,$accountTypeId,$accountNumber)],
			'unapplied_amount'=>'sometimes|gte:0',
			'net_balance_rules'=>new SettlementPlusWithoutCanNotBeGreaterNetBalance($this->get('settlements',[])),
			'settlements'=>$partnerType =='is_supplier' ? new AtLeaseOneSettlementMustBeExist($this->get('settlements',[])) : [],
			'cheque_number'=>$type == MoneyPayment::PAYABLE_CHEQUE ? ['required',new UniqueChequeNumberRule(Request()->input('delivery_bank_id.payable_cheque'),Request()->get('current_cheque_id'),__('Cheque Number Already Exist'))] : [],
			'due_date'=>$type == MoneyPayment::PAYABLE_CHEQUE ? ['required',new DateMustBeGreaterThanOrEqualDate(null,$openingBalanceDate , __('Cheque Due Date Must Be Greater Than Or Equal Account Opening Date') )]:[],
			'receipt_number'=>$type== MoneyPayment::CASH_PAYMENT ? ['required',new UniqueReceiptNumberForReceivingBranchRule('cash_payments',$this->delivery_branch_id?:0,$this->cash_id,__('Receipt Number For This Branch Already Exist'))] : [],
			'purchases_orders_amounts'=>$partnerType =='is_supplier' ? [new UnappliedAmountForContractAsDownPaymentRule($this->unapplied_amount?:0,$this->is_down_payment,$paidAmount)] : [], 
			'allocations'=>[new ValidAllocationsRule()],
			'amount_can_not_be_greater_than_end_balance_at_payment_date'=>new AmountCanNotBeGreaterThanEndBalanceAtPaymentDate($type,$this->input('paid_amount.'.$type),$this->route('company'),$this->input('account_type.'.$type),$this->input('account_number.'.$type),$financialInstitutionId,$this->delivery_date,$this->delivery_branch_id,null),
			'downPayment_over_contract'=>[new ContractDownPaymentRule($paidAmount,false)]
        ];
    }
	public function messages()
	{
		$type = $this->type ; 
		return [
		
			'account_type.'.$type.'.required' => __('Please Select Account Type') ,
			'account_number.'.$type.'.required' => __('Please Select Account Number') ,
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
