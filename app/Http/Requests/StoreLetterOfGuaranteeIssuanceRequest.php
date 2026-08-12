<?php

namespace App\Http\Requests;

use App\Enums\LgTypes;
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
			'cash_cover_amount'=>[new LgTermAmountRule($this->get('category_name'),$this->get('cash_cover_deducted_from_account_type'),$this->get('lg_fees_and_commission_account_id'),$this->get('issuance_date'),$this->get('cash_cover_amount',0),$this->get('lg_commission_amount',0),$this->get('min_lg_commission_fees',0),$this->get('issuance_fees',0),$this->get('company_id'),$this->get('financial_institution_id'))],
			// Bid Bond is not linked to a contract; every other LG type must be.
			// NOTE: the form sends the literal string "null" for an empty
			// select (see HasBasicStoreRequest::storeBasicForm), so a plain
			// 'required' rule wouldn't catch it — this closure treats that
			// sentinel the same as truly empty.
			'contract_id'=>[function ($attribute, $value, $fail) {
				$isBidBond = $this->get('lg_type') === LgTypes::BID_BOND;
				if (!$isBidBond && (blank($value) || $value === 'null')) {
					$fail(__('Contract is required for this LG type.'));
				}
			}],
        ];
    }
}
