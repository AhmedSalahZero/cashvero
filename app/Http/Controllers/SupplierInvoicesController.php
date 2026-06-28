<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Partner;
use Illuminate\Http\Request;

class SupplierInvoicesController extends Controller
{
	public function getSupplierInvoicesForSupplier(Request $request,Company  $company)
	{
		$supplierId = $request->get('supplierId');
		$currencyName = $request->get('currencyName');
		$partner=Partner::find($supplierId);
		$supplierInvoices = $partner->SupplierInvoice->where('currency',$currencyName)->pluck('invoice_number','id')->toArray();
		return response()->json([
			'supplierInvoices'=>$supplierInvoices
		]);
	}
}
