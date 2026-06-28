<?php

namespace App\Http\Requests;

use App\Models\MoneyPayment;
use App\Rules\MoneyPaymentCanBeDeletedRule;
use Illuminate\Foundation\Http\FormRequest;

class DeleteMoneyPaymentRequest extends FormRequest
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
		$moneyPayment = Request()->route('moneyPayment') ;
		/**
		 * @var MoneyPayment $moneyPayment 
		 */
		$company = Request()->route('company');
	
        return [
            // 'net_balance'=>[new MoneyPaymentCanBeDeletedRule($moneyPayment ,$company )]
        ];
    }
}
