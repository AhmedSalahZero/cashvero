<?php

namespace App\Http\Requests;

use App\Rules\ActiveFinancialInstitutionAccountRule;
use App\Rules\AmountCanNotBeGreaterThanEndBalanceAtPaymentDate;
use App\Rules\DateMustBeLessThanOrEqualDate;
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
		$companyId = $this->route('company')->id;
        return [
			/**
			 * * ما ينفعش نعمل تحويل داخلي بتاريخ بعد النهاردة
			 */
			'transfer_date'=>['required',new DateMustBeLessThanOrEqualDate(null,now(),__('Transaction Date Can Not Be Greater Than Today'))],
			'amount'=>['required','gt:0'],
			'from_account_number'=>[new ActiveFinancialInstitutionAccountRule($companyId, $accountType, $accountNumber, $financialInstitutionId)],
			'to_account_number'=>[new ActiveFinancialInstitutionAccountRule($companyId, $this->get('to_account_type_id'), $this->get('to_account_number'), $this->get('to_bank_id'))],
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
