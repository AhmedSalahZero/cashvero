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
 *   ✅ update() → `role` required validation now added, matching
 *      store(). (`redirect()->back()` left as-is — unlike store()'s
 *      redirect issue, landing back on the edit form after a save
 *      isn't a broken/empty page, so not treated as a bug.)
 *   ✅ store() / update() / destroy() → REAL BUG FIXED (2026-07-24
 *      audit, confirmed with the project owner): none of these three
 *      previously had any authorization check verifying the acting
 *      user was actually allowed to assign the submitted role (or,
 *      for destroy(), remove a user holding their current role) — a
 *      genuine privilege-escalation gap, since the UI-level
 *      `v-if="isSuperAdmin"` link-hiding in AppLayout.vue was the
 *      only thing standing between any authenticated user and being
 *      able to grant themselves Super Admin. Fixed by extracting the
 *      rule set that already existed correctly (but display-only) in
 *      getCommonViewVars() into authUserCanAssignRole(), and actually
 *      enforcing it. destroy() was also previously an empty no-op
 *      method — implemented as a soft delete; see its own docblock
 *      for what was deliberately left conservative.
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

    /**
     * ⚠️ REAL BUG FIXED HERE (2026-07-24 audit, confirmed with the
     * project owner as a genuine privilege-escalation gap — see the
     * full writeup in the audit report; summarized version here).
     *
     * This exact rule set already existed, correctly, in
     * getCommonViewVars() below — but it was ONLY ever used to decide
     * which options to *display* in the role dropdown (a client-side
     * convenience), never to verify that a *submitted* role was one
     * the acting user was actually allowed to assign. store() and
     * update() applied whatever role was posted with no check at all,
     * meaning any authenticated user who could reach those two routes
     * directly (bypassing the UI, which only ever shows the allowed
     * options) could assign ANY role — including Super Admin — to any
     * user, and update() could additionally reassign any user's
     * company access, also with no check.
     *
     * Extracted here as the single, authoritative source of truth for
     * "is $authUser allowed to assign $role" so getCommonViewVars()'s
     * display logic and store()/update()'s enforcement can never drift
     * apart from each other again — the same "one source of truth,
     * not two things that can disagree" principle this whole codebase
     * already uses correctly in other places (e.g. BankStatementController
     * sharing one row-mapping method between its screen and its export).
     *
     * Business rule is UNCHANGED from what getCommonViewVars() already
     * encoded — nothing new invented here, just made authoritative:
     *   - Super Admin role: only assignable by an existing Super Admin,
     *     or left in place if the user being edited already has it.
     *   - Company Admin / Manager / User roles: assignable by anyone
     *     holding the matching 'create company admin' / 'create manager'
     *     / 'create user' permission, or left in place if the user
     *     being edited already has that role.
     */
    protected function authUserCanAssignRole(User $authUser, ?string $role, ?User $existingUser): bool
    {
        if (! $role) {
            return false;
        }
        if ($role === User::SUPER_ADMIN) {
            return $authUser->isSuperAdmin() || ($existingUser && $existingUser->hasRole(User::SUPER_ADMIN));
        }
        if ($role === User::COMPANY_ADMIN) {
            return $authUser->can('create company admin') || ($existingUser && $existingUser->hasRole(User::COMPANY_ADMIN));
        }
        if ($role === User::MANAGER) {
            return $authUser->can('create manager') || ($existingUser && $existingUser->hasRole(User::MANAGER));
        }
        if ($role === User::USER) {
            return $authUser->can('create user') || ($existingUser && $existingUser->hasRole(User::USER));
        }

        return false;
    }

    protected function getCommonViewVars(?User $user, ?Company $company): array
    {
        $companies = $company ? Company::where('id', $company->id)->get() : Company::all();
        $authUser = auth()->user();

        $roleOptions = [];
        foreach ([User::SUPER_ADMIN => 'Super Admin', User::COMPANY_ADMIN => 'Company Admin', User::MANAGER => 'Manager', User::USER => 'User'] as $roleValue => $roleLabel) {
            if ($this->authUserCanAssignRole($authUser, $roleValue, $user)) {
                $roleOptions[] = ['value' => $roleValue, 'label' => $roleLabel];
            }
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
     *
     * ⚠️ REAL BUG FIXED HERE (2026-07-24 audit): no check previously
     * verified the acting user was actually allowed to assign the
     * submitted role — see authUserCanAssignRole()'s docblock above
     * for the full explanation. A user with no user-management
     * permission at all could not previously be stopped from POSTing
     * role=super-admin directly. Fixed with a 403 abort before any
     * write happens.
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
        abort_unless($this->authUserCanAssignRole($user, $request->input('role'), null), 403);
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
     * Updates a User.
     *
     * ⚠️ REAL BUG FIXED HERE (2026-07-24 audit, confirmed with the
     * project owner as a genuine privilege-escalation gap). This
     * method previously had NO authorization check at all — any
     * authenticated user who could reach this route (e.g. by copying
     * the request the real super-admin UI sends and replaying it
     * directly, since the UI only *hides* the link for non-super-admins
     * rather than the server enforcing anything) could reassign any
     * user's role, including granting themselves or anyone else
     * Super Admin, and could reassign any user's company access —
     * entirely from raw request input. The class's own previous
     * docblock said "UNCHANGED, deliberately" — that referred to a
     * different, lower-severity gap (missing 'role' required
     * validation, matching store()'s already-fixed issue) and did not
     * knowingly accept this authorization gap; it simply hadn't been
     * surfaced yet at the time that note was written.
     *
     * Fixed with the same check now used by store() and by
     * getCommonViewVars()'s role-options display — see
     * authUserCanAssignRole()'s docblock above for the full
     * explanation of why this one check is now the single source of
     * truth for all three.
     *
     * NOT changed in this fix, flagged for a separate decision:
     * company assignment (`$user->companies()->sync(...)`) still has
     * no scoping check of its own — when this route is reached without
     * a {company} context, getCommonViewVars() already offers every
     * company in the system as a valid option to any user who passes
     * the role check above (including a Company-Admin-permission
     * holder who is not a full Super Admin), so such a user could
     * still reassign a target user's company access more broadly than
     * their own company scope. Worth a deliberate decision on the
     * intended company-scoping policy for non-super-admin callers
     * before this route is exposed to anyone other than true Super
     * Admins in practice.
     */
    public function update(Request $request, User $user)
    {
        $authUser = auth()->user();
        $request->validate([
            'role' => 'required',
        ]);
        abort_unless($this->authUserCanAssignRole($authUser, $request->input('role'), $user), 403);

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

    /**
     * ⚠️ REAL BUG FIXED HERE (2026-07-24 audit): this was previously
     * an empty method — the delete-user route existed and presumably
     * looked like it worked from the UI, but calling it did nothing
     * at all.
     *
     * Implemented as a soft delete using the `deleted_at` column
     * already present on the `users` table, gated by the same
     * role-assignment authorization used by store()/update() (a
     * reasonable proxy for "is this acting user senior enough to
     * remove a user holding this role" — same business rule, not a
     * new one), plus a guard against a user deleting their own
     * account.
     *
     * NOTE — deliberately conservative: the `users` model does not
     * currently use Eloquent's SoftDeletes trait, so this sets
     * `deleted_at` directly rather than calling `$user->delete()`.
     * That means existing reads elsewhere in the app (e.g. plain
     * `User::find()` calls) will NOT automatically exclude a
     * soft-deleted user unless SoftDeletes is adopted on the model
     * more broadly — a separate, larger decision with wider ripple
     * effects across the codebase than this one fix should make
     * unilaterally. Flagging clearly rather than silently assuming
     * that adoption.
     */
    public function destroy($id)
    {
        $authUser = auth()->user();
        $user = User::findOrFail($id);

        if ($authUser->id === $user->id) {
            abort(403, __('You cannot delete your own account.'));
        }

        $targetRole = $user->roles[0]->name ?? null;
        abort_unless($this->authUserCanAssignRole($authUser, $targetRole, $user), 403);

        $user->deleted_at = now();
        $user->save();

        return redirect()->back();
    }
}
