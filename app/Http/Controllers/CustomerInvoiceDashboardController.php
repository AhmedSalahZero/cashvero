<?php

namespace App\Http\Controllers;
use App\Enums\LcTypes;
use App\Enums\LgTypes;
use App\Helpers\HArr;
use App\Helpers\HDate;
use App\Http\Controllers\CashFlowReportController;
use App\Http\Controllers\WithdrawalsSettlementReportController;
use App\Models\AccountType;
use App\Models\Branch;
use App\Models\CashInSafeStatement;
use App\Models\CertificatesOfDeposit;
use App\Models\CleanOverdraft;
use App\Models\Company;
use App\Models\CustomerInvoice;
use App\Models\Deduction;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionAccount;
use App\Models\ForeignExchangeRate;
use App\Models\FullySecuredOverdraft;
use App\Models\LetterOfCreditIssuance;
use App\Models\LetterOfGuaranteeIssuance;
use App\Models\MediumTermLoan;
use App\Models\OverdraftAgainstAssignmentOfContract;
use App\Models\OverdraftAgainstCommercialPaper;
use App\Models\Partner;
use App\Models\TimeOfDeposit;
use App\Services\CashDashboardService;
use App\Models\Traits\Controllers\HasBalances;
use App\ReadyFunctions\ChequeAgingService;
use App\ReadyFunctions\InvoiceAgingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CustomerInvoiceDashboardController extends Controller
{
	use HasBalances;
    public function viewCashDashboard(Company $company, Request $request)
    {
        return view('admin.dashboard.cash', app(CashDashboardService::class)->build($company, $request));
    }
	public function refreshBankMovementChart(Request $request,Company $company){
		$numberOfWeeks = 2 ;
		$currency = $request->get('currencyName');
		$accountNumber = $request->get('accountNumber');
		$companyId = $company->id ;
		$date = $request->get('date') ;
		$date = Carbon::make($date)->format('Y-m-d');
		$modelName = $request->get('modelName');
		$fullName = new ('\App\Models\\'.$modelName) ;
		$financialInstitutionBankId = $request->get('bankId');
		$account = $fullName::findByAccountNumber($accountNumber,$companyId,$financialInstitutionBankId);
		if (! $account) {
			return response()->json(['chart_date' => []]);
		}

		$bankStatementName = $fullName::getBankStatementTableName() ;
		$foreignKeyInStatementTable = $fullName->getForeignKeyInStatementTable();
		$foreignKeyName = $fullName::generateForeignKeyFormModelName();
		$dateBeforeWeeks = Carbon::make($date)->subWeeks($numberOfWeeks)->format('Y-m-d');
		$model = new  $fullName ;
		$tableName = $model->getTable();
		$begin = new \DateTime($dateBeforeWeeks );
		$end   = new \DateTime( $date );
		$chartData = [];

		$dailyTotals = DB::table($bankStatementName)
			->where($bankStatementName.'.company_id', $company->id)
			->whereBetween('date', [$dateBeforeWeeks, $date])
			->where($foreignKeyName, $account->id)
			->join($tableName, $tableName.'.id', '=', $bankStatementName.'.'.$foreignKeyInStatementTable)
			->where('financial_institution_id', $financialInstitutionBankId)
			->where('currency', $currency)
			->groupBy('date')
			->selectRaw('date, SUM(debit) as total_debit, SUM(credit) as total_credit')
			->get()
			->keyBy('date');

		$statementHistory = DB::table($bankStatementName)
			->where($bankStatementName.'.company_id', $company->id)
			->where('date', '<=', $date)
			->where($foreignKeyName, $account->id)
			->join($tableName, $tableName.'.id', '=', $bankStatementName.'.'.$foreignKeyInStatementTable)
			->where('financial_institution_id', $financialInstitutionBankId)
			->where('currency', $currency)
			->orderBy('date')
			->orderBy($bankStatementName.'.id')
			->get(['date', 'end_balance', $bankStatementName.'.id as statement_id']);

		$historyIndex = 0;
		$historyCount = $statementHistory->count();
		$lastEndBalance = 0;

		for ($currentDateObject = clone $begin; $currentDateObject <= $end; $currentDateObject->modify('+1 day')) {
			$currentDateAsString = $currentDateObject->format('Y-m-d');

			while ($historyIndex < $historyCount && $statementHistory[$historyIndex]->date <= $currentDateAsString) {
				$lastEndBalance = (float) $statementHistory[$historyIndex]->end_balance;
				$historyIndex++;
			}

			$totalsAtDate = $dailyTotals->get($currentDateAsString);
			$chartData[] = [
				'date' => $currentDateAsString,
				'debit' => (float) ($totalsAtDate->total_debit ?? 0),
				'credit' => (float) ($totalsAtDate->total_credit ?? 0),
				'end_balance' => $lastEndBalance,
			];
		}

		return response()->json([
			'chart_date'=>$chartData
		]);
	}
	public function sumForTotalCard(array $oldArr  , array $newItems ):array{
		foreach($newItems as $index => $oldItems){
			foreach($oldItems as $key => $value){
				$oldArr[$key]   =  isset($oldArr[$key]) ? $oldArr[$key] + $value : $value ;
			}
		}
		return $oldArr;
	}
	
	public function formatFlowCashInOutChartData(array $totalCashInItems ,array $totalCashOutItems,array $dates ){
		$totalCashInItems = HArr::removeKeysFromArray($totalCashInItems,['total_of_total']);
		$totalCashOutItems = HArr::removeKeysFromArray($totalCashOutItems,['total_of_total']);
		// $dates2 = array_merge(array_keys($totalCashInItems),array_keys($totalCashOutItems));
		
		$formattedResult = [];
		foreach($dates as  $weekAndYear => $startAndEndDateArray){
				$endDate = $startAndEndDateArray['end_date'];
				$currentCashIn = $totalCashInItems[$weekAndYear] ?? 0 ;
				$currentCashOut = $totalCashOutItems[$weekAndYear] ?? 0 ;
				$formattedResult[] = ['date'=>$endDate,'cash_in'=>$currentCashIn , 'cash_out'=>$currentCashOut];
		}
		return $formattedResult;
	}
    public function viewForecastDashboard(Company $company, Request $request)
    {
		$clientsWithContracts = Partner::onlyCompany($company->id)	->onlyCustomers()->onlyThatHaveContracts()->get();
		$allCurrencies = getCurrenciesForSuppliersAndCustomers($company->id) ;
		// $financialInstitutionsThatHaveMediumTermLoans = FinancialInstitution::onlyCompany($company->id)->onlyHasMediumTermLoans()->get();
		$dashboardResult = [];
		$moneyReceivedOrPaymentModelNameMap = [
			'CustomerInvoice'=>'MoneyReceived',
			'SupplierInvoice'=>'MoneyPayment'
		];
		$cashFlowReportResult = null ;
		$cashFlowReport = [];
		$contractCode = null;
		$reportCurrentName = null;
		$weeks = [];
		if($request->has('contract_id')){
			$report =(new ContractCashFlowReportController())->result($company,$request,true,-1);
			if($report instanceof RedirectResponse){
				return $report;
			}
			$cashFlowReportResult = $report['result'];
			$dates = $report['dates'];
			$weeks = $report['weeks'];
			$contractCode = $report['contractCode'];
			$reportCurrentName = $report['currencyName'];
			$reportInterval = $report['reportInterval'];
			$pastDueSupplierInvoices = $report['pastDueSupplierInvoices'];
			$pastDueInstallments = $report['pastDueInstallments'];
			$pastDueCustomerInvoices = $report['pastDueCustomerInvoices'];
			$cashFlowReport['total_cash_in_out_flow']=$this->formatFlowCashInOutChartData($cashFlowReportResult['customers'][__('Total Cash Inflow')]['total'] ?? [],$cashFlowReportResult['cash_expenses'][__('Total Cash Outflow')]['total'] ?? [],$dates);
			$cashFlowReport['accumulated_net_cash']= formatAccumulatedNetCash($cashFlowReportResult['cash_expenses'][__('Net Cash (+/-)')]['total'] ?? [] ,$dates );
		}else{
			$report =(new CashFlowReportController())->result($company,$request,true,null,-1);
			if($report instanceof RedirectResponse){
				return $report;
			}
				$reportInterval = $report['reportInterval'];
			$cashFlowReportResult = $report['result'];
			$dates = $report['dates'];
			$weeks = $report['weeks'];
			$contractCode = $report['contractCode'];
			$reportCurrentName = $report['currencyName'];
				$reportInterval = $report['reportInterval'];
				$pastDueSupplierInvoices = $report['pastDueSupplierInvoices'];
				$pastDueInstallments = $report['pastDueInstallments'];
				$pastDueCustomerInvoices = $report['pastDueCustomerInvoices'];
			$cashFlowReport['total_cash_in_out_flow']=$this->formatFlowCashInOutChartData($cashFlowReportResult['customers'][__('Total Cash Inflow')]['total'] ?? [],$cashFlowReportResult['cash_expenses'][__('Total Cash Outflow')]['total'] ?? [],$dates);
			$cashFlowReport['accumulated_net_cash']= formatAccumulatedNetCash($cashFlowReportResult['cash_expenses'][__('Net Cash (+/-)')]['total'] ?? [] ,$dates );
		}
		
		$overdraftAccountTypes = AccountType::onlyOverdraftsAccounts()->get();
		$invoiceTypesModels = ['CustomerInvoice', 'SupplierInvoice'] ;
        $cashStartDate = $request->get('cash_start_date', now()->format('Y-m-d'));
        $cashEndDate = $request->get('cash_end_date', Carbon::make($cashStartDate)->addYear()->format('Y-m-d'));
		$withdrawalStartDate = now()->subMonths(WithdrawalsSettlementReportController::NUMBER_OF_INTERNAL_MONTHS)->format('Y-m-d');
		$withdrawalEndDate = $request->get('withdrawal_end_date',now()->format('Y-m-d'));
		
		$loanStartDate = $request->get('loan_start_date', now()->format('Y-m-d'));
		$loanEndDate = $request->get('loan_end_date', Carbon::make($loanStartDate)->addMonths(WithdrawalsSettlementReportController::NUMBER_OF_INTERNAL_MONTHS)->format('Y-m-d'));
		
		$agingDate = $request->get('aging_date',now()->format('Y-m-d'))  ;
        $selectedCurrencies = $request->get('currencies', $allCurrencies) ;

		
		$allFinancialInstitutionIds = $company->financialInstitutions->pluck('id')->toArray(); 
		foreach($selectedCurrencies as $currencyName)
		{
			foreach ($invoiceTypesModels as $modelType) {
				$moneyReceivedOrPaymentModelName  = $moneyReceivedOrPaymentModelNameMap[$modelType];
				$clientIdsForInvoices = ('\App\Models\\' . $modelType)::getAllUniquePartnerIds($company->id,$currencyName);
				$clientIdsForCheques = ('\App\Models\\' . $moneyReceivedOrPaymentModelName)::getAllUniquePartnerIdsForCheques($company->id,$currencyName);

				/**
				 * * Customers Invoices Aging & Supplier Invoices Aging
				 */
				$invoiceAgingService = new InvoiceAgingService($company->id, $agingDate,$currencyName);
				$chequeAgingService = new ChequeAgingService($company->id, $agingDate,$currencyName);
				$agingsForInvoices = $invoiceAgingService->__execute($clientIdsForInvoices, $modelType) ;
				$agingsForInvoices = $invoiceAgingService->formatForDashboard($agingsForInvoices,$modelType);
				/**
				 * * Customers Cheques Aging & Supplier Cheques Aging
				 */
				$agingsForChequesWithChart = $chequeAgingService->__execute($clientIdsForCheques, $modelType) ;
				$agingsForCheques = $agingsForChequesWithChart['result_for_table'];
				$agingsForChequesCharts = $agingsForChequesWithChart['result_for_chart'];
				
				$agingsForCheques = $chequeAgingService->formatForDashboard($agingsForCheques,$modelType);
	
				$dashboardResult['invoices_aging'][$modelType][$currencyName] = $agingsForInvoices ;
				$dashboardResult['cheques_aging_for_table'][$modelType][$currencyName] = $agingsForCheques ;
				$dashboardResult['cheques_aging_for_chart'][$modelType][$currencyName] = $agingsForChequesCharts ;
				
			}
		}
        return view('admin.dashboard.forecast', [
            'company' => $company,
			'dashboardResult'=>$dashboardResult,
			'invoiceTypesModels'=>$invoiceTypesModels,
			'cashStartDate'=>$cashStartDate,
			'cashEndDate'=>$cashEndDate,
			'withdrawalStartDate'=>$withdrawalStartDate,
			'withdrawalEndDate'=>$withdrawalEndDate,	
			'loanStartDate'=>$loanStartDate,
			'loanEndDate'=>$loanEndDate,
			'reportInterval'=>$reportInterval,
			'dates'=>$dates,
			'weeks'=>$weeks,
			'overdraftAccountTypes'=>$overdraftAccountTypes,
			'selectedCurrencies'=>$selectedCurrencies,
			'allFinancialInstitutionIds'=>$allFinancialInstitutionIds,
			'clientsWithContracts'=>$clientsWithContracts,
			'cashFlowReport'=>$cashFlowReport,
			'contractCode'=>$contractCode,
			'currencyName'=>$reportCurrentName,
			'currentCurrencyName'=>$reportCurrentName,
			'pastDueCustomerInvoices'=>$pastDueCustomerInvoices??[],
			'pastDueSupplierInvoices'=>$pastDueSupplierInvoices,
			'pastDueInstallments'=>$pastDueInstallments,
			'selectedReportInterval'=>$request->get('report_interval','weekly'),
			'selectedPartnerId'=>$request->get('partner_id'),
			'selectedContractId'=>$request->get('contract_id'),
			// 'financialInstitutionsThatHaveMediumTermLoans'=>$financialInstitutionsThatHaveMediumTermLoans
			
        ]);

        // return view('admin.dashboard.forecast', ['company' => $company]);
    }

    public function showInvoiceReport(Company $company, Request $request, int $partnerId, string $currency, $modelType)
    {

        $fullClassName = ('\App\Models\\' . $modelType) ;

        $clientIdColumnName = $fullClassName::CLIENT_ID_COLUMN_NAME ;
        $isCollectedOrPaid = $fullClassName::COLLETED_OR_PAID ;
        $moneyReceivedOrPaidText = (new $fullClassName())->getMoneyReceivedOrPaidText();
        $moneyReceivedOrPaidUrlName = (new $fullClassName())->getMoneyReceivedOrPaidUrlName();
		
		$deductions = Deduction::onlyForCompany($company->id)->get();

        $invoices = ('App\Models\\' . $modelType)::where('company_id', $company->id)
        ->where($clientIdColumnName, $partnerId)
        ->where('currency', $currency)
		->orderByRaw('invoice_date asc , invoice_due_date desc , net_balance desc')
        ->get();
        $customer = Partner::find($partnerId);
        if (!count($invoices)) {
            return  redirect()->back()->with('fail', __('No Data Found'));
        }
		$hasProjectNameColumn = $modelType == 'CustomerInvoice'?  CustomerInvoice::hasProjectNameColumn() : false;
		$totalCollectionOrPaidText  = $modelType == 'CustomerInvoice' ? __('Total Collections') : __('Total Payments');
        return view('admin.reports.invoice-report', [
            'invoices' => $invoices,
            'partnerName' => $customer->getName(),
            'partnerId' => $customer->getId(),
            'currency' => $currency,
            'isCollectedOrPaid' => 'is' . ucfirst($isCollectedOrPaid),
            'moneyReceivedOrPaidText' => $moneyReceivedOrPaidText,
            'moneyReceivedOrPaidUrlName' => $moneyReceivedOrPaidUrlName,
			'modelType'=>$modelType,
			'clientIdColumnName'=>$clientIdColumnName,
			'deductions'=>$deductions,
			'hasProjectNameColumn'=>$hasProjectNameColumn,
			'totalCollectionOrPaidText'=>$totalCollectionOrPaidText
        ]);
    }

	
	public function viewLGLCDashboard(Company $company, Request $request)
    {
			// start fully SecuredOverdraft
			$financialInstitutions = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get();
			$charts =  [];
			$tablesData = [];
			
			$lgTypes = LgTypes::getAll() ;
			$lgTypes = $request->ajax() && ! is_numeric($request->get('lgType')) ?  LgTypes::only((array) $request->get('lgType'))  : $lgTypes ; 
			
			$lcTypes = LcTypes::getAll() ;
			$lcTypes = $request->ajax() && ! is_numeric($request->get('lcType')) ?  LcTypes::only((array) $request->get('lcType'))  : $lcTypes ; 
			
			$typesForLgAndLc = [
				'lg'=>$lgTypes,
				'lc'=>$lcTypes
			];
		$financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get();
		
	
		$currentDate = now()->format('Y-m-d') ;
        $date = $request->get('date');
		$date = $date ? HDate::formatDateFromDatePicker($date) : $currentDate;
		// $year = explode('-',$date)[0];
		$date = Carbon::make($date)->format('Y-m-d');
		$allCurrencies = getCurrenciesForSuppliersAndCustomers($company->id) ;
	
		$details = [];
		
		$selectedFinancialInstitutionBankIds = [];
		
        $selectedCurrencies = $request->get('currencies', $allCurrencies) ;
		$source = $request->get('lgSource');
        $reports = [];
		$canShowDashboardPerCurrency = [];
		
		foreach([
			'lg'=>[
			'letter_of_facility_table_name'=>'letter_of_guarantee_facilities',
			'statement_table_name'=>'letter_of_guarantee_statements',
			'statement_table'=>'\App\Models\LetterOfGuaranteeStatement'
		],	
			'lc'=>
			[
			'letter_of_facility_table_name'=>'letter_of_credit_facilities',
			'statement_table_name'=>'letter_of_credit_statements',
			'statement_table'=>'\App\Models\LetterOfCreditStatement'
			] 
			
			] as $currentLgOrLcType => $lgOrLcOptionsArr){
			$statementTableFullClassName = $lgOrLcOptionsArr['statement_table'];
			$letterOfFacilityTableName = $lgOrLcOptionsArr['letter_of_facility_table_name'];
			$currentStatementTableName = $lgOrLcOptionsArr['statement_table_name'];

			$lgOrLcTypes = $typesForLgAndLc[$currentLgOrLcType];
			foreach ($selectedCurrencies as $currencyName) {
				
				
				$financialInstitutionBankIds = [
					// 'lg'=>array_keys($company->letterOfGuaranteeIssuances->where('status','!=','cancelled')->where('lg_currency',$currencyName)->load('financialInstitutionBank')->pluck('financialInstitutionBank.bank.name_en','financialInstitutionBank.id')->toArray()),
					'lg'=>array_keys($company->letterOfGuaranteeFacilities->where('currency',$currencyName)->pluck('financialInstitution.bank.name_en','financialInstitution.id')->toArray()),
					'lc'=>array_keys($company->letterOfCreditFacilities->where('currency',$currencyName)->pluck('financialInstitution.bank.name_en','financialInstitution.id')->toArray()),
					// 'lg'=>array_keys($company->letterOfGuaranteeIssuances->where('status','!=','cancelled')->where('lg_currency',$currencyName)->load('financialInstitutionBank')->pluck('financialInstitutionBank.bank.name_en','financialInstitutionBank.id')->toArray()),
					// 'lc'=>array_keys($company->letterOfCreditIssuances->where('status','!=','cancelled')->load('financialInstitutionBank')->pluck('financialInstitutionBank.bank.name_en','financialInstitutionBank.id')->toArray()),
				][$currentLgOrLcType];
					
				$selectedFinancialInstitutionBankIds = $request->ajax() && $request->get('financialInstitutionId') > 0 ? (array)$request->get('financialInstitutionId') : $financialInstitutionBankIds; 
				
				$currentLimit = DB::table($letterOfFacilityTableName)
				->where($letterOfFacilityTableName.'.company_id', $company->id)
				->where('currency', $currencyName)
				->where('contract_end_date', '>=', $date)
				->orderBy('contract_end_date', 'desc')
				->sum('limit'); 
				
				$reports[$currentLgOrLcType][$currencyName]['limit'] = $currentLimit ;
				
					$canShowDashboardPerCurrency[$currentLgOrLcType][$currencyName]  = $currentLimit > 0;
					// $canShowDashboardPerCurrency[$currentLgOrLcType][$currencyName]  = DB::table($currentStatementTableName)->where('company_id',$company->id)->where('currency',$currencyName)->exists();
				
				foreach($lgOrLcTypes as $currentLgType => $currentLgTitle){
					$statementTableFullClassName::getDashboardOutstandingPerTypeFormattedData($charts,$company,$currencyName , $date , $currentLgType,$source,$selectedFinancialInstitutionBankIds);
				}
				
				foreach ($selectedFinancialInstitutionBankIds as $financialInstitutionBankId) {
					
					$currentFinancialInstitution = FinancialInstitution::find($financialInstitutionBankId);
					$statementTableFullClassName::getDashboardOutstandingPerFinancialInstitutionFormattedData($charts,$company,$currencyName , $date ,$financialInstitutionBankId,$currentFinancialInstitution->getName(),$source,$lgOrLcTypes);
						
					$lastLetterOfGuaranteeOrCreditFacilities = DB::table($letterOfFacilityTableName)
					->where($letterOfFacilityTableName.'.company_id', $company->id)
					->where('currency', $currencyName)
					->where('contract_end_date', '>=', $date)
					->where($letterOfFacilityTableName.'.financial_institution_id', '=', $financialInstitutionBankId)
					->orderBy('contract_end_date', 'desc')
					->get();
					foreach($lastLetterOfGuaranteeOrCreditFacilities as $currentLastLetterOfGuaranteeOrCreditFacility){
						foreach($lgOrLcTypes as $currentLgType => $currentLgTitle){
							$statementTableFullClassName::getDashboardOutstandingTableFormattedData($tablesData,$company,$currencyName , $date ,$financialInstitutionBankId,$currentLgType,$currentFinancialInstitution->getName(),$currentLastLetterOfGuaranteeOrCreditFacility,$source);
						}
						
					}
						
						foreach($lastLetterOfGuaranteeOrCreditFacilities as $currentLastLetterOfGuaranteeOrCreditFacility){
							$debug = false ;
							if($currentLgOrLcType =='lc' && $currencyName=='USD'){
								$debug=true;
								}
							$details[$currencyName][$currentLgOrLcType][] = [
								'limit'=>$currentLimit =  $currentLastLetterOfGuaranteeOrCreditFacility->limit  ,
								'outstanding_balance'=> $currentOutstanding = $statementTableFullClassName::getTotalOutstandingBalanceForAllTypes($currentLastLetterOfGuaranteeOrCreditFacility->id,$company->id,$financialInstitutionBankId,$currencyName,$debug)  , 
								'room'=> $currentRoom = $currentLimit - $currentOutstanding ,
								'cash_cover'=> $currentCashCover = $statementTableFullClassName::getTotalCashCoverForAllTypes($currentLastLetterOfGuaranteeOrCreditFacility->id,$company->id,$financialInstitutionBankId,$currencyName)  , 
								'financial_institution_name'=>$currentFinancialInstitution->getName()
							] ;
							
							
							$total[$currentLgOrLcType][$currencyName]['limit'] = isset($total[$currentLgOrLcType][$currencyName]['limit']) ? $total[$currentLgOrLcType][$currencyName]['limit'] + $currentLimit  : $currentLimit ;
							$total[$currentLgOrLcType][$currencyName]['outstanding_balance'] = isset($total[$currentLgOrLcType][$currencyName]['outstanding_balance']) ? $total[$currentLgOrLcType][$currencyName]['outstanding_balance'] + $currentOutstanding  : $currentOutstanding ;
						
							$total[$currentLgOrLcType][$currencyName]['room'] = isset($total[$currentLgOrLcType][$currencyName]['room']) ? $total[$currentLgOrLcType][$currencyName]['room'] + $currentRoom  : $currentRoom ;
							$total[$currentLgOrLcType][$currencyName]['cash_cover'] = isset($total[$currentLgOrLcType][$currencyName]['cash_cover']) ? $total[$currentLgOrLcType][$currencyName]['cash_cover'] + $currentCashCover  : $currentCashCover ;
				
						}
					
	
				}
				// $reports[$currentLgOrLcType][$currencyName]['limit'] = $total[$currentLgOrLcType][$currencyName]['limit'] ?? 0 ;
				$reports[$currentLgOrLcType][$currencyName]['outstanding_balance'] = $total[$currentLgOrLcType][$currencyName]['outstanding_balance'] ?? 0 ;
				$reports[$currentLgOrLcType][$currencyName]['room'] = $total[$currentLgOrLcType][$currencyName]['room'] ?? 0 ;
				$reports[$currentLgOrLcType][$currencyName]['cash_cover'] = $total[$currentLgOrLcType][$currencyName]['cash_cover'] ?? 0 ;
			}
			
			
		}
        
		if($request->ajax()){
			
			return response()->json([
				'tablesData'=>$tablesData ,
				'charts'=>$charts
			]);
		}
	
        return view('admin.reports.lglc-report', [
            'company' => $company,
            'financialInstitutionBanks' => $financialInstitutionBanks,
            'reports' => $reports,
            'selectedCurrencies' => $selectedCurrencies,
			'allCurrencies'=>$allCurrencies,
            'selectedFinancialInstitutionsIds' => $selectedFinancialInstitutionBankIds,
			'details'=>$details,
			'charts'=>$charts,
			'lgTypes'=>LgTypes::getAll(),
			'lcTypes'=>LcTypes::getAll(),
			'lgSources'=>LetterOfGuaranteeIssuance::lgSources(),
			'lcSources'=>LetterOfCreditIssuance::lcSources(),
			'tablesData'=>$tablesData,
			'financialInstitutions'=>$financialInstitutions,
			'canShowDashboardPerCurrency'=>$canShowDashboardPerCurrency
        ]);
    }


    public function showInvoiceStatementReport(Company $company, Request $request, int $partnerId, string $currency, string $modelType , ?string $startDate = null , ?string $endDate = null , bool $returnResult = false)
    {
		$showAllPartner = $request->boolean('all_partners');
		$partnerId = $request->has('partner_id') ? $request->get('partner_id') : $partnerId;
        $fullClassName = ('\App\Models\\' . $modelType) ;
		$isCustomer = $modelType == 'CustomerInvoice' ? 1 : 0;
		$isSupplier = $modelType == 'CustomerInvoice' ? 0 : 1;
		$partners = Partner::when($partnerId && !$showAllPartner ,function(Builder $builder) use ($partnerId,$isSupplier,$isCustomer){
			$builder->whereIn('id',(array) $partnerId )->where('is_customer',$isCustomer)->where('is_supplier',$isSupplier);
		})->whereHas($modelType,function(Builder $builder) use($currency){
			if($currency != 'main_currency'){
				$builder->where('currency',$currency);
			}
		})
		->where('company_id',$company->id)
		->pluck('name','id')->toArray();
		
        $clientIdColumnName = $fullClassName::CLIENT_ID_COLUMN_NAME ;
        $customerStatementText = (new $fullClassName())->getCustomerOrSupplierStatementText();
        $startDate = $startDate ?: $request->get('start_date', now()->subMonths(12)->format('Y-m-d'));
        $endDate = $endDate?: $request->get('end_date', now()->format('Y-m-d'));
        $invoices = ('\App\Models\\' . $modelType)::getInvoicesForInvoiceStartAndEndDate( $clientIdColumnName, $partnerId, $company ,  $currency ,  $startDate ,  $endDate);

        $partner = Partner::find($partnerId);
		if(!$partner){
			return view('admin.reports.customer-statement-report', [
				'invoicesWithItsReceivedMoney' => [],
				'partnerName' => null,
				'partnerId' => $partnerId,
				'currency' => $currency,
				'startDate' => $startDate,
				'endDate' => $endDate,
				'customerStatementText' => $customerStatementText,
				'partners'=>$partners,
				'showAllPartner'=>$showAllPartner
			]);
		}
        $partnerName = $partner->getName() ;
        $invoicesWithItsReceivedMoney = $this->formatForStatementReport($invoices, $partnerId, $startDate, $endDate, $currency,$modelType);
		if($returnResult){
			if(count($invoicesWithItsReceivedMoney) < 1){
				return [];
			}
			return $invoicesWithItsReceivedMoney ;
		}
        if (count($invoicesWithItsReceivedMoney) < 1) {
            return  redirect()->back()->with('fail', __('No Data Found'));
        }
		
        return view('admin.reports.customer-statement-report', [
            'invoicesWithItsReceivedMoney' => $invoicesWithItsReceivedMoney,
            'partnerName' => $partnerName,
            'partnerId' => $partnerId,
            'currency' => $currency,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'customerStatementText' => $customerStatementText,
			'partners'=>$partners,
			'showAllPartner'=>$showAllPartner
        ]);
    }
	protected function getKeysFromStdClass(?\Illuminate\Support\Collection $stdClass , array $keys,array $additionalData = []):array 
{
	$result = [];
	foreach($stdClass as $index => $stdObject)
	{
		$stdArray = (array) $stdObject;
		$result[] = array_merge( Arr::only($stdArray , $keys) , $additionalData );
	}
	return $result ;
	
}
	
}
