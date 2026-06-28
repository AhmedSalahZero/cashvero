<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\Api\OdooService;
use Illuminate\Http\Request;


class ReadOdooPartners extends Controller
{
	public function handle(Request $request,  Company $company)
	{
		$odoo = new OdooService($company);
		$startDate = $request->get('odoo_start_date');
		$endDate = $request->get('odoo_end_date');
		$odoo->getPartners($startDate,$endDate,$company->id);
		
		try{
		}catch(\Exception $e){
			session()->put('fail', $e->getMessage());
			return back();
		}
		return redirect()->back()->with('success',__('Partners Reading Has Been Completed'));
		
	}
}
