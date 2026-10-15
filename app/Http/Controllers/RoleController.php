<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\User;
use App\Support\Permissions\PermissionRegistry;
use App\Support\Permissions\PermissionResolver;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * RoleController
 * ==================================================================
 * Role Management: create roles and decide which permissions each one's
 * TEMPLATE carries.
 *
 * ⚠️ Editing a role here does NOT change any existing user. This
 * application is user-based (see PermissionResolver): a template is
 * copied onto a user when the account is created, or when an admin
 * applies it from that user's permission screen — never inherited
 * live. Both this screen and the user screen say so on the page, since
 * the opposite assumption is the natural one.
 *
 * Authorization is handled centrally by EnforcePermission via
 * RoutePermissionMap (`role.view` / `role.create` / `role.update` /
 * `role.delete`), so there is no per-action gate written here — with
 * two exceptions that are genuinely conditional and therefore belong
 * in the action itself:
 *
 *   • the four built-in roles cannot be renamed or deleted;
 *   • nobody may grant a role a permission they do not themselves
 *     hold, which would otherwise be a trivial privilege escalation
 *     (create a role with every permission, assign it to yourself).
 */
class RoleController extends Controller
{
    /**
     * Roles the application's own logic depends on by name
     * (User::isSuperAdmin(), the user-creation rules, seeders).
     * They may be re-permissioned but never renamed or removed.
     */
    private const PROTECTED_ROLES = [
        User::SUPER_ADMIN,
        User::COMPANY_ADMIN,
        User::MANAGER,
        User::USER,
    ];

