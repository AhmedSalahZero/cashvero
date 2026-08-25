<?php
namespace App\Http\Controllers;

use App\Http\Requests\MarkChequeAsPaidRequest;
use App\Http\Requests\StoreCashExpenseRequest;
use App\Http\Requests\UnmarkChequeAsPaidRequest;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\CashExpense;
use App\Models\CashExpenseCategory;
use App\Models\CashExpenseCategoryName;
use App\Models\Company;
use App\Models\Currency;
use App\Models\FinancialInstitution;
use App\Models\ForeignExchangeRate;
use App\Models\OutgoingTransfer;
use App\Models\Partner;
use App\Models\PayableCheque;
use App\Services\Api\CashExpenseOdooService;
use App\Services\Api\OdooSync;
use App\Support\ShareholderAccounts\AccountNumberLabel;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * CashExpenseController
 * ------------------------------------------------------------------
 * Three tabs, all backed by the CashExpense model with a different
 * `type`: Outgoing Transfer (bank), Cash Payment (safe/branch), and
 * Payable Cheque (issued, tracked until marked paid). Each tab's query
 * — getCashExpenseCashPayments()/getCashExpenseOutgoingTransfer()/
 * getCashExpensePayableCheques() on Company — is real SQL with proper
 * eager-loading and pagination already; none of that, or
 * markChequesAsPaid()/markOutgoingTransfersAsPaid()/store()/update(),
 * is touched here.
 *
 * Note: the old Blade page defaulted its *visible* active tab to
 * Outgoing Transfer (first in the tab markup) but the *controller*
 * defaulted $activeTab to Cash Payment when no ?active= was present —
 * a small pre-existing mismatch between what looked selected and
 * what the server treated as selected. This rewrite picks one
 * consistently (Outgoing Transfer, matching what the page visually
 * showed as active) rather than reproducing the mismatch.
 *
 * ── Frontend migration status ───────────────────────────────────────
 *   ✅ index() → MIGRATED to Vue + Inertia
 *      (resources/js/Pages/CashExpense/Index.vue). Batch "Mark As
 *      Paid" (Outgoing Transfer and Payable Cheque tabs) is included,
 *      via the same pre-existing markChequesAsPaid()/
 *      markOutgoingTransfersAsPaid() endpoints.
 *   🔲 create()/edit() → NOT YET migrated (still the old Blade form).
 *      This form pulls in Cash Expense Categories, contract-linked
 *      down payments, and supplier-invoice pre-fill — clearly a
 *      bigger, riskier piece than the list page, so it's planned as
 *      its own follow-up step rather than rushed in alongside this.
 */
class CashExpenseController
{
    use GeneralFunctions;

