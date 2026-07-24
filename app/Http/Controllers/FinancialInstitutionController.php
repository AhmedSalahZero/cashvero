<?php
namespace App\Http\Controllers;

use App\Helpers\HArr;
use App\Http\Requests\StoreCurrentAccountRequest;
use App\Http\Requests\StoreFinancialInstitutionRequest;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\CertificatesOfDeposit;
use App\Models\AccountType;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\LetterOfCreditFacility;
use App\Models\LeasingCompany;
use App\Models\MoneyReceived;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * FinancialInstitutionController
 * ------------------------------------------------------------------
 * Manages the "financial institutions" a company banks with — this is
 * CashVero's umbrella term covering four different relationship types:
 *
 *   - bank                 → a regular bank (current accounts, overdrafts, CDs, etc.)
 *   - leasing_companies    → companies the business leases assets through
 *   - factoring_companies  → companies the business factors invoices through
 *   - mortgage_companies   → mortgage/property-finance providers
 *
 * Responsibilities of this controller:
 *   - List/search all financial institutions of each type (index)
 *   - Create / edit / delete a financial institution record
 *   - Add new bank accounts under an existing financial institution
 *   - Display every account & facility balance tied to one institution
 *     (overdrafts, time deposits, certificates of deposit, etc.)
 *   - Small JSON lookups used by other forms (interest rate, LC issuances)
 *
 * ── Frontend migration status (as of this file's last update) ──────
 * CashVero is being incrementally migrated from Blade + jQuery/Bootstrap
 * to Vue 3 + Inertia + Tailwind, one page at a time (see project roadmap).
 *
 *   - viewAllAccounts()  → ALREADY migrated. Returns Inertia::render(),
 *                          served by resources/js/Pages/BankAccounts/Index.vue
 *   - index()            → ALREADY migrated. Returns Inertia::render(),
 *                          served by resources/js/Pages/FinancialInstitutions/Index.vue
 *                          (the 4-tab list: banks/leasing/factoring/mortgage)
 *                          ⚠️ Leasing Company create/edit (a single
 *                          "name" field) is now an inline modal right
 *                          on this same page, per the project owner's
 *                          decision — no separate Create/Edit page.
 *                          leasingCompanyStoreUrl + a per-row
 *                          update_url were added for this; nothing
 *                          else on this page changed. Bank/Mortgage/
 *                          Factoring create-edit flows UNCHANGED
 *                          (Factoring still navigates to its own old
 *                          Blade create/edit — not requested here).
 *   - create() / edit()  → ALREADY migrated. Both share ONE Vue page —
 *                          resources/js/Pages/FinancialInstitutions/Form.vue —
 *                          distinguished by whether `model` is null (create,
 *                          shows the initial-accounts repeater) or populated
 *                          (edit, repeater hidden). store()/update() are
 *                          unchanged — Inertia posts to the same endpoints
 *                          and follows the redirect response as normal.
 *   - addAccount()       → ALREADY migrated. Returns Inertia::render(),
 *                          served by resources/js/Pages/FinancialInstitutions/AddAccount.vue
 *                          (same repeater pattern as Form.vue's create mode,
 *                          minus the bank/branch fields). storeAccount() unchanged.
 *
 *   ✅ "Financial Institution" + "Current Accounts" are now FULLY migrated
 *      (per the roadmap phase order). Next up: Time Deposits, Certificates
 *      of Deposit, and the 4 overdraft types.
 *
 *   - All other methods  → NOT YET migrated. Still return view() with
 *                          traditional Blade templates under resources/views/reports/financial-institution/
 *
 * When migrating another method in this controller, follow the same
 * pattern used in viewAllAccounts(): transform Eloquent models into
 * plain arrays (Inertia serializes props to JSON — it cannot call
 * methods on PHP objects client-side), and pre-resolve any URLs
 * (route() calls) server-side since Ziggy is not installed.
 */
class FinancialInstitutionController
{
    use GeneralFunctions;

    /**
     * Filter a collection of bank-related records (money received, etc.)
     * by a single field/value pair and an optional date range.
     * Used by index() when searching the "bank" tab.
     */
    protected function applyFilter(Request $request,Collection $collection):Collection{
		if(!count($collection)){
			return $collection;
		}
		$searchFieldName = $request->get('field');
		$dateFieldName = $searchFieldName === 'balance_date' ? 'balance_date' : 'created_at'; 
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
		});
		
		return $collection;
	}
	/**
	 * List financial institutions for a company, split by relationship
	 * type via the ?active= query param: bank | leasing_companies |
	 * factoring_companies | mortgage_companies. Each type supports its
	 * own search fields (see $companiesSearchFields and siblings below).
	 *
	 * Still Blade-rendered: view('reports.financial-institution.index').
	 */
	/**
 * The main "Financial Institutions" list — 4 tabs: banks, leasing
 * companies, factoring companies, mortgage companies. Each tab has
 * its own dataset, search fields, and create route.
 *
 * ✅ MIGRATED to Vue + Inertia. Renders
 * resources/js/Pages/FinancialInstitutions/Index.vue.
 *
 * Data is flattened into plain arrays with pre-resolved action URLs
 * (edit/delete/view-accounts/add-account/each facility type) and
 * permission flags — Vue cannot call PHP model methods or check
 * Spatie permissions directly, so everything needed is resolved here.
 */
	public function index(Company $company,Request $request)
	{
		$financialInst = new CertificatesOfDeposit();

		$type = $request->get('active','bank') ;
		$financialInstitutionsBanks = $company->financialInstitutionsBanks() ;
		$financialInstitutionsBanks = $type == 'bank' ?  $this->applyFilter($request,$financialInstitutionsBanks)  :$financialInstitutionsBanks ;

		$leasingCompanies = $company->leasingCompanies;
		$leasingCompanies = $type == 'leasing_companies' ? $this->applyLeasingFilter($request, $leasingCompanies) : $leasingCompanies;

		$factoringCompanies = $company->factoringCompanies;
		$factoringCompanies = $type == 'factoring_companies' ? $this->applyLeasingFilter($request, $factoringCompanies) : $factoringCompanies;
		$financialInstitutionsMortgageCompanies = $company->financialInstitutionsMortgageCompanies() ;
		$financialInstitutionsMortgageCompanies = $type == 'mortgage_companies' ? $this->applyFilter($request,$financialInstitutionsMortgageCompanies) : $financialInstitutionsMortgageCompanies ;

		// Permission flags — resolved once here since Vue can't call hasAuthFor()/can() directly
		$permissions = [
			'create' => hasAuthFor('create financial institutions'),
			'update' => hasAuthFor('update financial institutions'),
			'delete' => hasAuthFor('delete financial institutions'),
			'view_time_of_deposit' => hasAuthFor('view time of deposit'),
			'view_certificate_of_deposit' => hasAuthFor('view certificate of deposit'),
			'view_fully_secured_overdraft' => hasAuthFor('view fully secured overdraft'),
			'view_clean_overdraft' => hasAuthFor('view clean overdraft'),
			'view_overdraft_against_commercial_paper' => hasAuthFor('view overdraft against commercial paper'),
			'view_overdraft_against_assignment_of_contract' => hasAuthFor('view overdraft against assignment of contract'),
			'view_letter_of_guarantee_issuance' => hasAuthFor('view letter of guarantee issuance'),
			'view_letter_of_credit_facility' => hasAuthFor('view letter of credit facility'),
			'view_medium_term_loan' => hasAuthFor('view medium term loan'),
		];

		// ── Banks tab ────────────────────────────────────────────────
		$banksData = collect($financialInstitutionsBanks)->map(function ($bank) use ($company) {
			return [
				'id' => $bank->id,
				'bank_name' => $bank->getBankName(),
				'branch_name' => $bank->getBranchName(),
				'company_account_number' => $bank->getCompanyAccountNumber(),
				'view_accounts_url' => route('view.all.bank.accounts', ['company' => $company->id, 'financialInstitution' => $bank->id]),
				'edit_url' => route('edit.financial.institutions', ['company' => $company->id, 'financialInstitution' => $bank->id]),
				'delete_url' => route('delete.financial.institutions', ['company' => $company->id, 'financialInstitution' => $bank->id]),
				'add_current_account_url' => route('financial.institution.add.account', ['company' => $company->id, 'financialInstitution' => $bank->id]),
				'view_time_of_deposit_url' => route('view.time.of.deposit', ['company' => $company->id, 'financialInstitution' => $bank->id]),
				'view_certificates_of_deposit_url' => route('view.certificates.of.deposit', ['company' => $company->id, 'financialInstitution' => $bank->id]),
				'view_fully_secured_overdraft_url' => route('view.fully.secured.overdraft', ['company' => $company->id, 'financialInstitution' => $bank->id]),
				'view_clean_overdraft_url' => route('view.clean.overdraft', ['company' => $company->id, 'financialInstitution' => $bank->id]),
				'view_overdraft_against_commercial_paper_url' => route('view.overdraft.against.commercial.paper', ['company' => $company->id, 'financialInstitution' => $bank->id]),
				'view_overdraft_against_assignment_of_contract_url' => route('view.overdraft.against.assignment.of.contract', ['company' => $company->id, 'financialInstitution' => $bank->id]),
				'view_letter_of_guarantee_facility_url' => route('view.letter.of.guarantee.facility', ['company' => $company->id, 'financialInstitution' => $bank->id]),
				'view_letter_of_credit_facility_url' => route('view.letter.of.credit.facility', ['company' => $company->id, 'financialInstitution' => $bank->id]),
				'view_medium_term_loans_url' => route('loans.index', ['company' => $company->id, 'financialInstitution' => $bank->id]),
			];
		})->values();

		// ── Leasing companies tab ────────────────────────────────────
		// ⚠️ Per the project owner's decision: create/edit for a
		// Leasing Company (just a name field) is now an inline modal
		// right here on this page instead of a separate Create/Edit
		// page — added update_url per row and leasingCompanyStoreUrl
		// below. LeasingCompanyController's store()/update() are
		// UNCHANGED — they already redirect back to this same page.
		$leasingData = collect($leasingCompanies)->map(function ($lc) use ($company) {
			return [
				'id' => $lc->id,
				'name' => $lc->getName(),
				'contracts_url' => route('leasing.contracts.index', ['company' => $company->id, 'leasingCompany' => $lc->id]),
				'edit_url' => null,
				'update_url' => route('leasing.companies.update', ['company' => $company->id, 'leasingCompany' => $lc->id]),
				'delete_url' => route('leasing.companies.destroy', ['company' => $company->id, 'leasingCompany' => $lc->id]),
			];
		})->values();

		// ── Factoring companies tab ──────────────────────────────────
		// Same inline-modal treatment as Leasing Companies (just a
		// "name" field) — added update_url per row.
		$factoringData = collect($factoringCompanies)->map(function ($fc) use ($company) {
			return [
				'id' => $fc->id,
				'name' => $fc->getName(),
				'contracts_url' => route('factoring.contracts.index', ['company' => $company->id, 'factoringCompany' => $fc->id]),
				'edit_url' => null,
				'update_url' => route('factoring.companies.update', ['company' => $company->id, 'factoringCompany' => $fc->id]),
				'delete_url' => route('factoring.companies.destroy', ['company' => $company->id, 'factoringCompany' => $fc->id]),
			];
		})->values();

		// ── Mortgage companies tab ───────────────────────────────────
		$mortgageData = collect($financialInstitutionsMortgageCompanies)->map(function ($mc) use ($company) {
			return [
				'id' => $mc->id,
				'name' => $mc->getName(),
				'branch_name' => $mc->getBranchName(),
				'edit_url' => route('edit.financial.institutions', ['company' => $company->id, 'financialInstitution' => $mc->id]),
				'delete_url' => route('delete.financial.institutions', ['company' => $company->id, 'financialInstitution' => $mc->id]),
			];
		})->values();

		return \Inertia\Inertia::render('FinancialInstitutions/Index', [
			'activeTab' => $type,
			'company' => ['id' => $company->id],
			'permissions' => $permissions,
			'banks' => $banksData,
			'leasingCompanies' => $leasingData,
			'factoringCompanies' => $factoringData,
			'mortgageCompanies' => $mortgageData,
			'createUrls' => [
				'bank' => route('create.financial.institutions', ['company' => $company->id]),
			],
			'leasingCompanyStoreUrl' => route('leasing.companies.store', ['company' => $company->id]),
			'factoringCompanyStoreUrl' => route('factoring.companies.store', ['company' => $company->id]),
			'tabUrls' => [
				'bank' => route('view.financial.institutions', ['company' => $company->id, 'active' => 'bank']),
				'leasing_companies' => route('view.financial.institutions', ['company' => $company->id, 'active' => 'leasing_companies']),
				'factoring_companies' => route('view.financial.institutions', ['company' => $company->id, 'active' => 'factoring_companies']),
				'mortgage_companies' => route('view.financial.institutions', ['company' => $company->id, 'active' => 'mortgage_companies']),
			],
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
	 * Same idea as applyFilter(), but for leasing/factoring/mortgage
	 * companies, which don't have the bank-specific fields (bank_id,
	 * swift_code, etc.) — just a generic field/value/date-range filter.
	 */
	protected function applyLeasingFilter(Request $request, Collection $collection): Collection
	{
		if (!count($collection)) {
			return $collection;
		}

		$searchFieldName = $request->get('field');
		$dateFieldName = 'created_at';
		$from = $request->get('from');
		$to = $request->get('to');
		$value = $request->query('value');

		return $collection
			->when($request->has('value'), function ($collection) use ($value, $searchFieldName) {
				return $collection->filter(function ($item) use ($value, $searchFieldName) {
					return false !== stristr((string) $item->{$searchFieldName}, (string) $value);
				});
			})
			->when($request->get('from'), function ($collection) use ($dateFieldName, $from) {
				return $collection->where($dateFieldName, '>=', $from);
			})
			->when($request->get('to'), function ($collection) use ($dateFieldName, $to) {
				return $collection->where($dateFieldName, '<=', $to);
			});
	}
	
	/**
 * Show the "add financial institution" form. Only offers banks the
 * company doesn't already have a relationship with ($exceptBanks).
 *
 * ✅ MIGRATED to Vue + Inertia — shares the same page component as
 * edit() (resources/js/Pages/FinancialInstitutions/Form.vue), with
 * `model: null` signaling "create mode" (shows the initial-accounts
 * repeater, which only makes sense when the institution doesn't
 * exist yet).
 */
	public function create(Company $company)
	{
		$exceptBanks = FinancialInstitution::where('company_id',$company->id)->pluck('bank_id')->toArray() ;
		$banks = Bank::whereNotIn('id',$exceptBanks)->pluck('view_name','id');
	
		return \Inertia\Inertia::render('FinancialInstitutions/Form', [
			'company' => ['id' => $company->id],
			'model' => null,
			'banks' => $banks,
			'currencies' => getCurrencies(),
			'hasOdooIntegration' => $company->hasOdooIntegrationCredentials(),
			'listUrl' => route('view.financial.institutions', ['company' => $company->id, 'active' => 'bank']),
			'submitUrl' => route('store.financial.institutions', ['company' => $company->id]),
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
	 * Create a new financial institution record. If type is 'bank',
	 * also creates its initial bank account(s) via storeNewAccounts().
	 * Redirects back to the correct tab on the index page (getActiveTab()).
	 *
	 * Unchanged by the Vue migration — Inertia posts to this exact same
	 * endpoint and follows the redirect response automatically.
	 */

	public function store(Company $company , StoreFinancialInstitutionRequest $request){
    $type = $request->get('type');
    $data = $request->only(['type','branch_name']);
    $accounts = $type == 'bank' ? $request->get('accounts',[]) : [];
 
    $data['created_by'] = auth()->user()->id ;
    $data['company_id'] = $company->id ;
    $additionalData = [];
 
    if($type =='bank'){
        $additionalData = ['bank_id','company_account_number','iban','main_currency'] ;
    }
    else{
        $additionalData = ['name'] ;
    }
 
    foreach($additionalData as $name){
        $data[$name] = $request->get($name);
    }
    /**
     * @var FinancialInstitution $financialInstitution
     */
    $financialInstitution = FinancialInstitution::create($data);
    $financialInstitution->storeNewAccounts($accounts,$company);
    $activeTab = $this->getActiveTab($type);
    return redirect()->route('view.financial.institutions',['company'=>$company->id,'active'=>$activeTab])->with('success',__('Data Store Successfully'));
 
	}

	/**
	 * Maps a financial-institution "type" value to the tab name used
	 * in the index page's URL (?active=...), so redirects land the
	 * user back on the tab they were working in.
	 */
	protected function getActiveTab(string $moneyType)
	{
		return [
			'bank'=>'bank',
			'leasing_companies'=>'leasing_companies',
			'factoring_companies'=>'factoring_companies',
			'mortgage_companies'=>'mortgage_companies'
		][$moneyType];
	}

	/**
	 * Show the "edit financial institution" form, pre-filled with the
	 * existing record. Excludes other banks already linked to this
	 * company, but keeps this institution's own bank in the list.
	 *
	 * ✅ MIGRATED to Vue + Inertia — shares the same page component as
	 * create() (resources/js/Pages/FinancialInstitutions/Form.vue), with
	 * `model` populated signaling "edit mode" (hides the initial-accounts
	 * repeater — accounts are managed from the Bank Accounts page once
	 * the institution already exists).
	 */
	public function edit(Company $company , Request $request , FinancialInstitution $financialInstitution){
		$exceptBanks = FinancialInstitution::where('company_id',$company->id)->pluck('bank_id')->toArray() ;
		$exceptBanks = HArr::removeKeyFromArrayByValue($exceptBanks,[$financialInstitution->bank_id]);
		$banks = Bank::whereNotIn('id',$exceptBanks)->pluck('view_name','id');
	
		return \Inertia\Inertia::render('FinancialInstitutions/Form', [
			'company' => ['id' => $company->id],
			'model' => [
				'id' => $financialInstitution->id,
				'bank_id' => $financialInstitution->bank_id,
				'branch_name' => $financialInstitution->getBranchName(),
				'company_account_number' => $financialInstitution->getCompanyAccountNumber(),
			],
			'banks' => $banks,
			'currencies' => getCurrencies(),
			'hasOdooIntegration' => $company->hasOdooIntegrationCredentials(),
			'listUrl' => route('view.financial.institutions', ['company' => $company->id, 'active' => 'bank']),
			'submitUrl' => route('update.financial.institutions', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
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
	 * Save changes to an existing financial institution. Field set
	 * saved differs by type (bank vs leasing/factoring/mortgage) —
	 * see the $additionalData branching below.
	 *
	 * Unchanged by the Vue migration.
	 */
	public function update(Company $company , StoreFinancialInstitutionRequest $request , FinancialInstitution $financialInstitution){
		$type = $request->get('type');
		$data['updated_by'] = auth()->user()->id ;
		$data = $request->only(['type','branch_name']);
		$additionalData = [];
		if($type =='bank'){
			$additionalData = ['bank_id','company_account_number','swift_code','iban_code','current_account_number','main_currency','balance_amount'] ;
		}
		else{
			$additionalData = ['name'] ;
		}
		foreach($additionalData as $name){
			$data[$name] = $request->get($name);
		}
		$financialInstitution->update($data);
		$activeTab = $this->getActiveTab($type);
		return redirect()->route('view.financial.institutions',['company'=>$company->id,'active'=>$activeTab])->with('success',__('Item Has Been Updated Successfully'));
	}
	
	/**
	 * Delete a financial institution AND every bank account under it.
	 * ⚠️ No confirmation/undo at this layer — the confirmation happens
	 * in the UI before this endpoint is ever called. There is no soft
	 * delete here, so this is permanent.
	 */
	public function destroy(Company $company , FinancialInstitution $financialInstitution)
	{
		$financialInstitution->accounts->each(function($account){
			$account->delete();
		});
		$financialInstitution->delete();
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}
	
	/**
	 * Show the "add a new bank account" form for an existing financial
	 * institution (e.g. adding a second current account under the same bank).
	 *
	 * ✅ MIGRATED to Vue + Inertia. Renders
	 * resources/js/Pages/FinancialInstitutions/AddAccount.vue — reuses
	 * the same repeater pattern as the create-institution form, just
	 * without the bank/branch fields (the institution already exists).
	 */
	public function addAccount(Company $company , Request $request , FinancialInstitution $financialInstitution)
	{
		return \Inertia\Inertia::render('FinancialInstitutions/AddAccount', [
			'company' => ['id' => $company->id],
			'financialInstitution' => [
				'id' => $financialInstitution->id,
				'name' => $financialInstitution->getName(),
			],
			'currencies' => getCurrencies(),
			'hasOdooIntegration' => $company->hasOdooIntegrationCredentials(),
			'backUrl' => route('view.all.bank.accounts', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
			'submitUrl' => route('financial.institution.store.account', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
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
	 * Persist one or more new accounts added via addAccount(), then
	 * redirect to viewAllAccounts() so the user sees the updated list.
	 *
	 * Unchanged by the Vue migration.
	 */
	public function storeAccount(Company $company , StoreCurrentAccountRequest $request , FinancialInstitution $financialInstitution)
	{
		$accounts = $request->get('accounts',[]) ;
		$financialInstitution->storeNewAccounts($accounts,$company);
		return redirect()->route('view.all.bank.accounts',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id ])->with('success',__('Item Has Been Delete Successfully'));
		
	}
	/**
	 * عرض كل الحسابات الخاصة بالبنك
	 * (Arabic: "Show all accounts belonging to the bank")
	 *
	 * Displays every account/facility balance tied to one financial
	 * institution: regular accounts, time deposits, certificates of
	 * deposit, and all 4 overdraft types.
	 *
	 * ✅ MIGRATED to Vue + Inertia (first page converted in the CashVero
	 * modernization effort). Renders resources/js/Pages/BankAccounts/Index.vue.
	 *
	 * Data is deliberately flattened into plain arrays with pre-resolved
	 * edit/delete/lock URLs before being sent to Inertia — Vue cannot call
	 * PHP model methods (getType(), getAccountNumber(), etc.) directly,
	 * so every value the page needs must be resolved here first.
	 *
	 * Lock/unlock uses LockBankAccountController@lockOrUnlock (route name
	 * 'lock.or.unlock.bank.account') — this is the REAL, actively-used
	 * generic lock endpoint covering all 7 lockable account/facility types.
	 * (FinancialInstitutionAccountController::lockOrUnlock() is a separate,
	 * unused/dead method — nothing in the app calls its route. Don't
	 * confuse the two; this page correctly uses the real one.)
	 */
	public function viewAllAccounts(Company $company , Request $request , FinancialInstitution $financialInstitution)
	{
		$rawGroups = [
			$financialInstitution->accounts ,
			$financialInstitution->timeOfDeposits ,
			$financialInstitution->certificatesOfDeposits ,
			$financialInstitution->fullySecuredOverdrafts ,
			$financialInstitution->cleanOverdrafts ,
			$financialInstitution->overdraftAgainstCommercialPapers ,
			$financialInstitution->overdraftAgainstAssignmentOfContracts ,
		];

		// Preloaded once (not per-account) to avoid an N+1 query — maps
		// e.g. "FinancialInstitutionAccount" => its AccountType id.
		$accountTypeIdsByModel = \App\Models\AccountType::pluck('id', 'model_name');

		$bankAccounts = collect($rawGroups)->flatten()->map(function ($account) use ($company, $financialInstitution, $accountTypeIdsByModel) {
			$isEditable = $account instanceof \App\Models\FinancialInstitutionAccount;
			$modelName = class_basename($account);
			$accountTypeId = $accountTypeIdsByModel[$modelName] ?? null;
			$isLockable = $accountTypeId && method_exists($account, 'isActive');

			return [
				'id' => $account->id,
				'type_label' => $account->getType(),
				'account_number' => $account->getAccountNumber(),
				'currency_formatted' => $account->getCurrencyFormatted(),
				'balance_formatted' => $account->getLastAmountFormatted(
					$company->id,
					$account->getCurrency(),
					$account->getFinancialInstitutionId(),
					$account->getAccountNumber()
				),
				'is_editable' => $isEditable,
				'edit_url' => $isEditable
					? route('edit.financial.institutions.account', ['company' => $company->id, 'financialInstitutionAccount' => $account->id])
					: null,
				'delete_url' => $isEditable
					? route('delete.financial.institutions.account', ['company' => $company->id, 'financialInstitutionAccount' => $account->id])
					: null,
				'is_lockable' => $isLockable,
				'is_active' => $isLockable ? (bool) $account->isActive() : null,
				'lock_url' => $isLockable
					? route('lock.or.unlock.bank.account', ['company' => $company->id, 'accountType' => $accountTypeId, 'accountId' => $account->id])
					: null,
			];
		})->values();

		return \Inertia\Inertia::render('BankAccounts/Index', [
			'bankAccounts' => $bankAccounts,
			'financialInstitution' => [
				'id' => $financialInstitution->id,
				'name' => $financialInstitution->getName(),
			],
			'company' => [
				'id' => $company->id,
			],
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
	 * AJAX lookup: returns the interest rate for a given Letter of
	 * Credit facility, used to auto-fill a form field when the user
	 * picks a bank + LC facility combination.
	 */
	public function getInterestRateForFinancialInstitution(Company $company , Request $request)
	{
		$financialInstitutionId = $request->get('financialInstitutionId');
		$letterOfCreditFacilityId = $request->get('letterOfCreditFacilityId');
		if(!$financialInstitutionId || !$letterOfCreditFacilityId){
			return ;
		}
		$letterOfCreditFacility = LetterOfCreditFacility::find($letterOfCreditFacilityId); ;
		$interestRate =  0 ; 
		if($letterOfCreditFacility instanceof LetterOfCreditFacility){
			$interestRate = $letterOfCreditFacility->interest_rate ;
		}
		
		return response()->json([
			'interest_rate'=>$interestRate
		]);
	}	
	/**
	 * AJAX lookup: returns the Letter of Credit issuances for a given
	 * financial institution, filtered by currency — used to populate
	 * a dropdown when the user is settling an LC-related transaction.
	 */
	public function getLcIssuanceBasedOnFinancialInstitution(Company $company , Request $request)
	{
		$financialInstitutionId = $request->get('financialInstitutionId');
		$currency = $request->get('currency');
		$financialInstitution = FinancialInstitution::find($financialInstitutionId) ;
		$letterOfCreditIssuances = $financialInstitution->letterOfCreditIssuances->where('lc_cash_cover_currency',$currency)->pluck('transaction_name','id')->toArray() ;
		return response()->json([
			'letterOfCreditIssuances'=>$letterOfCreditIssuances
			// 'interest_rate'=>$interestRate
		]);
	}	
	
}