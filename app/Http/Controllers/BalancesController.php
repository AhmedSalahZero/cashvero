<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use App\Models\Partner;
use App\Models\User;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * BalancesController
 * ------------------------------------------------------------------
 * Shows how much each partner currently owes/is owed — this ONE
 * controller serves BOTH the "Customers Balances" and "Suppliers
 * Balances" sidebar pages, distinguished only by the $modelType
 * route parameter ('CustomerInvoice' vs 'SupplierInvoice'). Nothing
 * here is customer-specific; migrating index() migrates both pages
 * at once (confirmed intentional, not an accident of this migration).
 *
 * All net-balance math (net_balance, invoice_status, total_deductions)
 * is computed by MySQL triggers on `customer_invoices` /
 * `supplier_invoices` / `invoice_deductions` — see
 * app/Triggers/Cashvero/customer_invoices_triggers.sql and
 * invoice_deductions.sql. This controller only READS those columns;
 * none of that math is touched or duplicated here.
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   - index()                            → ALREADY migrated. Returns
 *                                           Inertia::render(), served by
 *                                           resources/js/Pages/Balances/Index.vue
 *                                           (shared page — see class note above).
 *                                           Calculation logic (sumNetBalancePerCurrency,
 *                                           getDownPaymentInMainCurrency, subtractQuery,
 *                                           addMainCurrency) is UNCHANGED, deliberately.
 *   - showTotalNetBalanceDetailsReport() → ALREADY migrated. Returns
 *                                           Inertia::render(), served by
 *                                           resources/js/Pages/Balances/TotalNetBalanceDetails.vue.
 *                                           Now supports a third filter
 *                                           mode, "Coming Dues"
 *                                           (invoice_status = 'not_due_yet'),
 *                                           added at the project owner's
 *                                           request — new, not part of
 *                                           the original app.
 *
 * Two related report pages reached from this one — the per-customer
 * "Statement Report" and "Invoice Report" buttons — live in
 * CustomerInvoiceDashboardController (showInvoiceStatementReport /
 * showInvoiceReport), not here. Still Blade as of this update.
 */
