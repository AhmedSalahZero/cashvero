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
	public function index(Company $company,Request $request,FinancialInstitution $financialInstitution)
	{


		$letterOfCreditFacilities = $financialInstitution->letterOfCreditFacilities ;

		$letterOfCreditFacilities =   $this->applyFilter($request,$letterOfCreditFacilities) ;

		$searchFields = [
			'contract_start_date'=>__('Contract Start Date'),
			'contract_end_date'=>__('Contract End Date'),
			'currency'=>__('Currency'),
			'limit'=>__('Limit'),

		];
        return view('reports.LetterOfCreditFacility.index', [
			'company'=>$company,
			'searchFields'=>$searchFields,
			'financialInstitution'=>$financialInstitution,
			'letterOfCreditFacilities'=>$letterOfCreditFacilities
		]);
    }

	public function create(Company $company,FinancialInstitution $financialInstitution)
	{
        return view('reports.LetterOfCreditFacility.form',[
			'financialInstitution'=>$financialInstitution,
			'letterOfCreditFacilitiesTypes'=>LetterOfCreditFacility::getTypes(),
			'cdOrTdAccountTypes' =>AccountType::onlyCdOrTdAccounts()->get()
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

	public function edit(Company $company , Request $request , FinancialInstitution $financialInstitution , LetterOfCreditFacility $letterOfCreditFacility){

        return view('reports.LetterOfCreditFacility.form',[
			'financialInstitution'=>$financialInstitution,
			'model'=>$letterOfCreditFacility,
			'letterOfCreditFacilitiesTypes'=>LetterOfCreditFacility::getTypes(),
			'cdOrTdAccountTypes' =>AccountType::onlyCdOrTdAccounts()->get()
		]);

	}

	public function update(Company $company , StoreLetterOfCreditFacilityRequest $request , FinancialInstitution $financialInstitution,LetterOfCreditFacility $letterOfCreditFacility){
		$this->mergeConditionalValuesToRequest($request);
		$termAndConditions =  $request->get('termAndConditions',[]) ;
        $source = LetterOfCreditIssuance::LC_FACILITY;
		$data['updated_by'] = auth()->user()->id ;
		$data = $request->only($this->getCommonDataArr());
		foreach(['contract_start_date','contract_end_date'] as $dateField){
			$data[$dateField] = $request->get($dateField) ? Carbon::make($request->get($dateField))->format('Y-m-d'):null;
		}

     $letterOfCreditFacility->update($data);
     $currencyName = $letterOfCreditFacility->getCurrency();
     LetterOfCreditStatement::deleteButTriggerChangeOnLastElement($letterOfCreditFacility->letterOfCreditStatements->where('type',LetterOfCreditIssuance::LC_FACILITY_BEGINNING_BALANCE));
     LetterOfCreditCashCoverStatement::deleteButTriggerChangeOnLastElement($letterOfCreditFacility->letterOfCreditCashCoverStatements->where('type',LetterOfCreditIssuance::LC_FACILITY_BEGINNING_BALANCE));
		$letterOfCreditFacility->termAndConditions->each(function($termAndCondition){
			$termAndCondition->delete();
		});

		foreach($termAndConditions as $termAndConditionArr){
			$letterOfCreditFacility->termAndConditions()->create(array_merge($termAndConditionArr , [
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
		// $type = $request->get('type','letter-of-credit-facilities');
		
		// $activeTab = $type ;
		$activeTab = 'letter-of-credit-facilities' ;
		return redirect()->route('view.letter.of.credit.facility',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'active'=>$activeTab])->with('success',__('Item Has Been Updated Successfully'));


	}

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
	public function updateOutstandingBalanceAndLimits(Request $request , Company $company  ){
		$lcIssuanceId =  $request->get('lcIssuanceId');
		$letterOfCreditIssuance = LetterOfCreditIssuance::find($lcIssuanceId);
		$cdOrTdAccountId = $request->get('cdOrTdAccountId');
		$selectedLcType = $request->get('lcType');
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
        $minLcCommissionRateForCurrentLcType  = $letterOfCreditFacility  && $letterOfCreditFacility->termAndConditionForLcType($selectedLcType)  ? $letterOfCreditFacility->termAndConditionForLcType($selectedLcType)->min_commission_fees : 0;
        $lcCommissionRate  = $letterOfCreditFacility  && $letterOfCreditFacility->termAndConditionForLcType($selectedLcType) ? $letterOfCreditFacility->termAndConditionForLcType($selectedLcType)->commission_rate : 0;
        $minLcCashCoverRateForCurrentLcType  = $letterOfCreditFacility && $letterOfCreditFacility->termAndConditionForLcType($selectedLcType)  ? $letterOfCreditFacility->termAndConditionForLcType($selectedLcType)->cash_cover_rate : 0;
        $minLcIssuanceFeesForCurrentLcType  = $letterOfCreditFacility  && $letterOfCreditFacility->termAndConditionForLcType($selectedLcType) ? $letterOfCreditFacility->termAndConditionForLcType($selectedLcType)->issuance_fees : 0;
		$lcAmountInMainCurrency = 0;
		if($isLCFacilitySource && $letterOfCreditFacility){
			$currencyName = $letterOfCreditFacility->getCurrency();
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
			->where('currency',$currencyName)
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
		$totalLastOutstandingBalanceOfFourTypes =abs($totalLastOutstandingBalanceOfFourTypes) - $lcAmountInMainCurrency;
		$currentLcOutstanding = abs($currentLcOutstanding)  - $lcAmountInMainCurrency ;
		 
		
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
	public function getLcFacilityBasedOnFinancialInstitution(Request $request){
		$financialInstitutionId = $request->get('financialInstitutionId');
		$financialInstitution = FinancialInstitution::find($financialInstitutionId);
		$letterOfCreditFacilities = $financialInstitution ? $financialInstitution->LetterOfCreditFacilities
		->where('contract_end_date', '>=', now())
		->pluck('name','id')->toArray() : [];
		return response()->json([
			'letterOfCreditFacilities'=>$letterOfCreditFacilities
		]);
		
	}
	

}
