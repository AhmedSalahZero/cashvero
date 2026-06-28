<?php
namespace App\Http\Controllers;
use App\Enums\LgTypes;
use App\Helpers\HArr;
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
	public function index(Company $company,Request $request,FinancialInstitution $financialInstitution)
	{


		$letterOfGuaranteeFacilities = $financialInstitution->letterOfGuaranteeFacilities ;

		$letterOfGuaranteeFacilities =   $this->applyFilter($request,$letterOfGuaranteeFacilities) ;

		$searchFields = [
			'contract_start_date'=>__('Contract Start Date'),
			'contract_end_date'=>__('Contract End Date'),
			'currency'=>__('Currency'),
			'limit'=>__('Limit'),

		];
        return view('reports.LetterOfGuaranteeFacility.index', [
			'company'=>$company,
			'searchFields'=>$searchFields,
			'financialInstitution'=>$financialInstitution,
			'letterOfGuaranteeFacilities'=>$letterOfGuaranteeFacilities
		]);
    }

	public function create(Company $company,FinancialInstitution $financialInstitution)
	{
        return view('reports.LetterOfGuaranteeFacility.form',[
			'financialInstitution'=>$financialInstitution,
		]);
    }
	public function getCommonDataArr():array
	{
		return ['name','contract_start_date','contract_end_date','outstanding_date','currency','limit','outstanding_amount'];
	}
	public function store(Company $company  ,FinancialInstitution $financialInstitution, Request $request){
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
		// $currencyName = $letterOfGuaranteeFacility->getCurrency();
		// $source = LetterOfGuaranteeIssuance::LG_FACILITY;
		foreach($termAndConditions as $termAndConditionArr){
			$termAndConditionArr['company_id'] = $company->id ;
			$termAndConditionArr['outstanding_date'] = $request->get('outstanding_date');
			// $currentOutstandingBalance = $termAndConditionArr['outstanding_balance'] ;
			// $currentCashCover = $termAndConditionArr['cash_cover_rate'];
			
			// $currentLgType = $termAndConditionArr['lg_type'] ;
			// if($currentOutstandingBalance){
				$letterOfGuaranteeFacility->termAndConditions()->create(array_merge($termAndConditionArr , [
				]));
			// }
			// if($currentOutstandingBalance > 0){
			// 	$letterOfGuaranteeFacility->handleLetterOfGuaranteeStatement($financialInstitution->id,$source,$letterOfGuaranteeFacility->id,$currentLgType,$company->id,$termAndConditionArr['outstanding_date'],0,0,$currentOutstandingBalance,$currencyName,0,0,LetterOfGuaranteeIssuance::LG_FACILITY_BEGINNING_BALANCE);
			// }
			// $cashCoverOpeningBalance = $currentCashCover / 100 * $currentOutstandingBalance ;
			// if( $cashCoverOpeningBalance > 0 ){
			// 	$letterOfGuaranteeFacility->handleLetterOfGuaranteeCashCoverStatement($financialInstitution->id,$source,$letterOfGuaranteeFacility->id,$currentLgType,$company->id,$termAndConditionArr['outstanding_date'],0,$cashCoverOpeningBalance,0,$currencyName,0,LetterOfGuaranteeIssuance::LG_FACILITY_BEGINNING_BALANCE);
			// }

		}
		$type = $request->get('type','letter-of-guarantee-facilities');
		$activeTab = $type ;

		return redirect()->route('view.letter.of.guarantee.facility',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'active'=>$activeTab])->with('success',__('Data Store Successfully'));

	}

	public function edit(Company $company , Request $request , FinancialInstitution $financialInstitution , LetterOfGuaranteeFacility $letterOfGuaranteeFacility){

        return view('reports.LetterOfGuaranteeFacility.form',[
			'financialInstitution'=>$financialInstitution,
			'model'=>$letterOfGuaranteeFacility
		]);

	}

	public function update(Company $company , Request $request , FinancialInstitution $financialInstitution,LetterOfGuaranteeFacility $letterOfGuaranteeFacility){
		$termAndConditions =  $request->get('termAndConditions',[]) ;
        $source = LetterOfGuaranteeIssuance::LG_FACILITY;
		$data['updated_by'] = auth()->user()->id ;
		$data = $request->only($this->getCommonDataArr());
		foreach(['contract_start_date','contract_end_date','outstanding_date'] as $dateField){
			$data[$dateField] = $request->get($dateField) ? Carbon::make($request->get($dateField))->format('Y-m-d'):null;
		}

     $letterOfGuaranteeFacility->update($data);
     $currencyName = $letterOfGuaranteeFacility->getCurrency();
     LetterOfGuaranteeStatement::deleteButTriggerChangeOnLastElement($letterOfGuaranteeFacility->letterOfGuaranteeStatements->where('type',LetterOfGuaranteeIssuance::LG_FACILITY_BEGINNING_BALANCE));
     LetterOfGuaranteeCashCoverStatement::deleteButTriggerChangeOnLastElement($letterOfGuaranteeFacility->letterOfGuaranteeCashCoverStatements->where('type',LetterOfGuaranteeIssuance::LG_FACILITY_BEGINNING_BALANCE));
		$letterOfGuaranteeFacility->termAndConditions->each(function($termAndCondition){
			$termAndCondition->delete();
		});

		foreach($termAndConditions as $termAndConditionArr){
			$letterOfGuaranteeFacility->termAndConditions()->create(array_merge($termAndConditionArr , [
			]));
            $termAndConditionArr['outstanding_date'] = $request->get('outstanding_date');
			$currentOutstandingBalance = $termAndConditionArr['outstanding_balance'] ;
			$currentCashCoverRate = $termAndConditionArr['cash_cover_rate'] / 100  ;
			$currentCashCoverBeginningBalance  = $currentOutstandingBalance * $currentCashCoverRate ; 
			$currentLgType = $termAndConditionArr['lg_type'] ;
			// if($currentOutstandingBalance > 0 ){
			// 	$letterOfGuaranteeFacility->handleLetterOfGuaranteeStatement($financialInstitution->id,$source,$letterOfGuaranteeFacility->id,$currentLgType,$company->id,$termAndConditionArr['outstanding_date'],0,0,$currentOutstandingBalance,$currencyName,0,0,LetterOfGuaranteeIssuance::LG_FACILITY_BEGINNING_BALANCE);
			// }
			// if($currentCashCoverBeginningBalance > 0){
			// 	$letterOfGuaranteeFacility->handleLetterOfGuaranteeCashCoverStatement($financialInstitution->id,$source,$letterOfGuaranteeFacility->id,$currentLgType,$company->id,$termAndConditionArr['outstanding_date'],0,$currentCashCoverBeginningBalance,0,$currencyName,0,LetterOfGuaranteeIssuance::LG_FACILITY_BEGINNING_BALANCE);
			// }
			

		}
		$type = $request->get('type','letter-of-guarantee-facilities');
		$activeTab = $type ;
		return redirect()->route('view.letter.of.guarantee.facility',['company'=>$company->id,'financialInstitution'=>$financialInstitution->id,'active'=>$activeTab])->with('success',__('Item Has Been Updated Successfully'));


	}

	public function destroy(Company $company , FinancialInstitution $financialInstitution , LetterOfGuaranteeFacility $letterOfGuaranteeFacility)
	{

         LetterOfGuaranteeStatement::deleteButTriggerChangeOnLastElement($letterOfGuaranteeFacility->letterOfGuaranteeStatements->where('type',LetterOfGuaranteeIssuance::LG_FACILITY_BEGINNING_BALANCE));
         LetterOfGuaranteeCashCoverStatement::deleteButTriggerChangeOnLastElement($letterOfGuaranteeFacility->letterOfGuaranteeCashCoverStatements->where('type',LetterOfGuaranteeIssuance::LG_FACILITY_BEGINNING_BALANCE));

		$letterOfGuaranteeFacility->termAndConditions->each(function($termAndCondition){
            $termAndCondition->delete();

		});
		$letterOfGuaranteeFacility->delete();
		return redirect()->back()->with('success',__('Item Has Been Delete Successfully'));
	}
	public function updateOutstandingBalanceAndLimits(Request $request , Company $company ){
		$lgIssuanceId =  $request->get('lgIssuanceId');
		$letterOfGuaranteeIssuance = LetterOfGuaranteeIssuance::find($lgIssuanceId);
		$financialInstitutionId = $request->get('financialInstitutionId') ;
		$selectedLgType = $request->get('lgType');
		$isBidBond = $selectedLgType == 'bid-bond'  ;
		$totalCashCoverStatementDebit = 0 ;
		$currencyName = null ;
		$customersArr =   Partner::onlyCustomers()->onlyForCompany($company->id)
		->when(!$isBidBond,function(Builder $builder){
			$builder->onlyThatHaveContracts();
		})
		->orderBy('name','asc')
		->pluck('id','name')
		->toArray();

	
		$otherPartnerArr = Partner::onlyOtherPartners()->onlyForCompany($company->id)
		->orderBy('name','asc')
		->pluck('id','name')
		->toArray();
		$customerOrOtherPartnersArr = HArr::mergeTwoAssocArr($customersArr,$otherPartnerArr);
	
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
			'total_cash_cover_statement_debit'=>$totalCashCoverStatementDebit
		]);
	}
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
