<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;

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
 *
 * ⚠️ Second round of the SAME symptom, different cause: after that
 * first fix the page started showing Laravel's raw redirect document
 * ("Redirecting to https://…/en/companySection.") inside Inertia's
 * error dialog. A plain redirect only works for an Inertia visit if
 * the browser transparently follows the 302 on the XHR and hands
 * Inertia the *followed* page — when that doesn't happen the client
 * gets the 302 itself, which carries no X-Inertia header, so Inertia
 * treats it as an invalid response and dumps the body in a dialog.
 * Inertia::location() removes that dependency entirely: for an
 * Inertia request it answers 409 + X-Inertia-Location, which the
 * client is built to handle by navigating to the URL itself; for a
 * plain (non-Inertia) request it still returns the exact same
 * RedirectResponse as before. Flash message and delete logic
 * unchanged.
 */
class RemoveCompanyController extends Controller
{
    
    public function __invoke(Request $request)
    {
        // UI is Super-Admin-only; enforce the same gate on the server
        // so a forged POST from any authenticated session cannot wipe a company.
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $company_id = $request->get('company_id') ;
     
        $company = Company::where('id',$company_id)->firstOrFail();
		Artisan::call('delete:all',['company_id'=>$company_id]);
        $company->delete();

        $redirect = redirect()->route('companySection.index')
            ->with('success', __('Company and all its data were deleted successfully.'));

        // Inertia visit  -> 409 + X-Inertia-Location (client navigates itself)
        // normal request -> the same RedirectResponse as before
        return Inertia::location($redirect);
    }
}
