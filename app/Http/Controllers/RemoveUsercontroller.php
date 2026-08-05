<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * RemoveUsercontroller
 * ------------------------------------------------------------------
 * The real "delete a user" mechanism used by the Super Admin Users
 * page (SuperAdmin/Users/Index.vue calls this via router.post(
 * 'remove-user', {user_id}) — UserController::destroy() is a genuine
 * empty stub, confirmed unused).
 *
 * ⚠️ Real bug fixed here — the same root cause already found and
 * fixed on RemoveCompanyController and elsewhere in this codebase
 * (Roadmap bugs #19/#22/#38): this always returned
 * response()->json(['status'=>true]), but the calling page uses a
 * genuine Inertia visit (router.post), which can only ever accept a
 * redirect or an Inertia::render() — never a raw JSON body. Fixed by
 * redirecting back to whichever Users list the delete was triggered
 * from (redirect()->back(), same approach the original
 * CompanyController::destroy() already used) instead — this also
 * correctly preserves the company-scoped vs. all-companies view,
 * since UserController::index() supports both. The deletion logic
 * itself ($user->delete()) is completely UNCHANGED.
 *
 * ⚠️ Second round of the SAME symptom, different cause — identical to
 * RemoveCompanyController (see its docblock): a plain redirect only
 * works for an Inertia visit if the browser transparently follows the
 * 302 on the XHR and hands Inertia the *followed* page. When that
 * doesn't happen the client gets the 302 itself, which carries no
 * X-Inertia header, so Inertia treats it as an invalid response and
 * dumps Laravel's raw "Redirecting to …" document in a dialog.
 * Inertia::location() drops that dependency: for an Inertia request it
 * answers 409 + X-Inertia-Location, which the client handles by
 * navigating there itself; for a plain request it returns the very
 * same RedirectResponse as before. back() still resolves the target,
 * so the company-scoped vs. all-companies view is still preserved.
 */
class RemoveUsercontroller extends Controller
{

    public function __invoke(Request $request)
    {
        // UI is Super-Admin-only; enforce the same gate on the server
        // so a forged POST from any authenticated session cannot delete users.
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $user_id = $request->get('user_id') ;
     
        $user = User::where('id',$user_id)->firstOrFail();
            $user->delete();

        $redirect = redirect()->back()->with('success', __('User deleted successfully.'));

        // Inertia visit  -> 409 + X-Inertia-Location (client navigates itself)
        // normal request -> the same RedirectResponse as before
        return Inertia::location($redirect);
    }
}