	public function index(Company $company,Request $request)
	{
		$paginationPerPage = GeneralFunctions::getPaginationLimit();
		$numberOfMonthsBetweenEndDateAndStartDate = 18 ;
		$activeTab = $request->get('active',CashExpense::OUTGOING_TRANSFER) ;
		$filterDates = [];
		foreach(CashExpense::getAllTypes() as $type){
			$startDate = $request->has('startDate') ? $request->input('startDate.'.$type) : now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d');
			$endDate = $request->has('endDate') ? $request->input('endDate.'.$type) : now()->format('Y-m-d');

			$filterDates[$type] = [
				'startDate'=>$startDate,
				'endDate'=>$endDate
			];
		}

		$financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get();
		$accountTypes = AccountType::onlyCashAccounts()->get();

		/**
		 * Row mapping below is new presentation-layer code — every
		 * getter called is pre-existing/UNCHANGED. isOpenBalance()
		 * rows (system-generated from an opening balance import) get
		 * no edit/delete URLs, matching the old page's
		 * @if(!$money->isOpenBalance()) guard exactly.
		 */
		$mapCommon = function (CashExpense $model) use ($company) {
			return [
				'id' => $model->id,
				'expense_category_name' => $model->getExpenseCategoryName(),
				'expense_name' => $model->getExpenseName(),
				'payment_date_formatted' => $model->getPaymentDateFormatted(),
				'paid_amount_formatted' => $model->getPaidAmountFormatted(),
				'currency' => $model->getCurrencyToPaymentCurrencyFormatted(),
				'is_open_balance' => $model->isOpenBalance(),
				'user_comment' => $model->hasComment() ? $model->getUserComment() : null,
				'is_fully_integrated_with_odoo' => $company->hasOdooIntegrationCredentials() && $model->fullyIntegratedWithOdoo(),
				'has_odoo_error' => (bool) $model->hasOdooError(),
				'odoo_error' => $model->getOdooError(),
				'odoo_reference_names' => $model->getOdooReferenceNames(),
				'edit_url' => $model->isOpenBalance() ? null : route('edit.cash.expense', ['company' => $company->id, 'cashExpense' => $model->id]),
				/**
				 * Same isOpenBalance() guard as edit/delete: an opening
				 * balance row is system-generated from an import, not
				 * something a user entered, so there is nothing
				 * meaningful to copy it into.
				 */
				'copy_url' => $model->isOpenBalance() ? null : route('copy.cash.expense', ['company' => $company->id, 'cashExpense' => $model->id]),
				'delete_url' => $model->isOpenBalance() ? null : route('delete.cash.expense', ['company' => $company->id, 'cashExpense' => $model->id]),
			];
		};

		/**
		 * FIX (per audit, 2026-08-13): the query + eager-load + paginate
		 * + row-mapping for all THREE tabs used to run unconditionally
		 * on every single request to this page — including a plain
		 * "next page" pagination click on just ONE of them. Nothing on
		 * screen ever shows more than the active tab's data (confirmed:
		 * no tab-count badges reference the other tabs), so the other
		 * two tabs' work was pure waste on every such request, and it
		 * compounds badly once the table has real production-sized data.
		 *
		 * Each tab's actual work now lives in this closure, so it's
		 * only executed when Inertia actually needs that specific prop
		 * — every prop still resolves normally on a full page load
		 * (e.g. first opening the page, or switching tabs, which stays
		 * instant/client-side exactly as before, since all three are
		 * still present on that initial load). The saving specifically
		 * kicks in on partial reloads — see goToPage()/applyFilters()
		 * in Index.vue, which now pass `only` naming just the active
		 * tab's prop, so a "next page" click genuinely only recomputes
		 * that one tab instead of all three.
		 */
		$buildTab = function (string $type, string $label, bool $hasBatchCollection) use (
			$company, $activeTab, $filterDates, $paginationPerPage, $mapCommon
		) {
			$startDate = $filterDates[$type]['startDate'] ?? null;
			$endDate = $filterDates[$type]['endDate'] ?? null;

			$pageParamByType = [
				CashExpense::OUTGOING_TRANSFER => 'outgoingTransferPage',
				CashExpense::CASH_PAYMENT => 'cashPaymentsPage',
				CashExpense::PAYABLE_CHEQUE => 'payableChequesPage',
			];
			$queryMethodByType = [
				CashExpense::OUTGOING_TRANSFER => 'getCashExpenseOutgoingTransfer',
				CashExpense::CASH_PAYMENT => 'getCashExpenseCashPayments',
				CashExpense::PAYABLE_CHEQUE => 'getCashExpensePayableCheques',
			];

			$queryMethod = $queryMethodByType[$type];
			$paginator = $company->{$queryMethod}($startDate, $endDate, $activeTab)
				->paginate($paginationPerPage, ['*'], $pageParamByType[$type])
				->withQueryString();

			$mapped = $paginator->through(function (CashExpense $model) use ($mapCommon, $type, $company) {
				$common = $mapCommon($model);
				if ($type === CashExpense::OUTGOING_TRANSFER) {
					return array_merge($common, [
						// getOutgoingTransferDeliveryBankName() returns the Bank's
						// combined view_name (English + Arabic in one string) —
						// the actual cause of the "too long" column. Pulling
						// name_en/name_ar straight off the related Bank lets the
						// page show them as two shorter stacked lines instead.
						'bank_name_en' => optional(optional(optional($model->outgoingTransfer)->deliveryBank)->bank)->name_en,
						'bank_name_ar' => optional(optional(optional($model->outgoingTransfer)->deliveryBank)->bank)->name_ar,
						'bank_name' => $model->getOutgoingTransferDeliveryBankName(),
						'account_type_name' => $model->getOutgoingTransferAccountTypeName(),
						'account_number' => AccountNumberLabel::forCurrentAccount($company->id, $model->getOutgoingTransferDeliveryBankId(), $model->getOutgoingTransferAccountNumber()),
					]);
				}
				if ($type === CashExpense::CASH_PAYMENT) {
					return array_merge($common, [
						'branch_name' => $model->getCashPaymentBranchName(),
						'receipt_number' => $model->getCashPaymentReceiptNumber(),
					]);
				}
				// PAYABLE_CHEQUE
				$dueStatus = $model->payableCheque ? $model->payableCheque->getDueStatusFormatted() : null;
				return array_merge($common, [
					'status' => $model->payableCheque?->getStatusFormatted(),
					'is_paid' => $model->payableCheque?->getStatus() === 'paid',
					'cheque_number' => $model->payableCheque?->getChequeNumber(),
					'bank_name_en' => optional(optional(optional($model->payableCheque)->deliveryBank)->bank)->name_en,
					'bank_name_ar' => optional(optional(optional($model->payableCheque)->deliveryBank)->bank)->name_ar,
					'bank_name' => $model->payableCheque?->getPaymentBankName(),
					'account_type_name' => $model->payableCheque?->getAccountTypeName(),
					'account_number' => AccountNumberLabel::forCurrentAccount($company->id, $model->getPayableChequePaymentBankId(), $model->payableCheque?->getAccountNumber()),
					'due_date_formatted' => $model->payableCheque?->getDueDateFormatted(),
					'due_status' => $dueStatus,
					'can_mark_paid' => !$model->isOpenBalance() && $model->payableCheque?->getStatus() !== 'paid',
					'can_unmark_paid' => !$model->isOpenBalance() && $model->payableCheque?->getStatus() === 'paid',
				]);
			});

			return [
				'label' => $label,
				'rows' => $mapped,
				'startDate' => $startDate,
				'endDate' => $endDate,
				'hasBatchCollection' => $hasBatchCollection,
			];
		};

		return \Inertia\Inertia::render('CashExpense/Index', [
			'company' => ['id' => $company->id],
			'activeTab' => $activeTab,
			'canCreate' => hasAuthFor('cash_expense.create'),
			'canUpdate' => hasAuthFor('cash_expense.update'),
			'canDelete' => hasAuthFor('cash_expense.delete'),
			// Marking a payable cheque / outgoing transfer as paid.
			'canMarkAsPaid' => hasAuthFor('cash_expense.mark_as_paid'),
			'outgoingTransferTab' => fn () => $buildTab(CashExpense::OUTGOING_TRANSFER, __('Outgoing Transfer'), true),
			'cashPaymentTab' => fn () => $buildTab(CashExpense::CASH_PAYMENT, __('Cash Payment'), false),
			'payableChequeTab' => fn () => $buildTab(CashExpense::PAYABLE_CHEQUE, __('Payable Cheques'), true),
			'indexUrl' => route('view.cash.expense', ['company' => $company->id]),
			'createUrl' => route('create.cash.expense', ['company' => $company->id]),
			'markChequesAsPaidUrl' => route('cash.expense.payable.cheque.mark.as.paid', ['company' => $company->id]),
			'unmarkChequesAsPaidUrl' => route('cash.expense.payable.cheque.unmark.as.paid', ['company' => $company->id]),
			'markOutgoingTransfersAsPaidUrl' => route('cash.expense.outgoing.transfer.mark.as.paid', ['company' => $company->id]),
		]);
    }

