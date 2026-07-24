<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\ReadyFunctions\InvoiceAgingService;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * * هي اسمها اعمار الديون
 * * هو عباره عن الفواتير اللي لسه مفتوحة ( اعمار الديون) .. سواء الدين لسه جايه او المتاخر او حق اليوم
 * * وبالتالي بمجرد ما تندفع مش بتيجي هنا (لو النت بلانس اكبر من صفر يبقي لسه ما استدتش كاملا)
 *
 * AgingController
 * ------------------------------------------------------------------
 * Shared by BOTH "Customer Aging" and "Suppliers Aging" sidebar
 * pages — same pattern as BalancesController, distinguished only by
 * the $modelType route parameter.
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   - index()                              → ALREADY migrated. Returns
 *                                             Inertia::render(), served
 *                                             by resources/js/Pages/Aging/Form.vue.
 *                                             Submits to result() below,
 *                                             which is still Blade — a
 *                                             native (non-Inertia) form
 *                                             POST, same as any link to
 *                                             a not-yet-migrated page.
 *   - result()                             → ALREADY migrated. Returns
 *                                             Inertia::render(), served
 *                                             by resources/js/Pages/Aging/Result.vue.
 *                                             $returnResult=true (internal
 *                                             use by CollectionEffectivenessIndexController)
 *                                             is unaffected — returns
 *                                             before any reshaping.
 *   - getCustomersFromBusinessUnitsAndCurrencies() → UNCHANGED. A real
 *                                             JSON API endpoint (not a
 *                                             page visit) — used by the
 *                                             Vue form via axios to
 *                                             live-refresh the Currency
 *                                             and Customer/Supplier
 *                                             dropdowns as filters
 *                                             change. Correct for it
 *                                             to stay JSON.
 */
