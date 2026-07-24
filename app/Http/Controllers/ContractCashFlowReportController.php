<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Partner;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * ContractCashFlowReportController
 * ------------------------------------------------------------------
 * The form/list page for a per-contract cash flow report. The actual
 * report engine is NOT here — result() is a thin wrapper that
 * pre-resolves the contract/customer and delegates straight into
 * CashFlowReportController::result(), which already renders Inertia
 * (Pages/CashFlowReport/Result.vue — shared with Company Cash Flow).
 * ⚠️ CALCULATION LOGIC IS 100% UNTOUCHED here, same reasoning as
 * CashFlowReportController's own class docblock.
 *
 * ── Frontend migration status ───────────────────────────────────
 *   index()  → ✅ Inertia::render, Pages/ContractCashFlowReport/Index.vue
 *              (customer → contract cascading select, reusing the
 *              existing get.contracts.for.customer.or.supplier AJAX
 *              endpoint the same way CashExpense/Form.vue already
 *              does + saved-reports list). Previously Blade
 *              (reports.contract_cash_flow_form +
 *              contract-cashflow-report-index).
 *   result() → already Inertia (delegates into
 *              CashFlowReportController::result()), UNCHANGED.
 *   Excel export → handled by CashFlowReportController::exportExcel(),
 *              since Contract Cash Flow shares that same Result.vue
 *              page and export route with Company Cash Flow.
 */
class ContractCashFlowReportController
{
    use GeneralFunctions;

	/**
	 * Form + saved-reports list. getContractsForCustomerOrSupplier()
	 * (ContractsController, UNCHANGED) already returns each contract's
	 * id/name/code/amount/currency/start_date/end_date — exactly the
	 * fields the old Blade's inline JS read off `data-*` attributes —
	 * so the Vue page fetches it the same way CashExpense/Form.vue's
	 * loadContractsForPartner() already does, no new endpoint needed.
	 */
    public function index(Company $company)
	{
		$clientsWithContracts = Partner::onlyCompany($company->id)->orderBy('name')->onlyCustomers()->onlyThatHaveCustomerContracts()->get();
		$contractCashflowReports = $company->cashflowReports->where('is_contract', 1);

		return Inertia::render('ContractCashFlowReport/Index', [
			'company' => ['id' => $company->id, 'name' => $company->getName()],
			'clientsWithContracts' => $clientsWithContracts->map(fn ($p) => ['id' => $p->id, 'name' => $p->getName()])->values(),
			'getContractsForCustomerUrl' => route('get.contracts.for.customer.or.supplier', ['company' => $company->id]),
			'savedReports' => $contractCashflowReports->values()->map(fn ($r) => [
				'id' => $r->id,
				'name' => $r->getName(),
				'interval' => $r->getIntervalName(),
				'start_date_formatted' => $r->getStartDateFormatted(),
				'end_date_formatted' => $r->getEndDateFormatted(),
				// Reusing the generic (company) result route is deliberate,
				// not a leftover bug — CashFlowReportController::result()
				// already fully handles cached-report replay for BOTH
				// company and contract reports purely from $cashflowReport
				// ->report_data, without needing contract_id again. This
				// matches the exact routing the original Blade partial
				// (contract-cashflow-report-index.blade.php) already used.
				'view_url' => route('result.cashflow.report', ['company' => $company->id, 'returnResultAsArray' => 'view', 'cashflowReport' => $r->id]),
				'delete_url' => route('delete.cashflow.report', ['company' => $company->id, 'cashflowReport' => $r->id]),
			]),
			'urls' => [
				'result' => route('result.contract.cashflow.report', ['company' => $company->id]),
			],
		]);
    }
	public function result(Company $company , Request $request , bool $returnResultAsArray = false ,$defaultCashFlowId = 0){
		$formStartDate =$request->get('start_date',$request->get('cash_start_date'));
		$formEndDate =$request->get('end_date',$request->get('cash_end_date'));
		$reportInterval =  $request->get('report_interval','weekly');
		
		$contractId = $request->get('contract_id')	 ;
		$finalResult = [];
		$contract = Contract::find($contractId);
		/**
		 * @var Contract|null $contract 
		 */
		$contractCode = $contract ? $contract->getCode() : null ;
		$contractName = $contract ? $contract->getName() : null ;
		if(is_null($contractCode)){
			return redirect()->back()->with('fail',__('Please Select Contract'));
		}
		$customer = $contract ? $contract->client : null ;
		$customerId = $customer ? $customer->getId() : null ;
		$customerName = $customer ? $customer->getName() : null ;
		$title = __('Contract Cash Flow Report') . ' [ '. $reportInterval . ' ] ['. $customerName . ' ] ' . ' [ ' . $contractName . ' ]';
		$request->merge([
			'title'=>$title,
		]);
		return  (new CashFlowReportController)->result($company,$request,$returnResultAsArray,null,$defaultCashFlowId);
	
	}
	public function formatAccumulatedNetCash(array $netCashes,array $weeks)
	{
		$currentAccumulated = 0 ;
		$result = [];

		foreach($weeks as $week => $weekNumber){
			$currentAccumulated +=  $netCashes[$week] ?? 0;
			$result[$week] = $currentAccumulated ;
		}
		return $result ;
	}
	public function mergeTotal(array $totals , $collectionOfItems):array 
	{
		foreach($collectionOfItems as $itemStdClass){
			$week = $itemStdClass->week_start_date;
			$currentAmount = $itemStdClass->amount;
			$year = explode('-',$week)[0];
			$month = explode('-',$week)[1];
			$totals[$month.'-'.$year] = isset($totals[$month.'-'.$year]) ? $totals[$month.'-'.$year] + $currentAmount : $currentAmount;
		}
		return $totals;
	}
	protected function mergeYearWithWeek(array $weeks , Carbon $startDate ):array{
		$newWeeks = [];
		if(!count($weeks)){
			return [];
		}
		foreach($weeks as $date => $weekNumber){
			$currentDate =Carbon::make($date);
				$year = $currentDate->year ;
				if($currentDate->greaterThanOrEqualTo($startDate)){
					$newWeeks[$weekNumber.'-'.$year] = $weekNumber; 
				}
		}
		return $newWeeks;
		
	}
	public function getPastDueCustomerInvoices(string $invoiceType,string $currency , int $companyId , string $startDate,string $contractCode ){
		$fullClassName = '\App\Models\\'.$invoiceType;
		$items  = $fullClassName::where('company_id',$companyId)
		->where('contract_code',$contractCode)
		->where('net_balance','>',0)
		->whereIn('invoice_status',['past_due','partially_collected_and_past_due'])
		->where('currency',$currency)->where('invoice_due_date','<',$startDate)->get() ;
		foreach($items as $item){
			$item->net_balance_until_date = $item->getNetBalanceUntil($startDate);
		}
		
		return $items;
	}
}
