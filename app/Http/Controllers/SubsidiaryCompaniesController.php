<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreSubsidiaryCompanyRequest;
use App\Models\Company;
use App\Models\Partner;
use App\Services\Api\OdooService;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SubsidiaryCompaniesController
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
		$currentType = $request->get('active',Partner::SUBSIDIARY_COMPANIES);
		
		$filterDates = [];
		foreach([Partner::SUBSIDIARY_COMPANIES] as $type){
			$startDate = $request->has('startDate') ? $request->input('startDate.'.$type) : now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d');
			$endDate = $request->has('endDate') ? $request->input('endDate.'.$type) : now()->format('Y-m-d');
			
			$filterDates[$type] = [
				'startDate'=>$startDate,
				'endDate'=>$endDate
			];
		}
		
		
		 
		  /**
		 * * start of subsidiaryCompanies 
		 */
		
		 $startDate = $filterDates[Partner::SUBSIDIARY_COMPANIES]['startDate'] ?? null ;
		$endDate = $filterDates[Partner::SUBSIDIARY_COMPANIES]['endDate'] ?? null ;
		$subsidiaryCompanies = $company->subsidiaryCompanies ;
		$subsidiaryCompanies =  $subsidiaryCompanies->filterByCreatedAt($startDate,$endDate) ;
		$subsidiaryCompanies =  $currentType == Partner::SUBSIDIARY_COMPANIES ? $this->applyFilter($request,$subsidiaryCompanies):$subsidiaryCompanies ;

		/**
		 * * end of subsidiaryCompanies 
		 */
		 
		
		 $searchFields = [
			Partner::SUBSIDIARY_COMPANIES=>[
				'created_at'=>__('Created At'),
				'name'=>__('Name')
			],
		];
	
		$models = [
			Partner::SUBSIDIARY_COMPANIES =>$subsidiaryCompanies ,
		];

        return view('subsidiaryCompanies.index', [
			'company'=>$company,
			'searchFields'=>$searchFields,
			'models'=>$models,
			'filterDates'=>$filterDates,
			'indexRouteName'=>'subsidiary.companies.index',
			'companyHasOdoo'=>$company->hasOdooIntegrationCredentials()
		]);
    }
	public function create(Company $company)
	{
        return view('subsidiaryCompanies.form',$this->getCommonViewVars($company));
    }
	public function getCommonViewVars(Company $company,$model = null)
	{
	
		return [
			'model'=>$model,
			// 'companyHasOdoo'=>$company->hasOdooIntegrationCredentials()
		];
	}
	
	public function store(Company $company   , StoreSubsidiaryCompanyRequest $request){
		$type = Partner::SUBSIDIARY_COMPANIES;
		$subsidiaryCompany = new Partner ;
		$subsidiaryCompany->is_subsidiary_company = 1 ;
		
		$subsidiaryCompany = $subsidiaryCompany->storeBasicForm($request);
			$subsidiaryCompany->syncAccounts($request, $company);
		
		$activeTab = $type ; 
		return response()->json([
			'redirectTo'=>route('subsidiary.companies.index',['company'=>$company->id,'active'=>$activeTab])
		]);
		
	}

	public function edit(Company $company,Partner $subsidiaryCompany)
	{
        return view('subsidiaryCompanies.form' ,$this->getCommonViewVars($company,$subsidiaryCompany));
    }
	
	public function update(Company $company, StoreSubsidiaryCompanyRequest $request , Partner $subsidiaryCompany){
		
		// $lcSettlementInternalTransfer->deleteRelations();
		// $subsidiaryCompany->delete();
		$oldName = $subsidiaryCompany->getName();
		$newName = $request->get('name');
		$subsidiaryCompany->update([
			'name'=>$newName
		]);
		$subsidiaryCompany->syncAccounts($request, $company);
		
		
		
		$type = Partner::SUBSIDIARY_COMPANIES;
		// $this->store($company,$request);
		$activeTab = $type ;
		return response()->json([
			'redirectTo'=>route('subsidiary.companies.index',['company'=>$company->id,'active'=>$activeTab])
		]);
	}
	
	public function destroy(Company $company , Partner $subsidiaryCompany)
	{
		// $lcSettlementInternalTransfer->deleteRelations();
		$subsidiaryCompany->delete();
		
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}
	
}
