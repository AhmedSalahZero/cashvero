<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreOverdraftAgainstAssignmentOfContractRequest;
use App\Http\Requests\UpdateOverdraftAgainstAssignmentOfContractRequest;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Contract;
use App\Models\FinancialInstitution;
use App\Models\LendingInformationAgainstAssignmentOfContract;
use App\Models\OverdraftAgainstAssignmentOfContract;
use App\Models\Partner;
use App\Models\Traits\Controllers\HasOverdraftRate;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class OverdraftAgainstAssignmentOfContractController
{
    use GeneralFunctions,HasOverdraftRate;
	public static function getModelName()
	{
		return OverdraftAgainstAssignmentOfContract::class ;
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

		$odAgainstAssignmentOfContracts = $company->overdraftAgainstAssignmentOfContracts->where('financial_institution_id',$financialInstitution->id) ;

		$odAgainstAssignmentOfContracts =   $this->applyFilter($request,$odAgainstAssignmentOfContracts) ;
		
		
		
		$searchFields = [
			'contract_start_date'=>__('Contract Start Date'),
			'contract_end_date'=>__('Contract End Date'),
			'account_number'=>__('Contract Number'),
			'currency'=>__('Currency'),
			'limit'=>__('Limit'),
			'outstanding_balance'=>__('Outstanding Balance'),
			'balance_date'=>__('Balance Date'),
			
		];
		$customers = Partner::where('is_customer',1)->has('contracts')->where('company_id',$company->id)->pluck('name','id')->toArray();

        return view('reports.overdraft-against-assignment-of-contract.index', [
			'company'=>$company,
			'searchFields'=>$searchFields,
			'financialInstitution'=>$financialInstitution,
			'odAgainstAssignmentOfContracts'=>$odAgainstAssignmentOfContracts,
			'customers'=>$customers
		]);
    }
	public function create(Company $company,FinancialInstitution $financialInstitution)
	{
		$banks = Bank::pluck('view_name','id');
		$selectedBranches =  Branch::getBranchesForCurrentCompany($company->id) ;
        return view('reports.overdraft-against-assignment-of-contract.form',[
			'banks'=>$banks,
			// 'customers'=>$customers,
			'selectedBranches'=>$selectedBranches,
			'financialInstitution'=>$financialInstitution,
		]);
    }
	public function getCommonDataArr():array 
	{
		return ['contract_start_date','account_number','contract_end_date','currency','limit','outstanding_balance','balance_date','borrowing_rate','bank_margin_rate','interest_rate','min_interest_rate','highest_debt_balance_rate','admin_fees_rate','to_be_setteled_max_within_days','max_lending_limit_per_contract'];
	}
	public function store(Company $company  ,FinancialInstitution $financialInstitution, StoreOverdraftAgainstAssignmentOfContractRequest $request){
		$data = $request->only( $this->getCommonDataArr());
		foreach(['contract_start_date','contract_end_date','balance_date'] as $dateField){
			$data[$dateField] = $request->get($dateField) ? Carbon::make($request->get($dateField))->format('Y-m-d'):null;
		}
		// $lendingInformation = $request->get('infos',[]) ; 
		$data['created_by'] = auth()->user()->id ;
		$data['company_id'] = $company->id ;
		/**
		 * @var OverdraftAgainstAssignmentOfContract $odAgainstAssignmentOfContract 
		 */
		$odAgainstAssignmentOfContract = $financialInstitution->overdraftAgainstAssignmentOfContracts()->create($data);
		$type = $request->get('type','overdraft-against-assignment-of-contract');
		$activeTab = $type ; 
		
		$odAgainstAssignmentOfContract->storeOutstandingBreakdown($request,$company);
	
		return response()->json([
			'redirectTo'=>route('view.overdraft.against.assignment.of.contract',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'active'=>$activeTab])
		]);
	}

	public function edit(Company $company , Request $request , FinancialInstitution $financialInstitution , OverdraftAgainstAssignmentOfContract $odAgainstAssignmentOfContract){
	
		
		$banks = Bank::pluck('view_name','id');

		$selectedBranches =  Branch::getBranchesForCurrentCompany($company->id) ;
        return view('reports.overdraft-against-assignment-of-contract.form',[
			'banks'=>$banks,
			'selectedBranches'=>$selectedBranches,
			'financialInstitution'=>$financialInstitution,
			// 'customers'=>$customers,
			'model'=>$odAgainstAssignmentOfContract
		]);
		
	}
	
	public function update(Company $company , UpdateOverdraftAgainstAssignmentOfContractRequest $request , FinancialInstitution $financialInstitution,OverdraftAgainstAssignmentOfContract $odAgainstAssignmentOfContract){
		// $infos =  $request->get('infos',[]) ;
		$data['updated_by'] = auth()->user()->id ;
		$data = $request->only($this->getCommonDataArr());
		foreach(['contract_start_date','contract_end_date','balance_date'] as $dateField){
			$data[$dateField] = $request->get($dateField) ? Carbon::make($request->get($dateField))->format('Y-m-d'):null;
		}
		$odAgainstAssignmentOfContract->update($data);
		$odAgainstAssignmentOfContract->triggerChangeOnContracts();
		
		$odAgainstAssignmentOfContract->storeOutstandingBreakdown($request,$company);

		$type = $request->get('type','overdraft-against-assignment-of-contract');
		$activeTab = $type ;
		return response()->json([
			'redirectTo'=>route('view.overdraft.against.assignment.of.contract',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'active'=>$activeTab])
		]);
	}
	
	public function destroy(Company $company , FinancialInstitution $financialInstitution , OverdraftAgainstAssignmentOfContract $odAgainstAssignmentOfContract)
	{
		foreach(['lendingInformation','rates','overdraftAgainstAssignmentOfContractBankLimits','overdraftAgainstAssignmentOfContractBankStatements'] as $hasManyRelationName)
		$odAgainstAssignmentOfContract->{$hasManyRelationName}->each(function($model){
			$model->delete();
		});	
		$odAgainstAssignmentOfContract->delete();
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}
	public function applyLendingInformation(Request $request , Company $company , FinancialInstitution $financialInstitution , OverdraftAgainstAssignmentOfContract $odAgainstAssignmentOfContract )
	{
		$contractId = $request->get('contract_id_create') ;
		$assignmentDate = Carbon::make($request->get('assignment_date_create'))->format('Y-m-d') ;
		$contract = Contract::find($contractId);
		$odAgainstAssignmentOfContract->lendingInformation()->create([
			'company_id'=>$company->id ,
			'lending_rate'=>$request->get('lending_rate_create'),
			'customer_id'=>$request->get('customer_id_create'),
			'assignment_date'=>$assignmentDate,
			'contract_id'=>$contractId,
			'updated_at'=>now()
		]);
	
		$contract->update([
			// 'account_type'=>AccountType::onlyAgainstAssignmentOfContract()->first()->id ,
			// 'account_number'=>$odAgainstAssignmentOfContract->getAccountNumber(),
			'status'=>Contract::RUNNING_AND_AGAINST,
			'updated_at'=>now(),
			'overdraft_against_assignment_of_contract_id'=>$odAgainstAssignmentOfContract->id
		]);
		$statementRow = $odAgainstAssignmentOfContract->overdraftAgainstAssignmentOfContractBankStatements()->where('type','active-limit',)->exists();
		if(!$statementRow){
		
			$statementRow = $odAgainstAssignmentOfContract->overdraftAgainstAssignmentOfContractBankStatements()->create([
				'type'=>'active-limit',
				'debit'=>0,
				'credit'=>0,
				'is_debit'=>1 ,
				'is_credit'=>0,
				'priority'=>3 ,
				'company_id'=>$company->id,
				'date'=>$assignmentDate ,
				'comment_en'=>'-',
				'comment_ar'=>'-'
			]);
		}
		
		
		
		
		return redirect()->back()->with('success',__('Done'));
	
	}
	public function editLendingInformation(Request $request , Company $company , FinancialInstitution $financialInstitution , LendingInformationAgainstAssignmentOfContract $lendingInformation)
	{
		$contractId = $request->get('contract_id_edit') ;
		$assignmentDate = $request->get('assignment_date_edit') ;
		$assignmentDate = Carbon::make($assignmentDate)->format('Y-m-d');
		$contract = Contract::find($contractId);
		$overdraftAgainstAssignmentOfContract = $lendingInformation->overdraftAgainstAssignmentOfContract;
		$lendingInformation->update([
			'lending_rate'=>$request->get('lending_rate_edit'),
			'customer_id'=>$request->get('customer_id_edit'),
			'contract_id'=>$contractId,
			'assignment_date'=>$assignmentDate,
			'updated_at'=>now()
		]);
		$contract->update([
			'overdraft_against_assignment_of_contract_id'=>$overdraftAgainstAssignmentOfContract->id,
			'updated_at'=>now(),
		]);
		
		$statementRow = $overdraftAgainstAssignmentOfContract->overdraftAgainstAssignmentOfContractBankStatements()->where('type','active-limit',)->first();
		if($statementRow){
			$statementRow->update([
					'type'=>'active-limit',
					'debit'=>0,
					'credit'=>0,
					'is_debit'=>1 ,
					'is_credit'=>0,
					'priority'=>3 ,
					'company_id'=>$company->id,
					'date'=>$assignmentDate ,
					'comment_en'=>'-',
					'comment_ar'=>'-'
			]);
			
		}
		
	
		return response()->json([
			'status'=>true ,
			'reloadCurrentPage'=>true 
		]);
	}
	public function deleteLendingInformation(Request $request , Company $company , FinancialInstitution $financialInstitution , LendingInformationAgainstAssignmentOfContract $lendingInformation)
	{
		
		$lendingInformation->contract 
		//  && $lendingInformation->overdraftAgainstAssignmentOfContract->lendingInformation->count() == 1
		 
		 ? $lendingInformation->contract->update([
			'status'=>Contract::RUNNING,
			// 'account_type'=>null,
			// 'account_number'=>null ,
			'overdraft_against_assignment_of_contract_id'=>null
		]) : null;
		
		// $lendingInformation->overdraftAgainstAssignmentOfContract->triggerChangeOnContracts();
		$lendingInformation->delete();
		return redirect()->back()->with('success',__('Done'));
	}
	public function applyAgainstLending(Request $request , Company $company , FinancialInstitution $financialInstitution , LendingInformationAgainstAssignmentOfContract $lendingInformation)
	{
		// $lendingInformation->contract->update();
	}
	
}
