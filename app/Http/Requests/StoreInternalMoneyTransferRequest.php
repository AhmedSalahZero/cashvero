<?php

namespace App\Http\Requests;

use App\Rules\AmountCanNotBeGreaterThanEndBalanceAtPaymentDate;
use Illuminate\Foundation\Http\FormRequest;

class StoreInternalMoneyTransferRequest extends FormRequest
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
		$this->merge([
			'amount'=>number_unformat($this->get('amount')),
		]);
		return [];
	}
 
    public function rules()
    {
		$type = $this->get('type');
		$amount  = $this->get('amount');
		$accountType = $this->get('from_account_type_id');
		$accountNumber = $this->get('from_account_number');
		$financialInstitutionId = $this->get('from_bank_id');
		$date = $this->get('transfer_date');
		$branchId =$this->get('from_branch_id') ;
		$currency = $this->get('currency_to_sell');
        return [
			'transfer_date'=>'required',
			'amount'=>['required','gt:0'],
			'amount_can_not_be_greater_than_end_balance_at_payment_date'=>new AmountCanNotBeGreaterThanEndBalanceAtPaymentDate($type,$amount,$this->route('company'),$accountType,$accountNumber,$financialInstitutionId,$date,$branchId,$currency),
        ];
    }
	public function messages()
	{
		return [
			'transfer_date.required'=>__('Transaction Date Is Required'),
		];
	}
	
}
