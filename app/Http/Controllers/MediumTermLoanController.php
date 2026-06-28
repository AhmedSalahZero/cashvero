<?php
namespace App\Http\Controllers;
use App\Http\Controllers\MoneyReceivedController;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionAccount;
use App\Models\LoanSchedule;
use App\Models\LoanScheduleSettlement;
use App\Models\MediumTermLoan;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class MediumTermLoanController
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
		})
		->sortByDesc('id')->values();
		return $collection;
	}
	public function index(Company $company,Request $request,FinancialInstitution $financialInstitution)
	{
		
		$currentType = $request->get('active',MediumTermLoan::RUNNING);
		
		$filterDates = [];
		foreach(MediumTermLoan::getAllTypes() as $type){
			$endDate = $request->has('endDate') ? $request->input('endDate.'.$type) : now()->format('Y-m-d');
			
			$filterDates[$type] = [
				'endDate'=>$endDate
			];
		}
		
		
		 
		  /**
		 * * start of bank to safe internal money transfer 
		 */
		
		$runningEndDate = $filterDates[MediumTermLoan::RUNNING]['endDate'] ?? null ;
		$mediumTermLoans = $company->mediumTermLoans->where('financial_institution_id',$financialInstitution->id) ;
		$mediumTermLoans =  $mediumTermLoans->filterByLoanEndDate($runningEndDate) ;
		$mediumTermLoans =  $currentType == MediumTermLoan::RUNNING ? $this->applyFilter($request,$mediumTermLoans):$mediumTermLoans ;

		/**
		 * * end of bank to safe internal money transfer 
		 */
		 
		
		 $searchFields = [
			MediumTermLoan::RUNNING=>[
				'name'=>__('Name'),
				'start_date'=>__('Start Date'),
				'end_Date'=>__('End Date'),
			],
		];
	
		$models = [
			MediumTermLoan::RUNNING =>$mediumTermLoans ,
		];

        return view('loans.index', [
			'company'=>$company,
			'searchFields'=>$searchFields,
			'models'=>$models,
			'financialInstitution'=>$financialInstitution,
			'filterDates'=>$filterDates
		]);
    }
	public function create(Company $company,FinancialInstitution $financialInstitution)
	{
        return view('loans.form',$this->getCommonViewVars($company,$financialInstitution));
    }
	public function getCommonViewVars(Company $company,$financialInstitution,$model = null)
	{
		$banks = Bank::pluck('view_name','id');
		return [
			'banks'=>$banks,
			'financialInstitution'=>$financialInstitution,
			'model'=>$model
		];
	}
	
	public function store(Company $company   , Request $request , FinancialInstitution $financialInstitution){
		$type = MediumTermLoan::RUNNING;
		$mediumTermLoan = new MediumTermLoan ;
		$mediumTermLoan->status = MediumTermLoan::RUNNING;
		$mediumTermLoan->storeBasicForm($request);
		$activeTab = $type ; 
		return redirect()->route('loans.index',['company'=>$company->id,'active'=>$activeTab,'financialInstitution'=>$financialInstitution->id])->with('success',__('Data Store Successfully'));
		
	}

	public function edit(Company $company,FinancialInstitution $financialInstitution,MediumTermLoan $mediumTermLoan)
	{

        return view('loans.form' ,$this->getCommonViewVars($company,$financialInstitution,$mediumTermLoan));
    }
	
	public function update(Company $company, Request $request , FinancialInstitution $financialInstitution , MediumTermLoan $mediumTermLoan){
		
		$mediumTermLoan->deleteRelations();
		$mediumTermLoan->delete();
		$type = MediumTermLoan::RUNNING;
		$this->store($company,$request,$financialInstitution);
		$activeTab = $type ;
		return redirect()->route('loans.index',['company'=>$company->id,'active'=>$activeTab,'financialInstitution'=>$financialInstitution->id])->with('success',__('Item Has Been Updated Successfully'));
	}
	
	public function destroy(Company $company ,FinancialInstitution $financialInstitution, MediumTermLoan $mediumTermLoan)
	{
		$mediumTermLoan->deleteRelations();
		$mediumTermLoan->delete();
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}
	public function viewUploadLoanSchedule()
	{
		return view('loans.upload');
	}
	public function viewLoanScheduleSettlement(Company $company , LoanSchedule $loanSchedule)
	{
		if (!$loanSchedule->hasMediumTermLoan()) {
			return redirect()
				->back()
				->with('warning', __('This loan schedule installment is not linked to an active medium term loan.'));
		}

		 return view('admin.loan-schedule-settlements.index',$this->getCommonSettlementVars($company,$loanSchedule));
	}
	public function storeLoanScheduleSettlement(Company $company,Request $request , LoanSchedule $loanSchedule)
	{
		if (!$loanSchedule->hasMediumTermLoan()) {
			return redirect()
				->back()
				->with('warning', __('This loan schedule installment is not linked to an active medium term loan.'));
		}

		$currentAccountNumber = $request->get('current_account_number');
		$amount = number_unformat($request->get('amount'));
		$date = $request->get('date');

		$balanceValidationResponse = $this->validateCurrentAccountBalanceForSettlement(
			$company,
			$loanSchedule,
			$currentAccountNumber,
			$date,
			$amount
		);

		if ($balanceValidationResponse) {
			return $balanceValidationResponse;
		}

		/**
		 * @var LoanScheduleSettlement $loanScheduleSettlement
		 */
		$loanScheduleSettlement=$loanSchedule->settlements()->create([
			'current_account_number'=>$currentAccountNumber,
			'amount'=>$amount,
			'date'=>$date ,
			'company_id'=>$company->id 
		]);
		$financialInstitutionId = $loanSchedule->getFinancialInstitutionId();
		$accountType = AccountType::onlyCurrentAccount()->first();
		$commentEn = __('Settlement For Loan ' .$loanSchedule->getMediumTermLoanName() . ' Installment No. ' . $loanSchedule->getInstallmentNumber()  ,[],'en') ;
		$commentAr = __('Settlement For Loan ' .$loanSchedule->getMediumTermLoanName() . ' Installment No. ' . $loanSchedule->getInstallmentNumber()  ,[],'ar') ;
		$loanScheduleSettlement->handleCreditStatement($company->id , $financialInstitutionId,$accountType,$currentAccountNumber,null,$date,$amount,null,null,$commentEn,$commentAr);
		$loanScheduleSettlement->handleLoanStatement($company->id ,$financialInstitutionId,$currentAccountNumber,$date,$amount,$commentEn,$commentAr);
		return back();
	}
	
	public function updateLoanScheduleSettlement(Company $company,Request $request , LoanScheduleSettlement $loanScheduleSettlement)
	{
		$loanSchedule = $loanScheduleSettlement->loanSchedule;
		$this->deleteLoanScheduleSettlement($company,$request,$loanScheduleSettlement);
		$this->storeLoanScheduleSettlement($company,$request,$loanSchedule);
		return redirect()->route('view.loan.schedule.settlements',['loanSchedule'=>$loanSchedule->id,'company'=>$company->id]);
	}
	public function editLoanScheduleSettlement(Company $company,Request $request , LoanScheduleSettlement $loanScheduleSettlement)
	{
		$loanSchedule = $loanScheduleSettlement->loanSchedule;

		if (!$loanSchedule || !$loanSchedule->hasMediumTermLoan()) {
			return redirect()
				->back()
				->with('warning', __('This loan schedule installment is not linked to an active medium term loan.'));
		}

		return view('admin.loan-schedule-settlements.index',$this->getCommonSettlementVars($company,$loanSchedule,$loanScheduleSettlement));
	}
	public function deleteLoanScheduleSettlement(Company $company,Request $request , LoanScheduleSettlement $loanScheduleSettlement)
	{
		$loanScheduleSettlement->deleteAllRelations();
		$loanScheduleSettlement->delete();
		return back();
	}
	protected function getCommonSettlementVars(Company $company,LoanSchedule $loanSchedule , ?LoanScheduleSettlement $loanScheduleSettlement = null):array 
	{
		$currentAccountType = AccountType::onlyCurrentAccount()->first();
		$currentAccounts = FinancialInstitutionAccount::getAllAccountNumberForCurrency($company->id , $loanSchedule->getCurrency(),$loanSchedule->getFinancialInstitutionId());
		return [
			'loanSchedule'=>$loanSchedule,
			'company'=>$company,
			'settlements'=>$loanSchedule->settlements,
			'currentAccounts'=>$currentAccounts,
			'model'=>$loanScheduleSettlement,
			'currentAccountTypeId'=>$currentAccountType?->id,
			'financialInstitutionId'=>$loanSchedule->getFinancialInstitutionId(),
			'isContractLoanSchedule' => false,
			'settlementRoutes' => [
				'store' => route('store.loan.schedule.settlements', ['company' => $company->id, 'loanSchedule' => $loanSchedule->id]),
				'update' => $loanScheduleSettlement
					? route('update.loan.schedule.settlements', ['company' => $company->id, 'loanScheduleSettlement' => $loanScheduleSettlement->id])
					: null,
				'edit' => 'edit.loan.schedule.settlements',
				'delete' => 'delete.loan.schedule.settlements',
				'back' => route('view.uploading', [
					'company' => $company->id,
					'loanId' => $loanSchedule->getMediumTermLoanId(),
					'model' => 'LoanSchedule',
				]),
			],
			'settlementModelType' => 'LoanScheduleSettlement',
			'settlementRouteParameter' => 'loanScheduleSettlement',
		];
	}

	protected function validateCurrentAccountBalanceForSettlement(
		Company $company,
		LoanSchedule $loanSchedule,
		string $accountNumber,
		string $date,
		float $amount,
		?LoanScheduleSettlement $editingSettlement = null
	): ?\Illuminate\Http\RedirectResponse {
		$accountType = AccountType::onlyCurrentAccount()->first();

		if (!$accountType) {
			return back()->with('fail', __('Current account type is not configured.'))->withInput();
		}

		$balanceRequest = Request::create('/', 'GET', [
			'accountType' => $accountType->id,
			'accountNumber' => $accountNumber,
			'financialInstitutionId' => $loanSchedule->getFinancialInstitutionId(),
			'balanceDate' => Carbon::make($date)->format('Y-m-d'),
			'modelId' => $editingSettlement?->id,
			'modelType' => 'LoanScheduleSettlement',
		]);

		$balanceResponse = (new MoneyReceivedController())->updateNetBalanceBasedOnAccountNumber($balanceRequest, $company);
		$balance = (float) ($balanceResponse->getData()->balance ?? 0);

		if ($amount > $balance) {
			return back()->with('fail', __('Net Balance Less Than Paid Amount'))->withInput();
		}

		return null;
	}
	public function refreshReport(Company $company,Request $request) // ajax 
	{
		$financialInstitutionId = (int) $request->get('financialInstitutionId');
		$loanId = (int) $request->get('mediumTermLoanId');
		$currencyName = $request->get('currencyName');
		$startDate = $request->get('loanStartDate');
		$endDate = $request->get('loanEndDate');
		$result = DB::table('medium_term_loans')
		->where('medium_term_loans.company_id', $company->id)
		->where('medium_term_loans.currency', $currencyName)
		->join('loan_schedules', 'loan_schedules.medium_term_loan_id', '=', 'medium_term_loans.id')
		->when($loanId !== 0, function ($builder) use ($loanId) {
			$builder->where('medium_term_loans.id', '=', $loanId);
		})
		->when($financialInstitutionId !== 0, function ($builder) use ($financialInstitutionId) {
			$builder->where('medium_term_loans.financial_institution_id', '=', $financialInstitutionId);
		})
		->whereBetween('loan_schedules.date', [$startDate, $endDate])
		->orderBy('loan_schedules.date')
		->select([
			'loan_schedules.date',
			'loan_schedules.schedule_payment',
		])
		->get();
		
		return response()->json([
			'status'=>true ,
			'data'=>$result
		]);
	}
	public function getMediumTermLoanForFinancialInstitution(Company $company , Request $request){
		$currency = $request->get('currency');
		$financialInstitutionId = (int) $request->get('financialInstitutionId');

		if ($financialInstitutionId === 0) {
			$loans = $company->mediumTermLoans()->where('currency', $currency)->get();
		} else {
			$financialInstitution = FinancialInstitution::find($financialInstitutionId);
			$loans = $financialInstitution
				? $financialInstitution->loans()->where('currency', $currency)->get()
				: collect();
		}

		return response()->json([
			'status'=>true ,
			'loans'=>$loans
		]);
	}

}
