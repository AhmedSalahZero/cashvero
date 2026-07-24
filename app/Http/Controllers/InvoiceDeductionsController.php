<?php
namespace App\Http\Controllers;

use App\Http\Requests\UpdateInvoiceDeductionRequest;
use App\Models\Company;
use App\Models\Deduction;
use App\Models\InvoiceDeduction;
use App\Traits\GeneralFunctions;
use Illuminate\Validation\ValidationException;

/**
 * InvoiceDeductionsController
 * ------------------------------------------------------------------
 * Replaces the full set of deductions on one invoice (detach-then-
 * recreate) — reached from the "Deduct" modal on the Invoice Report
 * page (CustomerInvoiceDashboardController::showInvoiceReport).
 *
 * update() is UNCHANGED except for its response type. The original
 * returned raw `response()->json([...])` in both the insufficient-
 * balance case AND the success case — correct for the old jQuery-AJAX
 * modal, which read that JSON body itself, but incompatible with
 * Inertia ("All Inertia requests must receive a valid Inertia
 * response"). Same root cause and fix as the response()->json
 * pattern already documented multiple times in the project roadmap
 * (§13, bugs #19/#22). Fixed here by:
 *   - throwing a real ValidationException for the insufficient-balance
 *     case, so it surfaces through the same `page.props.errors`
 *     mechanism every other form in this app already reads — instead
 *     of a one-off `{status, errorMessage}` shape only this modal
 *     understood
 *   - redirecting back with a flash success message on the happy path
 * All balance math, the detach/recreate logic, and the exchange-rate
 * calculation are byte-for-byte unchanged.
 */
class InvoiceDeductionsController
{
    use GeneralFunctions;
	
	public function update(UpdateInvoiceDeductionRequest $request , Company $company ,  $InvoiceId , $invoiceModelName ){
		$totalDeductions = array_sum(array_column($request->input('deductions',[]),'amount'));

		$invoice = ('App\Models\\'.$invoiceModelName)::find($InvoiceId);
		$currentBalance  =$invoice->net_balance + $invoice->deductions->sum('pivot.amount');
		$invoice->net_balance = $currentBalance - $totalDeductions;
		
		if($invoice->net_balance < 0 ){
			throw ValidationException::withMessages([
				'deductions' => __('No Enough Balance .. Current Balance Is ' . $currentBalance),
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
		return redirect()->back()->with('success', __('Deductions updated successfully'));
		
	}
	
	
}