class AgingController
{
    use GeneralFunctions;
    public function index(Company $company,string $modelType)
	{
		$fullClassName = ('\App\Models\\'.$modelType) ;
		$customersOrSupplierText = (new $fullClassName)->getClientDisplayName();
		$title = (new $fullClassName)->getAgingTitle();
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

        return Inertia::render('Aging/Form', [
			'businessUnits'=>$businessUnits,
			'salesPersons'=>$salesPersons,
			'businessSectors'=>$businessSectors,
			'currencies'=>$currencies,
			'customersOrSupplierText'=>$customersOrSupplierText,
			'title'=>$title,
			'modelType'=>$modelType,
			'submitUrl'=>route('result.aging.analysis', ['company'=>$company->id, 'modelType'=>$modelType]),
			'ajaxCustomersUrl'=>route('get.customers.or.suppliers.from.business.units.currencies', ['company'=>$company->id, 'modelType'=>$modelType]),
		]);
    }
	/**
	 * Aging results — the matrix report (Past Due / Current Due /
	 * Coming Due, broken into day-interval buckets, per customer,
	 * with per-invoice drill-down) plus 3 summary breakdowns.
	 * Renders resources/js/Pages/Aging/Result.vue.
	 *
	 * ALL the actual math above this comment — __execute(),
	 * getDueNameWithDiffInDays(), getDayInterval() — is completely
	 * UNCHANGED. Everything below just reshapes the already-computed
	 * $agings array (which mixes client names as keys alongside
	 * reserved keys like 'total'/'grand_total'/'charts') into a flat,
	 * explicit structure Inertia can actually pass to Vue.
	 *
	 * $returnResult=true (CollectionEffectivenessIndexController's
	 * internal use) returns the raw $agings array BEFORE any of this
	 * reshaping — untouched, exactly as before.
	 *
	 * Design note: the original rendered 3 pie/donut charts via
	 * amCharts. Replaced here with horizontal bar-breakdowns instead
	 * — same 'charts' data from the service, just a different (and
	 * more legible with 8+ buckets) presentation. Flagged explicitly,
	 * not a silent substitution.
	 */
	public function result(Company $company , Request $request,string $modelType , bool $returnResult = false ){
		
		$fullClassName = ('\App\Models\\'.$modelType) ;
		$customersOrSupplierAgingText = (new $fullClassName)->getCustomerOrSupplierAgingText();
		$clientNameText = (new $fullClassName)->getClientNameText();
		$aginDate = $request->get('again_date',$request->get('end_date',now()->format('Y-m-d')));
		$currency = $request->get('currency');
		$invoiceTableName = getUploadParamsFromType($modelType)['dbName'];
		$fullClassName = 'App\Models\\'.$modelType ;
		$customer_or_supplier_name=$fullClassName::CLIENT_NAME_COLUMN_NAME;
		$customer_or_supplier_id=$fullClassName::CLIENT_ID_COLUMN_NAME;
		$businessUnits = $request->get('business_units',[]);
		$salesPersons = $request->get('sales_persons',[]);
		$businessSectors = $request->get('business_sectors',[]);
		$clientIds = $request->get('client_ids',array_keys($this->getCustomersOrSuppliers($invoiceTableName ,$currency, $customer_or_supplier_id,$customer_or_supplier_name,$company,$businessUnits,$salesPersons,$businessSectors)->toArray()));
		$invoiceAgingService = new InvoiceAgingService($company->id ,$aginDate,$currency);
		$agings  = $invoiceAgingService->__execute($clientIds,$modelType) ;
		$weeksDates =formatWeeksDatesFromStartDate($aginDate);
		
		if($returnResult){
			return $agings ;
		}

		// ── Reshape for Vue. $agings mixes client names as top-level
		// keys with reserved keys (total/grand_total/charts/etc) —
		// separate those out explicitly rather than relying on Vue
		// (or a future reader) to know which keys are "special."
		$dayIntervals = getInvoiceDayIntervals();
		$pastDueColumns = array_reverse(array_merge($dayIntervals, [InvoiceAgingService::MORE_THAN_150])); // farthest-out first, matches original
		$comingDueColumns = array_merge($dayIntervals, [InvoiceAgingService::MORE_THAN_150]); // nearest first

		$bucketRow = function ($sourceForDueName) use ($pastDueColumns, $comingDueColumns) {
			$pastDue = [];
			foreach ($pastDueColumns as $interval) {
				$pastDue[$interval] = $sourceForDueName['past_due'][$interval] ?? 0;
			}
			$comingDue = [];
			foreach ($comingDueColumns as $interval) {
				$comingDue[$interval] = $sourceForDueName['coming_due'][$interval] ?? 0;
			}
			return [
				'past_due' => $pastDue,
				'current_due' => $sourceForDueName['current_due'][0] ?? ($sourceForDueName['current_due']['total'] ?? 0),
				'coming_due' => $comingDue,
			];
		};

		$clientNames = array_keys($agings['grand_clients_total'] ?? []);
		$clientRows = [];
		foreach ($clientNames as $clientName) {
			$clientData = $agings[$clientName] ?? [];
			$bucketed = $bucketRow($clientData);
			$invoiceRows = [];
			foreach (($clientData['invoices'] ?? []) as $invoiceNumber => $invoiceData) {
				$invoiceBucketed = $bucketRow($invoiceData);
				$invoiceRows[] = array_merge($invoiceBucketed, [
					'invoice_number' => $invoiceNumber,
					'total' => $invoiceData['total'] ?? 0,
				]);
			}
			$clientRows[] = array_merge($bucketed, [
				'name' => $clientName,
				'total' => $clientData['total'] ?? 0,
				'invoices' => $invoiceRows,
			]);
		}

		$totalsRow = $bucketRow([
			'past_due' => $agings['total']['past_due'] ?? [],
			'current_due' => $agings['total']['current_due'] ?? [],
			'coming_due' => $agings['total']['coming_due'] ?? [],
		]);
		$totalsRow['total'] = $agings['grand_total'] ?? 0;

		return Inertia::render('Aging/Result', [
			'agingDate' => \Carbon\Carbon::make($aginDate)->format('d-m-Y'),
			'clientNameText' => $clientNameText,
			'customersOrSupplierAgingText' => $customersOrSupplierAgingText,
			'pastDueColumns' => $pastDueColumns,
			'comingDueColumns' => $comingDueColumns,
			'weeksDates' => $weeksDates,
			'clientRows' => $clientRows,
			'totalsRow' => $totalsRow,
			'charts' => $agings['charts'] ?? [],
			'backUrl' => route('view.aging.analysis', ['company' => $company->id, 'modelType' => $modelType]),
		]);
	}
	protected function getCustomersOrSuppliers($invoiceTableName ,$currency, $customer_or_supplier_id,$customer_or_supplier_name,$company,$businessUnits,$salesPersons,$businessSectors)
	{
		$query = DB::table($invoiceTableName)->select($customer_or_supplier_name,$customer_or_supplier_id,'currency')
		->where('currency',$currency)->where($invoiceTableName.'.company_id',$company->id)
		->join('partners','partners.id','=',$invoiceTableName.'.'.$customer_or_supplier_id)
		->where('net_balance','>',0);
		if(count($businessUnits)){
			$query = $query->whereIn('business_unit',$businessUnits);
		}
		if(count($salesPersons)){
			$query = $query->whereIn('sales_person',$salesPersons);
		}
		if(count($businessSectors)){
			$query = $query->whereIn('business_sector',$businessSectors);
		}

		$data = $query->get();
		/**
		 * @var Collection $data ;
		 */
		return  $data->unique($customer_or_supplier_id)->pluck($customer_or_supplier_name,$customer_or_supplier_id);
		
	}
	public function getCustomersFromBusinessUnitsAndCurrencies(Company $company ,Request $request,string $modelType)
	{
		$invoiceTableName = getUploadParamsFromType($modelType)['dbName'];
		$fullClassName = 'App\Models\\'.$modelType ;
		$customer_or_supplier_name=$fullClassName::CLIENT_NAME_COLUMN_NAME;
		$customer_or_supplier_id=$fullClassName::CLIENT_ID_COLUMN_NAME;
		$currency = $request->get('currencies');
		$businessUnits = $request->get('business_units',[]);
		$salesPersons = $request->get('sales_persons',[]);
		$businessSectors = $request->get('business_sectors',[]);
        // $partners = $modelType == 'CustomerInvoice' ?  
		
		// Partner::getCustomersForCompany($company->id,$currency,$businessUnits,$salesPersons,$businessSectors) : Partner::getSuppliersForCompany($company->id);

		$customers = $this->getCustomersOrSuppliers($invoiceTableName ,$currency, $customer_or_supplier_id,$customer_or_supplier_name,$company,$businessUnits,$salesPersons,$businessSectors);
		
		$currencies = DB::table($invoiceTableName)->select($customer_or_supplier_name,'currency')
		->where('company_id',$company->id)
		->where('net_balance','>',0)
		->orderBy('currency')
		->get()
		->unique('currency')->pluck('currency');
		
	
		
		return response()->json([
			'status'=>true ,
			'message'=>__('Success'),
			'data'=>[
				'customer_names'=>$customers,
				'currencies_names'=>$currencies,
			]
		]);
		
	}


}
