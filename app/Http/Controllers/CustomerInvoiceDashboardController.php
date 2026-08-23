<?php

namespace App\Http\Controllers;
use App\Enums\LcTypes;
use App\Enums\LgTypes;
use App\Exports\Statements\CustomerSupplierStatementExport;
use App\Exports\Statements\InvoiceReportExport;
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
use Inertia\Inertia;

/**
 * CustomerInvoiceDashboardController
 * ------------------------------------------------------------------
 * A grouping of customer/supplier-invoice-related dashboard and report
 * pages that don't share a single business entity, only a common data
 * source (customer_invoices / supplier_invoices). Responsibilities:
 *
 *   - Cash / Forecast / LG&LC dashboards (viewCashDashboard,
 *     viewForecastDashboard, viewLGLCDashboard) — the "Dashboard"
 *     sidebar section's 3 tabs. All 3 now MIGRATED to Inertia/Vue
 *     (see each method's own docblock for specifics).
 *   - showInvoiceReport() — the per-customer "Invoice Report" page,
 *     reached from Customers/Suppliers Balances. Includes a real
 *     write action (the "Deduct" modal, posting to
 *     update.invoice.deductions, migrated alongside this in
 *     InvoiceDeductionsController — see its own docblock for the
 *     Inertia-compatibility fix that required).
 *   - showInvoiceStatementReport() — the per-customer "Statement
 *     Report" page (a ledger: beginning balance, invoices,
 *     collections, deductions, down payments, factoring), reached
 *     the same way. Entirely READ-ONLY (just a date-range filter).
 *
 * All the actual ledger math lives in HasBalances::formatForStatementReport()
 * / appendBalances() — UNCHANGED, deliberately. The one bit of display
 * math that genuinely lives at the presentation layer (the running
 * "End Balance" column) was already computed in the original Blade
 * template itself, not in HasBalances — see the Vue page for where
 * that now lives, same formula.
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   - showInvoiceStatementReport() → ALREADY migrated. Returns
 *                                     Inertia::render(), served by
 *                                     resources/js/Pages/Balances/Statement.vue
 *   - showInvoiceReport()          → ALREADY migrated. Returns
 *                                     Inertia::render(), served by
 *                                     resources/js/Pages/Balances/InvoiceReport.vue
 *   - viewCashDashboard() / viewForecastDashboard() / viewLGLCDashboard()
 *                                   → ✅ MIGRATED to Inertia/Vue — see
 *                                     Dashboard/CashStatus.vue,
 *                                     Dashboard/Forecast.vue,
 *                                     Dashboard/LGLCStatus.vue.
 */
class CustomerInvoiceDashboardController extends Controller
{
	use HasBalances;

	private const INVOICE_ROWS_PER_PAGE = 50;

    /**
     * Cash Status dashboard — the "Cash Status" tab of the Dashboard
     * sidebar section. All the actual math (balances, overdraft room,
     * card totals) is computed by CashDashboardService::build(),
     * UNCHANGED — this method's only job is to flatten the handful of
     * Eloquent collections the Blade version called methods on
     * directly (Inertia can't call PHP methods client-side) and
     * pre-resolve every URL the Vue page needs (no Ziggy — see Style
     * Guide §8). Renders resources/js/Pages/Dashboard/CashStatus.vue.
     *
     * ── Frontend migration status ──
     *   ✅ viewCashDashboard() → MIGRATED to Inertia/Vue.
     */
    public function viewCashDashboard(Company $company, Request $request)
    {
        $data = app(CashDashboardService::class)->build($company, $request);

        $bankList = fn ($collection) => collect($collection)->map(fn ($bank) => [
            'id' => $bank->id,
            'name' => $bank->getName(),
        ])->values()->all();

        $overdraftTypes = [
            'FullySecuredOverdraft' => 'Fully Secured Overdraft',
            'CleanOverdraft' => 'Clean Overdraft',
            'OverdraftAgainstCommercialPaper' => 'Overdraft Against Commercial Paper',
            'OverdraftAgainstAssignmentOfContract' => 'Overdraft Against Assignment Of Contract',
        ];
        $bankStatementUrls = [];
        $withdrawalReportUrls = [];
        foreach (array_keys($overdraftTypes) as $accountType) {
            foreach ($data['selectedCurrencies'] as $currencyName) {
                $bankStatementUrls[$accountType][$currencyName] = route('view.bank.statement', ['company' => $company->id, 'accountType' => $accountType, 'currency' => $currencyName]);
                $withdrawalReportUrls[$accountType][$currencyName] = route('view.withdrawals.settlement.report', ['company' => $company->id, 'accountType' => $accountType, 'currency' => $currencyName]);
            }
        }

        $firstAccountTypeId = fn ($collection) => optional(collect($collection)->first())->id;

        return Inertia::render('Dashboard/CashStatus', array_merge($data, [
            'company' => ['id' => $company->id],
            'allCurrencies' => array_values($data['allCurrencies']),
            'financialInstitutionBanks' => $bankList($data['financialInstitutionBanks']),
            'allFullySecuredOverdraftBanks' => $bankList($data['allFullySecuredOverdraftBanks']),
            'allCleanOverdraftBanks' => $bankList($data['allCleanOverdraftBanks']),
            'allOverdraftAgainstCommercialPaperBanks' => $bankList($data['allOverdraftAgainstCommercialPaperBanks']),
            'allOverdraftAgainstAssignmentOfContractBanks' => $bankList($data['allOverdraftAgainstAssignmentOfContractBanks']),
            'mediumTermLoansArr' => $this->flattenLoanOrLeasingArr($data['mediumTermLoansArr'], $data['date'], false),
            'leasingContractsArr' => $this->flattenLoanOrLeasingArr($data['leasingContractsArr'], $data['date'], true),
            'overdraftTypeLabels' => $overdraftTypes,
            'overdraftAccountTypeIds' => [
                'FullySecuredOverdraft' => $firstAccountTypeId($data['fullySecuredOverdraftAccountTypes']),
                'CleanOverdraft' => $firstAccountTypeId($data['cleanOverdraftAccountTypes']),
                'OverdraftAgainstCommercialPaper' => $firstAccountTypeId($data['overdraftAgainstCommercialPaperAccountTypes']),
                'OverdraftAgainstAssignmentOfContract' => $firstAccountTypeId($data['overdraftAgainstAssignmentOfContractAccountTypes']),
            ],
            'bankStatementUrls' => $bankStatementUrls,
            'withdrawalReportUrls' => $withdrawalReportUrls,
            'filterUrl' => route('view.customer.invoice.dashboard.cash', ['company' => $company->id]),
            'refreshChartUrl' => route('refresh.chart.limits.data', ['company' => $company->id]),
            'accountNumbersUrl' => route('bank.statement.account.numbers', ['company' => $company->id]),
            'dashboardTabUrls' => [
                'cash' => route('view.customer.invoice.dashboard.cash', ['company' => $company->id]),
                'contracts' => route('view.contracts.dashboard', ['company' => $company->id]),
                'lglc' => route('view.lglc.dashboard', ['company' => $company->id]),
                'forecast' => route('view.customer.invoice.dashboard.forecast', ['company' => $company->id]),
            ],
        ]));
    }

