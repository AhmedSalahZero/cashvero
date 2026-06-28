<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreBusinessUnitRequest;
use App\Models\CashVeroBusinessUnit;
use App\Models\Company;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BusinessUnitsController
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
		$currentType = $request->get('active',CashVeroBusinessUnit::BUSINESS_UNITS);
		
		$filterDates = [];
		foreach([CashVeroBusinessUnit::BUSINESS_UNITS] as $type){
			$startDate = $request->has('startDate') ? $request->input('startDate.'.$type) : now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d');
			$endDate = $request->has('endDate') ? $request->input('endDate.'.$type) : now()->format('Y-m-d');
			
			$filterDates[$type] = [
				'startDate'=>$startDate,
				'endDate'=>$endDate
			];
		}
		
		
		 
		  /**
		 * * start of $businessUnits 
		 */
		
		$businessUnitStartDate = $filterDates[CashVeroBusinessUnit::BUSINESS_UNITS]['startDate'] ?? null ;
		$businessUnitEndDate = $filterDates[CashVeroBusinessUnit::BUSINESS_UNITS]['endDate'] ?? null ;
		$businessUnits = $company->businessUnits ;
		$businessUnits =  $businessUnits->filterByCreatedAt($businessUnitStartDate,$businessUnitEndDate) ;
		
		$businessUnits =  $currentType == CashVeroBusinessUnit::BUSINESS_UNITS ? $this->applyFilter($request,$businessUnits):$businessUnits ;

		/**
		 * * end of $businessUnits 
		 */
		 
		
		 $searchFields = [
			CashVeroBusinessUnit::BUSINESS_UNITS=>[
				'created_at'=>__('Created At'),
				'name'=>__('Name')
			],
		];
	
		$models = [
			CashVeroBusinessUnit::BUSINESS_UNITS =>$businessUnits ,
		];

        return view('business_units.index', [
			'company'=>$company,
			'searchFields'=>$searchFields,
			'models'=>$models,
			'filterDates'=>$filterDates,
			'indexRouteName'=>'business.units.index',
			'title'=>__('Business Units'),
			'tableTitle'=>__('Business Units Table'),
			'createPermissionName'=>'create business units',
			'updatePermissionName'=>'update business units',
			'deletePermissionName'=>'delete business units',
			'createRouteName'=>'business.units.create',
			'createRoute'=>route('business.units.create',['company'=>$company->id]),
			'editModelName'=>'business.units.edit',
			'deleteRouteName'=>'business.units.destroy'
		]);
    }
	public function create(Company $company)
	{
        return view('business_units.form',$this->getCommonViewVars($company));
    }
	public function getCommonViewVars(Company $company,$model = null)
	{
	
		return [
			'model'=>$model,
			'updateRouteName'=>'business.units.update',
			'storeRouteName'=>'business.units.store',
		];
	}
	
	public function store(Company $company   , StoreBusinessUnitRequest $request){
		$type = CashVeroBusinessUnit::BUSINESS_UNITS;
		$model = new CashVeroBusinessUnit ;
		$model->storeBasicForm($request);
		$activeTab = $type ; 
		return response()->json([
			'redirectTo'=>route('business.units.index',['company'=>$company->id,'active'=>$activeTab])
		]);
		
	}

	public function edit(Company $company,CashVeroBusinessUnit $businessUnit)
	{

        return view('business_units.form' ,$this->getCommonViewVars($company,$businessUnit));
    }
	
	public function update(Company $company, StoreBusinessUnitRequest $request , CashVeroBusinessUnit $businessUnit){
		
		$newName = $request->get('name');
		$oldName = $businessUnit->getName();
		DB::table('customer_invoices')->where('company_id',$company->id)->where('business_unit',$oldName)->update([
			'business_unit'=>$newName
		]);
		
		$businessUnit->update([
			'name'=>$newName
		]);
		$type = CashVeroBusinessUnit::BUSINESS_UNITS;
		// $this->store($company,$request);
		$activeTab = $type ;
		return response()->json([
			'redirectTo'=>route('business.units.index',['company'=>$company->id,'active'=>$activeTab])
		]);
	}
	
	public function destroy(Company $company , CashVeroBusinessUnit $businessUnit)
	{
		// $lcSettlementInternalTransfer->deleteRelations();
		$businessUnit->delete();
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}
	
}
