<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreBusinessSectorRequest;
use App\Models\CashVeroBusinessSector;
use App\Models\Company;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BusinessSectorsController
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
		$currentType = $request->get('active',CashVeroBusinessSector::BUSINESS_SECTORS);
		
		$filterDates = [];
		foreach([CashVeroBusinessSector::BUSINESS_SECTORS] as $type){
			$startDate = $request->has('startDate') ? $request->input('startDate.'.$type) : now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d');
			$endDate = $request->has('endDate') ? $request->input('endDate.'.$type) : now()->format('Y-m-d');
			
			$filterDates[$type] = [
				'startDate'=>$startDate,
				'endDate'=>$endDate
			];
		}
		
		
		 
		  /**
		 * * start of $businessSectors 
		 */
		
		$businessSectorStartDate = $filterDates[CashVeroBusinessSector::BUSINESS_SECTORS]['startDate'] ?? null ;
		$businessSectorEndDate = $filterDates[CashVeroBusinessSector::BUSINESS_SECTORS]['endDate'] ?? null ;
		$businessSectors = $company->businessSectors ;
		$businessSectors =  $businessSectors->filterByCreatedAt($businessSectorStartDate,$businessSectorEndDate) ;
		
		$businessSectors =  $currentType == CashVeroBusinessSector::BUSINESS_SECTORS ? $this->applyFilter($request,$businessSectors):$businessSectors ;

		/**
		 * * end of $businessSectors 
		 */
		 
		
		 $searchFields = [
			CashVeroBusinessSector::BUSINESS_SECTORS=>[
				'created_at'=>__('Created At'),
				'name'=>__('Name')
			],
		];
	
		$models = [
			CashVeroBusinessSector::BUSINESS_SECTORS =>$businessSectors ,
		];

        return view('business_sectors.index', [
			'company'=>$company,
			'searchFields'=>$searchFields,
			'models'=>$models,
			'filterDates'=>$filterDates,
			'indexRouteName'=>'business.sectors.index',
			'title'=>__('Business Sectors'),
			'tableTitle'=>__('Business Sectors Table'),
			'createPermissionName'=>'create business sectors',
			'updatePermissionName'=>'update business sectors',
			'deletePermissionName'=>'delete business sectors',
			'createRouteName'=>'business.sectors.create',
			'createRoute'=>route('business.sectors.create',['company'=>$company->id]),
			'editModelName'=>'business.sectors.edit',
			'deleteRouteName'=>'business.sectors.destroy'
		]);
    }
	public function create(Company $company)
	{
        return view('business_sectors.form',$this->getCommonViewVars($company));
    }
	public function getCommonViewVars(Company $company,$model = null)
	{
	
		return [
			'model'=>$model,
			'updateRouteName'=>'business.sectors.update',
			'storeRouteName'=>'business.sectors.store',
		];
	}
	
	public function store(Company $company   , StoreBusinessSectorRequest $request){
		$type = CashVeroBusinessSector::BUSINESS_SECTORS;
		$model = new CashVeroBusinessSector ;
		$model->storeBasicForm($request);
		$activeTab = $type ; 
		return response()->json([
			'redirectTo'=>route('business.sectors.index',['company'=>$company->id,'active'=>$activeTab])
		]);
		
	}

	public function edit(Company $company,CashVeroBusinessSector $businessSector)
	{
        return view('business_sectors.form' ,$this->getCommonViewVars($company,$businessSector));
    }
	
	public function update(Company $company, StoreBusinessSectorRequest $request , CashVeroBusinessSector $businessSector){
		
		$newName = $request->get('name');
		$oldName = $businessSector->getName();
		DB::table('customer_invoices')->where('company_id',$company->id)->where('business_sector',$oldName)->update([
			'business_sector'=>$newName
		]);
		$businessSector->update([
			'name'=>$newName
		]);
		$type = CashVeroBusinessSector::BUSINESS_SECTORS;
		// $this->store($company,$request);
		$activeTab = $type ;
		return response()->json([
			'redirectTo'=>route('business.sectors.index',['company'=>$company->id,'active'=>$activeTab])
		]);
	}
	
	public function destroy(Company $company , CashVeroBusinessSector $businessSector)
	{
		// $lcSettlementInternalTransfer->deleteRelations();
		$businessSector->delete();
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}
	
}
