<?php

namespace App\Http\Controllers;

use App\Enums\LcTypes;
use App\Enums\LgTypes;
use App\Models\AccountType;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\LcOverdraftBankStatement;
use App\Models\LetterOfCreditFacility;
use App\Models\LetterOfCreditIssuance;
use App\Models\LetterOfGuaranteeIssuance;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LGLCSBanktatementController
{
    use GeneralFunctions;

    public function index(Company $company,Request $request)
    {
		$lcSources = LetterOfCreditIssuance::lcSources();
		$selectedAccountTypeName = $request->get('accountType');
		$selectedCurrency  = $request->get('currency');
		$financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get();
		$accountTypes = AccountType::onlyCashAccounts()->get();		
        return view('lg_lc_statement_form', [
            'company' => $company,
            'financialInstitutionBanks' => $financialInstitutionBanks,
			'accountTypes'=>$accountTypes,
			'selectedAccountTypeName'=>$selectedAccountTypeName,
			'selectedCurrency'=>$selectedCurrency,
			'lcSources'=>$lcSources
        ]);
    }

    public function result(Company $company, Request $request)
    {
        $startDate = $request->get('start_date');
        $endDate = $request->get('end_date');
        $financialInstitutionId = $request->get('financial_institution_id');
		$financialInstitution = FinancialInstitution::find($financialInstitutionId);
		$lcFacilityId=  $request->get('lc_facility_id');
		$financialInstitutionName = $financialInstitution->getName();

		$letterOfCreditFacility = LetterOfCreditFacility::find($lcFacilityId);
		$letterOfCreditFacilityName = $letterOfCreditFacility ? $letterOfCreditFacility->getName() : null; 
        $currencyName = $request->get('currency');
		$results = [];
		$reportType = $request->get('report_type');
	
		$statementTableName = [
			'LetterOfCreditIssuance'=>'letter_of_credit_statements',
			'LetterOfGuaranteeIssuance'=>'letter_of_guarantee_statements',
			'LCOverdraft'=>'lc_overdraft_bank_statements'
		][$reportType];
		$isLcOverdraftBankStatement = $statementTableName == 'lc_overdraft_bank_statements';
		$lcTypeOrLgTypeColumnName = [
			'LetterOfCreditIssuance'=>'lc_type',
			'LetterOfGuaranteeIssuance'=>'lg_type',
		][$reportType] ?? null;

		$source = $request->get('source');
		$type = $request->get('type');
		
		
		$results = DB::table($statementTableName)
				 ->where($statementTableName.'.company_id',$company->id)
				 ->where('date', '>=', $startDate)
				 ->where('date', '<=', $endDate)
				 ->when(!$isLcOverdraftBankStatement,function($q) use($currencyName){
					 $q->where('currency',$currencyName);
				 })
				 ->when(!$isLcOverdraftBankStatement,function($q) use($financialInstitutionId){
					 $q->where('financial_institution_id',$financialInstitutionId);
				 })
				 ->when($source,function($q) use ($source){
					 $q->where('source',$source);
				 })
				 ->when($isLcOverdraftBankStatement,function($q) use($lcFacilityId){
					$q->where('lc_facility_id',$lcFacilityId);
				 })
				 ->when($lcTypeOrLgTypeColumnName , function($q) use ($lcTypeOrLgTypeColumnName,$type){
					 $q->where($lcTypeOrLgTypeColumnName,$type);
				 })
				 ->orderByRaw('date desc , '.$statementTableName.'.id desc')
				 ->get();
        if (!count($results)) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }

		
		$source = [
			'LetterOfCreditIssuance'=>LetterOfCreditIssuance::lcSources(),
			'LetterOfGuaranteeIssuance'=>LetterOfGuaranteeIssuance::lgSources() ,
			'LCOverdraft'=>LcOverdraftBankStatement::getSources()
		][$reportType][$source] ?? null;
		
		$type = [
			'LetterOfCreditIssuance'=>LcTypes::getAll(),
			'LetterOfGuaranteeIssuance'=>LgTypes::getAll(),
		][$reportType][$request->get('type')]??null;
		
        return view('lc_lg_bank_statement_result', [
            'results' => $results,
            'currency' => $currencyName,
			'financialInstitutionName'=>$financialInstitutionName,
			'type'=>$type,
			'source'=>$source,
			'isLcOverdraftBankStatement'=>$isLcOverdraftBankStatement,
			'letterOfCreditFacilityName'=>$letterOfCreditFacilityName
        ]);
    }
	public function getLgOrLcType(Request $request , Company $company){
	
		$modelName = $request->get('lcOrLg');
	
		$types = [
			'LetterOfCreditIssuance'=>LcTypes::getAll(),
			'LetterOfGuaranteeIssuance'=>LgTypes::getAll() ,
			'LCOverdraft'=>[]
		][$modelName];
		
		$sources = [
			'LetterOfCreditIssuance'=>LetterOfCreditIssuance::lcSources(),
			'LetterOfGuaranteeIssuance'=>LetterOfGuaranteeIssuance::lgSources() ,
			'LCOverdraft'=>[]
		][$modelName];
		return response()->json([
			'types'=>$types ,
			'sources'=>$sources
		]);
	}
}
