<?php

namespace App\Http\Requests;

use App\Models\FinancialInstitutionAccount;
use App\Rules\AtLeastOneMainFunctionalCurrencyExistAtAccountRule;
use App\Rules\DateMustBeGreaterThanOrEqualDate;
use App\Rules\UniqueAccountNumberRule;
use App\Rules\DateCanNotBeAfterAnyStatementRule;


class UpdateCurrentAccountRequest extends StoreCurrentAccountRequest
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
		$financialInstitutionAccount = Request()->route('financialInstitutionAccount') ;
		$company = Request()->route('company') ;
		$mainFunctionalCurrency = $company->getMainFunctionalCurrency();

		/**
		 * @var FinancialInstitutionAccount $financialInstitutionAccount 
		 */

		$excludeAccountNumbers = (array)$financialInstitutionAccount->getAccountNumber();
		// $balanceDate = $financialInstitutionAccount->getBalanceDate() ;
		$balanceDate = Request('balance_date');
		$financialInstitutionId = $financialInstitutionAccount->getFinancialInstitutionId();
        return [
			'beginning_balance_rule'=>new DateCanNotBeAfterAnyStatementRule($financialInstitutionAccount->id,$balanceDate),
			// Business rule (confirmed with project owner, 2026-07-24): same
			// floor as when opening a brand-new account — an existing
			// account's balance date can't be edited to a date earlier than
			// the company's Opening Balance Date.
			'balance_date'=>['required','date',new DateMustBeGreaterThanOrEqualDate(
				null,
				$company->opening_balance_date,
				__('Balance Date Cannot Be Earlier Than The Company Opening Balance Date'),
				true
			)],
			'account_number'=>new UniqueAccountNumberRule($excludeAccountNumbers),
			'account_interests.*.start_date'=>['required',new DateMustBeGreaterThanOrEqualDate(null,$balanceDate,__('Interest Date Must Be Greater Than Or Equal Beginning Balance Date'),true)],
			'currency'=>['required',new AtLeastOneMainFunctionalCurrencyExistAtAccountRule($this->old_currency,$mainFunctionalCurrency,$financialInstitutionId)]
		];
    }
}
