<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreOverdraftAgainstCommercialPaperRequest;
use App\Http\Requests\UpdateOverdraftAgainstCommercialPaperRequest;
use App\Models\AccountType;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\OverdraftAgainstCommercialPaper;
use App\Models\Traits\Controllers\HasOverdraftRate;
use App\Services\Api\OdooService;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * OverdraftAgainstCommercialPaperController
 * ------------------------------------------------------------------
 * Manages Overdraft Against Commercial Paper facilities — an
 * overdraft the bank grants against commercial papers (e.g. post-dated
 * cheques) the company holds. Same rate-history sub-feature as Clean/
 * Fully Secured Overdraft, plus a unique "Lending Information"
 * repeater: per-tier lending rates based on how many days until a
 * commercial paper is due (e.g. papers due within 30 days lend at one
 * rate, 60 days at another). There's also an automatic "Limits"
 * ledger (overdraft_against_commercial_paper_limits, tied to
 * individual cheques) — that's backend bookkeeping only, maintained
 * by updateFirstLimitsTableFromDate(), not a user-facing CRUD screen.
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   ✅ index()          → MIGRATED to Vue + Inertia. Renders
 *                          resources/js/Pages/OverdraftAgainstCommercialPaper/Index.vue.
 *   ✅ create() / edit() → MIGRATED to Vue + Inertia. Both render the
 *                          same shared page,
 *                          resources/js/Pages/OverdraftAgainstCommercialPaper/Form.vue,
 *                          distinguished by a `mode: 'create' | 'edit'` prop.
 *   ⚠️ getCommonDataArr() → REAL BUG FIXED (same class of bug found on
 *      Fully Secured Overdraft): listed 'borrowing_rate',
 *      'bank_margin_rate', 'interest_rate', 'min_interest_rate' —
 *      NONE of which are real columns on overdraft_against_commercial_papers
 *      (confirmed against the actual database schema). This crashed
 *      every create/edit with a SQL error. Fixed by removing them —
 *      the rate fields genuinely belong to the separate
 *      overdraft_against_commercial_paper_rates history table, handled
 *      by storeRate() in the model's boot():created hook.
 *   ⚠️ StoreOverdraftAgainstCommercialPaperRequest → 'balance_date' was
 *      never validated server-side (same bug already found and fixed
 *      on Fully Secured Overdraft and Clean Overdraft) — that field
 *      feeds the first rate-history row's date, which is NOT NULL in
 *      the database. Fixed here too.
 *   ⚠️ update() → 'updated_by' was being set then immediately wiped
 *      out by the next line overwriting the whole $data array — same
 *      bug already found and fixed on the other 3 already-migrated
 *      overdraft/deposit controllers. Fixed here too.
 *   ✅ Odoo Code support added from the start (this type never had it
 *      before — the database columns were added in the same migration
 *      that added them to Fully Secured/Clean Overdraft, per the
 *      project owner's explicit request to prepare all 4 overdraft
 *      types at once).
 *   ✅ store() / destroy() → presentation-only change (response type
 *      only, for store()/update()). Financial logic — Lending
 *      Information repeater, Outstanding Breakdown, the Limits ledger
 *      — UNCHANGED, deliberately.
 */
class OverdraftAgainstCommercialPaperController
{
    use GeneralFunctions , HasOverdraftRate;
	
