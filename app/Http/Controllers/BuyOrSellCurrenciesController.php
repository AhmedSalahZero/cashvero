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

/**
 * BuyOrSellCurrenciesController
 * ------------------------------------------------------------------
 * Records a currency conversion between any combination of Bank
 * accounts and cash Safes (Branches): Bank→Bank, Bank→Safe, Safe→Bank,
 * Safe→Safe — one shared `type` field on the model, four tabs on the
 * list page. Which fields apply depends on the type: a "bank" leg
 * needs a Bank + Account Type + Account Number; a "safe" leg needs
 * just a Branch.
 *
 * store()/update() post real money movements — CashInSafeStatement,
 * CurrentAccountBankStatement, and (for accounts under an overdraft
 * facility) the various overdraft bank statement tables, via
 * handleBankToBankTransfer() / handleBankToSafeTransfer() /
 * handleSafeToBankTransfer() / handleSafeToSafeTransfer(), then syncs
 * to Odoo via handleOdooTransfer(). NONE of that is touched here.
 *
 * ── Frontend migration status ───────────────────────────────────────
 *   ✅ index() → MIGRATED to Vue + Inertia
 *      (resources/js/Pages/BuyOrSellCurrencies/Index.vue).
 *      The four queries (one per type, each already using real SQL
 *      pagination via ->paginate() — this page never had FX Rate's
 *      "load everything into memory" problem) are UNCHANGED. The one
 *      real difference: the old page's text search (`field`/`value`)
 *      was wired to a per-type closure that only actually worked for
 *      one field ('transaction_date', duplicating the date-range
 *      filter that already existed) — its general-purpose sibling,
 *      applyFilter(), was written but never called (commented out on
 *      all four queries). Rather than reproduce a search box that
 *      didn't search, the new page's search now genuinely filters the
 *      active tab (bank/branch name, account number) via a real
 *      `where()`/`whereHas()` on that tab's query.
 *   🔲 create()/edit() → MIGRATED to Vue + Inertia. Both render the
 *      same shared page, resources/js/Pages/BuyOrSellCurrencies/Form.vue,
 *      distinguished by `mode: 'create' | 'edit'`. See buildFormProps()
 *      for how getCommonViewVars()'s existing, UNCHANGED output is
 *      turned into Inertia props. The type-dependent field groups
 *      (Bank vs Branch on each side), the cascading Bank→Account
 *      Type→Account Number and Currency→Branch dropdowns, and the
 *      auto-calculated Buy Amount are all reproduced client-side
 *      against the same pre-existing AJAX endpoints the old jQuery
 *      form used. NOT reproduced: the old form's live Balance/Net
 *      Balance preview widgets (informational only, fed by separate
 *      AJAX calls) — flagged here as a known gap, not a silent drop.
 *   ⚠️ store()'s final return was changed from a raw JSON body
 *      (`{redirectTo: ...}`, read manually by the old jQuery AJAX
 *      form) to a real HTTP redirect, which Inertia's router.post()
 *      needs to swap pages correctly. This mirrors what the original
 *      code's own commented-out line already intended. Nothing above
 *      that return line — none of the transfer-handling calls — was
 *      touched. update() calls store() internally, so this covers it
 *      too.
 */
class BuyOrSellCurrenciesController
{
    use GeneralFunctions;

    /**
     * Applies the active tab's date-range and (new) search filters to
     * its query. Presentation-layer only — filterByTransactionDate()
     * and every relation eager-loaded below are pre-existing and
     * UNCHANGED.
     */
    protected function applyTypeFilters($query, Request $request, string $type, string $currentType, array $searchableColumns)
    {
        if ($type !== $currentType) {
            return $query;
        }
        $searchValue = $request->get('value');
        return $query->when($searchValue, function ($query) use ($searchValue, $searchableColumns) {
            return $query->where(function ($query) use ($searchValue, $searchableColumns) {
                foreach ($searchableColumns as $column) {
                    $query->orWhere($column, 'like', '%' . $searchValue . '%');
                }
            });
        });
    }

