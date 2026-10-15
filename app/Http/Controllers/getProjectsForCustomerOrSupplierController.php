<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Partner;
use Illuminate\Http\Request;

class getProjectsForCustomerOrSupplierController extends Controller
{
    public function handle(Request $request , Company $company)
	{
		$partnerId = $request->get('customerOrSupplierId') ;
		$partner = Partner::find($partnerId);
		$contracts = $partner->contracts ;
		// foreach($contracts as $contract){
		// 	$contract->sales_orders_as_array = $contract->salesOrders->pluck('so_number','id');
		// 	$contract->purchases_orders_as_array = $contract->purchasesOrders->pluck('po_number','id');
		// }
		return response()->json([
			'projects'=>$contracts
		]);
	}
}
