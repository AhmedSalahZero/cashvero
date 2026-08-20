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
use App\Support\ShareholderAccounts\AccountNumberLabel;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * TimeOfDepositsController
 * ------------------------------------------------------------------
 * Manages "Time of Deposit" (TD) records under a Financial Institution —
 * a fixed-term deposit product with three states: running, matured,
 * and broken (cashed in early, with a penalty).
 *
 * Responsibilities of this controller:
 *   - List TDs for a financial institution, split by state (index)
 *   - Create / edit / delete a TD
 *   - Apply Deposit  → mark a running TD as matured, post interest
 *   - Apply Break    → break a running TD early, post penalty/interest
 *   - Reverse Deposit / Reverse Broken → undo either of the above
 *   - Periodic interest postings and TD renewal-date history are
 *     handled by their own methods/controller (see below) and are
 *     NOT part of this migration pass.
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   ✅ index()          → MIGRATED to Vue + Inertia. Renders
 *                          resources/js/Pages/TimeOfDeposits/Index.vue.
 *   ✅ create() / edit() → MIGRATED to Vue + Inertia. Both render the
 *                          same shared page,
 *                          resources/js/Pages/TimeOfDeposits/Form.vue,
 *                          distinguished by a `mode: 'create' | 'edit'` prop.
 *   ✅ store() / update() / destroy() / applyDeposit() / applyBreak() /
 *      reverseDeposit() → presentation-only change: these already
 *      redirect back to the migrated index() page, so no further
 *      changes were needed here. The financial logic inside each of
 *      these methods is UNCHANGED, deliberately — bank statement
 *      postings, Odoo sync, and interest math are untouched.
 *   ⚠️ reverseBroken() → ONE deliberate bug fix applied here (found
 *      and confirmed with the project owner during modernization, not
 *      a blind change): removed a redundant, unfiltered second call to
 *      deleteButTriggerChangeOnLastElement() that was erroneously
 *      deleting the TD's original funding-deduction bank statement due
 *      to Eloquent relation caching. See the docblock directly above
 *      that line in the method body for the full trace. This is a
 *      real, root-cause fix — not a rewrite of the method's intended
 *      behavior.
 *   ✅ applyPeriodInterest() → triggered from a modal on the migrated
 *      Index.vue page ("Apply Periodic Interest" button, all three
 *      tabs). The method body itself is UNCHANGED, deliberately —
 *      same interest posting logic as before, just reachable from
 *      the new screen.
 *   ✅ viewPeriodInterest() → MIGRATED to Vue + Inertia. Renders
 *      resources/js/Pages/TimeOfDeposits/PeriodInterest.vue.
 *   ✅ deletePeriodInterest() → UNCHANGED, deliberately. Now triggered
 *      from the migrated PeriodInterest.vue page.
 *   🔲 TimeOfDepositRenewalDateController (separate controller entirely)
 *      → NOT YET migrated. Planned as its own follow-up.
 *
 * When migrating the remaining pieces, follow the same pattern used in
 * index(): transform Eloquent models into plain arrays (Inertia
 * serializes props to JSON — it cannot call methods on PHP objects
 * client-side), and pre-resolve any URLs (route() calls) server-side
 * since Ziggy is not installed.
 */
class TimeOfDepositsController
{
    use GeneralFunctions;

