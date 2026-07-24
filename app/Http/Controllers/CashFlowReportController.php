<?php

namespace App\Http\Controllers;

use App\Exports\CashFlowMatrixExport;
use App\Helpers\HArr;
use App\Helpers\HDate;
use App\Models\CashExpense;
use App\Models\CashflowReport;
use App\Models\Cheque;
use App\Models\Company;
use App\Models\Contract;
use App\Models\ContractLoanSchedule;
use App\Models\CustomerInvoice;
use App\Models\ForeignExchangeRate;
use App\Models\LetterOfCreditIssuance;
use App\Models\LetterOfGuaranteeIssuance;
use App\Models\LoanSchedule;
use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use App\Models\PayableCheque;
use App\Models\PoAllocation;
use App\Models\SettlementAllocation;
use App\Models\SupplierInvoice;
use App\Models\TimeOfDeposit;
use App\Services\Reports\CashFlowCompanyPeriodBatchLoader;
use App\Services\Reports\CashFlowContractDetailPeriodBatchLoader;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * CashFlowReportController
 * ------------------------------------------------------------------
 * The Company Cash Flow Report AND the Contract Cash Flow Report share
 * this exact same result() engine — ContractCashFlowReportController::result()
 * is a thin wrapper that just pre-sets contract_id/title and delegates
 * straight into this class. Same for the Consolidated Cash Flow Report,
 * which uses a separate service but the same underlying data primitives.
 *
 * ⚠️ CALCULATION LOGIC IS 100% UNTOUCHED. Every method here that builds
 * the actual report numbers (result(), finalizeContractCashFlowTotals(),
 * the batch loaders, every model static method it calls) is left exactly
 * as-is — this migration only changes HOW the final payload is delivered
 * to the browser (Inertia::render() instead of view()), never what's IN
 * that payload or how it's computed. Given this report drives real
 * business decisions and has genuinely intricate calculation logic
 * (weekly/monthly/daily bucketing, multi-currency, saved-report JSON
 * snapshots), that separation was deliberate.
 *
 * ── Frontend migration status ───────────────────────────────────
 *   index()  → Inertia::render, Pages/CashFlowReport/Index.vue
 *              (filter form + saved-reports list)
 *   result() → Inertia::render, Pages/CashFlowReport/Result.vue
 *              (both the "replay a saved report" early-return branch
 *              and the normal freshly-computed branch — same payload
 *              shape either way, since a saved report is just this
 *              same $reportData JSON-cached and replayed later)
 *   adjustCustomerDueInvoices() / adjustLoanPastDueInstallments() /
 *   saveProjection() / destroy() → UNCHANGED. These already respond
 *              with plain JSON ({status, reloadCurrentPage:true}) or a
 *              redirect for their own AJAX/form-post callers — not
 *              full-page Inertia visits, so no conversion needed. The
 *              Vue page calls them with fetch() and reloads via
 *              router.reload() on success, same effect as the
 *              original's window.location.reload().
 *   exportExcel() → ✅ New (project-owner requested, "same as the
 *              Statements reports"). Colored via the new
 *              App\Exports\CashFlowMatrixExport — deliberately fed
 *              from the client-computed table (see that class's own
 *              docblock for why), never recomputed here. Shared by
 *              both Company and Contract Cash Flow, since both use
 *              this same Result.vue page and the same export route.
 */
class CashFlowReportController
{
    use GeneralFunctions;
    public function index(Company $company)
	{
		$cashflowReports = $company->cashflowReports->where('is_contract',0);
        return Inertia::render('CashFlowReport/Index', [
			'company' => ['id' => $company->id, 'name' => $company->getName()],
			'currencies' => collect(getBanksCurrencies())->map(fn ($label, $code) => ['code' => $code, 'label' => touppercase($label)])->values(),
			'mainFunctionalCurrency' => $company->getMainFunctionalCurrency(),
			'savedReports' => $cashflowReports->values()->map(fn ($r) => [
				'id' => $r->id,
				'name' => $r->getName(),
				'interval' => $r->getIntervalName(),
				'start_date_formatted' => $r->getStartDateFormatted(),
				'end_date_formatted' => $r->getEndDateFormatted(),
				'view_url' => route('result.cashflow.report', ['company' => $company->id, 'returnResultAsArray' => 'view', 'cashflowReport' => $r->id]),
				'delete_url' => route('delete.cashflow.report', ['company' => $company->id, 'cashflowReport' => $r->id]),
			]),
			'urls' => [
				'result' => route('result.cashflow.report', ['company' => $company->id]),
			],
		]);
    }
	public function getRedirectRoute(bool $isContract):string 
	{
		return $isContract ?'result.contract.cashflow.report' :'result.cashflow.report';
	}

	/**
	 * محور التقويم المشترك لتقرير التدفق المدمج (أسابيع/أشهر/أيام + FX) — يُحسب مرة واحدة لكل العقود.
	 *
	 * @return array<string, mixed>|RedirectResponse
	 */
	public function buildSharedTimelineContext(Company $company, Request $request): array|RedirectResponse
	{
		$defaultStartDate = $request->get('cash_start_date', now()->format('Y-m-d'));
		$defaultEndDate = $request->get('cash_end_date', now()->addMonth()->format('Y-m-d'));
		$formStartDate = Carbon::make($request->get('start_date', $defaultStartDate))->format('Y-m-d');
		$formEndDate = Carbon::make($request->get('end_date', $defaultEndDate))->format('Y-m-d');
		if (! now()->between($formStartDate, $formEndDate)) {
			return redirect()->back()->with('fail', __('Kindly the date of Today must be included within the report duration'));
		}

		$reportInterval = $request->get('report_interval');
		if (empty($reportInterval) || ! in_array($reportInterval, ['daily', 'weekly', 'monthly'], true)) {
			$reportInterval = 'monthly';
		}

		$startDate = Carbon::make($request->get('start_date', $defaultStartDate))->format('Y-m-d');
		$endDate = Carbon::make($request->get('end_date', $defaultEndDate))->format('Y-m-d');
		$year = explode('-', $startDate)[0];

		$datesWithWeeks = [];
		if ($reportInterval === 'weekly') {
			$datesWithWeeks = HDate::getWeekNumberBetweenDates($year, Carbon::make($endDate));
		} elseif ($reportInterval === 'monthly') {
			$datesWithWeeks = HDate::getMonthNumberBetweenDates($year, Carbon::make($endDate));
		} elseif ($reportInterval === 'daily') {
			$datesWithWeeks = HDate::getDayNumberBetweenDates($year, Carbon::make($endDate));
		}

		$weeks = $this->mergeYearWithWeek($datesWithWeeks, Carbon::make($startDate));
		$datesWithWeekNumber = $this->getDateWithWeakNumber($datesWithWeeks, Carbon::make($startDate));
		$foreignExchangeRates = ForeignExchangeRate::where('company_id', $company->id)->get();
		$firstIndex = array_key_first($weeks);
		$lastIndex = array_key_last($weeks);
		$dates = [];

		foreach ($weeks as $currentWeekYear => $week) {
			$currentYear = explode('-', $currentWeekYear)[1];
			if ($currentWeekYear === $firstIndex) {
				$periodStart = $startDate;
				$periodEnd = HDate::getMinDateOfWeek($datesWithWeeks, $week, $currentYear)['end_date'];
			} elseif ($currentWeekYear === $lastIndex) {
				$periodStart = HDate::getMinDateOfWeek($datesWithWeeks, $week, $currentYear)['start_date'];
				$periodEnd = $request->get('end_date', $defaultEndDate);
			} else {
				$rangedWeeks = HDate::getMinDateOfWeek($datesWithWeeks, $week, $currentYear);
				$periodStart = $rangedWeeks['start_date'];
				$periodEnd = $rangedWeeks['end_date'];
			}
			$dates[$currentWeekYear] = [
				'start_date' => $periodStart,
				'end_date' => $periodEnd,
			];
		}

		return [
			'mainFunctionalCurrency' => $company->getMainFunctionalCurrency(),
			'reportInterval' => $reportInterval,
			'formStartDate' => $formStartDate,
			'formEndDate' => $formEndDate,
			'defaultStartDate' => $defaultStartDate,
			'defaultEndDate' => $defaultEndDate,
			'startDate' => $startDate,
			'endDate' => $endDate,
			'weeks' => $weeks,
			'dates' => $dates,
			'datesWithWeekNumber' => $datesWithWeekNumber,
			'datesWithWeeks' => $datesWithWeeks,
			'foreignExchangeRates' => $foreignExchangeRates,
			'firstIndex' => $firstIndex,
			'lastIndex' => $lastIndex,
			'months' => generateDatesBetweenTwoDates(Carbon::make($formStartDate), Carbon::make($formEndDate)),
			'days' => generateDatesBetweenTwoDates(Carbon::make($formStartDate), Carbon::make($formEndDate), 'addDay'),
		];
	}
	
	public function result(
		Company $company,
		Request $request,
		bool $returnResultAsArray = false,
		?CashFlowReport $cashflowReport = null,
		$defaultCashFlowId = 0,
		?array $sharedTimelineContext = null,
		?Collection $preloadedPoAllocations = null,
		?Contract $preloadedContract = null,
	) {
		$saveReport = $request->has('save_report');
		$resetReport = $request->has('reset_report') && $request->get('reset_report');
		$contractId = $request->get('contract_id')	 ;
		$contract = $preloadedContract ?? Contract::find($contractId);
		
		/**
		 * @var Contract|null $contract 
		 */
		$contractCode = $contract ? $contract->getCode() : null ;
		$contractName = $contract ? $contract->getName() : null ;
		$customer = $contract ? $contract->client : null ;
		$customerId = $customer ? $customer->getId() : null ;
		$customerName = $customer ? $customer->getName() : null ;
		$isContract = (bool)$customerId;
		$redirectRouteName = $this->getRedirectRoute($isContract);
		$cashflowReportId = $cashflowReport && $cashflowReport->id ? $cashflowReport->id : $defaultCashFlowId;
		if( $resetReport && !session()->has('without_resetting') ){
			$company->resetCashFlowReport();			
			$queryParams = $request->query();
			$queryParams['reset_report'] = 0;
			$queryParams['company'] = $company->id;
			if($cashflowReportId){
				$queryParams['cashflowReport'] = $cashflowReportId;
				if($contractId){
					$queryParams['contract_id'] = $contractId;
				}
			}
			return redirect()->route($redirectRouteName,  $queryParams);
		}
		if($cashflowReport && $cashflowReport->report_data){
			$reportData = json_decode($cashflowReport->report_data,true);
			$currencyName = Arr::first($reportData['allCurrencies']);
			return Inertia::render('CashFlowReport/Result', array_merge($reportData, $this->resultViewExtras($company, $contractCode, $currencyName, $cashflowReport, $reportData['letterOfGuaranteeModelData'] ?? [])));
		}
			$mainFunctionalCurrency= $company->getMainFunctionalCurrency();
		$isContract = (bool)$contract ;
		$currencyName = $isContract ? $contract->getCurrency(): $request->get('currency',$mainFunctionalCurrency);
		$reportInterval = $request->get('report_interval');
		if (empty($reportInterval) || !in_array($reportInterval, ['daily', 'weekly', 'monthly'], true)) {
			$reportInterval = 'monthly';
			// return redirect()->back()->with('fail', __('Please select Report Interval.'))->withInput($request->only(['report_interval', 'start_date', 'end_date', 'contract_id', 'partner_id']));
		}
		$customerContractId = $contractId ;
		
		if ($preloadedPoAllocations !== null) {
			$poAllocations = $preloadedPoAllocations;
		} else {
			$poAllocations = PoAllocation::where('po_allocations.contract_id',$customerContractId)	
			->join('purchase_orders','purchase_orders.id','=','po_allocations.purchase_order_id')
			->join('contracts','contracts.id','=','purchase_orders.contract_id')
			->get();
		}

		if ($sharedTimelineContext !== null) {
			$mainFunctionalCurrency = $sharedTimelineContext['mainFunctionalCurrency'];
			$reportInterval = $sharedTimelineContext['reportInterval'];
			$formStartDate = $sharedTimelineContext['formStartDate'];
			$formEndDate = $sharedTimelineContext['formEndDate'];
			$defaultStartDate = $sharedTimelineContext['defaultStartDate'];
			$defaultEndDate = $sharedTimelineContext['defaultEndDate'];
			$startDate = $sharedTimelineContext['startDate'];
			$endDate = $sharedTimelineContext['endDate'];
			$weeks = $sharedTimelineContext['weeks'];
			$datesWithWeekNumber = $sharedTimelineContext['datesWithWeekNumber'];
			$datesWithWeeks = $sharedTimelineContext['datesWithWeeks'];
			$foreignExchangeRates = $sharedTimelineContext['foreignExchangeRates'];
			$firstIndex = $sharedTimelineContext['firstIndex'];
			$lastIndex = $sharedTimelineContext['lastIndex'];
			$dates = $sharedTimelineContext['dates'];
			$months = $sharedTimelineContext['months'];
			$days = $sharedTimelineContext['days'];
		} else {
			$defaultStartDate = $request->get('cash_start_date',now()->format('Y-m-d'));
			$defaultEndDate = $request->get('cash_end_date',now()->addMonth()->format('Y-m-d'));
			$formStartDate =Carbon::make($request->get('start_date',$defaultStartDate))->format('Y-m-d'); 
			$formEndDate =Carbon::make($request->get('end_date',$defaultEndDate))->format('Y-m-d');
			if(!now()->between($formStartDate,$formEndDate)){
				return redirect()->back()->with('fail',__('Kindly the date of Today must be included within the report duration'));
			}
		}

		$title = $request->has('title') ? $request->get('title') : __('Company Cash Flow') . ' [ ' . $reportInterval . ' ]' ;
		
		// $reportInterval = 'daily';
		$result = [];
		$letterOfGuaranteeModelData = [];
		// $cashExpenseCategoryNamesArr = [];
		$pastDueSupplierInvoicesForContracts = collect([]);
		$result['customers']=[
			'Cash & Banks Balance'=>[],
			'Checks Collected'=>[],
			'Incoming Transfers'=>[],
			'Bank Deposits'=> [],
			'Cash Collections'=> [],
			'Time Of Deposits'=> [],
			'Cheques Under Collection'=>[],
			'Cheques In Safe'=>[],
			'Cancelled LGs Cash Cover'=>[],
			'Customers Invoices'=>[],
			'Customers Past Due Invoices'=>[],
			'Forecasted Project Collection'=>[],
			'Projected Other Cash In Items'=>[],
			__('Total Cash Inflow')=>[]
		];
		if($contractId){
			unset($result['customers']['Cash & Banks Balance']);
			unset($result['customers']['Time Of Deposits']);
		}
		$result['suppliers'] = [];
		$result['cash_expenses'] = [];
		$noRowHeaders =  $reportInterval == 'weekly' ? 3 : 1 ;
		if ($sharedTimelineContext === null) {
			$months = generateDatesBetweenTwoDates(Carbon::make($formStartDate),Carbon::make($formEndDate)); 
			$days = generateDatesBetweenTwoDates(Carbon::make($formStartDate),Carbon::make($formEndDate),'addDay'); 
			$startDate = Carbon::make($request->get('start_date',$defaultStartDate))->format('Y-m-d');
			$year = explode('-',$startDate)[0];
			$endDate  = Carbon::make($request->get('end_date',$defaultEndDate))->format('Y-m-d');
			$datesWithWeeks = [];
			if($reportInterval == 'weekly'){
				$datesWithWeeks = 	HDate::getWeekNumberBetweenDates($year , Carbon::make($endDate)) ;
			}
			elseif($reportInterval == 'monthly'){
				$datesWithWeeks = 	HDate::getMonthNumberBetweenDates($year , Carbon::make($endDate)) ;
			}
			elseif($reportInterval == 'daily'){
				$datesWithWeeks = 	HDate::getDayNumberBetweenDates($year , Carbon::make($endDate)) ;
			}
			$weeks  = $this->mergeYearWithWeek($datesWithWeeks ,Carbon::make($startDate) );
			$datesWithWeekNumber  = $this->getDateWithWeakNumber($datesWithWeeks ,Carbon::make($startDate) );
			$foreignExchangeRates = ForeignExchangeRate::where('company_id',$company->id)->get();
			$firstIndex = array_key_first($weeks);
			$lastIndex = array_key_last($weeks);
			$dates = [];
		}
		$currency = $request->get('currency');

		if (is_null($currency)) {
			$currency = $contract ? $contract->getCurrency() : $company->getMainFunctionalCurrency();
		}
		$redirectRouteName = $this->getRedirectRoute($isContract);
		$rangedWeeks = [];
		CashExpense::getProjectionOtherCashOut($result ,$company,$cashflowReportId,$isContract) ;
		  if(!$contractId){
		      CustomerInvoice::getCashAndBankBalanceAtDate($result ,$foreignExchangeRates,$mainFunctionalCurrency,$startDate ,array_keys($weeks)[0],$company->id) ;
			  LoanSchedule::getLoanInstallmentsAtDates($result,$foreignExchangeRates,$mainFunctionalCurrency,$company->id,$datesWithWeekNumber,$endDate);
			  ContractLoanSchedule::getContractLoanInstallmentsAtDates($result,$foreignExchangeRates,$mainFunctionalCurrency,$company->id,$datesWithWeekNumber,$endDate);
		}
		
		  CustomerInvoice::getProjectionOtherCashIn($result ,$company,$cashflowReportId,$isContract) ;
		  /**
		   * ! start postponed
		   */
		  CustomerInvoice::getForecastedProjectCollection($result ,$startDate , $endDate,$currency,$company->id,$datesWithWeekNumber,$contractId) ;
		   SupplierInvoice::getForecastedProjectCollection($result ,$startDate , $endDate,$currency,$company->id,$datesWithWeekNumber,$contractId) ;
		
		 /**
		   * ! end postponed
		   */
		  
		  CustomerInvoice::getCustomerInvoicesUnderCollectionAtDatesForContracts($result,$company->id,$contractCode,$datesWithWeekNumber,$endDate);
		
		  $isContract ? SupplierInvoice::getSupplierInvoicesForPoUnderCollectionAtDates($result,$company->id,$datesWithWeekNumber,$startDate,$endDate,$poAllocations,$pastDueSupplierInvoicesForContracts) : SupplierInvoice::getSupplierInvoicesUnderCollectionAtDates($result,$company->id,$datesWithWeekNumber,$startDate,$endDate);

		if ($sharedTimelineContext === null && $dates === []) {
			$dates = $this->buildPeriodDatesMap(
				$weeks,
				$datesWithWeeks,
				$startDate,
				$endDate,
				$request->get('end_date', $defaultEndDate),
			);
		}

		$periodStart = $startDate;
		$periodEnd = $endDate;

		ForeignExchangeRate::beginRequestMemo();

		try {
			if (! $contractId) {
				CashFlowCompanyPeriodBatchLoader::apply(
					$result,
					$foreignExchangeRates,
					$mainFunctionalCurrency,
					$company->id,
					$periodStart,
					$periodEnd,
					$dates,
					$letterOfGuaranteeModelData,
				);
			} else {
				CashFlowContractDetailPeriodBatchLoader::apply(
					$result,
					$letterOfGuaranteeModelData,
					$foreignExchangeRates,
					$mainFunctionalCurrency,
					$company->id,
					(string) $contractCode,
					(int) $contractId,
					(int) $customerId,
					$periodStart,
					$periodEnd,
					$dates,
				);
			}
		} finally {
			ForeignExchangeRate::endRequestMemo();
		}
		$pastDueCustomerInvoices = $this->getPastDueCustomerInvoices('CustomerInvoice',$currency,$company->id,$contractCode);
		$pastDueSupplierInvoices = $isContract ? $pastDueSupplierInvoicesForContracts->toArray() : $this->getPastDueCustomerInvoices('SupplierInvoice', $currency, $company->id, $contractCode);
		$pastDueInstallments = $this->getPastDueLoanSchedules($currency, $company->id);
		$supplierContractCodes = $pastDueSupplierInvoicesForContracts->pluck('contract_code')->toArray();
		$currentContractCode = $isContract ? $supplierContractCodes : [$contractCode];
		$supplierDueInvoices = json_decode(json_encode(DB::table('weekly_cashflow_custom_due_invoices')->where('weekly_cashflow_custom_due_invoices.company_id', $company->id)
			->where('invoice_type', 'SupplierInvoice')
			->where('cashflow_report_id', $cashflowReportId)
			->where('is_contract', $isContract)
			->when($contractCode, function ($query) use ($currentContractCode) {
				$query->join('supplier_invoices', 'supplier_invoices.id', '=', 'weekly_cashflow_custom_due_invoices.invoice_id')
					->where('supplier_invoices.contract_code', $currentContractCode);
			})
			->groupBy('week_start_date')->selectRaw('week_start_date,sum(amount) as amount')->get()), true);
		$customerDueInvoices = json_decode(json_encode(DB::table('weekly_cashflow_custom_due_invoices')->where('weekly_cashflow_custom_due_invoices.company_id', $company->id)
			->where('invoice_type', 'CustomerInvoice')
			->where('cashflow_report_id', $cashflowReportId)
			->where('is_contract', $isContract)
			->when($contractCode, function ($query) use ($contractCode) {
				$query->join('customer_invoices', 'customer_invoices.id', '=', 'weekly_cashflow_custom_due_invoices.invoice_id')
					->where('customer_invoices.contract_code', $contractCode);
			})
			->groupBy('week_start_date')->selectRaw('week_start_date,sum(amount) as amount')->get()), true);
		$pastDueLoanInstallments = json_decode(json_encode(DB::table('weekly_cashflow_custom_past_due_schedules')
			->where('company_id', $company->id)
			->groupBy('week_start_date')->selectRaw('week_start_date,sum(amount) as amount')->get()), true);

		$this->finalizeContractCashFlowTotals(
			$result,
			$company,
			$currency,
			$contractCode,
			$datesWithWeekNumber,
			$weeks,
			$cashflowReportId,
			$isContract,
			$contractId ? (int) $contractId : null,
			$formStartDate,
			$formEndDate,
			$pastDueSupplierInvoicesForContracts,
			$customerDueInvoices,
			$supplierDueInvoices,
			$pastDueLoanInstallments,
		);

		$orderByKeys = [
			'Cash Payments',
			'Outgoing Transfers',
			'Paid Payable Cheques',
			'Under Payment Payable Cheques',
			'Suppliers Invoices',
			'Suppliers Past Due Invoices',
			'Loan Past Due Installments',
			'Forecasted Suppliers Contract Payments',
		];

		$result['suppliers'] = collect($result['suppliers'])->sortBy(function ($value, $key) use ($orderByKeys) {
			return array_search($key, $orderByKeys);
		})->toArray();

		if ($returnResultAsArray) {
			return [
				'result' => $result,
				'dates' => $dates,
				'contractCode' => $contractCode,
				'pastDueCustomerInvoices' => [$currency => $pastDueCustomerInvoices],
				'currencyName' => $currencyName,
				'reportInterval' => $reportInterval,
				'weeks' => $weeks,
				'pastDueSupplierInvoices' => $pastDueSupplierInvoices,
				'pastDueInstallments' => $pastDueInstallments,
			];
		}
		$allCurrencies = [$currency];
		$finalResult[$currency] = $result;
		$pastDueCustomerInvoicesPerCurrency[$currency] = $pastDueCustomerInvoices;
		$customerDueInvoicesPerCurrency[$currency] = $customerDueInvoices;
		$reportData = [
			'weeks' => $weeks,
			'allCurrencies' => $allCurrencies,
			'finalResult' => $finalResult,
			'dates' => $dates,
			'pastDueCustomerInvoices' => $pastDueCustomerInvoicesPerCurrency,
			'customerDueInvoices' => $customerDueInvoicesPerCurrency,
			'pastDueSupplierInvoices' => $pastDueSupplierInvoices,
			'supplierDueInvoices' => $supplierDueInvoices,
			'pastDueInstallments' => $pastDueInstallments,
			'pastDueLoanInstallments' => $pastDueLoanInstallments,
			'letterOfGuaranteeModelData' => $letterOfGuaranteeModelData,
			'months' => $months,
			'days' => $days,
			'reportInterval' => $reportInterval,
			'report_interval' => $reportInterval,
			'noRowHeaders' => $noRowHeaders,
			'title' => $title,
		];

		if ($saveReport) {
			$cashFlowReport = CashflowReport::create([
				'is_contract' => $isContract,
				'report_name' => $request->get('report_name'),
				'report_data' => json_encode($reportData),
				'start_date' => $formStartDate,
				'end_date' => $formEndDate,
				'report_interval' => $reportInterval,
				'company_id' => $company->id,
			]);
			$routeParams = ['company' => $company->id, 'report_interval' => $reportInterval, 'returnResultAsArray' => 'view', 'cashflowReport' => $cashFlowReport->id, 'start_date' => $formStartDate, 'end_date' => $formEndDate];
			if ($isContract) {
				$routeParams['contract_id'] = $contractId;
			}

			return redirect()->route($redirectRouteName, $routeParams);
		}

		return Inertia::render('CashFlowReport/Result', array_merge($reportData, $this->resultViewExtras($company, $contractCode, $currencyName, null, $letterOfGuaranteeModelData)));
	}

	/**
	 * Colored Excel export (project-owner requested — "same as the
	 * Statements reports") for Company AND Contract Cash Flow, which
	 * share this exact same Result.vue page. Styled via the shared
	 * App\Exports\CashFlowMatrixExport.
	 *
	 * ⚠️ Deliberately does NOT recompute the report. The `payload`
	 * posted here is built client-side by Result.vue from its own
	 * already-rendered `tablesByCurrency` — the same buildCurrencyTable()
	 * output the person is looking at — so the export is guaranteed to
	 * match the screen exactly, and this method never re-implements
	 * that row-mutation calculation logic in PHP. Received via a plain
	 * POST form submit (not axios/fetch), matching the existing
	 * "native form submission avoids Inertia's ajax() branch" pattern
	 * already used elsewhere in this codebase (see roadmap bug #38) —
	 * appropriate here too, since triggering a file download from a
	 * fetch/axios response requires extra blob handling this avoids.
	 */
	public function exportExcel(Company $company, Request $request)
	{
		$payload = json_decode((string) $request->input('payload', '{}'), true);
		if (! is_array($payload)) {
			$payload = [];
		}

		$title = (string) ($payload['title'] ?? __('Cash Flow Report'));
		$currency = (string) ($payload['currency'] ?? '');
		$periodLabels = array_values($payload['periodLabels'] ?? []);
		$rows = $payload['rows'] ?? [];

		$headings = array_merge([__('Item')], $periodLabels, [__('Total')]);

		$exportRows = collect($rows)->map(function ($row) {
			return [
				'label' => (string) ($row['label'] ?? ''),
				'type' => (string) ($row['type'] ?? 'row'),
				'values' => array_map(static fn ($v) => (float) $v, $row['values'] ?? []),
				'total' => (float) ($row['total'] ?? 0),
			];
		})->values()->all();

		$fileNameParts = ['Cash-Flow-Report', $currency];
		$fileName = preg_replace('/[^A-Za-z0-9\-]+/', '-', implode('-', array_filter($fileNameParts))).'.xlsx';

		return (new CashFlowMatrixExport($headings, $exportRows, $title))->download($fileName);
	}

	/**
	 * Shared extra props for both result() view branches (cached-replay
	 * and freshly-computed) — company/urls/cashflowReport, none of which
	 * affect the calculation itself.
	 */
	protected function resultViewExtras(Company $company, ?string $contractCode, ?string $currencyName, ?CashflowReport $cashflowReport = null, array $letterOfGuaranteeModelData = []): array
	{
		$isContract = $contractCode ? 1 : 0;
		$projectionModel = $cashflowReport ?: $company;
		$cashProjections = $projectionModel->cashProjects()->where('is_contract', $isContract)->get();

		return [
			'company' => ['id' => $company->id, 'name' => $company->getName()],
			'currencyName' => $currencyName,
			'contractCode' => $contractCode,
			'letterOfGuaranteeModelData' => $letterOfGuaranteeModelData,
			'cashflowReport' => $cashflowReport ? [
				'id' => $cashflowReport->id,
				'name' => $cashflowReport->getName(),
				'is_contract' => (bool) $cashflowReport->is_contract,
			] : null,
			'cashProjections' => [
				'in' => $cashProjections->where('type', 'in')->values()->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'amounts' => $p->amounts ?: []]),
				'out' => $cashProjections->where('type', 'out')->values()->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'amounts' => $p->amounts ?: []]),
			],
			'urls' => [
				'index' => route('view.cashflow.report', ['company' => $company->id]),
				'result' => route('result.cashflow.report', ['company' => $company->id]),
				'adjustCustomerDueInvoices' => route('adjust.customer.dues.invoices', ['company' => $company->id]),
				'adjustLoanPastDueInstallments' => route('adjust.loan.past.dues.installments', ['company' => $company->id]),
				'saveProjection' => route('save.projection', ['company' => $company->id]),
				'exportExcel' => route('export.cashflow.report', ['company' => $company->id]),
			],
		];
	}

	
	public function finalizeContractCashFlowTotals(
		array &$result,
		Company $company,
		string $currency,
		?string $contractCode,
		array $datesWithWeekNumber,
		array $weeks,
		int $cashflowReportId = 0,
		bool $isContract = true,
		?int $contractId = null,
		string $formStartDate = '',
		string $formEndDate = '',
		$pastDueSupplierInvoicesForContracts = [],
		array $customerDueInvoices = [],
		array $supplierDueInvoices = [],
		array $pastDueLoanInstallments = [],
	): void {
		if ($isContract && $contractId) {
			SupplierInvoice::getForecastedProjectPayment($result, $formStartDate, $formEndDate, $currency, $company->id, $datesWithWeekNumber, $contractId);
		}

		$totalCashInFlowArray = $result['customers'][__('Total Cash Inflow')]['total'] ?? [];
		$totalCashInFlowArray = $this->mergeTotal($totalCashInFlowArray, $customerDueInvoices, $datesWithWeekNumber);
		$totalCashOutFlowArray = $this->sumAllTotalKeys($result['suppliers'] ?? [], $result['cash_expenses'] ?? [], $datesWithWeekNumber);

		$totalCashOutFlowArray = $this->mergeTotal($totalCashOutFlowArray, $supplierDueInvoices, $datesWithWeekNumber, true);

		$totalCashOutFlowArray = $this->mergeTotal($totalCashOutFlowArray, $pastDueLoanInstallments, $datesWithWeekNumber);
		$result['customers'][__('Total Cash Inflow')]['total'] = $totalCashInFlowArray;
		$outProjection = $result['cash_expenses'][__('Projected Other Cash Out Items')] ?? [];
		unset($result['cash_expenses'][__('Projected Other Cash Out Items')]);
		$result['cash_expenses'][__('Projected Other Cash Out Items')] = $outProjection;
		$result['cash_expenses'][__('Total Cash Outflow')]['total'] = $totalCashOutFlowArray;
		$netCash = HArr::subtractAtDates([$totalCashInFlowArray, $totalCashOutFlowArray], array_merge(array_keys($totalCashInFlowArray), array_keys($totalCashOutFlowArray)));

		$result['cash_expenses'][__('Net Cash (+/-)')]['total'] = $netCash;

		$result['cash_expenses'][__('Accumulated Net Cash (+/-)')]['total'] = $this->formatAccumulatedNetCash($netCash, $weeks);
	}

	public function formatAccumulatedNetCash(array $netCashes,array $weeks)
	{
		$currentAccumulated = 0 ;
		$result = [];

		foreach($weeks as $week => $weekNumber){
			$currentAccumulated +=  $netCashes[$week] ?? 0;
			$result[$week] = $currentAccumulated ;
		}
		return $result ;
	}
	public function mergeTotal(array $totals , $arrayOfItems,array $datesWithWeekNumber,$debug = false ):array 
	{
		foreach($arrayOfItems as $itemArr){
			$dateFormatted = $datesWithWeekNumber[$itemArr['week_start_date']]??null;
		
			if(is_null($dateFormatted)){
				continue;
			}
			$currentAmount = $itemArr['amount'];
			$totals[$dateFormatted] = isset($totals[$dateFormatted]) ? $totals[$dateFormatted] + $currentAmount : $currentAmount;
		}
		return $totals;
	}
	/**
	 * @param  array<string, string|int>  $weeks
	 * @return array<string, array{start_date: string, end_date: string}>
	 */
	protected function buildPeriodDatesMap(
		array $weeks,
		array $datesWithWeeks,
		string $reportStartDate,
		string $reportEndDate,
		?string $requestEndDate = null,
	): array {
		$dates = [];
		$firstIndex = array_key_first($weeks);
		$lastIndex = array_key_last($weeks);

		foreach ($weeks as $currentWeekYear => $week) {
			$currentYear = explode('-', $currentWeekYear)[1];
			if ($currentWeekYear === $firstIndex) {
				$periodStart = $reportStartDate;
				$periodEnd = HDate::getMinDateOfWeek($datesWithWeeks, $week, $currentYear)['end_date'];
			} elseif ($currentWeekYear === $lastIndex) {
				$periodStart = HDate::getMinDateOfWeek($datesWithWeeks, $week, $currentYear)['start_date'];
				$periodEnd = $requestEndDate ?? $reportEndDate;
			} else {
				$rangedWeeks = HDate::getMinDateOfWeek($datesWithWeeks, $week, $currentYear);
				$periodStart = $rangedWeeks['start_date'];
				$periodEnd = $rangedWeeks['end_date'];
			}

			$dates[$currentWeekYear] = [
				'start_date' => $periodStart,
				'end_date' => $periodEnd,
			];
		}

		return $dates;
	}

	protected function mergeYearWithWeek(array $weeks , Carbon $startDate ):array{
		$newWeeks = [];
		if(!count($weeks)){
			return [];
		}
		foreach($weeks as $date => $weekNumber){
			$currentDate =Carbon::make($date);
				$year = $currentDate->year ;
				if($currentDate->greaterThanOrEqualTo($startDate)){
					$newWeeks[$weekNumber.'-'.$year] = $weekNumber; 
				}
			
		}
		return $newWeeks;
		
	}
	
	protected function getDateWithWeakNumber(array $weeks , Carbon $startDate ):array{
		
		$newWeeks = [];
		if(!count($weeks)){
			return [];
		}
		foreach($weeks as $date => $weekNumber){
			$currentDate =Carbon::make($date);
				$year = $currentDate->year ;
				if($currentDate->greaterThanOrEqualTo($startDate)){
					$newWeeks[$date] =  $weekNumber.'-'.$year; 
				}
			
		}
		return $newWeeks;
		
	}
	
	
	
	
	
	


	
	
	public function getPastDueCustomerInvoices(string $invoiceType,string $currency , int $companyId , ?string $contractCode = null ){
		$fullClassName = '\App\Models\\'.$invoiceType;

		$items  = $fullClassName::where('company_id',$companyId)
		->where('net_balance','>',0)
		->whereIn('invoice_status',['past_due','partially_collected_and_past_due'])
		->where('currency',$currency)
		->where('invoice_due_date','<',now()->format('Y-m-d'))
		->when($contractCode , function($query) use($contractCode) {
			$query->where('contract_code',$contractCode);
		})
		->orderBy('invoice_due_date')
		->get()->toArray() ;
		
		return $items;
	}
	public function getPastDueLoanSchedules(string $currency , int $companyId  ){
		$items  = LoanSchedule::where('loan_schedules.company_id',$companyId)
		->where('remaining','>',0)
		->join('medium_term_loans','medium_term_loans.id','=','loan_schedules.medium_term_loan_id')
		->where('medium_term_loans.currency',$currency)
		->whereIn('loan_schedules.status',['past_due','partially_collected_and_past_due'])
		->where('date','<',now()->format('Y-m-d'))
		->orderBy('date')
		->selectRaw('loan_schedules.*,medium_term_loans.currency,medium_term_loans.name as loan_name')->get()->toArray() ;
		return $items;
	}
	
	
	
	
	
	// protected function getCashExpensesAtDates(int $companyId , string $startDate , string $endDate,string $currency,int $cashExpenseCategoryNameId) 
	// {
	// 	return DB::table('cash_expenses')->where('company_id',$companyId)->whereBetween('payment_date',[$startDate,$endDate])->where('currency',$currency)->where('cash_expense_category_name_id',$cashExpenseCategoryNameId)->sum('paid_amount');
	// }
	public function adjustCustomerDueInvoices(Request $request,Company $company){
		$invoiceType = $request->get('invoiceType');
		$currencyName = $request->get('currency_name');
		$contractCode = $request->get('contract_code');
		$isContract = $request->get('is_contract');
		$cashflowReportId = $request->get('cashflow_report_id');
	
		foreach($request->get('customer_invoice_id',[]) as $customerInvoiceId){
			$weekStartDate = $request->input('week_start_date.'.$customerInvoiceId);
			$percentage = $request->input('percentage.'.$customerInvoiceId);
			$invoiceAmount = $request->input('invoice_amount.'.$customerInvoiceId);
			$amount = $percentage/100  * $invoiceAmount;
			$first = DB::table('weekly_cashflow_custom_due_invoices')
			->where('company_id',$company->id)
			->where('invoice_id',$customerInvoiceId)
			->where('is_contract',$isContract)
			->where('cashflow_report_id',$cashflowReportId)
			->where('invoice_type',$invoiceType)->first();
			$data = [
				'company_id'=>$company->id ,
				'invoice_id'=>$customerInvoiceId,
				'invoice_type'=>$invoiceType,
				'week_start_date'=>$weekStartDate,
				'percentage'=>$percentage,
				'amount'=>$amount,
				'cashflow_report_id'=>$cashflowReportId,
				'is_contract'=>$isContract,
			] ;
			if($first){
				DB::table('weekly_cashflow_custom_due_invoices')
				->where('company_id',$company->id)
				->where('invoice_id',$customerInvoiceId)
				->where('cashflow_report_id',$cashflowReportId)
				->where('is_contract',$isContract)
				->where('invoice_type',$invoiceType)->update($data);
			}else{
				DB::table('weekly_cashflow_custom_due_invoices')->insert($data);
			}
			
		}
		$this->refreshDueInvoicesAndSettlements($company,$request,$currencyName,$isContract,$contractCode);
		// $excludeIds = $pastDueInstallments->where('net_balance_until_date','<=',0)->pluck('id')->toArray() ;
		// ->whereNotIn('loan_schedule_id',$excludeIds)

			// 'pastDueCustomerInvoices'=>$pastDueCustomerInvoicesPerCurrency,
			// 'customerDueInvoices'=>$customerDueInvoicesPerCurrency,
			// 'pastDueSupplierInvoices'=>$pastDueSupplierInvoices,
			// 'supplierDueInvoices'=>$supplierDueInvoices,
			// 'pastDueInstallments'=>$pastDueInstallments,
			// 'pastDueLoanInstallments'=>$pastDueLoanInstallments,
			
		
	
		return response()->json([
			'status'=>true ,
			'message'=>'',
			'reloadCurrentPage'=>true 
		]);
	}
	public function refreshDueInvoicesAndSettlements(Company $company , Request $request , string $currency , bool $isContract ,?string $contractCode = null  )
	{
		
		
		
	
		$cashflowReportId = $request->get('cashFlowReportId');
		$model  = $cashflowReportId ? CashFlowReport::find($cashflowReportId) : $company;
		// for loans 
		if($cashflowReportId && $cashflowReportId > 0){
			$oldReportData = json_decode($model->report_data,true);
			$oldReportData ? extract($oldReportData) : null;
			// for customers 
			$pastDueCustomerInvoices = $this->getPastDueCustomerInvoices('CustomerInvoice',$currency,$company->id,$contractCode);
			// $excludeIds = $pastDueCustomerInvoices->where('net_balance_until_date','<=',0)->pluck('id')->toArray() ;
			$customerDueInvoices=json_decode(json_encode(DB::table('weekly_cashflow_custom_due_invoices')->where('company_id',$company->id)
			->where('invoice_type','CustomerInvoice')
			->where('cashflow_report_id',$cashflowReportId)
			->where('is_contract',$isContract)
			// ->whereNotIn('invoice_id',$excludeIds)
			->groupBy('week_start_date')->selectRaw('week_start_date,sum(amount) as amount')->get()),true);
		
		// for suppliers 
			$pastDueSupplierInvoices = $this->getPastDueCustomerInvoices('SupplierInvoice',$currency,$company->id,$contractCode);
			$supplierDueInvoices=json_decode(json_encode(DB::table('weekly_cashflow_custom_due_invoices')->where('company_id',$company->id)
			->where('invoice_type','SupplierInvoice')
			->where('cashflow_report_id',$cashflowReportId)
			->where('is_contract',$isContract)
			// ->whereNotIn('invoice_id',$excludeIds)
			->groupBy('week_start_date')->selectRaw('week_start_date,sum(amount) as amount')->get()),true);
		
			$pastDueInstallments = $this->getPastDueLoanSchedules($currency,$company->id);
			$pastDueLoanInstallments=json_decode(json_encode(DB::table('weekly_cashflow_custom_past_due_schedules')->where('company_id',$company->id)
			->groupBy('week_start_date')->selectRaw('week_start_date,sum(amount) as amount')->get()),true);
			$pastDueCustomerInvoicesPerCurrency[$currency] = $pastDueCustomerInvoices;
			$customerDueInvoicesPerCurrency[$currency] = $customerDueInvoices;
		
			$oldReportData['pastDueCustomerInvoices'] =$pastDueCustomerInvoicesPerCurrency ;
			$oldReportData['customerDueInvoices']=$customerDueInvoicesPerCurrency;
			$oldReportData['pastDueSupplierInvoices']=$pastDueSupplierInvoices;
			$oldReportData['supplierDueInvoices']=$supplierDueInvoices;
			$oldReportData['pastDueInstallments']=$pastDueInstallments;
			$oldReportData['pastDueLoanInstallments']=$pastDueLoanInstallments;
		
			$model->update([
				'report_data'=>json_encode($oldReportData)
			]);
		}
	}
	
	
	public function adjustLoanPastDueInstallments(Request $request,Company $company ){
		$currencyName = $request->get('currency_name');
		$isContract = $request->get('is_contract');
		$contractCode = $request->get('contract_code');
		// $contractCode = 
		foreach($request->get('loan_schedule_id',[]) as $loanScheduleId){
			$weekStartDate = $request->input('week_start_date.'.$loanScheduleId);
			$percentage = $request->input('percentage.'.$loanScheduleId);
			$invoiceAmount = $request->input('invoice_amount.'.$loanScheduleId);
			$amount = $percentage/100  * $invoiceAmount;
			$first = DB::table('weekly_cashflow_custom_past_due_schedules')
			->where('company_id',$company->id)
			->where('is_contract',$isContract)
			->where('loan_schedule_id',$loanScheduleId)
			->first();
			$data = [
				'is_contract'=>$isContract,
				'loan_schedule_id'=>$loanScheduleId,
				'week_start_date'=>$weekStartDate,
				'percentage'=>$percentage,
				'amount'=>$amount,
				'company_id'=>$company->id 
			] ;
			if($first){
				DB::table('weekly_cashflow_custom_past_due_schedules')
				->where('company_id',$company->id)
				->where('is_contract',$isContract)
				->where('loan_schedule_id',$loanScheduleId)
				->update($data);
			}else{
				DB::table('weekly_cashflow_custom_past_due_schedules')->insert($data);
			}
			$this->refreshDueInvoicesAndSettlements($company,$request,$currencyName,$isContract,$contractCode);
		}
		return response()->json([
			'status'=>true ,
			'message'=>'',
			'reloadCurrentPage'=>true 
		]);
	}
	
	public function saveProjection(Request $request , Company $company )
	{
		// just initialize 
		$allCurrencies = [
			$company->getMainFunctionalCurrency()
		];
		$redirectRouteName= '';
		$projectionType = $request->get('type');
		$dates = array_keys((array)json_decode($request->input('dates.0')));
		$cashflowReportId = $request->get('cashFlowReportId');
		$isContract = $request->get('is_contract');
		$model  = $cashflowReportId ? CashFlowReport::find($cashflowReportId) : $company;
		$model->cashProjects()->where('is_contract',$isContract)->where('type',$projectionType)->delete();
		foreach($request->get('projection-'.$projectionType.'id') as $projectionArr){
			$amounts = $projectionArr['amounts'];
			$amounts = array_combine($dates,$amounts);
			$model->cashProjects()->create([
				'is_contract'=>$isContract,
				'name'=>$projectionArr['name'],
				'type'=>$projectionType,
				'amounts'=>$amounts,
				'cashflow_report_id'=>$cashflowReportId,
				'company_id'=>$company->id ,
			]);
		}
		// $request->merge([
		// 	'reset_report'=>0
		// ]);			
		
		if($cashflowReportId){
	
			$newResult =[];
			CashExpense::getProjectionOtherCashOut($newResult ,$company,$cashflowReportId,$isContract) ;
			CustomerInvoice::getProjectionOtherCashIn($newResult ,$company,$cashflowReportId,$isContract) ;
			$oldReportData = json_decode($model->report_data,true);
			extract($oldReportData);
			foreach($allCurrencies as $currencyName){
				$oldReportData['finalResult'][$currencyName]['customers']['Projected Other Cash In Items'] =$newResult['customers']['Projected Other Cash In Items']??[] ;
				$oldReportData['finalResult'][$currencyName]['cash_expenses']['Projected Other Cash Out Items'] =$newResult['customers']['Projected Other Cash Out Items']??[] ;
			}
			$model->update([
				'report_data'=>json_encode($oldReportData)
			]);
			return redirect()->route($redirectRouteName,['company'=>$company->id,'cashflowReport'=>$model->id,'returnResultAsArray'=>'view']);
			
		}
		return redirect()->back()->with('without_resetting',1);
			
	}

	public function destroy(Request $request, Company $company,CashflowReport $cashflowReport){
		$viewRouteName = $cashflowReport->is_contract ? 'view.contract.cashflow.report' :'view.cashflow.report';
		$cashflowReport->cashProjects()->delete();
		DB::table('weekly_cashflow_custom_due_invoices')
		->where('company_id',$company->id)
		->where('cashflow_report_id',$cashflowReport->id)->delete();
		$cashflowReport->delete();
		return redirect()->route($viewRouteName,['company'=>$company->id]);
	}
	protected function sumAllTotalKeys(array $items,array $items2,array $datesWithWeekNumber){
		
		$totals=[];
		foreach(array_flip($datesWithWeekNumber) as $week=>$date){
			foreach($items as $subItemName => $itemArr){
				$currentTotal = $itemArr['total'][$week]??0 ;
				$totals[$week]= isset($totals[$week]) ? $totals[$week] + $currentTotal:$currentTotal ;
			}
			foreach($items2 as $subItemName => $itemArr){
				$currentTotal = $itemArr['total'][$week]??0 ;
				$totals[$week]= isset($totals[$week]) ? $totals[$week] + $currentTotal:$currentTotal ;
			}
		}
		return $totals;
		
	}
}
