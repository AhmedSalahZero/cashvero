<?php

namespace App\Http\Controllers;

use App\Services\Api\OdooSync;

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
			// Never the raw text: a connection failure names the internal
			// Odoo host and quotes a PHP function, neither of which the
			// reader may see or can act on. The full detail is logged.
			session()->flash('fail', OdooSync::userFacingMessage($e));

			return back();
		}

		return redirect()->back()->with('success', __('Partners Reading Has Been Completed'));
	}
}