    /**
     * Flattens a MediumTermLoan/LeasingContract collection (keyed by
     * currency) into plain arrays — both models expose the same
     * shaped getters (getName/getLimitFormatted/etc.), just under a
     * different "owning institution" method name, hence the
     * $isLeasing switch. Purely presentational; none of the
     * underlying getters are touched.
     *
     * Outstanding/Paid are computed LIVE here, principal-only —
     * matching what a bank statement actually shows, not what was
     * previously used (SUM(remaining), which double-counted future
     * un-accrued interest and read too high).
     *
     * Per row: outstanding principal = min(principle_amount, remaining).
     * This applies the standard banking convention — a payment settles
     * interest first, then principal — using only data already stored
     * per row, no schema change or re-amortization engine needed:
     *   - remaining = schedule_payment − actual amount settled (already
     *     correctly, automatically maintained per row, including
     *     partial payments, by LoanScheduleSettlement's /
     *     ContractLoanScheduleSettlement's booted() hooks)
     *   - if remaining >= principle_amount: even interest isn't fully
     *     covered yet, so the full principal is still outstanding →
     *     min() caps at principle_amount
     *   - if remaining < principle_amount: interest is fully covered,
     *     and (principle_amount − remaining) of principal has actually
     *     been retired → min() correctly returns what's left
     * This also automatically nets in whatever was already paid before
     * the company joined CashVero, as long as the uploaded schedule's
     * first row correctly started from the true remaining principal.
     */
    private function flattenLoanOrLeasingArr($arrByCurrency, string $date, bool $isLeasing): array
    {
        $out = [];
        foreach ($arrByCurrency as $currencyName => $collection) {
            $out[$currencyName] = collect($collection)->map(function ($item) use ($date, $isLeasing) {
                $next = $item->getNextInstallmentDateAndAmount($date);
                $schedules = $isLeasing ? $item->contractLoanSchedules : $item->loanSchedules;

                /**
                 * REVERTED 2026-08-15: the previous edit here summed each
                 * installment's full remaining (principal + interest) and
                 * treated Limit as if it were the total of every future
                 * payment. That's wrong — Limit is the loan/lease's
                 * PRINCIPAL amount, and the sum of principal+interest
                 * across a whole amortization schedule is always bigger
                 * than the principal alone. That edit made Outstanding
                 * balloon past Limit and Paid go negative even on an
                 * untouched loan — confirmed wrong by the client
                 * (screenshot: Limit 6,000,000, Outstanding 10,304,294,
                 * Paid -4,304,294, nothing actually paid). Back to
                 * outstanding PRINCIPAL only, which is the standard
                 * meaning of "Outstanding" for a loan balance and is what
                 * correctly nets to ~Limit when nothing has been paid.
                 *
                 * The original small-gap symptom (Limit 6,000,000, nothing
                 * paid, "Paid" showing 3,333) still needs a real
                 * diagnosis — see the follow-up investigation, since it's
                 * most likely a rounding artifact in the schedule's own
                 * principal amounts (their sum landing a few thousand off
                 * Limit), not this formula.
                 */
                $outstanding = $schedules->sum(fn ($schedule) => min($schedule->getPrincipleAmount(), $schedule->getRemaining()));
                $paid = $item->getLimit() - $outstanding;

                return [
                    'id' => $item->id,
                    'institution_name' => $isLeasing ? $item->getLeasingCompanyName() : $item->getFinancialInstitutionName(),
                    'name' => $item->getName(),
                    'limit_formatted' => $item->getLimitFormatted(),
                    'paid_formatted' => number_format($paid),
                    'outstanding_formatted' => number_format($outstanding),
                    'next_installment_date' => $next['date_formatted'] ?? null,
                    'next_installment_amount' => $next['amount_formatted'] ?? null,
                    'total_past_due_remaining_formatted' => $item->getTotalPastDueRemainingFormatted(),
                    'past_dues' => $item->getLoanPastDuesDetailsArray(),
                ];
            })->values()->all();
        }
        return $out;
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
		/**
		 * FIX (per report, 2026-08-13): the dashboard's own Start/End
		 * Date form fields already defaulted to today → +3 months
		 * (previously +1 month, per the client's initial ask) — but
		 * CashFlowReportController::result() below has its own,
		 * completely separate default date range (buildSharedTimelineContext(),
		 * still today → +1 month), and nothing here ever told it about
		 * the 3-month range computed just below. On a fresh visit
		 * (no start_date/end_date in the URL yet), that meant the form
		 * fields showed 3 months while the actual chart was still
		 * silently computed over only 1 — two different date ranges
		 * disagreeing on the same page. Merging the computed dates into
		 * the request here (only when not already present) makes both
		 * sides agree, without touching CashFlowReportController's own
		 * default logic at all — it still applies normally for any
		 * other caller, this just makes sure THIS page's request always
		 * carries an explicit date range before reaching it.
		 */
		$cashFlowStartDate = $request->get('start_date', now()->format('Y-m-d'));
		$cashFlowEndDate = $request->get('end_date', Carbon::make($cashFlowStartDate)->addMonths(3)->format('Y-m-d'));
		if (! $request->has('start_date')) {
			$request->merge(['start_date' => $cashFlowStartDate]);
		}
		if (! $request->has('end_date')) {
			$request->merge(['end_date' => $cashFlowEndDate]);
		}

		$clientsWithContracts = Partner::onlyCompany($company->id)	->onlyCustomers()->onlyThatHaveCustomerContracts()->get();
		$allCurrencies = getCurrenciesForSuppliersAndCustomers($company->id) ;
		// $financialInstitutionsThatHaveMediumTermLoans = FinancialInstitution::onlyCompany($company->id)->onlyHasMediumTermLoans()->get();
		$dashboardResult = [];
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
			// A contract only ever has a single currency, so key by it too for
			// consistency with the per-currency lookup used in the Blade view.
			$cashFlowReport[$reportCurrentName]['total_cash_in_out_flow']=$this->formatFlowCashInOutChartData($cashFlowReportResult['customers'][__('Total Cash Inflow')]['total'] ?? [],$cashFlowReportResult['cash_expenses'][__('Total Cash Outflow')]['total'] ?? [],$dates);
			$cashFlowReport[$reportCurrentName]['accumulated_net_cash']= formatAccumulatedNetCash($cashFlowReportResult['cash_expenses'][__('Net Cash (+/-)')]['total'] ?? [] ,$dates );
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
			$cashFlowReport[$reportCurrentName]['total_cash_in_out_flow']=$this->formatFlowCashInOutChartData($cashFlowReportResult['customers'][__('Total Cash Inflow')]['total'] ?? [],$cashFlowReportResult['cash_expenses'][__('Total Cash Outflow')]['total'] ?? [],$dates);
			$cashFlowReport[$reportCurrentName]['accumulated_net_cash']= formatAccumulatedNetCash($cashFlowReportResult['cash_expenses'][__('Net Cash (+/-)')]['total'] ?? [] ,$dates );

			/**
			 * * "Monthly Cash Flow" / "Accumulated Cash Flow" charts are shown once per
			 * * currency tab, so they must be computed separately per currency (the
			 * * company-wide report above only covers $reportCurrentName).
			 */
			/**
			 * FIX (per audit, 2026-08-13): this used to default to
			 * $allCurrencies — EVERY currency the company has ever
			 * configured a safe/bank account in, not just the ones
			 * actually relevant right now — meaning the full,
			 * genuinely heavy CashFlowReportController::result() ran
			 * once per currency, every single time this page loaded,
			 * even for currencies nobody was looking at. Defaulting to
			 * just the active report currency cuts that from N heavy
			 * report runs to 1 on a normal visit; explicitly requesting
			 * more via ?currencies[]=... (e.g. clicking a not-yet-loaded
			 * currency pill — see Forecast.vue) still computes exactly
			 * what's asked for, same as before. $selectedCurrencies
			 * (the full list used for the currency PILL buttons
			 * themselves, further down) is intentionally untouched —
			 * people still see every currency as an option, this only
			 * changes which ones get computed up front.
			 */
			$selectedCurrenciesForCashFlowChart = $request->filled('currencies') ? (array) $request->get('currencies') : [];
			foreach ($selectedCurrenciesForCashFlowChart as $currencyNameForChart) {
				if ($currencyNameForChart === $reportCurrentName || isset($cashFlowReport[$currencyNameForChart])) {
					continue;
				}
				$currencyRequest = $request->duplicate();
				$currencyRequest->query->set('currency', $currencyNameForChart);
				$currencyReport = (new CashFlowReportController())->result($company, $currencyRequest, true, null, -1);
				if ($currencyReport instanceof RedirectResponse) {
					continue;
				}
				$cashFlowReport[$currencyNameForChart]['total_cash_in_out_flow'] = $this->formatFlowCashInOutChartData(
					$currencyReport['result']['customers'][__('Total Cash Inflow')]['total'] ?? [],
					$currencyReport['result']['cash_expenses'][__('Total Cash Outflow')]['total'] ?? [],
					$currencyReport['dates']
				);
				$cashFlowReport[$currencyNameForChart]['accumulated_net_cash'] = formatAccumulatedNetCash(
					$currencyReport['result']['cash_expenses'][__('Net Cash (+/-)')]['total'] ?? [],
					$currencyReport['dates']
				);
			}
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
		/**
		 * ⚠️ REAL BUG FIXED HERE (per audit, 2026-08-18): same issue as
		 * viewLGLCDashboard() below — this used to read
		 * $request->get('currencies', $allCurrencies), so every
		 * switchCurrency() visit that passed ?currencies[]=EURO collapsed
		 * the pill row to that one currency. $selectedCurrencies must
		 * always be the FULL list; only $currenciesToCompute narrows.
		 */
        $selectedCurrencies = $allCurrencies ;
		/**
		 * FIX (per audit, 2026-08-13): same reasoning as the cash-flow
		 * chart loop above — $selectedCurrencies itself is left
		 * completely unchanged (it's also what the currency PILL
		 * buttons render from, further down, so narrowing it would
		 * have hidden currencies as options entirely, not just skipped
		 * computing them). This new, separate, narrower list is what
		 * actually drives the two heavy loops below instead — the
		 * financial institutions lookup and, more importantly, the
		 * Invoice/Cheque Aging summary (2 services × 2 invoice types ×
		 * however many currencies — previously run for every company
		 * currency on every visit, regardless of which one anyone was
		 * actually looking at).
		 */
		$currenciesToCompute = $request->filled('currencies') ? (array) $request->get('currencies') : array_values(array_filter([$reportCurrentName]));

		$financialInstitutionsByCurrency = [];
		foreach ($currenciesToCompute as $currencyName) {
			$financialInstitutionsByCurrency[$currencyName] = FinancialInstitution::onlyCompany($company->id)
				->onlyHasMediumTermLoans($currencyName)
				->get();
		}

		
		$allFinancialInstitutionIds = $company->financialInstitutions->pluck('id')->toArray(); 
		foreach($currenciesToCompute as $currencyName)
		{
			foreach ($invoiceTypesModels as $modelType) {
				/**
				 * * Customers Invoices Aging & Supplier Invoices Aging
				 */
				$invoiceAgingService = new InvoiceAgingService($company->id, $agingDate,$currencyName);
				$chequeAgingService = new ChequeAgingService($company->id, $agingDate,$currencyName);
				$agingsForInvoices = $invoiceAgingService->__execute([], $modelType) ;
				$agingsForInvoices = $invoiceAgingService->formatForDashboard($agingsForInvoices,$modelType);
				/**
				 * * Customers Cheques Aging & Supplier Cheques Aging
				 */
				$agingsForChequesWithChart = $chequeAgingService->__execute([], $modelType) ;
				$agingsForCheques = $agingsForChequesWithChart['result_for_table'];
				$agingsForChequesCharts = $agingsForChequesWithChart['result_for_chart'];
				
				$agingsForCheques = $chequeAgingService->formatForDashboard($agingsForCheques,$modelType);
	
				$dashboardResult['invoices_aging'][$modelType][$currencyName] = $agingsForInvoices ;
				$dashboardResult['cheques_aging_for_table'][$modelType][$currencyName] = $agingsForCheques ;
				$dashboardResult['cheques_aging_for_chart'][$modelType][$currencyName] = $agingsForChequesCharts ;
				
			}
		}
		// ✅ MIGRATED to Inertia/Vue — renders
		// resources/js/Pages/Dashboard/Forecast.vue. All the reporting
		// math above (CashFlowReportController/ContractCashFlowReportController
		// results, InvoiceAgingService, ChequeAgingService) is
		// UNCHANGED; only the final response was touched, plus
		// flattening the 3 Eloquent collections the Blade version
		// called methods on directly (Inertia can't call PHP methods
		// client-side).
		$partnerList = fn ($collection) => collect($collection)->map(fn ($partner) => [
			'id' => $partner->id,
			'name' => $partner->getName(),
		])->values()->all();
		$accountTypeList = fn ($collection) => collect($collection)->map(fn ($accountType) => [
			'id' => $accountType->id,
			'name' => $accountType->getName(),
		])->values()->all();
		$financialInstitutionsByCurrencyFlat = [];
		foreach ($financialInstitutionsByCurrency as $currencyName => $collection) {
			$financialInstitutionsByCurrencyFlat[$currencyName] = collect($collection)->map(fn ($fi) => [
				'id' => $fi->id,
				'name' => $fi->getName(),
			])->values()->all();
		}

        return Inertia::render('Dashboard/Forecast', [
            'company' => ['id' => $company->id],
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
			'overdraftAccountTypes'=>$accountTypeList($overdraftAccountTypes),
			'selectedCurrencies'=>array_values($selectedCurrencies),
			'allCurrencies'=>array_values($allCurrencies),
			'allFinancialInstitutionIds'=>$allFinancialInstitutionIds,
			'clientsWithContracts'=>$partnerList($clientsWithContracts),
			'cashFlowReport'=>$cashFlowReport,
			'contractCode'=>$contractCode,
			'currencyName'=>$reportCurrentName,
			'currentCurrencyName'=>$reportCurrentName,
			'pastDueCustomerInvoices'=>$pastDueCustomerInvoices??[],
			'pastDueSupplierInvoices'=>$pastDueSupplierInvoices,
			'pastDueInstallments'=>$pastDueInstallments,
			// The interval the report ACTUALLY ran at, not a guess at it.
			// CashFlowReportController::result() falls back to 'monthly' when
			// report_interval is missing or invalid, so reading the raw query
			// param with a 'weekly' default made the filter contradict the
			// charts on first entry: monthly buckets rendered, "Weekly"
			// selected. $reportInterval comes straight back from the report.
			'selectedReportInterval'=>$reportInterval,
			'cashFlowStartDate'=>$cashFlowStartDate,
			'cashFlowEndDate'=>$cashFlowEndDate,
			'selectedPartnerId'=>$request->get('partner_id'),
			'selectedContractId'=>$request->get('contract_id'),
			'financialInstitutionsByCurrency'=>$financialInstitutionsByCurrencyFlat,
			'filterUrl'=>route('view.customer.invoice.dashboard.forecast', ['company' => $company->id]),
			'dashboardTabUrls' => [
				'cash' => route('view.customer.invoice.dashboard.cash', ['company' => $company->id]),
				'contracts' => route('view.contracts.dashboard', ['company' => $company->id]),
				'lglc' => route('view.lglc.dashboard', ['company' => $company->id]),
				'forecast' => route('view.customer.invoice.dashboard.forecast', ['company' => $company->id]),
			],
			// 'financialInstitutionsThatHaveMediumTermLoans'=>$financialInstitutionsThatHaveMediumTermLoans
			
        ]);
    }