	public static function getModelName()
	{
		return OverdraftAgainstCommercialPaper::class ;
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
	 * The main "Overdraft Against Commercial Paper" list — one flat
	 * list per financial institution.
	 *
	 * ✅ MIGRATED to Vue + Inertia. Renders
	 * resources/js/Pages/OverdraftAgainstCommercialPaper/Index.vue.
	 */
	public function index(Company $company,Request $request,FinancialInstitution $financialInstitution)
	{
		$overdraftAgainstCommercialPapers = $company->overdraftAgainstCommercialPapers->where('financial_institution_id',$financialInstitution->id) ;
		$overdraftAgainstCommercialPapers =   $this->applyFilter($request,$overdraftAgainstCommercialPapers) ;

		$lockableAccountType = AccountType::onlyOverdraftAgainstCommercialPaper()->first();
		$canUpdate = hasAuthFor('update overdraft against commercial paper');
		$canDelete = hasAuthFor('delete overdraft against commercial paper');
		$canCreateRate = hasAuthFor('create overdraft against commercial paper');

        return \Inertia\Inertia::render('OverdraftAgainstCommercialPaper/Index', [
			'company' => ['id' => $company->id],
			'financialInstitution' => ['id' => $financialInstitution->id, 'name' => $financialInstitution->getName()],
			'canCreate' => hasAuthFor('create overdraft against commercial paper'),
			'canUpdate' => $canUpdate,
			'canDelete' => $canDelete,
			'canCreateRate' => $canCreateRate,
			'createUrl' => route('create.overdraft.against.commercial.paper', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
			'rows' => $overdraftAgainstCommercialPapers->map(function (OverdraftAgainstCommercialPaper $ocp) use ($company, $financialInstitution, $lockableAccountType) {
				return [
					'id' => $ocp->id,
					'contract_start_date_formatted' => $ocp->getContractStartDateFormatted(),
					'contract_end_date_formatted' => $ocp->getContractEndDateFormatted(),
					'account_number' => $ocp->getAccountNumber(),
					'currency' => $ocp->getCurrencyFormatted(),
					'limit_formatted' => $ocp->getLimitFormatted(),
					'borrowing_rate_formatted' => $ocp->getBorrowingRateFormatted(),
					'margin_rate_formatted' => $ocp->getMarginRateFormatted(),
					'interest_rate_formatted' => $ocp->getInterestRateFormatted(),
					'is_active' => (bool) $ocp->is_active,
					'edit_url' => route('edit.overdraft.against.commercial.paper', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'overdraftAgainstCommercialPaper' => $ocp->id]),
					'delete_url' => route('delete.overdraft.against.commercial.paper', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'overdraftAgainstCommercialPaper' => $ocp->id]),
					'lock_url' => $lockableAccountType ? route('lock.or.unlock.bank.account', ['company' => $company->id, 'accountType' => $lockableAccountType->id, 'accountId' => $ocp->id]) : null,
					'apply_rate_url' => route('overdraft-against-commercial-paper-apply.rates', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'overdraftAgainstCommercialPaper' => $ocp->id]),
					'rates' => $ocp->rates->map(fn ($rate) => [
						'id' => $rate->id,
						'date_formatted' => $rate->getDateFormatted(),
						'date' => $rate->getDate(),
						'borrowing_rate' => $rate->getBorrowingRate(),
						'borrowing_rate_formatted' => $rate->getBorrowingRateFormatted(),
						'margin_rate' => $rate->getMarginRate(),
						'margin_rate_formatted' => $rate->getMarginRateFormatted(),
						'min_interest_rate' => $rate->getMinInterestRate(),
						'interest_rate_formatted' => $rate->getInterestRateFormatted(),
						'edit_url' => route('overdraft-against-commercial-paper-edit-rates', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'rate' => $rate->id]),
						'delete_url' => route('overdraft-against-commercial-paper-delete-rate', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'rate' => $rate->id]),
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
	 * Shows the "Add Overdraft Against Commercial Paper" form.
	 *
	 * ✅ MIGRATED to Vue + Inertia — shares the same page component as
	 * edit() (resources/js/Pages/OverdraftAgainstCommercialPaper/Form.vue),
	 * distinguished by the `mode: 'create'` prop.
	 */
	public function create(Company $company,FinancialInstitution $financialInstitution)
	{
        return \Inertia\Inertia::render('OverdraftAgainstCommercialPaper/Form', [
			'mode' => 'create',
			'company' => ['id' => $company->id],
			'financialInstitution' => ['id' => $financialInstitution->id, 'name' => $financialInstitution->getName()],
			'currencies' => getCurrencies(),
			'hasOdooIntegration' => $company->hasOdooIntegrationCredentials(),
			'model' => null,
			'submitUrl' => route('store.overdraft.against.commercial.paper', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
			'backUrl' => route('view.overdraft.against.commercial.paper', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
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
	 * ⚠️ REAL BUG FIXED HERE (same class of bug found and fixed on
	 * FullySecuredOverdraftController): this used to also list
	 * 'borrowing_rate', 'bank_margin_rate', 'interest_rate',
	 * 'min_interest_rate' — none of which are real columns on
	 * overdraft_against_commercial_papers (confirmed against the
	 * actual database schema). The rate values genuinely belong to
	 * the separate overdraft_against_commercial_paper_rates history
	 * table (handled by storeRate() in the model's boot():created
	 * hook). Including these as keys in $data caused create()/update()
	 * to attempt an INSERT/UPDATE with unknown columns — a hard SQL
	 * error, not a silent no-op — so saving a new or edited facility
	 * was crashing outright.
	 */
	public function getCommonDataArr():array 
	{
		return ['contract_start_date','account_number','contract_end_date','currency','limit','outstanding_balance','balance_date','highest_debt_balance_rate','admin_fees_rate','to_be_setteled_max_within_days','max_lending_limit_per_customer'];
	}
	/**
	 * Stores a new Overdraft Against Commercial Paper. Financial logic
	 * — Lending Information repeater, Outstanding Breakdown, and (via
	 * the model's boot():created hook) the first rate history entry —
	 * is UNCHANGED, deliberately. Only the response type changed: a
	 * plain redirect instead of a raw JSON body, so Inertia can handle
	 * it natively.
	 */
	public function store(Company $company  ,FinancialInstitution $financialInstitution, StoreOverdraftAgainstCommercialPaperRequest $request){
		
		$data = $request->only( $this->getCommonDataArr());
		foreach(['contract_start_date','contract_end_date','balance_date'] as $dateField){
			$data[$dateField] = $request->get($dateField) ? Carbon::make($request->get($dateField))->format('Y-m-d'):null;
		}
		$lendingInformation = $request->get('infos',[]) ; 
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
		 * @var OverdraftAgainstCommercialPaper $overdraftAgainstCommercialPaper 
		 */
		$overdraftAgainstCommercialPaper = $financialInstitution->overdraftAgainstCommercialPapers()->create($data);
		$type = $request->get('type','overdraft-against-commercial-paper');
		$activeTab = $type ; 
		
		$overdraftAgainstCommercialPaper->storeOutstandingBreakdown($request,$company);
		foreach($lendingInformation as $lendingInformationArr){
			$overdraftAgainstCommercialPaper->lendingInformation()->create(array_merge($lendingInformationArr , [
			]));
		}
		return redirect()->route('view.overdraft.against.commercial.paper',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'active'=>$activeTab])->with('success',__('Data Store Successfully'));
		
	}

	/**
	 * Shows the "Edit Overdraft Against Commercial Paper" form.
	 *
	 * ✅ MIGRATED to Vue + Inertia — shares the same page component as
	 * create() (resources/js/Pages/OverdraftAgainstCommercialPaper/Form.vue),
	 * distinguished by the `mode: 'edit'` prop.
	 */
	public function edit(Company $company , Request $request , FinancialInstitution $financialInstitution , OverdraftAgainstCommercialPaper $overdraftAgainstCommercialPaper){
        return \Inertia\Inertia::render('OverdraftAgainstCommercialPaper/Form', [
			'mode' => 'edit',
			'company' => ['id' => $company->id],
			'financialInstitution' => ['id' => $financialInstitution->id, 'name' => $financialInstitution->getName()],
			'currencies' => getCurrencies(),
			'hasOdooIntegration' => $company->hasOdooIntegrationCredentials(),
			'model' => [
				'id' => $overdraftAgainstCommercialPaper->id,
				'contract_start_date' => $overdraftAgainstCommercialPaper->getContractStartDate(),
				'contract_end_date' => $overdraftAgainstCommercialPaper->getContractEndDate(),
				'account_number' => $overdraftAgainstCommercialPaper->getAccountNumber(),
				'odoo_code' => $overdraftAgainstCommercialPaper->getOdooCode(),
				'currency' => $overdraftAgainstCommercialPaper->getCurrency(),
				'limit' => $overdraftAgainstCommercialPaper->getLimit(),
				'outstanding_balance' => $overdraftAgainstCommercialPaper->getOutstandingBalance(),
				'balance_date' => $overdraftAgainstCommercialPaper->balance_date,
				'highest_debt_balance_rate' => $overdraftAgainstCommercialPaper->highest_debt_balance_rate,
				'admin_fees_rate' => $overdraftAgainstCommercialPaper->admin_fees_rate,
				'to_be_setteled_max_within_days' => $overdraftAgainstCommercialPaper->getMaxSettlementDays(),
				'max_lending_limit_per_customer' => $overdraftAgainstCommercialPaper->getMaxLendingLimitPerCustomer(),
				'lending_information' => $overdraftAgainstCommercialPaper->lendingInformation->map(fn ($info) => [
					'for_commercial_papers_due_within_days' => $info->for_commercial_papers_due_within_days,
					'lending_rate' => $info->lending_rate,
				])->values(),
				'outstanding_breakdowns' => $overdraftAgainstCommercialPaper->outstandingBreakdowns->map(fn ($b) => [
					'settlement_date' => $b->settlement_date,
					'amount' => $b->amount,
				])->values(),
			],
			'submitUrl' => route('update.overdraft.against.commercial.paper', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'overdraftAgainstCommercialPaper' => $overdraftAgainstCommercialPaper->id]),
			'backUrl' => route('view.overdraft.against.commercial.paper', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
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
	 * Updates an existing facility's main details. UNCHANGED financial
	 * logic (Lending Information resync, Outstanding Breakdown, the
	 * Limits ledger update), deliberately. Two fixes here, same as the
	 * other 3 overdraft/deposit controllers:
	 *   1. 'updated_by' was being set then immediately wiped out by
	 *      the next line overwriting the whole $data array.
	 *   2. The response was changed from a raw JSON body to a normal
	 *      redirect for Inertia compatibility.
	 * Odoo Code support added, matching Fully Secured/Clean Overdraft.
	 */
	public function update(Company $company , UpdateOverdraftAgainstCommercialPaperRequest $request , FinancialInstitution $financialInstitution,OverdraftAgainstCommercialPaper $overdraftAgainstCommercialPaper){
		// $infos =  $request->get('infos',[]) ;
		$infos =  $request->get('infos',[]) ;
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
		
		$overdraftAgainstCommercialPaper->update($data);
		$overdraftAgainstCommercialPaper->storeOutstandingBreakdown($request,$company);
		$overdraftAgainstCommercialPaper->lendingInformation()->delete();
		foreach($infos as $lendingInformationArr){
			 $overdraftAgainstCommercialPaper->lendingInformation()->create($lendingInformationArr);
		}
		$overdraftAgainstCommercialPaper->updateFirstLimitsTableFromDate();
		$type = $request->get('type','overdraft-against-commercial-paper');
		$activeTab = $type ;
		return redirect()->route('view.overdraft.against.commercial.paper',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'active'=>$activeTab])->with('success',__('Item Has Been Updated Successfully'));
		
		
	}
	
	/**
	 * Deletes an Overdraft Against Commercial Paper and its related
	 * rates/limits/bank statements/lending-information rows.
	 * UNCHANGED, deliberately.
	 */
	public function destroy(Company $company , FinancialInstitution $financialInstitution , OverdraftAgainstCommercialPaper $overdraftAgainstCommercialPaper)
	{
		foreach(['lendingInformation','rates','overdraftAgainstCommercialPaperBankLimits','overdraftAgainstCommercialPaperBankStatements'] as $hasManyRelationName){
			$overdraftAgainstCommercialPaper->{$hasManyRelationName}->each(function($model){
				$model->delete();
			});	
		}
		$overdraftAgainstCommercialPaper->delete();
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}

	
	
}
