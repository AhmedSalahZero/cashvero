<?php

namespace App\Http\Requests;


class UpdateFullySecuredOverdraftRequest extends StoreFullySecuredOverdraftRequest
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
		$fullySecuredOverdraft = Request()->route('fullySecuredOverdraft');
		$excludeAccountNumbers = (array)$fullySecuredOverdraft->getAccountNumber();

		/**
		 * Applied from the start this time (found the hard way on Clean
		 * Overdraft — see UpdateCleanOverdraftRequest for the full
		 * story): once a renewal exists, Edit only ever submits
		 * contract_end_date/limit/rates/settlement days, so this must
		 * not require the fields it deliberately stops sending.
		 */
		if ($fullySecuredOverdraft->hasRenewals()) {
			return [
				'contract_end_date' => ['required','date'],
				'cd_or_td_lending_percentage' => ['required','numeric','gt:0'],
				'highest_debt_balance_rate' => ['nullable','numeric','gte:0'],
				'admin_fees_rate' => ['nullable','numeric','gte:0'],
				'to_be_setteled_max_within_days' => ['required','integer','gte:0'],
			];
		}

        return array_merge(
			parent::rules($excludeAccountNumbers),
			[]
		);
    }
}
