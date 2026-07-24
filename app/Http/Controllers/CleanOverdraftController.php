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
		$canUpdate = hasAuthFor('update clean overdraft');
		$canDelete = hasAuthFor('delete clean overdraft');
		$canCreateRate = hasAuthFor('create clean overdraft');

        return \Inertia\Inertia::render('CleanOverdraft/Index', [
			'company' => ['id' => $company->id],
			'financialInstitution' => ['id' => $financialInstitution->id, 'name' => $financialInstitution->getName()],
			'canCreate' => hasAuthFor('create clean overdraft'),
			'canUpdate' => $canUpdate,
			'canDelete' => $canDelete,
			'canCreateRate' => $canCreateRate,
			'createUrl' => route('create.clean.overdraft', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
			'rows' => $cleanOverdrafts->map(function (CleanOverdraft $co) use ($company, $financialInstitution, $lockableAccountType) {
				return [
					'id' => $co->id,
					'contract_start_date_formatted' => $co->getContractStartDateFormatted(),
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
		
		$cleanOverdraft->handleEndOfMonthInterestForContractStatements($data['contract_start_date'],$data['contract_end_date'],$company->id);
		
		
		// a new empty line in clean overdraft bank statement
		$cleanOverdraft->cleanOverdraftBankStatements()->create([
			'type'=>'active-limit',
			'is_debit'=>1 ,
			'is_credit'=> 0 ,
			'priority'=>3,
			'company_id'=>$company->id ,
			'date'=>$cleanOverdraft->contract_start_date ,
			'limit'=>$cleanOverdraft->limit ,
			'debit'=>0,
			'credit'=>0,
			'comment_en'=>__('Limit'),
			'comment_ar'=>__('Limit',[],'ar'),
			
		]);
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
        return \Inertia\Inertia::render('CleanOverdraft/Form', [
			'mode' => 'edit',
			'company' => ['id' => $company->id],
			'financialInstitution' => ['id' => $financialInstitution->id, 'name' => $financialInstitution->getName()],
			'currencies' => getCurrencies(),
			'hasOdooIntegration' => $company->hasOdooIntegrationCredentials(),
			'model' => [
				'id' => $cleanOverdraft->id,
				'contract_start_date' => $cleanOverdraft->getContractStartDate(),
				'contract_end_date' => $cleanOverdraft->getContractEndDate(),
				'account_number' => $cleanOverdraft->getAccountNumber(),
				'odoo_code' => $cleanOverdraft->getOdooCode(),
				'currency' => $cleanOverdraft->getCurrency(),
				'limit' => $cleanOverdraft->getLimit(),
				'outstanding_balance' => $cleanOverdraft->getOutstandingBalance(),
				'balance_date' => $cleanOverdraft->balance_date,
				'highest_debt_balance_rate' => $cleanOverdraft->highest_debt_balance_rate,
				'admin_fees_rate' => $cleanOverdraft->admin_fees_rate,
				'to_be_setteled_max_within_days' => $cleanOverdraft->getMaxSettlementDays(),
				'outstanding_breakdowns' => $cleanOverdraft->outstandingBreakdowns->map(fn ($b) => [
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
		$data = $request->only($this->getCommonDataArr());
		$data['updated_by'] = auth()->user()->id ;
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
		$cleanOverdraft->update($data);
		$cleanOverdraft->handleEndOfMonthInterestForContractStatements($data['contract_start_date'],$data['contract_end_date'],$company->id);
		$cleanOverdraft->storeOutstandingBreakdown($request,$company);
		$cleanOverdraft->updateLimitRaw();
		$type = $request->get('type','clean-over-draft');
		$activeLimitRow = $cleanOverdraft->cleanOverdraftBankStatements->where('type','active-limit')->first();
		$activeLimitRowData = [
			'type'=>'active-limit',
			'is_debit'=>1 ,
			'is_credit'=> 0 ,
			'priority'=>3,
			'company_id'=>$company->id ,
			'date'=>$cleanOverdraft->contract_start_date ,
			'limit'=>$cleanOverdraft->limit ,
			'debit'=>0,
			'credit'=>0,
			'comment_en'=>'-',
			'comment_ar'=>'-',
			
		];
		if($activeLimitRow){
			$activeLimitRow->update($activeLimitRowData);
		}else{
			$cleanOverdraft->cleanOverdraftBankStatements()->create($activeLimitRowData);
		}
		$activeTab = $type ;
		return redirect()->route('view.clean.overdraft',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'active'=>$activeTab])->with('success',__('Item Has Been Updated Successfully'));
	}
	
	/**
	 * Deletes a Clean Overdraft. UNCHANGED, deliberately — the model's
	 * deleting() hook already cleans up its rates and bank statements.
	 */
	public function destroy(Company $company , FinancialInstitution $financialInstitution , CleanOverdraft $cleanOverdraft)
	{
		$cleanOverdraft->delete();
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}

	
}
