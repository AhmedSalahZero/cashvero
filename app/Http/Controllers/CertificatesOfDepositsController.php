<?php
namespace App\Http\Controllers;

use App\Support\Instructions\PageInstructions;
use App\Http\Requests\StoreCertificateOfDepositRequest;
use App\Http\Requests\UpdateCertificateOfDepositRequest;
use App\Models\AccountType;
use App\Models\Bank;
use App\Models\Branch;
use App\Models\CertificatesOfDeposit;
use App\Models\Company;
use App\Models\CurrentAccountBankStatement;
use App\Models\FinancialInstitution;
use App\Services\Api\OdooService;
use App\Support\ShareholderAccounts\AccountNumberLabel;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * CertificatesOfDepositsController
 * ------------------------------------------------------------------
 * Manages Certificate Of Deposit (CD) records under a Financial
 * Institution — structurally the near-twin of Time Of Deposit: a
 * fixed-term deposit product with the same three states (running,
 * matured, broken) and the same apply/break/reverse action set.
 * Unlike Time Of Deposit, CDs have no renewal-date history feature.
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   ✅ index()          → MIGRATED to Vue + Inertia. Renders
 *                          resources/js/Pages/CertificatesOfDeposits/Index.vue.
 *   ✅ create() / edit() → MIGRATED to Vue + Inertia. Both render the
 *                          same shared page,
 *                          resources/js/Pages/CertificatesOfDeposits/Form.vue,
 *                          distinguished by a `mode: 'create' | 'edit'` prop.
 *   ✅ viewPeriodInterest() → MIGRATED to Vue + Inertia. Renders
 *      resources/js/Pages/CertificatesOfDeposits/PeriodInterest.vue.
 *   ✅ store() / destroy() / applyDeposit() / applyBreak() /
 *      reverseDeposit() / applyPeriodInterest() / deletePeriodInterest()
 *      → presentation-only change: these already redirect back to the
 *      migrated index() page. The financial logic inside each of
 *      these methods is UNCHANGED, deliberately.
 *   ⚠️ update() → ONE real bug fixed (confirmed with the project
 *      owner): a call to a nonexistent method that was crashing every
 *      CD edit. See the docblock directly above the method.
 *   ⚠️ reverseBroken() → ONE real bug fixed (confirmed with the
 *      project owner): the same phantom-cash bug already found and
 *      fixed on TimeOfDeposit::reverseBroken(). See the docblock
 *      directly above the method.
 */