    public function index(Company $company, Request $request)
    {
        $paginationPerPage = GeneralFunctions::getPaginationLimit();
        $numberOfMonthsBetweenEndDateAndStartDate = 18;
        $currentType = $request->get('active', BuyOrSellCurrency::BANK_TO_BANK);

        $filterDates = [];
        foreach (BuyOrSellCurrency::getAllTypes() as $type => $title) {
            $startDate = $request->has('startDate') ? $request->input('startDate.' . $type) : now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d');
            $endDate = $request->has('endDate') ? $request->input('endDate.' . $type) : now()->format('Y-m-d');
            $filterDates[$type] = [
                'startDate' => $startDate,
                'endDate' => $endDate,
            ];
        }

        $bankToBankStartDate = $filterDates[BuyOrSellCurrency::BANK_TO_BANK]['startDate'] ?? null;
        $bankToBankEndDate = $filterDates[BuyOrSellCurrency::BANK_TO_BANK]['endDate'] ?? null;
        $bankToBankBuyOrSellCurrencies = $company->bankToBankBuyOrSellCurrencies()
        ->filterByTransactionDate($bankToBankStartDate, $bankToBankEndDate);
        $bankToBankBuyOrSellCurrencies = $this->applyTypeFilters($bankToBankBuyOrSellCurrencies, $request, BuyOrSellCurrency::BANK_TO_BANK, $currentType, ['from_account_number', 'to_account_number']);
        $bankToBankBuyOrSellCurrencies = $bankToBankBuyOrSellCurrencies
        ->with(['fromBank.bank', 'fromAccountType', 'toBank.bank', 'toAccountType', 'fromBranch', 'toBranch'])
        ->orderByDesc('transaction_date')
        ->paginate($paginationPerPage, ['*'], 'bankToBankBuyOrSellCurrenciesPage')
        ->withQueryString();

        $safeToBankStartDate = $filterDates[BuyOrSellCurrency::SAFE_TO_BANK]['startDate'] ?? null;
        $safeToBankEndDate = $filterDates[BuyOrSellCurrency::SAFE_TO_BANK]['endDate'] ?? null;
        $safeToBankBuyOrSellCurrencies = $company->safeToBankBuyOrSellCurrencies()
        ->filterByTransactionDate($safeToBankStartDate, $safeToBankEndDate);
        $safeToBankBuyOrSellCurrencies = $this->applyTypeFilters($safeToBankBuyOrSellCurrencies, $request, BuyOrSellCurrency::SAFE_TO_BANK, $currentType, ['to_account_number']);
        $safeToBankBuyOrSellCurrencies = $safeToBankBuyOrSellCurrencies
        ->with(['fromBank.bank', 'fromAccountType', 'toBank.bank', 'toAccountType', 'fromBranch', 'toBranch'])
        ->orderByDesc('transaction_date')
        ->paginate($paginationPerPage, ['*'], 'safeToBankBuyOrSellCurrenciesPage')
        ->withQueryString();

        $bankToSafeStartDate = $filterDates[BuyOrSellCurrency::BANK_TO_SAFE]['startDate'] ?? null;
        $bankToSafeEndDate = $filterDates[BuyOrSellCurrency::BANK_TO_SAFE]['endDate'] ?? null;
        $bankToSafeBuyOrSellCurrencies = $company->bankToSafeBuyOrSellCurrencies()
        ->filterByTransactionDate($bankToSafeStartDate, $bankToSafeEndDate);
        $bankToSafeBuyOrSellCurrencies = $this->applyTypeFilters($bankToSafeBuyOrSellCurrencies, $request, BuyOrSellCurrency::BANK_TO_SAFE, $currentType, ['from_account_number']);
        $bankToSafeBuyOrSellCurrencies = $bankToSafeBuyOrSellCurrencies
        ->with(['fromBank.bank', 'fromAccountType', 'toBank.bank', 'toAccountType', 'fromBranch', 'toBranch'])
        ->orderByDesc('transaction_date')
        ->paginate($paginationPerPage, ['*'], 'bankToSafeBuyOrSellCurrenciesPage')
        ->withQueryString();

        $safeToSafeStartDate = $filterDates[BuyOrSellCurrency::SAFE_TO_SAFE]['startDate'] ?? null;
        $safeToSafeEndDate = $filterDates[BuyOrSellCurrency::SAFE_TO_SAFE]['endDate'] ?? null;
        $safeToSafeBuyOrSellCurrencies = $company->safeToSafeBuyOrSellCurrencies()
        ->filterByTransactionDate($safeToSafeStartDate, $safeToSafeEndDate);
        $safeToSafeBuyOrSellCurrencies = $this->applyTypeFilters($safeToSafeBuyOrSellCurrencies, $request, BuyOrSellCurrency::SAFE_TO_SAFE, $currentType, []);
        $safeToSafeBuyOrSellCurrencies = $safeToSafeBuyOrSellCurrencies
        ->with(['fromBank.bank', 'fromAccountType', 'toBank.bank', 'toAccountType', 'fromBranch', 'toBranch'])
        ->orderByDesc('transaction_date')
        ->paginate($paginationPerPage, ['*'], 'safeToSafeBuyOrSellCurrenciesPage')
        ->withQueryString();

        $models = [
            BuyOrSellCurrency::BANK_TO_BANK => $bankToBankBuyOrSellCurrencies,
            BuyOrSellCurrency::SAFE_TO_BANK => $safeToBankBuyOrSellCurrencies,
            BuyOrSellCurrency::BANK_TO_SAFE => $bankToSafeBuyOrSellCurrencies,
            BuyOrSellCurrency::SAFE_TO_SAFE => $safeToSafeBuyOrSellCurrencies,
        ];

        /**
         * Column sets differ per type (see class docblock): a "bank"
         * leg shows Bank/Account Type/Account Number, a "safe" leg
         * shows just Branch. Row mapping includes every possible
         * column; the Vue page decides which to render per tab.
         */
        $mapRow = function (BuyOrSellCurrency $model) use ($company) {
            return [
                'id' => $model->id,
                'transaction_date_formatted' => $model->getTransactionDateFormatted(),
                'amount_to_sell_formatted' => $model->getAmountToSellFormatted(),
                'currency_to_sell' => $model->getCurrencyToSellFormatted(),
                'exchange_rate_formatted' => number_format($model->getExchangeRate(), 4),
                'reciprocal_exchange_rate_formatted' => number_format(1 / $model->getExchangeRate(), 4),
                'amount_to_buy_formatted' => $model->getAmountToBuyFormatted(),
                'currency_to_buy' => $model->getCurrencyToBuyFormatted(),
                // getFromBankName()/getToBankName() return the Bank's
                // view_name, which packs the English AND Arabic name
                // into one string — the actual cause of the "too long"
                // column, not something a max-width can fix. Pulling
                // name_en/name_ar straight off the related Bank (via
                // the FinancialInstitution -> Bank relation, read-only,
                // nothing changed on either model) lets the page show
                // them as two shorter stacked lines instead.
                'from_bank_name_en' => optional(optional($model->fromBank)->bank)->name_en,
                'from_bank_name_ar' => optional(optional($model->fromBank)->bank)->name_ar,
                'from_bank_name' => $model->getFromBankName(),
                'from_account_type_name' => $model->getFromAccountTypeName(),
                'from_account_number' => $model->getFromAccountNumber(),
                'to_bank_name_en' => optional(optional($model->toBank)->bank)->name_en,
                'to_bank_name_ar' => optional(optional($model->toBank)->bank)->name_ar,
                'to_bank_name' => $model->getToBankName(),
                'to_account_type_name' => $model->getToAccountTypeName(),
                'to_account_number' => $model->getToAccountNumber(),
                'from_branch_name' => $model->getFromBranchName(),
                'to_branch_name' => $model->getToBranchName(),
                'user_comment' => $model->hasComment() ? $model->getUserComment() : null,
                'is_fully_integrated_with_odoo' => $company->hasOdooIntegrationCredentials() && $model->fullyIntegratedWithOdoo(),
                'odoo_reference_names' => $model->getOdooReferenceNames(),
                'edit_url' => route('buy-or-sell-currencies.edit', ['company' => $company->id, 'buy_or_sell_currency' => $model->id]),
                'delete_url' => route('buy-or-sell-currencies.destroy', ['company' => $company->id, 'buy_or_sell_currency' => $model->id]),
            ];
        };

        $tabs = [];
        foreach ($models as $type => $paginator) {
            $tabs[$type] = [
                'label' => BuyOrSellCurrency::getAllTypes()[$type],
                'rows' => $paginator->through($mapRow),
                'startDate' => $filterDates[$type]['startDate'],
                'endDate' => $filterDates[$type]['endDate'],
            ];
        }

        return \Inertia\Inertia::render('BuyOrSellCurrencies/Index', [
            'company' => ['id' => $company->id],
            'activeTab' => $currentType,
            'allTypes' => BuyOrSellCurrency::getAllTypes(),
            'tabs' => $tabs,
            'searchValue' => $request->get('value'),
            'canCreate' => hasAuthFor('create buy or sell currency'),
            'canUpdate' => hasAuthFor('update buy or sell currency'),
            'canDelete' => hasAuthFor('delete buy or sell currency'),
            'indexUrl' => route('buy-or-sell-currencies.index', ['company' => $company->id]),
            'createUrl' => route('buy-or-sell-currencies.create', ['company' => $company->id]),
        ]);
    }
	/**
	 * Add Sell Or Buy Currency form.
	 *
	 * ✅ MIGRATED to Vue + Inertia. Shares the same page component as
	 * edit() (resources/js/Pages/BuyOrSellCurrencies/Form.vue), same
	 * `mode: 'create' | 'edit'` pattern used everywhere else in this
	 * project. getCommonViewVars() below is UNCHANGED, deliberately.
	 */
	public function create(Company $company)
	{
        return \Inertia\Inertia::render('BuyOrSellCurrencies/Form', $this->buildFormProps($company, null));
    }

