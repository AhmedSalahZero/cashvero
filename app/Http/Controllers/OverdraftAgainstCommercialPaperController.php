<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreOverdraftAgainstCommercialPaperRequest;
use App\Http\Requests\UpdateOverdraftAgainstCommercialPaperRequest;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\OverdraftAgainstCommercialPaper;
use App\Models\Traits\Controllers\HasOverdraftRate;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class OverdraftAgainstCommercialPaperController
{
    use GeneralFunctions , HasOverdraftRate;
	
	public static function getModelName()
	{
		return OverdraftAgainstCommercialPaper::class ;
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
		
		$overdraftAgainstCommercialPapers = $company->overdraftAgainstCommercialPapers->where('financial_institution_id',$financialInstitution->id) ;

		$overdraftAgainstCommercialPapers =   $this->applyFilter($request,$overdraftAgainstCommercialPapers) ;
		$searchFields = [
			'contract_start_date'=>__('Contract Start Date'),
			'contract_end_date'=>__('Contract End Date'),
			'account_number'=>__('Contract Number'),
			'currency'=>__('Currency'),
			'limit'=>__('Limit'),
			'outstanding_balance'=>__('Outstanding Balance'),
			'balance_date'=>__('Balance Date'),
			
		];

        return view('reports.overdraft-against-commercial-paper.index', [
			'company'=>$company,
			'searchFields'=>$searchFields,
			'financialInstitution'=>$financialInstitution,
			'overdraftAgainstCommercialPapers'=>$overdraftAgainstCommercialPapers,
		
		]);
    }
	public function create(Company $company,FinancialInstitution $financialInstitution)
	{
		// $customers = Partner::where('is_customer',1)->where('company_id',$company->id)->pluck('name','id')->toArray();
		$banks = Bank::pluck('view_name','id');
		$selectedBranches =  Branch::getBranchesForCurrentCompany($company->id) ;
        return view('reports.overdraft-against-commercial-paper.form',[
			'banks'=>$banks,
			// 'customers'=>$customers,
			'selectedBranches'=>$selectedBranches,
			'financialInstitution'=>$financialInstitution,
		]);
    }
	public function getCommonDataArr():array 
	{
		return ['contract_start_date','account_number','contract_end_date','currency','limit','outstanding_balance','balance_date','borrowing_rate','bank_margin_rate','interest_rate','min_interest_rate','highest_debt_balance_rate','admin_fees_rate','to_be_setteled_max_within_days','max_lending_limit_per_customer'];
	}
	public function store(Company $company  ,FinancialInstitution $financialInstitution, StoreOverdraftAgainstCommercialPaperRequest $request){
		
		$data = $request->only( $this->getCommonDataArr());
		foreach(['contract_start_date','contract_end_date','balance_date'] as $dateField){
			$data[$dateField] = $request->get($dateField) ? Carbon::make($request->get($dateField))->format('Y-m-d'):null;
		}
		$lendingInformation = $request->get('infos',[]) ; 
		$data['created_by'] = auth()->user()->id ;
		$data['company_id'] = $company->id ;
		/**
		 * @var OverdraftAgainstCommercialPaper $overdraftAgainstCommercialPaper 
		 */
		$overdraftAgainstCommercialPaper = $financialInstitution->overdraftAgainstCommercialPapers()->create($data);
		$type = $request->get('type','overdraft-against-commercial-paper');
		$activeTab = $type ; 
		
		$overdraftAgainstCommercialPaper->storeOutstandingBreakdown($request,$company);
		foreach($lendingInformation as $lendingInformationArr){
			$overdraftAgainstCommercialPaper->lendingInformation()->create(array_merge($lendingInformationArr , [
			]));
		}
		return response()->json([
			'redirectTo'=>route('view.overdraft.against.commercial.paper',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'active'=>$activeTab])
		]);
		
	}

	public function edit(Company $company , Request $request , FinancialInstitution $financialInstitution , OverdraftAgainstCommercialPaper $overdraftAgainstCommercialPaper){
		$banks = Bank::pluck('view_name','id');
		$selectedBranches =  Branch::getBranchesForCurrentCompany($company->id) ;
		// $customers = Partner::where('is_customer',1)->where('company_id',$company->id)->pluck('name','id')->toArray();
        return view('reports.overdraft-against-commercial-paper.form',[
			'banks'=>$banks,
			'selectedBranches'=>$selectedBranches,
			'financialInstitution'=>$financialInstitution,
			// 'customers'=>$customers,
			'model'=>$overdraftAgainstCommercialPaper
		]);
		
	}
	
	public function update(Company $company , UpdateOverdraftAgainstCommercialPaperRequest $request , FinancialInstitution $financialInstitution,OverdraftAgainstCommercialPaper $overdraftAgainstCommercialPaper){
		// $infos =  $request->get('infos',[]) ;
		$infos =  $request->get('infos',[]) ;
		$data['updated_by'] = auth()->user()->id ;
		$data = $request->only($this->getCommonDataArr());
		foreach(['contract_start_date','contract_end_date','balance_date'] as $dateField){
			$data[$dateField] = $request->get($dateField) ? Carbon::make($request->get($dateField))->format('Y-m-d'):null;
		}
		
		$overdraftAgainstCommercialPaper->update($data);
		$overdraftAgainstCommercialPaper->storeOutstandingBreakdown($request,$company);
		$overdraftAgainstCommercialPaper->lendingInformation()->delete();
		foreach($infos as $lendingInformationArr){
			 $overdraftAgainstCommercialPaper->lendingInformation()->create($lendingInformationArr);
		}
		$overdraftAgainstCommercialPaper->updateFirstLimitsTableFromDate();
		$type = $request->get('type','overdraft-against-commercial-paper');
		$activeTab = $type ;
		return response()->json([
			'redirectTo'=>route('view.overdraft.against.commercial.paper',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'active'=>$activeTab])
		]);
		
		
	}
	
	public function destroy(Company $company , FinancialInstitution $financialInstitution , OverdraftAgainstCommercialPaper $overdraftAgainstCommercialPaper)
	{
		foreach(['lendingInformation','rates','overdraftAgainstCommercialPaperBankLimits','overdraftAgainstCommercialPaperBankStatements'] as $hasManyRelationName){
			$overdraftAgainstCommercialPaper->{$hasManyRelationName}->each(function($model){
				$model->delete();
			});	
		}
		$overdraftAgainstCommercialPaper->delete();
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}

	
	
}