    /**
     * Customer/Supplier Invoice Report — every invoice for one
     * customer + currency, with per-invoice actions: Adjust Due Date
     * (still Blade, plain link out), Money Received/Payment (still
     * Blade, plain link out), and Deduct (a real write action — see
     * InvoiceDeductionsController@update, migrated alongside this).
     * Renders resources/js/Pages/Balances/InvoiceReport.vue.
     *
     * Every *Formatted()/get*() call below is the model's existing,
     * UNCHANGED formatting method — this method's job is only to
     * flatten each invoice into a plain array (Inertia can't call
     * PHP methods client-side) and pre-resolve the URLs the page
     * needs (no Ziggy — see Style Guide §8).
     */
    public function showInvoiceReport(Company $company, Request $request, int $partnerId, string $currency, $modelType)
    {

        $fullClassName = ('\App\Models\\' . $modelType) ;

        $clientIdColumnName = $fullClassName::CLIENT_ID_COLUMN_NAME ;
        $isCollectedOrPaid = $fullClassName::COLLETED_OR_PAID ;
        $moneyReceivedOrPaidText = (new $fullClassName())->getMoneyReceivedOrPaidText();
        $moneyReceivedOrPaidUrlName = (new $fullClassName())->getMoneyReceivedOrPaidUrlName();
		
		$deductions = Deduction::onlyForCompany($company->id)->get();

        /*
         * Paginated at the database level: a long-standing customer can
         * have thousands of invoices, and every row here costs several
         * accessor lookups (deductions, due-date history, exchange rate).
         * `id` is appended to the sort so the order is total — without it
         * invoices sharing a date could swap between pages and be shown
         * twice or skipped. The Excel export below still runs the same
         * query unpaginated.
         */
        $invoices = ('App\Models\\' . $modelType)::where('company_id', $company->id)
        ->where($clientIdColumnName, $partnerId)
        ->where('currency', $currency)
		->with(['deductions', 'dueDateHistories'])
		->orderByRaw('invoice_date asc , invoice_due_date desc , net_balance desc , id asc')
        ->paginate(self::INVOICE_ROWS_PER_PAGE)
        ->withQueryString();
        $customer = Partner::find($partnerId);
        if (!$invoices->total()) {
            return  redirect()->back()->with('fail', __('No Data Found'));
        }
		$hasProjectNameColumn = $modelType == 'CustomerInvoice'?  CustomerInvoice::hasProjectNameColumn() : false;
		$totalCollectionOrPaidText  = $modelType == 'CustomerInvoice' ? __('Total Collections') : __('Total Payments');

		$invoiceIsCollectedOrPaidMethod = 'is' . ucfirst($isCollectedOrPaid);
		$formattedInvoices = $invoices->through(function ($invoice) use ($invoiceIsCollectedOrPaidMethod, $company, $modelType, $moneyReceivedOrPaidUrlName, $hasProjectNameColumn, $currency) {
			$isDone = $invoice->{$invoiceIsCollectedOrPaidMethod}();
			return [
				'id' => $invoice->id,
				'project_name' => $hasProjectNameColumn ? $invoice->getProjectName() : null,
				'invoice_date' => $invoice->getInvoiceDateFormatted(),
				'invoice_number' => $invoice->getInvoiceNumber(),
				'invoice_amount_formatted' => $invoice->getInvoiceAmountFormatted(),
				// Exchange-rate info modal only makes sense off the
				// company's main currency (matches the original's
				// `@if($currency != $company->getMainFunctionalCurrency())` guard).
				'show_exchange_info' => $currency != $company->getMainFunctionalCurrency(),
				'net_invoice_in_main_currency_formatted' => number_format($invoice->getNetInvoiceInMainCurrencyAmount(), 2),
				'exchange_rate_formatted' => number_format($invoice->getExchangeRate(), 4),
				'total_withhold_formatted' => $invoice->getTotalWithholdAmountFormatted(),
				'vat_amount_formatted' => $invoice->getVatAmountFormatted(),
				'total_deduction_formatted' => $invoice->getTotalDeductionFormatted(),
				'total_collected_or_paid_formatted' => $invoice->getTotalCollectedOrPaidFormatted(),
				'due_date_formatted' => $invoice->getDueDateFormatted(),
				'net_balance' => $invoice->getNetBalance(),
				'net_balance_formatted' => $invoice->getNetBalanceFormatted(),
				'status_formatted' => $invoice->getStatusFormatted(),
				'aging' => $invoice->getAging(),
				'is_collected_or_paid' => $isDone,
				'due_date_history_count' => $invoice->dueDateHistories->count(),
				'adjust_due_date_url' => $isDone ? null : route('adjust.due.dates', ['company' => $company->id, 'modelId' => $invoice->id, 'modelType' => $modelType]),
				'money_action_url' => $isDone ? null : route($moneyReceivedOrPaidUrlName, ['company' => $company->id, 'model' => $invoice->id]),
				'update_deductions_url' => route('update.invoice.deductions', ['company' => $company->id, 'modelId' => $invoice->id, 'modelType' => $modelType]),
				'invoice_date_iso' => $invoice->getInvoiceDate(),
				// Existing deductions, shaped for the Vue repeater's
				// starting rows — same 3 pivot fields the original
				// Blade repeater read (deduction_id, date, amount).
				'deductions' => $invoice->deductions->map(fn ($d) => [
					'deduction_id' => $d->pivot->deduction_id,
					'date' => $d->pivot->date,
					'amount' => $d->pivot->amount,
				])->values(),
			];
		});

        return Inertia::render('Balances/InvoiceReport', [
            'invoices' => $formattedInvoices->toArray(),
            'partnerName' => $customer->getName(),
            'partnerId' => $customer->getId(),
            'currency' => $currency,
            'moneyReceivedOrPaidText' => $moneyReceivedOrPaidText,
			'modelType'=>$modelType,
			'deductionOptions'=>$deductions->map(fn ($d) => ['id' => $d->id, 'name' => $d->getName()])->values(),
			'hasProjectNameColumn'=>$hasProjectNameColumn,
			'totalCollectionOrPaidText'=>$totalCollectionOrPaidText,
			'downPaymentSettlementUrl'=>route('view.contracts.down.payments', ['company' => $company->id, 'partnerId' => $partnerId, 'modelType' => $modelType, 'currency' => $currency]),
			// Same filters this request already resolved (partnerId/currency/
			// modelType come from the route itself), so the "Export to Excel"
			// button is a plain link — matches the pre-resolved-URL, no-Ziggy
			// convention used by every other report's export button.
			'exportUrl'=>route('export.invoice.report', ['company' => $company->id, 'partnerId' => $partnerId, 'currency' => $currency, 'modelType' => $modelType]),
			'backUrl'=>route('view.balances', ['company' => $company->id, 'modelType' => $modelType]),
        ]);
    }

