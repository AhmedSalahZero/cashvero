<?php

namespace App\Http\Requests;

use App\Models\FinancialInstitution;
use App\Models\MoneyReceived;
use App\Rules\DateMustBeGreaterThanOrEqualDate;
use App\Rules\DateMustBeLessThanOrEqualDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

class SendToUnderCollectionChequeRequest extends FormRequest
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
		$moneyReceivedIds = Request()->get('cheques');
		$ids = is_array($moneyReceivedIds) ? $moneyReceivedIds : explode(',', (string) $moneyReceivedIds);
		$ids = array_values(array_filter(array_map('intval', $ids)));
		$firstMoneyReceived = $ids === [] ? null : MoneyReceived::whereIn('id', $ids)->orderByDesc('receiving_date')->first();
		$greatestReceivingDate = $firstMoneyReceived ? $firstMoneyReceived->receiving_date : null;
		$drawlBankId = Request()->input('drawl_bank_id',Arr::first(Request()->input('receiving_bank_id',[]))) ;
		$financialInstitution  = FinancialInstitution::find($drawlBankId);
		$accountType  = Request()->get('account_type') ; 
		$accountType = is_array($accountType) ? Arr::first($accountType) : $accountType ;
		$openingBalanceDate = null;
		$accountNumber = Request()->get('account_number');
		$accountNumber = is_array($accountNumber) ? Arr::first($accountNumber) : $accountNumber;
		
		if ($accountType && $financialInstitution) {
			$openingBalanceDate = $financialInstitution->getOpeningBalanceForAccount($accountType, $accountNumber);
		}
		$depositRules = [
			'bail',
			'required',
			new DateMustBeLessThanOrEqualDate(null, now(), __('Dates Must Be Less Than Or Equal To Today')),
		];
		if ($greatestReceivingDate !== null) {
			$depositRules[] = new DateMustBeGreaterThanOrEqualDate(null, $greatestReceivingDate, __('Deposit Date Must Be Greater Than Or Equal Receiving Date'));
		}
		if ($openingBalanceDate !== null && $openingBalanceDate !== '') {
			$depositRules[] = new DateMustBeGreaterThanOrEqualDate(null, $openingBalanceDate, __('Deposit Date Must Be Greater Than Or Equal Account Opening Balance Date'));
		}

        return [
			'cheques' => [
				function ($attribute, $value, $fail) use ($ids, $firstMoneyReceived) {
					if ($ids === [] || ! $firstMoneyReceived) {
						$fail(__('No valid cheques were sent. Close the modal and use Send Under Collection from the row again.'));
					}
				},
			],
			'account_type'=>['bail','required'],
			'drawl_bank_id'=>['bail','sometimes','required','exists:financial_institutions,id'],
            'deposit_date'=> $depositRules,
        ];
    }
	public function withValidator($validator)
    {
        $validator->sometimes(['drawl_bank_id', 'deposit_date'], 'required', function ($input) {
            return !is_null($input->account_type) && $input->account_type !== '';
        });
    }
	public function messages()
	{
	
		return [
			'drawl_bank_id.required'=>__('Please Select Drawl Bank'),
			'drawl_bank_id.exists'=>__('This Bank Not Exist'),
			'deposit_date.required'=>__('Please Select :attribute',['attribute'=>__('Deposit Date - Max Date Of Today')]),
			'account_type.required'=>__('Please Select :attribute',['attribute'=>__('Account Type')]),
			// ''=>
		];
		
	}
}
