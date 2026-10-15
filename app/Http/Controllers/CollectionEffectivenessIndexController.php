<?php

namespace App\Http\Controllers;

use App\Helpers\HDate;
use App\Models\Company;
use App\Models\Partner;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * CollectionEffectivenessIndexController
 * ------------------------------------------------------------------
 * How much of what SHOULD have been collected/paid in a period
 * actually was, per customer/supplier — "Collection Effectiveness
 * Index" for customers, "Payment Effectiveness Index" for suppliers.
 *
 * This controller was already written to support both — see the
 * in_array('collection', $request->segments()) modelType check in
 * index(), and SupplierInvoice::getEffectivenessTitle()/getEffectivenessText(),
 * which already return "Payment Effectiveness Index Form"/"Payment
 * Effectiveness Index". Only the Supplier-side ROUTE and sidebar
 * entry were ever missing — added alongside this migration (see
 * routes/web.php: view.payments.effectiveness.index /
 * result.payments.effectiveness.index), not a new feature invented
 * here, just completing what was already half-built.
 *
 * result() internally reuses AgingController::result() and
 * CustomerInvoiceDashboardController::showInvoiceStatementReport()
 * in their $returnResult=true internal-use mode — both UNCHANGED,
 * both return their raw arrays before any Inertia-specific code in
 * either of those methods runs.
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   - index() / result() → ALREADY migrated. Return Inertia::render(),
 *                           served by resources/js/Pages/CollectionEffectiveness/Form.vue
 *                           and resources/js/Pages/CollectionEffectiveness/Result.vue
 */