    /**
     * Excel export (project-owner requested) for the Invoice Report —
     * "the colored one", matching every other Statement/Report export's
     * shared look (see AbstractStatementExport / InvoiceReportExport).
     * Deliberately re-runs the SAME query/ordering showInvoiceReport()
     * uses, rather than reusing its already-mapped $formattedInvoices,
     * so this export can pull the RAW numeric values (getInvoiceAmount(),
     * getNetBalance(), etc.) instead of the comma-formatted display
     * strings the Vue page needs — real numbers in the spreadsheet, not
     * text, so Excel's own number formatting/SUM in InvoiceReportExport
     * works correctly. Action-only columns (Adjust Due Date, Deduct,
     * Money Received/Payment) are on-screen-only and have no place in
     * a static export.
     */
    public function exportInvoiceReport(Company $company, Request $request, int $partnerId, string $currency, $modelType)
    {
        $fullClassName = ('\App\Models\\' . $modelType);
        $clientIdColumnName = $fullClassName::CLIENT_ID_COLUMN_NAME;

        $invoices = ('App\Models\\' . $modelType)::where('company_id', $company->id)
            ->where($clientIdColumnName, $partnerId)
            ->where('currency', $currency)
            ->orderByRaw('invoice_date asc , invoice_due_date desc , net_balance desc')
            ->get();
        $customer = Partner::find($partnerId);
        if (! count($invoices)) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }

        $hasProjectNameColumn = $modelType == 'CustomerInvoice' ? CustomerInvoice::hasProjectNameColumn() : false;
        $totalCollectionOrPaidText = $modelType == 'CustomerInvoice' ? __('Total Collections') : __('Total Payments');

        $headings = ['#'];
        if ($hasProjectNameColumn) {
            $headings[] = 'Project Name';
        }
        array_push($headings, 'Invoice Date', 'Invoice Number', 'Invoice Amount', 'Withhold Amount', 'VAT Amount',
            'Total Deductions', $totalCollectionOrPaidText, 'Invoice Due Date', 'Net Balance', 'Status', 'Aging');

        $rows = $invoices->values()->map(function ($invoice, $index) use ($hasProjectNameColumn, $totalCollectionOrPaidText) {
            $line = ['#' => $index + 1];
            if ($hasProjectNameColumn) {
                $line['Project Name'] = $invoice->getProjectName();
            }
            $line['Invoice Date'] = $invoice->getInvoiceDateFormatted();
            $line['Invoice Number'] = $invoice->getInvoiceNumber();
            $line['Invoice Amount'] = $invoice->getInvoiceAmount();
            $line['Withhold Amount'] = $invoice->getTotalWithholdAmount();
            $line['VAT Amount'] = (float) $invoice->getVatAmount();
            $line['Total Deductions'] = $invoice->getTotalDeduction();
            $line[$totalCollectionOrPaidText] = $invoice->getTotalCollectedOrPaid();
            $line['Invoice Due Date'] = $invoice->getDueDateFormatted();
            $line['Net Balance'] = (float) $invoice->getNetBalance();
            $line['Status'] = $invoice->getStatusFormatted();
            $line['Aging'] = $invoice->getAging();

            return $line;
        });

        $fileNameParts = ['Invoice-Report', $customer->getName(), $currency];
        $fileName = preg_replace('/[^A-Za-z0-9\-]+/', '-', implode('-', $fileNameParts)).'.xlsx';

