<?php

namespace App\Http\Controllers;


use App\Enums\LgSources;
use App\Enums\LgTypes;
use App\Models\Company;
use App\Models\Partner;
use App\Traits\GeneralFunctions;
use Carbon\Carbon;
use Illuminate\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;


class LgByBeneficiaryNameReportController
{
    use GeneralFunctions;

    public function index(Company $company,Request $request)
    {
		$selectedCurrency  = $request->get('currency_name');
		$currencies = DB::table('letter_of_guarantee_issuances')->where('company_id',$company->id)->get()->unique('lg_currency')->pluck('lg_currency','lg_currency')->toArray();
        return view('lg_by_beneficiary_name_form', [
            'company' => $company,
			'selectedCurrency'=>$selectedCurrency,
			'currencies'=>$currencies
        ]);
    }

    public function result(Company $company, Request $request)
    {
        $startDate = $request->get('start_date');
        // $endDate = $request->get('end_date');
      
        $currencyName = $request->get('currency_name');
		$results = [];
		$partnerIds = $request->get('beneficiary_id',[]);
		// $beneficiaryName = Partner::find($partnerId)->getName();
		$status = $request->get('status');
		$results = DB::table('letter_of_guarantee_issuances')->where('letter_of_guarantee_issuances.company_id',$company->id)->where('lg_currency',$currencyName)->whereIn('partner_id',$partnerIds)
		->when($status== 'running',function($q){
			$q->where('status','running');
		})
		->where('renewal_date','>=',$startDate)
		// ->whereBetween('issuance_date',[$startDate,$endDate])
		->join('partners','partners.id','=','letter_of_guarantee_issuances.partner_id')
		->join('financial_institutions','financial_institutions.id','=','letter_of_guarantee_issuances.financial_institution_id')
		->join('banks','banks.id','=','financial_institutions.bank_id')
		->selectRaw(
			'letter_of_guarantee_issuances.id as id , partner_id , partners.name as partner_name , lg_type , transaction_name,lg_code, source ,banks.name_en as financial_institution_name , lg_amount , case when status = \'cancelled\' then \'cancelled\' else (DATE_FORMAT(renewal_date,\'%d-%m-%Y\')) end as renewal_date , cash_cover_amount,lg_commission_rate '
		)->get();
        if (!count($results)) {
            return redirect()->back()->with('fail', __('No Data Found'));
        }
		$results = $this->paginate($results,50);
		$lgsTypes = LgTypes::getAll();
		$lgsSources = LgSources::getAll();
        return view('lg_by_beneficiary_name_result', [
            'results' => $results,
			'lgsTypes'=>$lgsTypes,
            'currency' => $currencyName,
			'lgsSources'=>$lgsSources,
			'startDate'=>Carbon::make($startDate)->format('d-m-Y'),
        ]);
    }
	
function paginate(\Illuminate\Support\Collection $results, $pageSize)
{
	$page = Paginator::resolveCurrentPage('page');
	
	$total = $results->count();
	return $this->paginator($results->forPage($page, $pageSize), $total, $pageSize, $page, [
		'path' => Paginator::resolveCurrentPath(),
		'pageName' => 'page',
	]);

}
 function paginator($items, $total, $perPage, $currentPage, $options)
{
	return Container::getInstance()->makeWith(LengthAwarePaginator::class, compact(
		'items', 'total', 'perPage', 'currentPage', 'options'
	));
}
}
