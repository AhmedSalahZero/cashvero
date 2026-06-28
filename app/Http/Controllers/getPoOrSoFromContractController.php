<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contract;
use Illuminate\Http\Request;

class getPoOrSoFromContractController extends Controller
{
	public function handle(Request $request , Company $company)
	{
		$contractId = $request->get('contractId');
		$contract = Contract::find($contractId);
		
		return response()->json([
			'sales_orders'=>$contract ? $contract->salesOrders : [],
			'purchase_orders'=>$contract ? $contract->purchasesOrders :[]
		]);
	}
}
