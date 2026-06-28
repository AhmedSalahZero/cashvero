<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Partner;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;


class PartnersStatementController
{
    use GeneralFunctions;
    public function index(Company $company)
	{
		$partnerTypes = ['is_subsidiary_company'=>__('Subsidiary Company') , 'is_shareholder'=>__('Shareholder') , 'is_employee'=>__('Employee'),
		'is_other_partner'=>__('Other Partner'),
		'is_tax'=>__('Taxes & Insurance')
	
	];
		
        return view('partners_statement_form', [
			'company'=>$company,
			'partnerTypes'=>$partnerTypes
		]);
    }
	public function result(Company $company , Request $request){
		$startDate = $request->get('start_date');
		$endDate = $request->get('end_date');
		$partnerType = $request->get('partner_type');
		$currency = $request->get('currency');
		$partnerIds = (array)$request->get('partner_id',[]);
		// foreach($partnerIds )
	//	$partner = Partner::find($partnerId);
		$statementTableName = [
			'is_subsidiary_company'=>'subsidiary_company_statements',
			'is_shareholder'=>'shareholder_statements',
			'is_employee'=>'employee_statements',
			'is_other_partner'=>'other_partner_statements',
			'is_tax'=>'tax_statements'
		][$partnerType] ;
		$statements = [];
		foreach($partnerIds as $partnerId){
			$partner = Partner::find($partnerId);
			$currentResult = DB::table($statementTableName)
			->where('.company_id',$company->id)
			->where('currency_name',$currency)
			->where('partner_id',$partnerId)
			->where('date','>=',$startDate)
			->where('date','<=',$endDate)
			->orderByRaw('full_date asc , created_at asc')
			->get() ;
			if(count($currentResult)){
				$statements[$partner->id]=['name'=>$partner->getName() , 'statements'=>$currentResult];
			}
			
		}
		

			if(!count($statements)){
				return redirect()
									->back()
									->with('fail',__('No Data Found'))	
									;
			}
		
		return view('partners_statement_result',[
			'statements'=>$statements,
			'currency'=>$currency,
			'title'=>__('Partners Statements')
			
			// 'partner'=>$partner
		]);
	}




}