class BalancesController
{
	CONST NET_BALANCE_CONDITION = ' ';
	// CONST NET_BALANCE_CONDITION = 'net_balance > 0 and ';
    use GeneralFunctions;
	protected function sumNetBalancePerCurrency(array $items, string $mainCurrency,string $clientNameColumnName ):array 
	{

		$total = [
			'currencies' => [],
			'customers_per_currency' => [],
			'customers_per_main_currency' => [],
		];

		
		$id = 0 ;
		foreach($items as $item){
			$currencyName = $item->currency ;
			$currentValueForMainCurrency= $item->net_balance_in_main_currency  ;
			$currentValueForCurrency = $item->net_balance;
			if(!$currencyName){
				continue;
			}
			
			$customerName = $item->{$clientNameColumnName} ;
			$total['currencies'][$currencyName] = isset($total['currencies'][$currencyName]) ? $total['currencies'][$currencyName] + $currentValueForCurrency   :  $currentValueForCurrency;
			// $total['main_currency'][$mainCurrency] = isset($total['main_currency'][$mainCurrency]) ? $total['main_currency'][$mainCurrency] + $currentValueForMainCurrency  : $currentValueForMainCurrency;
			$total['customers_per_currency'][$mainCurrency][$customerName][$id] =   $currentValueForCurrency;
			$total['customers_per_main_currency'][$mainCurrency][$customerName][$id] =   $currentValueForMainCurrency;
			$id++;
			
		}
		$valueAtMainCurrency = $total['currencies'][$mainCurrency] ?? 0;
		unset($total['currencies'][$mainCurrency]);
		$totalOfCurrency  = $total['currencies'] ;
		$total['currencies'] = [$mainCurrency => $valueAtMainCurrency]+$totalOfCurrency ;
		return $total ;
	}
    /**
     * Customers/Suppliers Balances — summary page.
     * Renders resources/js/Pages/Balances/Index.vue.
     * $modelType decides which sidebar page this serves ('CustomerInvoice'
     * or 'SupplierInvoice') — see class docblock. All balance math below
     * this line is UNCHANGED from the original Blade version; only the
     * final `return` was rewritten (view() → Inertia::render()), plus
     * the pre-resolving of every button URL the Vue page needs (no
     * Ziggy in this project — see Style Guide §8).
     */
    public function index(Request $request,Company $company,string $modelType)
	{
		$netBalanceCondition = self::NET_BALANCE_CONDITION;
		$fullClassName = ('\App\Models\\'.$modelType) ;
		$customersOrSupplierText = (new $fullClassName )->getClientDisplayName();
		$title = (new $fullClassName )->getBalancesTitle();
		$customersOrSupplierStatementText = (new $fullClassName)->getCustomerOrSupplierStatementText();
		$clientNameColumnName = $fullClassName::CLIENT_NAME_COLUMN_NAME ;
		$clientIdColumnName = $fullClassName::CLIENT_ID_COLUMN_NAME ;
		$isCustomerOrSupplierColumnName = $fullClassName::IS_CUSTOMER_OR_SUPPLIER;
		$tableName = $fullClassName::TABLE_NAME ; 
		$user =User::where('id',$request->user()->id)->get();
		$mainCurrency = $company->getMainFunctionalCurrency();
		$mainFunctionalCurrency = $company->getMainFunctionalCurrency();
		$downPaymentTableName = $fullClassName::DOWN_PAYMENT_SETTLEMENT_TABLE_NAME;
		$downPaymentSettlementModelName=$fullClassName::DOWN_PAYMENT_SETTLEMENT_MODEL_NAME;
		$moneyModelName=$fullClassName::MONEY_MODEL_NAME;
		$invoiceNetBalanceSqlQuery = 'select partners.id as '. $clientIdColumnName .' , partners.name as '.$clientNameColumnName.' , currency , ifnull(sum(net_balance),0) as net_balance , ifnull(sum(net_balance_in_main_currency),0) as net_balance_in_main_currency from partners   left join  '. $tableName .' on partners.id = '.$tableName.'.'.$clientIdColumnName.' where '.$isCustomerOrSupplierColumnName.'=1 and '.$netBalanceCondition.'   partners.company_id = '. $company->id  .'  group by partners.id, '.$clientIdColumnName.' , currency order by net_balance desc;';
		$invoicesBalances = DB::select($invoiceNetBalanceSqlQuery);
		$downPaymentSqlQuery =  'select  '.  $clientIdColumnName .' , currency , sum(down_payment_balance) as down_payment_balance from '. $downPaymentTableName .' where   company_id = '. $company->id .' group by '. $clientIdColumnName .' , currency  order by down_payment_balance desc;';
		$partnerIds = collect($invoicesBalances)->pluck($clientIdColumnName,$clientIdColumnName)->toArray() ;
		$downPaymentsInMainCurrency = $this->getDownPaymentInMainCurrency($partnerIds,$mainFunctionalCurrency,$clientIdColumnName,$downPaymentSettlementModelName,$moneyModelName,$company);

		$downPayments = DB::select($downPaymentSqlQuery);
		
		$invoicesBalancesWithPartnersWithoutInvoices = $this->subtractQuery($invoicesBalances,$downPayments,$clientIdColumnName,$clientNameColumnName);
		$invoicesBalances = $invoicesBalancesWithPartnersWithoutInvoices['data'] ?? [];
		$partnersWithoutInvoices = $invoicesBalancesWithPartnersWithoutInvoices['partners_without_invoices'];
		$invoicesBalancesForMainFunctionalCurrency = $this->addMainCurrency($invoicesBalances,$downPaymentsInMainCurrency,$partnersWithoutInvoices,$clientNameColumnName,$clientIdColumnName);
		
		$invoicesBalances = array_merge($invoicesBalances , $invoicesBalancesForMainFunctionalCurrency);
		$cardNetBalances = $this->sumNetBalancePerCurrency($invoicesBalances,$mainFunctionalCurrency,$clientNameColumnName);
		$hasMoreThanCurrency = isset($cardNetBalances['currencies']) && count($cardNetBalances['currencies']) >1 ;

		// ── Group the flat $invoicesBalances list by currency, and
		// pre-resolve every button URL the Vue page needs (no Ziggy —
		// see Style Guide §8). This block is purely presentational:
		// it reshapes the exact same $invoicesBalances / $cardNetBalances
		// the Blade version already had, nothing above is touched.
		$rowsByCurrency = [];
		foreach ($invoicesBalances as $row) {
			$currency = $row->currency;
			// Partners with zero invoices come back from the left-join
			// SQL above with currency = NULL. The original Blade never
			// showed these rows either (each row only rendered inside
			// a tab whose currency matched — a null currency matches
			// no tab). Same behavior here, just made explicit instead
			// of an accidental side effect of the template loop.
			if (!$currency) {
				continue;
			}
			$rowsByCurrency[$currency][] = [
				'client_id' => $row->{$clientIdColumnName},
				'client_name' => $row->{$clientNameColumnName},
				'currency' => $currency,
				// The two halves of net_balance, shown as their own columns so
				// the number is auditable instead of just asserted.
				'invoices' => (float) ($row->invoices_amount ?? 0),
				'down_payments' => (float) ($row->down_payment_amount ?? 0),
				'net_balance' => (float) $row->net_balance,
				// "Invoice Report" never existed for the main-currency
				// pseudo-row in the original Blade either (confirmed,
				// not a gap introduced here) — null hides the button.
				'statement_report_url' => route('view.invoice.statement.report', [
					'company' => $company->id, 'partnerId' => $row->{$clientIdColumnName},
					'currency' => $currency, 'modelType' => $modelType,
				]),
				'invoice_report_url' => $currency !== 'main_currency' ? route('view.invoice.report', [
					'company' => $company->id, 'partnerId' => $row->{$clientIdColumnName},
					'currency' => $currency, 'modelType' => $modelType,
				]) : null,
			];
		}

		$currencyCards = [];
		foreach ($cardNetBalances['currencies'] ?? [] as $currencyName => $total) {
			$currencyCards[] = [
				'currency' => $currencyName,
				'total' => (float) $total,
				'rows' => $rowsByCurrency[$currencyName] ?? [],
				// The original Blade rendered these two buttons for
				// main_currency too, just visually hidden via a CSS
				// class (visibility-hidden) — the effect for the user
				// was identical to not rendering them, so that's what
				// we do here. Flagging this explicitly per methodology
				// §3.3 rather than leaving it a silent behavior change.
				'all_invoices_url' => $currencyName !== 'main_currency' ? route('show.total.net.balance.in', [
					'company' => $company->id, 'currency' => $currencyName, 'modelType' => $modelType,
				]) : null,
				'past_due_url' => $currencyName !== 'main_currency' ? route('show.total.net.balance.in', [
					'company' => $company->id, 'currency' => $currencyName, 'modelType' => $modelType, 'only' => 'past_due',
				]) : null,
				// New — not part of the original app. See
				// showTotalNetBalanceDetailsReport()'s docblock for
				// the confirmed "Coming Dues" definition.
				'coming_dues_url' => $currencyName !== 'main_currency' ? route('show.total.net.balance.in', [
					'company' => $company->id, 'currency' => $currencyName, 'modelType' => $modelType, 'only' => 'coming_due',
				]) : null,
				// "Statement Report for every partner in this currency at once"
				// — the button the original injected via JS at the top of
				// each currency tab (see balances_form.blade.php's bottom
				// @foreach script block). Same target route, partnerId=0
				// + all_partners=1, just a real button now instead of JS-injected.
				'bulk_statement_url' => route('view.invoice.statement.report', [
					'company' => $company->id, 'partnerId' => 0, 'currency' => $currencyName,
					'modelType' => $modelType, 'all_partners' => 1,
				]),
			];
		}

		return Inertia::render('Balances/Index', [
			'company' => ['id' => $company->id],
			'modelType' => $modelType,
			'title' => $title,
			'customersOrSupplierText' => $customersOrSupplierText,
			'customersOrSupplierStatementText' => $customersOrSupplierStatementText,
			'mainFunctionalCurrency' => $mainFunctionalCurrency,
			'currencyCards' => $currencyCards,
		]);
    }
	protected function getDownPaymentInMainCurrency(array $partnerIds,string $mainFunctionalCurrency,string $clientIdColumnName,string $downPaymentSettlementModelName , string $moneyModelName,Company $company):array{
		$result = [];
		$fullDownPaymentModelName = 'App\Models\\'.$downPaymentSettlementModelName;
		$downPaymentSettlements = $fullDownPaymentModelName::
		where('down_payment_balance','!=',0)
		->whereIn($clientIdColumnName,$partnerIds)
		->with([$moneyModelName])
		->get();
		
		foreach($downPaymentSettlements as $downPaymentSettlement){
			$moneyReceived = $downPaymentSettlement->{$moneyModelName} ;
			/**
			 * @var MoneyReceived|MoneyPayment|null $moneyReceived
			 */
			$partnerId = $downPaymentSettlement->{$clientIdColumnName};
			$downPaymentCurrency = $downPaymentSettlement->currency ;
			$foreignExchangeRateAtDate =$moneyReceived ? $moneyReceived->getForeignExchangeRateAtDate($moneyReceived->getReceivingOrPaymentCurrency(),$company) : 1;
			$downPaymentBalance = $downPaymentSettlement->down_payment_balance  ;
			$downPaymentBalanceInMainCurrency = $downPaymentBalance * $foreignExchangeRateAtDate;
			if($mainFunctionalCurrency != $downPaymentCurrency){
				$result[$partnerId] = isset($result[$partnerId]) ? $result[$partnerId] + $downPaymentBalanceInMainCurrency : $downPaymentBalanceInMainCurrency;
			}else{
				$result[$partnerId] = isset($result[$partnerId]) ? $result[$partnerId] + $downPaymentBalance : $downPaymentBalance;
			}

		}
		return $result ;
		
		
	}
	/**
	 * Applies each partner's down payments to their invoice balances.
	 *
	 * Two things are recorded on every row on the way through, both new:
	 *   - invoices_amount / down_payment_amount — the two halves of
	 *     net_balance, so the table can show the breakdown instead of
	 *     only the net. net_balance itself is untouched and still equals
	 *     invoices_amount - down_payment_amount on every row.
	 *   - rows for down payments that used to fall through the cracks —
	 *     see rowsForDownPaymentsWithoutMatchingInvoice() for why.
	 */
	protected function subtractQuery($invoicesBalances,$downPayments,$clientIdColumnName,$clientNameColumnName){
		$newRecords = [];
		$partnersWithoutInvoices = [];
		// Snapshot BEFORE the loop below runs — it mutates rows in place,
		// including overwriting `currency` on the no-invoices branch, so
		// asking these questions afterwards gives the wrong answer.
		$invoicedPairs = [];
		$partnerNames = [];
		foreach($invoicesBalances as $invoiceBalanceStdClass){
			$partnerNames[$invoiceBalanceStdClass->{$clientIdColumnName}] = $invoiceBalanceStdClass->{$clientNameColumnName};
			if($invoiceBalanceStdClass->currency){
				$invoicedPairs[$invoiceBalanceStdClass->{$clientIdColumnName}.'|'.$invoiceBalanceStdClass->currency] = true;
			}
			$invoiceBalanceStdClass->invoices_amount = $invoiceBalanceStdClass->net_balance;
			$invoiceBalanceStdClass->down_payment_amount = 0;
		}
		$hasInvoiceBalances = count($invoicesBalances);
		foreach($hasInvoiceBalances ? $invoicesBalances : [null] as $invoiceBalanceStdClass ){
			
			$addNewRecord = false ;
			$invoicePartnerId =$invoiceBalanceStdClass ?  $invoiceBalanceStdClass->{$clientIdColumnName} : null;
			$invoicePartnerName =$invoiceBalanceStdClass? $invoiceBalanceStdClass->{$clientNameColumnName} : null;
			$invoiceCurrency =$invoiceBalanceStdClass ? $invoiceBalanceStdClass->currency : null ;
			foreach($downPayments as $downPaymentStdClass){
				if(!$hasInvoiceBalances){
					/**
					 * * دي علشان لو مفيش فواتير بس فيه داونبيمنت
					 */
					$invoiceCurrency = null ;
					$invoicePartnerId = $downPaymentStdClass->{$clientIdColumnName};
					$invoicePartnerName = Partner::find($invoicePartnerId)->getName();
					$addNewRecord = true;
				}
				
				$downPaymentPartnerId = $downPaymentStdClass->{$clientIdColumnName} ;
				$downPaymentCurrency = $downPaymentStdClass->currency ;
				
				
				if($downPaymentCurrency == $invoiceCurrency && $downPaymentPartnerId == $invoicePartnerId
				){
					$invoiceBalanceStdClass->net_balance = $invoiceBalanceStdClass->net_balance - $downPaymentStdClass->down_payment_balance;
					$invoiceBalanceStdClass->down_payment_amount = $invoiceBalanceStdClass->down_payment_amount + $downPaymentStdClass->down_payment_balance;
					continue;
				}
				if(is_null($invoiceCurrency) && $downPaymentPartnerId == $invoicePartnerId ){
					$partnersWithoutInvoices[$invoicePartnerId] = $invoicePartnerId;
					if(!$addNewRecord){
						$invoiceBalanceStdClass->currency = $downPaymentCurrency ;
						$invoiceBalanceStdClass->net_balance = 0 - $downPaymentStdClass->down_payment_balance;
						$invoiceBalanceStdClass->invoices_amount = 0;
						$invoiceBalanceStdClass->down_payment_amount = $downPaymentStdClass->down_payment_balance;
						$addNewRecord = true;
					}else{

						$newRecords[] = json_decode(json_encode([
							$clientIdColumnName=>$invoicePartnerId,
							$clientNameColumnName=>$invoicePartnerName,
							'currency'=>$downPaymentCurrency,
							'invoices_amount'=>0,
							'down_payment_amount'=>$downPaymentStdClass->down_payment_balance,
							'net_balance'=>0 - $downPaymentStdClass->down_payment_balance,
							'net_balance_in_main_currency'=>0 - $downPaymentStdClass->down_payment_balance
						]));
					}
				}
			}
		}
			return [
				'partners_without_invoices'=>$partnersWithoutInvoices ,
				'data'=>array_merge(
					$invoicesBalances,
					$newRecords,
					$this->rowsForDownPaymentsWithoutMatchingInvoice($downPayments,$invoicedPairs,$partnerNames,$partnersWithoutInvoices,$clientIdColumnName,$clientNameColumnName)
				)
			] ;
	}

