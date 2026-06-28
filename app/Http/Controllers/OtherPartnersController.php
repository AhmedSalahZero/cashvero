<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreOtherPartnerRequest;
use App\Models\Company;
use App\Models\Partner;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class OtherPartnersController
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
		$currentType = $request->get('active',Partner::OTHER_PARTNERS);
		
		$filterDates = [];
		foreach([Partner::OTHER_PARTNERS] as $type){
			$startDate = $request->has('startDate') ? $request->input('startDate.'.$type) : now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d');
			$endDate = $request->has('endDate') ? $request->input('endDate.'.$type) : now()->format('Y-m-d');
			
			$filterDates[$type] = [
				'startDate'=>$startDate,
				'endDate'=>$endDate
			];
		}
		
		
		 
		  /**
		 * * start of otherPartners 
		 */
		
		 $startDate = $filterDates[Partner::OTHER_PARTNERS]['startDate'] ?? null ;
		$endDate = $filterDates[Partner::OTHER_PARTNERS]['endDate'] ?? null ;
		$otherPartners = $company->otherPartners ;
		$otherPartners =  $otherPartners->filterByCreatedAt($startDate,$endDate) ;
		$otherPartners =  $currentType == Partner::OTHER_PARTNERS ? $this->applyFilter($request,$otherPartners):$otherPartners ;

		/**
		 * * end of otherPartners 
		 */
		 
		
		 $searchFields = [
			Partner::OTHER_PARTNERS=>[
				'created_at'=>__('Created At'),
				'name'=>__('Name')
			],
		];
	
		$models = [
			Partner::OTHER_PARTNERS =>$otherPartners ,
		];

        return view('otherPartners.index', [
			'company'=>$company,
			'searchFields'=>$searchFields,
			'models'=>$models,
			'filterDates'=>$filterDates,
			'indexRouteName'=>'other.partners.index'
		]);
    }
	public function create(Company $company)
	{
        return view('otherPartners.form',$this->getCommonViewVars($company));
    }
	public function getCommonViewVars(Company $company,$model = null)
	{
	
		return [
			'model'=>$model
		];
	}
	
	public function store(Company $company   , StoreOtherPartnerRequest $request){
		$type = Partner::OTHER_PARTNERS;
		$otherPartner = new Partner ;
		$otherPartner->is_other_partner = 1 ;
		$otherPartner->storeBasicForm($request);
		$activeTab = $type ; 
		return response()->json([
			'redirectTo'=>route('other.partners.index',['company'=>$company->id,'active'=>$activeTab])
		]);
		
	}

	public function edit(Company $company,Partner $otherPartner)
	{

        return view('otherPartners.form' ,$this->getCommonViewVars($company,$otherPartner));
    }
	
	public function update(Company $company, StoreOtherPartnerRequest $request , Partner $otherPartner){
		
		// $lcSettlementInternalTransfer->deleteRelations();
		// $otherPartner->delete();
		$oldName = $otherPartner->getName();
		$newName = $request->get('name');
		$otherPartner->update([
			'name'=>$newName
		]);
		
		$type = Partner::OTHER_PARTNERS;
		// $this->store($company,$request);
		$activeTab = $type ;
		return response()->json([
			'redirectTo'=>route('other.partners.index',['company'=>$company->id,'active'=>$activeTab])
		]);
	}
	
	public function destroy(Company $company , Partner $otherPartner)
	{
		// $lcSettlementInternalTransfer->deleteRelations();
		$otherPartner->delete();
		
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}
	
}
