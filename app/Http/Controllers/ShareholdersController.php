<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreShareholderRequest;
use App\Models\Company;
use App\Models\Partner;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ShareholdersController
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
		$currentType = $request->get('active',Partner::SHAREHOLDERS);
		
		$filterDates = [];
		foreach([Partner::SHAREHOLDERS] as $type){
			$startDate = $request->has('startDate') ? $request->input('startDate.'.$type) : now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d');
			$endDate = $request->has('endDate') ? $request->input('endDate.'.$type) : now()->format('Y-m-d');
			
			$filterDates[$type] = [
				'startDate'=>$startDate,
				'endDate'=>$endDate
			];
		}
		
		
		 
		  /**
		 * * start of shareholders 
		 */
		
		$shareholderStartDate = $filterDates[Partner::SHAREHOLDERS]['startDate'] ?? null ;
		$shareholderEndDate = $filterDates[Partner::SHAREHOLDERS]['endDate'] ?? null ;
		$shareholders = $company->shareholders ;
		$shareholders =  $shareholders->filterByCreatedAt($shareholderStartDate,$shareholderEndDate) ;
		$shareholders =  $currentType == Partner::SHAREHOLDERS ? $this->applyFilter($request,$shareholders):$shareholders ;

		/**
		 * * end of shareholders 
		 */
		 
		
		 $searchFields = [
			Partner::SHAREHOLDERS=>[
				'created_at'=>__('Created At'),
				'name'=>__('Name')
			],
		];
	
		$models = [
			Partner::SHAREHOLDERS =>$shareholders ,
		];

        return view('shareholders.index', [
			'company'=>$company,
			'searchFields'=>$searchFields,
			'models'=>$models,
			'filterDates'=>$filterDates,
			'indexRouteName'=>'shareholders.index'
		]);
    }
	public function create(Company $company)
	{
        return view('shareholders.form',$this->getCommonViewVars($company));
    }
	public function getCommonViewVars(Company $company,$model = null)
	{
	
		return [
			'model'=>$model
		];
	}
	
	public function store(Company $company   , StoreShareholderRequest $request){
		$type = Partner::SHAREHOLDERS;
		$shareholder = new Partner ;
		$shareholder->is_shareholder = 1 ;
		$shareholder->storeBasicForm($request);
		$activeTab = $type ; 
		return response()->json([
			'redirectTo'=>route('shareholders.index',['company'=>$company->id,'active'=>$activeTab])
		]);
		
	}

	public function edit(Company $company,Partner $shareholder)
	{

        return view('shareholders.form' ,$this->getCommonViewVars($company,$shareholder));
    }
	
	public function update(Company $company, StoreShareholderRequest $request , Partner $shareholder){
		
		// $lcSettlementInternalTransfer->deleteRelations();
		// $shareholder->delete();
		$oldName = $shareholder->getName();
		$newName = $request->get('name');
		$shareholder->update([
			'name'=>$newName
		]);

		$type = Partner::SHAREHOLDERS;
		// $this->store($company,$request);
		$activeTab = $type ;
		return response()->json([
			'redirectTo'=>route('shareholders.index',['company'=>$company->id,'active'=>$activeTab])
		]);
	}
	
	public function destroy(Company $company , Partner $shareholder)
	{
		// $lcSettlementInternalTransfer->deleteRelations();
		$shareholder->delete();
		
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}
	
}
