<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreCleanOverdraftRequest;
use App\Http\Requests\UpdateCleanOverdraftRequest;
use App\Models\AccountType;
use App\Models\CleanOverdraft;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\Traits\Controllers\HasOverdraftRate;
use App\Services\Api\OdooService;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * ! No Odoo Service Yet
 */
/**
 * CleanOverdraftController
 * ------------------------------------------------------------------
 * Manages Clean Overdraft facilities — an overdraft NOT secured
 * against a specific CD/TD (unlike Fully Secured Overdraft), just a
 * straightforward limit + rate agreement with the bank. Same
 * rate-history sub-feature as Fully Secured (only the last rate entry
 * is editable/deletable), plus the same "Outstanding Breakdown"
 * repeater for balances brought in from before joining CashVero.
 * No Odoo integration yet (per the original class comment).
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   ✅ index()          → MIGRATED to Vue + Inertia. Renders
 *                          resources/js/Pages/CleanOverdraft/Index.vue.
 *   ✅ create() / edit() → MIGRATED to Vue + Inertia. Both render the
 *                          same shared page,
 *                          resources/js/Pages/CleanOverdraft/Form.vue,
 *                          distinguished by a `mode: 'create' | 'edit'` prop.
 *   ✅ getCommonDataArr() → checked against the actual database schema
 *      (schema_full.txt) as promised in FullySecuredOverdraftController's
 *      docblock — all fields here DO correspond to real columns. No
 *      equivalent bug found here.
 *   ⚠️ update() → 'updated_by' was being set then immediately wiped
 *      out by the next line overwriting the whole $data array — same
 *      bug already found and fixed on Time/Certificates Of Deposit and
 *      Fully Secured Overdraft. Fixed here too.
 *   ⚠️ StoreCleanOverdraftRequest → 'balance_date' was never actually
 *      validated server-side (only a browser-only `required` hint on
 *      the old Blade form), even though the model's boot():created
 *      hook uses it as the `date` for the first rate-history row,
 *      which is NOT NULL in the database — same bug already found and
 *      fixed on Fully Secured Overdraft. Fixed here too, before it
 *      could cause the same crash.
 *   ✅ store() / destroy() → presentation-only change (response type
 *      only, for store()/update()). Financial logic UNCHANGED,
 *      deliberately.
 */
