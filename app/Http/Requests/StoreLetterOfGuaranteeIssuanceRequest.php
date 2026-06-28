<?php

namespace App\Http\Requests;

use App\Rules\LgTermAmountRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLetterOfGuaranteeIssuanceRequest extends FormRequest
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
			'transaction_name'=>['required'],
			'lg_fees_and_commission_account_id'=>'required',
			'issuance_date'=>['required'],
			'lg_amount'=>['required','gt:0'],
			'cash_cover_amount'=>[new LgTermAmountRule($this->get('category_name'),$this->get('cash_cover_deducted_from_account_type'),$this->get('lg_fees_and_commission_account_id'),$this->get('issuance_date'),$this->get('cash_cover_amount',0),$this->get('lg_commission_amount',0),$this->get('min_lg_commission_fees',0),$this->get('issuance_fees',0),$this->get('company_id'),$this->get('financial_institution_id'))]
        ];
    }
}
