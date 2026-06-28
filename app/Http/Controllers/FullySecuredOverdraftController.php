<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreFullySecuredOverdraftRequest;
use App\Http\Requests\UpdateFullySecuredOverdraftRequest;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\FullySecuredOverdraft;
use App\Models\Traits\Controllers\HasOverdraftRate;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class FullySecuredOverdraftController
{
    use GeneralFunctions , HasOverdraftRate;
	public static function getModelName()
	{
		return FullySecuredOverdraft::class ;
	}
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
				if($searchFieldName == 'bank_id'){
					$currentValue = $moneyReceived->getBankName() ;  
				}
				return false !== stristr($currentValue , $value);
			});
		})
		->when($request->get('from') , function($collection) use($dateFieldName,$from){
			return $collection->where($dateFieldName,'>=',$from);
		})
		->when($request->get('to') , function($collection) use($dateFieldName,$to){
			return $collection->where($dateFieldName,'<=',$to);
		})
		->sortByDesc('id')->values();
		
		return $collection;
	}
	public function index(Company $company,Request $request,FinancialInstitution $financialInstitution)
	{
		$fullySecuredOverdrafts = $company->fullySecuredOverdrafts->where('financial_institution_id',$financialInstitution->id) ;
		$fullySecuredOverdrafts =   $this->applyFilter($request,$fullySecuredOverdrafts) ;
		$searchFields = [
			'contract_start_date'=>__('Contract Start Date'),
			'contract_end_date'=>__('Contract End Date'),
			'account_number'=>__('Contract Number'),
			'currency'=>__('Currency'),
			'limit'=>__('Limit'),
			'outstanding_balance'=>__('Outstanding Balance'),
			'balance_date'=>__('Balance Date'),
			
		];

        return view('reports.fully-secured-overdraft.index', [
			'company'=>$company,
			'searchFields'=>$searchFields,
			'financialInstitution'=>$financialInstitution,
			'fullySecuredOverdrafts'=>$fullySecuredOverdrafts
		]);
    }
	public function create(Company $company,FinancialInstitution $financialInstitution)
	{
		$banks = Bank::pluck('view_name','id');
		$selectedBranches =  Branch::getBranchesForCurrentCompany($company->id) ;
        return view('reports.fully-secured-overdraft.form',[
			'banks'=>$banks,
			'selectedBranches'=>$selectedBranches,
			'financialInstitution'=>$financialInstitution,
			'cdOrTdAccountTypes' =>AccountType::onlyCdOrTdAccounts()->get()
		]);
    }
	public function getCommonDataArr():array 
	{
		return ['contract_start_date','account_number','contract_end_date','currency','limit','outstanding_balance','balance_date','borrowing_rate','bank_margin_rate','interest_rate','min_interest_rate','highest_debt_balance_rate','admin_fees_rate','to_be_setteled_max_within_days','cd_or_td_account_type_id','cd_or_td_id','cd_or_td_lending_percentage'];
	}
	public function store(Company $company  ,FinancialInstitution $financialInstitution, StoreFullySecuredOverdraftRequest $request){

		$data = $request->only( $this->getCommonDataArr());
		foreach(['contract_start_date','contract_end_date','balance_date'] as $dateField){
			$data[$dateField] = $request->get($dateField) ? Carbon::make($request->get($dateField))->format('Y-m-d'):null;
		}
		$data['created_by'] = auth()->user()->id ;
		$data['company_id'] = $company->id ;
		$data['cd_or_td_account_id'] = $request->get('cd_or_td_id');
		/**
		 * @var FullySecuredOverdraft $fullySecuredOverdraft 
		 */
		$fullySecuredOverdraft = $financialInstitution->fullySecuredOverdrafts()->create($data);
		$type = $request->get('type','fully-secured-over-draft');
		$activeTab = $type ; 
		
		$fullySecuredOverdraft->fullySecuredOverdraftBankStatements()->create([
			'type'=>'active-limit',
			'is_debit'=>1 ,
			'is_credit'=> 0 ,
			'priority'=>3,
			'company_id'=>$company->id ,
			'date'=>$fullySecuredOverdraft->contract_start_date ,
			'limit'=>$fullySecuredOverdraft->limit ,
			'debit'=>0,
			'credit'=>0,
			'comment_en'=>__('Limit'),
			'comment_ar'=>__('Limit',[],'ar'),
			
		]);
		
		
		$fullySecuredOverdraft->storeOutstandingBreakdown($request,$company);
		return response()->json([
			'redirectTo'=>route('view.fully.secured.overdraft',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'active'=>$activeTab])
		]);
		
		
	}

	public function edit(Company $company , Request $request , FinancialInstitution $financialInstitution , FullySecuredOverdraft $fullySecuredOverdraft){
		$banks = Bank::pluck('view_name','id');
		$selectedBranches =  Branch::getBranchesForCurrentCompany($company->id) ;
        return view('reports.fully-secured-overdraft.form',[
			'banks'=>$banks,
			'selectedBranches'=>$selectedBranches,
			'financialInstitution'=>$financialInstitution,
			// 'customers'=>$customers,
			'model'=>$fullySecuredOverdraft,
			'cdOrTdAccountTypes' =>AccountType::onlyCdOrTdAccounts()->get()
		]);
		
	}
	
	public function update(Company $company , UpdateFullySecuredOverdraftRequest $request , FinancialInstitution $financialInstitution,FullySecuredOverdraft $fullySecuredOverdraft){
		$data['updated_by'] = auth()->user()->id ;
		$data = $request->only($this->getCommonDataArr());
		foreach(['contract_start_date','contract_end_date','balance_date'] as $dateField){
			$data[$dateField] = $request->get($dateField) ? Carbon::make($request->get($dateField))->format('Y-m-d'):null;
		}
		
		$fullySecuredOverdraft->update($data);
		$fullySecuredOverdraft->storeOutstandingBreakdown($request,$company);
		$fullySecuredOverdraft->updateLimitRaw();
		
		$type = $request->get('type','fully-secured-over-draft');
		$activeTab = $type ;
		return response()->json([
			'redirectTo'=>route('view.fully.secured.overdraft',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'active'=>$activeTab])
		]);
		
	}
	
	public function destroy(Company $company , FinancialInstitution $financialInstitution , FullySecuredOverdraft $fullySecuredOverdraft)
	{
	
		$fullySecuredOverdraft->delete();
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}

	
	
}
