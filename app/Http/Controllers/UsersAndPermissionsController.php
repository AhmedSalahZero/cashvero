<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * UsersAndPermissionsController
 * ------------------------------------------------------------------
 * edit()/update() — the "eye icon" screen from the User list:
 * editing one specific user's individual permission overrides.
 *
 * ── Frontend migration status ─────────────────────────────────────
 *   ✅ edit() → Vue + Inertia (SuperAdmin/Users/Permissions.vue)
 *   ✅ update() → redirects correctly
 */
class UsersAndPermissionsController extends Controller
{
    public function edit(User $user, ?Company $company = null)
    {
        $groups = [];
        foreach (\App\Helpers\HAuth::getPermissions($user->getSystemsNames()) as $permissionArr) {
            $groups[$permissionArr['group']][] = [
                'name' => $permissionArr['name'],
                'label' => $permissionArr['view-name'],
                'checked' => $user->can($permissionArr['name']),
            ];
        }

        return \Inertia\Inertia::render('SuperAdmin/Users/Permissions', [
            'company' => $company ? ['id' => $company->id] : null,
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
            'groups' => collect($groups)->map(fn ($perms, $groupName) => [
                'name' => $groupName,
                'permissions' => $perms,
            ])->values(),
            'submitUrl' => $company
                ? route('user.permissions.update', ['user' => $user->id, 'company' => $company->id])
                : route('user.permissions.update', ['user' => $user->id]),
            'backUrl' => $company ? route('user.index', ['company' => $company->id]) : route('user.index'),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $user->syncPermissions(array_keys((array) $request->permissions));
        toastr()->success(__('updated Successfully'));

        return redirect()->back();
    }
}
