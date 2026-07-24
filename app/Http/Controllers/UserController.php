<?php

namespace App\Http\Controllers;

use App\Helpers\HArr;
use App\Helpers\HAuth;
use App\Models\Company;
use App\Models\User;
use App\Traits\ImageSave;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

/**
 * UserController
 * ------------------------------------------------------------------
 * Super-admin-only user management (create/edit basic user info,
 * assign companies + a role). Reached with or without a {company}
 * segment — when reached from within a specific company's context,
 * the company multi-select is pre-narrowed to just that company.
 *
 * ⚠️ Confirmed NOT wired into the actual create/edit form:
 * renderPermissionForUser() and getUsersBasedOnCompanyAndRole() are
 * real AJAX endpoints with real routes, but the original
 * users/form.blade.php never calls either one — no inline permission
 * preview exists on this form. Editing an individual user's actual
 * permission overrides is a SEPARATE screen entirely
 * (UsersAndPermissionsController, reached via the eye icon on the
 * list — see that controller). Left both untouched here, unused,
 * matching the original exactly.
 *
 * ⚠️ Role dropdown options are gated per-option, not all-or-nothing:
 * "Super Admin" only shows if the CURRENT user is a super admin (or
 * the user being edited already has that role); "Company Admin" /
 * "Manager" / "User" each gated by a distinct 'create X' permission
 * on the current user (or already having that role, when editing).
 * Matched exactly — computed server-side into a `roleOptions` array
 * so the Vue page doesn't need to know these rules itself.
 *
 * ⚠️ "Max Users Allowed" is only relevant when Role = Company Admin —
 * matches the original's JS show/hide toggle.
 *
 * ── Frontend migration status (as of this file's last update) ──────
 *   ✅ index() / create() / edit() → MIGRATED to Vue + Inertia.
 *      Renders resources/js/Pages/SuperAdmin/Users/Index.vue and
 *      .../Form.vue.
 *   ✅ store() → Real bug fixed (see its own docblock): was
 *      `redirect()->back()`, landing the user back on the empty
 *      Create form after a successful save instead of the Users list.
 *      Now redirects explicitly to user.index (company-scoped or
 *      global, matching how it was reached). Also now validates
 *      `role` as required — previously missing entirely, so a blank
 *      role reached Spatie's assignRole() and threw a raw exception
 *      rather than a clean validation message.
 *   ⚠️ update() → still ends in `redirect()->back()` and still has no
 *      `role` required validation — the same 2 issues store() had.
 *      Left UNCHANGED for now since only the create flow was
 *      reported; flagging in case the project owner wants edit
 *      matched too.
 */
class UserController extends Controller
{
    use ImageSave;

    public function __construct()
    {
        $this->middleware(['can:view users'])->only(['index']);
    }