	/**
	 * Add Cash Expense form.
	 *
	 * ✅ MIGRATED to Vue + Inertia. Shares the same page component as
	 * edit() (resources/js/Pages/CashExpense/Form.vue), same
	 * `mode: 'create' | 'edit'` pattern used everywhere else in this
	 * project.
	 *
	 * Scope note: this covers the core expense entry — category,
	 * amount, the three payment-method field groups, and allocating
	 * the expense across customer contracts ("Allocating With Customer
	 * Contracts" — a repeater: pick a customer, their contracts load
	 * via the same AJAX endpoint used by the Contracts page's PO
	 * Allocation modal, code/amount auto-fill, you set an allocate
	 * amount per contract; saves via saveAllocations(), UNCHANGED).
	 * Two more advanced pieces of the old form are still NOT included
	 * (both are `sometimes`/empty-default in StoreCashExpenseRequest,
	 * not required to save a basic expense):
	 *   - Pre-filling from a specific supplier invoice ($supplierInvoiceId).
	 *   - Inline "add a new category/expense name" from the form
	 *     (the old page used a shared generic modal component whose
	 *     AJAX endpoint isn't a named route — safer to leave this as
	 *     "pick an existing one" for now than guess at it).
	 * Flagging these here rather than silently dropping them.
	 */
	public function create(Company $company,$supplierInvoiceId = null)
	{
		return \Inertia\Inertia::render('CashExpense/Form', $this->buildFormProps($company, null));
    }

	/**
	 * "Copy" — the CREATE form, opened pre-filled from an existing
	 * expense so an expense that repeats (same category, same bank,
	 * same amount) can be saved again without re-typing it.
	 *
	 * It is the create form, not the edit form: nothing about
	 * $cashExpense is touched, and saving inserts a NEW row. Only
	 * buildFormProps()'s third argument differs from create(), which
	 * is what strips the identity of the copied row — see
	 * COPY_CLEARED_FIELDS for exactly which fields do not survive a
	 * copy, and why.
	 */
	public function copy(Company $company, CashExpense $cashExpense)
	{
		return \Inertia\Inertia::render('CashExpense/Form', $this->buildFormProps($company, $cashExpense, true));
	}