class CollectionEffectivenessIndexController
{
    use GeneralFunctions;
    public function index(Company $company,Request $request)
	{
		$defaultStartDate = now()->subMonths(12)->format('Y-m-d');
		$defaultEndDate = now()->format('Y-m-d');
		$modelType = in_array('collection',$request->segments()) ? 'CustomerInvoice' : 'SupplierInvoice' ;
		$fullClassName = ('\App\Models\\'.$modelType) ;
		$customersOrSupplierText = (new $fullClassName)->getClientDisplayName();
		$title = (new $fullClassName)->getEffectivenessTitle();
		$clientNameColumnName = $fullClassName::CLIENT_NAME_COLUMN_NAME ;
		$invoiceTableName = getUploadParamsFromType($modelType)['dbName'];
		$exportables = getExportableFieldsForModel($company->id,$modelType) ; 
		$salesPersons = [];
		$businessUnits = [];
		$businessSectors = [];
		if(isset($exportables['business_unit'])){
			$businessUnits = DB::table('cash_vero_business_units')->where('company_id',$company->id)->pluck('name')->toArray();
		}
		if(isset($exportables['sales_person'])){
			$salesPersons = DB::table('cash_vero_sales_persons')->where('company_id',$company->id)->pluck('name')->toArray();
		}
		if(isset($exportables['business_sector'])){
			$businessSectors = DB::table('cash_vero_business_sectors')->where('company_id',$company->id)->pluck('name')->toArray();
		}
		$currencies = DB::table($invoiceTableName)
		->where('company_id', $company->id)       
		->whereNotNull('currency')                
		->where('currency', '!=', '')                  
		->orderBy('currency')
		->distinct()                                     
		->pluck('currency')                            
		->toArray();    
		
		$invoices = ('\App\Models\\'.$modelType)::where($clientNameColumnName,'!=',null)->where($clientNameColumnName,'!=','')->onlyCompany($company->id)->get();
		
		$invoices = $invoices->unique('customer_name')->values() ;
		// The results controller reads clients[] as NAME strings
		// (Partner::getPartnerFromName), not IDs — unlike Aging's
		// client_ids[]. Options are built by name accordingly.
		$clientOptions = $invoices->map(fn ($invoice) => $invoice->getName())->unique()->values();

        return Inertia::render('CollectionEffectiveness/Form', [
			'businessUnits'=>$businessUnits,
			'salesPersons'=>$salesPersons,
			'businessSectors'=>$businessSectors,
			'currencies'=>$currencies,
			'customersOrSupplierText'=>$customersOrSupplierText,
			'title'=>$title,
			'modelType'=>$modelType,
			'defaultStartDate'=>$defaultStartDate,
			'defaultEndDate'=>$defaultEndDate,
			'clientOptions'=>$clientOptions,
			'resultUrl'=>route($modelType === 'CustomerInvoice' ? 'result.collections.effectiveness.index' : 'result.payments.effectiveness.index', ['company'=>$company->id]),
			'ajaxCustomersUrl'=>route('get.customers.or.suppliers.from.business.units.currencies', ['company'=>$company->id,'modelType'=>$modelType]),
		]);
    }
	/**
	 * Effectiveness Index results — one row per customer/supplier,
	 * one column per date period (or a single "whole interval"
	 * column), each cell a collection/payment effectiveness
	 * percentage, plus an "All Company" summary row.
	 * Renders resources/js/Pages/CollectionEffectiveness/Result.vue.
	 *
	 * ALL the math above this comment is UNCHANGED — including the
	 * two internal reuses of AgingController::result() and
	 * showInvoiceStatementReport() in their $returnResult=true mode.
	 * Everything below just reshapes the already-computed values into
	 * an explicit row/column structure for Vue.
	 */
	public function result(Company $company , Request $request){
		$modelType = $request->get('model_type');
		$fullClassName = ('\App\Models\\'.$modelType) ;
		$companyId =$company->id ;
		$reportType = $request->get('report_type','whole_interval');
		$startDate = $request->get('start_date');
		$endDate = $request->get('end_date');
		$currency = $request->get('currency');
		$reportName = (new $fullClassName)->getEffectivenessText();
		$totalCurrentTotalToBeCollectedPerDate = [] ;
		$totalCurrentTotalCollectedPerDate = [] ;
		$collectionEffectivenessIndexForAllCustomersPerDate = [];
		
		$totalCurrentTotalToBeCollectedPerCustomer = [] ;
		$collectionEffectivenessIndexForAllCustomersPerCustomer = [];
		$totalCurrentTotalCollectedPerCustomer = [] ;
		$totalCurrentTotalCollectedPerAll =0;
		$totalCurrentTotalToBeCollectedPerAll = 0 ;
		$collectionEffectivenessIndexForAllCustomersPerAll = 0;
		
		$datesForHeader = [];
		$customerOrSupplierNameText = (new $fullClassName)->getClientNameText();
		$agingResult = (new AgingController)->result($company,$request,$modelType,true);
		$collectionEffectivenessIndexPerCustomer = [];
		$isMonthlyReport =$reportType == 'monthly'; 
		$dates = $isMonthlyReport ? HDate::generateStartDateAndEndDateBetween($startDate,$endDate) : [['start_date'=>$startDate,'end_date'=>$endDate]] ;  
		foreach($dates as $currentDateArr){
			$currentStartDate = $currentDateArr['start_date'];
			$currentEndDate = $currentDateArr['end_date'];
			$indexForStartAndEndDate = $currentStartDate.'/'.$currentEndDate;
			$datesForHeader[] =$indexForStartAndEndDate; 
			foreach($request->get('clients') as $partnerName){
			
				$currentPartner = Partner::getPartnerFromName($partnerName,$companyId);
				if(!$currentPartner){
					continue ;
				}
				$currentPartnerId = $currentPartner->id; 
				$currentInvoiceStatementReportResult = (new CustomerInvoiceDashboardController())->showInvoiceStatementReport($company,$request,$currentPartnerId,$currency,$modelType,$currentStartDate,$currentEndDate,true);
				if(!count($currentInvoiceStatementReportResult)){
					continue ; 
				}
				$currentBeginningBalance = isset($currentInvoiceStatementReportResult) && $currentInvoiceStatementReportResult[0]['debit'] > 0 ? $currentInvoiceStatementReportResult[0]['debit'] : $currentInvoiceStatementReportResult[0]['credit'] * -1;
				unset($currentInvoiceStatementReportResult[0]);
				$currentSumOfDebit = array_sum(array_column($currentInvoiceStatementReportResult,'debit'));
				$currentSumOfCredit = array_sum(array_column($currentInvoiceStatementReportResult,'credit'));
				$currentTotalCollected =  $currentSumOfCredit ;
				$currentComingDues = $agingResult[$partnerName]['coming_due']['total'] ?? 0 ;
				$currentTotalToBeCollected =  $currentBeginningBalance + $currentSumOfDebit - $currentComingDues ;
				$totalCurrentTotalCollectedPerDate[$indexForStartAndEndDate]= isset($totalCurrentTotalCollectedPerDate[$indexForStartAndEndDate]) ? $totalCurrentTotalCollectedPerDate[$indexForStartAndEndDate] + $currentTotalCollected :$currentTotalCollected;
				$totalCurrentTotalCollectedPerCustomer[$partnerName]= isset($totalCurrentTotalCollectedPerCustomer[$partnerName]) ? $totalCurrentTotalCollectedPerCustomer[$partnerName] + $currentTotalCollected :$currentTotalCollected;
				$totalCurrentTotalCollectedPerAll+= $currentTotalCollected;
				$totalCurrentTotalToBeCollectedPerDate[$indexForStartAndEndDate] = isset($totalCurrentTotalToBeCollectedPerDate[$indexForStartAndEndDate]) ? $totalCurrentTotalToBeCollectedPerDate[$indexForStartAndEndDate]+ $currentTotalToBeCollected : $currentTotalToBeCollected;
				$totalCurrentTotalToBeCollectedPerCustomer[$partnerName] = isset($totalCurrentTotalToBeCollectedPerCustomer[$partnerName]) ? $totalCurrentTotalToBeCollectedPerCustomer[$partnerName]+ $currentTotalToBeCollected : $currentTotalToBeCollected;
				$totalCurrentTotalToBeCollectedPerAll += $currentTotalToBeCollected;
				$collectionEffectivenessIndexPerCustomer[$partnerName][$indexForStartAndEndDate] =$currentTotalToBeCollected ? $currentTotalCollected /$currentTotalToBeCollected *100 :0 ;
				$collectionEffectivenessIndexForAllCustomersPerDate[$indexForStartAndEndDate] = $totalCurrentTotalToBeCollectedPerDate[$indexForStartAndEndDate] ? $totalCurrentTotalCollectedPerDate[$indexForStartAndEndDate] /$totalCurrentTotalToBeCollectedPerDate[$indexForStartAndEndDate] *100 :0 ;
				$collectionEffectivenessIndexForAllCustomersPerCustomer[$partnerName] = $totalCurrentTotalToBeCollectedPerCustomer[$partnerName] ? $totalCurrentTotalCollectedPerCustomer[$partnerName] /$totalCurrentTotalToBeCollectedPerCustomer[$partnerName] *100 :0 ;
			}
		}
		$collectionEffectivenessIndexForAllCustomersPerAll = $totalCurrentTotalToBeCollectedPerAll ? $totalCurrentTotalCollectedPerAll/$totalCurrentTotalToBeCollectedPerAll*100 :0;
		
		$tableHeaders =  $datesForHeader ;

		// ── Reshape for Vue: one row per customer, each with its
		// percentage per header column + (if monthly) an overall total.
		$rows = [];
		foreach ($collectionEffectivenessIndexPerCustomer as $partnerName => $effectivenessIndexArrs) {
			$values = [];
			foreach ($tableHeaders as $header) {
				$values[$header] = $effectivenessIndexArrs[$header] ?? 0;
			}
			$rows[] = [
				'name' => $partnerName,
				'values' => $values,
				'total' => $collectionEffectivenessIndexForAllCustomersPerCustomer[$partnerName] ?? 0,
			];
		}
		$allCompanyRow = ['values' => [], 'total' => $collectionEffectivenessIndexForAllCustomersPerAll];
		foreach ($tableHeaders as $header) {
			$allCompanyRow['values'][$header] = $collectionEffectivenessIndexForAllCustomersPerDate[$header] ?? 0;
		}
		
		return Inertia::render('CollectionEffectiveness/Result', [
			'reportName'=>$reportName,
			'tableHeaders'=>$tableHeaders,
			'customerOrSupplierNameText'=>$customerOrSupplierNameText,
			'rows'=>$rows,
			'allCompanyRow'=>$allCompanyRow,
			'isMonthlyReport'=>$isMonthlyReport,
			'backUrl'=>route($modelType === 'CustomerInvoice' ? 'view.collections.effectiveness.index' : 'view.payments.effectiveness.index', ['company'=>$company->id]),
		]);
	}

	


}
