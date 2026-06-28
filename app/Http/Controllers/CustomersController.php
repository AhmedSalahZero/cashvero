<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreCustomerRequest;
use App\Models\Company;
use App\Models\Partner;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CustomersController
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
		$currentType = $request->get('active',Partner::CUSTOMERS);
		
		$filterDates = [];
		foreach([Partner::CUSTOMERS] as $type){
			$startDate = $request->has('startDate') ? $request->input('startDate.'.$type) : now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d');
			$endDate = $request->has('endDate') ? $request->input('endDate.'.$type) : now()->format('Y-m-d');
			
			$filterDates[$type] = [
				'startDate'=>$startDate,
				'endDate'=>$endDate
			];
		}
		
		
		 
		  /**
		 * * start of customers 
		 */
		
		$customerStartDate = $filterDates[Partner::CUSTOMERS]['startDate'] ?? null ;
		$customerEndDate = $filterDates[Partner::CUSTOMERS]['endDate'] ?? null ;
		$customers = $company->customers ;
		$customers =  $customers->filterByCreatedAt($customerStartDate,$customerEndDate) ;
		$customers =  $currentType == Partner::CUSTOMERS ? $this->applyFilter($request,$customers):$customers ;

		/**
		 * * end of customers 
		 */
		 
		
		 $searchFields = [
			Partner::CUSTOMERS=>[
				
				'created_at'=>__('Created At'),
				'name'=>__('Name')
			],
		];
	
		$models = [
			Partner::CUSTOMERS =>$customers ,
		];

        return view('customers.index', [
			'company'=>$company,
			'searchFields'=>$searchFields,
			'models'=>$models,
			'filterDates'=>$filterDates,
			'indexRouteName'=>'customers.index'
		]);
    }
	public function create(Company $company)
	{
        return view('customers.form',$this->getCommonViewVars($company));
    }
	public function getCommonViewVars(Company $company,$model = null)
	{
	
		return [
			'model'=>$model
		];
	}
	
	public function store(Company $company   , StoreCustomerRequest $request){
		$type = Partner::CUSTOMERS;
		$customer = new Partner ;
		$customer->is_customer = 1 ;
		$customer->storeBasicForm($request);
		$activeTab = $type ; 
		return response()->json([
			'redirectTo'=>route('customers.index',['company'=>$company->id,'active'=>$activeTab])
		]);
		
	}

	public function edit(Company $company,Partner $customer)
	{

        return view('customers.form' ,$this->getCommonViewVars($company,$customer));
    }
	
	public function update(Company $company, StoreCustomerRequest $request , Partner $customer){
		
		// $lcSettlementInternalTransfer->deleteRelations();
		// $customer->delete();
		$oldName = $customer->getName();
		$newName = $request->get('name');
		$customer->update([
			'name'=>$newName
		]);
		if($oldName != $newName){
			DB::table('customer_invoices')->where('customer_id',$customer->id)->update([
				'customer_name'=>$newName
			]);
		}
		$type = Partner::CUSTOMERS;
		// $this->store($company,$request);
		$activeTab = $type ;
		return response()->json([
			'redirectTo'=>route('customers.index',['company'=>$company->id,'active'=>$activeTab])
		]);
	}
	
	public function destroy(Company $company , Partner $customer)
	{
		// $lcSettlementInternalTransfer->deleteRelations();
		$customer->delete();
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}
	
}
