<?php
namespace App\Http\Controllers;

use App\Http\Requests\StorePartnerRequest;
use App\Models\Company;
use App\Models\Partner;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PartnersController
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
	//	->sortByDesc('id')
		
		return $collection;
	}
	public function index(Company $company,Request $request)
	{
		
		$numberOfMonthsBetweenEndDateAndStartDate = 18 ;
		$currentType = $request->get('active',Partner::PARTNERS);
		
		$filterDates = [];
		foreach([Partner::PARTNERS] as $type){
			$startDate = $request->has('startDate') ? $request->input('startDate.'.$type) : now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d');
			$endDate = $request->has('endDate') ? $request->input('endDate.'.$type) : now()->format('Y-m-d');
			
			$filterDates[$type] = [
				'startDate'=>$startDate,
				'endDate'=>$endDate
			];
		}
		
		
		 
		  /**
		 * * start of PARTNERS 
		 */
		
		 $partnerStartDate = $filterDates[Partner::PARTNERS]['startDate'] ?? null ;
		 $partnerEndDate = $filterDates[Partner::PARTNERS]['endDate'] ?? null ;
		 if($request->has('field')){
			 $partnerStartDate = $request->get('from');
		 }
		 if($request->has('field')){
			 $partnerEndDate = $request->get('to');
		 }
		 $partners = $company->partners->where('is_tax','!=',1) ;
		 $partners =  $partners->filterByCreatedAt($partnerStartDate,$partnerEndDate) ;
		 $partners =  $currentType == Partner::PARTNERS ? $this->applyFilter($request,$partners):$partners ;
 
		 /**
		  * * end of PARTNERS 
		  */
		  
		 
		
		 $searchFields = [
			Partner::PARTNERS=>[
		//		'created_at'=>__('Created At'),
				'name'=>__('Name')
			],
		];
	
		$models = [
			Partner::PARTNERS =>$partners ,
		];

        return view('partners.index', [
			'company'=>$company,
			'searchFields'=>$searchFields,
			'models'=>$models,
			'filterDates'=>$filterDates,
			'indexRouteName'=>'partners.index'
		]);
    }
	public function create(Company $company)
	{
        return view('partners.form',$this->getCommonViewVars($company));
    }
	public function getCommonViewVars(Company $company,$model = null)
	{
	
		return [
			'model'=>$model,
			'companyHasOdoo'=>$company->hasOdooIntegrationCredentials()
		];
	}
	
	public function store(Company $company   , StorePartnerRequest $request){
		$type = Partner::PARTNERS;
		$partner = new Partner ;
		$partner->storeBasicForm($request);
		$activeTab = $type ; 
		return response()->json([
			'redirectTo'=>route('partners.index',['company'=>$company->id,'active'=>$activeTab])
		]);
		
	}

	public function edit(Company $company,Partner $partner)
	{

        return view('partners.form' ,$this->getCommonViewVars($company,$partner));
    }
	
	public function update(Company $company, StorePartnerRequest $request , Partner $partner){
		// $lcSettlementInternalTransfer->deleteRelations();
		// $partner->delete();
		$oldName = $partner->getName();
		$partner->update([
			'is_customer'=>$request->boolean('is_customer'),
			'is_supplier'=>$request->boolean('is_supplier'),
			'is_employee'=>$request->boolean('is_employee'),
			'is_shareholder'=>$request->boolean('is_shareholder'),
			'is_other_partner'=>$request->boolean('is_other_partner'),
			'is_subsidiary_company'=>$request->boolean('is_subsidiary_company'),
		]);
		
		
		$newName = $request->get('name');
		$partner->storeBasicForm($request);
		$partner->update([
			'name'=>$newName
		]);
		DB::table('customer_invoices')->where('customer_id',$partner->id)->update([
			'customer_name'=>$newName
		]);
		DB::table('supplier_invoices')->where('supplier_id',$partner->id)->update([
			'supplier_name'=>$newName
		]);
		$type = Partner::PARTNERS;
		// $this->store($company,$request);
		$activeTab = $type ;
		return response()->json([
			'redirectTo'=>route('partners.index',['company'=>$company->id,'active'=>$activeTab])
		]);
	}
	
	public function destroy(Company $company , Partner $partner)
	{
		// $lcSettlementInternalTransfer->deleteRelations();
		$partner->delete();
		
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}
	
}
