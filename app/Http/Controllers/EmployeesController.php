<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreEmployeeRequest;
use App\Models\Company;
use App\Models\Partner;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class EmployeesController
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
		$currentType = $request->get('active',Partner::EMPLOYEES);
		
		$filterDates = [];
		foreach([Partner::EMPLOYEES] as $type){
			$startDate = $request->has('startDate') ? $request->input('startDate.'.$type) : now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d');
			$endDate = $request->has('endDate') ? $request->input('endDate.'.$type) : now()->format('Y-m-d');
			
			$filterDates[$type] = [
				'startDate'=>$startDate,
				'endDate'=>$endDate
			];
		}
		
		
		 
		  /**
		 * * start of employees 
		 */
		
		$employeeStartDate = $filterDates[Partner::EMPLOYEES]['startDate'] ?? null ;
		$employeeEndDate = $filterDates[Partner::EMPLOYEES]['endDate'] ?? null ;
		$employees = $company->employees ;
		$employees =  $employees->filterByCreatedAt($employeeStartDate,$employeeEndDate) ;
		$employees =  $currentType == Partner::EMPLOYEES ? $this->applyFilter($request,$employees):$employees ;

		/**
		 * * end of employees 
		 */
		 
		
		 $searchFields = [
			Partner::EMPLOYEES=>[
				'created_at'=>__('Created At'),
				'name'=>__('Name')
			],
		];
	
		$models = [
			Partner::EMPLOYEES =>$employees ,
		];

        return view('employees.index', [
			'company'=>$company,
			'searchFields'=>$searchFields,
			'models'=>$models,
			'filterDates'=>$filterDates,
			'indexRouteName'=>'employees.index'
		]);
    }
	public function create(Company $company)
	{
        return view('employees.form',$this->getCommonViewVars($company));
    }
	public function getCommonViewVars(Company $company,$model = null)
	{
	
		return [
			'model'=>$model
		];
	}
	
	public function store(Company $company   , StoreEmployeeRequest $request){
		$type = Partner::EMPLOYEES;
		$employee = new Partner ;
		$employee->is_employee = 1 ;
		$employee->storeBasicForm($request);
		$activeTab = $type ; 
		return response()->json([
			'redirectTo'=>route('employees.index',['company'=>$company->id,'active'=>$activeTab])
		]);
		
	}

	public function edit(Company $company,Partner $employee)
	{

        return view('employees.form' ,$this->getCommonViewVars($company,$employee));
    }
	
	public function update(Company $company, StoreEmployeeRequest $request , Partner $employee){
		
		// $lcSettlementInternalTransfer->deleteRelations();
		// $employee->delete();
		$oldName = $employee->getName();
		$newName = $request->get('name');
		$employee->update([
			'name'=>$newName
		]);
		$employee->updateNamesInAllTables(['customer_name','supplier_name'],$oldName,$newName,$company->id,['partner_type','=','is_employee']);
		$type = Partner::EMPLOYEES;
		// $this->store($company,$request);
		$activeTab = $type ;
		return response()->json([
			'redirectTo'=>route('employees.index',['company'=>$company->id,'active'=>$activeTab])
		]);
	}
	
	public function destroy(Company $company , Partner $employee)
	{
		// $lcSettlementInternalTransfer->deleteRelations();
		$employee->delete();
		
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}
	
}
