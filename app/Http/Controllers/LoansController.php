<?php
namespace App\Http\Controllers;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\LcSettlementInternalMoneyTransfer;
use App\Models\LetterOfCreditIssuance;
use App\Models\MediumTermLoan;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class LoansController
{
    use GeneralFunctions;
    protected function applyFilter(Request $request,Collection $collection):Collection{
		if(!count($collection)){
			return $collection;
		}
		$searchFieldName = $request->get('field');
		$dateFieldName =  'created_at' ; // change it 
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
	public function index(Company $company,Request $request)
	{
		
		$numberOfMonthsBetweenEndDateAndStartDate = 18 ;
		$currentType = $request->get('active',LcSettlementInternalMoneyTransfer::BANK_TO_LETTER_OF_CREDIT);
		
		$filterDates = [];
		foreach(LcSettlementInternalMoneyTransfer::getAllTypes() as $type){
			$startDate = $request->has('startDate') ? $request->input('startDate.'.$type) : now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d');
			$endDate = $request->has('endDate') ? $request->input('endDate.'.$type) : now()->format('Y-m-d');
			
			$filterDates[$type] = [
				'startDate'=>$startDate,
				'endDate'=>$endDate
			];
		}
		
		
		 
		  /**
		 * * start of bank to safe internal money transfer 
		 */
		
		$bankToSafeStartDate = $filterDates[LcSettlementInternalMoneyTransfer::BANK_TO_LETTER_OF_CREDIT]['startDate'] ?? null ;
		$bankToSafeEndDate = $filterDates[LcSettlementInternalMoneyTransfer::BANK_TO_LETTER_OF_CREDIT]['endDate'] ?? null ;
		$bankToLcSettlementInternalMoneyTransfers = $company->bankToLcSettlementInternalMoneyTransfers ;
		$bankToLcSettlementInternalMoneyTransfers =  $bankToLcSettlementInternalMoneyTransfers->filterByTransferDate($bankToSafeStartDate,$bankToSafeEndDate) ;
		$bankToLcSettlementInternalMoneyTransfers =  $currentType == LcSettlementInternalMoneyTransfer::BANK_TO_LETTER_OF_CREDIT ? $this->applyFilter($request,$bankToLcSettlementInternalMoneyTransfers):$bankToLcSettlementInternalMoneyTransfers ;

		/**
		 * * end of bank to safe internal money transfer 
		 */
		 
		
		 $searchFields = [
			LcSettlementInternalMoneyTransfer::BANK_TO_LETTER_OF_CREDIT=>[
				'transfer_date'=>__('Transfer Date')
			],
		];
	
		$models = [
			LcSettlementInternalMoneyTransfer::BANK_TO_LETTER_OF_CREDIT =>$bankToLcSettlementInternalMoneyTransfers ,
		];

        return view('lc-settlement-internal-money-transfer.index', [
			'company'=>$company,
			'searchFields'=>$searchFields,
			'models'=>$models
		]);
    }
	public function create(Company $company,FinancialInstitution $financialInstitution)
	{
        return view('loans.form',$this->getCommonViewVars($company,$financialInstitution));
    }
	public function getCommonViewVars(Company $company,$financialInstitution,$model = null)
	{
		$banks = Bank::pluck('view_name','id');
		// $selectedBranches =  Branch::getBranchesForCurrentCompany($company->id) ;
		// $financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get();
		// $accountTypes = AccountType::onlyCurrentAccount()->get();
		return [
			'banks'=>$banks,
			'financialInstitution'=>$financialInstitution,
			// 'selectedBranches'=>$selectedBranches,
			// 'financialInstitutionBanks'=>$financialInstitutionBanks,
			// 'accountTypes'=>$accountTypes,
			'model'=>$model
		];
	}
	
	public function store(Company $company   , Request $request , FinancialInstitution $financialInstitution){
		$type = MediumTermLoan::RUNNING;
		$internalMoneyTransfer = new MediumTermLoan ;
		$internalMoneyTransfer->status = MediumTermLoan::RUNNING;
		$internalMoneyTransfer->storeBasicForm($request);
		$activeTab = $type ; 
		return redirect()->route('loans.index',['company'=>$company->id,'active'=>$activeTab])->with('success',__('Data Store Successfully'));
		
	}

	public function edit(Company $company,MediumTermLoan $cashLoan,FinancialInstitution $financialInstitution)
	{

        return view('loans.form' ,$this->getCommonViewVars($company,$financialInstitution,$cashLoan));
    }
	
	public function update(Company $company, Request $request , FinancialInstitution $financialInstitution , MediumTermLoan $cashLoan){
		
		$cashLoan->deleteRelations();
		$cashLoan->delete();
		$type = MediumTermLoan::RUNNING;
		$this->store($company,$request,$financialInstitution);
		$activeTab = $type ;
		return redirect()->route('loans.index',['company'=>$company->id,'active'=>$activeTab])->with('success',__('Item Has Been Updated Successfully'));
	}
	
	public function destroy(Company $company ,FinancialInstitution $financialInstitution, MediumTermLoan $cashLoan)
	{
		$cashLoan->deleteRelations();
		$cashLoan->delete();
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}
	
}
