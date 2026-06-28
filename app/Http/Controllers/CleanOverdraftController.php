<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreCleanOverdraftRequest;
use App\Http\Requests\UpdateCleanOverdraftRequest;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\CleanOverdraft;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\Traits\Controllers\HasOverdraftRate;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * ! No Odoo Service Yet
 */
class CleanOverdraftController
{
    use GeneralFunctions , HasOverdraftRate;
	public static function getModelName()
	{
		return CleanOverdraft::class ;
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
		
		$cleanOverdrafts = $company->cleanOverdrafts->where('financial_institution_id',$financialInstitution->id) ;
		$cleanOverdrafts =   $this->applyFilter($request,$cleanOverdrafts) ;
		$searchFields = [
			'contract_start_date'=>__('Contract Start Date'),
			'contract_end_date'=>__('Contract End Date'),
			'account_number'=>__('Contract Number'),
			'currency'=>__('Currency'),
			'limit'=>__('Limit'),
			'outstanding_balance'=>__('Outstanding Balance'),
			'balance_date'=>__('Balance Date'),
		];

        return view('reports.clean-overdraft.index', [
			'company'=>$company,
			'searchFields'=>$searchFields,
			'financialInstitution'=>$financialInstitution,
			'cleanOverdrafts'=>$cleanOverdrafts
		]);
    }
	public function create(Company $company,FinancialInstitution $financialInstitution)
	{
		$banks = Bank::pluck('view_name','id');
		$selectedBranches =  Branch::getBranchesForCurrentCompany($company->id) ;
        return view('reports.clean-overdraft.form',[
			'banks'=>$banks,
			'selectedBranches'=>$selectedBranches,
			'financialInstitution'=>$financialInstitution,
		]);
    }
	public function getCommonDataArr():array 
	{
		return ['contract_start_date','account_number','contract_end_date','currency','limit','outstanding_balance','balance_date'
		,'highest_debt_balance_rate','admin_fees_rate','to_be_setteled_max_within_days'];
	}
	public function store(Company $company  ,FinancialInstitution $financialInstitution, StoreCleanOverdraftRequest $request){

		$data = $request->only( $this->getCommonDataArr());
		foreach(['contract_start_date','contract_end_date','balance_date'] as $dateField){
			$data[$dateField] = $request->get($dateField) ? Carbon::make($request->get($dateField))->format('Y-m-d'):null;
		}
		$data['created_by'] = auth()->user()->id ;
		$data['company_id'] = $company->id ;
		/**
		 * @var CleanOverdraft $cleanOverdraft 
		 */
		$cleanOverdraft = $financialInstitution->cleanOverdrafts()->create($data);
		
		$cleanOverdraft->handleEndOfMonthInterestForContractStatements($data['contract_start_date'],$data['contract_end_date'],$company->id);
		
		
		// a new empty line in clean overdraft bank statement
		$cleanOverdraft->cleanOverdraftBankStatements()->create([
			'type'=>'active-limit',
			'is_debit'=>1 ,
			'is_credit'=> 0 ,
			'priority'=>3,
			'company_id'=>$company->id ,
			'date'=>$cleanOverdraft->contract_start_date ,
			'limit'=>$cleanOverdraft->limit ,
			'debit'=>0,
			'credit'=>0,
			'comment_en'=>__('Limit'),
			'comment_ar'=>__('Limit',[],'ar'),
			
		]);
		/**
		 * * Rates Will Be Stored In  Created Observer 
		 */
	
		$type = $request->get('type','clean-over-draft');
		$activeTab = $type ; 
		
		$cleanOverdraft->storeOutstandingBreakdown($request,$company);
		return response()->json([
			'redirectTo'=>route('view.clean.overdraft',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'active'=>$activeTab])
		]);
	}

	public function edit(Company $company , Request $request , FinancialInstitution $financialInstitution , CleanOverdraft $cleanOverdraft){
		$banks = Bank::pluck('view_name','id');
		$selectedBranches =  Branch::getBranchesForCurrentCompany($company->id) ;
        return view('reports.clean-overdraft.form',[
			'banks'=>$banks,
			'selectedBranches'=>$selectedBranches,
			'financialInstitution'=>$financialInstitution,
			// 'customers'=>$customers,
			'model'=>$cleanOverdraft
		]);
		
	}
	
	public function update(Company $company , UpdateCleanOverdraftRequest $request , FinancialInstitution $financialInstitution,CleanOverdraft $cleanOverdraft){
		$data['updated_by'] = auth()->user()->id ;
		
		$data = $request->only($this->getCommonDataArr());
		foreach(['contract_start_date','contract_end_date','balance_date'] as $dateField){
			$data[$dateField] = $request->get($dateField) ? Carbon::make($request->get($dateField))->format('Y-m-d'):null;
		}
		$cleanOverdraft->update($data);
		$cleanOverdraft->handleEndOfMonthInterestForContractStatements($data['contract_start_date'],$data['contract_end_date'],$company->id);
		$cleanOverdraft->storeOutstandingBreakdown($request,$company);
		$cleanOverdraft->updateLimitRaw();
		$type = $request->get('type','clean-over-draft');
		$activeLimitRow = $cleanOverdraft->cleanOverdraftBankStatements->where('type','active-limit')->first();
		$activeLimitRowData = [
			'type'=>'active-limit',
			'is_debit'=>1 ,
			'is_credit'=> 0 ,
			'priority'=>3,
			'company_id'=>$company->id ,
			'date'=>$cleanOverdraft->contract_start_date ,
			'limit'=>$cleanOverdraft->limit ,
			'debit'=>0,
			'credit'=>0,
			'comment_en'=>'-',
			'comment_ar'=>'-',
			
		];
		if($activeLimitRow){
			$activeLimitRow->update($activeLimitRowData);
		}else{
			$cleanOverdraft->cleanOverdraftBankStatements()->create($activeLimitRowData);
		}
		$activeTab = $type ;
		return response()->json([
			'redirectTo'=>route('view.clean.overdraft',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'active'=>$activeTab])
		]);
	}
	
	public function destroy(Company $company , FinancialInstitution $financialInstitution , CleanOverdraft $cleanOverdraft)
	{
		$cleanOverdraft->delete();
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}

	
}
