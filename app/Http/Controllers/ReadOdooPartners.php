<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\Api\OdooService;
use Illuminate\Http\Request;

class ReadOdooPartners extends Controller
{
	public function handle(Request $request, Company $company)
	{
		$odoo = new OdooService($company);
		$startDate = $request->get('odoo_start_date');
		$endDate = $request->get('odoo_end_date');

		// ⚠️ REAL BUG FIXED HERE (2026-07-24 audit, Stage 5):
		// getPartners() previously ran OUTSIDE an empty try/catch, so
		// Odoo/network failures became raw Laravel error pages instead
		// of the friendly flash used by ReadOdooContracts/Invoices.
		try {
			$odoo->getPartners($startDate, $endDate, $company->id);
		} catch (\Exception $e) {
			session()->put('fail', $e->getMessage());

			return back();
		}

		return redirect()->back()->with('success', __('Partners Reading Has Been Completed'));
	}
}
