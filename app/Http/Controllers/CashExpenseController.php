<?php
namespace App\Http\Controllers;

use App\Http\Requests\MarkChequeAsPaidRequest;
use App\Http\Requests\StoreCashExpenseRequest;
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
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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
		$cashPaymentsStartDate = $filterDates[CashExpense::CASH_PAYMENT]['startDate'] ?? null ;
		$cashPaymentsEndDate = $filterDates[CashExpense::CASH_PAYMENT]['endDate'] ?? null ;

		$outgoingTransferStartDate = $filterDates[CashExpense::OUTGOING_TRANSFER]['startDate'] ?? null ;
		$outgoingTransferEndDate = $filterDates[CashExpense::OUTGOING_TRANSFER]['endDate'] ?? null ;

		$payableChequesStartDate = $filterDates[CashExpense::PAYABLE_CHEQUE]['startDate'] ?? null ;
		$payableChequesEndDate = $filterDates[CashExpense::PAYABLE_CHEQUE]['endDate'] ?? null ;

		$cashPayments = $company->getCashExpenseCashPayments($cashPaymentsStartDate ,$cashPaymentsEndDate ,$activeTab)->paginate($paginationPerPage,['*'],'cashPaymentsPage')->withQueryString() ;
		$outgoingTransfer = $company->getCashExpenseOutgoingTransfer($outgoingTransferStartDate,$outgoingTransferEndDate,$activeTab)->paginate($paginationPerPage,['*'],'outgoingTransferPage')->withQueryString() ;
		$payableCheques = $company->getCashExpensePayableCheques($payableChequesStartDate,$payableChequesEndDate,$activeTab)->paginate($paginationPerPage,['*'],'payableChequesPage')->withQueryString() ;

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
				'odoo_reference_names' => $model->getOdooReferenceNames(),
				'edit_url' => $model->isOpenBalance() ? null : route('edit.cash.expense', ['company' => $company->id, 'cashExpense' => $model->id]),
				'delete_url' => $model->isOpenBalance() ? null : route('delete.cash.expense', ['company' => $company->id, 'cashExpense' => $model->id]),
			];
		};

		$cashPaymentsMapped = $cashPayments->through(function (CashExpense $model) use ($mapCommon) {
			return array_merge($mapCommon($model), [
				'branch_name' => $model->getCashPaymentBranchName(),
				'receipt_number' => $model->getCashPaymentReceiptNumber(),
			]);
		});
		$outgoingTransferMapped = $outgoingTransfer->through(function (CashExpense $model) use ($mapCommon) {
			return array_merge($mapCommon($model), [
				// getOutgoingTransferDeliveryBankName() returns the Bank's
				// combined view_name (English + Arabic in one string) —
				// the actual cause of the "too long" column. Pulling
				// name_en/name_ar straight off the related Bank lets the
				// page show them as two shorter stacked lines instead.
				'bank_name_en' => optional(optional(optional($model->outgoingTransfer)->deliveryBank)->bank)->name_en,
				'bank_name_ar' => optional(optional(optional($model->outgoingTransfer)->deliveryBank)->bank)->name_ar,
				'bank_name' => $model->getOutgoingTransferDeliveryBankName(),
				'account_type_name' => $model->getOutgoingTransferAccountTypeName(),
				'account_number' => $model->getOutgoingTransferAccountNumber(),
			]);
		});
		$payableChequesMapped = $payableCheques->through(function (CashExpense $model) use ($mapCommon) {
			$dueStatus = $model->payableCheque ? $model->payableCheque->getDueStatusFormatted() : null;
			return array_merge($mapCommon($model), [
				'status' => $model->payableCheque?->getStatusFormatted(),
				'is_paid' => $model->payableCheque?->getStatus() === 'paid',
				'cheque_number' => $model->payableCheque?->getChequeNumber(),
				'bank_name_en' => optional(optional(optional($model->payableCheque)->deliveryBank)->bank)->name_en,
				'bank_name_ar' => optional(optional(optional($model->payableCheque)->deliveryBank)->bank)->name_ar,
				'bank_name' => $model->payableCheque?->getPaymentBankName(),
				'account_type_name' => $model->payableCheque?->getAccountTypeName(),
				'account_number' => $model->payableCheque?->getAccountNumber(),
				'due_date_formatted' => $model->payableCheque?->getDueDateFormatted(),
				'due_status' => $dueStatus,
				'can_mark_paid' => !$model->isOpenBalance() && $model->payableCheque?->getStatus() !== 'paid',
			]);
		});

		return \Inertia\Inertia::render('CashExpense/Index', [
			'company' => ['id' => $company->id],
			'activeTab' => $activeTab,
			'canCreate' => hasAuthFor('create cash expenses'),
			'canUpdate' => hasAuthFor('update cash expenses'),
			'canDelete' => hasAuthFor('delete cash expenses'),
			'tabs' => [
				CashExpense::OUTGOING_TRANSFER => [
					'label' => __('Outgoing Transfer'),
					'rows' => $outgoingTransferMapped,
					'startDate' => $outgoingTransferStartDate,
					'endDate' => $outgoingTransferEndDate,
					'hasBatchCollection' => true,
				],
				CashExpense::CASH_PAYMENT => [
					'label' => __('Cash Payment'),
					'rows' => $cashPaymentsMapped,
					'startDate' => $cashPaymentsStartDate,
					'endDate' => $cashPaymentsEndDate,
					'hasBatchCollection' => false,
				],
				CashExpense::PAYABLE_CHEQUE => [
					'label' => __('Payable Cheques'),
					'rows' => $payableChequesMapped,
					'startDate' => $payableChequesStartDate,
					'endDate' => $payableChequesEndDate,
					'hasBatchCollection' => true,
				],
			],
			'indexUrl' => route('view.cash.expense', ['company' => $company->id]),
			'createUrl' => route('create.cash.expense', ['company' => $company->id]),
			'markChequesAsPaidUrl' => route('cash.expense.payable.cheque.mark.as.paid', ['company' => $company->id]),
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
	 * Turns the old create()/edit()'s existing query logic (all
	 * UNCHANGED below) into the flat, pre-formatted prop shape Inertia
	 * needs. New presentation-layer code only.
	 */
	protected function buildFormProps(Company $company, ?CashExpense $model): array
	{
		$currencies = getCurrencies();
		$clientsWithContracts = Partner::onlyCompany($company->id)->onlyCustomers()->onlyThatHaveCustomerContracts()->get();
		$accountTypes = AccountType::onlyCashAccounts()->get();
		$selectedBranches = Branch::getBranchesForCurrentCompany($company->id);
		$financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get();
		$cashExpenseCategories = CashExpenseCategory::where('company_id', $company->id)->orderBy('name', 'asc')->get();
		$cashExpenseCategoryNames = CashExpenseCategoryName::whereIn('cash_expense_category_id', $cashExpenseCategories->pluck('id'))->get();

		return [
			'company' => ['id' => $company->id],
			'mode' => $model ? 'edit' : 'create',
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
			] : null,
			'submitUrl' => $model
				? route('update.cash.expense', ['company' => $company->id, 'cashExpense' => $model->id])
				: route('store.cash.expense', ['company' => $company->id]),
			'backUrl' => route('view.cash.expense', ['company' => $company->id]),
			'getBankBalanceUrl' => route('update.balance.and.net.balance.based.on.account.number', ['company' => $company->id]),
		];
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
		OdooSync::transaction(function () use ($company, $request, $cashExpense) {
			$cashExpense->deleteRelations();
			$cashExpense->delete();

			$this->storeWithinTransaction($company, $request);
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
			// $chequeDueDate = $cashExpense->payableCheque->due_date;
			$cashExpense->payableCheque->update($data);
			
			// FIX (per bug report): markPayableChequeAsPaidInOdoo() used to
			// run unconditionally and throw RuntimeException("Missing
			// company Odoo DB URL/Name.") for any company without Odoo
			// credentials configured — even though the update() above had
			// already committed successfully, so the cheque silently ended
			// up correctly marked as paid despite the request erroring out.
			// Guarded the same way every other Odoo-touching call in this
			// codebase already is.
			if ($company->hasOdooIntegrationCredentials()) {
				$cashExpense->markPayableChequeAsPaidInOdoo();
			}
			
			
			if($currentStatement = $cashExpense->getCurrentStatement()){
				$currentStatement->handleFullDateAfterDateEdit($data['actual_payment_date'],$currentStatement->debit,$currentStatement->credit);

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