	/**
	 * The fields a COPY deliberately does not carry over.
	 *
	 * `cheque_number` and `receipt_number` are unique — per delivery
	 * bank and per receiving branch respectively (see
	 * UniqueChequeNumberRule / UniqueReceiptNumberForReceivingBranchRule
	 * in StoreCashExpenseRequest). Copying either would guarantee a
	 * validation failure on the very first save, which is the exact
	 * opposite of what a copy is for, so they come through blank for
	 * the user to fill in.
	 *
	 * The three id fields are the copied row's identity. They must be
	 * null or the form would submit as an edit of the original —
	 * `payable_cheque_id`/`cash_payment_id` in particular are what the
	 * uniqueness rules exclude from their own check, so leaving them
	 * set would let a genuine duplicate number through.
	 */
	private const COPY_CLEARED_FIELDS = [
		'id', 'payable_cheque_id', 'cash_payment_id',
		'cheque_number', 'receipt_number',
		/**
		 * A copy opens with the date empty, so it is entered for the
		 * day the new expense is actually being made rather than
		 * inheriting the copied one by accident.
		 *
		 * ⚠️ This is listed HERE rather than being nulled inside the
		 * shared 'model' array (where it was briefly set to null) —
		 * buildFormProps() serves edit() too, and a null there blanks
		 * the date when opening an EXISTING expense for editing. The
		 * Vue form falls back to today when the date is empty
		 * (Form.vue: `props.model?.payment_date ?? todayDate()`), so
		 * that would silently re-date every expense on save.
		 */
		'payment_date',
	];

	/**
	 * Turns the old create()/edit()'s existing query logic (all
	 * UNCHANGED below) into the flat, pre-formatted prop shape Inertia
	 * needs. New presentation-layer code only.
	 *
	 * $isCopy renders the SAME filled-in form as edit, but as a create:
	 * `mode` is 'create', the identity fields are stripped, and
	 * `submitUrl` points at store() instead of update(). Everything
	 * else — including the contract allocations — is copied as-is, so
	 * the form opens ready to save.
	 */
	protected function buildFormProps(Company $company, ?CashExpense $model, bool $isCopy = false): array
	{
		$currencies = getCurrencies();
		$clientsWithContracts = Partner::onlyCompany($company->id)->onlyCustomers()->onlyThatHaveCustomerContracts()->get();
		$accountTypes = AccountType::onlyCashAccounts()->get();
		$selectedBranches = Branch::getBranchesForCurrentCompany($company->id);
		$financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get();
		$cashExpenseCategories = CashExpenseCategory::where('company_id', $company->id)->orderBy('name', 'asc')->get();
		$cashExpenseCategoryNames = CashExpenseCategoryName::whereIn('cash_expense_category_id', $cashExpenseCategories->pluck('id'))->get();

		$props = [
			'company' => ['id' => $company->id, 'mainFunctionalCurrency' => $company->getMainFunctionalCurrency()],
			'mode' => $model && ! $isCopy ? 'edit' : 'create',
			/**
			 * A copy is a create, with one exception: the contract
			 * dropdown must list the SAME contracts the copied
			 * allocations point at. get.contracts.for.customer.or.supplier
			 * hides child contracts (parent_id != null) unless
			 * inEditMode is set, so without this a copied allocation on
			 * a sub-contract would open with an empty Contract select.
			 * The form uses this for that lookup only — everything else
			 * about it stays a create.
			 */
			'isCopy' => $isCopy,
			'locale' => app()->getLocale(),
			'types' => [
				CashExpense::CASH_PAYMENT => __('Cash Payment'),
				CashExpense::PAYABLE_CHEQUE => __('Payable Cheque'),
				CashExpense::OUTGOING_TRANSFER => __('Outgoing Transfer'),
			],
			'currencies' => $currencies,
			'categories' => $cashExpenseCategories->map(fn ($c) => ['id' => $c->getId(), 'name' => $c->getName()])->values(),
			'categoryNames' => $cashExpenseCategoryNames->map(fn ($n) => ['id' => $n->id, 'name' => $n->getName(), 'category_id' => $n->cash_expense_category_id])->values(),
			'branches' => collect($selectedBranches)->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
			'financialInstitutionBanks' => $financialInstitutionBanks->map(fn ($b) => ['id' => $b->id, 'name' => $b->getName()])->values(),
			'accountTypes' => $accountTypes->map(fn ($a) => ['id' => $a->id, 'name' => $a->getName()])->values(),
			'clientsWithContracts' => $clientsWithContracts->map(fn ($c) => ['id' => $c->id, 'name' => $c->getName()])->values(),
			'getContractsForCustomerUrl' => route('get.contracts.for.customer.or.supplier', ['company' => $company->id]),
			'existingAllocations' => $model
				? $model->contracts->map(fn ($contract) => [
					'partner_id' => $contract->client?->id,
					'contract_id' => $contract->id,
					'contract_code' => $contract->getCode(),
					'contract_amount' => $contract->getAmount(),
					'contract_currency' => $contract->getCurrency(),
					'amount' => $contract->pivot->amount,
				])->values()
				: [],
			'model' => $model ? [
				'id' => $model->id,
				'type' => $model->getType(),
				'payment_date' => $model->getPaymentDate(),
				'currency' => $model->getCurrency(),
				'expense_category_id' => $model->getExpenseCategoryId(),
				'cash_expense_category_name_id' => $model->getCashExpenseCategoryNameId(),
				'exchange_rate' => $model->getExchangeRate(),
				'paid_amount' => $model->getPaidAmount(),
				'user_comment' => $model->getUserComment(),
				'delivery_branch_id' => $model->cashPayment?->delivery_branch_id,
				'receipt_number' => $model->getCashPaymentReceiptNumber(),
				'outgoing_transfer_delivery_bank_id' => $model->outgoingTransfer?->delivery_bank_id,
				'outgoing_transfer_account_type' => $model->getOutgoingTransferAccountTypeId(),
				'outgoing_transfer_account_number' => $model->getOutgoingTransferAccountNumber(),
				'is_bank_charges' => $model->isOutgoingTransferBankCharges(),
				'payable_cheque_delivery_bank_id' => $model->payableCheque?->delivery_bank_id,
				'payable_cheque_account_type' => $model->getPayableChequeAccountTypeId(),
				'payable_cheque_account_number' => $model->getPayableChequeAccountNumber(),
				'due_date' => $model->payableCheque?->getDueDate(),
				'cheque_number' => $model->payableCheque?->getChequeNumber(),
				/**
				 * FIX (per bug report, 2026-08-13): the uniqueness checks
				 * for Cheque Number / Receipt Number need the sub-record's
				 * own id (payable_cheques.id / cash_payments.id — NOT
				 * cash_expenses.id) to exclude the record being edited
				 * from the duplicate check. This was never sent from the
				 * new Vue form at all, so in edit mode the exclude value
				 * was always null — and Laravel's query builder treats
				 * where('id', '!=', null) as whereNotNull('id'), which
				 * matches virtually every row including the one being
				 * edited. That's why editing a cheque/receipt falsely
				 * reported "already exists" even with its own unchanged
				 * number.
				 */
				'payable_cheque_id' => $model->payableCheque?->id,
				'cash_payment_id' => $model->cashPayment?->id,
			] : null,
			'submitUrl' => $model && ! $isCopy
				? route('update.cash.expense', ['company' => $company->id, 'cashExpense' => $model->id])
				: route('store.cash.expense', ['company' => $company->id]),
			'backUrl' => route('view.cash.expense', ['company' => $company->id]),
			'getBankBalanceUrl' => route('update.balance.and.net.balance.based.on.account.number', ['company' => $company->id]),
		];

		if ($isCopy && $props['model']) {
			foreach (self::COPY_CLEARED_FIELDS as $field) {
				$props['model'][$field] = null;
			}
		}

		return $props;
	}

