<?php
namespace App\Http\Controllers;

use App\Http\Requests\StoreOverdraftAgainstAssignmentOfContractRequest;
use App\Http\Requests\UpdateOverdraftAgainstAssignmentOfContractRequest;
use App\Models\AccountType;
use App\Models\Company;
use App\Models\Contract;
use App\Models\FinancialInstitution;
use App\Models\LendingInformationAgainstAssignmentOfContract;
use App\Models\OverdraftAgainstAssignmentOfContract;
use App\Models\Partner;
use App\Models\Traits\Controllers\HasOverdraftRate;
use App\Services\Api\OdooService;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * OverdraftAgainstAssignmentOfContractController
 * ------------------------------------------------------------------
 * Manages Overdraft Against Assignment Of Contract facilities — an
 * overdraft secured against real customer contracts assigned to the
 * bank as collateral. Same rate-history sub-feature as the other 3
 * overdraft types, plus a genuinely richer "Lending Information"
 * feature (unlike Commercial Paper's simple rate-tier repeater): each
 * Lending Information row links a REAL Contract + Customer, marks
 * that Contract as `running_and_against` while it's assigned, and
 * reverts it to `running` when unassigned. This is managed entirely
 * from the list page (modals), not the create/edit form — the
 * original form.blade.php has no lending-information section at all.
 *
 * ⚠️ applyAgainstLending() is a genuinely EMPTY stub in the original
 * code — its route exists, but the method body does nothing (the one
 * line of logic it might have had is commented out). This is
 * preserved exactly as-is: no UI was built to trigger it, since there
 * is nothing for it to do yet. Not something to silently "complete."
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   ✅ index()          → MIGRATED to Vue + Inertia. Renders
 *                          resources/js/Pages/OverdraftAgainstAssignmentOfContract/Index.vue.
 *   ✅ create() / edit() → MIGRATED to Vue + Inertia. Both render the
 *                          same shared page,
 *                          resources/js/Pages/OverdraftAgainstAssignmentOfContract/Form.vue,
 *                          distinguished by a `mode: 'create' | 'edit'` prop.
 *   ⚠️ getCommonDataArr() → REAL BUG FIXED (same class of bug found on
 *      all 3 other overdraft types): listed 'borrowing_rate',
 *      'bank_margin_rate', 'interest_rate', 'min_interest_rate' — none
 *      of which are real columns on overdraft_against_assignment_of_contracts
 *      (confirmed against the actual schema). This crashed every
 *      create/edit with a SQL error.
 *   ⚠️ StoreOverdraftAgainstAssignmentOfContractRequest → 'balance_date'
 *      was never validated server-side (same bug already found and
 *      fixed on the other 3 overdraft types) — fixed here too, before
 *      it could cause the same first-rate-record crash.
 *   ⚠️ update() → 'updated_by' overwrite bug, same as the other 3.
 *   ⚠️ editLendingInformation() → response converted from raw JSON to
 *      a redirect, for Inertia compatibility (same fix already applied
 *      to editRate() in the shared HasOverdraftRate trait).
 *   ✅ Odoo Code support added, matching the other 3 overdraft types.
 *   ✅ applyLendingInformation() / deleteLendingInformation() /
 *      store() / destroy() → presentation-only changes only.
 *      Financial logic — Contract status transitions, the
 *      active-limit bank statement row, Outstanding Breakdown —
 *      UNCHANGED, deliberately.
 */
class OverdraftAgainstAssignmentOfContractController
{
    use GeneralFunctions,HasOverdraftRate;
	public static function getModelName()
	{
		return OverdraftAgainstAssignmentOfContract::class ;
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
	 * The main "Overdraft Against Assignment Of Contract" list — one
	 * flat list per financial institution.
	 *
	 * ✅ MIGRATED to Vue + Inertia. Renders
	 * resources/js/Pages/OverdraftAgainstAssignmentOfContract/Index.vue.
	 *
	 * Every customer's contracts are fetched up front (flattened, one
	 * list, tagged with customer_id), the same pattern already used
	 * successfully for CD/TD account selection on Fully Secured
	 * Overdraft — the original relied on a client-side AJAX endpoint
	 * this codebase has no traceable server route for (likely a
	 * vendored/compiled JS file not present in this snapshot).
	 */
	public function index(Company $company,Request $request,FinancialInstitution $financialInstitution)
	{

		$odAgainstAssignmentOfContracts = $company->overdraftAgainstAssignmentOfContracts->where('financial_institution_id',$financialInstitution->id) ;
		$odAgainstAssignmentOfContracts =   $this->applyFilter($request,$odAgainstAssignmentOfContracts) ;

		$lockableAccountType = AccountType::onlyOverdraftAgainstAssignmentOfContract()->first();
		$canUpdate = hasAuthFor('update overdraft against assignment of contract');
		$canDelete = hasAuthFor('delete overdraft against assignment of contract');
		$canCreateRate = hasAuthFor('create overdraft against assignment of contract');

		$customers = Partner::where('is_customer',1)->onlyThatHaveCustomerContracts()->where('company_id',$company->id)->get();
		$contracts = [];
		foreach ($customers as $customer) {
			foreach ($customer->contracts->where('model_type', 'Customer') as $contract) {
				$contracts[] = [
					'id' => $contract->id,
					'customer_id' => $customer->id,
					'name' => $contract->getName(),
					'currency' => $contract->getCurrency(),
					'amount_formatted' => $contract->getAmountFormatted(),
					'amount' => $contract->getAmount(),
					'start_date' => $contract->getStartDate(),
					'end_date' => $contract->getEndDate(),
				];
			}
		}

        return \Inertia\Inertia::render('OverdraftAgainstAssignmentOfContract/Index', [
			'company' => ['id' => $company->id],
			'financialInstitution' => ['id' => $financialInstitution->id, 'name' => $financialInstitution->getName()],
			'canCreate' => hasAuthFor('create overdraft against assignment of contract'),
			'canUpdate' => $canUpdate,
			'canDelete' => $canDelete,
			'canCreateRate' => $canCreateRate,
			'createUrl' => route('create.overdraft.against.assignment.of.contract', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
			'customers' => $customers->map(fn ($c) => ['id' => $c->id, 'name' => $c->getName()])->values(),
			'contracts' => $contracts,
			'rows' => $odAgainstAssignmentOfContracts->map(function (OverdraftAgainstAssignmentOfContract $od) use ($company, $financialInstitution, $lockableAccountType) {
				return [
					'id' => $od->id,
					'contract_start_date_formatted' => $od->getContractStartDateFormatted(),
					'contract_end_date_formatted' => $od->getContractEndDateFormatted(),
					'account_number' => $od->getAccountNumber(),
					'currency' => $od->getCurrencyFormatted(),
					'currency_raw' => $od->getCurrency(),
					'limit_formatted' => $od->getLimitFormatted(),
					'borrowing_rate_formatted' => $od->getBorrowingRateFormatted(),
					'margin_rate_formatted' => $od->getMarginRateFormatted(),
					'interest_rate_formatted' => $od->getInterestRateFormatted(),
					'is_active' => (bool) $od->is_active,
					'edit_url' => route('edit.overdraft.against.assignment.of.contract', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'odAgainstAssignmentOfContract' => $od->id]),
					'delete_url' => route('delete.overdraft.against.assignment.of.contract', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'odAgainstAssignmentOfContract' => $od->id]),
					'lock_url' => $lockableAccountType ? route('lock.or.unlock.bank.account', ['company' => $company->id, 'accountType' => $lockableAccountType->id, 'accountId' => $od->id]) : null,
					'apply_rate_url' => route('overdraft-against-assignment-of-contract-apply.rates', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'odAgainstAssignmentOfContract' => $od->id]),
					'apply_lending_information_url' => route('lending.information.apply.for.against.assignment.of.contract', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'odAgainstAssignmentOfContract' => $od->id]),
					'rates' => $od->rates->map(fn ($rate) => [
						'id' => $rate->id,
						'date_formatted' => $rate->getDateFormatted(),
						'date' => $rate->getDate(),
						'borrowing_rate' => $rate->getBorrowingRate(),
						'borrowing_rate_formatted' => $rate->getBorrowingRateFormatted(),
						'margin_rate' => $rate->getMarginRate(),
						'margin_rate_formatted' => $rate->getMarginRateFormatted(),
						'min_interest_rate' => $rate->getMinInterestRate(),
						'interest_rate_formatted' => $rate->getInterestRateFormatted(),
						'edit_url' => route('overdraft-against-assignment-of-contract-edit-rates', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'rate' => $rate->id]),
						'delete_url' => route('overdraft-against-assignment-of-contract-delete-rate', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'rate' => $rate->id]),
					])->values(),
					'lending_information' => $od->lendingInformation->map(fn ($info) => [
						'id' => $info->getId(),
						'customer_id' => $info->getCustomerId(),
						'customer_name' => $info->getCustomerName(),
						'contract_id' => $info->contract_id,
						'contract_name' => $info->getContractName(),
						'contract_amount_formatted' => $info->getContractAmountFormatted(),
						'contract_start_date' => $info->getContractStartDate(),
						'contract_end_date' => $info->getContractEndDate(),
						'assignment_date' => $info->getAssignmentEndDate(),
						'lending_rate' => $info->getLendingRate(),
						'lending_rate_formatted' => $info->getLendingRateFormatted(),
						'lending_amount_formatted' => $info->getLendingAmountFormatted(),
						'edit_url' => route('lending.information.edit.for.against.assignment.of.contract', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'lendingInformation' => $info->getId()]),
						'delete_url' => route('lending.information.delete.for.against.assignment.of.contract', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'lendingInformation' => $info->getId()]),
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
	 * Shows the "Add Overdraft Against Assignment Of Contract" form.
	 *
	 * ✅ MIGRATED to Vue + Inertia — shares the same page component as
	 * edit() (resources/js/Pages/OverdraftAgainstAssignmentOfContract/Form.vue),
	 * distinguished by the `mode: 'create'` prop.
	 */
	public function create(Company $company,FinancialInstitution $financialInstitution)
	{
        return \Inertia\Inertia::render('OverdraftAgainstAssignmentOfContract/Form', [
			'mode' => 'create',
			'company' => ['id' => $company->id],
			'financialInstitution' => ['id' => $financialInstitution->id, 'name' => $financialInstitution->getName()],
			'currencies' => getCurrencies(),
			'hasOdooIntegration' => $company->hasOdooIntegrationCredentials(),
			'model' => null,
			'submitUrl' => route('store.overdraft.against.assignment.of.contract', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
			'backUrl' => route('view.overdraft.against.assignment.of.contract', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
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
		/**
		 * ⚠️ REAL BUG FIXED HERE (same class of bug found and fixed
		 * on all 3 other overdraft types): this used to also list
		 * 'borrowing_rate', 'bank_margin_rate', 'interest_rate',
		 * 'min_interest_rate' — none of which are real columns on
		 * overdraft_against_assignment_of_contracts (confirmed against
		 * the actual database schema). Those rate values genuinely
		 * belong to the separate rates history table.
		 */
		return ['contract_start_date','account_number','contract_end_date','currency','limit','outstanding_balance','balance_date','highest_debt_balance_rate','admin_fees_rate','to_be_setteled_max_within_days','max_lending_limit_per_contract'];
	}
	/**
	 * Stores a new Overdraft Against Assignment Of Contract. Financial
	 * logic — Outstanding Breakdown and (via the model's
	 * boot():created hook) the first rate history entry — is
	 * UNCHANGED, deliberately. Only the response type changed: a plain
	 * redirect instead of a raw JSON body, so Inertia can handle it
	 * natively.
	 */
	public function store(Company $company  ,FinancialInstitution $financialInstitution, StoreOverdraftAgainstAssignmentOfContractRequest $request){
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
		 * @var OverdraftAgainstAssignmentOfContract $odAgainstAssignmentOfContract 
		 */
		$odAgainstAssignmentOfContract = $financialInstitution->overdraftAgainstAssignmentOfContracts()->create($data);
		$type = $request->get('type','overdraft-against-assignment-of-contract');
		$activeTab = $type ; 
		
		$odAgainstAssignmentOfContract->storeOutstandingBreakdown($request,$company);
	
		return redirect()->route('view.overdraft.against.assignment.of.contract',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'active'=>$activeTab])->with('success',__('Data Store Successfully'));
	}

	/**
	 * Shows the "Edit Overdraft Against Assignment Of Contract" form.
	 *
	 * ✅ MIGRATED to Vue + Inertia — shares the same page component as
	 * create() (resources/js/Pages/OverdraftAgainstAssignmentOfContract/Form.vue),
	 * distinguished by the `mode: 'edit'` prop.
	 */
	public function edit(Company $company , Request $request , FinancialInstitution $financialInstitution , OverdraftAgainstAssignmentOfContract $odAgainstAssignmentOfContract){
        return \Inertia\Inertia::render('OverdraftAgainstAssignmentOfContract/Form', [
			'mode' => 'edit',
			'company' => ['id' => $company->id],
			'financialInstitution' => ['id' => $financialInstitution->id, 'name' => $financialInstitution->getName()],
			'currencies' => getCurrencies(),
			'hasOdooIntegration' => $company->hasOdooIntegrationCredentials(),
			'model' => [
				'id' => $odAgainstAssignmentOfContract->id,
				'contract_start_date' => $odAgainstAssignmentOfContract->getContractStartDate(),
				'contract_end_date' => $odAgainstAssignmentOfContract->getContractEndDate(),
				'account_number' => $odAgainstAssignmentOfContract->getAccountNumber(),
				'odoo_code' => $odAgainstAssignmentOfContract->getOdooCode(),
				'currency' => $odAgainstAssignmentOfContract->getCurrency(),
				'limit' => $odAgainstAssignmentOfContract->getLimit(),
				'outstanding_balance' => $odAgainstAssignmentOfContract->getOutstandingBalance(),
				'balance_date' => $odAgainstAssignmentOfContract->balance_date,
				'highest_debt_balance_rate' => $odAgainstAssignmentOfContract->highest_debt_balance_rate,
				'admin_fees_rate' => $odAgainstAssignmentOfContract->admin_fees_rate,
				'to_be_setteled_max_within_days' => $odAgainstAssignmentOfContract->getMaxSettlementDays(),
				'max_lending_limit_per_contract' => $odAgainstAssignmentOfContract->max_lending_limit_per_contract,
				'outstanding_breakdowns' => $odAgainstAssignmentOfContract->outstandingBreakdowns->map(fn ($b) => [
					'settlement_date' => $b->settlement_date,
					'amount' => $b->amount,
				])->values(),
			],
			'submitUrl' => route('update.overdraft.against.assignment.of.contract', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'odAgainstAssignmentOfContract' => $odAgainstAssignmentOfContract->id]),
			'backUrl' => route('view.overdraft.against.assignment.of.contract', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
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
	 * logic (triggerChangeOnContracts(), Outstanding Breakdown),
	 * deliberately. Same 2 fixes as the other 3 overdraft controllers:
	 * the updated_by overwrite bug, and the JSON→redirect response
	 * change for Inertia compatibility. Odoo Code support added.
	 */
	public function update(Company $company , UpdateOverdraftAgainstAssignmentOfContractRequest $request , FinancialInstitution $financialInstitution,OverdraftAgainstAssignmentOfContract $odAgainstAssignmentOfContract){
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
		$odAgainstAssignmentOfContract->update($data);
		$odAgainstAssignmentOfContract->triggerChangeOnContracts();
		
		$odAgainstAssignmentOfContract->storeOutstandingBreakdown($request,$company);

		$type = $request->get('type','overdraft-against-assignment-of-contract');
		$activeTab = $type ;
		return redirect()->route('view.overdraft.against.assignment.of.contract',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'active'=>$activeTab])->with('success',__('Item Has Been Updated Successfully'));
	}
	
	/**
	 * Deletes an Overdraft Against Assignment Of Contract and its
	 * related rates/limits/bank statements/lending-information rows.
	 * UNCHANGED, deliberately.
	 */
	public function destroy(Company $company , FinancialInstitution $financialInstitution , OverdraftAgainstAssignmentOfContract $odAgainstAssignmentOfContract)
	{
		foreach(['lendingInformation','rates','overdraftAgainstAssignmentOfContractBankLimits','overdraftAgainstAssignmentOfContractBankStatements'] as $hasManyRelationName)
		$odAgainstAssignmentOfContract->{$hasManyRelationName}->each(function($model){
			$model->delete();
		});	
		$odAgainstAssignmentOfContract->delete();
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}
	/**
	 * Links a customer's contract to this facility as collateral —
	 * marks the Contract as running_and_against and creates the
	 * active-limit bank statement row if one doesn't exist yet.
	 * UNCHANGED, deliberately. Already redirects back, so no response
	 * change was needed here.
	 */
	public function applyLendingInformation(Request $request , Company $company , FinancialInstitution $financialInstitution , OverdraftAgainstAssignmentOfContract $odAgainstAssignmentOfContract )
	{
		$contractId = $request->get('contract_id_create') ;
		$assignmentDate = Carbon::make($request->get('assignment_date_create'))->format('Y-m-d') ;
		$contract = Contract::find($contractId);
		$odAgainstAssignmentOfContract->lendingInformation()->create([
			'company_id'=>$company->id ,
			'lending_rate'=>$request->get('lending_rate_create'),
			'customer_id'=>$request->get('customer_id_create'),
			'assignment_date'=>$assignmentDate,
			'contract_id'=>$contractId,
			'updated_at'=>now()
		]);
	
		$contract->update([
			// 'account_type'=>AccountType::onlyAgainstAssignmentOfContract()->first()->id ,
			// 'account_number'=>$odAgainstAssignmentOfContract->getAccountNumber(),
			'status'=>Contract::RUNNING_AND_AGAINST,
			'updated_at'=>now(),
			'overdraft_against_assignment_of_contract_id'=>$odAgainstAssignmentOfContract->id
		]);
		$statementRow = $odAgainstAssignmentOfContract->overdraftAgainstAssignmentOfContractBankStatements()->where('type','active-limit',)->exists();
		if(!$statementRow){
		
			$statementRow = $odAgainstAssignmentOfContract->overdraftAgainstAssignmentOfContractBankStatements()->create([
				'type'=>'active-limit',
				'debit'=>0,
				'credit'=>0,
				'is_debit'=>1 ,
				'is_credit'=>0,
				'priority'=>3 ,
				'company_id'=>$company->id,
				'date'=>$assignmentDate ,
				'comment_en'=>'-',
				'comment_ar'=>'-'
			]);
		}
		
		
		
		
		return redirect()->back()->with('success',__('Done'));
	
	}
	/**
	 * Updates a lending-information link. UNCHANGED financial logic,
	 * deliberately. The response was changed from a raw JSON body to
	 * a normal redirect for Inertia compatibility — same fix already
	 * applied to editRate() in the shared HasOverdraftRate trait.
	 */
	public function editLendingInformation(Request $request , Company $company , FinancialInstitution $financialInstitution , LendingInformationAgainstAssignmentOfContract $lendingInformation)
	{
		$contractId = $request->get('contract_id_edit') ;
		$assignmentDate = $request->get('assignment_date_edit') ;
		$assignmentDate = Carbon::make($assignmentDate)->format('Y-m-d');
		$contract = Contract::find($contractId);
		$overdraftAgainstAssignmentOfContract = $lendingInformation->overdraftAgainstAssignmentOfContract;
		$lendingInformation->update([
			'lending_rate'=>$request->get('lending_rate_edit'),
			'customer_id'=>$request->get('customer_id_edit'),
			'contract_id'=>$contractId,
			'assignment_date'=>$assignmentDate,
			'updated_at'=>now()
		]);
		$contract->update([
			'overdraft_against_assignment_of_contract_id'=>$overdraftAgainstAssignmentOfContract->id,
			'updated_at'=>now(),
		]);
		
		$statementRow = $overdraftAgainstAssignmentOfContract->overdraftAgainstAssignmentOfContractBankStatements()->where('type','active-limit',)->first();
		if($statementRow){
			$statementRow->update([
					'type'=>'active-limit',
					'debit'=>0,
					'credit'=>0,
					'is_debit'=>1 ,
					'is_credit'=>0,
					'priority'=>3 ,
					'company_id'=>$company->id,
					'date'=>$assignmentDate ,
					'comment_en'=>'-',
					'comment_ar'=>'-'
			]);
			
		}
		
	
		return redirect()->back()->with('success',__('Done'));
	}
	/**
	 * Deletes a lending-information link and reverts the Contract's
	 * status back to running. UNCHANGED, deliberately. Already
	 * redirects back, so no response change was needed here.
	 */
	public function deleteLendingInformation(Request $request , Company $company , FinancialInstitution $financialInstitution , LendingInformationAgainstAssignmentOfContract $lendingInformation)
	{
		
		$lendingInformation->contract 
		//  && $lendingInformation->overdraftAgainstAssignmentOfContract->lendingInformation->count() == 1
		 
		 ? $lendingInformation->contract->update([
			'status'=>Contract::RUNNING,
			// 'account_type'=>null,
			// 'account_number'=>null ,
			'overdraft_against_assignment_of_contract_id'=>null
		]) : null;
		
		// $lendingInformation->overdraftAgainstAssignmentOfContract->triggerChangeOnContracts();
		$lendingInformation->delete();
		return redirect()->back()->with('success',__('Done'));
	}
	/**
	 * ⚠️ This method is a genuine no-op in the original code — its
	 * route exists (`apply.against.lending`), but the body does
	 * nothing (the one line it might have had is commented out).
	 * Preserved exactly as-is: no Vue UI was built to trigger this,
	 * since there is nothing here for it to trigger. Flagging this
	 * explicitly rather than silently leaving an inert route
	 * un-mentioned.
	 */
	public function applyAgainstLending(Request $request , Company $company , FinancialInstitution $financialInstitution , LendingInformationAgainstAssignmentOfContract $lendingInformation)
	{
		// $lendingInformation->contract->update();
	}
	
}
