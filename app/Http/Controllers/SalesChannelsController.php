<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreSalesChannelRequest;
use App\Models\CashVeroSalesChannel;
use App\Models\Company;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SalesChannelsController
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
		$currentType = $request->get('active',CashVeroSalesChannel::SALES_CHANNELS);
		
		$filterDates = [];
		foreach([CashVeroSalesChannel::SALES_CHANNELS] as $type){
			$startDate = $request->has('startDate') ? $request->input('startDate.'.$type) : now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d');
			$endDate = $request->has('endDate') ? $request->input('endDate.'.$type) : now()->format('Y-m-d');
			
			$filterDates[$type] = [
				'startDate'=>$startDate,
				'endDate'=>$endDate
			];
		}
		
		
		 
		  /**
		 * * start of $salesChannels 
		 */
		
		$salesChannelStartDate = $filterDates[CashVeroSalesChannel::SALES_CHANNELS]['startDate'] ?? null ;
		$salesChannelEndDate = $filterDates[CashVeroSalesChannel::SALES_CHANNELS]['endDate'] ?? null ;
		$salesChannels = $company->salesChannels ;
		$salesChannels =  $salesChannels->filterByCreatedAt($salesChannelStartDate,$salesChannelEndDate) ;
		
		$salesChannels =  $currentType == CashVeroSalesChannel::SALES_CHANNELS ? $this->applyFilter($request,$salesChannels):$salesChannels ;

		/**
		 * * end of $salesChannels 
		 */
		 
		
		 $searchFields = [
			CashVeroSalesChannel::SALES_CHANNELS=>[
				'created_at'=>__('Created At'),
				'name'=>__('Name')
			],
		];
	
		$models = [
			CashVeroSalesChannel::SALES_CHANNELS =>$salesChannels ,
		];

        return view('sales_channels.index', [
			'company'=>$company,
			'searchFields'=>$searchFields,
			'models'=>$models,
			'filterDates'=>$filterDates,
			'indexRouteName'=>'sales.channels.index',
			'title'=>__('Sales channels'),
			'tableTitle'=>__('Sales channels Table'),
			'createPermissionName'=>'create sales channels',
			'updatePermissionName'=>'update sales channels',
			'deletePermissionName'=>'delete sales channels',
			'createRouteName'=>'sales.channels.create',
			'createRoute'=>route('sales.channels.create',['company'=>$company->id]),
			'editModelName'=>'sales.channels.edit',
			'deleteRouteName'=>'sales.channels.destroy'
		]);
    }
	public function create(Company $company)
	{
        return view('sales_channels.form',$this->getCommonViewVars($company));
    }
	public function getCommonViewVars(Company $company,$model = null)
	{
	
		return [
			'model'=>$model,
			'updateRouteName'=>'sales.channels.update',
			'storeRouteName'=>'sales.channels.store',
		];
	}
	
	public function store(Company $company   , StoreSalesChannelRequest $request){
		$type = CashVeroSalesChannel::SALES_CHANNELS;
		$model = new CashVeroSalesChannel ;
		$model->storeBasicForm($request);
		$activeTab = $type ; 
		return response()->json([
			'redirectTo'=>route('sales.channels.index',['company'=>$company->id,'active'=>$activeTab])
		]);
		
	}

	public function edit(Company $company,CashVeroSalesChannel $salesChannel)
	{

        return view('sales_channels.form' ,$this->getCommonViewVars($company,$salesChannel));
    }
	
	public function update(Company $company, StoreSalesChannelRequest $request , CashVeroSalesChannel $salesChannel){
		
		$newName = $request->get('name');
		$salesChannel->update([
			'name'=>$newName
		]);
		$type = CashVeroSalesChannel::SALES_CHANNELS;
		// $this->store($company,$request);
		$activeTab = $type ;
		return response()->json([
			'redirectTo'=>route('sales.channels.index',['company'=>$company->id,'active'=>$activeTab])
		]);
	}
	
	public function destroy(Company $company , CashVeroSalesChannel $salesChannel)
	{
		// $lcSettlementInternalTransfer->deleteRelations();
		$salesChannel->delete();
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}
	
}