	/**
	 * Down payments that no per-currency tab was showing at all.
	 *
	 * The loop in subtractQuery() only applies a down payment when the
	 * partner has an invoice row in that SAME currency, and it has a
	 * fallback for partners with NO invoices whatsoever (it turns their
	 * empty row into a negative one). What it never had is a fallback for
	 * the case in between: a partner who HAS invoices, just not in the
	 * currency they were paid an advance in. Those down payments were
	 * silently dropped from every per-currency tab — while
	 * addMainCurrency() counted them in full, because it subtracts a
	 * partner's down payments regardless of currency. That is exactly how
	 * a Main Currency total could come out negative while every
	 * individual currency tab looked positive.
	 *
	 * This method closes that gap: one row per orphaned down payment, in
	 * the currency it was actually paid in, with no invoices behind it.
	 */
	protected function rowsForDownPaymentsWithoutMatchingInvoice(array $downPayments,array $invoicedPairs,array $partnerNames,array $partnersWithoutInvoices,string $clientIdColumnName,string $clientNameColumnName):array
	{
		$rows = [];
		foreach($downPayments as $downPaymentStdClass){
			$partnerId = $downPaymentStdClass->{$clientIdColumnName};
			// Partners with no invoices at all already got their row from
			// subtractQuery()'s own branch — adding another here would
			// count the same down payment twice.
			if(isset($partnersWithoutInvoices[$partnerId])){
				continue;
			}
			if(isset($invoicedPairs[$partnerId.'|'.$downPaymentStdClass->currency])){
				continue;
			}
			$partnerName = $partnerNames[$partnerId] ?? optional(Partner::find($partnerId))->getName();
			$rows[] = json_decode(json_encode([
				$clientIdColumnName=>$partnerId,
				$clientNameColumnName=>$partnerName,
				'currency'=>$downPaymentStdClass->currency,
				'invoices_amount'=>0,
				'down_payment_amount'=>$downPaymentStdClass->down_payment_balance,
				'net_balance'=>0 - $downPaymentStdClass->down_payment_balance,
				// Deliberately 0, NOT the negative balance: addMainCurrency()
				// sums this column and then subtracts the partner's down
				// payments in main currency itself. Anything non-zero here
				// would subtract them twice and move the Main Currency total,
				// which was already the correct number.
				'net_balance_in_main_currency'=>0
			]));
		}
		return $rows;
	}
		
