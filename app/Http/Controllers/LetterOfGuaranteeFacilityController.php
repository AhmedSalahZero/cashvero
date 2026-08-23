<?php
namespace App\Http\Controllers;
use App\Enums\LgTypes;
use App\Models\AccountType;
use App\Models\CertificatesOfDeposit;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\LetterOfGuaranteeCashCoverStatement;
use App\Models\LetterOfGuaranteeFacility;
use App\Models\LetterOfGuaranteeIssuance;
use App\Models\LetterOfGuaranteeStatement;
use App\Models\Partner;
use App\Models\TimeOfDeposit;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * LetterOfGuaranteeFacilityController
 * ------------------------------------------------------------------
 * Manages LG Facility — the credit line a bank grants for issuing
 * Letters Of Guarantee against, similar in concept to an overdraft
 * facility. Its "Term & Conditions" are a fixed 4-row matrix, one row
 * per LG type (Bid Bond, Final LG, Advanced Payment LG, Performance
 * LG — see App\Enums\LgTypes), each with its own commission
 * rate/interval, cash cover rate, min commission fees, and issuance
 * fees.
 *
 * `updateOutstandingBalanceAndLimits()` and
 * `getLgFacilityBasedOnFinancialInstitution()` are pure AJAX-style
 * data endpoints consumed by the LG Issuance form (real-time limit/
 * commission-rate lookups as the user builds an issuance) — they stay
 * exactly as they are; JSON responses are correct here since nothing
 * calls them via Inertia navigation.
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   ✅ index() / create() / edit() → MIGRATED to Vue + Inertia.
 *      create()/edit() render the same shared page,
 *      resources/js/Pages/LetterOfGuaranteeFacility/Form.vue,
 *      distinguished by a `mode: 'create' | 'edit'` prop.
 *   ⚠️ update() → 'updated_by' was being set then immediately wiped
 *      out by the next line overwriting the whole $data array — same
 *      bug already found and fixed on every deposit/overdraft
 *      controller so far. Fixed here too.
 *   ✅ getCommonDataArr() → checked against the actual database
 *      schema before building (per the project's "check every
 *      sibling" rule) — every field here DOES correspond to a real
 *      column. No equivalent bug found here.
 *   ✅ store() / destroy() → presentation-only change (response type
 *      only, for update()). Financial logic — the term & conditions
 *      matrix, the LG/cash-cover statement cleanup — UNCHANGED,
 *      deliberately.
 */
class LetterOfGuaranteeFacilityController
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
	 * The main "LG Facility" list — one flat list per financial
	 * institution.
	 *
	 * ✅ MIGRATED to Vue + Inertia. Renders
	 * resources/js/Pages/LetterOfGuaranteeFacility/Index.vue.
	 */
	public function index(Company $company,Request $request,FinancialInstitution $financialInstitution)
	{
		$letterOfGuaranteeFacilities = $financialInstitution->letterOfGuaranteeFacilities ;
		$letterOfGuaranteeFacilities =   $this->applyFilter($request,$letterOfGuaranteeFacilities) ;

		return \Inertia\Inertia::render('LetterOfGuaranteeFacility/Index', [
			'company' => ['id' => $company->id],
			'financialInstitution' => ['id' => $financialInstitution->id, 'name' => $financialInstitution->getName()],
			'canCreate' => hasAuthFor('lg_facility.create'),
			'createUrl' => route('create.letter.of.guarantee.facility', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
			'lgTypes' => \App\Enums\LgTypes::getAll(),
			'commissionIntervals' => getCommissionInterval(),
			'rows' => $letterOfGuaranteeFacilities->map(function (LetterOfGuaranteeFacility $lgf) use ($company, $financialInstitution) {
				$latestChapter = $lgf->getLatestTerms();
				return [
					'id' => $lgf->id,
					'name' => $lgf->getName(),
					'contract_start_date_formatted' => $lgf->getCurrentChapterStartDateFormatted(),
					'contract_end_date_formatted' => $lgf->getContractEndDateFormatted(),
					'currency' => $lgf->getCurrency(),
					'limit_formatted' => $lgf->getLimitFormatted(),
					'term_and_conditions' => ($latestChapter?->termAndConditions ?? $lgf->termAndConditions)->map(fn ($tc) => [
						'lg_type_formatted' => $tc->getLgTypeFormatted(),
						'cash_cover_rate_formatted' => $tc->getCashCoverRate() . ' %',
						'commission_rate_formatted' => $tc->getCommissionRate() . ' %',
						'commission_interval' => $tc->getCommissionInterval(),
						'min_commission_fees_formatted' => number_format($tc->getMinCommissionFees()),
						'issuance_fees_formatted' => number_format($tc->getIssuanceFees()),
					])->values(),
					'edit_url' => route('edit.letter.of.guarantee.facility', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'letterOfGuaranteeFacility' => $lgf->id]),
					'delete_url' => route('delete.letter.of.guarantee.facility', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'letterOfGuaranteeFacility' => $lgf->id]),
					'renew_url' => route('letter-of-guarantee-facility.renew', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'letterOfGuaranteeFacility' => $lgf->id]),
					'delete_renewal_url' => route('letter-of-guarantee-facility.delete-renewal', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'letterOfGuaranteeFacility' => $lgf->id]),
					'has_renewals' => $lgf->hasRenewals(),
					'terms_history' => $lgf->termsHistories->map(fn ($t) => [
						'id' => $t->id,
						'effective_date_formatted' => $t->getEffectiveDateFormatted(),
						'contract_end_date_formatted' => $t->getContractEndDateFormatted(),
						'limit_formatted' => $t->getLimitFormatted(),
						'is_original' => (bool) $t->is_original,
						'term_and_conditions' => $t->termAndConditions->map(fn ($tc) => [
							'lg_type_formatted' => $tc->getLgTypeFormatted(),
							'cash_cover_rate_formatted' => $tc->getCashCoverRate() . ' %',
							'commission_rate_formatted' => $tc->getCommissionRate() . ' %',
							'commission_interval' => $tc->getCommissionInterval(),
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
	 * Shows the "Add LG Facility" form.
	 *
	 * ✅ MIGRATED to Vue + Inertia — shares the same page component as
	 * edit() (resources/js/Pages/LetterOfGuaranteeFacility/Form.vue),
	 * distinguished by the `mode: 'create'` prop.
	 */
	public function create(Company $company,FinancialInstitution $financialInstitution)
	{
        return \Inertia\Inertia::render('LetterOfGuaranteeFacility/Form', [
			'mode' => 'create',
			'company' => ['id' => $company->id],
			'financialInstitution' => ['id' => $financialInstitution->id, 'name' => $financialInstitution->getName()],
			'currencies' => getCurrencies(),
			'commissionIntervals' => getCommissionInterval(),
			'lgTypes' => \App\Enums\LgTypes::getAll(),
			'model' => null,
			'submitUrl' => route('store.letter.of.guarantee.facility', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
			'backUrl' => route('view.letter.of.guarantee.facility', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
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
		return ['name','contract_start_date','contract_end_date','outstanding_date','currency','limit','outstanding_amount'];
	}
	/**
	 * Stores a new LG Facility, including its 4-row term & conditions
	 * matrix. Now validated by a real request class (see
	 * StoreLetterOfGuaranteeFacilityRequest) — previously had none at
	 * all. Original chapter created immediately, per Facility Renewal.
	 */
	public function store(Company $company  ,FinancialInstitution $financialInstitution, \App\Http\Requests\StoreLetterOfGuaranteeFacilityRequest $request){
		$data = $request->only( $this->getCommonDataArr());
		foreach(['contract_start_date','contract_end_date','outstanding_date'] as $dateField){
			$data[$dateField] = $request->get($dateField) ? Carbon::make($request->get($dateField))->format('Y-m-d'):null;
		}
		$termAndConditions = $request->get('termAndConditions',[]) ;
		$data['created_by'] = auth()->user()->id ;
		$data['company_id'] = $company->id ;
		$data['outstanding_amount'] = $data['outstanding_amount'] ? $data['outstanding_amount']: 0; 
		/**
		 * @var LetterOfGuaranteeFacility $letterOfGuaranteeFacility
		 */

		$letterOfGuaranteeFacility = $financialInstitution->LetterOfGuaranteeFacilities()->create($data);
		$originalChapter = $letterOfGuaranteeFacility->createOriginalTermsHistory();
		foreach($termAndConditions as $termAndConditionArr){
			$termAndConditionArr['company_id'] = $company->id ;
			$termAndConditionArr['outstanding_date'] = $request->get('outstanding_date');
			$letterOfGuaranteeFacility->termAndConditions()->create(array_merge($termAndConditionArr , [
				'terms_history_id' => $originalChapter->id,
			]));
		}
		$type = $request->get('type','letter-of-guarantee-facilities');
		$activeTab = $type ;

		return redirect()->route('view.letter.of.guarantee.facility',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'active'=>$activeTab])->with('success',__('Data Store Successfully'));

	}

	/**
	 * Shows the "Edit LG Facility" form.
	 *
	 * ✅ MIGRATED to Vue + Inertia — shares the same page component as
	 * create() (resources/js/Pages/LetterOfGuaranteeFacility/Form.vue),
	 * distinguished by the `mode: 'edit'` prop.
	 */
	public function edit(Company $company , Request $request , FinancialInstitution $financialInstitution , LetterOfGuaranteeFacility $letterOfGuaranteeFacility){

		$hasRenewals = $letterOfGuaranteeFacility->hasRenewals();
		$latestChapter = $letterOfGuaranteeFacility->getLatestTerms();
        return \Inertia\Inertia::render('LetterOfGuaranteeFacility/Form', [
			'mode' => 'edit',
			'hasRenewals' => $hasRenewals,
			'company' => ['id' => $company->id],
			'financialInstitution' => ['id' => $financialInstitution->id, 'name' => $financialInstitution->getName()],
			'currencies' => getCurrencies(),
			'commissionIntervals' => getCommissionInterval(),
			'lgTypes' => \App\Enums\LgTypes::getAll(),
			'model' => [
				'id' => $letterOfGuaranteeFacility->id,
				'name' => $letterOfGuaranteeFacility->getName(),
				'contract_start_date' => $hasRenewals ? $latestChapter->effective_date : $letterOfGuaranteeFacility->getContractStartDate(),
				'contract_end_date' => $letterOfGuaranteeFacility->getContractEndDate(),
				'currency' => $letterOfGuaranteeFacility->getCurrency(),
				'limit' => $letterOfGuaranteeFacility->getLimit(),
				'outstanding_date' => $letterOfGuaranteeFacility->getOutstandingDate(),
				'outstanding_amount' => $letterOfGuaranteeFacility->getOutstandingAmount(),
				/**
				 * Facility Renewal: scoped to the CURRENT (latest)
				 * chapter's own rows only — never the raw, unscoped
				 * relation, which would mix every past chapter's rates.
				 */
				'term_and_conditions' => collect(\App\Enums\LgTypes::getAll())->map(function ($label, $lgType) use ($latestChapter, $letterOfGuaranteeFacility) {
					$tc = $latestChapter
						? $latestChapter->termAndConditions->firstWhere('lg_type', $lgType)
						: $letterOfGuaranteeFacility->termAndConditionForLgType($lgType);
					return [
						'lg_type' => $lgType,
						'outstanding_balance' => $tc ? $tc->getOutstandingBalance() : 0,
						'cash_cover_rate' => $tc ? $tc->getCashCoverRate() : 0,
						'commission_rate' => $tc ? $tc->getCommissionRate() : 0,
						'commission_interval' => $tc ? $tc->getCommissionInterval() : 'quarterly',
						'min_commission_fees' => $tc ? $tc->getMinCommissionFees() : 0,
						'issuance_fees' => $tc ? $tc->getIssuanceFees() : 0,
					];
				})->values(),
			],
			'submitUrl' => route('update.letter.of.guarantee.facility', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id, 'letterOfGuaranteeFacility' => $letterOfGuaranteeFacility->id]),
			'backUrl' => route('view.letter.of.guarantee.facility', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id]),
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
	 * Updates an existing LG Facility. Now validated by a real request
	 * class (see UpdateLetterOfGuaranteeFacilityRequest) — previously
	 * had none. Once a renewal exists, only limit/contract_end_date can
	 * change, and the Term & Conditions rebuild below is scoped to the
	 * CURRENT chapter's rows only — never touches older chapters'
	 * history (same real bug class already fixed on Commercial Paper's
	 * tiers: the old code wiped every row for the whole facility).
	 */
	public function update(Company $company , \App\Http\Requests\UpdateLetterOfGuaranteeFacilityRequest $request , FinancialInstitution $financialInstitution,LetterOfGuaranteeFacility $letterOfGuaranteeFacility){
		$hasRenewals = $letterOfGuaranteeFacility->hasRenewals();
		$termAndConditions =  $request->get('termAndConditions',[]) ;
		$fieldsToUpdate = $hasRenewals ? ['contract_end_date','limit'] : $this->getCommonDataArr();
		$data = $request->only($fieldsToUpdate);
		$data['updated_by'] = auth()->user()->id ;
		$dateFields = $hasRenewals ? ['contract_end_date'] : ['contract_start_date','contract_end_date','outstanding_date'];
		foreach($dateFields as $dateField){
			$data[$dateField] = $request->get($dateField) ? Carbon::make($request->get($dateField))->format('Y-m-d'):null;
		}

		$letterOfGuaranteeFacility->update($data);
		$latestChapter = $letterOfGuaranteeFacility->getLatestTerms();
		if ($latestChapter) {
			$latestChapter->update([
				'effective_date' => $latestChapter->is_original ? $letterOfGuaranteeFacility->contract_start_date : $latestChapter->effective_date,
				'limit' => $letterOfGuaranteeFacility->limit,
				'contract_end_date' => $letterOfGuaranteeFacility->contract_end_date,
			]);
		}

		if (! $hasRenewals) {
			$currencyName = $letterOfGuaranteeFacility->getCurrency();
			LetterOfGuaranteeStatement::deleteButTriggerChangeOnLastElement($letterOfGuaranteeFacility->letterOfGuaranteeStatements->where('type',LetterOfGuaranteeIssuance::LG_FACILITY_BEGINNING_BALANCE));
			LetterOfGuaranteeCashCoverStatement::deleteButTriggerChangeOnLastElement($letterOfGuaranteeFacility->letterOfGuaranteeCashCoverStatements->where('type',LetterOfGuaranteeIssuance::LG_FACILITY_BEGINNING_BALANCE));
			if ($latestChapter) {
				$latestChapter->termAndConditions()->delete();
				foreach($termAndConditions as $termAndConditionArr){
					$latestChapter->termAndConditions()->create(array_merge($termAndConditionArr, [
						'letter_of_guarantee_facility_id' => $letterOfGuaranteeFacility->id,
						'company_id' => $company->id,
					]));
				}
			}
		}
		$type = $request->get('type','letter-of-guarantee-facilities');
		$activeTab = $type ;
		return redirect()->route('view.letter.of.guarantee.facility',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'active'=>$activeTab])->with('success',__('Item Has Been Updated Successfully'));


	}

	/**
	 * Deletes an LG Facility and its term & conditions rows / opening
	 * statement entries. Client-confirmed rule (applied from the start,
	 * same as every other facility type): blocked while it still has
	 * LGs issued against it.
	 */
	public function destroy(Company $company , FinancialInstitution $financialInstitution , LetterOfGuaranteeFacility $letterOfGuaranteeFacility)
	{
		if ($letterOfGuaranteeFacility->hasAnyTransactions()) {
			return redirect()->back()->withErrors([
				'delete' => __('This facility cannot be deleted because it still has LGs issued against it. Please remove those first.'),
			]);
		}

         LetterOfGuaranteeStatement::deleteButTriggerChangeOnLastElement($letterOfGuaranteeFacility->letterOfGuaranteeStatements->where('type',LetterOfGuaranteeIssuance::LG_FACILITY_BEGINNING_BALANCE));
         LetterOfGuaranteeCashCoverStatement::deleteButTriggerChangeOnLastElement($letterOfGuaranteeFacility->letterOfGuaranteeCashCoverStatements->where('type',LetterOfGuaranteeIssuance::LG_FACILITY_BEGINNING_BALANCE));

		$letterOfGuaranteeFacility->termAndConditions->each(function($termAndCondition){
            $termAndCondition->delete();

		});
		$letterOfGuaranteeFacility->delete();
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}

	/**
	 * Facility Renewal — Phase 5 (final facility type). Requires the
	 * full 4-row Term & Conditions matrix — see
	 * LetterOfGuaranteeFacility::renew().
	 */
	public function renew(Company $company, \App\Http\Requests\RenewLetterOfGuaranteeFacilityRequest $request, FinancialInstitution $financialInstitution, LetterOfGuaranteeFacility $letterOfGuaranteeFacility)
	{
		$effectiveDate = Carbon::make($request->get('effective_date'))->format('Y-m-d');
		$contractEndDate = $request->get('contract_end_date')
			? Carbon::make($request->get('contract_end_date'))->format('Y-m-d')
			: null;

		try {
			$letterOfGuaranteeFacility->renew($effectiveDate, [
				'limit' => $request->get('limit'),
				'contract_end_date' => $contractEndDate,
				'notes' => $request->get('notes'),
			], $request->get('termAndConditions', []), auth()->user()->id);
		} catch (\InvalidArgumentException $e) {
			return redirect()->back()->withErrors(['effective_date' => $e->getMessage()]);
		}

		return redirect()
			->route('view.letter.of.guarantee.facility', ['company' => $company->id, 'financialInstitution' => $financialInstitution->id])
			->with('success', __('Facility Renewed Successfully'));
	}

	public function deleteRenewal(Company $company, FinancialInstitution $financialInstitution, LetterOfGuaranteeFacility $letterOfGuaranteeFacility)
	{
		try {
			$letterOfGuaranteeFacility->deleteLatestRenewal();
		} catch (\InvalidArgumentException $e) {
			return redirect()->back()->withErrors(['renewal' => $e->getMessage()]);
		}

		return redirect()->back()->with('success', __('Renewal Deleted — Facility Reverted To Previous Terms'));
	}
	/**
	 * Pure AJAX data endpoint consumed by the LG Issuance form —
	 * real-time limit/outstanding/commission-rate lookups as the user
	 * picks LG type, source, currency, etc. UNCHANGED, deliberately.
	 * Stays a JSON response — nothing calls this via Inertia
	 * navigation, it's a live-lookup endpoint the Issuance form will
	 * call directly.
	 */
	public function updateOutstandingBalanceAndLimits(Request $request , Company $company ){
		$lgIssuanceId =  $request->get('lgIssuanceId');
		$letterOfGuaranteeIssuance = LetterOfGuaranteeIssuance::find($lgIssuanceId);
		$financialInstitutionId = $request->get('financialInstitutionId') ;
		$selectedLgType = $request->get('lgType');
		$isBidBond = $selectedLgType == 'bid-bond'  ;
		$totalCashCoverStatementDebit = 0 ;
		$currencyName = null ;
		$customerOrOtherPartnersArr = Partner::onlyForCompany($company->id)
			->where(function (Builder $query) use ($isBidBond) {
				$query->where('is_other_partner', 1)
					->orWhere(function (Builder $customerQuery) use ($isBidBond) {
						$customerQuery->where('is_customer', 1);
						if (!$isBidBond) {
							$customerQuery->onlyThatHaveCustomerContracts();
						}
					});
			})
			->get(['id', 'name'])
			->sortBy(fn (Partner $partner) => mb_strtolower((string) $partner->name), SORT_NATURAL)
			->pluck('id', 'name')
			->all();
	
		$accountTypeId = $request->get('accountTypeId');
		$currentSource = $request->get('source');
		$cdOrTdAccountId = $request->get('cdOrTdAccountId');
		$isLGFacilitySource = $currentSource == LetterOfGuaranteeIssuance::LG_FACILITY;
		$isHundredPercentageSource = $currentSource == LetterOfGuaranteeIssuance::HUNDRED_PERCENTAGE_CASH_COVER;
		$isCdSource = $currentSource == LetterOfGuaranteeIssuance::AGAINST_CD;
		$isTdSource = $currentSource ==  LetterOfGuaranteeIssuance::AGAINST_TD;
		$isCdOrTdSource = $currentSource == LetterOfGuaranteeIssuance::AGAINST_CD||$currentSource == LetterOfGuaranteeIssuance::AGAINST_TD;
		$letterOfGuaranteeFacility = $request->has('letterOfGuaranteeFacilityId') ? LetterOfGuaranteeFacility::find($request->get('letterOfGuaranteeFacilityId')) : null;
		$letterOfGuaranteeFacilityId = $letterOfGuaranteeFacility ? $letterOfGuaranteeFacility->id : 0 ;
		
		
		if($isLGFacilitySource && $letterOfGuaranteeFacility){
			$currencyName = $letterOfGuaranteeFacility->getCurrency();
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
			$currencyName = $request->get('lgCurrency');
		}
		$currentLgTypeOutstanding = 0 ;
		$financialInstitution = FinancialInstitution::find($financialInstitutionId);
		if(!$financialInstitution){
			return ;
		}
        $minLgCommissionRateForCurrentLgType  = $letterOfGuaranteeFacility  && $selectedLgType && $letterOfGuaranteeFacility->termAndConditionForLgType($selectedLgType) ? $letterOfGuaranteeFacility->termAndConditionForLgType($selectedLgType)->min_commission_fees : 0;
		
        $lgCommissionRate  = $letterOfGuaranteeFacility && $selectedLgType  && $letterOfGuaranteeFacility->termAndConditionForLgType($selectedLgType) ? $letterOfGuaranteeFacility->termAndConditionForLgType($selectedLgType)->commission_rate : 0;
        $minLgCashCoverRateForCurrentLgType  = $letterOfGuaranteeFacility && $selectedLgType  && $letterOfGuaranteeFacility->termAndConditionForLgType($selectedLgType) ? $letterOfGuaranteeFacility->termAndConditionForLgType($selectedLgType)->cash_cover_rate : 0;
		$minLgIssuanceFeesForCurrentLgType  = $letterOfGuaranteeFacility && $selectedLgType  && $letterOfGuaranteeFacility->termAndConditionForLgType($selectedLgType) ? $letterOfGuaranteeFacility->termAndConditionForLgType($selectedLgType)->issuance_fees : 0;
		$lgAmount = 0 ;
		if($letterOfGuaranteeIssuance){
			$minLgCashCoverRateForCurrentLgType = $letterOfGuaranteeIssuance->getCashCoverRate();
			$lgCommissionRate = $letterOfGuaranteeIssuance->getLgCommissionRate();
			$minLgIssuanceFeesForCurrentLgType = $letterOfGuaranteeIssuance->getIssuanceFees();
			$lgAmount= $letterOfGuaranteeIssuance->getLgAmount();
		}
		
		
		if($isCdOrTdSource){

			$totalCashCoverStatementDebit = DB::table('letter_of_guarantee_issuances')
			->where('letter_of_guarantee_issuances.cash_cover_deducted_from_account_id',$cdOrTdAccountId)
			->where('cash_cover_deducted_from_account_type',$accountTypeId)
			->where('letter_of_guarantee_cash_cover_statements.company_id',$company->id)
			->where('letter_of_guarantee_issuances.status',LetterOfGuaranteeIssuance::RUNNING)
			->where('letter_of_guarantee_cash_cover_statements.source',LetterOfGuaranteeIssuance::LG_FACILITY)
			->where('letter_of_guarantee_cash_cover_statements.currency',$currencyName)
			// ->where('letter_of_guarantee_cash_cover_statements.lg_type',$lgTypeId)
			->where('letter_of_guarantee_cash_cover_statements.financial_institution_id',$financialInstitutionId)
			->join('letter_of_guarantee_cash_cover_statements','letter_of_guarantee_issuances.id','=','letter_of_guarantee_cash_cover_statements.letter_of_guarantee_issuance_id')
			->orderByRaw('date desc , letter_of_guarantee_cash_cover_statements.id desc')
			// ->select('letter_of_guarantee_cash_cover_statements.end_balance as cash_cover_statement_end_balance')
			->sum('letter_of_guarantee_cash_cover_statements.debit')
			;
	
		}
		

		$totalLastOutstandingBalanceOfFourTypes = 0 ;
		
		foreach(LgTypes::getAll() as $lgTypeId => $lgTypeNameFormatted){
		
		
			$letterOfGuaranteeStatement = DB::table('letter_of_guarantee_statements')
			->where('company_id',$company->id)
			->where('currency',$currencyName)
			->where('financial_institution_id',$financialInstitutionId)
			->when($currentSource == LetterOfGuaranteeIssuance::LG_FACILITY , function( $query) use ($letterOfGuaranteeFacilityId){
				$query->where('lg_facility_id',$letterOfGuaranteeFacilityId);
			})
			->when($isCdOrTdSource,function($query) use ($cdOrTdAccountId){
				$query->where('cd_or_td_id',$cdOrTdAccountId);
			})
			->where('lg_type',$lgTypeId)
			->where('source',$currentSource)
			->orderByRaw('date desc , letter_of_guarantee_statements.id desc')
			->first();
			
			

			$letterOfGuaranteeStatementEndBalance = $letterOfGuaranteeStatement ? $letterOfGuaranteeStatement->end_balance : 0 ;
			
			if($lgTypeId == $selectedLgType ){
				$currentLgTypeOutstanding = $letterOfGuaranteeStatementEndBalance;
			}
			$totalLastOutstandingBalanceOfFourTypes += $letterOfGuaranteeStatementEndBalance;
		}
		$totalLastOutstandingBalanceOfFourTypes = abs($totalLastOutstandingBalanceOfFourTypes) - $lgAmount;
		$limit = $letterOfGuaranteeFacility ? $letterOfGuaranteeFacility->getLimit() : 0;
		$currentLgTypeOutstanding = abs($currentLgTypeOutstanding) - $lgAmount ;
	
		return response()->json([
			'limit'=>number_format($limit) ,
			'total_lg_outstanding_balance'=>number_format($totalLastOutstandingBalanceOfFourTypes),
			'total_room'=>number_format($limit - $totalLastOutstandingBalanceOfFourTypes ),
			'currency_name'=>$currencyName,
			'current_lg_type_outstanding_balance'=>number_format($currentLgTypeOutstanding),
            'min_lg_commission_rate'=>$minLgCommissionRateForCurrentLgType,
			'lg_commission_rate'=>$lgCommissionRate , 
            'min_lg_cash_cover_rate_for_current_lg_type'=>$minLgCashCoverRateForCurrentLgType ,
            'min_lg_issuance_fees_for_current_lg_type'=>$minLgIssuanceFeesForCurrentLgType,
			'customers'=>$customerOrOtherPartnersArr,
			/**
			 * * المستفيدين اللي مش لازم يتربط بيهم عقد — جهة حكومية او
			 * * مالك عقار مثلا .. الفورم بيشيل النجمة عن حقل العقد اول ما
			 * * يتختار واحد منهم ، من غير نداء تاني للسيرفر
			 *
			 * * نفس القاعدة اللي التحقق بيطبقها ، متعرفة في مكان واحد
			 * @see \App\Support\LetterOfGuarantee\LgContractRequirement
			 */
			'customers_without_contract_requirement'=>\App\Support\LetterOfGuarantee\LgContractRequirement::partnerIdsWithoutContractRequirement(
				array_map('intval', array_values($customerOrOtherPartnersArr))
			),
			'total_cash_cover_statement_debit'=>$totalCashCoverStatementDebit
		]);
	}
	/**
	 * Pure AJAX data endpoint — active LG Facilities for a given
	 * financial institution, used to populate the LG Issuance form's
	 * facility dropdown. UNCHANGED, deliberately.
	 */
	public function getLgFacilityBasedOnFinancialInstitution(Request $request){
		$financialInstitutionId = $request->get('financialInstitutionId');
		$financialInstitution = FinancialInstitution::find($financialInstitutionId);
		$letterOfGuaranteeFacilities = $financialInstitution ? $financialInstitution->LetterOfGuaranteeFacilities
		->where('contract_end_date', '>=', now())
		->pluck('name','id')->toArray() : [];
		return response()->json([
			'letterOfGuaranteeFacilities'=>$letterOfGuaranteeFacilities
		]);
		
	}


}
