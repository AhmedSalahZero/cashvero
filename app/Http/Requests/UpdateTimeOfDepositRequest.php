<?php

namespace App\Http\Requests;

use App\Models\TimeOfDeposit;


class UpdateTimeOfDepositRequest extends StoreTimeOfDepositRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true ;
    }

    public function rules(array $excludeAccountNumbers = [])
    {
		$timeOfDeposit = Request()->route('timeOfDeposit') ;
		/**
		 * @var TimeOfDeposit $timeOfDeposit 
		 */
		$excludeAccountNumbers = (array)$timeOfDeposit->getAccountNumber();
        return array_merge(
			parent::rules($excludeAccountNumbers),
			[]
		);
    }
}
