<?php
namespace App\Http\Controllers;

use App\Helpers\HArr;
use App\Http\Requests\StoreCurrentAccountRequest;
use App\Http\Requests\StoreFinancialInstitutionRequest;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\CertificatesOfDeposit;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\LetterOfCreditFacility;
use App\Models\LeasingCompany;
use App\Models\MoneyReceived;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class FinancialInstitutionController
{
    use GeneralFunctions;
    protected function applyFilter(Request $request,Collection $collection):Collection{
		if(!count($collection)){
			return $collection;
		}
		$searchFieldName = $request->get('field');
		$dateFieldName = $searchFieldName === 'balance_date' ? 'balance_date' : 'created_at'; 
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
		});
		
		return $collection;
	}
	public function index(Company $company,Request $request)
	{
		$financialInst = new CertificatesOfDeposit();
		
		$type = $request->get('active','bank') ;
		$financialInstitutionsBanks = $company->financialInstitutionsBanks() ;
		$financialInstitutionsBanks = $type == 'bank' ?  $this->applyFilter($request,$financialInstitutionsBanks)  :$financialInstitutionsBanks ;

		$leasingCompanies = $company->leasingCompanies;
		$leasingCompanies = $type == 'leasing_companies' ? $this->applyLeasingFilter($request, $leasingCompanies) : $leasingCompanies;

		$factoringCompanies = $company->factoringCompanies;
		$factoringCompanies = $type == 'factoring_companies' ? $this->applyLeasingFilter($request, $factoringCompanies) : $factoringCompanies;
		$financialInstitutionsMortgageCompanies = $company->financialInstitutionsMortgageCompanies() ;
		$financialInstitutionsMortgageCompanies = $type == 'mortgage_companies' ? $this->applyFilter($request,$financialInstitutionsMortgageCompanies) : $financialInstitutionsMortgageCompanies ;
		
		$companiesSearchFields = [
			'bank_id'=>__('Bank Name'),
			'branch_name'=>__('Branch Name'),
			'company_account_number'=>__('Company Account Number'),
			'swift_code'=>__('Swift Code'),
			'iban_code'=>__('IBAN Code'),
			'current_account_number'=>__('Current Account Number'),
			'balance_amount'=>__('Balance Amount'),
			'balance_date'=>__('Balance Date')
		];
		$leasingInstitutionCompaniesSearchField = [
			'name'=>__('Name'),
		];
		$factoringInstitutionCompaniesSearchField = [
			'name'=>__('Name'),
		];
		$mortgageInstitutionCompaniesSearchField = [
			'name'=>__('Name'),
			'branch_name'=>__('Branch Name'),
		];
		
		$financialInstitutionCompanies = 
		[
		'mortgage_companies'=>[
			'title'=>__('Mortgage Companies Table'),
			'searchFieldsArr'=>$mortgageInstitutionCompaniesSearchField,
			'financialInstitutionCompanies'=>$financialInstitutionsMortgageCompanies
		]
		];
		$selectedBanks = MoneyReceived::getDrawlBanksForCurrentCompany($company->id) ;
		$banks = Bank::pluck('view_name','id');
        return view('reports.financial-institution.index', compact(
			'company',
			'companiesSearchFields',
			'selectedBanks',
			'banks',
			'financialInstitutionsBanks',
			'financialInstitutionCompanies',
			'leasingCompanies',
			'leasingInstitutionCompaniesSearchField',
			'factoringCompanies',
			'factoringInstitutionCompaniesSearchField'
		));
    }

	protected function applyLeasingFilter(Request $request, Collection $collection): Collection
	{
		if (!count($collection)) {
			return $collection;
		}

		$searchFieldName = $request->get('field');
		$dateFieldName = 'created_at';
		$from = $request->get('from');
		$to = $request->get('to');
		$value = $request->query('value');

		return $collection
			->when($request->has('value'), function ($collection) use ($value, $searchFieldName) {
				return $collection->filter(function ($item) use ($value, $searchFieldName) {
					return false !== stristr((string) $item->{$searchFieldName}, (string) $value);
				});
			})
			->when($request->get('from'), function ($collection) use ($dateFieldName, $from) {
				return $collection->where($dateFieldName, '>=', $from);
			})
			->when($request->get('to'), function ($collection) use ($dateFieldName, $to) {
				return $collection->where($dateFieldName, '<=', $to);
			});
	}
	
	public function create(Company $company)
	{
		$exceptBanks = FinancialInstitution::where('company_id',$company->id)->pluck('bank_id')->toArray() ;
		$banks = Bank::whereNotIn('id',$exceptBanks)->pluck('view_name','id');
		$selectedBranches =  Branch::getBranchesForCurrentCompany($company->id) ;
        return view('reports.financial-institution.form',[
			'banks'=>$banks,
			'selectedBranches'=>$selectedBranches,
		]);
    }
	
	public function store(Company $company , StoreFinancialInstitutionRequest $request){
		$type = $request->get('type');
		$data = $request->only(['type','branch_name']);
		$accounts = $type == 'bank' ? $request->get('accounts',[]) : [];

		$data['created_by'] = auth()->user()->id ;
		$data['company_id'] = $company->id ;
		$additionalData = [];

		if($type =='bank'){
			$additionalData = ['bank_id','company_account_number','iban','main_currency'] ;
		}
		else{
			$additionalData = ['name'] ;
		}
	
		foreach($additionalData as $name){
			$data[$name] = $request->get($name);
		}
		// $data['balance_date'] = $request->get('balance_date') ? Carbon::make($request->get('balance_date'))->format('Y-m-d'):null;
		/**
		 * @var FinancialInstitution $financialInstitution
		 */
		$financialInstitution = FinancialInstitution::create($data);
		$financialInstitution->storeNewAccounts($accounts,$company);
		$activeTab = $this->getActiveTab($type);
		return redirect()->route('view.financial.institutions',['company'=>$company->id,'active'=>$activeTab])->with('success',__('Data Store Successfully'));
		
	}
	protected function getActiveTab(string $moneyType)
	{
		return [
			'bank'=>'bank',
			'leasing_companies'=>'leasing_companies',
			'factoring_companies'=>'factoring_companies',
			'mortgage_companies'=>'mortgage_companies'
		][$moneyType];
	}
	public function edit(Company $company , Request $request , FinancialInstitution $financialInstitution){
		$exceptBanks = FinancialInstitution::where('company_id',$company->id)->pluck('bank_id')->toArray() ;
		$exceptBanks = HArr::removeKeyFromArrayByValue($exceptBanks,[$financialInstitution->bank_id]);
		$banks = Bank::whereNotIn('id',$exceptBanks)->pluck('view_name','id');
		$selectedBranches =  Branch::getBranchesForCurrentCompany($company->id) ;
		
        return view('reports.financial-institution.form',[
			'banks'=>$banks,
			'selectedBranches'=>$selectedBranches,
			'model'=>$financialInstitution
		]);
		
	}
	
	public function update(Company $company , StoreFinancialInstitutionRequest $request , FinancialInstitution $financialInstitution){
		$type = $request->get('type');
		$data['updated_by'] = auth()->user()->id ;
		$data = $request->only(['type','branch_name']);
		$additionalData = [];
		if($type =='bank'){
			$additionalData = ['bank_id','company_account_number','swift_code','iban_code','current_account_number','main_currency','balance_amount'] ;
		}
		else{
			$additionalData = ['name'] ;
		}
		foreach($additionalData as $name){
			$data[$name] = $request->get($name);
		}
		// $data['balance_date'] = $request->get('balance_date') ? Carbon::make($request->get('balance_date'))->format('Y-m-d'):null;
		$financialInstitution->update($data);
		// $financialInstitution->getMainAccount();
		 $activeTab = $this->getActiveTab($type);
		return redirect()->route('view.financial.institutions',['company'=>$company->id,'active'=>$activeTab])->with('success',__('Item Has Been Updated Successfully'));
	}
	
	public function destroy(Company $company , FinancialInstitution $financialInstitution)
	{
		$financialInstitution->accounts->each(function($account){
			$account->delete();
		});
		$financialInstitution->delete();
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}
	
	public function addAccount(Company $company , Request $request , FinancialInstitution $financialInstitution)
	{
		return view('reports.financial-institution.add-account',[
			'company'=>$company,
			'financialInstitution'=>$financialInstitution,
		]);
	}
	public function storeAccount(Company $company , StoreCurrentAccountRequest $request , FinancialInstitution $financialInstitution)
	{
		$accounts = $request->get('accounts',[]) ;
		$financialInstitution->storeNewAccounts($accounts,$company);
		return redirect()->route('view.all.bank.accounts',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id ])->with('success',__('Item Has Been Delete Successfully'));
		
	}
	/**
	 * * عرض كل الحسابات الخاصة بالبنك
	 */
	public function viewAllAccounts(Company $company , Request $request , FinancialInstitution $financialInstitution)
	{
		$bankAccounts = [
			$financialInstitution->accounts ,
			$financialInstitution->timeOfDeposits ,
			$financialInstitution->certificatesOfDeposits ,
			$financialInstitution->fullySecuredOverdrafts ,
			$financialInstitution->cleanOverdrafts ,
			$financialInstitution->overdraftAgainstCommercialPapers ,
			$financialInstitution->overdraftAgainstAssignmentOfContracts ,
		];

		return view('bank-accounts.index',[
			'allBankAccounts'=>$bankAccounts,
			'financialInstitution'=>$financialInstitution,
			'company'=>$company,
			'filterDate'=>now()->format('Y-m-d'),
		]);
	}

	public function getInterestRateForFinancialInstitution(Company $company , Request $request)
	{
		$financialInstitutionId = $request->get('financialInstitutionId');
		$letterOfCreditFacilityId = $request->get('letterOfCreditFacilityId');
		if(!$financialInstitutionId || !$letterOfCreditFacilityId){
			return ;
		}
		$letterOfCreditFacility = LetterOfCreditFacility::find($letterOfCreditFacilityId); ;
		$interestRate =  0 ; 
		if($letterOfCreditFacility instanceof LetterOfCreditFacility){
			$interestRate = $letterOfCreditFacility->interest_rate ;
		}
		
		return response()->json([
			'interest_rate'=>$interestRate
		]);
	}	
	public function getLcIssuanceBasedOnFinancialInstitution(Company $company , Request $request)
	{
		$financialInstitutionId = $request->get('financialInstitutionId');
		$currency = $request->get('currency');
		$financialInstitution = FinancialInstitution::find($financialInstitutionId) ;
		$letterOfCreditIssuances = $financialInstitution->letterOfCreditIssuances->where('lc_cash_cover_currency',$currency)->pluck('transaction_name','id')->toArray() ;
		return response()->json([
			'letterOfCreditIssuances'=>$letterOfCreditIssuances
			// 'interest_rate'=>$interestRate
		]);
	}	
	
}
