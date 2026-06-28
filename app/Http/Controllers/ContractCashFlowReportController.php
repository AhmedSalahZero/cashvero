<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contract;
use App\Models\Partner;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ContractCashFlowReportController
{
    use GeneralFunctions;
    public function index(Company $company)
	{
		$clientsWithContracts = Partner::onlyCompany($company->id)->orderBy('name')->onlyCustomers()->onlyThatHaveContracts()->get();
		$contractCashflowReports = $company->cashflowReports->where('is_contract',1);
        return view('reports.contract_cash_flow_form', compact('company','clientsWithContracts','contractCashflowReports'));
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
		return  (new CashFlowReportController)->result($company,$request,false,null,$defaultCashFlowId);
	
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
