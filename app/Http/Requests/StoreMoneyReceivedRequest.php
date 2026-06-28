<?php

namespace App\Http\Requests;

use App\Models\MoneyReceived;
use App\Rules\AtLeaseOneSettlementMustBeExist;
use App\Rules\ContractAmountWithUnappliedAmountRule;
use App\Rules\ContractDownPaymentRule;
use App\Rules\ReceivingOrPaymentDateRule;
use App\Rules\SettlementPlusWithoutCanNotBeGreaterNetBalance;
use App\Rules\UnappliedAmountForContractAsDownPaymentRule;
use App\Rules\UniqueChequeNumberForCustomerRule;
use App\Rules\UniqueReceiptNumberForReceivingBranchRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreMoneyReceivedRequest extends FormRequest 
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
		$receivedAmounts = $this->received_amount ;
		$receivedAmounts = collect($receivedAmounts)->map(function($item){
			return number_unformat($item);
		})->toArray();
		$additionalData = [];
		
		if($this->down_payment_type == MoneyReceived::DOWN_PAYMENT_GENERAL || $this->down_payment_type == MoneyReceived::SETTLEMENT_OF_OPENING_BALANCE){
			$additionalData = [
				'contract_id'=>null,
				'sales_orders_amounts'=>[],
				'settlements'=>[],
			];
		}
		$this->merge(array_merge([
			'received_amount'=>$receivedAmounts,
			'unapplied_amount'=>number_unformat($this->get('unapplied_amount'))
		] , $additionalData));
		return [];
	}


    public function rules()
    {
		$companyId = getCurrentCompanyId();
		$type = $this->type ; 
		$partnerType = $this->partner_type;
		$receivedAmount = $this->{'received_amount.'.$type };
		$financialInstitutionId = $this->input('receiving_bank_id.'.$type);
		
		$accountTypeId = $this->input('account_type.'.$type);
		$accountNumber = $this->input('account_number.'.$type);
		
			
        return [
			'receiving_date'=>['required',new ReceivingOrPaymentDateRule($companyId,$type,[MoneyReceived::CASH_IN_BANK,MoneyReceived::INCOMING_TRANSFER],[MoneyReceived::CASH_IN_SAFE],$financialInstitutionId,$accountTypeId,$accountNumber)],
			'customer_id'=>'required',
			'type'=>'required',
			'receiving_branch_id'=>$type == MoneyReceived::CASH_IN_SAFE  ? ['required','not_in:-1'] : [],
			'received_amount.'.$type => ['required','gt:0'],
			'account_type.'.$type => $accountTypeValidation =  $type == MoneyReceived::INCOMING_TRANSFER || $type == MoneyReceived::CASH_IN_BANK ? 'required' : 'sometimes',
			'account_number.'.$type=>$accountTypeValidation,
			'unapplied_amount'=>['sometimes','gte:0'],
			'contract_id'=>$partnerType == 'is_customer'?[new ContractAmountWithUnappliedAmountRule($this->get('unapplied_amount',0),$this->get('contract_id',0))]:[],
			'net_balance_rules'=>new SettlementPlusWithoutCanNotBeGreaterNetBalance($this->get('settlements',[])),
			'settlements'=>$partnerType =='is_customer' ? new AtLeaseOneSettlementMustBeExist($this->get('settlements',[])) : [],
			'due_date'=>$type == MoneyReceived::CHEQUE  ? ['required'] : [],
			'cheque_number'=>$type == MoneyReceived::CHEQUE  ? ['required',new UniqueChequeNumberForCustomerRule(Request()->get('drawee_bank_id'),Request('current_cheque_id'),__('Cheque Number Already Exist'))] : [],
			'receipt_number'=>$type== MoneyReceived::CASH_IN_SAFE ? ['required',new UniqueReceiptNumberForReceivingBranchRule('cash_in_safes',$this->receiving_branch_id?:0,$this->cash_id,__('Receipt Number For This Branch Already Exist'))] : [],
	
			'sales_orders_amounts'=>$partnerType =='is_customer' ? [new UnappliedAmountForContractAsDownPaymentRule($this->unapplied_amount?:0,$this->is_down_payment,$receivedAmount)] : [] ,
			'downPayment_over_contract'=>[new ContractDownPaymentRule($receivedAmount,true)]
        ];
    }
	
	public function messages()
	{
		$type = $this->type ; 
		return [
			'receiving_branch_id.not_in'=>__('Please Enter New Branch Name'),
			'account_type.'.$type.'.required' => __('Please Select Account Type') ,
			'account_number.'.$type.'.required' => __('Please Select Account Number') ,
			'unapplied_amount.gte'=>__('Invalid Unapplied Amount'),
			'type.required'=>__('Please Select Money Type'),
			'received_amount.'.$type.'.required'=>__('Please Enter Received Amount'),
			'received_amount.'.$type.'.gt'=>__('Received Amount Must Be Greater Than Zero'),
			'due_date.required'=>__('Cheque Due Date Is Required'),
			'receiving_date.required'=>__('Please Select Receiving Date'),
			'cheque_number.required'=>__('Please Insert Cheque Number'),
		];
	}
	
}
