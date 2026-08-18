<?php
namespace App\Http\Controllers;
use App\Enums\LcTypes;
use App\Http\Requests\StoreLetterOfCreditFacilityRequest;
use App\Models\AccountType;
use App\Models\CertificatesOfDeposit;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\LcOverdraftBankStatement;
use App\Models\LetterOfCreditCashCoverStatement;
use App\Models\LetterOfCreditFacility;
use App\Models\LetterOfCreditIssuance;
use App\Models\LetterOfCreditStatement;
use App\Models\TimeOfDeposit;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * LetterOfCreditFacilityController
 * ------------------------------------------------------------------
 * Manages LC Facility — the credit line a bank grants for issuing
 * Letters Of Credit against. Unlike LG Facility, an LC Facility has a
 * `type`: 'unsecured' (Limit entered directly) or 'fully-secured'
 * (Limit auto-calculated as an existing CD/TD's amount × lending
 * percentage — same concept, and same UI pattern, as
 * FullySecuredOverdraftController). Its "Term & Conditions" are a
 * fixed 3-row matrix, one row per LC type (Sight LC, Deferred, Cash
 * Against Document — see App\Enums\LcTypes).
 *
 * `updateOutstandingBalanceAndLimits()` and
 * `getLcFacilityBasedOnFinancialInstitution()` are pure AJAX-style
 * data endpoints consumed by the LC Issuance form — stay exactly as
 * they are; JSON responses are correct here since nothing calls them
 * via Inertia navigation.
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   ✅ index() / create() / edit() → MIGRATED to Vue + Inertia.
 *      create()/edit() render the same shared page,
 *      resources/js/Pages/LetterOfCreditFacility/Form.vue,
 *      distinguished by a `mode: 'create' | 'edit'` prop.
 *   ✅ getCommonDataArr() → checked against the actual database
 *      schema before building (per the project's "check every
 *      sibling" rule — this is the same field list shape that was a
 *      real, broken bug on Fully Secured Overdraft). Every field
 *      here DOES correspond to a real column on
 *      `letter_of_credit_facilities`. No equivalent bug found here.
 *   ⚠️ update() → 'updated_by' was being set then immediately wiped
 *      out by the next line overwriting the whole $data array — the
 *      same bug already found and fixed on Time Of Deposit,
 *      Certificates Of Deposit, Fully Secured Overdraft, and LG
 *      Facility (see roadmap §14). Fixed here too — same bug class,
 *      5th confirmed instance.
 *   ✅ store() / update() / destroy() / mergeConditionalValuesToRequest()
 *      / getCommonDataArr() → presentation-only change (response
 *      type for create/edit, plus the updated_by fix above).
 *      Financial logic — the type toggle, the CD/TD-based limit
 *      calculation, the term & conditions matrix, the LC/cash-cover
 *      statement cleanup — UNCHANGED, deliberately.
 *   ℹ️ No Odoo-related column exists on `letter_of_credit_facilities`
 *      (unlike Fully Secured Overdraft's `odoo_code`) — confirmed
 *      against the schema, not omitted by mistake.
 */
class LetterOfCreditFacilityController
{
    use GeneralFunctions;
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
	 * Builds the flat list of running CD/TD accounts eligible to
	 * secure a Fully Secured LC Facility — same helper shape as
	 * FullySecuredOverdraftController::buildCdOrTdAccounts(), reused
	 * here since it's the same "pick an existing CD/TD, filtered by
	 * type and currency" concept.
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
	 * The main "LC Facility" list — one flat list per financial
	 * institution.
	 *
	 * ✅ MIGRATED to Vue + Inertia. Renders
	 * resources/js/Pages/LetterOfCreditFacility/Index.vue.
	 */
	public function index(Company $company,Request $request,FinancialInstitution $financialInstitution)
	{
		$letterOfCreditFacilities = $financialInstitution->letterOfCreditFacilities ;
		$letterOfCreditFacilities =   $this->applyFilter($request,$letterOfCreditFacilities) ;

		return \Inertia\Inertia::render('LetterOfCreditFacility/Index', [
			'company' => ['id' => $company->id],
			'financialInstitution' => ['id' => $financialInstitution->id, 'name' => $financialInstitution->getName()],
			'canCreate' => hasAuthFor('lc_facility.create'),
			'createUrl' => route('create.letter.of.credit.facility', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
			'lcTypes' => LcTypes::getAll(),
			'rows' => $letterOfCreditFacilities->map(function (LetterOfCreditFacility $lcf) use ($company, $financialInstitution) {
				$latestChapter = $lcf->getLatestTerms();
				return [
					'id' => $lcf->id,
					'name' => $lcf->getName(),
					'type' => $lcf->getType(),
					'type_formatted' => LetterOfCreditFacility::getTypes()[$lcf->getType()] ?? $lcf->getType(),
					'contract_start_date_formatted' => $lcf->getCurrentChapterStartDateFormatted(),
					'contract_end_date_formatted' => $lcf->getContractEndDateFormatted(),
					'currency' => $lcf->getCurrency(),
					'limit_formatted' => $lcf->getLimitFormatted(),
					'borrowing_rate_formatted' => number_format((float) $lcf->borrowing_rate, 2),
					'bank_margin_rate_formatted' => number_format((float) $lcf->bank_margin_rate, 2),
					'interest_rate_formatted' => number_format((float) $lcf->interest_rate, 2),
					'term_and_conditions' => ($latestChapter?->termAndConditions ?? $lcf->termAndConditions)->map(fn ($tc) => [
						'lc_type_formatted' => $tc->getLcTypeFormatted(),
						'cash_cover_rate_formatted' => $tc->getCashCoverRate() . ' %',
						'commission_rate_formatted' => $tc->getCommissionRate() . ' %',
						'min_commission_fees_formatted' => number_format($tc->getMinCommissionFees()),
						'issuance_fees_formatted' => number_format($tc->getIssuanceFees()),
					])->values(),
					'edit_url' => route('edit.letter.of.credit.facility', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'letterOfCreditFacility' => $lcf->id]),
					'delete_url' => route('delete.letter.of.credit.facility', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'letterOfCreditFacility' => $lcf->id]),
					'renew_url' => route('letter-of-credit-facility.renew', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'letterOfCreditFacility' => $lcf->id]),
					'delete_renewal_url' => route('letter-of-credit-facility.delete-renewal', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'letterOfCreditFacility' => $lcf->id]),
					'has_renewals' => $lcf->hasRenewals(),
					'terms_history' => $lcf->termsHistories->map(fn ($t) => [
						'id' => $t->id,
						'effective_date_formatted' => $t->getEffectiveDateFormatted(),
						'contract_end_date_formatted' => $t->getContractEndDateFormatted(),
						'limit_formatted' => $t->getLimitFormatted(),
						'borrowing_rate' => $t->borrowing_rate,
						'bank_margin_rate' => $t->bank_margin_rate,
						'interest_rate' => $t->interest_rate,
						'is_original' => (bool) $t->is_original,
						'term_and_conditions' => $t->termAndConditions->map(fn ($tc) => [
							'lc_type_formatted' => $tc->getLcTypeFormatted(),
							'cash_cover_rate_formatted' => $tc->getCashCoverRate() . ' %',
							'commission_rate_formatted' => $tc->getCommissionRate() . ' %',
							'min_commission_fees_formatted' => number_format($tc->getMinCommissionFees()),
							'issuance_fees_formatted' => number_format($tc->getIssuanceFees()),
						])->values(),
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
	 * Shows the "Add LC Facility" form.
	 *
	 * ✅ MIGRATED to Vue + Inertia — shares the same page component as
	 * edit() (resources/js/Pages/LetterOfCreditFacility/Form.vue),
	 * distinguished by the `mode: 'create'` prop.
	 */
	public function create(Company $company,FinancialInstitution $financialInstitution)
	{
        return \Inertia\Inertia::render('LetterOfCreditFacility/Form', [
			'mode' => 'create',
			'company' => ['id' => $company->id],
			'financialInstitution' => ['id' => $financialInstitution->id, 'name' => $financialInstitution->getName()],
			'currencies' => getCurrencies(),
			'lcTypes' => LcTypes::getAll(),
			'facilityTypes' => LetterOfCreditFacility::getTypes(),
			'cdOrTdAccountTypes' => AccountType::onlyCdOrTdAccounts()->get()->map(fn ($t) => ['id' => $t->id, 'name' => $t->getName()])->values(),
			'cdOrTdAccounts' => $this->buildCdOrTdAccounts($company, $financialInstitution),
			'model' => null,
			'submitUrl' => route('store.letter.of.credit.facility', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
			'backUrl' => route('view.letter.of.credit.facility', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
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
		return ['name','cd_or_td_currency','cd_or_td_account_type_id','cd_or_td_id','cd_or_td_amount','cd_or_td_interest','cd_or_td_lending_percentage','type','contract_start_date','contract_end_date','currency','limit','borrowing_rate','bank_margin_rate','interest_rate','min_interest_rate','highest_debt_balance_rate','admin_fees_rate'];
	}
	protected function mergeConditionalValuesToRequest($request):void
	{
		$type = $request->get('type');
		$isFullySecured = $type == LetterOfCreditFacility::FULLY_SECURED;
		$request->merge([
			'limit'=>$isFullySecured ? $request->get('cd_or_td_limit',0) : $request->get('limit'),
			'cd_or_td_currency'=>$isFullySecured ? $request->get('cd_or_td_currency'):null,
			'cd_or_td_account_type_id'=>$isFullySecured ? $request->get('cd_or_td_account_type_id'):null,
			'cd_or_td_id'=>$isFullySecured ? $request->get('cd_or_td_id'):null,
			'cd_or_td_amount'=>$isFullySecured ? $request->get('cd_or_td_amount'):null,
			'cd_or_td_interest'=>$isFullySecured ? $request->get('cd_or_td_interest'):null,
			'cd_or_td_lending_percentage'=>$isFullySecured ? $request->get('cd_or_td_lending_percentage'):null,
		]);
	}

	/**
	 * Stores a new LC Facility, including its term & conditions
	 * matrix. UNCHANGED, deliberately.
	 */
	public function store(Company $company  ,FinancialInstitution $financialInstitution, StoreLetterOfCreditFacilityRequest $request){
		
		$this->mergeConditionalValuesToRequest($request);
		$data = $request->only( $this->getCommonDataArr());
		foreach(['contract_start_date','contract_end_date'] as $dateField){
			$data[$dateField] = $request->get($dateField) ? Carbon::make($request->get($dateField))->format('Y-m-d'):null;
		}
		$termAndConditions = $request->get('termAndConditions',[]) ;
		$data['created_by'] = auth()->user()->id ;
		$data['company_id'] = $company->id ;
		// $data['outstanding_amount'] = $data['outstanding_amount'] ? $data['outstanding_amount']: 0; 
		/**
		 * @var LetterOfCreditFacility $letterOfCreditFacility
		 */
		$letterOfCreditFacility = $financialInstitution->LetterOfCreditFacilities()->create($data);

		$letterOfCreditFacility->handleEndOfMonthInterestForOverdraft($data['contract_start_date'],$data['contract_end_date'],$company->id);

		/**
		 * Facility Renewal — Phase 6. Every facility gets its
		 * "chapter one" terms-history row the moment it's created —
		 * same fix already applied to every earlier facility type —
		 * so its first-ever Renew doesn't wrongly become the ONLY
		 * history row.
		 */
		$originalTermsHistory = $letterOfCreditFacility->createOriginalTermsHistory();

		$currencyName = $letterOfCreditFacility->getCurrency();
		$source = LetterOfCreditIssuance::LC_FACILITY;

		foreach($termAndConditions as $termAndConditionArr){
			$termAndConditionArr['company_id'] = $company->id ;
			// $termAndConditionArr['outstanding_date'] = $request->get('outstanding_date');
			// $currentOutstandingBalance = $termAndConditionArr['outstanding_balance'] ;
			// $currentCashCover = $termAndConditionArr['cash_cover_rate'];
			
		//	$currentLcType = $termAndConditionArr['lc_type'] ;
			// if($currentOutstandingBalance){
				$letterOfCreditFacility->termAndConditions()->create(array_merge($termAndConditionArr , [
					'terms_history_id' => $originalTermsHistory->id,
				]));
			// }
			// if($currentOutstandingBalance > 0){
			// 	$letterOfCreditFacility->handleLetterOfCreditStatement($financialInstitution->id,$source,$letterOfCreditFacility->id,$currentLcType,$company->id,$termAndConditionArr['outstanding_date'],0,0,$currentOutstandingBalance,$currencyName,0,0,LetterOfCreditIssuance::LC_FACILITY_BEGINNING_BALANCE);
				
			// }
			// $cashCoverOpeningBalance = $currentCashCover / 100 * $currentOutstandingBalance ;
			// if( $cashCoverOpeningBalance > 0 ){
			// 	$letterOfCreditFacility->handleLetterOfCreditCashCoverStatement($financialInstitution->id,$source,$letterOfCreditFacility->id,$currentLcType,$company->id,$termAndConditionArr['outstanding_date'],0,$cashCoverOpeningBalance,0,$currencyName,0,LetterOfCreditIssuance::LC_FACILITY_BEGINNING_BALANCE);
			// }

		}
		// $type = $request->get('type','letter-of-credit-facilities');
		// $activeTab = $type ;
		
		$activeTab = 'letter-of-credit-facilities' ;

		return redirect()->route('view.letter.of.credit.facility',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'active'=>$activeTab])->with('success',__('Data Store Successfully'));

	}

	/**
	 * Shows the "Edit LC Facility" form.
	 *
	 * ✅ MIGRATED to Vue + Inertia — shares the same page component as
	 * create() (resources/js/Pages/LetterOfCreditFacility/Form.vue),
	 * distinguished by the `mode: 'edit'` prop.
	 */
	public function edit(Company $company , Request $request , FinancialInstitution $financialInstitution , LetterOfCreditFacility $letterOfCreditFacility){

        return \Inertia\Inertia::render('LetterOfCreditFacility/Form', [
			'mode' => 'edit',
			'company' => ['id' => $company->id],
			'financialInstitution' => ['id' => $financialInstitution->id, 'name' => $financialInstitution->getName()],
			'currencies' => getCurrencies(),
			'lcTypes' => LcTypes::getAll(),
			'facilityTypes' => LetterOfCreditFacility::getTypes(),
			'cdOrTdAccountTypes' => AccountType::onlyCdOrTdAccounts()->get()->map(fn ($t) => ['id' => $t->id, 'name' => $t->getName()])->values(),
			'cdOrTdAccounts' => $this->buildCdOrTdAccounts($company, $financialInstitution),
			'model' => [
				'id' => $letterOfCreditFacility->id,
				'name' => $letterOfCreditFacility->getName(),
				'type' => $letterOfCreditFacility->getType(),
				'contract_start_date' => $letterOfCreditFacility->getContractStartDate(),
				'contract_end_date' => $letterOfCreditFacility->getContractEndDate(),
				'currency' => $letterOfCreditFacility->getCurrency(),
				'limit' => $letterOfCreditFacility->getLimit(),
				'cd_or_td_currency' => $letterOfCreditFacility->cd_or_td_currency,
				'cd_or_td_account_type_id' => $letterOfCreditFacility->getCdOrTdAccountTypeId(),
				'cd_or_td_id' => $letterOfCreditFacility->getCdOrTdId(),
				'cd_or_td_amount' => $letterOfCreditFacility->cd_or_td_amount,
				'cd_or_td_interest' => $letterOfCreditFacility->cd_or_td_interest,
				'cd_or_td_lending_percentage' => $letterOfCreditFacility->cd_or_td_lending_percentage,
				'borrowing_rate' => $letterOfCreditFacility->getBorrowingRate(),
				'bank_margin_rate' => $letterOfCreditFacility->bank_margin_rate,
				'interest_rate' => $letterOfCreditFacility->interest_rate,
				'min_interest_rate' => $letterOfCreditFacility->min_interest_rate,
				'highest_debt_balance_rate' => $letterOfCreditFacility->highest_debt_balance_rate,
				'term_and_conditions' => collect(LcTypes::getAll())->map(function ($label, $lcType) use ($letterOfCreditFacility) {
					$tc = $letterOfCreditFacility->termAndConditionForLcType($lcType);
					return [
						'lc_type' => $lcType,
						'cash_cover_rate' => $tc ? $tc->getCashCoverRate() : 0,
						'commission_rate' => $tc ? $tc->getCommissionRate() : 0,
						'min_commission_fees' => $tc ? $tc->getMinCommissionFees() : 0,
						'issuance_fees' => $tc ? $tc->getIssuanceFees() : 0,
					];
				})->values(),
			],
			'submitUrl' => route('update.letter.of.credit.facility', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'letterOfCreditFacility' => $letterOfCreditFacility->id]),
			'backUrl' => route('view.letter.of.credit.facility', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
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
	 * Updates an existing LC Facility, including replacing its term &
	 * conditions matrix. Financial logic UNCHANGED, deliberately.
	 * ⚠️ Fixed here: 'updated_by' was set, then immediately wiped out
	 * by the very next line overwriting the whole $data array — the
	 * same bug already found and fixed on Time Of Deposit,
	 * Certificates Of Deposit, Fully Secured Overdraft, and LG
	 * Facility.
	 */
	public function update(Company $company , StoreLetterOfCreditFacilityRequest $request , FinancialInstitution $financialInstitution,LetterOfCreditFacility $letterOfCreditFacility){
		$this->mergeConditionalValuesToRequest($request);
		$termAndConditions =  $request->get('termAndConditions',[]) ;
        $source = LetterOfCreditIssuance::LC_FACILITY;
		$data = $request->only($this->getCommonDataArr());
		$data['updated_by'] = auth()->user()->id ;
		foreach(['contract_start_date','contract_end_date'] as $dateField){
			$data[$dateField] = $request->get($dateField) ? Carbon::make($request->get($dateField))->format('Y-m-d'):null;
		}

     $letterOfCreditFacility->update($data);
     $letterOfCreditFacility->handleEndOfMonthInterestForOverdraft($data['contract_start_date'],$data['contract_end_date'],$company->id);
     $currencyName = $letterOfCreditFacility->getCurrency();
     LetterOfCreditStatement::deleteButTriggerChangeOnLastElement($letterOfCreditFacility->letterOfCreditStatements->where('type',LetterOfCreditIssuance::LC_FACILITY_BEGINNING_BALANCE));
     LetterOfCreditCashCoverStatement::deleteButTriggerChangeOnLastElement($letterOfCreditFacility->letterOfCreditCashCoverStatements->where('type',LetterOfCreditIssuance::LC_FACILITY_BEGINNING_BALANCE));

		/**
		 * Facility Renewal — Phase 6. The regular Edit screen always
		 * edits whichever chapter is CURRENTLY the live, running one
		 * (same rule as Clean/Fully Secured Overdraft) — so this
		 * only ever touches the LATEST chapter's rate rows, never a
		 * past renewal's. If the facility somehow has zero
		 * terms-history rows yet (pre-dates this feature), its
		 * Original chapter is backfilled first.
		 */
		if ($letterOfCreditFacility->termsHistories()->count() === 0) {
			$letterOfCreditFacility->createOriginalTermsHistory();
		}
		$latestChapter = $letterOfCreditFacility->getLatestTerms();

		$letterOfCreditFacility->termAndConditions()->where('terms_history_id', $latestChapter->id)->get()->each(function($termAndCondition){
			$termAndCondition->delete();
		});

		foreach($termAndConditions as $termAndConditionArr){
			$letterOfCreditFacility->termAndConditions()->create(array_merge($termAndConditionArr , [
				'terms_history_id' => $latestChapter->id,
			]));
            // $termAndConditionArr['outstanding_date'] = $request->get('outstanding_date');
			// $currentOutstandingBalance = $termAndConditionArr['outstanding_balance'] ;
			$currentCashCoverRate = $termAndConditionArr['cash_cover_rate'] / 100  ;
			// $currentCashCoverBeginningBalance  = $currentOutstandingBalance * $currentCashCoverRate ; 
			$currentLcType = $termAndConditionArr['lc_type'] ;
			// if($currentOutstandingBalance > 0 ){
			// 	$letterOfCreditFacility->handleLetterOfCreditStatement($financialInstitution->id,$source,$letterOfCreditFacility->id,$currentLcType,$company->id,$termAndConditionArr['outstanding_date'],0,0,$currentOutstandingBalance,$currencyName,0,0,LetterOfCreditIssuance::LC_FACILITY_BEGINNING_BALANCE);
			// }
			// if($currentCashCoverBeginningBalance > 0){
			// 	$letterOfCreditFacility->handleLetterOfCreditCashCoverStatement($financialInstitution->id,$source,$letterOfCreditFacility->id,$currentLcType,$company->id,$termAndConditionArr['outstanding_date'],0,$currentCashCoverBeginningBalance,0,$currencyName,0,LetterOfCreditIssuance::LC_FACILITY_BEGINNING_BALANCE);
			// }
			

		}

		$latestChapter->update([
			'effective_date' => $latestChapter->is_original ? $letterOfCreditFacility->contract_start_date : $latestChapter->effective_date,
			'limit' => $letterOfCreditFacility->limit,
			'cd_or_td_lending_percentage' => $letterOfCreditFacility->cd_or_td_lending_percentage,
			'borrowing_rate' => $letterOfCreditFacility->borrowing_rate,
			'bank_margin_rate' => $letterOfCreditFacility->bank_margin_rate,
			'interest_rate' => $letterOfCreditFacility->interest_rate,
			'min_interest_rate' => $letterOfCreditFacility->min_interest_rate,
			'highest_debt_balance_rate' => $letterOfCreditFacility->highest_debt_balance_rate,
			'admin_fees_rate' => $letterOfCreditFacility->admin_fees_rate,
			'contract_end_date' => $letterOfCreditFacility->contract_end_date,
		]);
		// $type = $request->get('type','letter-of-credit-facilities');
		
		// $activeTab = $type ;
		$activeTab = 'letter-of-credit-facilities' ;
		return redirect()->route('view.letter.of.credit.facility',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'active'=>$activeTab])->with('success',__('Item Has Been Updated Successfully'));


	}

	/**
	 * Deletes an LC Facility and its term & conditions rows /
	 * statement entries. UNCHANGED, deliberately.
	 */
	public function destroy(Company $company , FinancialInstitution $financialInstitution , LetterOfCreditFacility $letterOfCreditFacility)
	{

         LetterOfCreditStatement::deleteButTriggerChangeOnLastElement($letterOfCreditFacility->letterOfCreditStatements
		//  ->where('type',LetterOfCreditIssuance::LC_FACILITY_BEGINNING_BALANCE)
		);
         LetterOfCreditCashCoverStatement::deleteButTriggerChangeOnLastElement($letterOfCreditFacility->letterOfCreditCashCoverStatements
		//  ->where('type',LetterOfCreditIssuance::LC_FACILITY_BEGINNING_BALANCE)
		);
		 LcOverdraftBankStatement::deleteButTriggerChangeOnLastElement($letterOfCreditFacility->lcOverdraftBankStatements);

		$letterOfCreditFacility->termAndConditions->each(function($termAndCondition){
            $termAndCondition->delete();

		});
		$letterOfCreditFacility->delete();
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}

	/**
	 * Facility Renewal — Phase 6. Records a new dated set of terms
	 * (both the flat Financing Terms & Conditions and the full 3-row
	 * LC-type matrix) for an EXISTING LC Facility — see
	 * LetterOfCreditFacility::renew() for the full rules.
	 */
	public function renew(Company $company, \App\Http\Requests\RenewLetterOfCreditFacilityRequest $request, FinancialInstitution $financialInstitution, LetterOfCreditFacility $letterOfCreditFacility)
	{
		$effectiveDate = Carbon::make($request->get('effective_date'))->format('Y-m-d');
		$contractEndDate = $request->get('contract_end_date')
			? Carbon::make($request->get('contract_end_date'))->format('Y-m-d')
			: null;

		try {
			$letterOfCreditFacility->renew($effectiveDate, [
				'limit' => $request->get('limit'),
				'cd_or_td_lending_percentage' => $request->get('cd_or_td_lending_percentage'),
				'borrowing_rate' => $request->get('borrowing_rate'),
				'bank_margin_rate' => $request->get('bank_margin_rate'),
				'min_interest_rate' => $request->get('min_interest_rate'),
				'highest_debt_balance_rate' => $request->get('highest_debt_balance_rate'),
				'admin_fees_rate' => $request->get('admin_fees_rate'),
				'contract_end_date' => $contractEndDate,
				'notes' => $request->get('notes'),
			], $request->get('termAndConditions', []), auth()->user()->id);
		} catch (\InvalidArgumentException $e) {
			return redirect()->back()->withErrors(['effective_date' => $e->getMessage()]);
		}

		return redirect()
			->route('view.letter.of.credit.facility', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id])
			->with('success', __('Facility Renewed Successfully'));
	}

	/**
	 * Deletes the facility's most recent renewal only — see
	 * LetterOfCreditFacility::deleteLatestRenewal() for the full
	 * rules.
	 */
	public function deleteRenewal(Company $company, FinancialInstitution $financialInstitution, LetterOfCreditFacility $letterOfCreditFacility)
	{
		try {
			$letterOfCreditFacility->deleteLatestRenewal();
		} catch (\InvalidArgumentException $e) {
			return redirect()->back()->withErrors(['renewal' => $e->getMessage()]);
		}

		return redirect()->back()->with('success', __('Renewal Deleted — Facility Reverted To Previous Terms'));
	}

	/**
	 * Pure AJAX data endpoint consumed by the LC Issuance form —
	 * real-time limit/outstanding/commission-rate lookups. UNCHANGED,
	 * deliberately. Stays a JSON response — nothing calls this via
	 * Inertia navigation.
	 */
	public function updateOutstandingBalanceAndLimits(Request $request , Company $company  ){
		$lcIssuanceId =  $request->get('lcIssuanceId');
		$letterOfCreditIssuance = LetterOfCreditIssuance::find($lcIssuanceId);
		$cdOrTdAccountId = $request->get('cdOrTdAccountId');
		$selectedLcType = $request->get('lcType');
		// ⚠️ Confirmed bug fix: Laravel's ConvertEmptyStringsToNull
		// middleware turns an empty 'lcType' into null before this
		// method sees it (happens on page load, before the user has
		// picked an LC Type — the original Blade form triggers this
		// same lookup at that point too). termAndConditionForLcType()
		// has a non-nullable `string` type-hint, so calling it with
		// null threw an uncaught TypeError (HTTP 500). Guarded with
		// `$selectedLcType &&` below — no calculation logic changed.
		$currentSource = $request->get('source');
		$isLCFacilitySource = $currentSource == LetterOfCreditIssuance::LC_FACILITY;
		$isHundredPercentageSource = $currentSource == LetterOfCreditIssuance::HUNDRED_PERCENTAGE_CASH_COVER;
		$isCdSource = $currentSource == LetterOfCreditIssuance::AGAINST_CD;
		$isTdSource = $currentSource ==  LetterOfCreditIssuance::AGAINST_TD;
		
		$letterOfCreditFacility = $request->has('letterOfCreditFacilityId') ? LetterOfCreditFacility::find($request->get('letterOfCreditFacilityId')) : null;
		$letterOfCreditFacilityId = $letterOfCreditFacility ? $letterOfCreditFacility->id : 0 ;
		$financialInstitutionId = $request->get('financialInstitutionId') ;
		if(!$financialInstitutionId){
			return ;
		}
		$totalCashCoverStatementDebit = 0 ;
	
		$currencyName = null ;
		$accountTypeId = $request->get('accountTypeId');
		$isCdOrTdSource = $currentSource == LetterOfCreditIssuance::AGAINST_CD||$currentSource == LetterOfCreditIssuance::AGAINST_TD;
		$currentLcOutstanding = 0 ;
		$financialInstitution = FinancialInstitution::find($financialInstitutionId);
		$letterOfCreditFacility = $request->has('letterOfCreditFacilityId') ? LetterOfCreditFacility::find($request->get('letterOfCreditFacilityId')) : null;
        $minLcCommissionRateForCurrentLcType  = $letterOfCreditFacility && $selectedLcType && $letterOfCreditFacility->termAndConditionForLcType($selectedLcType)  ? $letterOfCreditFacility->termAndConditionForLcType($selectedLcType)->min_commission_fees : 0;
        $lcCommissionRate  = $letterOfCreditFacility && $selectedLcType && $letterOfCreditFacility->termAndConditionForLcType($selectedLcType) ? $letterOfCreditFacility->termAndConditionForLcType($selectedLcType)->commission_rate : 0;
        $minLcCashCoverRateForCurrentLcType  = $letterOfCreditFacility && $selectedLcType && $letterOfCreditFacility->termAndConditionForLcType($selectedLcType)  ? $letterOfCreditFacility->termAndConditionForLcType($selectedLcType)->cash_cover_rate : 0;
        $minLcIssuanceFeesForCurrentLcType  = $letterOfCreditFacility && $selectedLcType && $letterOfCreditFacility->termAndConditionForLcType($selectedLcType) ? $letterOfCreditFacility->termAndConditionForLcType($selectedLcType)->issuance_fees : 0;
		$lcAmountInMainCurrency = 0;
		if($isLCFacilitySource && $letterOfCreditFacility){
			/**
			 * $currencyName is kept here only for the informational
			 * 'currency_name' field in the response below — it is no
			 * longer used to filter the letter_of_credit_statements query
			 * for LC_FACILITY source (see the query further down for why:
			 * two earlier attempts both tried to guess a single currency
			 * to filter by and both failed, because Cash Cover Currency is
			 * a genuinely free, per-issuance choice that varies across
			 * issuances against the very same facility).
			 */
			$currencyName = $request->get('lcCashCoverCurrency') ?: $company->getMainFunctionalCurrency();
		}
		if( $isCdSource && $cdOrTdAccountId){
			$certificateOfDeposit = CertificatesOfDeposit::find($cdOrTdAccountId);
			$currencyName = $certificateOfDeposit->getCurrency();
		}
		if( $isTdSource && $cdOrTdAccountId){
			$timeOfDeposit = TimeOfDeposit::find($cdOrTdAccountId);
			$currencyName = $timeOfDeposit->getCurrency();
		}
		if($isHundredPercentageSource){
			$currencyName = $request->get('lcCurrency');
		}
		if($letterOfCreditIssuance){
			$minLcCashCoverRateForCurrentLcType = $letterOfCreditIssuance->getCashCoverRate();
			$lcCommissionRate = $letterOfCreditIssuance->getLcCommissionRate();
			$minLcIssuanceFeesForCurrentLcType = $letterOfCreditIssuance->getIssuanceFees();
			$lcAmountInMainCurrency = $letterOfCreditIssuance->getLcAmountInMainCurrency();
		}
		if($isCdOrTdSource){
			$totalCashCoverStatementDebit = DB::table('letter_of_credit_issuances')
			->where('letter_of_credit_issuances.cash_cover_deducted_from_account_id',$cdOrTdAccountId)
			->where('cash_cover_deducted_from_account_type',$accountTypeId)
			->where('letter_of_credit_cash_cover_statements.company_id',$company->id)
			->where('letter_of_credit_issuances.status',LetterOfCreditIssuance::RUNNING)
			->where('letter_of_credit_cash_cover_statements.source',LetterOfCreditIssuance::LC_FACILITY)
			->where('letter_of_credit_cash_cover_statements.currency',$currencyName)
			->where('letter_of_credit_cash_cover_statements.financial_institution_id',$financialInstitutionId)
			->join('letter_of_credit_cash_cover_statements','letter_of_credit_issuances.id','=','letter_of_credit_cash_cover_statements.letter_of_credit_issuance_id')
			->orderByRaw('date desc , letter_of_credit_cash_cover_statements.id desc')
			->sum('letter_of_credit_cash_cover_statements.debit');
		}
		
		$totalLastOutstandingBalanceOfFourTypes = 0 ;
		foreach(LcTypes::getAll() as $lcTypeId => $lcTypeNameFormatted){
			$accountTypeId = $request->get('accountTypeId');
			$letterOfCreditStatement = DB::table('letter_of_credit_statements')
			->where('company_id',$company->id)
			/**
			 * ⚠️ REAL BUG FIXED HERE (client-flagged, 2026-08-18), THIRD AND
			 * FINAL ATTEMPT: two earlier attempts both tried to GUESS which
			 * single currency value to filter by (the facility's own
			 * currency, then the company's main currency, then the actual
			 * submitted Cash Cover Currency) — all three failed, because
			 * Cash Cover Currency is a genuinely free, PER-ISSUANCE choice.
			 * Two different LCs against the very same facility can each
			 * pick a different cash cover currency, so no single currency
			 * value can ever correctly capture "everything outstanding
			 * against this facility." The client's own real ledger (LG &
			 * LC Statement) proves this: it runs ONE continuous balance
			 * across issuances "AAA", "DDDDDDDD", "QQQQQQQ" together,
			 * regardless of each one's own cash cover currency — so this
			 * lookup should match that and stop filtering by currency
			 * entirely for LC_FACILITY source. This is safe: every row's
			 * numeric credit/debit is ALREADY always stored in the
			 * company's main currency regardless of what the `currency`
			 * column happens to be tagged with (see storeWithinTransaction()'s
			 * $lcAmountInMainCurrency — deliberately untouched, correct,
			 * real money-tracking) — so nothing here mixes incompatible
			 * units by dropping this filter. lc_facility_id + lc_type +
			 * source already fully scope this to exactly the right rows.
			 */
			->when($currentSource != LetterOfCreditIssuance::LC_FACILITY, function($query) use ($currencyName){
				$query->where('currency',$currencyName);
			})
			->where('financial_institution_id',$financialInstitutionId)
			->when($currentSource == LetterOfCreditIssuance::LC_FACILITY , function( $query) use ($letterOfCreditFacilityId){
				$query->where('lc_facility_id',$letterOfCreditFacilityId);
			})
			->when($isCdOrTdSource,function($query) use ($cdOrTdAccountId){
				$query->where('cd_or_td_id',$cdOrTdAccountId);
			})
			->where('lc_type',$lcTypeId)
			->where('source',$currentSource)
			->orderByRaw('date desc , letter_of_credit_statements.id desc')
			->first();
			$letterOfCreditStatementEndBalance = $letterOfCreditStatement ? $letterOfCreditStatement->end_balance : 0 ;
			if($lcTypeId == $selectedLcType ){
				$currentLcOutstanding = $letterOfCreditStatementEndBalance;
			}
			$totalLastOutstandingBalanceOfFourTypes += $letterOfCreditStatementEndBalance;
		}
		$limit = $letterOfCreditFacility ? $letterOfCreditFacility->getLimit() : 0;
		/**
		 * ⚠️ REAL BUG FIXED HERE (client-flagged, 2026-08-18): abs() was
		 * only ever applied to the raw four-types sum BEFORE subtracting
		 * this issuance's own amount (the edit-mode "exclude myself" step)
		 * — so the FINAL figure could still land negative whenever this
		 * issuance's own amount was larger than everything else combined,
		 * which is exactly what made Total/Type Outstanding read as a
		 * negative number in edit mode, and made Room read wrong as a
		 * direct result. Outstanding is a magnitude — it should never be
		 * signed — so abs() now wraps the FINAL figure, after the
		 * subtraction, not before it.
		 */
		/**
		 * ⚠️ REAL BUG FIXED HERE (client-flagged, 2026-08-18, second
		 * round): the outer abs() turned an OVER-subtraction into a
		 * bogus positive figure.
		 *
		 * In edit mode this issuance's own amount is subtracted so the
		 * form shows what is outstanding EXCLUDING the record being
		 * edited. But the two numbers are measured differently — the
		 * statement end_balance is a running balance in cash-cover
		 * currency, while $lcAmountInMainCurrency is this issuance's
		 * amount converted to the main currency — so the subtraction can
		 * legitimately go below zero. abs() then flipped that negative
		 * into a positive number equal to the gap, which is exactly the
		 * reported symptom: opening an LC for editing showed an
		 * Outstanding Balance that tracked the LC's own value instead of
		 * the facility's real usage (observed: 450,000 in create vs
		 * 50,000 in edit for a 500,000 issuance — 50,000 being nothing
		 * but the difference between the two measures).
		 *
		 * Outstanding is a magnitude that cannot be negative, and
		 * "everything else is already covered by this issuance" means
		 * zero, not the leftover gap. Clamped instead of mirrored.
		 */
		$totalLastOutstandingBalanceOfFourTypes = max(0, abs($totalLastOutstandingBalanceOfFourTypes) - $lcAmountInMainCurrency);
		$currentLcOutstanding = max(0, abs($currentLcOutstanding) - $lcAmountInMainCurrency);
		 
		
		return response()->json([
			'limit'=>number_format($limit) ,
			'total_lc_outstanding_balance'=>number_format($totalLastOutstandingBalanceOfFourTypes),
			'total_room'=>number_format($limit - $totalLastOutstandingBalanceOfFourTypes),
			'current_lc_type_outstanding_balance'=>number_format($currentLcOutstanding),
            'min_lc_commission_rate'=>$minLcCommissionRateForCurrentLcType,
			'lc_commission_rate'=>$lcCommissionRate , 
			'currency_name'=>$currencyName,
            'min_lc_cash_cover_rate_for_current_lc_type'=>$minLcCashCoverRateForCurrentLcType ,
            'min_lc_issuance_fees_for_current_lc_type'=>$minLcIssuanceFeesForCurrentLcType,
			// 'customers'=>$customerOrOtherPartnersArr,
			'total_cash_cover_statement_debit'=>$totalCashCoverStatementDebit	
		]);
	}

	/**
	 * ⚠️ REAL BUG FIXED HERE (client-flagged, 2026-08-11): this filtered
	 * out any facility whose contract had already expired — correct for
	 * picking a facility to issue a NEW LC against (a genuinely separate
	 * code path in LetterOfCreditIssuanceController that doesn't use
	 * this endpoint at all), but wrong here: this endpoint is used ONLY
	 * by the LG & LC Statement report, where an expired facility's past
	 * activity is still legitimate company history the user has every
	 * right to look up. Confirmed via search that nothing else in the
	 * app relies on this endpoint filtering by expiry, so removing it
	 * here is safe.
	 */
	public function getLcFacilityBasedOnFinancialInstitution(Request $request){
		$financialInstitutionId = $request->get('financialInstitutionId');
		$financialInstitution = FinancialInstitution::find($financialInstitutionId);
		$letterOfCreditFacilities = $financialInstitution ? $financialInstitution->LetterOfCreditFacilities
		->pluck('name','id')->toArray() : [];
		return response()->json([
			'letterOfCreditFacilities'=>$letterOfCreditFacilities
		]);
		
	}
	

}