	public function store(Company $company , StoreCashExpenseRequest $request
	// , $inUpdateMode = false
	){
		/**
		 * * الحفظ كله جوه ترانزاكشن واحدة
		 * * وأي اتصال بأودو بيتنفذ بعد ما الترانزاكشن تكومِت (شوف OdooSync)
		 */
		return OdooSync::transaction(function () use ($company, $request) {
			return $this->storeWithinTransaction($company, $request);
		});
	}

	protected function storeWithinTransaction(Company $company , StoreCashExpenseRequest $request){
		$moneyType = $request->get('type');
		$bankId = null;
		$paymentBranchName = $request->get('delivery_branch_id') ;
		$data = $request->only(['type','odoo_id','payment_date','currency','cash_expense_category_name_id','user_comment','journal_entry_id','odoo_id']);
		$cashExpenseCategoryNameId= $request->get('cash_expense_category_name_id');
		$cashExpenseCategoryName = CashExpenseCategoryName::find($cashExpenseCategoryNameId);
		$subCategoryName = $cashExpenseCategoryName->getName();
		$date = Carbon::make($data['payment_date'])->format('Y-m-d');
		$currencyName = $data['currency'];
		$data['user_id'] = auth()->user()->id ;
		$data['company_id'] = $company->id ;
		$isCashPaymentOrOutgoingTransfer = $moneyType == CashExpense::CASH_PAYMENT || $moneyType == CashExpense::OUTGOING_TRANSFER;
		// $isCashExpense = $moneyType == CashExpense::CASH_PAYMENT  ;
		// $isOutgoingTransfer = $moneyType == $moneyType == CashExpense::OUTGOING_TRANSFER  ;
		
		$relationData = [];
		$relationName = null ;
		$exchangeRate =  number_unformat($request->input('exchange_rate.'.$moneyType,1)) ;
		
		$paidAmount = $request->input('paid_amount.'.$moneyType ,0) ;
		$paidAmount = unformat_number($paidAmount);
		
		// ⚠️ REAL BUG FIXED HERE (2026-07-24 audit, Stage 5): an explicit
		// zero (or unformat-to-zero) exchange rate previously caused a
		// live division-by-zero on Cash Expense save.
		if (! is_numeric($exchangeRate) || (float) $exchangeRate <= 0) {
			throw \Illuminate\Validation\ValidationException::withMessages([
				'exchange_rate.'.$moneyType => [__('Exchange rate must be greater than zero.')],
			]);
		}

		$paidAmountInPayingCurrency = $paidAmount / $exchangeRate ;
		
		if($moneyType == CashExpense::CASH_PAYMENT){
			$relationData = $request->only(['receipt_number']) ;
			$relationData['delivery_branch_id'] = $this->generateBranchId($paymentBranchName,$company->id) ;
			$relationName = 'cashPayment';
		}
		elseif($moneyType ==CashExpense::OUTGOING_TRANSFER ){
			$relationName = 'outgoingTransfer';
			$bankId = $request->input('delivery_bank_id.'.CashExpense::OUTGOING_TRANSFER) ;
			$relationData = [
				'delivery_bank_id'=>$bankId,
				'actual_payment_date'=>$data['payment_date'],
				'account_number'=>$request->input('account_number.'.CashExpense::OUTGOING_TRANSFER),
				'account_type'=>$request->input('account_type.'.CashExpense::OUTGOING_TRANSFER),
				'is_bank_charges'=>$request->boolean('is_bank_charges')
			];
		}

		elseif($moneyType ==CashExpense::PAYABLE_CHEQUE ){
			$relationName = 'payableCheque';
			$bankId = $request->input('delivery_bank_id.'.CashExpense::PAYABLE_CHEQUE) ;
			$dueDate = $request->input('due_date') ;
			$relationData = [
				'due_date'=>$dueDate ,
				'actual_payment_date'=>$dueDate,
				'cheque_number'=>$request->input('cheque_number'),
				'delivery_bank_id'=>$bankId,
				'account_number'=>$request->input('account_number.'.CashExpense::PAYABLE_CHEQUE),
				'account_type'=>$request->input('account_type.'.CashExpense::PAYABLE_CHEQUE),
				'company_id'=>$company->id,
			];
		}
		$data['paid_amount'] = $paidAmount ;
		$amountInCurrency = $paidAmount ;
		$mainFunctionalCurrency = $company->getMainFunctionalCurrency();
		$amountInMainFunctionalCurrency = $currencyName != $mainFunctionalCurrency  ? $amountInCurrency * ForeignExchangeRate::getExchangeRateForCurrencyAndClosestDate($currencyName,$mainFunctionalCurrency,$date,$company->id) : $amountInCurrency ;
		
		$data['amount_in_invoice_currency'] = $paidAmountInPayingCurrency ;
		$data['exchange_rate'] =$exchangeRate ;
		/**
		 * @var CashExpense $cashExpense ;
		 */



		$cashExpense = CashExpense::create($data);
	
	

		 $relationData['company_id'] = $company->id ;
		 $cashExpense->$relationName()->create($relationData);
		 $cashExpense = $cashExpense->refresh();
		 
		$statementDate = $cashExpense->getStatementDate();
		$accountType = AccountType::find($request->input('account_type.'.$moneyType));
		$accountNumber = $request->input('account_number.'.$moneyType) ;
		$deliveryBranchId = $relationData['delivery_branch_id'] ?? null ;
		$cashExpense->handleCreditStatement($company->id , $bankId,$accountType,$accountNumber,$moneyType,$statementDate,$paidAmount,$deliveryBranchId,$currencyName);
		$contracts = $request->get('contracts',[]) ;
		$cashExpense->saveAllocations($contracts);
		
			
		 if($company->hasOdooIntegrationCredentials() && $isCashPaymentOrOutgoingTransfer
		 && $company->withinIntegrationDate($date)
		//  && !$inUpdateMode
		 ){
			/**
			 * * الاتصال بأودو بيتأجل لبعد ما الترانزاكشن تكومِت
			 * * لو أودو ضرب إيرور المصروف بيفضل محفوظ محليًا
			 * * وبيتسجل عليه synced_with_odoo = 0 مع سبب الفشل
			 */
			OdooSync::defer(function () use ($company, $cashExpense, $subCategoryName, $date, $amountInCurrency, $amountInMainFunctionalCurrency, $currencyName, $cashExpenseCategoryName) {
				$analytic_distribution = $cashExpense->formatAnalysisDistribution() ;
				$cashExpenseOdooService = new CashExpenseOdooService($company);
				$journalId = $cashExpenseOdooService->getJournalId($cashExpense) ;
				$creditOdooAccountId=$cashExpenseOdooService->getChartOfAccountId($cashExpense);
				$odooCurrencyId = Currency::getOdooId($currencyName);
				$debitOdooAccountId = $cashExpenseCategoryName->getOdooId();
				$userComment = $cashExpense->getUserComment();
				$result = $cashExpenseOdooService->createCashExpense($subCategoryName,$date,$amountInCurrency,$amountInMainFunctionalCurrency,$journalId,$odooCurrencyId,$debitOdooAccountId,$creditOdooAccountId,$analytic_distribution,null,null,false , $userComment);
				$cashExpense->account_bank_statement_line_id=$result['account_bank_statement_line_id'];
				$cashExpense->journal_entry_id=$result['journal_entry_id'];
				$cashExpense->odoo_reference=$result['reference'];
				$cashExpense->save();
			}, $cashExpense, 'Create Odoo cash expense');
		 }else{
			// cheques 
			$cashExpense->storeNonCustomerOrSupplierOdooExpense(false);
			
		 }
		 
	
		
		
		$activeTab = $moneyType;
		// if($inUpdateMode){
		// 	return $cashExpense;
		// }
		// Presentation-layer only: the old form submitted via jQuery
		// AJAX and read `response.redirectTo` itself to navigate. The
		// new Vue page submits via Inertia's router.post(), which
		// needs a real HTTP redirect to swap pages. Everything above
		// this line — every bit of expense/statement/Odoo handling —
		// is untouched.
		return redirect()->route('view.cash.expense', ['company' => $company->id, 'active' => $activeTab])->with('success', __('Data Store Successfully'));

	}

