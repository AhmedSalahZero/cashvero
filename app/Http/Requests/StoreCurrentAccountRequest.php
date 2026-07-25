<?php

namespace App\Http\Requests;

use App\Rules\DateMustBeGreaterThanOrEqualDate;
use App\Rules\UniqueAccountNumberRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCurrentAccountRequest extends FormRequest
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
        $company = currentCompany();

        return [
            'accounts.*.account_number'=>new UniqueAccountNumberRule($excludeAccountNumbers),
            // Same business rule as StoreFinancialInstitutionRequest (new
            // institution's first accounts): balance date can't be earlier
            // than the company's Opening Balance Date. 'nullable' preserves
            // storeNewAccounts()'s existing behavior of silently skipping
            // account creation when no balance date is given at all.
            'accounts.*.balance_date'=>['nullable','date',new DateMustBeGreaterThanOrEqualDate(
                null,
                $company?->opening_balance_date,
                __('Balance Date Cannot Be Earlier Than The Company Opening Balance Date'),
                true
            )],
        ];
    }
}
