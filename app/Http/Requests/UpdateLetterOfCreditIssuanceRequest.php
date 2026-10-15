<?php

namespace App\Http\Requests;


class UpdateLetterOfCreditIssuanceRequest extends StoreLetterOfCreditIssuanceRequest
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
        return array_merge(
			Parent::rules(),
			[]
		);
    }

    /**
     * Human names for the validation messages.
     *
     * Without these Laravel builds messages from the raw column name —
     * "The lc amount field is required", "The financial institution id
     * field is required" — which is what the form's error summary shows
     * the user verbatim. The labels here match the form's own field
     * captions so the message names the control the person is looking at.
     */
    public function attributes(): array
    {
        return [
            'transaction_name' => __('Transaction Name'),
            'financial_institution_id' => __('Bank'),
            'lc_facility_id' => __('LC Facility'),
            'lc_type' => __('LC Type'),
            'lc_code' => __('LC Code'),
            'lc_amount' => __('LC Amount'),
            'lc_currency' => __('LC Currency'),
            'lc_cash_cover_currency' => __('Cash Cover Currency'),
            'exchange_rate' => __('Exchange Rate'),
            'issuance_date' => __('Issuance Date'),
            'due_date' => __('Due Date'),
            'lc_duration_days' => __('LC Duration (Days)'),
            'partner_id' => __('Beneficiary Name'),
            'contract_id' => __('Contract Reference'),
            'new_purchase_order_number' => __('New PO'),
            'purchase_order_id' => __('Purchase Order'),
            'lc_fees_and_commission_account_id' => __('LC Fees & Commission Account'),
            'cash_cover_deducted_from_account_type' => __('Cash Cover Account Type'),
            'cash_cover_deducted_from_account_id' => __('Cash Cover Account'),
            'cash_cover_rate' => __('Cash Cover Rate'),
            'lc_commission_rate' => __('LC Commission Rate'),
            'issuance_fees' => __('Issuance Fees'),
            'min_lc_commission_fees' => __('Min LC Commission Fees'),
        ];
    }
}