        return (new InvoiceReportExport($headings, $rows, $totalCollectionOrPaidText))->download($fileName);
    }

	
	public function viewLGLCDashboard(Company $company, Request $request)
    {
			// start fully SecuredOverdraft
			$financialInstitutions = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get();
			$charts =  [];
			$tablesData = [];
			
			// ⚠️ Real bug fixed here: this used to gate on $request->ajax(),
			// but Inertia's own requests satisfy that exact same check
			// (Inertia sets the same X-Requested-With: XMLHttpRequest
			// header jQuery's .ajax() traditionally did — same root cause
			// as bugs #19/#22/#38 in the Roadmap). With lgType/lcType
			// absent (the normal case for a plain page visit), that meant
			// LgTypes::only((array) null) — collapsing $lgTypes to
			// effectively nothing — fired on every single Inertia visit to
			// this page, not just the old jQuery partial-refresh calls it
			// was written for. Fixed by checking whether a real filter
			// value was actually supplied ($request->filled()), matching
			// the same lesson already learned once on the Aging filters
			// (Roadmap bug #32: ->has() isn't ->filled()).
			$lgTypes = LgTypes::getAll() ;
			$lgTypes = $request->filled('lgType') ?  LgTypes::only((array) $request->get('lgType'))  : $lgTypes ; 
			
			$lcTypes = LcTypes::getAll() ;
			$lcTypes = $request->filled('lcType') ?  LcTypes::only((array) $request->get('lcType'))  : $lcTypes ; 
			
			$typesForLgAndLc = [
				'lg'=>$lgTypes,
				'lc'=>$lcTypes
			];
		$financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get();
		/**
		 * FIX (per audit, 2026-08-13): the per-bank loop below used to
		 * call FinancialInstitution::find($id) fresh on every single
		 * iteration — a classic N+1: one extra query per bank, every
		 * currency, every LG/LC pass. $financialInstitutionBanks just
		 * above already loads every bank for this company in one
		 * query; keying it by id here means the loop can just look the
		 * bank up in memory instead of asking the database again for
		 * data it already has.
		 */
		$financialInstitutionsById = $financialInstitutionBanks->keyBy('id');
		
	
		$currentDate = now()->format('Y-m-d') ;
        $date = $request->get('date');
		$date = $date ? HDate::formatDateFromDatePicker($date) : $currentDate;
		// $year = explode('-',$date)[0];
		$date = Carbon::make($date)->format('Y-m-d');
		/**
		 * FIX (per request, 2026-08-18): this page used to reuse
		 * getCurrenciesForSuppliersAndCustomers() — the SAME generic
		 * currency list Cash Status uses, sourced from this company's
		 * Branches and FinancialInstitutionAccounts, with nothing to
		 * do with LG or LC at all. That meant every currency the
		 * company has a bank account or branch in showed up as a
		 * clickable pill here, even ones with zero LG/LC facilities —
		 * clicking one of those correctly rendered an empty page (no
		 * facility, so nothing to show), but with no way for the user
		 * to tell "empty because broken" from "empty because there's
		 * genuinely nothing here" — hence this dashboard's own pill
		 * list now, scoped to only currencies where this company
		 * actually has at least one Letter of Guarantee or Letter of
		 * Credit facility on record (regardless of whether that
		 * facility is currently active/expired — a currency the
		 * company has ever used for LG/LC is still worth being able to
		 * switch to and inspect).
		 */
		$allCurrencies = array_values(array_unique(array_merge(
			DB::table('letter_of_guarantee_facilities')->where('company_id', $company->id)->whereNotNull('currency')->distinct()->pluck('currency')->toArray(),
			DB::table('letter_of_credit_facilities')->where('company_id', $company->id)->whereNotNull('currency')->distinct()->pluck('currency')->toArray()
		)));
	
		$details = [];
		
		$selectedFinancialInstitutionBankIds = [];
		
        /**
		 * ⚠️ REAL BUG FIXED HERE (per audit, 2026-08-18): this read
		 * $request->get('currencies', $allCurrencies) — the EXACT SAME
		 * request param $currenciesToCompute below narrows on. The
		 * comment just below always claimed "$selectedCurrencies itself
		 * is left completely unchanged" (i.e. always the full currency
		 * list, since it's what the currency PILL buttons render from),
		 * but the code never actually did that: every time
		 * LGLCStatus.vue's switchCurrency() asked the server to compute
		 * just one currency (via ?currencies[]=USD), this line echoed
		 * that SAME narrowed list straight back as 'selectedCurrencies'
		 * — so the very next render collapsed the whole pill row down
		 * to that one currency, permanently hiding every other pill
		 * (nothing in the UI could ever ask for a currency whose button
		 * no longer existed). Combined with activeCurrency needing to
		 * match whatever currency actually got computed, this is what
		 * made the page look entirely empty/stuck after clicking a
		 * currency. $selectedCurrencies must always be the FULL
		 * currency list — only $currenciesToCompute, just below, is
		 * meant to narrow based on the request.
		 */
		$selectedCurrencies = $allCurrencies ;
		/**
		 * FIX (per audit, 2026-08-13): same reasoning as the Cash
		 * Forecast dashboard fix — this used to default to EVERY
		 * currency the company has ever configured, meaning the whole
		 * nested LG/LC × bank × facility computation below (already a
		 * lot of individual queries for just ONE currency — see the
		 * FinancialInstitution::find() fix a few lines down) ran once
		 * per currency, every visit, regardless of which one anyone
		 * was actually looking at. $selectedCurrencies itself is left
		 * completely unchanged (see fix just above) — it's also what
		 * the currency PILL buttons render from, further down, so
		 * narrowing it directly would have hidden currencies as
		 * options entirely. This new, separate, narrower list is what
		 * actually drives the computation loop instead, defaulting to
		 * just the company's main currency (mirroring the exact same
		 * default LGLCStatus.vue's own activeCurrency already falls
		 * back to, so the one currency computed by default is always
		 * the one shown by default) — clicking a not-yet-loaded
		 * currency pill (see LGLCStatus.vue's switchCurrency())
		 * requests exactly that one currency instead.
		 */
		$currenciesToCompute = $request->filled('currencies')
			? (array) $request->get('currencies')
			: array_values(array_filter([in_array($company->getMainFunctionalCurrency(), $allCurrencies, true) ? $company->getMainFunctionalCurrency() : ($allCurrencies[0] ?? null)]));
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
			foreach ($currenciesToCompute as $currencyName) {
				
				
				$financialInstitutionBankIds = [
					// 'lg'=>array_keys($company->letterOfGuaranteeIssuances->where('status','!=','cancelled')->where('lg_currency',$currencyName)->load('financialInstitutionBank')->pluck('financialInstitutionBank.bank.name_en','financialInstitutionBank.id')->toArray()),
					'lg'=>array_keys($company->letterOfGuaranteeFacilities->where('currency',$currencyName)->pluck('financialInstitution.bank.name_en','financialInstitution.id')->toArray()),
					'lc'=>array_keys($company->letterOfCreditFacilities->where('currency',$currencyName)->pluck('financialInstitution.bank.name_en','financialInstitution.id')->toArray()),
					// 'lg'=>array_keys($company->letterOfGuaranteeIssuances->where('status','!=','cancelled')->where('lg_currency',$currencyName)->load('financialInstitutionBank')->pluck('financialInstitutionBank.bank.name_en','financialInstitutionBank.id')->toArray()),
					// 'lc'=>array_keys($company->letterOfCreditIssuances->where('status','!=','cancelled')->load('financialInstitutionBank')->pluck('financialInstitutionBank.bank.name_en','financialInstitutionBank.id')->toArray()),
				][$currentLgOrLcType];
					
				// Same fix as the lgType/lcType gate above — $request->ajax()
				// is true on every Inertia visit now, not just the old
				// jQuery partial-refresh call this was written for.
				$selectedFinancialInstitutionBankIds = $request->filled('financialInstitutionId') && $request->get('financialInstitutionId') > 0 ? (array)$request->get('financialInstitutionId') : $financialInstitutionBankIds; 
				
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
					
					$currentFinancialInstitution = $financialInstitutionsById->get($financialInstitutionBankId);
					/**
					 * ⚠️ REAL BUG FIXED HERE (per audit, 2026-08-18):
					 * $financialInstitutionsById is keyed from
					 * $financialInstitutionBanks, which is scoped to
					 * ->onlyBanks() (type = 'bank') only. But
					 * $financialInstitutionBankId here comes straight
					 * from this currency's LG/LC facilities themselves
					 * (see $financialInstitutionBankIds just above),
					 * with no such type filter. If any facility for the
					 * currency being computed is linked to a financial
					 * institution that ISN'T typed 'bank', the lookup
					 * above returns null — and every call below that
					 * dereferenced it (->getName()) threw a fatal
					 * "Call to a member function getName() on null",
					 * taking the ENTIRE request down with a 500. Since
					 * this only fires for whichever currency happens to
					 * have such a facility, it looked exactly like "EGP
					 * works fine, clicking USD blanks the page" — the
					 * failed Inertia visit never reached
					 * Inertia::render(), so props (and activeCurrency,
					 * client-side) never updated, leaving the OLD
					 * currency's pill stuck "active" over an empty
					 * page. Skipping institutions the loop can't
					 * resolve a bank record for — rather than crashing
					 * — keeps every other (valid) currency's data
					 * intact and simply omits the unresolvable one from
					 * this currency's charts/table, which a broken
					 * financial-institution link should do anyway
					 * rather than take the whole dashboard down.
					 */
					if (! $currentFinancialInstitution) {
						continue;
					}
					$statementTableFullClassName::getDashboardOutstandingPerFinancialInstitutionFormattedData($charts,$company,$currencyName , $date ,$financialInstitutionBankId,$currentFinancialInstitution->getName(),$source,$lgOrLcTypes);
						
					$lastLetterOfGuaranteeOrCreditFacilities = DB::table($letterOfFacilityTableName)
					->where($letterOfFacilityTableName.'.company_id', $company->id)
					->where('currency', $currencyName)
					->where('contract_end_date', '>=', $date)
					->where($letterOfFacilityTableName.'.financial_institution_id', '=', $financialInstitutionBankId)
					->orderBy('contract_end_date', 'desc')
					->get();

					// ⚠️ Bug fix (LC only — see the 'lg' branch after this
					// per-institution loop for how the LG table is built
					// instead): the table used to be built from the SAME
					// contract_end_date >= $date query as the KPI totals
					// above, which silently dropped any facility whose
					// contract had already expired. Per explicit product
					// decision, the LC table lists BOTH Running and Expired
					// facilities (with a Status column to tell them apart)
					// — the KPI cards above (Limit/Room/Outstanding/Cash
					// Cover totals) intentionally keep using only the
					// active-facility query above, unchanged.
					if ($currentLgOrLcType === 'lc') {
						$allLetterOfGuaranteeOrCreditFacilitiesForTable = DB::table($letterOfFacilityTableName)
						->where($letterOfFacilityTableName.'.company_id', $company->id)
						->where('currency', $currencyName)
						->where($letterOfFacilityTableName.'.financial_institution_id', '=', $financialInstitutionBankId)
						->orderBy('contract_end_date', 'desc')
						->get();
						foreach($allLetterOfGuaranteeOrCreditFacilitiesForTable as $currentFacilityForTable){
							$currentFacilityStatus = $currentFacilityForTable->contract_end_date >= $date ? __('Running') : __('Expired');
							foreach($lgOrLcTypes as $currentLgType => $currentLgTitle){
								$statementTableFullClassName::getDashboardOutstandingTableFormattedData($tablesData,$company,$currencyName , $date ,$financialInstitutionBankId,$currentLgType,$currentFinancialInstitution->getName(),$currentFacilityForTable,$source,$currentFacilityStatus);
							}
							
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

				// "Limit" KPI card breakdown — clicking it opens a per-bank
				// table (Limit / Outstanding / Room / Cash Cover), one row
				// per bank, plus a totals row. Reuses the exact same
				// per-facility figures already computed just above into
				// $details (active facilities only, LG-Facility-source-only
				// outstanding via getTotalOutstandingBalanceForAllTypes) —
				// just grouped by bank name instead of listed per facility,
				// so the totals here always foot to the same Limit/
				// Outstanding/Room/Cash Cover KPI values shown on the cards.
				$limitDetailsByBank = [];
				foreach ($details[$currencyName][$currentLgOrLcType] ?? [] as $facilityRow) {
					$bankName = $facilityRow['financial_institution_name'];
					if (! isset($limitDetailsByBank[$bankName])) {
						$limitDetailsByBank[$bankName] = [
							'financial_institution_name' => $bankName,
							'limit' => 0.0,
							'outstanding' => 0.0,
							'room' => 0.0,
							'cash_cover' => 0.0,
						];
					}
					$limitDetailsByBank[$bankName]['limit'] += $facilityRow['limit'];
					$limitDetailsByBank[$bankName]['outstanding'] += $facilityRow['outstanding_balance'];
					$limitDetailsByBank[$bankName]['room'] += $facilityRow['room'];
					$limitDetailsByBank[$bankName]['cash_cover'] += $facilityRow['cash_cover'];
				}
				$reports[$currentLgOrLcType][$currencyName]['limitDetailsByBank'] = array_values($limitDetailsByBank);

				// LG table — per explicit product decision, this is built
				// entirely differently from LC's: one row per (Bank, LG
				// Type, Source, Status), listing every individual LG
				// issuance (Running or Expired, never Cancelled) grouped
				// together with a count, rather than one row per facility.
				// 'Outstanding' here means the LGs' own face amount
				// (lg_amount) — NOT the shared facility ledger balance used
				// by the KPI cards above (getTotalOutstandingBalanceForAllTypes) —
				// per explicit clarification, since lg_amount is a real,
				// per-issuance figure the ledger can't be split into by
				// individual LG status. Cash Cover is likewise each LG's
				// own cash_cover_amount, summed per group. Status reuses
				// LetterOfGuaranteeIssuance::getStatus() — the exact same
				// cancelled > expired (renewal_date passed) > running logic
				// already used on the LG Index page — so this table's
				// Running/Expired split matches what the person sees there.
				if ($currentLgOrLcType === 'lg') {
					// ⚠️ Bug fix: FinancialInstitution has no 'name_en' column
					// of its own — the real bank name lives on the related
					// Bank model via getName()/getBankName(). Plucking
					// 'name_en' straight off financial_institutions rows
					// returned null for every row, so this column showed
					// blank for every LG. Building the lookup from getName()
					// instead, same as CashDashboardService's bankNameResolver.
					$financialInstitutionNamesById = $financialInstitutionBanks->mapWithKeys(fn ($fi) => [$fi->id => $fi->getName()]);
					$lgGroups = [];
					LetterOfGuaranteeIssuance::where('company_id', $company->id)
						->where('lg_currency', $currencyName)
						->whereIn('financial_institution_id', $selectedFinancialInstitutionBankIds)
						->where('status', '!=', LetterOfGuaranteeIssuance::CANCELLED)
						->get()
						->each(function ($lg) use (&$lgGroups, $financialInstitutionNamesById, $lgTypes) {
							$status = $lg->getStatus();
							if ($status === LetterOfGuaranteeIssuance::CANCELLED) {
								return;
							}
							$groupKey = $lg->financial_institution_id.'|'.$lg->lg_type.'|'.$lg->getSource().'|'.$status;
							if (! isset($lgGroups[$groupKey])) {
								$lgGroups[$groupKey] = [
									'financial_institution_name' => $financialInstitutionNamesById->get($lg->financial_institution_id, __('N/A')),
									'type' => $lgTypes[$lg->lg_type] ?? $lg->lg_type,
									'source' => LetterOfGuaranteeIssuance::lgSources()[$lg->getSource()] ?? $lg->getSource(),
									'status' => ucfirst($status),
									'count' => 0,
									'outstanding' => 0.0,
									'cash_cover' => 0.0,
								];
							}
							$lgGroups[$groupKey]['count']++;
							$lgGroups[$groupKey]['outstanding'] += (float) $lg->lg_amount;
							$lgGroups[$groupKey]['cash_cover'] += (float) $lg->cash_cover_amount;
						});
					$tablesData['lg_outstanding_for_table'][$currencyName] = array_values($lgGroups);
				}
			}
			
			
		}
        
		// ⚠️ Real bug fixed here — this exact branch is what caused the
		// reported "All Inertia requests must receive a valid Inertia
		// response, however a plain JSON response was received" error.
		// $request->ajax() is true on EVERY Inertia visit (Inertia sends
		// the same X-Requested-With: XMLHttpRequest header jQuery's
		// .ajax() used), not just the old jQuery partial-refresh calls
		// this branch was written for — so every real visit to this page
		// was hitting this branch and returning raw JSON instead of ever
		// reaching Inertia::render() below. Same root cause as bugs
		// #19/#22/#38 in the Roadmap (a widespread pattern in this
		// codebase), now found here too. Fixed by checking the request's
		// own X-Inertia header — set only by genuine Inertia visits —
		// so a real legacy ajax caller (if one is ever added back) would
		// still get JSON, but the Vue page itself never does.
		if($request->ajax() && ! $request->header('X-Inertia')){
			
			return response()->json([
				'tablesData'=>$tablesData ,
				'charts'=>$charts
			]);
		}

		// ✅ MIGRATED to Inertia/Vue — renders
		// resources/js/Pages/Dashboard/LGLCStatus.vue. All the
		// aggregation above (reports/details/tablesData/charts) is
		// UNCHANGED; only the final response and the two Eloquent
		// bank collections (Inertia can't call ->getName() client
		// side) were touched. The $request->ajax() branch above is
		// left in place but is no longer hit from the new Vue page,
		// which uses full Inertia visits for its filters instead —
		// same "old code left registered, just unlinked" pattern used
		// elsewhere in this migration.
		$bankList = fn ($collection) => collect($collection)->map(fn ($bank) => [
			'id' => $bank->id,
			'name' => $bank->getName(),
		])->values()->all();

        return Inertia::render('Dashboard/LGLCStatus', [
            'company' => ['id' => $company->id],
            'mainFunctionalCurrency' => $company->getMainFunctionalCurrency(),
            'financialInstitutionBanks' => $bankList($financialInstitutionBanks),
            'reports' => $reports,
            'selectedCurrencies' => array_values($selectedCurrencies),
			'allCurrencies'=>array_values($allCurrencies),
            'selectedFinancialInstitutionsIds' => $selectedFinancialInstitutionBankIds,
			'details'=>$details,
			'charts'=>$charts,
			'lgTypes'=>LgTypes::getAll(),
			'lcTypes'=>LcTypes::getAll(),
			'lgSources'=>LetterOfGuaranteeIssuance::lgSources(),
			'lcSources'=>LetterOfCreditIssuance::lcSources(),
			'tablesData'=>$tablesData,
			'financialInstitutions'=> $bankList($financialInstitutions),
			'canShowDashboardPerCurrency'=>$canShowDashboardPerCurrency,
			'date'=>$date,
			'selectedLgSource'=>$source,
			'filterUrl'=>route('view.lglc.dashboard', ['company' => $company->id]),
			'dashboardTabUrls' => [
				'cash' => route('view.customer.invoice.dashboard.cash', ['company' => $company->id]),
				'contracts' => route('view.contracts.dashboard', ['company' => $company->id]),
				'lglc' => route('view.lglc.dashboard', ['company' => $company->id]),
				'forecast' => route('view.customer.invoice.dashboard.forecast', ['company' => $company->id]),
			],
        ]);
    }


    /**
     * Customer/Supplier Statement Report — a ledger-style report:
     * beginning balance, then every invoice/collection/deduction/
     * down-payment/factoring movement in the chosen date range.
     * Renders resources/js/Pages/Balances/Statement.vue.
     *
     * All the actual ledger math (formatForStatementReport/appendBalances,
     * from HasBalances) is UNCHANGED — only the two `view(...)` returns
     * were rewritten to Inertia::render(), plus a pre-resolved
     * $filterUrl for the Vue page's own name/date filter form to
     * submit against (no Ziggy — see Style Guide §8).
     *
     * $returnResult=true is an internal-use mode (called directly by
     * CollectionEffectivenessIndexController, not through this route)
     * — that early-return branch is untouched and never reaches the
     * Inertia::render() below.
     */
    public function showInvoiceStatementReport(Company $company, Request $request, int $partnerId, string $currency, string $modelType , ?string $startDate = null , ?string $endDate = null , bool $returnResult = false)
    {
		$showAllPartner = $request->boolean('all_partners');
		$partnerId = $request->has('partner_id') ? $request->get('partner_id') : $partnerId;
        $fullClassName = ('\App\Models\\' . $modelType) ;
		// $isCustomer/$isSupplier used to also be required on the
		// single-partner branch below — removed. A partner who is
		// BOTH a customer and a supplier (is_customer and is_supplier
		// are independent flags, not mutually exclusive — perfectly
		// normal for a company you both buy from and sell to) would
		// fail that extra check and silently vanish from $partners,
		// so the dropdown had no option to select even though
		// $partnerId itself was correct — exactly the "selector is
		// sometimes empty" bug. The whereHas($modelType, ...) clause
		// below already correctly scopes to "has an invoice of this
		// type in this currency," which is the actually meaningful
		// filter; the type-flag check was redundant on top of it.
		$partners = Partner::when($partnerId && !$showAllPartner ,function(Builder $builder) use ($partnerId){
			$builder->whereIn('id',(array) $partnerId );
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
		$filterUrl = route('view.invoice.statement.report', [
			'company' => $company->id, 'partnerId' => $partnerId, 'currency' => $currency, 'modelType' => $modelType,
		]);
		$backUrl = route('view.balances', ['company' => $company->id, 'modelType' => $modelType]);
		if(!$partner){
			return Inertia::render('Balances/Statement', [
				'invoicesWithItsReceivedMoney' => [],
				'partnerName' => null,
				'partnerId' => $partnerId,
				'currency' => $currency,
				'startDate' => $startDate,
				'endDate' => $endDate,
				'customerStatementText' => $customerStatementText,
				'partners'=>$partners,
				'showAllPartner'=>$showAllPartner,
				'filterUrl'=>$filterUrl,
				'exportUrl'=>route('export.invoice.statement.report', [
					'company' => $company->id, 'partnerId' => $partnerId, 'currency' => $currency, 'modelType' => $modelType,
				]),
				'backUrl'=>$backUrl,
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
		
        return Inertia::render('Balances/Statement', [
            'invoicesWithItsReceivedMoney' => $invoicesWithItsReceivedMoney,
            'partnerName' => $partnerName,
            'partnerId' => $partnerId,
            'currency' => $currency,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'customerStatementText' => $customerStatementText,
			'partners'=>$partners,
			'showAllPartner'=>$showAllPartner,
			'filterUrl'=>$filterUrl,
			'exportUrl'=>route('export.invoice.statement.report', [
				'company' => $company->id, 'partnerId' => $partnerId, 'currency' => $currency, 'modelType' => $modelType,
			]),
			'backUrl'=>$backUrl,
        ]);
    }

	/**
	 * Excel export (project-owner requested) for the Statement Report —
	 * "the colored one", same shared look as every other Statement
	 * export (see AbstractStatementExport / CustomerSupplierStatementExport).
	 * Reuses formatForStatementReport() — the exact same source data
	 * showInvoiceStatementReport() renders on screen, so the workbook can
	 * never drift from what's displayed — plus start_date/end_date/
	 * partner_id/all_partners straight from the request, matching the
	 * filter form's own submit shape (Statement.vue's submitFilter()).
	 *
	 * The running "End Balance" column is recomputed here with the exact
	 * same formula as Statement.vue's runningBalances (row 0 = its own
	 * end_balance, every row after = previous running balance + debit -
	 * credit) — that calculation only ever existed client-side (in the
	 * Blade original too, per the Vue docblock), so a real number for
	 * each row has to be computed once here for the spreadsheet.
	 */
	public function exportInvoiceStatementReport(Company $company, Request $request, int $partnerId, string $currency, string $modelType)
	{
		$partnerId = $request->has('partner_id') ? $request->get('partner_id') : $partnerId;
		$fullClassName = ('\App\Models\\' . $modelType);
		$clientIdColumnName = $fullClassName::CLIENT_ID_COLUMN_NAME;
		$startDate = $request->get('start_date', now()->subMonths(12)->format('Y-m-d'));
		$endDate = $request->get('end_date', now()->format('Y-m-d'));

		$invoices = ('\App\Models\\' . $modelType)::getInvoicesForInvoiceStartAndEndDate($clientIdColumnName, $partnerId, $company, $currency, $startDate, $endDate);
		$partner = Partner::find($partnerId);
		if (! $partner) {
			return redirect()->back()->with('fail', __('No Data Found'));
		}
		$invoicesWithItsReceivedMoney = $this->formatForStatementReport($invoices, $partnerId, $startDate, $endDate, $currency, $modelType);
		if (count($invoicesWithItsReceivedMoney) < 1) {
			return redirect()->back()->with('fail', __('No Data Found'));
		}

		$headings = ['#', 'Date', 'Document Type', 'Document No', 'Debit', 'Credit', 'End Balance', 'Comment'];
		$runningBalance = null;
		$rows = collect($invoicesWithItsReceivedMoney)->values()->map(function ($item, $index) use (&$runningBalance) {
			$item = (array) $item;
			$runningBalance = $index === 0
				? (float) ($item['end_balance'] ?? 0)
				: $runningBalance + (float) ($item['debit'] ?? 0) - (float) ($item['credit'] ?? 0);

			return [
				'#' => $index + 1,
				'Date' => $item['date'] ?? null,
				'Document Type' => $item['document_type'] ?? null,
				'Document No' => $item['document_no'] ?? null,
				'Debit' => (float) ($item['debit'] ?? 0),
				'Credit' => (float) ($item['credit'] ?? 0),
				'End Balance' => $runningBalance,
				'Comment' => $item['comment'] ?? null,
			];
		});

		$fileNameParts = ['Statement', $partner->getName(), $currency];
		$fileName = preg_replace('/[^A-Za-z0-9\-]+/', '-', implode('-', $fileNameParts)).'.xlsx';

		return (new CustomerSupplierStatementExport($headings, $rows))->download($fileName);
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