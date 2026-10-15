<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionAccount;
use Illuminate\Http\Request;

class UpdateCurrentAccountBasedOnCurrencyController extends Controller
{
	public function index(Request $request , Company $company,FinancialInstitution $financialInstitution)
	{
		$currency = $request->get('currency');

		$activeAccounts = $financialInstitution->accounts
			->where('currency', $currency)
			->filter(fn (FinancialInstitutionAccount $account) => $account->isActive());

		$selectedIds = array_unique(array_filter([
			(int) $request->get('selected_account_id'),
			(int) $request->get('selected_maturity_account_id'),
		]));

		$lockedSelected = $financialInstitution->accounts
			->where('currency', $currency)
			->filter(fn (FinancialInstitutionAccount $account) => in_array($account->getId(), $selectedIds, true) && ! $account->isActive());

		$accounts = $activeAccounts->merge($lockedSelected)->unique('id');

		return response()->json([
			'status'=>true ,
			'message'=>'success',
			'data'=>$accounts->map(function(FinancialInstitutionAccount $account){
				return [
					$account->getId() => $account->getAccountNumber()
				];
			})->values()
		]);
	}
}
