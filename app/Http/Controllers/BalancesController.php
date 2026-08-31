<?php

namespace App\Http\Controllers;

use App\Support\Instructions\PageInstructions;

use App\Models\Company;
use App\Models\ForeignExchangeRate;
use App\Models\InternalSettlement;
use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use App\Models\Partner;
use App\Models\User;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
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

		/**
		 * ⚠️ The page no longer subtracts internal settlements here, and
		 * must not start again.
		 *
		 * A settlement now writes real rows into `settlements` /
		 * `payment_settlements`, and the invoice triggers take the
		 * money off `customer_invoices.net_balance` /
		 * `supplier_invoices.net_balance` themselves. Those reduced
		 * balances are what the query above already read, so
		 * subtracting the settlement a second time here would take the
		 * same amount off twice.
		 *
		 * The totals are still fetched, but only to SHOW what has been
		 * settled in its own column — they are not applied to the net.
		 */
		$settlementTotals = InternalSettlement::totalsByPartnerAndCurrency(
			$company->id,
			array_values(array_unique(array_column($invoicesBalances, $clientIdColumnName)))
		);

		$cardNetBalances = $this->sumNetBalancePerCurrency($invoicesBalances,$mainFunctionalCurrency,$clientNameColumnName);
		$hasMoreThanCurrency = isset($cardNetBalances['currencies']) && count($cardNetBalances['currencies']) >1 ;

		// ── Group the flat $invoicesBalances list by currency, and
		// pre-resolve every button URL the Vue page needs (no Ziggy —
		// see Style Guide §8). This block is purely presentational:
		// it reshapes the exact same $invoicesBalances / $cardNetBalances
		// the Blade version already had, nothing above is touched.
		/**
		 * Partners who are a customer AND a supplier at the same time —
		 * the only ones an internal settlement can apply to, since it
		 * offsets one of their balances against the other. is_customer
		 * and is_supplier are independent flags, so this is a normal
		 * situation, not an edge case: a company you both sell to and
		 * buy from.
		 */
		$dualRolePartnerIds = Partner::query()
			->where('company_id', $company->id)
			->where('is_customer', 1)
			->where('is_supplier', 1)
			->pluck('id')
			->flip()
			->toArray();

		/**
		 * The settlements already recorded, so the modal can show what
		 * has been settled before rather than only a running total —
		 * and can take one back if it was entered wrongly.
		 *
		 * Only fetched for partners a settlement could apply to at all,
		 * which on a real page is a small minority of the rows.
		 */
		$settlementsByPartnerAndCurrency = [];
		foreach (InternalSettlement::query()
			->where('company_id', $company->id)
			->whereIn('partner_id', array_keys($dualRolePartnerIds))
			->orderByDesc('settlement_date')
			->orderByDesc('id')
			->get() as $settlement) {
			$settlementsByPartnerAndCurrency[$settlement->partner_id.'|'.$settlement->currency][] = [
				'id' => $settlement->id,
				'date' => $settlement->getDate(),
				'date_formatted' => $settlement->getDateFormatted(),
				'amount' => $settlement->getAmount(),
				'user_comment' => $settlement->getUserComment(),
				// What it actually settled, so the history line is
				// readable without opening it.
				'customer_invoice_numbers' => $settlement->invoiceNumbersFor(InternalSettlement::SIDE_CUSTOMER),
				'supplier_invoice_numbers' => $settlement->invoiceNumbersFor(InternalSettlement::SIDE_SUPPLIER),
				'update_url' => route('update.internal.settlement', [
					'company' => $company->id, 'internalSettlement' => $settlement->id,
				]),
				'delete_url' => route('delete.internal.settlement', [
					'company' => $company->id, 'internalSettlement' => $settlement->id,
				]),
			];
		}

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
			$partnerId = (int) $row->{$clientIdColumnName};
			$isDualRole = isset($dualRolePartnerIds[$partnerId]);
			$netBalance = (float) $row->net_balance;

			$rowsByCurrency[$currency][] = [
				'client_id' => $row->{$clientIdColumnName},
				'client_name' => $row->{$clientNameColumnName},
				'currency' => $currency,
				// How much of this balance has already been offset
				// against the same partner's other side. Its own column
				// so the net stays auditable: invoices − down payments
				// − internal settlements = net balance.
				'internal_settlements' => (float) ($settlementTotals[$partnerId.'|'.$currency] ?? 0),
				'is_dual_role' => $isDualRole,
				/**
				 * Whether a NEW settlement can be recorded on this row.
				 *
				 * - main_currency is a computed roll-up across every
				 *   currency, not a balance that exists anywhere — an
				 *   offset has to be booked in a real currency.
				 * - Nothing left owed means nothing left to offset. The
				 *   server re-checks this on save (see
				 *   storeInternalSettlement); this only decides whether
				 *   the button is worth showing.
				 */
				'can_settle' => $isDualRole && $currency !== 'main_currency' && $netBalance > 0,
				'settlements' => $settlementsByPartnerAndCurrency[$partnerId.'|'.$currency] ?? [],
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
			/**
			 * Customer and supplier balances render the same component,
			 * so the guide follows whichever side is on screen.
			 */
			'instructionsUrl' => route('view.instructions', [
				'company' => $company->id,
				'page' => $modelType === 'SupplierInvoice'
					? PageInstructions::SUPPLIER_BALANCES
					: PageInstructions::CUSTOMER_BALANCES,
			]),
			'company' => ['id' => $company->id],
			'modelType' => $modelType,
			/**
			 * A settlement is always entered from the CUSTOMER side —
			 * "he owes us 10,000, how much of it do we hand back to him
			 * as a supplier". The Suppliers Balances page still shows
			 * the resulting column, because its own Net Balance moved
			 * too and an unexplained drop is worse than a read-only
			 * column; it just cannot start one.
			 */
			'canSettleInternally' => $modelType === 'CustomerInvoice' && hasAuthFor('customer_balance.settle'),
			'storeInternalSettlementUrl' => route('store.internal.settlement', ['company' => $company->id]),
			'internalSettlementInvoicesUrl' => route('internal.settlement.invoices', ['company' => $company->id]),
			'title' => $title,
			'customersOrSupplierText' => $customersOrSupplierText,
			'customersOrSupplierStatementText' => $customersOrSupplierStatementText,
			'mainFunctionalCurrency' => $mainFunctionalCurrency,
			'currencyCards' => $currencyCards,
		]);
    }
	/**
	 * The open invoices on one side of a partner, for one currency.
	 *
	 * "Open" is net_balance > 0 — what is still owed on that invoice
	 * after everything already collected, withheld and deducted. When
	 * an existing settlement is being edited, its own allocations are
	 * added back on top: they came off these balances when it was
	 * saved, and the edit form has to show the invoice as it was before
	 * this settlement touched it, or its own amount would look
	 * unavailable to itself.
	 *
	 * @return list<array<string, mixed>>
	 */
	protected function openInvoicesFor(Company $company, int $partnerId, string $currency, string $side, ?InternalSettlement $editing = null): array
	{
		$meta = InternalSettlement::sideTables($side);
		$mine = $editing?->allocationsBySide()[$side] ?? [];

		$rows = DB::table($meta['invoice_table'])
			->where('company_id', $company->id)
			->where($meta['partner_column'], $partnerId)
			->where('currency', $currency)
			->where(function ($q) use ($mine) {
				$q->where('net_balance', '>', 0);
				if ($mine !== []) {
					$q->orWhereIn('id', array_keys($mine));
				}
			})
			->orderBy('invoice_date')
			->orderBy('id')
			->get(['id', 'invoice_number', 'invoice_date', 'invoice_due_date', 'net_invoice_amount', 'net_balance']);

		return $rows->map(function ($row) use ($mine) {
			$allocated = (float) ($mine[$row->id] ?? 0);

			return [
				'id' => (int) $row->id,
				'invoice_number' => $row->invoice_number,
				'invoice_date' => $row->invoice_date,
				'invoice_due_date' => $row->invoice_due_date,
				'net_invoice_amount' => (float) $row->net_invoice_amount,
				// What is open right now, with this settlement's own
				// effect on this invoice added back.
				'open' => round((float) $row->net_balance + $allocated, 2),
				'allocated' => $allocated,
			];
		})->values()->all();
	}

	/**
	 * Everything the settle/edit dialog needs for one balances row.
	 *
	 * Loaded on demand rather than shipped with every row of the page —
	 * a company can have hundreds of partners and each one's invoices
	 * would ride along for a dialog that is opened once.
	 */
	public function internalSettlementInvoices(Request $request, Company $company)
	{
		$partnerId = (int) $request->get('partner_id');
		$currency = (string) $request->get('currency');
		$editing = $request->get('internal_settlement_id')
			? InternalSettlement::where('company_id', $company->id)->find($request->get('internal_settlement_id'))
			: null;

		$partner = Partner::where('company_id', $company->id)->find($partnerId);

		if (! $partner || ! $partner->isCustomer() || ! $partner->isSupplier() || $currency === '' || $currency === 'main_currency') {
			return response()->json(['customer' => [], 'supplier' => []]);
		}

		return response()->json([
			'customer' => $this->openInvoicesFor($company, $partnerId, $currency, InternalSettlement::SIDE_CUSTOMER, $editing),
			'supplier' => $this->openInvoicesFor($company, $partnerId, $currency, InternalSettlement::SIDE_SUPPLIER, $editing),
			'settlement' => $editing ? [
				'id' => $editing->id,
				'amount' => $editing->getAmount(),
				'settlement_date' => $editing->getDate(),
				'user_comment' => $editing->getUserComment(),
			] : null,
		]);
	}

	/**
	 * Checks one submitted settlement and returns its allocations.
	 *
	 * Every rule is enforced here rather than in the form, because the
	 * numbers decide what two invoices are worth afterwards:
	 *   - the partner is a customer AND a supplier
	 *   - the currency is a real one, not the main-currency roll-up
	 *   - no invoice is given more than it has open (its own current
	 *     allocation added back, when editing)
	 *   - both sides total the same, and that total is the amount
	 *
	 * @return array{0: array<string, array<int, float>>, 1: float}|string  allocations+amount, or an error message
	 */
	protected function validateInternalSettlement(Request $request, Company $company, ?InternalSettlement $editing = null): array|string
	{
		$partner = Partner::where('company_id', $company->id)->find($request->get('partner_id'));
		$currency = (string) $request->get('currency');

		if (! $partner || ! $partner->isCustomer() || ! $partner->isSupplier()) {
			return __('An internal settlement is only possible for a partner who is both a customer and a supplier.');
		}

		if ($currency === '' || $currency === 'main_currency') {
			return __('Please record the settlement in the currency it is owed in.');
		}

		$allocations = [];
		$totals = [];

		foreach ([InternalSettlement::SIDE_CUSTOMER, InternalSettlement::SIDE_SUPPLIER] as $side) {
			$open = collect($this->openInvoicesFor($company, (int) $partner->id, $currency, $side, $editing))
				->keyBy('id');
			$submitted = (array) $request->get($side.'_allocations', []);
			$allocations[$side] = [];
			$totals[$side] = 0.0;

			foreach ($submitted as $invoiceId => $amount) {
				$amount = round((float) $amount, 2);
				if ($amount <= 0) {
					continue;
				}

				$invoice = $open->get((int) $invoiceId);
				if (! $invoice) {
					return __('One of the selected invoices no longer belongs to this partner in this currency.');
				}

				if ($amount > $invoice['open'] + 0.001) {
					return __('Invoice :number only has :open :currency open — :amount cannot be allocated to it.', [
						'number' => $invoice['invoice_number'],
						'open' => number_format($invoice['open'], 2),
						'currency' => $currency,
						'amount' => number_format($amount, 2),
					]);
				}

				$allocations[$side][(int) $invoiceId] = $amount;
				$totals[$side] += $amount;
			}
		}

		$customerTotal = round($totals[InternalSettlement::SIDE_CUSTOMER], 2);
		$supplierTotal = round($totals[InternalSettlement::SIDE_SUPPLIER], 2);

		if ($customerTotal <= 0) {
			return __('Allocate the amount across at least one customer invoice and one supplier invoice.');
		}

		if (abs($customerTotal - $supplierTotal) > 0.01) {
			return __('The two sides must match: :customer allocated on customer invoices against :supplier on supplier invoices.', [
				'customer' => number_format($customerTotal, 2),
				'supplier' => number_format($supplierTotal, 2),
			]);
		}

		return [$allocations, $customerTotal];
	}

	/**
	 * Records one internal settlement, allocated across real invoices.
	 *
	 * Wrapped in a transaction because a half-applied settlement would
	 * leave one side's invoices paid and the other side's untouched —
	 * money that came from nowhere.
	 */
	public function storeInternalSettlement(Request $request, Company $company)
	{
		$request->validate([
			'partner_id' => ['required', 'integer'],
			'currency' => ['required', 'string'],
			'settlement_date' => ['required', 'date'],
			'user_comment' => ['nullable', 'string'],
		]);

		$checked = $this->validateInternalSettlement($request, $company);

		if (is_string($checked)) {
			return redirect()->back()->with('fail', $checked);
		}

		[$allocations, $amount] = $checked;

		DB::transaction(function () use ($request, $company, $allocations, $amount) {
			$settlement = InternalSettlement::create($this->internalSettlementAttributes($request, $company, $amount));
			$settlement->applyAllocations($allocations);
		});

		return redirect()->back()->with('success', __('Internal Settlement Saved Successfully'));
	}

	/**
	 * Edits an existing settlement.
	 *
	 * The old allocations are taken back FIRST, inside the same
	 * transaction, so the new ones are measured against invoices in the
	 * state they were before this settlement existed. Doing it the
	 * other way round would refuse a legitimate edit — raising an
	 * allocation from 80,000 to 90,000 would be judged against a
	 * balance the original 80,000 had already reduced.
	 */
	public function updateInternalSettlement(Request $request, Company $company, InternalSettlement $internalSettlement)
	{
		if ((int) $internalSettlement->company_id !== (int) $company->id) {
			abort(403);
		}

		$request->validate([
			'settlement_date' => ['required', 'date'],
			'user_comment' => ['nullable', 'string'],
		]);

		// The partner and currency of a settlement are what it IS —
		// changing either is a different settlement, so they are taken
		// from the stored row, not from the form.
		$request->merge([
			'partner_id' => $internalSettlement->partner_id,
			'currency' => $internalSettlement->currency,
		]);

		$checked = $this->validateInternalSettlement($request, $company, $internalSettlement);

		if (is_string($checked)) {
			return redirect()->back()->with('fail', $checked);
		}

		[$allocations, $amount] = $checked;

		DB::transaction(function () use ($request, $company, $internalSettlement, $allocations, $amount) {
			$internalSettlement->reverseAllocations();
			$internalSettlement->update($this->internalSettlementAttributes($request, $company, $amount, $internalSettlement));
			$internalSettlement->applyAllocations($allocations);
		});

		return redirect()->back()->with('success', __('Internal Settlement Saved Successfully'));
	}

	/**
	 * The stored shape of a settlement, shared by create and edit.
	 *
	 * @return array<string, mixed>
	 */
	protected function internalSettlementAttributes(Request $request, Company $company, float $amount, ?InternalSettlement $existing = null): array
	{
		$currency = $existing?->currency ?? (string) $request->get('currency');
		$mainCurrency = $company->getMainFunctionalCurrency();
		$date = Carbon::make($request->get('settlement_date'))->format('Y-m-d');

		/**
		 * Stamped from the settlement's own date, so the main-currency
		 * view keeps reading the rate that applied when the offset was
		 * agreed, however rates move afterwards.
		 */
		$exchangeRate = $currency === $mainCurrency
			? 1
			: (float) ForeignExchangeRate::getExchangeRateForCurrencyAndClosestDate($currency, $mainCurrency, $date, $company->id);
		$exchangeRate = $exchangeRate > 0 ? $exchangeRate : 1;

		return [
			'company_id' => $company->id,
			'partner_id' => $existing?->partner_id ?? $request->get('partner_id'),
			'currency' => $currency,
			'settlement_date' => $date,
			'amount' => $amount,
			'exchange_rate' => $exchangeRate,
			'amount_in_main_currency' => round($amount * $exchangeRate, 2),
			'user_comment' => $request->get('user_comment'),
			'created_by' => $existing?->created_by ?? $request->user()?->id,
			'updated_by' => $request->user()?->id,
		];
	}

	/**
	 * Takes a settlement back.
	 *
	 * Deleting the allocations is the whole reversal: each delete fires
	 * the invoice trigger that recomputes that invoice from the rows
	 * that remain, so both sides return to exactly what they were.
	 */
	public function destroyInternalSettlement(Company $company, InternalSettlement $internalSettlement)
	{
		if ((int) $internalSettlement->company_id !== (int) $company->id) {
			abort(403);
		}

		DB::transaction(function () use ($internalSettlement) {
			$internalSettlement->reverseAllocations();
			$internalSettlement->delete();
		});

		return redirect()->back()->with('success', __('Internal Settlement Deleted Successfully'));
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
			'instructionsUrl' => route('view.instructions', ['company' => $company->id, 'page' => PageInstructions::NET_BALANCE_DETAILS, 'modelType' => $modelType]),
			'invoicesBalances' => $rows,
			'currency' => $currency,
			'clientNameText' => $clientNameText,
			'moneyReceivedOrPaidText' => $moneyReceivedOrPaidText,
			'reportTitle' => $reportTitle,
			'backUrl' => route('view.balances', ['company' => $company->id, 'modelType' => $modelType]),
		]);
    }



}