	public function edit(Company $company , Request $request , cashExpense $cashExpense ,$supplierInvoiceId = null){
		return \Inertia\Inertia::render('CashExpense/Form', $this->buildFormProps($company, $cashExpense));
	}
	public function update(Company $company , StoreCashExpenseRequest $request , cashExpense $cashExpense){
		
		$newType = $request->get('type');
		// $accountNumber =  $request->input('account_number.'.$newType);
		$request->merge([
			// 'journal_entry_id'=>$cashExpense->journal_entry_id,
			// 'account_bank_statement_line_id'=>$cashExpense->account_bank_statement_line_id,
			'odoo_id'=>$cashExpense->odoo_id ,   // انا مش متاكد ان كان الكولوم دا محتاجينه ولا لا 
		]);
		
		// $accountNumberHasChanged = $cashExpense->getAccountNumber() != $accountNumber;
		/**
		 * * التعديل معمول كـ حذف ثم إنشاء
		 * * فلازم يكون كله في ترانزاكشن واحدة
		 */
		/**
		 * Wrapped so the delete+create above records as the single edit it
		 * is, and this record's history follows it onto the new row.
		 * See App\Support\Activity\ActivityLogger::asUpdate().
		 */
		\App\Support\Activity\ActivityLogger::asUpdate($cashExpense, function () use ($company, $request, $cashExpense) {
			OdooSync::transaction(function () use ($company, $request, $cashExpense) {
				$cashExpense->deleteRelations();
				$cashExpense->delete();

				$this->storeWithinTransaction($company, $request);
			});
		});

		 $activeTab = $newType;
		 // Same fix as store() — a real redirect instead of JSON, for
		 // Inertia's router.put() to work correctly.
		 return redirect()->route('view.cash.expense', ['company' => $company->id, 'active' => $activeTab])->with('success', __('Item Has Been Updated Successfully'));
	}
	