    public function index(?Company $company = null)
    {
        $roles = Role::where('guard_name', 'web')
            ->withCount('permissions')
            ->withCount('users')
            ->orderBy('id')
            ->get()
            ->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $this->humanise($role->name),
                'permissions_count' => $role->permissions_count,
                'users_count' => $role->users_count,
                'is_protected' => in_array($role->name, self::PROTECTED_ROLES, true),
                'is_super_admin' => $role->name === User::SUPER_ADMIN,
                'edit_url' => $this->url('roles.edit', ['role' => $role->id], $company),
                'delete_url' => $this->url('roles.destroy', ['role' => $role->id], $company),
            ]);

        return Inertia::render('SuperAdmin/Roles/Index', [
            'company' => $company ? ['id' => $company->id] : null,
            'roles' => $roles,
            'totalPermissions' => count(PermissionRegistry::keys()),
            'createUrl' => $this->url('roles.create', [], $company),
        ]);
    }

    public function create(?Company $company = null)
    {
        return Inertia::render('SuperAdmin/Roles/Form', $this->formProps(null, $company));
    }

    public function edit(Role $role, ?Company $company = null)
    {
        return Inertia::render('SuperAdmin/Roles/Form', $this->formProps($role, $company));
    }

    public function store(Request $request, ?Company $company = null)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/', Rule::unique('roles', 'name')->where('guard_name', 'web')],
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ], [
            'name.regex' => __('Use lowercase letters, numbers and hyphens only — for example "branch-manager".'),
        ]);

        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);
        $this->syncPermissions($role, $request->user(), $data['permissions'] ?? []);

        return redirect()
            ->to($this->url('roles.index', [], $company))
            ->with('success', __('Role created successfully'));
    }

    public function update(Request $request, Role $role, ?Company $company = null)
    {
        $isProtected = in_array($role->name, self::PROTECTED_ROLES, true);

        $rules = [
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ];

        // A built-in role keeps its name; renaming it would break
        // User::isSuperAdmin() and the user-creation rules that match
        // on these exact strings.
        if (! $isProtected) {
            $rules['name'] = ['required', 'string', 'max:100', 'regex:/^[a-z0-9-]+$/', Rule::unique('roles', 'name')->where('guard_name', 'web')->ignore($role->id)];
        }

        $data = $request->validate($rules);

        if (! $isProtected && isset($data['name'])) {
            $role->update(['name' => $data['name']]);
        }

        $this->syncPermissions($role, $request->user(), $data['permissions'] ?? []);

        return redirect()
            ->to($this->url('roles.index', [], $company))
            ->with('success', __('Role updated successfully'));
    }

    public function destroy(Role $role, ?Company $company = null)
    {
        if (in_array($role->name, self::PROTECTED_ROLES, true)) {
            return redirect()
                ->to($this->url('roles.index', [], $company))
                ->with('fail', __('Built-in roles cannot be deleted.'));
        }

        if ($role->users()->exists()) {
            return redirect()
                ->to($this->url('roles.index', [], $company))
                ->with('fail', __('This role is still assigned to users. Move them to another role first.'));
        }

        $role->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return redirect()
            ->to($this->url('roles.index', [], $company))
            ->with('success', __('Role deleted successfully'));
    }

    /**
     * Apply a permission set to a role.
     *
     * Two safeguards, both deliberate:
     *
     * 1. Only keys the registry declares are accepted. A forged request
     *    naming an arbitrary string can't create a stray permission row.
     *
     * 2. An editor cannot grant what they do not hold. Without this,
     *    anyone with `role.update` could mint a role carrying every
     *    permission and assign it to themselves — a complete bypass of
     *    the whole system. Super Admins are unrestricted.
     */
    private function syncPermissions(Role $role, ?User $actor, array $submitted): void
    {
        $declared = array_fill_keys(PermissionRegistry::keys(), true);
        $keys = array_values(array_filter($submitted, fn ($key) => isset($declared[$key])));

        if (! PermissionResolver::isSuperAdmin($actor)) {
            $held = array_fill_keys(PermissionResolver::grantedKeys($actor), true);
            $alreadyOnRole = array_fill_keys($role->permissions->pluck('name')->all(), true);

            $keys = array_values(array_filter(
                $keys,
                // Keep a permission that's already on the role even if
                // the editor lacks it, so a partial editor saving the
                // form doesn't silently strip rights they can't see.
                fn ($key) => isset($held[$key]) || isset($alreadyOnRole[$key])
            ));
        }

        // Ensure every key exists as a row before syncing — syncPermissions()
        // throws PermissionDoesNotExist otherwise, which is exactly the
        // failure mode UsersAndPermissionsController had to work around.
        foreach ($keys as $key) {
            Permission::firstOrCreate(['name' => $key, 'guard_name' => 'web']);
        }

        $role->syncPermissions($keys);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        PermissionResolver::flush();
    }

    private function formProps(?Role $role, ?Company $company): array
    {
        $actor = request()->user();
        $isSuperAdminRole = $role && $role->name === User::SUPER_ADMIN;

        return [
            'company' => $company ? ['id' => $company->id] : null,
            'mode' => $role ? 'edit' : 'create',
            'role' => $role ? [
                'id' => $role->id,
                'name' => $role->name,
                'label' => $this->humanise($role->name),
                'is_protected' => in_array($role->name, self::PROTECTED_ROLES, true),
                'is_super_admin' => $isSuperAdminRole,
                'users_count' => $role->users()->count(),
            ] : null,
            // groups → modules → permissions, ready to render as a
            // grouped checkbox matrix.
            'tree' => PermissionRegistry::tree(),
            'selected' => $role ? $role->permissions->pluck('name')->values() : [],
            // Keys the editor may toggle. Anything outside this renders
            // disabled, so the UI shows the same limit the server enforces.
            'grantable' => PermissionResolver::isSuperAdmin($actor)
                ? PermissionRegistry::keys()
                : PermissionResolver::grantedKeys($actor),
            'isSuperAdminEditor' => PermissionResolver::isSuperAdmin($actor),
            'submitUrl' => $role
                ? $this->url('roles.update', ['role' => $role->id], $company)
                : $this->url('roles.store', [], $company),
            'backUrl' => $this->url('roles.index', [], $company),
        ];
    }

    private function url(string $name, array $params, ?Company $company): string
    {
        if ($company) {
            $params['company'] = $company->id;
        }

        return route($name, $params);
    }

    private function humanise(string $roleName): string
    {
        return ucwords(str_replace('-', ' ', $roleName));
    }
}
