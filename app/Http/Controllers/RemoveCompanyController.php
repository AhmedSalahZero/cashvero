<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * RemoveCompanyController
 * ------------------------------------------------------------------
 * The real "delete a company" mechanism used by the Super Admin
 * Companies page (SuperAdmin/Companies/Index.vue calls this via
 * router.post('remove-company', {company_id}), NOT
 * CompanyController::destroy(), which is unused by the current UI).
 *
 * ⚠️ Real bug fixed here — the same root cause already found and
 * fixed multiple times elsewhere in this codebase (Roadmap bugs
 * #19/#22/#38, and again on the LG & LC dashboard): this always
 * returned response()->json(['status'=>true]), but the calling page
 * uses a genuine Inertia visit (router.post), which can only ever
 * accept a redirect or an Inertia::render() — never a raw JSON body.
 * That meant every single company deletion surfaced as "All Inertia
 * requests must receive a valid Inertia response, however a plain
 * JSON response was received", even though the delete itself had
 * already completed successfully underneath (hence it being gone on
 * reload). Fixed by redirecting back to the Companies list with a
 * flash message instead — the deletion logic itself (Artisan
 * `delete:all`, then $company->delete()) is completely UNCHANGED.
 */
class RemoveCompanyController extends Controller
{
    
    public function __invoke(Request $request)
    {
	
        $company_id = $request->get('company_id') ;
     
        $company = Company::where('id',$company_id)->firstOrFail();
		Artisan::call('delete:all',['company_id'=>$company_id]);
        $company->delete();

        return redirect()->route('companySection.index')->with('success', __('Company and all its data were deleted successfully.'));

    }
}