	public function destroy(Company $company , CashExpense $cashExpense)
	{
		$activeTab = $cashExpense->getType();
		OdooSync::transaction(function () use ($cashExpense) {
			$cashExpense->deleteRelations();
			$cashExpense->delete();
		});
		return redirect()->route('view.cash.expense',['company'=>$company->id,'active'=>$activeTab])->with('success',__('Cash Expense Has Been Updated Successfully'));
	}
	protected function generateBranchId($nameOrId,$companyId){
		$branch = Branch::where('id',$nameOrId)->first();
			if(!$branch){
				$branch = Branch::create([
					'name'=>$nameOrId,
					'company_id'=>$companyId ,
					'created_by'=>auth()->user()->id
				]);
			}
			return $branch->id ;
	}
	public function markChequesAsPaid(Company $company,MarkChequeAsPaidRequest $request)
	{
		$cashExpenseIds = $request->get('cheques') ;
		$cashExpenseIds = is_array($cashExpenseIds) ? $cashExpenseIds :  explode(',',$cashExpenseIds);
		$data = $request->only(['actual_payment_date']);
		$data['status'] = PayableCheque::PAID;
		foreach($cashExpenseIds as $cashExpenseId){
			$cashExpense = CashExpense::find($cashExpenseId) ;
			/**
			 * @var CashExpense $cashExpense
			 */
			/**
			 * FIX (per bug report, 2026-08-13): for a company WITH Odoo
			 * integration credentials configured, if the Odoo sync step
			 * below fails, the local "mark as paid" change must not
			 * stick either — otherwise CashVero shows the cheque as
			 * paid while Odoo never actually recorded it, and there's
			 * no way to tell from the UI that they've drifted apart.
			 * Wrapping the local update + Odoo sync + statement date
			 * fix in one DB transaction means an Odoo failure rolls
			 * back the local change too, so the cheque stays exactly
			 * as it was before the attempt — safe to just retry.
			 * Companies WITHOUT Odoo credentials are unaffected: the
			 * transaction still commits normally since
			 * markPayableChequeAsPaidInOdoo() is never even called for
			 * them, same as before this fix.
			 */
			try {
				DB::transaction(function () use ($cashExpense, $data, $company) {
					$cashExpense->payableCheque->update($data);

					if ($company->hasOdooIntegrationCredentials()) {
						$cashExpense->markPayableChequeAsPaidInOdoo();
					}

					if ($currentStatement = $cashExpense->getCurrentStatement()) {
						$currentStatement->handleFullDateAfterDateEdit($data['actual_payment_date'],$currentStatement->debit,$currentStatement->credit);
					}
				});
			} catch (\Throwable $e) {
				$message = __('Error While Connecting With Odoo').' : '.$e->getMessage();
				if($request->ajax() && ! $request->header('X-Inertia')){
					return response()->json([
						'status'=>false ,
						'msg'=>$message,
					]);
				}
				return redirect()->route('view.cash.expense',['company'=>$company->id,'active'=>CashExpense::PAYABLE_CHEQUE])->with('fail', $message);
			}

		}
		if($request->ajax() && ! $request->header('X-Inertia')){
			return response()->json([
				'status'=>true ,
				'msg'=>__('Good'),
				'pageLink'=>route('view.cash.expense',['company'=>$company->id,'active'=>CashExpense::PAYABLE_CHEQUE])
			]);
		}
		return redirect()->route('view.cash.expense',['company'=>$company->id,'active'=>CashExpense::PAYABLE_CHEQUE])->with('success', __('Item Has Been Updated Successfully'));

	}

