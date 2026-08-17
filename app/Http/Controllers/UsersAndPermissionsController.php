<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use App\Support\Permissions\PermissionRegistry;
use App\Support\Permissions\PermissionResolver;
use App\Support\Permissions\RoleTemplate;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

/**
 * UsersAndPermissionsController
 * ------------------------------------------------------------------
 * THE screen where access is decided.
 *
 * This application is user-based: every permission a person holds is a
 * row of their own, and this is where those rows are set. A role is
 * only a template that was copied here once at creation — changing a
 * role later does not reach anyone, so nothing on this screen is
 * "inherited" or locked.
 *
 * Reached from the Users list (the eye icon).
 */
class UsersAndPermissionsController extends Controller
{
    /**
     * Show one user's full permission set, grouped by module.
     */
    public function edit(User $user, ?Company $company = null)
    {
        abort_unless(request()->user()?->isSuperAdmin(), 403);

        $actor = request()->user();
        $held = $user->permissions->pluck('name')->flip();

        $isSuperAdmin = PermissionResolver::isSuperAdmin($user);

        // Which boxes this editor is allowed to move. A Super Admin may
        // move all of them; anyone else may only grant what they hold,
        // and may never take away something they cannot see.
        $grantable = PermissionResolver::isSuperAdmin($actor)
            ? PermissionRegistry::keys()
            : PermissionResolver::grantedKeys($actor);

        return \Inertia\Inertia::render('SuperAdmin/Users/Permissions', [
            'company' => $company ? ['id' => $company->id] : null,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->getRoleName(),
                'is_super_admin' => $isSuperAdmin,
                'permissions_count' => $held->count(),
            ],
            'tree' => PermissionRegistry::tree(),
            'selected' => $user->permissions->pluck('name')
                ->filter(fn ($name) => PermissionRegistry::has($name))
                ->values(),
            'grantable' => $grantable,
            'isSuperAdminEditor' => PermissionResolver::isSuperAdmin($actor),
            'totalPermissions' => count(PermissionRegistry::keys()),
            /**
             * Templates the editor can apply to fill the form in one
             * click. Purely a convenience — applying one only fills the
             * checkboxes; nothing is saved until Save is pressed.
             */
            'templates' => Role::where('guard_name', 'web')
                ->orderBy('id')
                ->get()
                ->map(fn (Role $role) => [
                    'name' => $role->name,
                    'label' => ucwords(str_replace('-', ' ', $role->name)),
                    'keys' => RoleTemplate::keysFor($role),
                ])
                ->values(),
            'submitUrl' => $company
                ? route('user.permissions.update', ['user' => $user->id, 'company' => $company->id])
                : route('user.permissions.update', ['user' => $user->id]),
            'backUrl' => $company ? route('user.index', ['company' => $company->id]) : route('user.index'),
        ]);
    }

    /**
     * Replace this user's permissions with exactly what was submitted.
     *
     * ⚠️ The previous implementation called Permission::firstOrCreate()
     * on every submitted name before syncing, to work around
     * PermissionDoesNotExist for names declared in HAuth but never
     * synced to the table. That workaround also meant a forged request
     * could mint arbitrary permission rows. RoleTemplate::setFor()
     * filters to keys the registry declares BEFORE creating anything,
     * so an unknown name is now discarded rather than created.
     *
     * It deliberately does NOT use the `refresh:permissions` command:
     * that deletes all permissions and all grants system-wide and
     * rebuilds everyone to defaults, which would wipe every per-user
     * configuration this screen exists to create.
     */
    public function update(Request $request, User $user)
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        $submitted = $request->input('permissions', []);

        // Accepts both shapes: the {key: 1} map the form posts, and a
        // plain list, so an API caller isn't forced into the UI's shape.
        $keys = array_is_list($submitted)
            ? array_values($submitted)
            : array_keys(array_filter($submitted));

        RoleTemplate::setFor($user, $keys, $request->user());

        return redirect()
            ->to($request->input('back_url') ?: route('user.index'))
            ->with('success', __('Permissions updated successfully'));
    }
}
