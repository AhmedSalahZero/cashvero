<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\ReadyFunctions\InvoiceAgingService;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * * هي اسمها اعمار الديون
 * * هو عباره عن الفواتير اللي لسه مفتوحة ( اعمار الديون) .. سواء الدين لسه جايه او المتاخر او حق اليوم
 * * وبالتالي بمجرد ما تندفع مش بتيجي هنا (لو النت بلانس اكبر من صفر يبقي لسه ما استدتش كاملا)
 */
class AgingController
{
    use GeneralFunctions;
    public function index(Company $company,string $modelType)
	{
		$fullClassName = ('\App\Models\\'.$modelType) ;
		$customersOrSupplierText = (new $fullClassName)->getClientDisplayName();
		$title = (new $fullClassName)->getAgingTitle();
		// $clientNameColumnName = $fullClassName::CLIENT_NAME_COLUMN_NAME ;
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
	
		// $currencies = DB::table($invoiceTableName)
		
		// ->where('company_id',$company->id)->where('currency','!=',null)->where('currency','!=','')
		// ->orderBy('currency')
		// ->selectRaw('currency')->get()->pluck('currency')->unique()->values()->toArray();
		
		$currencies = DB::table($invoiceTableName)
		->where('company_id', $company->id)       
		->whereNotNull('currency')                
		->where('currency', '!=', '')                  
		->orderBy('currency')
		->distinct()                                     
		->pluck('currency')                            
		->toArray();                                     
		
	
		
        return view('reports.aging_form', [
			'businessUnits'=>$businessUnits,
			'company'=>$company,
			// 'invoices'=>$invoices ,
			'salesPersons'=>$salesPersons,
			'businessSectors'=>$businessSectors,
			'currencies'=>$currencies,
			'customersOrSupplierText'=>$customersOrSupplierText,
			'title'=>$title,
			'modelType'=>$modelType
		]);
    }
	public function result(Company $company , Request $request,string $modelType , bool $returnResult = false ){
		
		$fullClassName = ('\App\Models\\'.$modelType) ;
		$customersOrSupplierAgingText = (new $fullClassName)->getCustomerOrSupplierAgingText();
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
		

		
		return view('admin.reports.invoices-aging',['agings'=>$agings,'aginDate'=>$aginDate,'weeksDates'=>$weeksDates,'customersOrSupplierAgingText'=>$customersOrSupplierAgingText]);
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
