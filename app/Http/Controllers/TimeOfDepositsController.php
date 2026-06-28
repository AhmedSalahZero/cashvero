<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreTimeOfDepositRequest;
use App\Http\Requests\UpdateTimeOfDepositRequest;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\Company;
use App\Models\CurrentAccountBankStatement;
use App\Models\FinancialInstitution;
use App\Models\TimeOfDeposit;
use App\Services\Api\OdooService;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class TimeOfDepositsController
{
    use GeneralFunctions;
    protected function applyFilter(Request $request,Collection $collection):Collection{
		if(!count($collection)){
			return $collection;
		}
		$searchFieldName = $request->input('field');
		$dateFieldName =  'start_date' ; // change it 
		if($request->input('field') == 'end_date'){
			$dateFieldName = 'end_date';
		}
		
		
		$from = $request->input('from');
		$to = $request->input('to');
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
		->when($request->input('from') , function($collection) use($dateFieldName,$from){
			return $collection->where($dateFieldName,'>=',$from);
		})
		->when($request->input('to') , function($collection) use($dateFieldName,$to){
			return $collection->where($dateFieldName,'<=',$to);
		})
		->sortByDesc('id');
		
		return $collection->values();
	}
	public function index(Company $company,Request $request,FinancialInstitution $financialInstitution)
	{
		$numberOfMonthsBetweenEndDateAndStartDate = 36 ;
		$currentType = $request->input('active',TimeOfDeposit::RUNNING);
		$filterDates = [];
		foreach(TimeOfDeposit::getAllTypes() as $type){
			$startDate = $request->has('startDate') ? $request->input('startDate.'.$type) : now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d');
			$endDate = $request->has('endDate') ? $request->input('endDate.'.$type) : now()->format('Y-m-d');
			
			$filterDates[$type] = [
				'startDate'=>$startDate,
				'endDate'=>$endDate
			];
		}
		/**
		 * * start of running time deposits 
		 */
		$runningTimeOfDepositsStartDate = $filterDates[TimeOfDeposit::RUNNING]['startDate'] ?? null ;
		$runningTimeOfDepositsEndDate = $filterDates[TimeOfDeposit::RUNNING]['endDate'] ?? null ;
		$runningTimeOfDeposits = $financialInstitution->runningTimeOfDeposits ;
		$runningTimeOfDeposits =  $runningTimeOfDeposits->filterByStartDate($runningTimeOfDepositsStartDate,$runningTimeOfDepositsEndDate) ;
		$runningTimeOfDeposits =  $currentType == TimeOfDeposit::RUNNING ? $this->applyFilter($request,$runningTimeOfDeposits):$runningTimeOfDeposits ;
		/**
		 * * end of running time deposits 
		 */
		
		 
		 
		 /**
		 * * start of matured time deposits 
		 */
		$maturedTimeOfDepositsStartDate = $filterDates[TimeOfDeposit::MATURED]['startDate'] ?? null ;
		$maturedTimeOfDepositsEndDate = $filterDates[TimeOfDeposit::MATURED]['endDate'] ?? null ;
		$maturedTimeOfDeposits = $financialInstitution->maturedTimeOfDeposits ;
		$maturedTimeOfDeposits =  $maturedTimeOfDeposits->filterByStartDate($maturedTimeOfDepositsStartDate,$maturedTimeOfDepositsEndDate) ;
		$maturedTimeOfDeposits =  $currentType == TimeOfDeposit::MATURED ? $this->applyFilter($request,$maturedTimeOfDeposits):$maturedTimeOfDeposits ;
		/**
		 * * end of matured time deposits 
		 */
		
		 
		 
		 	 /**
		 * * start of broken time deposits 
		 */
		$brokenTimeOfDepositsStartDate = $filterDates[TimeOfDeposit::BROKEN]['startDate'] ?? null ;
		$brokenTimeOfDepositsEndDate = $filterDates[TimeOfDeposit::BROKEN]['endDate'] ?? null ;
		$brokenTimeOfDeposits = $financialInstitution->brokenTimeOfDeposits ;
		$brokenTimeOfDeposits =  $brokenTimeOfDeposits->filterByStartDate($brokenTimeOfDepositsStartDate,$brokenTimeOfDepositsEndDate) ;
		$brokenTimeOfDeposits =  $currentType == TimeOfDeposit::BROKEN ? $this->applyFilter($request,$brokenTimeOfDeposits):$brokenTimeOfDeposits ;
		/**
		 * * end of broken time deposits 
		 */
		
		 
		
		$searchFields = [
			TimeOfDeposit::RUNNING=>[
				'start_date'=>__('Start Date'),
				'end_date'=>__('End Date'),
				'account_number'=>__('Account Number'),
				'currency'=>__('Currency'),
			],
			TimeOfDeposit::MATURED=>[
				'start_date'=>__('Start Date'),
				'end_date'=>__('End Date'),
				'account_number'=>__('Account Number'),
				'currency'=>__('Currency'),
			],
			TimeOfDeposit::BROKEN=>[
				'start_date'=>__('Start Date'),
				'end_date'=>__('End Date'),
				'account_number'=>__('Account Number'),
				'currency'=>__('Currency'),
			]
		];
		
		 
		$models = [
			TimeOfDeposit::RUNNING =>$runningTimeOfDeposits ,
			TimeOfDeposit::MATURED =>$maturedTimeOfDeposits ,
			TimeOfDeposit::BROKEN =>$brokenTimeOfDeposits ,
		];
		
        return view('reports.time-of-deposit.index', [
			'company'=>$company,
			'filterDates'=>$filterDates,
			'financialInstitution'=>$financialInstitution,
			'searchFields'=>$searchFields,
			'models'=>$models
		]);
    }
	
	public function create(Company $company,FinancialInstitution $financialInstitution)
	{
		$banks = Bank::pluck('view_name','id');
		$selectedBranches =  Branch::getBranchesForCurrentCompany($company->id) ;
		$accountTypes = AccountType::onlyCurrentAccount()->get();
		
		/**
		 * * عباره عن حساب جاري فقط
		 */
		$accounts = $financialInstitution->accounts ;
        return view('reports.time-of-deposit.form',[
			'banks'=>$banks,
			'selectedBranches'=>$selectedBranches,
			'financialInstitution'=>$financialInstitution,
			'accounts'=>$accounts,
			'accountTypes'=>$accountTypes
		]);
    }
	public function getCommonDataArr():array 
	{
		return ['start_date','account_number','amount','end_date','currency','interest_rate','interest_amount','maturity_amount_added_to_account_id','odoo_code','deducted_from_account_id','is_at_maturity'];
	}
	public function store(Company $company  ,FinancialInstitution $financialInstitution, StoreTimeOfDepositRequest $request){
		$data = $request->only( $this->getCommonDataArr());
		foreach(['start_date','end_date'] as $dateField){
			$data[$dateField] = $request->get($dateField) ? Carbon::make($request->get($dateField))->format('Y-m-d'):null;
		}
		$odooCode = $request->get('odoo_code') ;
		$deductedFromAccountId = $request->get('deducted_from_account_id',0) ;
		if($company->hasOdooIntegrationCredentials() && $odooCode ){
			$odooService = new OdooService($company);
			$odooCode = $request->get('odoo_code');
			$chartOfAccountId = $odooService->getChartOfAccountIdFromOdooCode($odooCode);
			$data['odoo_id'] = $chartOfAccountId ; 
			$data['journal_id'] =$odooService->getJournalIdFromChartOfAccountId($chartOfAccountId) ;
		}
		
		$data['created_by'] = auth()->user()->id ;
		$data['company_id'] = $company->id ;
		$data['interest_amount'] = number_unformat($request->get('interest_amount')) ;
		$timeOfDeposit=$financialInstitution->timeOfDeposits()->create($data);
		/**
		 * @var TimeOfDeposit $timeOfDeposit
		 */
		$amount = number_unformat($request->get('amount')) ;
		$startDate = $data['start_date'] ;
		
		$timeOfDeposit->handleDeductedForBankStatement($financialInstitution->id,$startDate,$amount,$company->id,$deductedFromAccountId,$request->get('account_number'));
		
		$timeOfDeposit->handleTdOrCdStoreDepositForOdoo(false);
		
		$type = $request->get('type',TimeOfDeposit::RUNNING);
		$activeTab = $type ; 
		
		return redirect()->route('view.time.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'active'=>$activeTab])->with('success',__('Data Store Successfully'));
		
	}
	
	public function edit(Company $company , Request $request , FinancialInstitution $financialInstitution , TimeOfDeposit $timeOfDeposit){
		$accounts = $financialInstitution->accounts ;
		$accountTypes = AccountType::onlyCurrentAccount()->get();
        return view('reports.time-of-deposit.form',[
			'financialInstitution'=>$financialInstitution,
			'model'=>$timeOfDeposit,
			'accounts'=>$accounts,
			'accountTypes'=>$accountTypes
		]);
		
	}
	
	public function update(Company $company , UpdateTimeOfDepositRequest $request , FinancialInstitution $financialInstitution,TimeOfDeposit $timeOfDeposit){
		$deductedFromAccountId = $request->get('deducted_from_account_id',0) ;
	//	$accountNumberHasChanged = $deductedFromAccountId != $timeOfDeposit->getDeductedFromAccountId();
		$data['updated_by'] = auth()->user()->id ;
		$data = $request->only($this->getCommonDataArr());
		foreach(['start_date','end_date'] as $dateField){
			$data[$dateField] = $request->get($dateField) ? Carbon::make($request->get($dateField))->format('Y-m-d'):null;
		}
		$data['interest_amount'] = number_unformat($request->get('interest_amount')) ;
		if($company->hasOdooIntegrationCredentials()){
			$odooService = new OdooService($company);
			$odooCode = $request->get('odoo_code');
			$chartOfAccountId = $odooService->getChartOfAccountIdFromOdooCode($odooCode);
			$data['odoo_id'] = $chartOfAccountId ; 
			$data['journal_id'] =$odooService->getJournalIdFromChartOfAccountId($chartOfAccountId) ;
		}
		$timeOfDeposit->update($data);
		$timeOfDeposit->deletePeriodInterestAmounts();
		$timeOfDeposit->handleDeductedForBankStatement($financialInstitution->id,$data['start_date'],number_unformat($request->get('amount')),$company->id,$deductedFromAccountId,$request->get('account_number'));
		$timeOfDeposit->handleTdOrCdStoreDepositForOdoo(false);
		$type = $request->get('type',TimeOfDeposit::RUNNING);
		$activeTab = $type ;
		return redirect()->route('view.time.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'active'=>$activeTab])->with('success',__('Item Has Been Updated Successfully'));
	}
	public function destroy(Company $company , FinancialInstitution $financialInstitution , TimeOFDeposit $timeOfDeposit)
	{
		$timeOfDeposit->deletePeriodInterestAmounts();
		$timeOfDeposit->deleteOdooRelations(false);
		CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($timeOfDeposit->currentAccountBankStatements);
		$timeOfDeposit->delete();
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}
	public function applyPeriodInterest(Company $company,Request $request,FinancialInstitution $financialInstitution,TimeOfDeposit $timeOfDeposit)
	{
		$periodInterestAmount = number_unformat($request->get('periodic_interest_amount')) ;
		$periodInterestDate = $request->get('periodic_interest_date') ;
		if(!$periodInterestDate){
			return redirect()->back()->with('fail',__('Period Interest Date Is Required'));
		}
		$timeOfDeposit->applyPeriodicInterestInStatement($financialInstitution,$periodInterestAmount,$periodInterestDate);
		$type = $request->get('type',TimeOfDeposit::RUNNING);
		$activeTab = $type ;
		return redirect()->route('view.time.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'active'=>$activeTab])->with('success',__('Item Has Been Updated Successfully'));
	}
	public function viewPeriodInterest(Company $company,Request $request,FinancialInstitution $financialInstitution,TimeOfDeposit $timeOfDeposit)
	{
		$rows = CurrentAccountBankStatement::where('company_id',$company->id)->where('time_of_deposit_id',$timeOfDeposit->id)->where('is_period_cd_or_td_interest',1)->get();
		return view('reports.time-of-deposit.view-period-interests',['company'=>$company,'financialInstitution'=>$financialInstitution,'model'=>$timeOfDeposit,'rows'=>$rows]);
	}
	public function deletePeriodInterest(Company $company,Request $request,FinancialInstitution $financialInstitution,TimeOfDeposit $timeOfDeposit,CurrentAccountBankStatement $currentAccountBankStatement)
	{
		$timeOfDeposit->deletePeriodInterest($currentAccountBankStatement);
		return redirect()->back()->with('success',__('Item Has Been Updated Successfully'));
	}
	

	/**
	 * * هنا اليوزر هياكد انه نزله الفايدة المستحقة وبالتالي هنزلها في حسابه الجاري اللي هو اختارة من الفورمة
	 */
	public function applyDeposit(Company $company,Request $request,FinancialInstitution $financialInstitution,TimeOfDeposit $timeOfDeposit)
	{
		$actualDepositDate = Carbon::make($request->get('deposit_date')) ;
		if(!$actualDepositDate){
			return redirect()->back()->with('fail',__('Deposit Date Is Required'));
		}
		$actualDepositDate = $actualDepositDate->format('Y-m-d') ;
		$actualInterestAmount  = number_unformat($request->get('actual_interest_amount')) ;
		$type = TimeOfDeposit::MATURED ;
		$timeOfDeposit->update([
			'deposit_date'=>$actualDepositDate,
			'actual_interest_amount'=>$actualInterestAmount,
			'status'=>$type
		]);
		
		$accountType = AccountType::where('slug',AccountType::CURRENT_ACCOUNT)->first() ;
		if($actualInterestAmount > 0){
			$currentAccount = $timeOfDeposit->handleDebitStatement($financialInstitution->id , $accountType , $timeOfDeposit->getMaturityAmountAddedToAccountNumber() , null , $actualDepositDate,$actualInterestAmount,null,null,1,null,null,false,true);
			$timeOfDeposit->storePeriodInterestOdooRelations($currentAccount,$actualDepositDate,$actualInterestAmount);
		}
		$commentEn = __('TD Amount',[],'en');
		$commentAr = __('TD Amount',[],'ar');
		$timeOfDeposit->handleDebitStatement($financialInstitution->id , $accountType , $timeOfDeposit->getMaturityAmountAddedToAccountNumber() , null , $actualDepositDate,$timeOfDeposit->getAmount(),null,null,1,$commentEn,$commentAr);
		$timeOfDeposit->handleTdOrCdStoreDepositForOdoo(true);
		return redirect()->route('view.time.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id ,'active'=>$type])->with('success',__('Time Of Deposit Has Been Marked As Matured'));
	}
	
	
	
		/**
	 * * هنا اليوزر هيعكس عملية التاكيد اللي كان اكدها اكنه عملها بالغلط فا هنرجع كل حاجه زي ما كانت ونحذف القيم اللي في جدول ال 
	 * * current account bank statements
	 */
	public function reverseDeposit(Company $company,Request $request,FinancialInstitution $financialInstitution,TimeOfDeposit $timeOfDeposit)
	{
		// $actualDepositDate = Carbon::make($request->get('actual_deposit_date'))->format('Y-m-d') ;
		// $actualInterestAmount  = $request->get('actual_interest_amount') ;
		$breakInterestStatement = $timeOfDeposit->currentAccountBankStatements->where('is_break_interest',1)->first();
		/**
		 * !!!!
		 */
		CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($timeOfDeposit->currentAccountBankStatements->where('type','!=',CurrentAccountBankStatement::DEDUCTED_FOR_CURRENT_ACCOUNT));
		if($breakInterestStatement){
			$timeOfDeposit->reverseOdooDeposit($breakInterestStatement);
		}
		
		
		
		$type = TimeOfDeposit::RUNNING ;
		$timeOfDeposit->update([
			'deposit_date'=>null,
			'actual_interest_amount'=>null,
			'status'=>TimeOfDeposit::RUNNING,
			'inbound_break_odoo_reference'=>null
		]);
		
		
		
		/**
		 * * هنشيل قيم ال
		 * * current account bank statement
		 */
		return redirect()->route('view.time.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id ,'active'=>$type])->with('success',__('Time Of Deposit Has Been Marked As Matured'));
	}
	
	
	/**
	 * * لو انت عملت شهادة ايداع في البنك تقدر تكسرها وتاخد قيمة الشهادة بتاعتك بس بيطبق عليك غرامة
	 */
	public function applyBreak(Company $company,Request $request,FinancialInstitution $financialInstitution,TimeOfDeposit $timeOfDeposit)
	{
		$breakDate = Carbon::make($request->get('break_date')) ;
		if(!$breakDate){
			return redirect()->back()->with('fail',__('Break Date Is Required'));
		}
		$breakDate = $breakDate->format('Y-m-d') ;
		$breakInterestAmount  = $request->get('break_interest_amount') ;
		$breakChargeAmount  = $request->get('break_charge_amount',0) ;
		$amount  = $request->get('amount') ;
		$type = TimeOfDeposit::BROKEN ;
		$timeOfDeposit->update([
			'break_date'=>$breakDate,
			'break_interest_amount'=>$breakInterestAmount,
			'status'=>$type,
			'break_charge_amount'=>$breakChargeAmount
		]);
		$timeOfDeposit->handleTdOrCdStoreDepositForOdoo(true);
		
		$accountType = AccountType::where('slug',AccountType::CURRENT_ACCOUNT)->first() ;
		/**
		 * * اول حاجه هنضيف دبت بقيمة الشهادة 
		 */
		if($amount > 0){
			$commentEn = __('TD Amount',[],'en');
			$commentAr = __('TD Amount',[],'ar');
			$timeOfDeposit->handleDebitStatement($financialInstitution->id , $accountType , $timeOfDeposit->getMaturityAmountAddedToAccountNumber() , null , $breakDate,$amount,null,null,1,$commentEn , $commentAr);
		}
		/**
		 * * تاني حاجه هنضيف دبت بقيمة الفايدة
		 */
		if($breakInterestAmount > 0){
			$commentEn = __('TD Interest Amount',[],'en');
			$commentAr = __('TD Interest Amount',[],'ar');
			$currentAccount = $timeOfDeposit->handleDebitStatement($financialInstitution->id , $accountType , $timeOfDeposit->getMaturityAmountAddedToAccountNumber() , null , $breakDate,$breakInterestAmount,null,null,1,$commentEn,$commentAr,false,true);
			$timeOfDeposit->storePeriodInterestOdooRelations($currentAccount,$breakDate,$breakInterestAmount);
				
		}
		/**
		 * * واخيرا هنضيف كريدت بقيمة الرسوم الادارية ( رسوم كسر الوديعة)
		 */
		if($breakChargeAmount){
			$commentEn = __('TD Break Fees Amount',[],'en');
			$commentAr = __('TD Break Fees Amount',[],'ar');
			$timeOfDeposit->handleCreditStatement($company->id,$financialInstitution->id , $accountType , $timeOfDeposit->getMaturityAmountAddedToAccountNumber() , null , $breakDate,$breakChargeAmount,null,null,$commentEn,$commentAr);
		}
		
		return redirect()->route('view.time.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id ,'active'=>$type])->with('success',__('Time Of Deposit Has Been Marked As Broken'));
	}
	
	
	/**
	 * * هنا اليوزر هيعكس عملية الكسر اللي كان اكدها اكنه عملها بالغلط فا هنرجع كل حاجه زي ما كانت ونحذف القيم اللي في جدول ال 
	 * * current account bank statements
	 */
	public function reverseBroken(Company $company,Request $request,FinancialInstitution $financialInstitution,TimeOfDeposit $timeOfDeposit)
	{
		$type = TimeOfDeposit::RUNNING ;
		
		$breakInterestStatement = $timeOfDeposit->currentAccountBankStatements->where('is_break_interest',1)->first();
		
		CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($timeOfDeposit->currentAccountBankStatements->where('type','!=',CurrentAccountBankStatement::DEDUCTED_FOR_CURRENT_ACCOUNT));
		if($breakInterestStatement){
			$timeOfDeposit->reverseOdooDeposit($breakInterestStatement);
		}
		
		
		$timeOfDeposit->update([
			'break_date'=>null,
			'break_interest_amount'=>null,
			'status'=>$type,
			'break_charge_amount'=>null,
			'inbound_break_odoo_reference'=>null
		]);
		/**
		 * * هنشيل قيم ال
		 * * current account bank statement
		 */
		
		
		
		 CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($timeOfDeposit->currentAccountBankStatements);
		 
		 
		return redirect()->route('view.time.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id ,'active'=>$type])->with('success',__('Time Of Deposit Has Been Marked As Matured'));
	}
}
