<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreSalesPersonRequest;
use App\Models\CashVeroSalesPerson;
use App\Models\Company;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalesPersonsController
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
		$currentType = $request->get('active',CashVeroSalesPerson::SALES_PERSONS);
		
		$filterDates = [];
		foreach([CashVeroSalesPerson::SALES_PERSONS] as $type){
			$startDate = $request->has('startDate') ? $request->input('startDate.'.$type) : now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d');
			$endDate = $request->has('endDate') ? $request->input('endDate.'.$type) : now()->format('Y-m-d');
			
			$filterDates[$type] = [
				'startDate'=>$startDate,
				'endDate'=>$endDate
			];
		}
		
		
		 
		  /**
		 * * start of $salesPersons 
		 */
		
		$salesPersonStartDate = $filterDates[CashVeroSalesPerson::SALES_PERSONS]['startDate'] ?? null ;
		$salesPersonEndDate = $filterDates[CashVeroSalesPerson::SALES_PERSONS]['endDate'] ?? null ;
		$salesPersons = $company->salesPersons ;
		$salesPersons =  $salesPersons->filterByCreatedAt($salesPersonStartDate,$salesPersonEndDate) ;
		
		$salesPersons =  $currentType == CashVeroSalesPerson::SALES_PERSONS ? $this->applyFilter($request,$salesPersons):$salesPersons ;

		/**
		 * * end of $salesPersons 
		 */
		 
		
		 $searchFields = [
			CashVeroSalesPerson::SALES_PERSONS=>[
				'created_at'=>__('Created At'),
				'name'=>__('Name')
			],
		];
	
		$models = [
			CashVeroSalesPerson::SALES_PERSONS =>$salesPersons ,
		];

        return view('sales_persons.index', [
			'company'=>$company,
			'searchFields'=>$searchFields,
			'models'=>$models,
			'filterDates'=>$filterDates,
			'indexRouteName'=>'sales.persons.index',
			'title'=>__('Sales Persons'),
			'tableTitle'=>__('Sales Persons Table'),
			'createPermissionName'=>'create sales persons',
			'updatePermissionName'=>'update sales persons',
			'deletePermissionName'=>'delete sales persons',
			'createRouteName'=>'sales.persons.create',
			'createRoute'=>route('sales.persons.create',['company'=>$company->id]),
			'editModelName'=>'sales.persons.edit',
			'deleteRouteName'=>'sales.persons.destroy'
		]);
    }
	public function create(Company $company)
	{
        return view('sales_persons.form',$this->getCommonViewVars($company));
    }
	public function getCommonViewVars(Company $company,$model = null)
	{
	
		return [
			'model'=>$model,
			'updateRouteName'=>'sales.persons.update',
			'storeRouteName'=>'sales.persons.store',
		];
	}
	
	public function store(Company $company   , StoreSalesPersonRequest $request){
		$type = CashVeroSalesPerson::SALES_PERSONS;
		$model = new CashVeroSalesPerson ;
		$model->storeBasicForm($request);
		$activeTab = $type ; 
		return response()->json([
			'redirectTo'=>route('sales.persons.index',['company'=>$company->id,'active'=>$activeTab])
		]);
		
	}

	public function edit(Company $company,CashVeroSalesPerson $salesPerson)
	{

        return view('sales_persons.form' ,$this->getCommonViewVars($company,$salesPerson));
    }
	
	public function update(Company $company, StoreSalesPersonRequest $request , CashVeroSalesPerson $salesPerson){
		
		$newName = $request->get('name');
		$oldName = $salesPerson->getName();
		DB::table('customer_invoices')->where('company_id',$company->id)->where('sales_person',$oldName)->update([
			'sales_person'=>$newName
		]);
		$salesPerson->update([
			'name'=>$newName
		]);
		$type = CashVeroSalesPerson::SALES_PERSONS;
		// $this->store($company,$request);
		$activeTab = $type ;
		return response()->json([
			'redirectTo'=>route('sales.persons.index',['company'=>$company->id,'active'=>$activeTab])
		]);
	}
	
	public function destroy(Company $company , CashVeroSalesPerson $salesPerson)
	{
		// $lcSettlementInternalTransfer->deleteRelations();
		$salesPerson->delete();
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}
	
}
