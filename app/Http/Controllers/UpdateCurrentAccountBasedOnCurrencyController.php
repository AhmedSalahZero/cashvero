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
		
		return response()->json([
			'status'=>true ,
			'message'=>'success',
			'data'=>$financialInstitution->accounts->where('currency',$request->get('currency'))->map(function(FinancialInstitutionAccount $account){
				return [
					$account->getId() => $account->getAccountNumber()
				];
			})->values()
		]);
	}
}