    /**
     * ✅ MIGRATED to Vue + Inertia. Renders
     * resources/js/Pages/SuperAdmin/Users/Index.vue.
     */
    public function index(?Company $company = null)
    {
        $users = User::getUsersWithRoles($company);

        return \Inertia\Inertia::render('SuperAdmin/Users/Index', [
            'company' => $company ? ['id' => $company->id] : null,
            'createUrl' => $company ? route('user.create', ['company' => $company->id]) : route('user.create'),
            'rows' => collect($users)->map(function (User $u) use ($company) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'avatar_url' => $u->getFirstMediaUrl() ?: null,
                    'role_name' => $u->roles[0]->name ?? '-',
                    'companies' => $u->companies->map(fn ($c) => $c->name['en'] ?? ($c->name[array_key_first($c->name ?? ['' => ''])] ?? ''))->values(),
                    'edit_url' => $company ? route('user.edit', ['user' => $u->id, 'company' => $company->id]) : route('user.edit', ['user' => $u->id]),
                    'permissions_url' => $company ? route('user.permissions.edit', ['user' => $u->id, 'company' => $company->id]) : route('user.permissions.edit', ['user' => $u->id]),
                ];
            })->values(),
            'removeUrl' => route('remove.user'),
        ]);
    }

    /**
     * ✅ MIGRATED to Vue + Inertia — shares the same page component as
     * edit() (resources/js/Pages/SuperAdmin/Users/Form.vue).
     */
    public function create(?Company $company = null)
    {
        return \Inertia\Inertia::render('SuperAdmin/Users/Form', $this->getCommonViewVars(null, $company));
    }

    protected function getCommonViewVars(?User $user, ?Company $company): array
    {
        $companies = $company ? Company::where('id', $company->id)->get() : Company::all();
        $authUser = auth()->user();

        $roleOptions = [];
        if ($authUser->isSuperAdmin() || ($user && $user->hasRole(User::SUPER_ADMIN))) {
            $roleOptions[] = ['value' => User::SUPER_ADMIN, 'label' => 'Super Admin'];
        }
        if ($authUser->can('create company admin') || ($user && $user->hasRole(User::COMPANY_ADMIN))) {
            $roleOptions[] = ['value' => User::COMPANY_ADMIN, 'label' => 'Company Admin'];
        }
        if ($authUser->can('create manager') || ($user && $user->hasRole(User::MANAGER))) {
            $roleOptions[] = ['value' => User::MANAGER, 'label' => 'Manager'];
        }
        if ($authUser->can('create user') || ($user && $user->hasRole(User::USER))) {
            $roleOptions[] = ['value' => User::USER, 'label' => 'User'];
        }

        return [
            'mode' => $user ? 'edit' : 'create',
            'company' => $company ? ['id' => $company->id] : null,
            'model' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->getFirstMediaUrl() ?: null,
                'max_users' => $user->max_users ?? 10,
                'company_ids' => $user->companies->pluck('id')->values(),
                'role' => $user->roles[0]->name ?? '',
            ] : null,
            'companies' => $companies->map(fn (Company $c) => ['id' => $c->id, 'name' => $c->name['en'] ?? ($c->name[array_key_first($c->name ?? ['' => ''])] ?? '')])->values(),
            'roleOptions' => $roleOptions,
            'submitUrl' => $user
                ? ($company ? route('user.update', ['user' => $user->id, 'company' => $company->id]) : route('user.update', ['user' => $user->id]))
                : ($company ? route('user.store', ['company' => $company->id]) : route('user.store')),
            'backUrl' => $company ? route('user.index', ['company' => $company->id]) : route('user.index'),
        ];
    }

    /**
     * Stores a new User.
     *
     * ✅ Real bug fixed here: same root cause as CompanyController's
     * store() (see that file) — ended with `redirect()->back()`,
     * landing the user back on the empty Create form after a
     * successful save instead of the Users list. Fixed by redirecting
     * explicitly to user.index, correctly preserving the
     * company-scoped vs. global context (matches getCommonViewVars()'s
     * own backUrl logic just above).
     *
     * ✅ Real gap fixed: `role` was never actually required
     * server-side — only `email` uniqueness was validated. Submitting
     * with no role selected reached `$newUser->assignRole($request->role)`
     * with a null/empty value, which Spatie's permission package
     * throws a raw, unfriendly exception for rather than a clean
     * validation message. Added `'role' => 'required'`, matching the
     * `*` the Vue form already (visually, but not functionally) marks
     * it with.
     */
    public function store(Request $request, ?Company $company = null)
    {
        $user = auth()->user();
        if (! $user->canStoreMoreUser()) {
            return redirect()->back()->with('fail', __('You Exceed Your Max Users [ '.$user->max_users.' ]'));
        }
        $request->validate([
            'email' => 'unique:users,email',
            'role' => 'required',
        ]);
        $request['password'] = Hash::make($request->password);
        $request['subscription'] = 'subscripted';

        $newUser = User::create(
            array_merge(
                $request->except('avatar', 'companies'),
                ['created_by' => auth()->user()->id]
            ),
        );
        $newUser->companies()->attach($request->companies);
        $newUser->assignRole($request->role);

        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->clearClassPermissions();
        $permissions = HAuth::getPermissions($newUser->getSystemsNames());
        foreach ($permissions as $permissionArr) {
            $permission = Permission::findByName($permissionArr['name']);
            $newUser->assignNewPermission($permissionArr, $permission);
        }

        ImageSave::saveIfExist('image', $newUser);

        return redirect()->route('user.index', $company ? ['company' => $company->id] : []);
    }

    /**
     * ✅ MIGRATED to Vue + Inertia — shares the same page component as
     * create() (resources/js/Pages/SuperAdmin/Users/Form.vue).
     */
    public function edit(User $user, ?Company $company = null)
    {
        return \Inertia\Inertia::render('SuperAdmin/Users/Form', $this->getCommonViewVars($user, $company));
    }

    /**
     * Updates a User. UNCHANGED, deliberately.
     */
    public function update(Request $request, User $user)
    {
        $user->update($request->except('avatar', 'companies'));
        $user->companies()->sync($request->companies);
        @count($user->roles) == 0 ?: $user->removeRole($user->roles[0]->name);

        $user->assignRole($request->role);
        ImageSave::saveIfExist('avatar', $user);

        return redirect()->back();
    }

    public function show($id)
    {
    }

    public function destroy($id)
    {
    }
}
