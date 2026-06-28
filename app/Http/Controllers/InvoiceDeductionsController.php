<?php
namespace App\Http\Controllers;

use App\Http\Requests\UpdateInvoiceDeductionRequest;
use App\Models\Company;
use App\Models\Deduction;
use App\Models\InvoiceDeduction;
use App\Traits\GeneralFunctions;


class InvoiceDeductionsController
{
    use GeneralFunctions;
	
	public function update(UpdateInvoiceDeductionRequest $request , Company $company ,  $InvoiceId , $invoiceModelName ){
		$totalDeductions = array_sum(array_column($request->input('deductions',[]),'amount'));

		$invoice = ('App\Models\\'.$invoiceModelName)::find($InvoiceId);
		$currentBalance  =$invoice->net_balance + $invoice->deductions->sum('pivot.amount');
		$invoice->net_balance = $currentBalance - $totalDeductions;
		
		if($invoice->net_balance < 0 ){
			return response()->json([
				'status'=>true,
				'errorMessage'=>__('No Enough Balance .. Current Balance Is ' . $currentBalance)
			]);
		}
	
		$invoice->deductions()->detach();
		$invoice->update([
			'total_deductions'=>0
		]);
		$invoiceExchangeRate = $invoice->getExchangeRate();
		foreach($request->get('deductions',[]) as $deductionArr){
			$deductionArr = array_merge($deductionArr,['invoice_type'=>$invoiceModelName,'invoice_id'=>$invoice->id,'company_id'=>$company->id]);
			$currentAmountInMainAndCurrencyCurrencyArr = Deduction::calculateAmountInMainCurrency($deductionArr['amount'],$deductionArr['date'],$invoice->getCurrency(),$invoiceExchangeRate,$company);
			$deductionArr['amount_in_main_currency'] = $currentAmountInMainAndCurrencyCurrencyArr['amount_in_main_currency'];
			$deductionArr['amount_in_invoice_exchange_rate'] = $currentAmountInMainAndCurrencyCurrencyArr['amount_in_invoice_exchange_rate'];
			$deductionArr['foreign_gain_or_loss'] = $deductionArr['amount_in_main_currency'] - $deductionArr['amount_in_invoice_exchange_rate'] ;
			InvoiceDeduction::create($deductionArr);
		}
		$invoice->update([
			'total_deductions'=>$totalDeductions
		]);
		return response()->json([
			'reloadCurrentPage'=>true
		]);
		
	}
	
	
}
