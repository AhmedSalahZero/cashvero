<?php

namespace App\Http\Controllers;


use App\Jobs\CheckDueAndPastedInvoicesJob;
use App\Jobs\ImportForeignExchangeRates;
use App\Jobs\ReactiveCurrentAccountStatement;
use App\Models\Company;

use App\Models\User;
use App\Traits\GeneralFunctions;
use Auth;
use Illuminate\Http\Request;



class HomeController extends Controller
{
	use GeneralFunctions;

	
	public function index(Request $request)
	{
		$user =  Auth::user();
		/**
		 * @var User $user
		 */
		$companies = $user->companies;
		if (count($user->companies) > 1) {
			return view('client_view.home', compact('companies'));
		} else {
			if(count($user->companies) == 0){
				auth()->logout();
				return redirect()->route('login');
			}
			$company = $user->companies[0];
			return view('client_view.homePage', compact('company'));
		}
	}
	public function redirectFun(Company $company)
	{
		return   redirect()->route('view.customer.invoice.dashboard.cash', [$company]);
	}
	public function welcomePage(Request $request, Company $company)
	{
		if($company->hasCashVero()){
			dispatch_sync(new ReactiveCurrentAccountStatement($company->id));
		}
		if($company->hasCashVero()){
			dispatch_sync(new CheckDueAndPastedInvoicesJob($company->id));
		}
		if($company->hasOdooIntegrationCredentials()){
			dispatch_sync(new ImportForeignExchangeRates($company->id));
		}
		
		return view('client_view.homePage', compact('company'));
	}




	
	
}
