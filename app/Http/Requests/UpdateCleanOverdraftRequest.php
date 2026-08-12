<?php

namespace App\Http\Requests;


class UpdateCleanOverdraftRequest extends StoreCleanOverdraftRequest
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
		$cleanOverdraft = Request()->route('cleanOverdraft');
		$excludeAccountNumbers = (array)$cleanOverdraft->getAccountNumber();

		/**
		 * ⚠️ REAL BUG FIXED HERE (client-flagged, 2026-08-11): once a
		 * facility has an active renewal, Form.vue intentionally stops
		 * sending contract_start_date / account_number / odoo_code /
		 * currency / outstanding_balance / balance_date /
		 * outstanding_breakdowns at all (see the comment in
		 * CleanOverdraftController::update() and Form.vue's submit()).
		 * This class kept inheriting StoreCleanOverdraftRequest's rules
		 * unconditionally, which still marked several of those as
		 * 'required' — so a perfectly legitimate save with nothing
		 * actually wrong on screen failed validation for fields the
		 * form was never going to submit in the first place.
		 */
		if ($cleanOverdraft->hasRenewals()) {
			return [
				'contract_end_date' => ['required','date'],
				'limit' => ['required','gt:0'],
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
