<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\Api\OdooService;
use Illuminate\Http\Request;


class ReadOdooContracts extends Controller
{
	public function handle(Request $request,  Company $company)
	{
		$odoo = new OdooService($company);
		$startDate = $request->input('odoo_start_date');
		$endDate = $request->input('odoo_end_date');
		try{
			$odoo->startImportContracts($startDate,$endDate,$company->id);
		}catch(\Exception $e){
			session()->put('fail', $e->getMessage());
			return back();
		}
		return redirect()->back()->with('success',__('Contracts Reading Has Been Completed'));
		
	}
}
