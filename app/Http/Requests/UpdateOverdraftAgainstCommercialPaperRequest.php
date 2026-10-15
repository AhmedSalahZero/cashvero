<?php

namespace App\Http\Requests;

use App\Models\OverdraftAgainstCommercialPaper;


class UpdateOverdraftAgainstCommercialPaperRequest extends StoreOverdraftAgainstCommercialPaperRequest
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
		$overdraftAgainstCommercialPaper = Request()->route('overdraftAgainstCommercialPaper') ;
		/**
		 * @var OverdraftAgainstCommercialPaper  $overdraftAgainstCommercialPaper ;
		 */
		$excludeAccountNumbers = (array)$overdraftAgainstCommercialPaper->getAccountNumber();

		/**
		 * Applied from the start (same lesson as Clean Overdraft's
		 * UpdateCleanOverdraftRequest — see that file for the full
		 * story): once a renewal exists, Edit only ever submits the
		 * fields below, so this must not require the fields it
		 * deliberately stops sending.
		 */
		if ($overdraftAgainstCommercialPaper->hasRenewals()) {
			return [
				'contract_end_date' => ['required','date'],
				'limit' => ['required','gt:0'],
				'max_lending_limit_per_customer' => ['required','gt:0'],
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
