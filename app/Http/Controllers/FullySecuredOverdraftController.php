<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreFullySecuredOverdraftRequest;
use App\Http\Requests\UpdateFullySecuredOverdraftRequest;
use App\Models\AccountType;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\FullySecuredOverdraft;
use App\Models\Traits\Controllers\HasOverdraftRate;
use App\Services\Api\OdooService;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * FullySecuredOverdraftController
 * ------------------------------------------------------------------
 * Manages Fully Secured Overdraft facilities — an overdraft the bank
 * grants secured against a Time Of Deposit or Certificate Of Deposit
 * the company already holds. The overdraft's limit is the CD/TD's
 * amount × a lending percentage (e.g. 80% of a 100,000 TD = 80,000
 * limit), and its interest rate is the CD/TD's own rate plus a bank
 * margin. Unlike Time/Certificates Of Deposit, there's no
 * running/matured/broken split — just one ongoing list per financial
 * institution — but it has its own rate-history sub-feature (only the
 * last rate entry is editable/deletable, same rule as TD's renewal
 * history) and an "Outstanding Breakdown" repeater for balances
 * brought in from before the company joined CashVero.
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   ✅ index()          → MIGRATED to Vue + Inertia. Renders
 *                          resources/js/Pages/FullySecuredOverdraft/Index.vue.
 *   ✅ create() / edit() → MIGRATED to Vue + Inertia. Both render the
 *                          same shared page,
 *                          resources/js/Pages/FullySecuredOverdraft/Form.vue,
 *                          distinguished by a `mode: 'create' | 'edit'` prop.
 *   ⚠️ getCommonDataArr() → REAL BUG FIXED (confirmed with the project
 *      owner — creating/editing was throwing a server error every
 *      time). See the docblock directly above that method.
 *   ⚠️ update() → ONE additional real bug fixed (missing
 *      cd_or_td_account_id remap + updated_by silently overwritten).
 *      See the docblock directly above that method.
 *   ✅ store() / destroy() → presentation-only change (response type
 *      only, for store()). Financial logic UNCHANGED, deliberately.
 *   ✅ applyRate() / editRate() / deleteRate() (in the shared
 *      HasOverdraftRate trait — used by every overdraft type, not
 *      just this one) → editRate()'s JSON response was converted to a
 *      plain redirect for Inertia compatibility. Financial logic
 *      UNCHANGED, deliberately.
 *
 * ⚠️ Note for whoever migrates the other 3 overdraft types next
 * (Clean Overdraft, Overdraft Against Commercial Paper, Overdraft
 * Against Assignment Of Contract): check getCommonDataArr() on EACH
 * of them against their actual database schema before assuming it's
 * correct — this exact class of bug (listing fields that don't
 * correspond to real columns) was found here and is easy to have been
 * copy-pasted into the others.
 */
