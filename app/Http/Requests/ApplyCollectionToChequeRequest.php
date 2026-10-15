<?php

namespace App\Http\Requests;

use App\Models\MoneyReceived;
use App\Rules\DateMustBeGreaterThanOrEqualDate;
use App\Rules\DateMustBeLessThanOrEqualDate;
use Illuminate\Foundation\Http\FormRequest;

class ApplyCollectionToChequeRequest extends FormRequest
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
		$moneyReceived = Request()->route('moneyReceived');
		/**
		 * @var MoneyReceived $moneyReceived 
		 */
		$depositDate = $moneyReceived->getChequeDepositDate();
	
		// $openingBalanceDate = 
		
        return [
            'actual_collection_date'=>['required',
			new DateMustBeLessThanOrEqualDate(null,now(),__('Collection Date Must Be Less Than Or Equal Today')),
			new DateMustBeGreaterThanOrEqualDate(null,$depositDate , __('Collection Date Must Be Greater Than Or Equal Deposit Date')),
			// new DateMustBeGreaterThanOrEqualDate(null,$openingBalanceDate , __('Collection Date Must Be Greater Than Or Equal Bank Account Opening Balance Date')),
			
			]
        ];
    }
	public function messages()
	{
		return [
			'actual_collection_date.required'=>__('Please Insert :attribute',['attribute'=>__('Collection Date - Max Date Of Today')])
		];
		
	}
}
