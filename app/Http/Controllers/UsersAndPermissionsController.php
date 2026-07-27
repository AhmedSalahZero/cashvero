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
        // Reached from Super Admin Users list; keep server in sync with UI gate.
        abort_unless(request()->user()?->isSuperAdmin(), 403);

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

    /**
     * ⚠️ REAL BUG FIXED HERE (2026-07-25): `HAuth::getPermissions()` is
     * the single source of truth for which permission checkboxes render
     * on this form (see edit() above) — but syncing that list into the
     * actual `permissions` table is a separate, manual step (the
     * `refresh:permissions` Artisan command). Whenever a new permission
     * is added to that list without also re-running that command, its
     * checkbox still renders fine (edit()'s `$user->can(...)` check
     * degrades gracefully to `false` for an unknown permission) — but
     * saving it here used to throw
     * Spatie\Permission\Exceptions\PermissionDoesNotExist, since
     * syncPermissions() requires every name to already exist as a real
     * row.
     *
     * Fixed by ensuring each submitted permission name exists
     * (firstOrCreate — creates it if missing, no-ops if it already
     * exists) before syncing. Deliberately NOT using the
     * `refresh:permissions` command as the fix: that command deletes
     * ALL permissions and ALL user/role permission assignments across
     * every company, then rebuilds everyone back to defaults — it
     * would silently wipe out any custom permission grants any admin
     * has ever configured, system-wide. This fix only ever creates the
     * specific permission name(s) actually being saved, and never
     * deletes or touches anything else.
     */
    public function update(Request $request, User $user)
    {
        // update() previously had no isCashManagement / role gate while edit()
        // lived behind Super Admin UI — enforce Super Admin on the write path too.
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $permissionNames = array_keys((array) $request->permissions);

        foreach ($permissionNames as $permissionName) {
            \Spatie\Permission\Models\Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        }

        $user->syncPermissions($permissionNames);
        toastr()->success(__('updated Successfully'));

        return redirect()->back();
    }
}
