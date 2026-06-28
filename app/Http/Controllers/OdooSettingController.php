<?php
namespace App\Http\Controllers;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\Partner;
use App\Services\Api\OdooService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OdooSettingController
{
    // use GeneralFunctions;
	public function index(Company $company,Request $request)
	{
		$financialInstitutionBanks = FinancialInstitution::onlyForCompany($company->id)->onlyBanks()->get();
        return view('other-odoo-settings.form', [
			'company'=>$company,
			'model'=>$company->odooSetting,
			'financialInstitutionBanks'=>$financialInstitutionBanks
		]);
    }
	public function store(Request $request, Company $company){
		$setting = $company->odooSetting;
		$result = [];
		$odooService = new OdooService($company);
		$taxesColumns = Partner::getTaxesNames() ;
		$revenueResults = [];
		foreach($request->get('revenues') as $revenueArr){
			$code = $revenueArr['odoo_code'];
			$bankId = isset($revenueArr['bank']) && is_numeric($revenueArr['bank']) ? $revenueArr['bank'] : null;
			$journal = $odooService->fetchData('account.account',['code','name'],[[['code','=',$code]]]);
			$odooId = $journal[0]['id']??null ;
			if($odooId){
				$revenueResults[]  = [
					'odoo_id'=>$odooId ,
					'odoo_code'=>$code ,
					'financial_institution_id'=>$bankId,
					'company_id'=>$company->id 
				]; 
			}
		}
		$company->interestRevenuesAccounts()->delete();
		if(count($revenueResults)){
			DB::table('interest_revenue_accounts')->insert($revenueResults);
		}
		foreach($request->except(array_merge(['_token','revenues'])) as $key => $value){
			$journal = $odooService->fetchData('account.account',['code','name'],[[['code','=',$value]]]);
			if($journal){
				$dbKeyName = str_replace('_code','_id',$key) ;
				$result[$dbKeyName] = $journal[0]['id'] ; 
				$result[$key] = $value ; 
				if(in_array($key,array_keys($taxesColumns))){
					Partner::where('company_id',$company->id)->where('name',$taxesColumns[$key])->where('is_tax',1)->update([
						'odoo_id'=>$result[$dbKeyName]
					]);
				}
			}
		}
		
		$setting ? $setting->update($result) :$company->odooSetting()->create($result) ;
		
		return redirect()->route('odoo-settings.index',['company'=>$company->id]);
	}
	
}
