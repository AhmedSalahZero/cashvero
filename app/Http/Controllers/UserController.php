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
    private const ROWS_PER_PAGE = 20;

    use ImageSave;

    public function __construct()
    {
        $this->middleware(['can:view users'])->only(['index']);
    }

    /**
     * ✅ MIGRATED to Vue + Inertia. Renders
     * resources/js/Pages/SuperAdmin/Users/Index.vue.
     */
    public function index(Request $request, ?Company $company = null)
    {
        /*
         * Paginated: without a company in the URL this is every user in
         * the system, and it grows with companies x users. The role
         * visibility gates in getUsersWithRolesQuery() are all whereHas
         * clauses, so they still apply across the whole set rather than
         * to the page on screen. Name search runs in SQL for the same
         * reason.
         */
        $search = trim((string) $request->get('search', ''));

        $users = User::getUsersWithRolesQuery($company)
            ->when($search !== '', fn ($q) => $q->where('users.name', 'like', '%'.$search.'%'))
            ->paginate(self::ROWS_PER_PAGE)
            ->withQueryString()
            ->through(function (User $u) use ($company) {
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'avatar_url' => $u->getFirstMediaUrl() ?: null,
                    'role_name' => $u->roles[0]->name ?? '-',
                    'companies' => $u->companies->map(fn ($c) => $c->name['en'] ?? ($c->name[array_key_first($c->name ?? ['' => ''])] ?? ''))->values(),
                    'edit_url' => $company ? route('user.edit', ['user' => $u->id, 'company' => $company->id]) : route('user.edit', ['user' => $u->id]),
                    'permissions_url' => $company ? route('user.permissions.edit', ['user' => $u->id, 'company' => $company->id]) : route('user.permissions.edit', ['user' => $u->id]),
                ];
            });

        return \Inertia\Inertia::render('SuperAdmin/Users/Index', [
            'company' => $company ? ['id' => $company->id] : null,
            'createUrl' => $company ? route('user.create', ['company' => $company->id]) : route('user.create'),
            'paginator' => $users->toArray(),
            'search' => $search,
            'indexUrl' => $company ? route('user.index', ['company' => $company->id]) : route('user.index'),
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

        /**
         * Custom roles created in Role Management. Assigning one is
         * gated by `user.assign_roles`, plus a rule that closes the
         * obvious escalation route: you may not hand someone a role
         * carrying permissions you do not hold yourself, otherwise
         * anyone able to assign roles could mint themselves an admin
         * via a custom role. (RoleController applies the mirror-image
         * rule when the role is built.)
         */
        if (! $authUser->hasPermissionKey('user.assign_roles')) {
            return $existingUser && $existingUser->hasRoleName($role);
        }

        if (\App\Support\Permissions\PermissionResolver::isSuperAdmin($authUser)) {
            return true;
        }

        $roleModel = \Spatie\Permission\Models\Role::where('name', $role)->where('guard_name', 'web')->first();

        if (! $roleModel) {
            return false;
        }

        $held = array_fill_keys(\App\Support\Permissions\PermissionResolver::grantedKeys($authUser), true);

        foreach ($roleModel->permissions->pluck('name') as $permissionName) {
            if (! isset($held[$permissionName])) {
                return $existingUser && $existingUser->hasRoleName($role);
            }
        }

        return true;
    }

    /**
     * Company IDs the acting user may assign. null = unrestricted (Super Admin).
     *
     * @return list<int>|null
     */
    protected function authUserAssignableCompanyIds(User $authUser): ?array
    {
        if ($authUser->isSuperAdmin()) {
            return null;
        }

        return $authUser->companies->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
    }

    /**
     * Owner policy (2026-07-26): only Super Admin freely picks any company.
     * Non–Super Admin is limited to their own companies; with a single company
     * they cannot change the target user's company assignment at all.
     */
    protected function authUserCanEditCompanyAssignment(User $authUser): bool
    {
        if ($authUser->isSuperAdmin()) {
            return true;
        }

        return $authUser->companies->count() > 1;
    }

    /**
     * Resolve the company IDs that will actually be written on store/update.
     *
     * @param  array<int|string>|null  $submitted
     * @return list<int>
     */
    protected function resolveCompanyIdsForWrite(User $authUser, ?array $submitted, ?User $existingUser): array
    {
        $assignable = $this->authUserAssignableCompanyIds($authUser);
        $submittedIds = array_values(array_unique(array_map('intval', (array) $submitted)));

        // Super Admin: any submitted set (still require at least one).
        if ($assignable === null) {
            return $submittedIds;
        }

        // Single-company non–Super Admin: cannot change assignment.
        if (! $this->authUserCanEditCompanyAssignment($authUser)) {
            if ($existingUser) {
                return $existingUser->companies->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
            }

            return $assignable;
        }

        // Multi-company non–Super Admin: only within their own companies.
        $forbidden = array_diff($submittedIds, $assignable);
        abort_unless($forbidden === [], 403, __('You can only assign companies you belong to.'));

        return array_values(array_intersect($submittedIds, $assignable));
    }

    protected function getCommonViewVars(?User $user, ?Company $company): array
    {
        $authUser = auth()->user();
        $assignableIds = $this->authUserAssignableCompanyIds($authUser);
        $canEditCompanies = $this->authUserCanEditCompanyAssignment($authUser);

        if ($company) {
            abort_unless(
                $assignableIds === null || in_array((int) $company->id, $assignableIds, true),
                403
            );
            $companies = Company::where('id', $company->id)->get();
        } elseif ($assignableIds === null) {
            $companies = Company::all();
        } else {
            $companies = Company::whereIn('id', $assignableIds)->get();
        }

        /**
         * Role options are read from the roles table rather than a
         * hardcoded list, so a role created in Role Management becomes
         * assignable here immediately — that is the whole point of
         * Role → Permissions → User being configurable.
         *
         * authUserCanAssignRole() still decides which of them THIS user
         * may hand out, and store()/update() re-check it, so widening
         * the list does not widen anyone's authority.
         */
        $roleOptions = [];
        foreach (\Spatie\Permission\Models\Role::where('guard_name', 'web')->orderBy('id')->get() as $role) {
            if ($this->authUserCanAssignRole($authUser, $role->name, $user)) {
                $roleOptions[] = [
                    'value' => $role->name,
                    'label' => ucwords(str_replace('-', ' ', $role->name)),
                ];
            }
        }

        $defaultCompanyIds = $user
            ? $user->companies->pluck('id')->map(fn ($id) => (int) $id)->values()->all()
            : ($canEditCompanies ? [] : $companies->pluck('id')->map(fn ($id) => (int) $id)->values()->all());

        return [
            'mode' => $user ? 'edit' : 'create',
            'company' => $company ? ['id' => $company->id] : null,
            'model' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->getFirstMediaUrl() ?: null,
                'max_users' => $user->max_users ?? 10,
                'company_ids' => $defaultCompanyIds,
                'role' => $user->roles[0]->name ?? '',
            ] : [
                'company_ids' => $defaultCompanyIds,
            ],
            'companies' => $companies->map(fn (Company $c) => ['id' => $c->id, 'name' => $c->name['en'] ?? ($c->name[array_key_first($c->name ?? ['' => ''])] ?? '')])->values(),
            'canEditCompanies' => $canEditCompanies,
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
     *
     * Company assignment scoped 2026-07-26 (owner policy): Super Admin
     * only may assign any company; others are limited to their own
     * companies, and a single-company admin cannot change assignment.
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
        $companyIds = $this->resolveCompanyIdsForWrite($user, $request->input('companies'), null);
        abort_unless(count($companyIds) > 0, 422, __('Select at least one company.'));
        $request['password'] = Hash::make($request->password);
        $request['subscription'] = 'subscripted';

        $newUser = User::create(
            array_merge(
                $request->except('avatar', 'companies'),
                ['created_by' => auth()->user()->id]
            ),
        );
        $newUser->companies()->attach($companyIds);
        $newUser->assignRole($request->role);

        app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        app()->make(\Spatie\Permission\PermissionRegistrar::class)->clearClassPermissions();

        /**
         * Seed the new user's OWN permissions from the role they were
         * given. Permissions in this application are user-based — the
         * role is a template copied once, here, and never consulted
         * again (see App\Support\Permissions\PermissionResolver).
         *
         * Replaces the previous loop over HAuth::getPermissions(), which
         * copied a fixed, code-level default list. Reading the role's
         * actual rows instead means whatever an admin has configured in
         * Role Management is what a new user of that role starts with.
         *
         * Passing the acting user applies the escalation guard: nobody
         * can seed a new account with more than they hold themselves.
         */
        \App\Support\Permissions\RoleTemplate::applyTo($newUser, $request->role, auth()->user());

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
     * Company assignment scoped 2026-07-26 (owner policy): Super Admin
     * only may assign any company; others are limited to their own
     * companies, and a single-company admin cannot change assignment
     * (existing pivot rows are preserved).
     */
    public function update(Request $request, User $user)
    {
        $authUser = auth()->user();
        $request->validate([
            'role' => 'required',
        ]);
        abort_unless($this->authUserCanAssignRole($authUser, $request->input('role'), $user), 403);

        $companyIds = $this->resolveCompanyIdsForWrite($authUser, $request->input('companies'), $user);
        abort_unless(count($companyIds) > 0, 422, __('Select at least one company.'));

        $previousRole = $user->getRoleName();

        $user->update($request->except('avatar', 'companies', 'reset_permissions_to_role'));
        $user->companies()->sync($companyIds);
        @count($user->roles) == 0 ?: $user->removeRole($user->roles[0]->name);

        $user->assignRole($request->role);

        /**
         * Changing the role does NOT rewrite this user's permissions.
         *
         * Permissions here are user-based: the role was a template
         * copied at creation, and this person's set may have been tuned
         * since. Silently re-applying a template on every save would
         * discard that tuning — the exact surprise this model exists to
         * avoid.
         *
         * The form offers an explicit "reset permissions to the new
         * role's template" checkbox for when that IS what's wanted; it
         * is opt-in, and only meaningful when the role actually changed.
         */
        if ($request->boolean('reset_permissions_to_role') && $previousRole !== $request->input('role')) {
            \App\Support\Permissions\RoleTemplate::applyTo($user, $request->input('role'), $authUser);
        }

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
