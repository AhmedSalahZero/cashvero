<?php

namespace App\Http\Requests;

use App\Rules\ValidAmountRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOpeningBalanceRequest extends FormRequest
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

        return [
			'date'=>'required',
            'cash-in-safe.*.received_branch_id'=>'required',
            'cash-in-safe.*.received_amount'=>['required',new ValidAmountRule],
            'cash-in-safe.*.currency'=>'required|string',
            'cash-in-safe.*.exchange_rate'=>'required|gt:0',
			
			
			'cheque.*.customer_id'=>'required|numeric',
			'cheque.*.currency'=>'required|string',
			'cheque.*.due_date'=>'required',
			'cheque.*.drawee_bank_id'=>'required|numeric',
			'cheque.*.received_amount'=>['required',new ValidAmountRule],
			'cheque.*.cheque_number'=>'required',
			'cheque.*.exchange_rate'=>'required|gt:0',
			
			
			'cheque-under-collection.*.customer_id'=>'required|numeric',
			'cheque-under-collection.*.currency'=>'required|string',
			'cheque-under-collection.*.due_date'=>'required',
			'cheque-under-collection.*.drawee_bank_id'=>'required|numeric',
			'cheque-under-collection.*.received_amount'=>['required',new ValidAmountRule],
			'cheque-under-collection.*.cheque_number'=>'required',
			'cheque-under-collection.*.exchange_rate'=>'required|gt:0',
			'cheque-under-collection.*.deposit_date'=>'required',
			'cheque-under-collection.*.drawl_bank_id'=>'required|numeric',
			'cheque-under-collection.*.account_type'=>'required|numeric',
			'cheque-under-collection.*.account_number'=>'required',
			'cheque-under-collection.*.clearance_days'=>'numeric',
			
			
			'payable_cheque.*.supplier_id'=>'required|numeric',
			'payable_cheque.*.currency'=>'required|string',
			'payable_cheque.*.due_date'=>'required',
			'payable_cheque.*.paid_amount'=>['required',new ValidAmountRule],
			'payable_cheque.*.cheque_number'=>'required',
			'payable_cheque.*.exchange_rate'=>'required|gt:0',
			'payable_cheque.*.delivery_bank_id'=>'required|numeric',
			'payable_cheque.*.account_type'=>'required|numeric',
			'payable_cheque.*.account_number'=>'required',
        ];
    }
}
