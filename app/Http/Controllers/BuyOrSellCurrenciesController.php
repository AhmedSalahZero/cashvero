<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreBuyOrSellCurrencyRequest;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\BuyOrSellCurrency;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class BuyOrSellCurrenciesController
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
		$paginationPerPage = GeneralFunctions::getPaginationLimit();
		$numberOfMonthsBetweenEndDateAndStartDate = 18 ;
		$currentType = $request->get('active',BuyOrSellCurrency::BANK_TO_BANK);
		
		$filterDates = [];
		foreach(BuyOrSellCurrency::getAllTypes() as $type => $title ){
			$startDate = $request->has('startDate') ? $request->input('startDate.'.$type) : now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d');
			$endDate = $request->has('endDate') ? $request->input('endDate.'.$type) : now()->format('Y-m-d');
			
			$filterDates[$type] = [
				'startDate'=>$startDate,
				'endDate'=>$endDate
			];
		}
		
		/**
		 * * start of bank to bank buy or sell currency 
		 */
		
		$bankToBankStartDate = $filterDates[BuyOrSellCurrency::BANK_TO_BANK]['startDate'] ?? null ;
		$bankToBankEndDate = $filterDates[BuyOrSellCurrency::BANK_TO_BANK]['endDate'] ?? null ;
		$bankToBankBuyOrSellCurrencies = $company->bankToBankBuyOrSellCurrencies()
		->filterByTransactionDate($bankToBankStartDate,$bankToBankEndDate)
		->when($currentType == BuyOrSellCurrency::BANK_TO_BANK, function ($query) {
			$searchFieldName = Request('field');
				$value = Request('value');
				$from = Request('from');
				$to = Request('to');
				$query->when($searchFieldName == 'transaction_date', function ($query) use ($from, $to) {
					$query->whereBetween('transaction_date', [$from, $to]);
				});
		})
		->with([
			'fromBank',
			'fromAccountType',
			'toBank',
			'toAccountType',
			'fromBranch',
			'toBranch',
		])
		->orderByDesc('transaction_date')
		->paginate($paginationPerPage,['*'],'bankToBankBuyOrSellCurrenciesPage')
		 ;
		// $bankToBankBuyOrSellCurrencies =  $bankToBankBuyOrSellCurrencies->filterByTransactionDate($bankToBankStartDate,$bankToBankEndDate) ;
		// $bankToBankBuyOrSellCurrencies =  $currentType == BuyOrSellCurrency::BANK_TO_BANK ? $this->applyFilter($request,$bankToBankBuyOrSellCurrencies):$bankToBankBuyOrSellCurrencies ;


		/**
		 * * end of bank to bank buy or sell currency 
		 */
		
		 
		 /**
		 * * start of safe to bank buy or sell currency 
		 */
		
		$safeToBankStartDate = $filterDates[BuyOrSellCurrency::SAFE_TO_BANK]['startDate'] ?? null ;
		$safeToBankEndDate = $filterDates[BuyOrSellCurrency::SAFE_TO_BANK]['endDate'] ?? null ;
		$safeToBankBuyOrSellCurrencies = $company->safeToBankBuyOrSellCurrencies()
		->filterByTransactionDate($safeToBankStartDate,$safeToBankEndDate)
		->when($currentType == BuyOrSellCurrency::SAFE_TO_BANK, function ($query)  {
			$searchFieldName = Request('field');
				$value = Request('value');
				$from = Request('from');
				$to = Request('to');
				$query->when($searchFieldName == 'transaction_date', function ($query) use ($from, $to) {
					$query->whereBetween('transaction_date', [$from, $to]);
				});
		})
		->with([
			'fromBank',
			'fromAccountType',
			'toBank',
			'toAccountType',
			'fromBranch',
			'toBranch',
		])
		->orderByDesc('transaction_date')
		->paginate($paginationPerPage,['*'],'safeToBankBuyOrSellCurrenciesPage')
		 ;
		// $safeToBankBuyOrSellCurrencies =  $safeToBankBuyOrSellCurrencies->filterByTransactionDate($safeToBankStartDate,$safeToBankEndDate) ;
		// $safeToBankBuyOrSellCurrencies =  $currentType == BuyOrSellCurrency::SAFE_TO_BANK ? $this->applyFilter($request,$safeToBankBuyOrSellCurrencies):$safeToBankBuyOrSellCurrencies ;

		/**
		 * * end of safe to bank buy or sell currency 
		 */
		
		 
		  /**
		 * * start of bank to safe buy or sell currency 
		 */
		
		$bankToSafeStartDate = $filterDates[BuyOrSellCurrency::BANK_TO_SAFE]['startDate'] ?? null ;
		$bankToSafeEndDate = $filterDates[BuyOrSellCurrency::BANK_TO_SAFE]['endDate'] ?? null ;
		$bankToSafeBuyOrSellCurrencies = $company->bankToSafeBuyOrSellCurrencies()
		->filterByTransactionDate($bankToSafeStartDate,$bankToSafeEndDate)
		->when($currentType == BuyOrSellCurrency::BANK_TO_SAFE, function ($query) {
			$searchFieldName = Request('field');
				$value = Request('value');
				$from = Request('from');
				$to = Request('to');
				$query->when($searchFieldName == 'transaction_date', function ($query) use ($from, $to) {
					$query->whereBetween('transaction_date', [$from, $to]);
				});
		})
		->with([
			'fromBank',
			'fromAccountType',
			'toBank',
			'toAccountType',
			'fromBranch',
			'toBranch',
		])
		->orderByDesc('transaction_date')
		->paginate($paginationPerPage,['*'],'bankToSafeBuyOrSellCurrenciesPage')
		 ;
		// $bankToSafeBuyOrSellCurrencies =  $bankToSafeBuyOrSellCurrencies->filterByTransactionDate($bankToSafeStartDate,$bankToSafeEndDate) ;
		// $bankToSafeBuyOrSellCurrencies =  $currentType == BuyOrSellCurrency::BANK_TO_SAFE ? $this->applyFilter($request,$bankToSafeBuyOrSellCurrencies):$bankToSafeBuyOrSellCurrencies ;

		/**
		 * * end of bank to safe buy or sell currency 
		 */
		
		 
		/**
		 * * start of safe to safe buy or sell currency 
		 */
		
		$safeToSafeStartDate = $filterDates[BuyOrSellCurrency::SAFE_TO_SAFE]['startDate'] ?? null ;
		$safeToSafeEndDate = $filterDates[BuyOrSellCurrency::SAFE_TO_SAFE]['endDate'] ?? null ;
		$safeToSafeBuyOrSellCurrencies = $company->safeToSafeBuyOrSellCurrencies()
		->filterByTransactionDate($safeToSafeStartDate,$safeToSafeEndDate)
		->when($currentType == BuyOrSellCurrency::SAFE_TO_SAFE, function ($query)  {
			$searchFieldName = Request('field');
				$value = Request('value');
				$from = Request('from');
				$to = Request('to');
				$query->when($searchFieldName == 'transaction_date', function ($query) use ($from, $to) {
					$query->whereBetween('transaction_date', [$from, $to]);
				});
		})
		->with([
			'fromBank',
			'fromAccountType',
			'toBank',
			'toAccountType',
			'fromBranch',
			'toBranch',
		])
		->orderByDesc('transaction_date')
		->paginate($paginationPerPage,['*'],'safeToSafeBuyOrSellCurrenciesPage')
		 ;
		// $safeToSafeBuyOrSellCurrencies =  $safeToSafeBuyOrSellCurrencies->filterByTransactionDate($safeToSafeStartDate,$safeToSafeEndDate) ;
		// $safeToSafeBuyOrSellCurrencies =  $currentType == BuyOrSellCurrency::SAFE_TO_SAFE ? $this->applyFilter($request,$safeToSafeBuyOrSellCurrencies):$safeToSafeBuyOrSellCurrencies ;

		/**
		 * * end of safe to safe buy or sell currency 
		 */
		
		 
		
		 $searchFields = [
			BuyOrSellCurrency::BANK_TO_BANK=>[
				'transaction_date'=>__('Transaction Date')
			],
			BuyOrSellCurrency::SAFE_TO_BANK=>[
				'transaction_date'=>__('Transaction Date')
			],
			BuyOrSellCurrency::BANK_TO_SAFE=>[
				'transaction_date'=>__('Transaction Date')
			],
			BuyOrSellCurrency::SAFE_TO_SAFE=>[
				'transaction_date'=>__('Transaction Date')
			],
			
		];
	
		$models = [
			BuyOrSellCurrency::BANK_TO_BANK =>$bankToBankBuyOrSellCurrencies ,
			BuyOrSellCurrency::SAFE_TO_BANK =>$safeToBankBuyOrSellCurrencies ,
			BuyOrSellCurrency::BANK_TO_SAFE =>$bankToSafeBuyOrSellCurrencies ,
			BuyOrSellCurrency::SAFE_TO_SAFE =>$safeToSafeBuyOrSellCurrencies ,
			
		];

        return view('buy-or-sell-currency.index', [
			'company'=>$company,
			'searchFields'=>$searchFields,
			'models'=>$models
		]);
    }
	public function create(Company $company)
	{
        return view('buy-or-sell-currency.form',$this->getCommonViewVars($company));
    }
	public function getCommonViewVars(Company $company,$model = null)
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
			// 'type'=>$type
		];
	}
	
	public function store(Company $company  , StoreBuyOrSellCurrencyRequest $request){
		$buyOrSellCurrency = new BuyOrSellCurrency ;
		$type = $request->get('type');
		$transferDate = Carbon::make($request->get('transaction_date'))->format('Y-m-d') ;
		$receivingDate = Carbon::make($transferDate)->addDays($request->get('transfer_days',0))->format('Y-m-d');
		$transferFromAmount = $request->get('currency_to_sell_amount',0) ;
		$transferToAmount =$request->get('currency_to_buy_amount') ;
		$exchangeRate  = $request->get('exchange_rate');
		$buyOrSellCurrency->storeBasicForm($request);
		$fromFinancialInstitutionId = $request->get('from_bank_id');
		$toFinancialInstitutionId = $request->get('to_bank_id');
		$fromAccountTypeId = $request->get('from_account_type_id');
		$toAccountTypeId = $request->get('to_account_type_id');
		$fromAccountNumber = $request->get('from_account_number');
		$toAccountNumber = $request->get('to_account_number');
		$toBranchId = $request->get('to_branch_id');
		$fromBranchId = $request->get('from_branch_id');
		$currencyToSellName = $request->get('currency_to_sell');	
		$currencyToBuyName = $request->get('currency_to_buy');	
		$fromAccountType = AccountType::find($fromAccountTypeId);
		$toAccountType = AccountType::find($toAccountTypeId);
		if($type === BuyOrSellCurrency::BANK_TO_BANK){
			$buyOrSellCurrency->handleBankToBankTransfer($company->id , $fromAccountType , $fromAccountNumber  , $fromFinancialInstitutionId , $toAccountType ,  $toAccountNumber,$toFinancialInstitutionId,$transferDate,$receivingDate,$transferFromAmount,$transferToAmount);
		}
		elseif($type === BuyOrSellCurrency::BANK_TO_SAFE ){
			$buyOrSellCurrency->handleBankToSafeTransfer($company->id , $fromAccountType , $fromAccountNumber  , $fromFinancialInstitutionId ,$toBranchId , $currencyToBuyName , $transferDate,$transferFromAmount,$transferToAmount);
		}
		elseif($type === BuyOrSellCurrency::SAFE_TO_BANK ){
			$buyOrSellCurrency->handleSafeToBankTransfer($company->id , $toAccountType , $toAccountNumber  , $toFinancialInstitutionId ,$fromBranchId , $currencyToSellName , $transferDate,$transferFromAmount,$transferToAmount);
		}
		elseif($type === BuyOrSellCurrency::SAFE_TO_SAFE ){
		
			$buyOrSellCurrency->handleSafeToSafeTransfer($company->id  ,$fromBranchId , $currencyToBuyName , $toBranchId , $currencyToSellName , $exchangeRate , $transferDate,$transferFromAmount,$transferToAmount);
		}
		$buyOrSellCurrency->handleOdooTransfer();
	
		
		$activeTab = $buyOrSellCurrency->getType() ; 
		
		return response()->json([
			'redirectTo'=>route('buy-or-sell-currencies.index',['company'=>$company->id,'active'=>$activeTab])
		]);
		// return redirect()->route('buy-or-sell-currencies.index',['company'=>$company->id,'active'=>$activeTab])->with('success',__('Data Store Successfully'));
		
	}

	public function edit(Company $company,BuyOrSellCurrency $buyOrSellCurrency)
	{
        return view('buy-or-sell-currency.form' ,$this->getCommonViewVars($company,$buyOrSellCurrency));
    }
	
	public function update(Company $company , StoreBuyOrSellCurrencyRequest $request , BuyOrSellCurrency $buyOrSellCurrency){

		// $type = $buyOrSellCurrency->getType();
		$buyOrSellCurrency->deleteRelations();
		$buyOrSellCurrency->delete();
		return $this->store($company,$request);
		// $activeTab = $type ;
		// return redirect()->route('buy-or-sell-currencies.index',['company'=>$company->id,'active'=>$activeTab])->with('success',__('Item Has Been Updated Successfully'));
	}
	
	public function destroy(Company $company , BuyOrSellCurrency $buyOrSellCurrency)
	{
		$buyOrSellCurrency->deleteRelations();
		$buyOrSellCurrency->delete();
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}
	
}
