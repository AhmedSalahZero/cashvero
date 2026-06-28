<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Models\Company;
use App\Models\Partner;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SuppliersController
{
    use GeneralFunctions;
    protected function applyFilter(Request $request,Collection $collection):Collection{
		if(!count($collection)){
			return $collection;
		}
		$searchFieldName = $request->get('field');
		$dateFieldName =  'created_at' ; // change it 
		// $dateFieldName = $searchFieldName === 'balance_date' ? 'balance_date' : 'created_at'; 
		$from = $request->get('from');
		$to = $request->get('to');
		$value = $request->query('value');
		$collection = $collection
		->when($request->has('value'),function($collection) use ($value,$searchFieldName){
			return $collection->filter(function($moneyReceived) use ($value,$searchFieldName){
				$currentValue = $moneyReceived->{$searchFieldName} ;
				// if($searchFieldName == 'bank_id'){
				// 	$currentValue = $moneyReceived->getBankName() ;  
				// }
				return false !== stristr($currentValue , $value);
			});
		})
		->when($request->get('from') , function($collection) use($dateFieldName,$from){
			return $collection->where($dateFieldName,'>=',$from);
		})
		->when($request->get('to') , function($collection) use($dateFieldName,$to){
			return $collection->where($dateFieldName,'<=',$to);
		});
		
		return $collection;
	}
	public function index(Company $company,Request $request)
	{
		
		$numberOfMonthsBetweenEndDateAndStartDate = 18 ;
		$currentType = $request->get('active',Partner::SUPPLIERS);
		
		$filterDates = [];
		foreach([Partner::SUPPLIERS] as $type){
			$startDate = $request->has('startDate') ? $request->input('startDate.'.$type) : now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d');
			$endDate = $request->has('endDate') ? $request->input('endDate.'.$type) : now()->format('Y-m-d');
			
			$filterDates[$type] = [
				'startDate'=>$startDate,
				'endDate'=>$endDate
			];
		}
		
		
		 
		  /**
		 * * start of suppliers 
		 */
		
		$supplierStartDate = $filterDates[Partner::SUPPLIERS]['startDate'] ?? null ;
		$supplierEndDate = $filterDates[Partner::SUPPLIERS]['endDate'] ?? null ;
		$suppliers = $company->suppliers ;
		$suppliers =  $suppliers->filterByCreatedAt($supplierStartDate,$supplierEndDate) ;
		$suppliers =  $currentType == Partner::SUPPLIERS ? $this->applyFilter($request,$suppliers):$suppliers ;

		/**
		 * * end of suppliers 
		 */
		 
		
		 $searchFields = [
			Partner::SUPPLIERS=>[
				
				'created_at'=>__('Created At'),
				'name'=>__('Name')
			],
		];
	
		$models = [
			Partner::SUPPLIERS =>$suppliers ,
		];

        return view('suppliers.index', [
			'company'=>$company,
			'searchFields'=>$searchFields,
			'models'=>$models,
			'filterDates'=>$filterDates,
			'indexRouteName'=>'suppliers.index'
		]);
    }
	public function create(Company $company)
	{
        return view('suppliers.form',$this->getCommonViewVars($company));
    }
	public function getCommonViewVars(Company $company,$model = null)
	{
	
		return [
			'model'=>$model
		];
	}
	
	public function store(Company $company   , StoreSupplierRequest $request){
		$type = Partner::SUPPLIERS;
		$supplier = new Partner ;
		$supplier->is_supplier = 1 ;
		$supplier->storeBasicForm($request);
		$activeTab = $type ; 
		return response()->json([
			'redirectTo'=>route('suppliers.index',['company'=>$company->id,'active'=>$activeTab])
		]);
		
	}

	public function edit(Company $company,Partner $supplier)
	{

        return view('suppliers.form' ,$this->getCommonViewVars($company,$supplier));
    }
	
	public function update(Company $company, StoreSupplierRequest $request , Partner $supplier){
		
		// $lcSettlementInternalTransfer->deleteRelations();
		// $supplier->delete();
		$oldName = $supplier->getName();
		$newName = $request->get('name');
		$supplier->update([
			'name'=>$newName
		]);
		if($oldName != $newName){
			DB::table('supplier_invoices')->where('supplier_id',$supplier->id)->update([
				'supplier_name'=>$newName
			]);
		}
		$type = Partner::SUPPLIERS;
		// $this->store($company,$request);
		$activeTab = $type ;
		return response()->json([
			'redirectTo'=>route('suppliers.index',['company'=>$company->id,'active'=>$activeTab])
		]);
	}
	
	public function destroy(Company $company , Partner $supplier)
	{
		// $lcSettlementInternalTransfer->deleteRelations();
		$supplier->delete();
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}
	
}