class CertificatesOfDepositsController
{
    use GeneralFunctions;
    protected function applyFilter(Request $request,Collection $collection):Collection{
		if(!count($collection)){
			return $collection;
		}
		$searchFieldName = $request->get('field');
		$dateFieldName =  'start_date' ; // change it 
		if($request->get('field') == 'end_date'){
			$dateFieldName = 'end_date';
		}
		
		
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
		
		return $collection->values();
	}
	/**
	 * The main "Certificates Of Deposit" list — 3 tabs: running,
	 * matured, broken.
	 *
	 * ✅ MIGRATED to Vue + Inertia. Renders
	 * resources/js/Pages/CertificatesOfDeposits/Index.vue.
	 *
	 * Date-range defaults follow the same rule agreed for Time Of
	 * Deposit: Running has NO default cutoff (a currently-open CD
	 * shouldn't be silently hidden just because it started a while
	 * ago), while Matured/Broken keep a rolling default window,
	 * clearly labeled on-screen so it's never a silent restriction.
	 */
	public function index(Company $company,Request $request,FinancialInstitution $financialInstitution)
	{
	
		
		$numberOfMonthsWindow = 18 ;
		$currentType = $request->get('active',CertificatesOfDeposit::RUNNING);
		$filterDates = [];
		foreach(CertificatesOfDeposit::getAllTypes() as $type){
			if ($type === CertificatesOfDeposit::RUNNING) {
				$startDate = $request->has('startDate') ? $request->input('startDate.'.$type) : null;
				$endDate = $request->has('endDate') ? $request->input('endDate.'.$type) : null;
			} else {
				$startDate = $request->has('startDate') ? $request->input('startDate.'.$type) : now()->subMonths($numberOfMonthsWindow)->format('Y-m-d');
				$endDate = $request->has('endDate') ? $request->input('endDate.'.$type) : now()->format('Y-m-d');
			}

			$filterDates[$type] = [
				'startDate'=>$startDate,
				'endDate'=>$endDate,
				'isDefaultWindow'=> $type !== CertificatesOfDeposit::RUNNING && !$request->has('startDate') && !$request->has('endDate'),
			];
		}
		/**
		 * * start of running certificates deposits 
		 */
		$runningCertificatesOfDepositsStartDate = $filterDates[CertificatesOfDeposit::RUNNING]['startDate'] ?? null ;
		$runningCertificatesOfDepositsEndDate = $filterDates[CertificatesOfDeposit::RUNNING]['endDate'] ?? null ;
		$runningCertificatesOfDeposits = $financialInstitution->runningCertificatesOfDeposits ;
		$runningCertificatesOfDeposits =  $runningCertificatesOfDeposits->filterByStartDate($runningCertificatesOfDepositsStartDate,$runningCertificatesOfDepositsEndDate) ;
		$runningCertificatesOfDeposits =  $currentType == CertificatesOfDeposit::RUNNING ? $this->applyFilter($request,$runningCertificatesOfDeposits):$runningCertificatesOfDeposits ;
		/**
		 * * end of running certificates deposits 
		 */
		
		 
		 
		 /**
		 * * start of matured certificates deposits 
		 */
		$maturedCertificatesOfDepositsStartDate = $filterDates[CertificatesOfDeposit::MATURED]['startDate'] ?? null ;
		$maturedCertificatesOfDepositsEndDate = $filterDates[CertificatesOfDeposit::MATURED]['endDate'] ?? null ;
		$maturedCertificatesOfDeposits = $financialInstitution->maturedCertificatesOfDeposits ;
		$maturedCertificatesOfDeposits =  $maturedCertificatesOfDeposits->filterByStartDate($maturedCertificatesOfDepositsStartDate,$maturedCertificatesOfDepositsEndDate) ;
		$maturedCertificatesOfDeposits =  $currentType == CertificatesOfDeposit::MATURED ? $this->applyFilter($request,$maturedCertificatesOfDeposits):$maturedCertificatesOfDeposits ;
		/**
		 * * end of matured certificates deposits 
		 */
		
		 
		 
		 	 /**
		 * * start of broken certificates deposits 
		 */
		$brokenCertificatesOfDepositsStartDate = $filterDates[CertificatesOfDeposit::BROKEN]['startDate'] ?? null ;
		$brokenCertificatesOfDepositsEndDate = $filterDates[CertificatesOfDeposit::BROKEN]['endDate'] ?? null ;
		$brokenCertificatesOfDeposits = $financialInstitution->brokenCertificatesOfDeposits ;
		$brokenCertificatesOfDeposits =  $brokenCertificatesOfDeposits->filterByStartDate($brokenCertificatesOfDepositsStartDate,$brokenCertificatesOfDepositsEndDate) ;
		$brokenCertificatesOfDeposits =  $currentType == CertificatesOfDeposit::BROKEN ? $this->applyFilter($request,$brokenCertificatesOfDeposits):$brokenCertificatesOfDeposits ;
		/**
		 * * end of broken certificates deposits 
		 */
		
		 
		
		$searchFields = [
			CertificatesOfDeposit::RUNNING=>[
				'start_date'=>__('Start Date'),
				'end_date'=>__('End Date'),
				'account_number'=>__('Account Number'),
				'currency'=>__('Currency'),
			],
			CertificatesOfDeposit::MATURED=>[
				'start_date'=>__('Start Date'),
				'end_date'=>__('End Date'),
				'account_number'=>__('Account Number'),
				'currency'=>__('Currency'),
			],
			CertificatesOfDeposit::BROKEN=>[
				'start_date'=>__('Start Date'),
				'end_date'=>__('End Date'),
				'account_number'=>__('Account Number'),
				'currency'=>__('Currency'),
			]
		];
		
		 
		$models = [
			CertificatesOfDeposit::RUNNING =>$runningCertificatesOfDeposits ,
			CertificatesOfDeposit::MATURED =>$maturedCertificatesOfDeposits ,
			CertificatesOfDeposit::BROKEN =>$brokenCertificatesOfDeposits ,
		];
		
		/**
		 * Flatten a CD collection into plain arrays for Inertia, with
		 * every action URL this row's row-menu could need pre-resolved.
		 */
		/**
		 * حسابات البنك الجارية بتتقرا مرة واحدة هنا وبتتمرر لكل صف علشان
		 * مايبقاش فيه استعلام لكل شهادة.
		 */
		$settlementAccounts = $financialInstitution->accounts ;
		$mapCertificates = function (Collection $certificates) use ($company, $financialInstitution, $settlementAccounts) {
			return $certificates->map(function (CertificatesOfDeposit $cd) use ($company, $financialInstitution, $settlementAccounts) {
				return [
					'id' => $cd->id,
					'status' => $cd->getStatus(),
					'start_date' => $cd->getStartDate(),
					'start_date_formatted' => $cd->getStartDateFormatted(),
					'end_date' => $cd->getEndDate(),
					'end_date_formatted' => $cd->getEndDateFormatted(),
					'account_number' => AccountNumberLabel::forOwnedInstrument($cd),
					'amount' => $cd->getAmount(),
					'amount_formatted' => $cd->getAmountFormatted(),
					'currency' => $cd->getCurrency(),
					'interest_rate_formatted' => $cd->getInterestRateFormatted(),
					'interest_amount' => $cd->getInterestAmount(),
					'interest_amount_formatted' => $cd->getInterestAmountFormatted(),
					'break_date_formatted' => $cd->getBreakDateFormatted(),
					'break_interest_amount_formatted' => $cd->getBreakInterestAmountFormatted(),
					'blocked_against_formatted' => $cd->getBlockedAgainstFormatted(),
					'is_due_today_or_greater' => $cd->isDueTodayOrGreater(),
					/**
					 * حساب التسوية اللي بوب اب الاستحقاق / الكسر بيسأل عنه — القيمة
					 * الافتراضية هي حساب الخصم الاصلي ، وبتبقى فاضية لو الوديعة
					 * اتسجلت opening balance.
					 */
					'settlement_account_id' => $cd->getSettlementOrDeductedFromAccountId(),
					'settlement_account_options' => $cd->getSettlementAccountOptions($settlementAccounts)->map(fn ($a) => [
						'id' => (int) $a->getId(),
						'account_number' => AccountNumberLabel::forOwnedInstrument($a),
					])->values(),
					'edit_url' => route('edit.certificates.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'certificatesOfDeposit' => $cd->id]),
					'delete_url' => route('delete.certificates.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'certificatesOfDeposit' => $cd->id]),
					'apply_deposit_url' => route('apply.deposit.to.certificate.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'certificatesOfDeposit' => $cd->id]),
					'apply_break_url' => route('apply.break.to.certificate.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'certificatesOfDeposit' => $cd->id]),
					'reverse_deposit_url' => route('reverse.deposit.to.certificate.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'certificatesOfDeposit' => $cd->id]),
					'reverse_broken_url' => route('reverse.broken.to.certificate.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'certificatesOfDeposit' => $cd->id]),
					'apply_period_interest_url' => route('apply.period.interest.to.certificates.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'certificatesOfDeposit' => $cd->id]),
					'view_period_interest_url' => route('view.period.interest.to.certificates.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'certificatesOfDeposit' => $cd->id]),
				];
			})->values();
		};

		return \Inertia\Inertia::render('CertificatesOfDeposits/Index', [
			'instructionsUrl' => route('view.instructions', ['company' => $company->id, 'page' => PageInstructions::CERTIFICATE_OF_DEPOSIT, 'financialInstitution' => $financialInstitution->id]),
			'company' => ['id' => $company->id],
			'financialInstitution' => [
				'id' => $financialInstitution->id,
				'name' => $financialInstitution->getName(),
			],
			'activeTab' => $currentType,
			'filterDates' => $filterDates,
			'canCreate' => hasAuthFor('certificate_of_deposit.create'),
			'deposits' => [
				CertificatesOfDeposit::RUNNING => $mapCertificates($runningCertificatesOfDeposits),
				CertificatesOfDeposit::MATURED => $mapCertificates($maturedCertificatesOfDeposits),
				CertificatesOfDeposit::BROKEN => $mapCertificates($brokenCertificatesOfDeposits),
			],
			'createUrl' => route('create.certificates.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
			'tabUrls' => [
				CertificatesOfDeposit::RUNNING => route('view.certificates.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'active' => CertificatesOfDeposit::RUNNING]),
				CertificatesOfDeposit::MATURED => route('view.certificates.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'active' => CertificatesOfDeposit::MATURED]),
				CertificatesOfDeposit::BROKEN => route('view.certificates.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'active' => CertificatesOfDeposit::BROKEN]),
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
	 * Shows the "Add Certificate Of Deposit" form.
	 *
	 * ✅ MIGRATED to Vue + Inertia — shares the same page component as
	 * edit() (resources/js/Pages/CertificatesOfDeposits/Form.vue),
	 * distinguished by the `mode: 'create'` prop. store() is
	 * UNCHANGED, deliberately.
	 */
	public function create(Company $company,FinancialInstitution $financialInstitution)
	{
		$accounts = $financialInstitution->accounts ;
        return \Inertia\Inertia::render('CertificatesOfDeposits/Form', [
			'instructionsUrl' => route('view.instructions', ['company' => $company->id, 'page' => PageInstructions::CERTIFICATE_OF_DEPOSIT_FORM, 'financialInstitution' => $financialInstitution->id]),
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
			'submitUrl' => route('store.certificates.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
			'backUrl' => route('view.certificates.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
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
	 * Stores a new CD. UNCHANGED, deliberately. Redirects to the
	 * migrated index().
	 */
	public function store(Company $company  ,FinancialInstitution $financialInstitution, StoreCertificateOfDepositRequest $request){
		
		$data = $request->only( $this->getCommonDataArr());
		$data += \App\Support\ShareholderAccounts\ShareholderAccountAccess::ownershipFromRequest($request);
		foreach(['start_date','end_date'] as $dateField){
			$data[$dateField] = $request->get($dateField) ? Carbon::make($request->get($dateField))->format('Y-m-d'):null;
		}
		$data['created_by'] = auth()->user()->id ;
		$data['company_id'] = $company->id ;
		$data['interest_amount'] = number_unformat($request->get('interest_amount')) ;
		$odooCode = $request->get('odoo_code') ;
		if($company->hasOdooIntegrationCredentials() && $odooCode ){
			$odooService = new OdooService($company);
			$odooCode = $request->get('odoo_code');
			$chartOfAccountId = $odooService->getChartOfAccountIdFromOdooCode($odooCode);
			$data['odoo_id'] = $chartOfAccountId ; 
			$data['journal_id'] =$odooService->getJournalIdFromChartOfAccountId($chartOfAccountId) ;
		}
		$deductedFromAccountId = $request->get('deducted_from_account_id',0) ;
		
		$model=$financialInstitution->certificatesOfDeposits()->create($data);
		/**
		 * @var CertificatesOfDeposit $model
		 */
		$model->handleDeductedForBankStatement($financialInstitution->id,$data['start_date'],number_unformat($request->get('amount')),$company->id,$deductedFromAccountId,$request->get('account_number'));
		$model->handleTdOrCdStoreDepositForOdoo(false);
		$type = $request->get('type',CertificatesOfDeposit::RUNNING);
		$activeTab = $type ; 
		
		return redirect()->route('view.certificates.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'active'=>$activeTab])->with('success',__('Data Store Successfully'));
		
	}
	
	/**
	 * Shows the "Edit Certificate Of Deposit" form.
	 *
	 * ✅ MIGRATED to Vue + Inertia — shares the same page component as
	 * create() (resources/js/Pages/CertificatesOfDeposits/Form.vue),
	 * distinguished by the `mode: 'edit'` prop. update() has the one
	 * confirmed bug fix noted below, otherwise unchanged.
	 */
	public function edit(Company $company , Request $request , FinancialInstitution $financialInstitution , CertificatesOfDeposit $certificatesOfDeposit){
		$accounts = $financialInstitution->accounts ;
        return \Inertia\Inertia::render('CertificatesOfDeposits/Form', [
			'instructionsUrl' => route('view.instructions', ['company' => $company->id, 'page' => PageInstructions::CERTIFICATE_OF_DEPOSIT_FORM, 'financialInstitution' => $financialInstitution->id]),
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
				'id' => $certificatesOfDeposit->id,
				'account_number' => $certificatesOfDeposit->getAccountNumber(),
				'currency' => $certificatesOfDeposit->getCurrency(),
				'odoo_code' => $certificatesOfDeposit->getOdooCode(),
				'deducted_from_account_id' => $certificatesOfDeposit->deducted_from_account_id,
				'maturity_amount_added_to_account_id' => $certificatesOfDeposit->getMaturityAmountAddedToAccountId(),
				'start_date' => $certificatesOfDeposit->getStartDate(),
				'end_date' => $certificatesOfDeposit->getEndDate(),
				'amount' => $certificatesOfDeposit->getAmount(),
				'interest_rate' => $certificatesOfDeposit->getInterestRate(),
				'interest_amount' => $certificatesOfDeposit->getInterestAmount(),
				'is_at_maturity' => !$certificatesOfDeposit->isPeriodically(),
			] + \App\Support\ShareholderAccounts\ShareholderAccountAccess::modelProps($certificatesOfDeposit),
			'submitUrl' => route('update.certificates.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'certificatesOfDeposit' => $certificatesOfDeposit->id]),
			'backUrl' => route('view.certificates.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
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
	 * owner before this fix was applied): this line used to call
	 * $certificatesOfDeposit->getDeductedFromAccountId() — a method
	 * that doesn't exist anywhere on this model or its traits.
	 * deducted_from_account_id is a plain database column, not a
	 * method. Calling an undefined method is a fatal PHP error, so
	 * every save of an existing Certificate Of Deposit was crashing
	 * here before reaching the database. (The sibling line on
	 * TimeOfDeposit::update() has the same call, but commented out —
	 * that's why TD never hit this.) Fixed by reading the raw column
	 * directly, same fix already applied on the TimeOfDeposit side.
	 */
	public function update(Company $company , UpdateCertificateOfDepositRequest $request , FinancialInstitution $financialInstitution,CertificatesOfDeposit $certificatesOfDeposit){
		$deductedFromAccountId = $request->get('deducted_from_account_id',0) ;
		$accountNumberHasChanged = $deductedFromAccountId != $certificatesOfDeposit->deducted_from_account_id;
		$data['updated_by'] = auth()->user()->id ;
		$data = $request->only($this->getCommonDataArr());
		$data += \App\Support\ShareholderAccounts\ShareholderAccountAccess::ownershipFromRequest($request);
		foreach(['start_date','end_date'] as $dateField){
			$data[$dateField] = $request->get($dateField) ? Carbon::make($request->get($dateField))->format('Y-m-d'):null;
		}
		$data['interest_amount'] = number_unformat($request->get('interest_amount')) ;
		$odooCode = $request->get('odoo_code') ;
		if($company->hasOdooIntegrationCredentials() && $odooCode ){
			$odooService = new OdooService($company);
			$odooCode = $request->get('odoo_code');
			$chartOfAccountId = $odooService->getChartOfAccountIdFromOdooCode($odooCode);
			$data['odoo_id'] = $chartOfAccountId ; 
			$data['journal_id'] =$odooService->getJournalIdFromChartOfAccountId($chartOfAccountId) ;
		}
		$certificatesOfDeposit->update($data);
		$certificatesOfDeposit->deletePeriodInterestAmounts();
	    $certificatesOfDeposit->handleDeductedForBankStatement($financialInstitution->id,$data['start_date'],number_unformat($request->get('amount')),$company->id,$deductedFromAccountId,$request->get('account_number'));
		$certificatesOfDeposit->handleTdOrCdStoreDepositForOdoo($accountNumberHasChanged);
		$type = $request->get('type',CertificatesOfDeposit::RUNNING);
		$activeTab = $type ;
		return redirect()->route('view.certificates.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'active'=>$activeTab])->with('success',__('Item Has Been Updated Successfully'));
	}
	/**
	 * Deletes a CD and its related bank-statement entries. UNCHANGED,
	 * deliberately.
	 */
	public function destroy(Company $company , FinancialInstitution $financialInstitution , CertificatesOFDeposit $certificatesOfDeposit)
	{
		$certificatesOfDeposit->deletePeriodInterestAmounts();
		$certificatesOfDeposit->deleteOdooRelations(false);
		CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($certificatesOfDeposit->currentAccountBankStatements);
		$certificatesOfDeposit->delete();
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}
	
	/**
	 * Applies a periodic (non-maturity) interest posting to a CD.
	 * UNCHANGED, deliberately.
	 */
	public function applyPeriodInterest(Company $company,Request $request,FinancialInstitution $financialInstitution,CertificatesOfDeposit $certificatesOfDeposit)
	{
		$periodInterestAmount = number_unformat($request->get('periodic_interest_amount')) ;
		$periodInterestDate = $request->get('periodic_interest_date') ;
		if(!$periodInterestDate){
			return redirect()->back()->with('fail',__('Period Interest Date Is Required'));
		}
		$certificatesOfDeposit->applyPeriodicInterestInStatement($financialInstitution,$periodInterestAmount,$periodInterestDate);
		$type = $request->get('type',CertificatesOfDeposit::RUNNING);
		$activeTab = $type ;
		return redirect()->route('view.certificates.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'active'=>$activeTab])->with('success',__('Item Has Been Updated Successfully'));
	}
	/**
	 * ✅ MIGRATED to Vue + Inertia. Renders
	 * resources/js/Pages/CertificatesOfDeposits/PeriodInterest.vue.
	 */
	public function viewPeriodInterest(Company $company,Request $request,FinancialInstitution $financialInstitution,CertificatesOfDeposit $certificatesOfDeposit)
	{
		$rows = CurrentAccountBankStatement::where('company_id',$company->id)->where('certificate_of_deposit_id',$certificatesOfDeposit->id)->where('is_period_cd_or_td_interest',1)->get();
		return \Inertia\Inertia::render('CertificatesOfDeposits/PeriodInterest', [
			'instructionsUrl' => route('view.instructions', ['company' => $company->id, 'page' => PageInstructions::CD_PERIOD_INTEREST, 'financialInstitution' => $financialInstitution->id]),
			'company' => ['id' => $company->id],
			'financialInstitution' => ['id' => $financialInstitution->id, 'name' => $financialInstitution->getName()],
			'certificatesOfDeposit' => ['id' => $certificatesOfDeposit->id, 'currency' => $certificatesOfDeposit->getCurrency()],
			'rows' => $rows->map(fn ($row) => [
				'id' => $row->id,
				'date' => $row->date,
				'amount_formatted' => number_format($row->debit, 2),
				'delete_url' => route('delete.period.interest.to.certificates.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'certificatesOfDeposit' => $certificatesOfDeposit->id, 'currentAccountBankStatement' => $row->id]),
			])->values(),
			'backUrl' => route('view.certificates.of.deposit', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
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
	 */
	public function deletePeriodInterest(Company $company,Request $request,FinancialInstitution $financialInstitution,CertificatesOfDeposit $certificatesOfDeposit,CurrentAccountBankStatement $currentAccountBankStatement)
	{
		$certificatesOfDeposit->deletePeriodInterest($currentAccountBankStatement);
		return redirect()->back()->with('success',__('Item Has Been Updated Successfully'));
	}
	
	
	/**
	 * * هنا اليوزر هياكد انه نزله الفايدة المستحقة وبالتالي هنزلها في حسابه الجاري اللي هو اختارة من الفورمة
	 */
	/**
	 * Marks a running CD as matured and posts the interest/maturity
	 * amount to the current account. UNCHANGED, deliberately.
	 */
	public function applyDeposit(Company $company,Request $request,FinancialInstitution $financialInstitution,CertificatesOfDeposit $certificatesOfDeposit)
	{
		$actualDepositDate = Carbon::make($request->get('deposit_date')) ;
		if(!$actualDepositDate){
			return redirect()->back()->with('fail',__('Deposit Date Is Required'));
		}
		$actualDepositDate = $actualDepositDate->format('Y-m-d') ;
		$actualInterestAmount  = number_unformat($request->get('actual_interest_amount')) ;
		/**
		 * * حساب التسوية اللي اصل الوديعة هيترد عليه — اليوزر بيختاره من البوب اب
		 * * وقيمته الافتراضية هي حساب الخصم الاصلي لو كان موجود ، ولو الوديعة
		 * * opening balance
		 * * فا لازم يختاره هنا لان مافيش
		 * * deducted_from_account_id
		 */
		$settlementAccountId = $request->get('settlement_account_id') ;
		if(!$certificatesOfDeposit->isEligibleSettlementAccount($settlementAccountId,$financialInstitution->accounts)){
			return redirect()->back()->with('fail',__('Please Select A Valid Settlement Account'));
		}
		$certificateType = CertificatesOfDeposit::MATURED ;
		$certificatesOfDeposit->update([
			'settlement_account_id'=>$settlementAccountId,
			'deposit_date'=>$actualDepositDate,
			'actual_interest_amount'=>$actualInterestAmount,
			'status'=>$certificateType
		]);
		$certificatesOfDeposit->handleTdOrCdStoreDepositForOdoo(true,$actualDepositDate);
		$accountType = AccountType::where('slug',AccountType::CURRENT_ACCOUNT)->first() ;
		if($actualInterestAmount > 0){
			$currentAccount = $certificatesOfDeposit->handleDebitStatement($financialInstitution->id , $accountType , $certificatesOfDeposit->getMaturityAmountAddedToAccountNumber() , null , $actualDepositDate,$actualInterestAmount,null,null,1,null,null,false,true);
			$certificatesOfDeposit->storePeriodInterestOdooRelations($currentAccount,$actualDepositDate,$actualInterestAmount);
			// ddd
		}
		$certificatesOfDeposit->handleDebitStatement($financialInstitution->id , $accountType , $certificatesOfDeposit->getMaturityAmountAddedToAccountNumber() , null , $actualDepositDate,$certificatesOfDeposit->getAmount());
		return redirect()->route('view.certificates.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id ,'active'=>$certificateType])->with('success',__('Certificate Has Been Marked As Matured'));
	}
	
	
	
		/**
	 * * هنا اليوزر هيعكس عملية التاكيد اللي كان اكدها اكنه عملها بالغلط فا هنرجع كل حاجه زي ما كانت ونحذف القيم اللي في جدول ال 
	 * * current account bank statements
	 */
	/**
	 * Reverses applyDeposit(). UNCHANGED, deliberately — already
	 * correctly excludes DEDUCTED_FOR_CURRENT_ACCOUNT (this is the
	 * pattern reverseBroken() was missing, now fixed to match).
	 */
	public function reverseDeposit(Company $company,Request $request,FinancialInstitution $financialInstitution,CertificatesOfDeposit $certificatesOfDeposit)
	{
		$certificateType = CertificatesOfDeposit::RUNNING ;
			$breakInterestStatement = $certificatesOfDeposit->currentAccountBankStatements->where('is_break_interest',1)->first();
		/**
		 * * الشهادة اللي مانزلش عليها فايدة مالهاش
		 * * break interest statement
		 * * ، وreverseOdooDeposit بتاخد الاستيتمنت مش nullable — فا من غير
		 * * الشرط ده عكس الاستحقاق كان بيرمي TypeError. نفس الحماية اللي في
		 * * TimeOfDepositsController::reverseDeposit()
		 */
		if($breakInterestStatement){
			$certificatesOfDeposit->reverseOdooDeposit($breakInterestStatement);
		}
		$certificatesOfDeposit->update([
			'settlement_account_id'=>null,
			'deposit_date'=>null,
			'actual_interest_amount'=>null,
			'status'=>CertificatesOfDeposit::RUNNING
		]);
		/**
		 * * هنشيل قيم ال
		 * * current account bank statement
		 */
		CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($certificatesOfDeposit->currentAccountBankStatements->where('type','!=',CurrentAccountBankStatement::DEDUCTED_FOR_CURRENT_ACCOUNT));
		return redirect()->route('view.certificates.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id ,'active'=>$certificateType])->with('success',__('Certificate Has Been Marked As Matured'));
	}
	
	
	/**
	 * * لو انت عملت شهادة ايداع في البنك تقدر تكسرها وتاخد قيمة الشهادة بتاعتك بس بيطبق عليك غرامة
	 */
	/**
	 * Breaks a running CD early (with penalty). UNCHANGED, deliberately.
	 */
	public function applyBreak(Company $company,Request $request,FinancialInstitution $financialInstitution,CertificatesOfDeposit $certificatesOfDeposit)
	{
		$breakDate = Carbon::make($request->get('break_date')) ;
		if(!$breakDate){
			return redirect()->back()->with('fail',__('Break Date Is Required'));
		}
		$breakDate = $breakDate->format('Y-m-d') ;
		$breakInterestAmount  = $request->get('break_interest_amount') ;
		$breakChargeAmount  = $request->get('break_charge_amount',0) ;
		$amount  = $request->get('amount') ;
		/**
		 * * حساب التسوية اللي اصل الوديعة هيترد عليه — اليوزر بيختاره من البوب اب
		 * * وقيمته الافتراضية هي حساب الخصم الاصلي لو كان موجود ، ولو الوديعة
		 * * opening balance
		 * * فا لازم يختاره هنا لان مافيش
		 * * deducted_from_account_id
		 */
		$settlementAccountId = $request->get('settlement_account_id') ;
		if(!$certificatesOfDeposit->isEligibleSettlementAccount($settlementAccountId,$financialInstitution->accounts)){
			return redirect()->back()->with('fail',__('Please Select A Valid Settlement Account'));
		}
		$certificateType = CertificatesOfDeposit::BROKEN ;
		$certificatesOfDeposit->update([
			'settlement_account_id'=>$settlementAccountId,
			'break_date'=>$breakDate,
			'break_interest_amount'=>$breakInterestAmount,
			'status'=>$certificateType,
			'break_charge_amount'=>$breakChargeAmount
		]);
			$certificatesOfDeposit->handleTdOrCdStoreDepositForOdoo(true,$breakDate);
		// $certificatesOfDeposit->storeOdooBreak(false);
		$accountType = AccountType::where('slug',AccountType::CURRENT_ACCOUNT)->first() ;
		/**
		 * * اول حاجه هنضيف دبت بقيمة الشهادة 
		 */
		if($amount > 0){
			$certificatesOfDeposit->handleDebitStatement($financialInstitution->id , $accountType , $certificatesOfDeposit->getMaturityAmountAddedToAccountNumber() , null , $breakDate,$amount);
		}
		/**
		 * * تاني حاجه هنضيف دبت بقيمة الفايدة
		 */
		if($breakInterestAmount > 0){
			$certificatesOfDeposit->handleDebitStatement($financialInstitution->id , $accountType , $certificatesOfDeposit->getMaturityAmountAddedToAccountNumber() , null , $breakDate,$breakInterestAmount);
		}
		/**
		 * * واخيرا هنضيف كريدت بقيمة الرسوم الادارية
		 */
		if($breakChargeAmount){
			$certificatesOfDeposit->handleCreditStatement($company->id,$financialInstitution->id , $accountType , $certificatesOfDeposit->getMaturityAmountAddedToAccountNumber() , null , $breakDate,$breakChargeAmount);
		}
		
		return redirect()->route('view.certificates.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id ,'active'=>$certificateType])->with('success',__('Certificate Has Been Marked As Broken'));
	}
	
	
	/**
	 * * هنا اليوزر هيعكس عملية الكسر اللي كان اكدها اكنه عملها بالغلط فا هنرجع كل حاجه زي ما كانت ونحذف القيم اللي في جدول ال 
	 * * current account bank statements
	 */
	/**
	 * ⚠️ REAL BUG FIXED HERE (found and confirmed with the project
	 * owner before this fix was applied): this method used to delete
	 * $certificatesOfDeposit->currentAccountBankStatements with NO
	 * filter at all — unlike reverseDeposit() just above, which
	 * correctly excludes CurrentAccountBankStatement::DEDUCTED_FOR_CURRENT_ACCOUNT
	 * (the entry recording money leaving the funding account when the
	 * CD was first created). Without that filter, every Break →
	 * Reverse Broken on a CD funded from a real account (not "Opening
	 * Balance") deleted that original entry too — same phantom-cash
	 * bug already found and fixed on TimeOfDeposit::reverseBroken().
	 * Fixed by adding the same exclusion filter reverseDeposit() above
	 * already uses correctly.
	 */
	public function reverseBroken(Company $company,Request $request,FinancialInstitution $financialInstitution,CertificatesOfDeposit $certificatesOfDeposit)
	{
		$certificateType = CertificatesOfDeposit::RUNNING ;
		$certificatesOfDeposit->update([
			'settlement_account_id'=>null,
			'break_date'=>null,
			'break_interest_amount'=>null,
	//		'status'=>$certificateType,
			'break_charge_amount'=>null,
			'status'=>CertificatesOfDeposit::RUNNING
		]);
		/**
		 * * هنشيل قيم ال
		 * * current account bank statement
		 */
		
		 CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($certificatesOfDeposit->currentAccountBankStatements->where('type','!=',CurrentAccountBankStatement::DEDUCTED_FOR_CURRENT_ACCOUNT));
		 
		 
		return redirect()->route('view.certificates.of.deposit',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id ,'active'=>$certificateType])->with('success',__('Certificate Has Been Marked As Matured'));
	}
}
