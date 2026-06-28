<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreInternalMoneyTransferRequest;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\InternalMoneyTransfer;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class InternalMoneyTransferController
{
    use GeneralFunctions;
    // protected function applyFilter(Request $request,Collection $collection):Collection{
	// 	if(!count($collection)){
	// 		return $collection;
	// 	}
	// 	$searchFieldName = $request->get('field');
	// 	$dateFieldName =  'created_at' ; // change it 
	// 	// $dateFieldName = $searchFieldName === 'balance_date' ? 'balance_date' : 'created_at'; 
	// 	$from = $request->get('from');
	// 	$to = $request->get('to');
	// 	$value = $request->query('value');
	// 	$collection = $collection
	// 	->when($request->has('value'),function($collection) use ($value,$searchFieldName){
	// 		return $collection->filter(function($moneyReceived) use ($value,$searchFieldName){
	// 			$currentValue = $moneyReceived->{$searchFieldName} ;
	// 			if($searchFieldName == 'bank_id'){
	// 				$currentValue = $moneyReceived->getBankName() ;  
	// 			}
	// 			return false !== stristr($currentValue , $value);
	// 		});
	// 	})
	// 	->when($request->get('from') , function($collection) use($dateFieldName,$from){
	// 		return $collection->where($dateFieldName,'>=',$from);
	// 	})
	// 	->when($request->get('to') , function($collection) use($dateFieldName,$to){
	// 		return $collection->where($dateFieldName,'<=',$to);
	// 	})
	// 	->sortByDesc('id')->values();
		
	// 	return $collection;
	// }
	public function index(Company $company,Request $request)
	{
		
		$numberOfMonthsBetweenEndDateAndStartDate = 18 ;
		$paginationPerPage = GeneralFunctions::getPaginationLimit();
		$currentType = $request->get('active',InternalMoneyTransfer::BANK_TO_BANK);
		
		$filterDates = [];
		foreach(InternalMoneyTransfer::getAllTypes() as $type){
			$startDate = $request->has('startDate') ? $request->input('startDate.'.$type) : now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d');
			$endDate = $request->has('endDate') ? $request->input('endDate.'.$type) : now()->format('Y-m-d');
			
			$filterDates[$type] = [
				'startDate'=>$startDate,
				'endDate'=>$endDate
			];
		}
		
		/**
		 * * start of bank to bank internal money transfer 
		 */
		
		$bankToBankStartDate = $filterDates[InternalMoneyTransfer::BANK_TO_BANK]['startDate'] ?? null ;
		$bankToBankEndDate = $filterDates[InternalMoneyTransfer::BANK_TO_BANK]['endDate'] ?? null ;
		$bankToBankInternalMoneyTransfers = $company->bankToBankInternalMoneyTransfers()
		->whereBetween('transfer_date',[$bankToBankStartDate,$bankToBankEndDate])
		->when($currentType == InternalMoneyTransfer::BANK_TO_BANK, function ($query)  {
			$searchFieldName = Request('field');
				$value = Request('value');
				$from = Request('from');
				$to = Request('to');
				$query->when($searchFieldName == 'transfer_date', function ($query) use ($from, $to) {
					$query->whereBetween('transfer_date', [$from, $to]);
				});
				
		})
		->orderByDesc('transfer_date')
		->paginate($paginationPerPage,['*'],'bankToBankInternalMoneyTransfersPage')
		 ;
	
		/**
		 * * end of bank to bank internal money transfer 
		 */
		
		 
		 /**
		 * * start of safe to bank internal money transfer 
		 */
		
		$safeToBankStartDate = $filterDates[InternalMoneyTransfer::SAFE_TO_BANK]['startDate'] ?? null ;
		$safeToBankEndDate = $filterDates[InternalMoneyTransfer::SAFE_TO_BANK]['endDate'] ?? null ;
		$safeToBankInternalMoneyTransfers = $company->safeToBankInternalMoneyTransfers()
		->whereBetween('transfer_date',[$safeToBankStartDate,$safeToBankEndDate])
		->when($currentType == InternalMoneyTransfer::SAFE_TO_BANK, function ($query) {
			$searchFieldName = Request('field');
				$value = Request('value');
				$from = Request('from');
				$to = Request('to');
				$query->when($searchFieldName == 'transfer_date', function ($query) use ($from, $to) {
					$query->whereBetween('transfer_date', [$from, $to]);
				});
		})
		->orderByDesc('transfer_date')
		->paginate($paginationPerPage,['*'],'safeToBankInternalMoneyTransfersPage')
		 ;
		// $safeToBankInternalMoneyTransfers =  $safeToBankInternalMoneyTransfers->filterByTransferDate($safeToBankStartDate,$safeToBankEndDate) ;
		// $safeToBankInternalMoneyTransfers =  $currentType == InternalMoneyTransfer::SAFE_TO_BANK ? $this->applyFilter($request,$safeToBankInternalMoneyTransfers):$safeToBankInternalMoneyTransfers ;

		/**
		 * * end of safe to bank internal money transfer 
		 */
		
		 
		  /**
		 * * start of bank to safe internal money transfer 
		 */
		
		$bankToSafeStartDate = $filterDates[InternalMoneyTransfer::BANK_TO_SAFE]['startDate'] ?? null ;
		$bankToSafeEndDate = $filterDates[InternalMoneyTransfer::BANK_TO_SAFE]['endDate'] ?? null ;
		$bankToSafeInternalMoneyTransfers = $company->bankToSafeInternalMoneyTransfers()
		->whereBetween('transfer_date',[$bankToSafeStartDate,$bankToSafeEndDate])
		->when($currentType == InternalMoneyTransfer::BANK_TO_SAFE, function ($query) {
			$searchFieldName = Request('field');
				$value = Request('value');
				$from = Request('from');
				$to = Request('to');
				$query->when($searchFieldName == 'transfer_date', function ($query) use ($from, $to) {
					$query->whereBetween('transfer_date', [$from, $to]);
				});
		})
		->orderByDesc('transfer_date')
		->paginate($paginationPerPage,['*'],'bankToSafeInternalMoneyTransfersPage')
		 ;
	
		/**
		 * * end of bank to safe internal money transfer 
		 */
		 
		 
		  /**
		 * * start of safe to safe internal money transfer 
		 */
		
		$safeToSafeStartDate = $filterDates[InternalMoneyTransfer::SAFE_TO_SAFE]['startDate'] ?? null ;
		$safeToSafeEndDate = $filterDates[InternalMoneyTransfer::SAFE_TO_SAFE]['endDate'] ?? null ;
		$safeToSafeInternalMoneyTransfers = $company->safeToSafeInternalMoneyTransfers()
		->whereBetween('transfer_date',[$safeToSafeStartDate,$safeToSafeEndDate])
		->when($currentType == InternalMoneyTransfer::SAFE_TO_SAFE, function ($query) {
			$searchFieldName = Request('field');
				$value = Request('value');
				$from = Request('from');
				$to = Request('to');
				$query->when($searchFieldName == 'transfer_date', function ($query) use ($from, $to) {
					$query->whereBetween('transfer_date', [$from, $to]);
				});
		})
		->orderByDesc('transfer_date')
		->paginate($paginationPerPage,['*'],'safeToSafeInternalMoneyTransfersPage')
		 ;

		/**
		 * * end of safe to safe internal money transfer 
		 */
		
		
		 $searchFields = [
			InternalMoneyTransfer::BANK_TO_BANK=>[
				'transfer_date'=>__('Transfer Date')
			],
			InternalMoneyTransfer::SAFE_TO_BANK=>[
				'transfer_date'=>__('Deposit Date')
			],
			InternalMoneyTransfer::BANK_TO_SAFE=>[
				'transfer_date'=>__('Withdrawal Date')
			],
			InternalMoneyTransfer::SAFE_TO_SAFE=>[
				'transfer_date'=>__('Withdrawal Date')
			],
			
		];
	
		$models = [
			InternalMoneyTransfer::BANK_TO_BANK =>$bankToBankInternalMoneyTransfers ,
			InternalMoneyTransfer::SAFE_TO_BANK =>$safeToBankInternalMoneyTransfers ,
			InternalMoneyTransfer::BANK_TO_SAFE =>$bankToSafeInternalMoneyTransfers ,
			InternalMoneyTransfer::SAFE_TO_SAFE =>$safeToSafeInternalMoneyTransfers ,
		];

        return view('internal-money-transfer.index', [
			'company'=>$company,
			'searchFields'=>$searchFields,
			'models'=>$models,
			'filterDates'=>$filterDates
		]);
    }
	public function create(Company $company,$type)
	{
		$formName = $type . '-form';
        return view('internal-money-transfer.'.$formName,$this->getCommonViewVars($company,$type));
    }
	public function getCommonViewVars(Company $company,string $type,$model = null)
	{
		$banks = Bank::pluck('view_name','id');
		$selectedBranches =  Branch::getBranchesForCurrentCompany($company->id) ;
		$financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get();
		$accountTypes = AccountType::onlyCashAccounts()->get();
		return [
			'banks'=>$banks,
			'selectedBranches'=>$selectedBranches,
			'financialInstitutionBanks'=>$financialInstitutionBanks,
			'accountTypes'=>$accountTypes,
			'model'=>$model,
			'type'=>$type
		];
	}
	
	public function store(Company $company , string $type  , StoreInternalMoneyTransferRequest $request){
		$internalMoneyTransfer = new InternalMoneyTransfer ;
		$internalMoneyTransfer->type = $type ;
		$transferDate = Carbon::make($request->get('transfer_date'))->format('Y-m-d') ;
		$receivingDate = Carbon::make($transferDate)->addDays($request->get('transfer_days',0))->format('Y-m-d');
		$transferAmount = $request->get('amount') ;
		$internalMoneyTransfer->storeBasicForm($request);
		$fromFinancialInstitutionId = $request->get('from_bank_id');
		// $fromFinancialInstitution = FinancialInstitution::find($fromFinancialInstitutionId);
		$toFinancialInstitutionId = $request->get('to_bank_id');
		// $toFinancialInstitution = FinancialInstitution::find($request->get('to_bank_id'));
		$fromAccountTypeId = $request->get('from_account_type_id');
		$toAccountTypeId = $request->get('to_account_type_id');
		$fromAccountNumber = $request->get('from_account_number');
		$toAccountNumber = $request->get('to_account_number');
		$toBranchId = $request->get('to_branch_id');
		$fromBranchId = $request->get('from_branch_id');
		$currencyName = $request->get('currency');	
		$fromAccountType = AccountType::find($fromAccountTypeId);
		$toAccountType = AccountType::find($toAccountTypeId);
		
		// $fromJournalId = null;
		// $toJournalId =  null ;
		
		if($type === InternalMoneyTransfer::BANK_TO_BANK){
			
			$internalMoneyTransfer->handleBankToBankTransfer($company->id , $fromAccountType , $fromAccountNumber  , $fromFinancialInstitutionId , $toAccountType ,  $toAccountNumber,$toFinancialInstitutionId,$transferDate,$receivingDate,$transferAmount);
		}
		elseif($type === InternalMoneyTransfer::BANK_TO_SAFE ){
			
			$internalMoneyTransfer->handleBankToSafeTransfer($company->id , $fromAccountType , $fromAccountNumber  , $fromFinancialInstitutionId ,$toBranchId , $currencyName , $transferDate,$transferAmount);
		}
		elseif($type === InternalMoneyTransfer::SAFE_TO_BANK ){
			$internalMoneyTransfer->handleSafeToBankTransfer($company->id , $toAccountType , $toAccountNumber  , $toFinancialInstitutionId ,$fromBranchId , $currencyName , $transferDate,$transferAmount);
		}
		elseif($type === InternalMoneyTransfer::SAFE_TO_SAFE ){
			$internalMoneyTransfer->handleSafeToSafeTransfer($company->id ,$toBranchId ,$fromBranchId , $currencyName , $transferDate,$transferAmount);
		}

		$internalMoneyTransfer->handleOdooTransfer();
		
		$activeTab = $type ; 
		
	
		return redirect()->route('internal-money-transfers.index',['company'=>$company->id,'active'=>$activeTab])->with('success',__('Data Store Successfully'));
		
	}

	public function edit(Company $company,string $type,InternalMoneyTransfer $internalMoneyTransfer)
	{
		$formName = $type . '-form';
        return view('internal-money-transfer.'.$formName ,$this->getCommonViewVars($company,$type,$internalMoneyTransfer));
    }
	
	public function update(Company $company , string $type , StoreInternalMoneyTransferRequest $request , InternalMoneyTransfer $internalMoneyTransfer){

		$internalMoneyTransfer->deleteRelations();
		$internalMoneyTransfer->delete();
		$this->store($company,$type,$request);
		$activeTab = $type ;
		return redirect()->route('internal-money-transfers.index',['company'=>$company->id,'active'=>$activeTab])->with('success',__('Item Has Been Updated Successfully'));
	}
	
	public function destroy(Company $company , string $type, InternalMoneyTransfer $internalMoneyTransfer)
	{
	
		$internalMoneyTransfer->deleteRelations();
		$internalMoneyTransfer->delete();
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}
}