    /**
     * Filter a collection of TD records by a single field/value pair
     * and an optional start/end date range. Used by index() for the
     * client's search box (still applied server-side on first load;
     * the Vue page also does light client-side filtering on top).
     */
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
	/**
	 * The main "Time Of Deposit" list — 3 tabs: running, matured, broken.
	 * Each tab has its own date-range filter and dataset.
	 *
	 * ✅ MIGRATED to Vue + Inertia. Renders
	 * resources/js/Pages/TimeOfDeposits/Index.vue.
	 *
	 * Data is flattened into plain arrays with pre-resolved action URLs
	 * (edit/delete/apply-deposit/apply-break/reverse-deposit/reverse-broken)
	 * and permission flags — Vue cannot call PHP model methods or check
	 * Spatie permissions directly, so everything needed is resolved here.
	 */
	public function index(Company $company,Request $request,FinancialInstitution $financialInstitution)
	{
		/**
		 * Date-range defaults, by tab:
		 *
		 * - RUNNING: no default cutoff. These are currently-open
		 *   deposits — there's no natural reason a TD that started 4
		 *   years ago and is still running should be hidden from
		 *   "Running Time Of Deposit" with no visible signal that
		 *   anything was filtered out. The date pickers are still
		 *   available if someone wants to narrow the view themselves.
		 *   (filterByStartDate() already treats null/null as "no
		 *   filter" — see the Collection macro in
		 *   AppServiceProvider::boot() — so this is a default-value
		 *   change only, not a change to that macro's behavior.)
		 *
		 * - MATURED / BROKEN: these are closed, historical records
		 *   that only grow over time, so a rolling default window is
		 *   a reasonable default — but it must be visible to the user,
		 *   not just silently pre-filled. See dateRangeLabel below.
		 */
		$numberOfMonthsBetweenEndDateAndStartDate = 36 ;
		$currentType = $request->input('active',TimeOfDeposit::RUNNING);
		$filterDates = [];
		foreach(TimeOfDeposit::getAllTypes() as $type){
			if ($type === TimeOfDeposit::RUNNING) {
				$startDate = $request->has('startDate') ? $request->input('startDate.'.$type) : null;
				$endDate = $request->has('endDate') ? $request->input('endDate.'.$type) : null;
			} else {
				$startDate = $request->has('startDate') ? $request->input('startDate.'.$type) : now()->subMonths($numberOfMonthsBetweenEndDateAndStartDate)->format('Y-m-d');
				$endDate = $request->has('endDate') ? $request->input('endDate.'.$type) : now()->format('Y-m-d');
			}

			$filterDates[$type] = [
				'startDate'=>$startDate,
				'endDate'=>$endDate,
				'isDefaultWindow'=> $type !== TimeOfDeposit::RUNNING && !$request->has('startDate') && !$request->has('endDate'),
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
		
		/**
		 * Flatten a TD collection into plain arrays for Inertia, with
		 * every action URL this row's row-menu could need pre-resolved.
		 */
		$mapDeposits = function (Collection $deposits) use ($company, $financialInstitution) {
			return $deposits->map(function (TimeOfDeposit $td) use ($company, $financialInstitution) {
				return [
					'id' => $td->id,
					'status' => $td->getStatus(),
					'start_date' => $td->getStartDate(),
					'start_date_formatted' => $td->getStartDateFormatted(),
					'end_date' => $td->getEndDate(),
					'end_date_formatted' => $td->getEndDateFormatted(),
					'account_number' => AccountNumberLabel::forOwnedInstrument($td),
					'amount' => $td->getAmount(),
					'amount_formatted' => $td->getAmountFormatted(),
					'currency' => $td->getCurrency(),
					'interest_rate_formatted' => $td->getInterestRateFormatted(),
					'interest_amount' => $td->getInterestAmount(),
					'interest_amount_formatted' => $td->getInterestAmountFormatted(),
					'break_date_formatted' => $td->getBreakDateFormatted(),
					'break_interest_amount_formatted' => $td->getBreakInterestAmountFormatted(),
					'blocked_against_formatted' => $td->getBlockedAgainstFormatted(),
					'is_due_today_or_greater' => $td->isDueTodayOrGreater(),
					'edit_url' => route('edit.time.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'timeOfDeposit' => $td->id]),
					'delete_url' => route('delete.time.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'timeOfDeposit' => $td->id]),
					'apply_deposit_url' => route('apply.deposit.to.time.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'timeOfDeposit' => $td->id]),
					'apply_break_url' => route('apply.break.to.time.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'timeOfDeposit' => $td->id]),
					'apply_period_interest_url' => route('apply.period.interest.to.time.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'timeOfDeposit' => $td->id]),
					'reverse_deposit_url' => route('reverse.deposit.to.time.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'timeOfDeposit' => $td->id]),
					'reverse_broken_url' => route('reverse.broken.to.time.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'timeOfDeposit' => $td->id]),
					'view_period_interest_url' => route('view.period.interest.to.time.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'timeOfDeposit' => $td->id]),
					'renewal_date_url' => route('time.of.deposit.renewal.date', ['company' => $company->id, 'timeOfDeposit' => $td->id]),
				];
			})->values();
		};

		return \Inertia\Inertia::render('TimeOfDeposits/Index', [
			'company' => ['id' => $company->id],
			'financialInstitution' => [
				'id' => $financialInstitution->id,
				'name' => $financialInstitution->getName(),
			],
			'activeTab' => $currentType,
			'filterDates' => $filterDates,
			'canCreate' => hasAuthFor('time_of_deposit.create'),
			'deposits' => [
				TimeOfDeposit::RUNNING => $mapDeposits($runningTimeOfDeposits),
				TimeOfDeposit::MATURED => $mapDeposits($maturedTimeOfDeposits),
				TimeOfDeposit::BROKEN => $mapDeposits($brokenTimeOfDeposits),
			],
			'createUrl' => route('create.time.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
			'tabUrls' => [
				TimeOfDeposit::RUNNING => route('view.time.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'active' => TimeOfDeposit::RUNNING]),
				TimeOfDeposit::MATURED => route('view.time.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'active' => TimeOfDeposit::MATURED]),
				TimeOfDeposit::BROKEN => route('view.time.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'active' => TimeOfDeposit::BROKEN]),
			],
			'backUrl' => route('view.all.bank.accounts', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
			'navUrls' => [
				'home' => route('home', ['company' => $company->id]),
				'bank_accounts' => route('view.financial.institutions', ['company' => $company->id, 'active' => 'bank']),
				'customers' => route('partners.index', ['company' => $company->id, 'type' => 'customers']),
				'suppliers' => route('partners.index', ['company' => $company->id, 'type' => 'suppliers']),
				'notifications' => route('view.notifications', ['company' => $company->id, 'type' => 'all']),
			],
		]);
    }

	/**
	 * Shows the "Add Time Of Deposit" form.
	 *
	 * ✅ MIGRATED to Vue + Inertia — shares the same page component as
	 * edit() (resources/js/Pages/TimeOfDeposits/Form.vue), distinguished
	 * by the `mode: 'create'` prop. store() is UNCHANGED, deliberately.
	 */
	public function create(Company $company,FinancialInstitution $financialInstitution)
	{
		$banks = Bank::pluck('view_name','id');
		$selectedBranches =  Branch::getBranchesForCurrentCompany($company->id) ;
		$accountTypes = AccountType::onlyCurrentAccount()->get();
		
		/**
		 * * عباره عن حساب جاري فقط
		 */
		$accounts = $financialInstitution->accounts ;
        return \Inertia\Inertia::render('TimeOfDeposits/Form', [
			'mode' => 'create',
			'company' => ['id' => $company->id],
			'financialInstitution' => ['id' => $financialInstitution->id, 'name' => $financialInstitution->getName()],
			'accounts' => $accounts->map(fn ($a) => [
				'id' => $a->getId(),
				'account_number' => AccountNumberLabel::forOwnedInstrument($a),
				'currency' => $a->getCurrency(),
				'is_active' => (bool) $a->is_active,
			])->values(),
			'currencies' => getCurrencies(),
			'hasOdooIntegration' => $company->hasOdooIntegrationCredentials(),
			// Shareholder ownership control — docs/shareholder-accounts.md
			...\App\Support\ShareholderAccounts\ShareholderAccountAccess::formProps($company->id),
			'model' => null,
			'submitUrl' => route('store.time.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
			'backUrl' => route('view.time.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
			'navUrls' => [
				'home' => route('home', ['company' => $company->id]),
				'bank_accounts' => route('view.financial.institutions', ['company' => $company->id, 'active' => 'bank']),
				'customers' => route('partners.index', ['company' => $company->id, 'type' => 'customers']),
				'suppliers' => route('partners.index', ['company' => $company->id, 'type' => 'suppliers']),
				'notifications' => route('view.notifications', ['company' => $company->id, 'type' => 'all']),
			],
		]);
    }
	public function getCommonDataArr():array 
	{
		return ['start_date','account_number','amount','end_date','currency','interest_rate','interest_amount','maturity_amount_added_to_account_id','odoo_code','deducted_from_account_id','is_at_maturity'];
	}
	/**
	 * Stores a new TD. UNCHANGED, deliberately — bank statement posting
	 * and Odoo sync logic untouched. Redirects to the migrated index().
	 */
	public function store(Company $company  ,FinancialInstitution $financialInstitution, StoreTimeOfDepositRequest $request){
		$data = $request->only( $this->getCommonDataArr());
		$data += \App\Support\ShareholderAccounts\ShareholderAccountAccess::ownershipFromRequest($request);
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
	
	/**
	 * Shows the "Edit Time Of Deposit" form.
	 *
	 * ✅ MIGRATED to Vue + Inertia — shares the same page component as
	 * create() (resources/js/Pages/TimeOfDeposits/Form.vue), distinguished
	 * by the `mode: 'edit'` prop. update() is UNCHANGED, deliberately.
	 */
	public function edit(Company $company , Request $request , FinancialInstitution $financialInstitution , TimeOfDeposit $timeOfDeposit){
		$accounts = $financialInstitution->accounts ;
        return \Inertia\Inertia::render('TimeOfDeposits/Form', [
			'mode' => 'edit',
			'company' => ['id' => $company->id],
			'financialInstitution' => ['id' => $financialInstitution->id, 'name' => $financialInstitution->getName()],
			'accounts' => $accounts->map(fn ($a) => [
				'id' => $a->getId(),
				'account_number' => AccountNumberLabel::forOwnedInstrument($a),
				'currency' => $a->getCurrency(),
				'is_active' => (bool) $a->is_active,
			])->values(),
			'currencies' => getCurrencies(),
			'hasOdooIntegration' => $company->hasOdooIntegrationCredentials(),
			// Shareholder ownership control — docs/shareholder-accounts.md
			...\App\Support\ShareholderAccounts\ShareholderAccountAccess::formProps($company->id),
			'model' => [
				'id' => $timeOfDeposit->id,
				'account_number' => $timeOfDeposit->getAccountNumber(),
				'currency' => $timeOfDeposit->getCurrency(),
				'odoo_code' => $timeOfDeposit->getOdooCode(),
				'deducted_from_account_id' => $timeOfDeposit->deducted_from_account_id,
				'maturity_amount_added_to_account_id' => $timeOfDeposit->getMaturityAmountAddedToAccountId(),
				'start_date' => $timeOfDeposit->getStartDate(),
				'end_date' => $timeOfDeposit->getEndDate(),
				'amount' => $timeOfDeposit->getAmount(),
				'interest_rate' => $timeOfDeposit->getInterestRate(),
				'interest_amount' => $timeOfDeposit->getInterestAmount(),
				'is_at_maturity' => !$timeOfDeposit->isPeriodically(),
			] + \App\Support\ShareholderAccounts\ShareholderAccountAccess::modelProps($timeOfDeposit),
			'submitUrl' => route('update.time.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'timeOfDeposit' => $timeOfDeposit->id]),
			'backUrl' => route('view.time.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
			'navUrls' => [
				'home' => route('home', ['company' => $company->id]),
				'bank_accounts' => route('view.financial.institutions', ['company' => $company->id, 'active' => 'bank']),
				'customers' => route('partners.index', ['company' => $company->id, 'type' => 'customers']),
				'suppliers' => route('partners.index', ['company' => $company->id, 'type' => 'suppliers']),
				'notifications' => route('view.notifications', ['company' => $company->id, 'type' => 'all']),
			],
		]);
	}
	
	/**
	 * Updates an existing TD. UNCHANGED, deliberately. Redirects to the
	 * migrated index().
	 */
	public function update(Company $company , UpdateTimeOfDepositRequest $request , FinancialInstitution $financialInstitution,TimeOfDeposit $timeOfDeposit){
		$deductedFromAccountId = $request->get('deducted_from_account_id',0) ;
	//	$accountNumberHasChanged = $deductedFromAccountId != $timeOfDeposit->getDeductedFromAccountId();
		$data['updated_by'] = auth()->user()->id ;
		$data = $request->only($this->getCommonDataArr());
		$data += \App\Support\ShareholderAccounts\ShareholderAccountAccess::ownershipFromRequest($request);
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
	/**
	 * Deletes a TD and its related bank-statement entries. UNCHANGED,
	 * deliberately.
	 */
	public function destroy(Company $company , FinancialInstitution $financialInstitution , TimeOFDeposit $timeOfDeposit)
	{
		$timeOfDeposit->deletePeriodInterestAmounts();
		$timeOfDeposit->deleteOdooRelations(false);
		CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($timeOfDeposit->currentAccountBankStatements);
		$timeOfDeposit->delete();
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}
	/**
	 * Applies a periodic (non-maturity) interest posting to a TD.
	 * UNCHANGED, deliberately — same interest-posting logic as before.
	 * Now triggered from the "Apply Periodic Interest" modal on the
	 * migrated Index.vue page (all three tabs).
	 */
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
	/**
	 * ✅ MIGRATED to Vue + Inertia. Renders
	 * resources/js/Pages/TimeOfDeposits/PeriodInterest.vue — a simple
	 * table of past periodic interest postings, with a delete action
	 * per row.
	 */
	public function viewPeriodInterest(Company $company,Request $request,FinancialInstitution $financialInstitution,TimeOfDeposit $timeOfDeposit)
	{
		$rows = CurrentAccountBankStatement::where('company_id',$company->id)->where('time_of_deposit_id',$timeOfDeposit->id)->where('is_period_cd_or_td_interest',1)->get();
		return \Inertia\Inertia::render('TimeOfDeposits/PeriodInterest', [
			'company' => ['id' => $company->id],
			'financialInstitution' => ['id' => $financialInstitution->id, 'name' => $financialInstitution->getName()],
			'timeOfDeposit' => ['id' => $timeOfDeposit->id, 'currency' => $timeOfDeposit->getCurrency()],
			'rows' => $rows->map(fn ($row) => [
				'id' => $row->id,
				'date' => $row->date,
				'amount_formatted' => number_format($row->debit, 2),
				'delete_url' => route('delete.period.interest.to.time.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'timeOfDeposit' => $timeOfDeposit->id, 'currentAccountBankStatement' => $row->id]),
			])->values(),
			'backUrl' => route('view.time.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
			'navUrls' => [
				'home' => route('home', ['company' => $company->id]),
				'bank_accounts' => route('view.financial.institutions', ['company' => $company->id, 'active' => 'bank']),
				'customers' => route('partners.index', ['company' => $company->id, 'type' => 'customers']),
				'suppliers' => route('partners.index', ['company' => $company->id, 'type' => 'suppliers']),
				'notifications' => route('view.notifications', ['company' => $company->id, 'type' => 'all']),
			],
		]);
	}
	/**
	 * Deletes a periodic interest posting. UNCHANGED, deliberately.
	 * Now triggered from the migrated PeriodInterest.vue page.
	 */
	public function deletePeriodInterest(Company $company,Request $request,FinancialInstitution $financialInstitution,TimeOfDeposit $timeOfDeposit,CurrentAccountBankStatement $currentAccountBankStatement)
	{
		$timeOfDeposit->deletePeriodInterest($currentAccountBankStatement);
		return redirect()->back()->with('success',__('Item Has Been Updated Successfully'));
	}
	

	/**
	 * * هنا اليوزر هياكد انه نزله الفايدة المستحقة وبالتالي هنزلها في حسابه الجاري اللي هو اختارة من الفورمة
	 */
	/**
	 * Marks a running TD as matured and posts the interest/maturity
	 * amount to the current account. UNCHANGED, deliberately. Redirects
	 * to the migrated index().
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
	/**
	 * Reverses applyDeposit() — sends the TD back to running and undoes
	 * its bank-statement postings. UNCHANGED, deliberately.
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
	/**
	 * Breaks a running TD early (with penalty) and posts the relevant
	 * debit/credit entries. UNCHANGED, deliberately.
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
	/**
	 * Reverses applyBreak() — sends the TD back to running and undoes
	 * its bank-statement postings. ONE bug fixed here (see the inline
	 * docblock inside the method body) — everything else is UNCHANGED.
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
		 * ⚠️ REAL BUG FIXED HERE (found during CashVero modernization,
		 * confirmed by the project owner before this fix was applied):
		 *
		 * This method used to call
		 * CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement()
		 * a SECOND time here, with no filter, on
		 * $timeOfDeposit->currentAccountBankStatements.
		 *
		 * Because that relationship is loaded and cached the moment
		 * it's first accessed above (Eloquent caches relations per
		 * model instance), that second, unfiltered call was reusing
		 * the original, full, stale collection — which still included
		 * the TD's original DEDUCTED_FOR_CURRENT_ACCOUNT bank
		 * statement (the entry recording money leaving the funding
		 * account when the TD was first created). That entry was
		 * deliberately excluded from the filtered delete call above,
		 * but the second call deleted it anyway — for real, since its
		 * database row still existed at that point.
		 *
		 * Net effect (only visible on TDs funded from a real account,
		 * i.e. NOT "Opening Balance" — Opening Balance TDs never
		 * create this statement in the first place): after Break →
		 * Reverse Broken, the TD went back to Running (still notionally
		 * holding the deposit) but the bank statement recording that
		 * the money was ever deducted from the account was gone —
		 * so the money effectively reappeared in the account while
		 * the TD still existed. A real double-counting / phantom-cash
		 * bug.
		 *
		 * reverseDeposit() (the sibling method for undoing "Apply
		 * Deposit") never had this problem — it only ever calls
		 * deleteButTriggerChangeOnLastElement() once, correctly
		 * filtered. This method now matches that same, correct,
		 * single-call pattern.
		 */
		return redirect()->route('view.time.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id ,'active'=>$type])->with('success',__('Time Of Deposit Has Been Marked As Matured'));
	}
}