<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Company;

use App\Models\Partner;
use App\Models\User;
use App\Traits\ImageSave;
use Illuminate\Http\Request;

class CompanyController extends Controller
{

    public function index()
    {
        $companies = Company::orderBy('id','desc')->get();
		
        return view('super_admin_view.companies.index',compact('companies'));
    }

 
    public function create()
    {
        return view('super_admin_view.companies.form');
    }

  
    public function store(Request $request)
    {
        toastr()->success('Created Successfully');
        $companySection = Company::create($request->except(['image','systems','is_api']));
		foreach($request->get('systems') as $systemName){
			$companySection->systems()->create([
				'system_name'=>$systemName
			]);
		}
		if($request->has('is_api')){
			return $companySection;
		}
        ImageSave::saveIfExist('image',$companySection);
		Partner::handleTaxesColumnsToPartnerTable($companySection);
		
        return redirect()->back();
    }
    // public function adminCompany(Request $request,$company_id)
    // {
    //     $company_row = Company::findOrFail($company_id);
    //     if ($request->method() == 'GET') {
    //         return view('super_admin_view.companies.form',compact('company_row'));
    //     }elseif ($request->method() == "POST") {
    //         $request['sub_of'] = $company_id;
    //         $request['type'] = 'single';

    //         $companySection = Company::create($request->except('image'));
    //         ImageSave::saveIfExist('image',$companySection);
    //         (new BranchController)->createMainBrach($companySection->id);
    //         toastr()->success('Created Successfully');
    //         return redirect()->back();
    //     }

    // }

    // public function editAdminCompany(Request $request,$company_id,Company $companySection)
    // {
    //     $company_row = Company::findOrFail($company_id);


    //     if ($request->method() == 'GET') {
    //         return view('super_admin_view.companies.form',compact('company_row','companySection'));
    //     }else {
    //         $companySection->update($request->except('image'));
    //         ImageSave::saveIfExist('image',$companySection);
    //         toastr()->success('Updated Successfully');
    //         return redirect()->back();
    //     }
    // }
 
    public function edit(Company $companySection)
    {
        return view('super_admin_view.companies.form',compact('companySection'));
    }

  
    public function update(Request $request, Company $companySection)
    {
        toastr()->success('Updated Successfully');
		$oldSystems =$companySection->getSystemsNames(); 
		$newSystems = $request->get('systems');
		$systemsToPreserve  = array_intersect($oldSystems,$newSystems);
		$newSystemsToBeAdded  = array_diff($newSystems,$oldSystems);
		$companySection->users->each(function($user){
			$user->update([
				'odoo_id'=>null 
			]);
		});
		
		foreach($request->get('odoo_username') as $userId => $odooUsername){
			$user = User::find($userId);
			$user->update([
				'odoo_username'=>$odooUsername,
				'odoo_db_password'=>$request->input('odoo_db_password.'.$userId)
			]);
		}
		Partner::handleTaxesColumnsToPartnerTable($companySection);
        $companySection->update($request->except(['image','systems','odoo_username','odoo_db_password']));
		
		$companySection->systems()->delete();
		foreach($newSystems as $systemName){
			$companySection->systems()->create(['system_name'=>$systemName]);
		}
        ImageSave::saveIfExist('image',$companySection);
		$companySection->syncPermissionForAllUser($systemsToPreserve,$newSystemsToBeAdded);
        toastr()->success('Updated Successfully');
        return redirect()->back();
    }

    public function destroy(Company $companySection)
    {
        toastr()->error('Deleted Successfully');
		
        $companySection->delete();
        return redirect()->back();
    }

}