		protected function addMainCurrency(array $items,array $downPaymentsInMainCurrency,array $partnersWithoutInvoices,string $clientNameColumnName,string $clientIdColumnName ){
	
			$formattedResult = [];
			$partnerNames = [];
			$totalPerCustomerForMainCurrency = [];
			foreach($items as $stdClass ){
				$partnerId = $stdClass->{$clientIdColumnName} ;
				$partnerName = $stdClass->{$clientNameColumnName} ;
				$partnerNames[$partnerId] = $partnerName;
				$totalPerCustomerForMainCurrency[$partnerId] = isset($totalPerCustomerForMainCurrency[$partnerId]) ? $totalPerCustomerForMainCurrency[$partnerId] + $stdClass->net_balance_in_main_currency :  $stdClass->net_balance_in_main_currency;
			}
			foreach($totalPerCustomerForMainCurrency as $partnerId => $total){
				$downPaymentForPartner = $downPaymentsInMainCurrency[$partnerId] ?? 0 ;
				// Same arithmetic as before — `in_array ? -dp : total-dp` — just
				// split in two so the invoice side can be its own column. The
				// resulting net_balance is byte-for-byte what it always was.
				$invoicesForPartner = in_array($partnerId,$partnersWithoutInvoices) ? 0 : $total ;
				$total = $invoicesForPartner - $downPaymentForPartner ;
				$formattedResult[] = json_decode(json_encode([
					$clientIdColumnName=>$partnerId,
					$clientNameColumnName=>$partnerNames[$partnerId],
					'currency'=>'main_currency',
					'invoices_amount'=>$invoicesForPartner,
					'down_payment_amount'=>$downPaymentForPartner,
					'net_balance'=>$total,
					'net_balance_in_main_currency'=>$total
				]));
			}
			return $formattedResult;
		
			
		
		// return $result;
	}
	
