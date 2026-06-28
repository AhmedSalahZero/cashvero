<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreBranchRequest;
use App\Models\CashVeroBranch;
use App\Models\Company;
use App\Repositories\SafeRepository;
use App\Services\Api\OdooService;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class BranchesController
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
		$currentType = $request->get('active',CashVeroBranch::BRANCHES);
		
		$filterDates = [];
		foreach([CashVeroBranch::BRANCHES] as $type){
			$startDate = $request->has('startDate') ? $request->input('startDate.'.$type) : now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d');
			$endDate = $request->has('endDate') ? $request->input('endDate.'.$type) : now()->format('Y-m-d');
			
			$filterDates[$type] = [
				'startDate'=>$startDate,
				'endDate'=>$endDate
			];
		}
		
		
		 
		  /**
		 * * start of $branches 
		 */
		
		$branchStartDate = $filterDates[CashVeroBranch::BRANCHES]['startDate'] ?? null ;
		$branchEndDate = $filterDates[CashVeroBranch::BRANCHES]['endDate'] ?? null ;
		$branches = $company->branches ;
		$branches =  $branches->filterByCreatedAt($branchStartDate,$branchEndDate) ;
		
		$branches =  $currentType == CashVeroBranch::BRANCHES ? $this->applyFilter($request,$branches):$branches ;

		/**
		 * * end of $branches 
		 */
		 
		
		 $searchFields = [
			CashVeroBranch::BRANCHES=>[
				'created_at'=>__('Created At'),
				'name'=>__('Name')
			],
		];
	
		$models = [
			CashVeroBranch::BRANCHES =>$branches ,
		];

        return view('branches.index', [
			'company'=>$company,
			'searchFields'=>$searchFields,
			'models'=>$models,
			'filterDates'=>$filterDates,
			'indexRouteName'=>'branches.index',
			'title'=>__('Safe'),
			'tableTitle'=>__('Safe Table'),
			'createPermissionName'=>'create branches',
			'updatePermissionName'=>'update branches',
			'deletePermissionName'=>'delete branches',
			'createRouteName'=>'branches.create',
			'createRoute'=>route('branches.create',['company'=>$company->id]),
			'editModelName'=>'branches.edit',
			'deleteRouteName'=>'branches.destroy'
		]);
    }
	public function create(Company $company)
	{
        return view('branches.form',$this->getCommonViewVars($company));
    }
	public function getCommonViewVars(Company $company,$model = null)
	{
	
		return [
			'model'=>$model,
			'updateRouteName'=>'branches.update',
			'storeRouteName'=>'branches.store',
		];
	}
	
	public function store(Company $company   , StoreBranchRequest $request,SafeRepository $safeRepository){
		$type = CashVeroBranch::BRANCHES;
		$hasOdoo  = $company->hasOdooIntegrationCredentials();
		$odooService = null ;
		if($hasOdoo){
			$odooService = new OdooService($company);
			
		}
		foreach($request->get('safe',[]) as $currentSafeArr){
			$currentSafeArr = array_merge($currentSafeArr , [
				'company_id'=>$company->id,
				'created_by'=>auth()->user()->id
			]);
			$model = $safeRepository->store($currentSafeArr);
			if($hasOdoo){
				$odooService->syncBranchSafe($model->odoo_code,$company->id);
			}
		}
		$activeTab = $type ; 
		return response()->json([
			'redirectTo'=>route('branches.index',['company'=>$company->id,'active'=>$activeTab])
		]);
		
	}

	public function edit(Company $company,CashVeroBranch $branch)
	{

        return view('branches.form' ,$this->getCommonViewVars($company,$branch));
    }
	
	public function update(Company $company, StoreBranchRequest $request , CashVeroBranch $branch){
		
		// $newName = $request->get('name');
		// $odooCode = $request->get('odoo_code');
		$hasOdoo = $company->hasOdooIntegrationCredentials();
		$odooService = null;
		if($hasOdoo){
			$odooService = new OdooService($company);
		}
		foreach($request->get('safe',[]) as $safeArr){
			$branch->update($safeArr);
			if($hasOdoo){
				$odooService->syncBranchSafe($branch->odoo_code,$company->id);
			}
		}
	
		$type = CashVeroBranch::BRANCHES;
		// $this->store($company,$request);
		$activeTab = $type ;
		return response()->json([
			'redirectTo'=>route('branches.index',['company'=>$company->id,'active'=>$activeTab])
		]);
	}
	
	public function destroy(Company $company , CashVeroBranch $branch)
	{
		// $lcSettlementInternalTransfer->deleteRelations();
		$branch->delete();
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}
	public function getBranchesForCurrency(Request $request , Company $company){
		$currency = $request->get('currencyName') ;
		$branches = CashVeroBranch::where('company_id',$company->id)->where('currency',$currency)->pluck('id','name')->toArray() ;
		return response()->json([
			'branches'=>$branches
		]);
	}
}
