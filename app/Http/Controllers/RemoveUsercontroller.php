<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

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
 */
class RemoveUsercontroller extends Controller
{

    public function __invoke(Request $request)
    {
        $user_id = $request->get('user_id') ;
     
        $user = User::where('id',$user_id)->firstOrFail();
            $user->delete();

        return redirect()->back()->with('success', __('User deleted successfully.'));

    }
}
