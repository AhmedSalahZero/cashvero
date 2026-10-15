<?php

namespace App\Http\Controllers;

use App\Services\Api\OdooSync;

use App\Models\Company;
use App\Services\Api\OdooService;
use Illuminate\Http\Request;


class ReadOdooInvoices extends Controller
{
	public function handle(Request $request,  Company $company)
	{
		$odoo = new OdooService($company);
		$startDate = $request->get('odoo_start_date');
		$endDate = $request->get('odoo_end_date');
		try{
			$odoo->startImportContracts($startDate,$endDate,$company->id);
			$odoo->startImportInvoices($startDate,$endDate,$company->id);
		}catch(\Exception $e){
			// Never the raw text: a connection failure names the internal
			// Odoo host and quotes a PHP function, neither of which the
			// reader may see or can act on. The full detail is logged.
			session()->flash('fail', OdooSync::userFacingMessage($e));
			return back();
		}
		return redirect()->back()->with('success',__('Invoices Reading Has Been Completed'));
		
	}
}