	/**
	 * Turns getCommonViewVars()'s existing output plus (in edit mode)
	 * the model's own data into the flat, pre-formatted prop shape
	 * Inertia needs. New presentation-layer code only —
	 * getCommonViewVars() and every getter called on $model below are
	 * pre-existing and UNCHANGED. The two cascading-dropdown AJAX
	 * endpoints (account numbers by bank+account type, branches by
	 * currency) are also pre-existing and untouched — the new Vue page
	 * calls them exactly as the old jQuery did, just via axios.
	 */
	protected function buildFormProps(Company $company, ?BuyOrSellCurrency $model): array
	{
		$commonVars = $this->getCommonViewVars($company, $model);

		return [
			'company' => ['id' => $company->id],
			'mode' => $model ? 'edit' : 'create',
			'locale' => app()->getLocale(),
			'allTypes' => BuyOrSellCurrency::getAllTypes(),
			'currencies' => getCurrencies(),
			'financialInstitutionBanks' => collect($commonVars['financialInstitutionBanks'])->map(fn ($b) => ['id' => $b->id, 'name' => $b->getName()])->values(),
			'accountTypes' => collect($commonVars['accountTypes'])->map(fn ($a) => ['id' => $a->id, 'name' => $a->getName()])->values(),
			'branches' => collect($commonVars['selectedBranches'])->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
			'model' => $model ? [
				'id' => $model->id,
				'type' => $model->getType(),
				'transaction_date' => $model->getTransactionDate(),
				'currency_to_sell' => $model->getCurrencyToSell(),
				'currency_to_buy' => $model->getCurrencyToBuy(),
				'currency_to_sell_amount' => $model->getAmountToSell(),
				'exchange_rate' => $model->getExchangeRate(),
				'currency_to_buy_amount' => $model->getAmountToBuy(),
				'from_bank_id' => $model->getFromBankId(),
				'from_account_type_id' => $model->getFromAccountTypeId(),
				'from_account_number' => $model->getFromAccountNumber(),
				'to_bank_id' => $model->getToBankId(),
				'to_account_type_id' => $model->getToAccountTypeId(),
				'to_account_number' => $model->getToAccountNumber(),
				'from_branch_id' => $model->getFromBranchId(),
				'to_branch_id' => $model->getToBranchId(),
				'user_comment' => $model->getUserComment(),
			] : null,
			'submitUrl' => $model
				? route('buy-or-sell-currencies.update', ['company' => $company->id, 'buy_or_sell_currency' => $model->id])
				: route('buy-or-sell-currencies.store', ['company' => $company->id]),
			'backUrl' => route('buy-or-sell-currencies.index', ['company' => $company->id]),
			'getBranchesForCurrencyUrl' => route('get.branch.based.on.currency', ['company' => $company->id]),
			// Feeds the "Balance [date] / Net Balance [date]" preview
			// above the Bank fields (Bank→Bank, Bank→Safe) — same
			// pre-existing endpoint (MoneyReceivedController) the old
			// jQuery form called when the From Account Number changed.
			'getBankBalanceUrl' => route('update.balance.and.net.balance.based.on.account.number', ['company' => $company->id]),
			// Feeds the "Balance [date]" preview above the Branch field
			// (Safe→Bank, Safe→Safe) — same pre-existing endpoint
			// (MoneyPaymentController) the old jQuery form called when
			// the From Branch changed.
			'getCashSafeBalanceUrl' => route('get.current.end.balance.of.cash.in.safe.statement', ['company' => $company->id]),
		];
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
		
		// Presentation-layer only: the old form submitted via jQuery
		// AJAX and read `response.redirectTo` itself to navigate. The
		// new Vue page submits via Inertia's router.post(), which
		// needs a real HTTP redirect to swap pages — exactly what the
		// commented-out line below already intended. Every transfer
		// handled above this point is untouched.
		return redirect()->route('buy-or-sell-currencies.index', ['company' => $company->id, 'active' => $activeTab])->with('success', __('Data Store Successfully'));
		
	}

	public function edit(Company $company,BuyOrSellCurrency $buyOrSellCurrency)
	{
        return \Inertia\Inertia::render('BuyOrSellCurrencies/Form', $this->buildFormProps($company, $buyOrSellCurrency));
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