class CleanOverdraftController
{
    use GeneralFunctions , HasOverdraftRate;
	public static function getModelName()
	{
		return CleanOverdraft::class ;
	}
    protected function applyFilter(Request $request,Collection $collection):Collection{
		if(!count($collection)){
			return $collection;
		}
		$searchFieldName = $request->get('field');
		$dateFieldName =  'created_at' ; // change it 
		// $dateFieldName = $searchFieldName === 'balance_date' ? 'balance_date' : 'created_at'; 
		$from = $request->get('from');
		$to = $request->get('to');
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
		->when($request->get('from') , function($collection) use($dateFieldName,$from){
			return $collection->where($dateFieldName,'>=',$from);
		})
		->when($request->get('to') , function($collection) use($dateFieldName,$to){
			return $collection->where($dateFieldName,'<=',$to);
		})
		->sortByDesc('id')->values();
		
		return $collection;
	}
	/**
	 * The main "Clean Overdraft" list — one flat list per financial
	 * institution.
	 *
	 * ✅ MIGRATED to Vue + Inertia. Renders
	 * resources/js/Pages/CleanOverdraft/Index.vue.
	 */
	public function index(Company $company,Request $request,FinancialInstitution $financialInstitution)
	{
		$cleanOverdrafts = $company->cleanOverdrafts->where('financial_institution_id',$financialInstitution->id) ;
		$cleanOverdrafts =   $this->applyFilter($request,$cleanOverdrafts) ;

		$lockableAccountType = AccountType::onlyCleanOverdraft()->first();
		$canUpdate = hasAuthFor('clean_overdraft.update');
		$canDelete = hasAuthFor('clean_overdraft.delete');
		$canCreateRate = hasAuthFor('clean_overdraft.create');

        return \Inertia\Inertia::render('CleanOverdraft/Index', [
			'company' => ['id' => $company->id],
			'financialInstitution' => ['id' => $financialInstitution->id, 'name' => $financialInstitution->getName()],
			'canCreate' => hasAuthFor('clean_overdraft.create'),
			'canUpdate' => $canUpdate,
			'canDelete' => $canDelete,
			'canCreateRate' => $canCreateRate,
			'createUrl' => route('create.clean.overdraft', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
			'rows' => $cleanOverdrafts->map(function (CleanOverdraft $co) use ($company, $financialInstitution, $lockableAccountType) {
				return [
					'id' => $co->id,
					'contract_start_date_formatted' => $co->getCurrentChapterStartDateFormatted(),
					'contract_end_date_formatted' => $co->getContractEndDateFormatted(),
					'account_number' => $co->getAccountNumber(),
					'currency' => $co->getCurrencyFormatted(),
					'limit_formatted' => $co->getLimitFormatted(),
					'borrowing_rate_formatted' => $co->getBorrowingRateFormatted(),
					'margin_rate_formatted' => $co->getMarginRateFormatted(),
					'interest_rate_formatted' => $co->getInterestRateFormatted(),
					'is_active' => (bool) $co->is_active,
					'edit_url' => route('edit.clean.overdraft', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'cleanOverdraft' => $co->id]),
					'delete_url' => route('delete.clean.overdraft', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'cleanOverdraft' => $co->id]),
					'lock_url' => $lockableAccountType ? route('lock.or.unlock.bank.account', ['company' => $company->id, 'accountType' => $lockableAccountType->id, 'accountId' => $co->id]) : null,
					'apply_rate_url' => route('clean-overdraft-apply.rates', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'cleanOverdraft' => $co->id]),
					'renew_url' => route('clean-overdraft.renew', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'cleanOverdraft' => $co->id]),
					'delete_renewal_url' => route('clean-overdraft.delete-renewal', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'cleanOverdraft' => $co->id]),
					'has_renewals' => $co->hasRenewals(),
					'terms_history' => $co->termsHistories->map(fn ($t) => [
						'id' => $t->id,
						'effective_date_formatted' => $t->getEffectiveDateFormatted(),
						'contract_end_date_formatted' => $t->contract_end_date ? \Carbon\Carbon::make($t->contract_end_date)->format('d-m-Y') : null,
						'limit_formatted' => $t->getLimitFormatted(),
						'highest_debt_balance_rate' => $t->highest_debt_balance_rate,
						'admin_fees_rate' => $t->admin_fees_rate,
						'to_be_setteled_max_within_days' => $t->to_be_setteled_max_within_days,
						'is_original' => (bool) $t->is_original,
					])->values(),
					'rates' => $co->rates->map(fn ($rate) => [
						'id' => $rate->id,
						'date_formatted' => $rate->getDateFormatted(),
						'date' => $rate->getDate(),
						'borrowing_rate' => $rate->getBorrowingRate(),
						'borrowing_rate_formatted' => $rate->getBorrowingRateFormatted(),
						'margin_rate' => $rate->getMarginRate(),
						'margin_rate_formatted' => $rate->getMarginRateFormatted(),
						'min_interest_rate' => $rate->getMinInterestRate(),
						'interest_rate_formatted' => $rate->getInterestRateFormatted(),
						'edit_url' => route('clean-overdraft-edit-rates', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'rate' => $rate->id]),
						'delete_url' => route('clean-overdraft-delete-rate', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'rate' => $rate->id]),
					])->values(),
				];
			})->values(),
			'backUrl' => route('view.financial.institutions', ['company' => $company->id, 'active' => 'bank']),
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
	 * Shows the "Add Clean Overdraft" form.
	 *
	 * ✅ MIGRATED to Vue + Inertia — shares the same page component as
	 * edit() (resources/js/Pages/CleanOverdraft/Form.vue), distinguished
	 * by the `mode: 'create'` prop.
	 */
	public function create(Company $company,FinancialInstitution $financialInstitution)
	{
        return \Inertia\Inertia::render('CleanOverdraft/Form', [
			'mode' => 'create',
			'company' => ['id' => $company->id],
			'financialInstitution' => ['id' => $financialInstitution->id, 'name' => $financialInstitution->getName()],
			'currencies' => getCurrencies(),
			'hasOdooIntegration' => $company->hasOdooIntegrationCredentials(),
			'model' => null,
			'submitUrl' => route('store.clean.overdraft', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
			'backUrl' => route('view.clean.overdraft', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
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
		return ['contract_start_date','account_number','contract_end_date','currency','limit','outstanding_balance','balance_date'
		,'highest_debt_balance_rate','admin_fees_rate','to_be_setteled_max_within_days'];
	}
	/**
	 * Stores a new Clean Overdraft. Financial logic — the initial
	 * "active-limit" bank statement row, outstanding breakdown, and
	 * (via the model's boot():created hook) the first rate history
	 * entry — is UNCHANGED, deliberately. Only the response type
	 * changed: a plain redirect instead of a raw JSON body, so Inertia
	 * can handle it natively.
	 */
	public function store(Company $company  ,FinancialInstitution $financialInstitution, StoreCleanOverdraftRequest $request){

		$data = $request->only( $this->getCommonDataArr());
		foreach(['contract_start_date','contract_end_date','balance_date'] as $dateField){
			$data[$dateField] = $request->get($dateField) ? Carbon::make($request->get($dateField))->format('Y-m-d'):null;
		}
		$data['created_by'] = auth()->user()->id ;
		$data['company_id'] = $company->id ;
		$odooCode = $request->get('odoo_code');
		if($company->hasOdooIntegrationCredentials() && $odooCode){
			$odooService = new OdooService($company);
			$chartOfAccountId = $odooService->getChartOfAccountIdFromOdooCode($odooCode);
			$data['odoo_code'] = $odooCode ;
			$data['odoo_id'] = $chartOfAccountId ;
			$data['journal_id'] = $odooService->getJournalIdFromChartOfAccountId($chartOfAccountId) ;
		}
		/**
		 * @var CleanOverdraft $cleanOverdraft 
		 */
		$cleanOverdraft = $financialInstitution->cleanOverdrafts()->create($data);
		/**
		 * Real bug fixed here (client-flagged, 2026-08-11) — see the
		 * full explanation on CleanOverdraft::createOriginalTermsHistory().
		 */
		$cleanOverdraft->createOriginalTermsHistory();
		
		$cleanOverdraft->handleEndOfMonthInterestForContractStatements($data['contract_start_date'],$data['contract_end_date'],$company->id);
		
		
		/**
		 * Client-directed rework (2026-08-11): now goes through the
		 * single authoritative updateLimitRaw() instead of duplicating
		 * the same row-building logic inline — this is what previously
		 * caused this row's label to drift out of sync (created here
		 * with one label, then silently overwritten with a different
		 * one by update()'s own now-removed duplicate block below).
		 */
		$cleanOverdraft->updateLimitRaw();
		/**
		 * * Rates Will Be Stored In  Created Observer 
		 */
	
		$type = $request->get('type','clean-over-draft');
		$activeTab = $type ; 
		
		$cleanOverdraft->storeOutstandingBreakdown($request,$company);
		return redirect()->route('view.clean.overdraft',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'active'=>$activeTab])->with('success',__('Data Store Successfully'));
	}

	/**
	 * Shows the "Edit Clean Overdraft" form.
	 *
	 * ✅ MIGRATED to Vue + Inertia — shares the same page component as
	 * create() (resources/js/Pages/CleanOverdraft/Form.vue), distinguished
	 * by the `mode: 'edit'` prop.
	 */
	public function edit(Company $company , Request $request , FinancialInstitution $financialInstitution , CleanOverdraft $cleanOverdraft){
		/**
		 * Client-directed rework (2026-08-10): Outstanding Balance /
		 * Balance Date / Outstanding Breakdown exist only to capture a
		 * one-time starting balance from before the facility joined
		 * CashVero — they have nothing to do with a renewal and must
		 * never be re-submitted once one exists (doing so would feed
		 * the trigger data that looks like it belongs to the current
		 * renewal, which it doesn't). Also: once a renewal exists, the
		 * "Contract Start Date" shown here is the CURRENT chapter's own
		 * start date (the renewal's effective date), not the account's
		 * true original — matching what the Renew popup itself shows.
		 */
		$hasRenewals = $cleanOverdraft->hasRenewals();
		$latestChapter = $cleanOverdraft->getLatestTerms();
        return \Inertia\Inertia::render('CleanOverdraft/Form', [
			'mode' => 'edit',
			'hasRenewals' => $hasRenewals,
			'company' => ['id' => $company->id],
			'financialInstitution' => ['id' => $financialInstitution->id, 'name' => $financialInstitution->getName()],
			'currencies' => getCurrencies(),
			'hasOdooIntegration' => $company->hasOdooIntegrationCredentials(),
			'model' => [
				'id' => $cleanOverdraft->id,
				'contract_start_date' => $hasRenewals ? $latestChapter->effective_date : $cleanOverdraft->getContractStartDate(),
				'contract_end_date' => $cleanOverdraft->getContractEndDate(),
				'account_number' => $cleanOverdraft->getAccountNumber(),
				'odoo_code' => $cleanOverdraft->getOdooCode(),
				'currency' => $cleanOverdraft->getCurrency(),
				'limit' => $cleanOverdraft->getLimit(),
				'outstanding_balance' => $hasRenewals ? null : $cleanOverdraft->getOutstandingBalance(),
				'balance_date' => $hasRenewals ? null : $cleanOverdraft->balance_date,
				'highest_debt_balance_rate' => $cleanOverdraft->highest_debt_balance_rate,
				'admin_fees_rate' => $cleanOverdraft->admin_fees_rate,
				'to_be_setteled_max_within_days' => $cleanOverdraft->getMaxSettlementDays(),
				'outstanding_breakdowns' => $hasRenewals ? [] : $cleanOverdraft->outstandingBreakdowns->map(fn ($b) => [
					'settlement_date' => $b->settlement_date,
					'amount' => $b->amount,
				])->values(),
			],
			'submitUrl' => route('update.clean.overdraft', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'cleanOverdraft' => $cleanOverdraft->id]),
			'backUrl' => route('view.clean.overdraft', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
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
	 * Updates an existing Clean Overdraft's main details. UNCHANGED
	 * financial logic, deliberately. Two fixes here:
	 *   1. $data['updated_by'] was being set, then immediately wiped
	 *      out by the very next line overwriting the whole array —
	 *      same bug already found and fixed on Time/Certificates Of
	 *      Deposit and Fully Secured Overdraft.
	 *   2. The response was changed from a raw JSON body to a normal
	 *      redirect (Inertia needs a redirect or Inertia::render(),
	 *      not an arbitrary JSON payload) — presentation-layer
	 *      plumbing only, nothing about what gets saved has changed.
	 */
	public function update(Company $company , UpdateCleanOverdraftRequest $request , FinancialInstitution $financialInstitution,CleanOverdraft $cleanOverdraft){
		/**
		 * Client-directed rework (2026-08-10): once a renewal exists,
		 * this form no longer submits account_number/currency/odoo_code/
		 * outstanding_balance/balance_date/contract_start_date at all
		 * (see edit() and Form.vue) — so only pull the fields that are
		 * actually still relevant to "correcting the current chapter's
		 * numbers". Using $request->only() with a smaller field list
		 * means anything not listed is left completely untouched in the
		 * database, rather than being overwritten with null.
		 */
		$hasRenewals = $cleanOverdraft->hasRenewals();
		$fieldsToUpdate = $hasRenewals
			? ['contract_end_date','limit','highest_debt_balance_rate','admin_fees_rate','to_be_setteled_max_within_days']
			: $this->getCommonDataArr();
		$data = $request->only($fieldsToUpdate);
		$data['updated_by'] = auth()->user()->id ;
		$dateFields = $hasRenewals ? ['contract_end_date'] : ['contract_start_date','contract_end_date','balance_date'];
		foreach($dateFields as $dateField){
			$data[$dateField] = $request->get($dateField) ? Carbon::make($request->get($dateField))->format('Y-m-d'):null;
		}
		if($company->hasOdooIntegrationCredentials() && ! $hasRenewals){
			$odooService = new OdooService($company);
			$odooCode = $request->get('odoo_code');
			$chartOfAccountId = $odooService->getChartOfAccountIdFromOdooCode($odooCode);
			$data['odoo_code'] = $odooCode ;
			$data['odoo_id'] = $chartOfAccountId ;
			$data['journal_id'] = $odooService->getJournalIdFromChartOfAccountId($chartOfAccountId) ;
		}
		$cleanOverdraft->update($data);
		/**
		 * ⚠️ REAL BUG FIXED HERE (client-flagged, 2026-08-10), REVISED
		 * (client-corrected, 2026-08-10): Edit is always allowed — it's
		 * editing whichever chapter is CURRENTLY the live, running
		 * contract (the client's call: only past/superseded chapters
		 * should ever be frozen, never the current one). So this now
		 * syncs the LATEST terms-history row, not just the "Original"
		 * one — covering both "never renewed yet" and "currently on a
		 * renewal" cases the same way. Deliberately does NOT touch that
		 * row's `effective_date`: for the Original chapter that's the
		 * account's true start date (fine to keep in step with
		 * contract_start_date below), but for a renewal chapter,
		 * effective_date is the renewal's own date and has nothing to
		 * do with this form — only Renew/Delete-Renewal ever change it.
		 */
		$latestChapter = $cleanOverdraft->getLatestTerms();
		if ($latestChapter) {
			$latestChapter->update([
				'effective_date' => $latestChapter->is_original ? $cleanOverdraft->contract_start_date : $latestChapter->effective_date,
				'limit' => $cleanOverdraft->limit,
				'highest_debt_balance_rate' => $cleanOverdraft->highest_debt_balance_rate,
				'admin_fees_rate' => $cleanOverdraft->admin_fees_rate,
				'to_be_setteled_max_within_days' => $cleanOverdraft->to_be_setteled_max_within_days,
				'contract_end_date' => $cleanOverdraft->contract_end_date,
			]);
		}
		$cleanOverdraft->handleEndOfMonthInterestForContractStatements($cleanOverdraft->contract_start_date,$cleanOverdraft->contract_end_date,$company->id);
		if (! $hasRenewals) {
			$cleanOverdraft->storeOutstandingBreakdown($request,$company);
		}
		/**
		 * Client-directed rework (2026-08-11): this used to be followed
		 * by a second, hand-built copy of the exact same "active-limit"
		 * row logic — which is what was silently overwriting the label
		 * back to a bare '-' on every single Edit save, right after
		 * updateLimitRaw() had just set it correctly. Removed; this one
		 * call is now the only place that ever touches this row.
		 */
		$cleanOverdraft->updateLimitRaw();
		$type = $request->get('type','clean-over-draft');
		$activeTab = $type ;
		return redirect()->route('view.clean.overdraft',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'active'=>$activeTab])->with('success',__('Item Has Been Updated Successfully'));
	}
	
	/**
	 * Deletes a Clean Overdraft. UNCHANGED, deliberately — the model's
	 * deleting() hook already cleans up its rates and bank statements.
	 */
	public function destroy(Company $company , FinancialInstitution $financialInstitution , CleanOverdraft $cleanOverdraft)
	{
		/**
		 * Client-confirmed rule (2026-08-10): can't delete a facility
		 * that still has real transactions against it — remove those
		 * first. See CleanOverdraft::hasAnyTransactions() for exactly
		 * what counts (system marker rows with zero amounts don't).
		 */
		if ($cleanOverdraft->hasAnyTransactions()) {
			return redirect()->back()->withErrors([
				'delete' => __('This facility cannot be deleted because it still has transactions. Please delete all related transactions first.'),
			]);
		}
		$cleanOverdraft->delete();
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}

	/**
	 * Facility Renewal — Phase 1.
	 *
	 * Records a new dated set of terms for an EXISTING Clean Overdraft.
	 * Unlike store(), this never creates a new clean_overdrafts row and
	 * never touches account_number — so the "account number already
	 * exists" validation never comes into play here, by design.
	 */
	public function renew(Company $company, \App\Http\Requests\RenewCleanOverdraftRequest $request, FinancialInstitution $financialInstitution, CleanOverdraft $cleanOverdraft)
	{
		$effectiveDate = Carbon::make($request->get('effective_date'))->format('Y-m-d');
		$contractEndDate = $request->get('contract_end_date')
			? Carbon::make($request->get('contract_end_date'))->format('Y-m-d')
			: null;

		try {
			$cleanOverdraft->renew($effectiveDate, [
				'limit' => $request->get('limit'),
				'highest_debt_balance_rate' => $request->get('highest_debt_balance_rate'),
				'admin_fees_rate' => $request->get('admin_fees_rate'),
				'to_be_setteled_max_within_days' => $request->get('to_be_setteled_max_within_days'),
				'contract_end_date' => $contractEndDate,
				'notes' => $request->get('notes'),
			], auth()->user()->id);
		} catch (\InvalidArgumentException $e) {
			return redirect()->back()->withErrors(['effective_date' => $e->getMessage()]);
		}

		return redirect()
			->route('view.clean.overdraft', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id])
			->with('success', __('Facility Renewed Successfully'));
	}

	/**
	 * Deletes the facility's most recent renewal only — see
	 * CleanOverdraft::deleteLatestRenewal() for the full rules
	 * (blocked if transactions exist on/after the renewal's date;
	 * reverts the facility to its previous chapter's terms; Edit
	 * unlocks again if that previous chapter is the Original one).
	 */
	public function deleteRenewal(Company $company, FinancialInstitution $financialInstitution, CleanOverdraft $cleanOverdraft)
	{
		try {
			$cleanOverdraft->deleteLatestRenewal();
		} catch (\InvalidArgumentException $e) {
			return redirect()->back()->withErrors(['renewal' => $e->getMessage()]);
		}

		return redirect()->back()->with('success', __('Renewal Deleted — Facility Reverted To Previous Terms'));
	}

	
}
