<?php

namespace App\Http\Requests;

use App\Models\Company;
use App\Rules\DateMustBeGreaterThanOrEqualDate;
use App\Rules\FinancialInstitutions\AccountMustHaveAtLeastOneMainCurrencyRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreFinancialInstitutionRequest extends FormRequest
{
    use \App\Http\Requests\Concerns\ValidatesShareholderOwnership;

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
        $company = currentCompany();

        return $this->shareholderOwnershipRules('accounts.*.') + [
            'accounts'=>['sometimes','required',new AccountMustHaveAtLeastOneMainCurrencyRule($company)],
            // Business rule (confirmed with project owner, 2026-07-24): a
            // bank account's balance date can never be earlier than the
            // company's own Opening Balance Date — applies when opening a
            // brand-new account here, and mirrored on StoreCurrentAccountRequest
            // (add-account flow) and UpdateCurrentAccountRequest (editing an
            // existing account's balance date).
            'accounts.*.balance_date'=>['nullable','date',new DateMustBeGreaterThanOrEqualDate(
                null,
                $company?->opening_balance_date,
                __('Balance Date Cannot Be Earlier Than The Company Opening Balance Date'),
                true
            )],
        ];
    }

    public function messages()
    {
        return $this->shareholderOwnershipMessages('accounts.*.');
    }
}