	public function unmarkChequesAsPaid(Company $company, UnmarkChequeAsPaidRequest $request)
	{
		foreach ($request->chequeIds() as $cashExpenseId) {
			/** @var CashExpense $cashExpense */
			$cashExpense = CashExpense::find($cashExpenseId);

			try {
				DB::transaction(function () use ($cashExpense) {
					$cashExpense->revertPayableChequeToUnpaid();
				});
			} catch (\Throwable $e) {
				$message = __('Error While Connecting With Odoo').' : '.$e->getMessage();
				if ($request->ajax() && ! $request->header('X-Inertia')) {
					return response()->json([
						'status' => false,
						'msg' => $message,
					]);
				}

				return redirect()->route('view.cash.expense', ['company' => $company->id, 'active' => CashExpense::PAYABLE_CHEQUE])->with('fail', $message);
			}
		}

		if ($request->ajax() && ! $request->header('X-Inertia')) {
			return response()->json([
				'status' => true,
				'msg' => __('Good'),
				'pageLink' => route('view.cash.expense', ['company' => $company->id, 'active' => CashExpense::PAYABLE_CHEQUE]),
			]);
		}

		return redirect()->route('view.cash.expense', ['company' => $company->id, 'active' => CashExpense::PAYABLE_CHEQUE])
			->with('success', __('Cheque Returned To Unpaid Successfully'));
	}

	public function markOutgoingTransfersAsPaid(Company $company,Request $request)
	{
		$cashExpenseIds = $request->get('cheques') ;
		$cashExpenseIds = is_array($cashExpenseIds) ? $cashExpenseIds :  explode(',',$cashExpenseIds);
		$data = $request->only(['actual_payment_date']);
		$data['status'] = OutgoingTransfer::PAID;
		foreach($cashExpenseIds as $cashExpenseId){
			$cashExpense = CashExpense::find($cashExpenseId) ;
			$cashExpense->outgoingTransfer->update($data);
			if($currentStatement=$cashExpense->getCurrentStatement()){
				$currentStatement->handleFullDateAfterDateEdit(Carbon::make($data['actual_payment_date'])->format('Y-m-d'),$currentStatement->debit,$currentStatement->credit);

			}

		}
		if($request->ajax() && ! $request->header('X-Inertia')){
			return response()->json([
				'status'=>true ,
				'msg'=>__('Good'),
				'pageLink'=>route('view.cash.expense',['company'=>$company->id,'active'=>CashExpense::OUTGOING_TRANSFER])
			]);
		}
		return redirect()->route('view.cash.expense',['company'=>$company->id,'active'=>CashExpense::OUTGOING_TRANSFER])->with('success', __('Item Has Been Updated Successfully'));

	}

	public function getAccountNumbersForAccountType(Company $company ,  Request $request ,  string $accountType,?string $selectedCurrency=null , ?int $financialInstitutionId = 0){
		$accountType = AccountType::find($accountType);
		$accountNumberModel =  ('\App\Models\\'.$accountType->getModelName())::getAllAccountNumberForCurrency($company->id , $selectedCurrency,$financialInstitutionId);
		return response()->json([
			'status'=>true ,
			'data'=>$accountNumberModel
		]);
	}
}
