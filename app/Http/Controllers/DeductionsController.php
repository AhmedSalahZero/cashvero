<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreDeductionRequest;
use App\Models\Company;
use App\Models\Deduction;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DeductionsController
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
		$currentType = $request->get('active',Deduction::DEDUCTIONS);
		
		$filterDates = [];
		foreach([Deduction::DEDUCTIONS] as $type){
			$startDate = $request->has('startDate') ? $request->input('startDate.'.$type) : now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d');
			$endDate = $request->has('endDate') ? $request->input('endDate.'.$type) : now()->format('Y-m-d');
			
			$filterDates[$type] = [
				'startDate'=>$startDate,
				'endDate'=>$endDate
			];
		}
		
		
		 
		  /**
		 * * start of deductions 
		 */
		
		$deductionStartDate = $filterDates[Deduction::DEDUCTIONS]['startDate'] ?? null ;
		$deductionEndDate = $filterDates[Deduction::DEDUCTIONS]['endDate'] ?? null ;
		$deductions = $company->deductions ;
		$deductions =  $deductions->filterByCreatedAt($deductionStartDate,$deductionEndDate) ;
		$deductions =  $currentType == Deduction::DEDUCTIONS ? $this->applyFilter($request,$deductions):$deductions ;

		/**
		 * * end of deductions 
		 */
		 
		
		 $searchFields = [
			Deduction::DEDUCTIONS=>[
				'created_at'=>__('Created At'),
				'name'=>__('Name')
			],
		];
	
		$models = [
			Deduction::DEDUCTIONS =>$deductions ,
		];

        return view('deductions.index', [
			'company'=>$company,
			'searchFields'=>$searchFields,
			'models'=>$models,
			'filterDates'=>$filterDates,
			'indexRouteName'=>'deductions.index'
		]);
    }
	public function create(Company $company)
	{
        return view('deductions.form',$this->getCommonViewVars($company));
    }
	public function getCommonViewVars(Company $company,$model = null)
	{
	
		return [
			'model'=>$model
		];
	}
	
	public function store(Company $company   , StoreDeductionRequest $request){
		$type = Deduction::DEDUCTIONS;
		$deduction = new Deduction ;
		// $deduction->is_deduction = 1 ;
		$deduction->storeBasicForm($request);
		$activeTab = $type ; 
		return response()->json([
			'redirectTo'=>route('deductions.index',['company'=>$company->id,'active'=>$activeTab])
		]);
		
	}

	public function edit(Company $company,Deduction $deduction)
	{

        return view('deductions.form' ,$this->getCommonViewVars($company,$deduction));
    }
	
	public function update(Company $company, StoreDeductionRequest $request , Deduction $deduction){
		
		// $lcSettlementInternalTransfer->deleteRelations();
		// $deduction->delete();
		// $oldName = $deduction->getName();
		$newName = $request->get('name');
		$deduction->update([
			'name'=>$newName
		]);

		$type = Deduction::DEDUCTIONS;
		$activeTab = $type ;
		return response()->json([
			'redirectTo'=>route('deductions.index',['company'=>$company->id,'active'=>$activeTab])
		]);
	}
	
	public function destroy(Company $company , Deduction $deduction)
	{
		// $deduction->deleteRelations();
		$deduction->delete();
		
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}
	
}