class FullySecuredOverdraftController
{
    use GeneralFunctions , HasOverdraftRate;
	public static function getModelName()
	{
		return FullySecuredOverdraft::class ;
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
	 * The main "Fully Secured Overdraft" list — one flat list per
	 * financial institution (no running/matured/broken split).
	 *
	 * ✅ MIGRATED to Vue + Inertia. Renders
	 * resources/js/Pages/FullySecuredOverdraft/Index.vue.
	 *
	 * Each row's full rate history is included so the "Rates" modal
	 * can show/add/edit/delete without a separate page load — Vue
	 * cannot call PHP model methods directly, so every value and URL
	 * the modal needs is pre-resolved here.
	 */
	public function index(Company $company,Request $request,FinancialInstitution $financialInstitution)
	{
		$fullySecuredOverdrafts = $company->fullySecuredOverdrafts->where('financial_institution_id',$financialInstitution->id) ;
		$fullySecuredOverdrafts =   $this->applyFilter($request,$fullySecuredOverdrafts) ;

		$lockableAccountType = AccountType::onlyFullySecuredOverdraft()->first();
		$canUpdate = hasAuthFor('update fully secured overdraft');
		$canDelete = hasAuthFor('delete fully secured overdraft');
		$canCreateRate = hasAuthFor('create fully secured overdraft');

		return \Inertia\Inertia::render('FullySecuredOverdraft/Index', [
			'company' => ['id' => $company->id],
			'financialInstitution' => ['id' => $financialInstitution->id, 'name' => $financialInstitution->getName()],
			'canCreate' => hasAuthFor('create fully secured overdraft'),
			'canUpdate' => $canUpdate,
			'canDelete' => $canDelete,
			'canCreateRate' => $canCreateRate,
			'createUrl' => route('create.fully.secured.overdraft', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
			'rows' => $fullySecuredOverdrafts->map(function (FullySecuredOverdraft $fso) use ($company, $financialInstitution, $lockableAccountType) {
				return [
					'id' => $fso->id,
					'contract_start_date_formatted' => $fso->getContractStartDateFormatted(),
					'contract_end_date_formatted' => $fso->getContractEndDateFormatted(),
					'account_number' => $fso->getAccountNumber(),
					'currency' => $fso->getCurrencyFormatted(),
					'limit_formatted' => $fso->getLimitFormatted(),
					'borrowing_rate_formatted' => $fso->getBorrowingRateFormatted(),
					'margin_rate_formatted' => $fso->getMarginRateFormatted(),
					'interest_rate_formatted' => $fso->getInterestRateFormatted(),
					'is_active' => (bool) $fso->is_active,
					'edit_url' => route('edit.fully.secured.overdraft', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'fullySecuredOverdraft' => $fso->id]),
					'delete_url' => route('delete.fully.secured.overdraft', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'fullySecuredOverdraft' => $fso->id]),
					'lock_url' => $lockableAccountType ? route('lock.or.unlock.bank.account', ['company' => $company->id, 'accountType' => $lockableAccountType->id, 'accountId' => $fso->id]) : null,
					'apply_rate_url' => route('fully-secured-overdraft-apply.rates', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'fullySecuredOverdraft' => $fso->id]),
					'rates' => $fso->rates->map(fn ($rate) => [
						'id' => $rate->id,
						'date_formatted' => $rate->getDateFormatted(),
						'date' => $rate->getDate(),
						'borrowing_rate' => $rate->getBorrowingRate(),
						'borrowing_rate_formatted' => $rate->getBorrowingRateFormatted(),
						'margin_rate' => $rate->getMarginRate(),
						'margin_rate_formatted' => $rate->getMarginRateFormatted(),
						'interest_rate_formatted' => $rate->getInterestRateFormatted(),
						'edit_url' => route('fully-secured-overdraft-edit-rates', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'rate' => $rate->id]),
						'delete_url' => route('fully-secured-overdraft-delete-rate', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'rate' => $rate->id]),
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
	 * Builds the flat list of running Time Of Deposit / Certificate Of
	 * Deposit accounts this financial institution holds, for the
	 * "CD Or TD Information" section of the form. The original Blade
	 * form populated its account-number dropdown via a client-side
	 * AJAX call this codebase doesn't have a traceable server route
	 * for (likely a vendored/compiled JS file not present in this
	 * snapshot) — rather than guess at reconstructing an endpoint I
	 * can't verify, this fetches every eligible account up front and
	 * lets the Vue page filter by type/currency client-side, the same
	 * pattern already used successfully for the currency-based account
	 * filtering on the Time/Certificates Of Deposit forms.
	 */
	protected function buildCdOrTdAccounts(Company $company, FinancialInstitution $financialInstitution): array
	{
		$accounts = [];
		foreach (AccountType::onlyCdOrTdAccounts()->get() as $accountType) {
			$modelClass = '\\App\\Models\\'.$accountType->getModelName();
			$records = $modelClass::where('company_id', $company->id)
				->where('financial_institution_id', $financialInstitution->id)
				->where('status', $modelClass::RUNNING)
				->get();
			foreach ($records as $record) {
				$accounts[] = [
					'id' => $record->id,
					'account_type_id' => $accountType->id,
					'account_number' => $record->getAccountNumber(),
					'currency' => $record->getCurrency(),
					'amount' => $record->getAmount(),
					'interest_rate' => $record->getInterestRate(),
				];
			}
		}
		return $accounts;
	}

	/**
	 * Shows the "Add Fully Secured Overdraft" form.
	 *
	 * ✅ MIGRATED to Vue + Inertia — shares the same page component as
	 * edit() (resources/js/Pages/FullySecuredOverdraft/Form.vue),
	 * distinguished by the `mode: 'create'` prop.
	 */
	public function create(Company $company,FinancialInstitution $financialInstitution)
	{
        return \Inertia\Inertia::render('FullySecuredOverdraft/Form', [
			'mode' => 'create',
			'company' => ['id' => $company->id],
			'financialInstitution' => ['id' => $financialInstitution->id, 'name' => $financialInstitution->getName()],
			'currencies' => getCurrencies(),
			'hasOdooIntegration' => $company->hasOdooIntegrationCredentials(),
			'cdOrTdAccountTypes' => AccountType::onlyCdOrTdAccounts()->get()->map(fn ($t) => ['id' => $t->id, 'name' => $t->getName()])->values(),
			'cdOrTdAccounts' => $this->buildCdOrTdAccounts($company, $financialInstitution),
			'model' => null,
			'submitUrl' => route('store.fully.secured.overdraft', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
			'backUrl' => route('view.fully.secured.overdraft', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
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
	 * ⚠️ REAL BUG FIXED HERE (found and confirmed with the project
	 * owner before this fix was applied — creating or editing a
	 * Fully Secured Overdraft was throwing a server error every time).
	 *
	 * This used to also list 'borrowing_rate', 'bank_margin_rate',
	 * 'interest_rate', 'min_interest_rate', and 'cd_or_td_id' —  none
	 * of which are real columns on the fully_secured_overdrafts table
	 * (confirmed against the actual database schema, not just the
	 * model's docblock). The rate values genuinely belong to the
	 * separate fully_secured_overdraft_rates history table (handled by
	 * storeRate() / the Apply Rate feature) — the main record was
	 * never meant to store them directly. Including these as keys in
	 * $data caused create()/update() to attempt an INSERT/UPDATE with
	 * unknown columns, which is a hard SQL error, not a silent no-op —
	 * so saving a new or edited Fully Secured Overdraft was crashing
	 * outright. 'cd_or_td_id' also isn't a real column — the correct
	 * column is 'cd_or_td_account_id', which store() already remaps
	 * correctly a few lines below; update() was missing that same
	 * remap entirely (fixed there too, see update() below).
	 */
	public function getCommonDataArr():array 
	{
		return ['contract_start_date','account_number','contract_end_date','currency','limit','outstanding_balance','balance_date','highest_debt_balance_rate','admin_fees_rate','to_be_setteled_max_within_days','cd_or_td_account_type_id','cd_or_td_lending_percentage'];
	}
	/**
	 * Stores a new Fully Secured Overdraft. The financial logic —
	 * creating the initial "active-limit" bank statement row, the
	 * outstanding breakdown, and (via the model's boot():created hook)
	 * the first rate history entry — is UNCHANGED, deliberately. Only
	 * the response type changed: a plain redirect instead of a raw
	 * JSON body, so Inertia can handle it natively.
	 */
	public function store(Company $company  ,FinancialInstitution $financialInstitution, StoreFullySecuredOverdraftRequest $request){

		$data = $request->only( $this->getCommonDataArr());
		foreach(['contract_start_date','contract_end_date','balance_date'] as $dateField){
			$data[$dateField] = $request->get($dateField) ? Carbon::make($request->get($dateField))->format('Y-m-d'):null;
		}
		$data['created_by'] = auth()->user()->id ;
		$data['company_id'] = $company->id ;
		$data['cd_or_td_account_id'] = $request->get('cd_or_td_id');
		$odooCode = $request->get('odoo_code');
		if($company->hasOdooIntegrationCredentials() && $odooCode){
			$odooService = new OdooService($company);
			$chartOfAccountId = $odooService->getChartOfAccountIdFromOdooCode($odooCode);
			$data['odoo_code'] = $odooCode ;
			$data['odoo_id'] = $chartOfAccountId ;
			$data['journal_id'] = $odooService->getJournalIdFromChartOfAccountId($chartOfAccountId) ;
		}
		/**
		 * @var FullySecuredOverdraft $fullySecuredOverdraft 
		 */
		$fullySecuredOverdraft = $financialInstitution->fullySecuredOverdrafts()->create($data);
		$type = $request->get('type','fully-secured-over-draft');
		$activeTab = $type ; 
		
		$fullySecuredOverdraft->fullySecuredOverdraftBankStatements()->create([
			'type'=>'active-limit',
			'is_debit'=>1 ,
			'is_credit'=> 0 ,
			'priority'=>3,
			'company_id'=>$company->id ,
			'date'=>$fullySecuredOverdraft->contract_start_date ,
			'limit'=>$fullySecuredOverdraft->limit ,
			'debit'=>0,
			'credit'=>0,
			'comment_en'=>__('Limit'),
			'comment_ar'=>__('Limit',[],'ar'),
			
		]);
		
		
		$fullySecuredOverdraft->storeOutstandingBreakdown($request,$company);
		return redirect()->route('view.fully.secured.overdraft',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'active'=>$activeTab])->with('success',__('Data Store Successfully'));
		
		
	}

	/**
	 * Shows the "Edit Fully Secured Overdraft" form.
	 *
	 * ✅ MIGRATED to Vue + Inertia — shares the same page component as
	 * create() (resources/js/Pages/FullySecuredOverdraft/Form.vue),
	 * distinguished by the `mode: 'edit'` prop.
	 */
	/**
	 * Shows the "Edit Fully Secured Overdraft" form.
	 *
	 * ✅ MIGRATED to Vue + Inertia — shares the same page component as
	 * create() (resources/js/Pages/FullySecuredOverdraft/Form.vue),
	 * distinguished by the `mode: 'edit'` prop.
	 */
	public function edit(Company $company , Request $request , FinancialInstitution $financialInstitution , FullySecuredOverdraft $fullySecuredOverdraft){
		$accounts = $this->buildCdOrTdAccounts($company, $financialInstitution);
		/**
		 * Make sure the CD/TD this overdraft is already linked to is
		 * always selectable, even if it's since matured/broken (same
		 * "still-selected-even-if-no-longer-eligible" rule already
		 * used for currency-filtered accounts elsewhere) — otherwise
		 * editing an older overdraft could show an empty dropdown
		 * where its actual linked account should be.
		 */
		$alreadyIncluded = collect($accounts)->contains(fn ($a) => $a['id'] === $fullySecuredOverdraft->getCdOrTdId() && $a['account_type_id'] === $fullySecuredOverdraft->getCdOrTdAccountTypeId());
		if (!$alreadyIncluded && $fullySecuredOverdraft->getCdOrTdId()) {
			$accountType = AccountType::find($fullySecuredOverdraft->getCdOrTdAccountTypeId());
			if ($accountType) {
				$modelClass = '\\App\\Models\\'.$accountType->getModelName();
				$record = $modelClass::find($fullySecuredOverdraft->getCdOrTdId());
				if ($record) {
					$accounts[] = [
						'id' => $record->id,
						'account_type_id' => $accountType->id,
						'account_number' => $record->getAccountNumber(),
						'currency' => $record->getCurrency(),
						'amount' => $record->getAmount(),
						'interest_rate' => $record->getInterestRate(),
					];
				}
			}
		}

        return \Inertia\Inertia::render('FullySecuredOverdraft/Form', [
			'mode' => 'edit',
			'company' => ['id' => $company->id],
			'financialInstitution' => ['id' => $financialInstitution->id, 'name' => $financialInstitution->getName()],
			'currencies' => getCurrencies(),
			'hasOdooIntegration' => $company->hasOdooIntegrationCredentials(),
			'cdOrTdAccountTypes' => AccountType::onlyCdOrTdAccounts()->get()->map(fn ($t) => ['id' => $t->id, 'name' => $t->getName()])->values(),
			'cdOrTdAccounts' => $accounts,
			'model' => [
				'id' => $fullySecuredOverdraft->id,
				'contract_start_date' => $fullySecuredOverdraft->getContractStartDate(),
				'contract_end_date' => $fullySecuredOverdraft->getContractEndDate(),
				'account_number' => $fullySecuredOverdraft->getAccountNumber(),
				'odoo_code' => $fullySecuredOverdraft->getOdooCode(),
				'currency' => $fullySecuredOverdraft->getCurrency(),
				'cd_or_td_account_type_id' => $fullySecuredOverdraft->getCdOrTdAccountTypeId(),
				'cd_or_td_id' => $fullySecuredOverdraft->getCdOrTdId(),
				'limit' => $fullySecuredOverdraft->getLimit(),
				'outstanding_balance' => $fullySecuredOverdraft->getOutstandingBalance(),
				'balance_date' => $fullySecuredOverdraft->balance_date,
				'highest_debt_balance_rate' => $fullySecuredOverdraft->highest_debt_balance_rate,
				'admin_fees_rate' => $fullySecuredOverdraft->admin_fees_rate,
				'to_be_setteled_max_within_days' => $fullySecuredOverdraft->getMaxSettlementDays(),
				'cd_or_td_lending_percentage' => $fullySecuredOverdraft->cd_or_td_lending_percentage,
				'outstanding_breakdowns' => $fullySecuredOverdraft->outstandingBreakdowns->map(fn ($b) => [
					'settlement_date' => $b->settlement_date,
					'amount' => $b->amount,
				])->values(),
			],
			'submitUrl' => route('update.fully.secured.overdraft', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'fullySecuredOverdraft' => $fullySecuredOverdraft->id]),
			'backUrl' => route('view.fully.secured.overdraft', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
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
	 * Updates an existing Fully Secured Overdraft's main details. Two
	 * real bugs fixed here (confirmed with the project owner):
	 *   1. $data['updated_by'] was being set, then immediately wiped
	 *      out by the very next line ($data = $request->only(...))
	 *      overwriting the whole array — so "who last edited this"
	 *      was never actually saved. Fixed by setting it after.
	 *   2. This method never remapped the submitted 'cd_or_td_id'
	 *      field to the real column 'cd_or_td_account_id' — store()
	 *      already did this correctly, update() was simply missing
	 *      the equivalent line, meaning the CD/TD linkage could never
	 *      actually be changed by editing (on top of the separate
	 *      crash bug from the invalid column, fixed in getCommonDataArr()
	 *      above).
	 * The response was also changed from a raw JSON body to a normal
	 * redirect — Inertia expects a redirect or an Inertia::render(),
	 * not an arbitrary JSON payload for the frontend to interpret
	 * manually. This is presentation-layer plumbing only; nothing
	 * about what gets saved has changed.
	 */
	public function update(Company $company , UpdateFullySecuredOverdraftRequest $request , FinancialInstitution $financialInstitution,FullySecuredOverdraft $fullySecuredOverdraft){
		$data = $request->only($this->getCommonDataArr());
		$data['updated_by'] = auth()->user()->id ;
		$data['cd_or_td_account_id'] = $request->get('cd_or_td_id');
		foreach(['contract_start_date','contract_end_date','balance_date'] as $dateField){
			$data[$dateField] = $request->get($dateField) ? Carbon::make($request->get($dateField))->format('Y-m-d'):null;
		}
		if($company->hasOdooIntegrationCredentials()){
			$odooService = new OdooService($company);
			$odooCode = $request->get('odoo_code');
			$chartOfAccountId = $odooService->getChartOfAccountIdFromOdooCode($odooCode);
			$data['odoo_code'] = $odooCode ;
			$data['odoo_id'] = $chartOfAccountId ;
			$data['journal_id'] = $odooService->getJournalIdFromChartOfAccountId($chartOfAccountId) ;
		}
		
		$fullySecuredOverdraft->update($data);
		$fullySecuredOverdraft->storeOutstandingBreakdown($request,$company);
		$fullySecuredOverdraft->updateLimitRaw();
		
		$type = $request->get('type','fully-secured-over-draft');
		$activeTab = $type ;
		return redirect()->route('view.fully.secured.overdraft',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'active'=>$activeTab])->with('success',__('Item Has Been Updated Successfully'));
		
	}
	
	/**
	 * Deletes a Fully Secured Overdraft. UNCHANGED, deliberately — the
	 * model's deleting() hook (see FullySecuredOverdraft::boot())
	 * already cleans up its rates and bank statements.
	 */
	public function destroy(Company $company , FinancialInstitution $financialInstitution , FullySecuredOverdraft $fullySecuredOverdraft)
	{
	
		$fullySecuredOverdraft->delete();
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}

	
	
}
