<?php

namespace App\Http\Requests;

use App\Rules\AmountCanNotBeGreaterThanEndBalanceAtPaymentDate;
use Illuminate\Foundation\Http\FormRequest;

class StoreBuyOrSellCurrencyRequest extends FormRequest
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
			'currency_to_sell_amount'=>number_unformat($this->get('currency_to_sell_amount')),
			'exchange_rate'=>number_unformat($this->get('exchange_rate')),
			'currency_to_buy_amount'=>number_unformat($this->get('currency_to_buy_amount')),
		]);
		return [];
	}
    public function rules()
    {
		$type = $this->get('type');
		$amount  = $this->get('currency_to_sell_amount');
		$accountType = $this->get('from_account_type_id');
		$accountNumber = $this->get('from_account_number');
		$financialInstitutionId = $this->get('from_bank_id');
		$date = $this->get('transaction_date');
		$branchId =$this->get('from_branch_id') ;
		$currency = $this->get('currency_to_sell');

        return [
			'transaction_date'=>'required',
			'currency_to_sell_amount'=>['required','gt:0'],
			'amount_can_not_be_greater_than_end_balance_at_payment_date'=>new AmountCanNotBeGreaterThanEndBalanceAtPaymentDate($type,$amount,$this->route('company'),$accountType,$accountNumber,$financialInstitutionId,$date,$branchId,$currency),
        ];
    }
	public function messages()
	{
		return [
			'transaction_date.required'=>__('Transaction Date Is Required'),
		];
	}
}
