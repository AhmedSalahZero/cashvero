<?php

namespace App\Http\Requests;

use App\Models\OverdraftAgainstAssignmentOfContract;


class UpdateOverdraftAgainstAssignmentOfContractRequest extends StoreOverdraftAgainstAssignmentOfContractRequest
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
		$odAgainstAssignmentOfContract = Request()->route('odAgainstAssignmentOfContract') ;
		/**
		 * @var OverdraftAgainstAssignmentOfContract  $odAgainstAssignmentOfContract ;
		 */
		$excludeAccountNumbers = (array)$odAgainstAssignmentOfContract->getAccountNumber();

		/**
		 * Applied from the start this time (found the hard way on Clean
		 * Overdraft — see UpdateCleanOverdraftRequest for the full
		 * story): once a renewal exists, Edit only ever submits the
		 * fields below, so this must not require the fields it
		 * deliberately stops sending.
		 */
		if ($odAgainstAssignmentOfContract->hasRenewals()) {
			return [
				'contract_end_date' => ['required','date'],
				'limit' => ['required','gt:0'],
				'max_lending_limit_per_contract' => ['required','gt:0'],
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