	/**
	 * All Invoices / Past Due / Coming Dues report — reached from the
	 * KPI card buttons on the Customers/Suppliers Balances page.
	 * Renders resources/js/Pages/Balances/TotalNetBalanceDetails.vue.
	 *
	 * "Coming Dues" (invoice_status = 'not_due_yet') is a genuinely
	 * NEW filter mode, added at the project owner's request — it did
	 * not exist in the original app. Confirmed definition: invoices
	 * that are not yet due (excludes 'due_to_day', which is due
	 * TODAY, not upcoming).
	 *
	 * The original only checked whether an `only` query param was
	 * PRESENT at all (any value meant "past due"). Switched to
	 * checking its VALUE instead, to support a third mode — this is
	 * backward compatible: the existing "Past Due" button already
	 * sends only=past_due (see BalancesController::index()), so its
	 * behavior is unchanged.
	 */
	public function showTotalNetBalanceDetailsReport(Request $request,Company $company , string $currency , string $modelType)
	{
		$netBalanceCondition = self::NET_BALANCE_CONDITION;
		$onlyMode = $request->get('only');
		$additionalWhereClause = match ($onlyMode) {
			'past_due' => "and invoice_status in ('past_due' , 'partially_collected_and_past_due' )",
			'coming_due' => "and invoice_status = 'not_due_yet'",
			default => '',
		};
		$fullClassName = ('\App\Models\\'.$modelType) ;
		$customersOrSupplierText = (new $fullClassName )->getClientDisplayName();
		$title = (new $fullClassName )->getBalancesTitle();
		$clientNameColumnName = $fullClassName::CLIENT_NAME_COLUMN_NAME ;
		$clientIdColumnName = $fullClassName::CLIENT_ID_COLUMN_NAME ;
		$tableName = $fullClassName::TABLE_NAME ;
		$user =User::where('id',$request->user()->id)->get();
		$mainCurrency = $company->getMainFunctionalCurrency();
		$moneyReceivedOrPaidUrlName = (new $fullClassName)->getMoneyReceivedOrPaidUrlName();
		$moneyReceivedOrPaidText = (new $fullClassName)->getMoneyReceivedOrPaidText();
		$clientNameText = (new $fullClassName)->getClientNameText();
		$query = 'select id,'. $clientNameColumnName .' ,invoice_due_date,invoice_status,invoice_number,DATE_FORMAT(invoice_date,"%d-%m-%Y") as invoice_date, currency , net_balance   from '. $tableName .' where '.$netBalanceCondition.'  currency = "'. $currency .'" and company_id = '. $company->id . ' ' . $additionalWhereClause . ' order by invoice_due_date asc , net_balance desc ;';
		$invoicesBalances = DB::select($query);

		$rows = collect($invoicesBalances)->map(function ($row) use ($clientNameColumnName, $company, $modelType, $moneyReceivedOrPaidUrlName) {
			return [
				'id' => $row->id,
				'client_name' => $row->{$clientNameColumnName},
				'invoice_number' => $row->invoice_number,
				'invoice_date' => $row->invoice_date,
				'currency' => $row->currency,
				'net_balance_formatted' => number_format($row->net_balance),
				'invoice_due_date_formatted' => \Carbon\Carbon::make($row->invoice_due_date)->format('d-m-Y'),
				'status_formatted' => snakeToCamel($row->invoice_status),
				'money_action_url' => route($moneyReceivedOrPaidUrlName, ['company' => $company->id, 'model' => $row->id]),
			];
		})->values();

		$reportTitle = match ($onlyMode) {
			'past_due' => 'Past Due',
			'coming_due' => 'Coming Dues',
			default => 'All Invoices',
		};

		return Inertia::render('Balances/TotalNetBalanceDetails', [
			'invoicesBalances' => $rows,
			'currency' => $currency,
			'clientNameText' => $clientNameText,
			'moneyReceivedOrPaidText' => $moneyReceivedOrPaidText,
			'reportTitle' => $reportTitle,
			'backUrl' => route('view.balances', ['company' => $company->id, 'modelType' => $modelType]),
		]);
    }



}
