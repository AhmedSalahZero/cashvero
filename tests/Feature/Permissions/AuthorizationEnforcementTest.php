<?php

namespace Tests\Feature\Permissions;

use App\Models\Company;
use App\Models\User;
use App\Support\Permissions\PermissionRegistry;
use App\Support\Permissions\PermissionResolver;
use Illuminate\Support\Facades\DB;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The assertions that matter most: that the SERVER refuses, regardless
 * of what the interface shows.
 *
 * Every case here goes through the real HTTP stack, so it exercises
 * EnforcePermission exactly as a forged request from Postman would.
 * A hidden button proves nothing; a 403 does.
 *
 * This application is USER-BASED: permissions live on the user, and a
 * role is only a template copied at creation. So every helper here
 * grants directly to the user, and there are explicit tests that a
 * role's permissions do NOT leak through.
 *
 * Runs against the development database (read-mostly; the few writes are
 * to a scratch role/user that is removed in tearDown). Point it elsewhere
 * with SMOKE_DB=<name>; it skips itself when the database is unreachable,
 * matching the convention in PaginationSmokeTest.
 */
class AuthorizationEnforcementTest extends TestCase
{
    private ?User $actor = null;

    private ?Company $company = null;

    private ?Role $scratchRole = null;

    private array $createdPermissionIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.mysql.database' => env('SMOKE_DB', 'cash-vero')]);
        DB::purge('mysql');

        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Development database not reachable: '.$e->getMessage());
        }

        // Same reason as PaginationSmokeTest: without this the locale
        // middleware 302s every request before a controller runs, and the
        // suite would pass while exercising nothing. Auth and the
        // permission middleware stay on — they are the subject here.
        $this->withoutMiddleware([
            LocaleSessionRedirect::class,
            LaravelLocalizationRedirectFilter::class,
            LaravelLocalizationViewPath::class,
        ]);

        $this->company = Company::first();

        if (! $this->company) {
            $this->markTestSkipped('Development database has no company to exercise.');
        }

        $this->scratchRole = Role::firstOrCreate(
            ['name' => 'test-scratch-role', 'guard_name' => 'web']
        );

        $this->actor = $this->makeScratchUser();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        PermissionResolver::flush();
    }

    protected function tearDown(): void
    {
        if ($this->actor) {
            DB::table('companies_users')->where('user_id', $this->actor->id)->delete();
            DB::table('model_has_roles')->where('model_id', $this->actor->id)->delete();
            DB::table('model_has_permissions')->where('model_id', $this->actor->id)->delete();
            User::withoutEvents(fn () => User::where('id', $this->actor->id)->forceDelete());
        }

        if ($this->scratchRole) {
            DB::table('role_has_permissions')->where('role_id', $this->scratchRole->id)->delete();
            $this->scratchRole->delete();
        }

        if ($this->createdPermissionIds) {
            Permission::whereIn('id', $this->createdPermissionIds)->delete();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        PermissionResolver::flush();

        parent::tearDown();
    }

    /* ── helpers ───────────────────────────────────────────────────── */

    private function makeScratchUser(): User
    {
        $user = new User;
        $user->name = 'Permissions Test Actor';
        $user->email = 'permissions-test-'.bin2hex(random_bytes(6)).'@example.test';
        $user->password = bcrypt('secret-'.bin2hex(random_bytes(8)));
        $user->save();

        $user->companies()->attach($this->company->id);
        $user->assignRole($this->scratchRole);

        return $user->fresh();
    }

    /**
     * Grant the actor a set of canonical keys DIRECTLY — the only path
     * that confers access in this application.
     *
     * @param  string[]  $keys
     */
    private function grant(array $keys): void
    {
        $this->actor->syncPermissions($this->permissionModels($keys));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        PermissionResolver::flush();
        $this->actor = $this->actor->fresh();
        $this->actor->load('roles.permissions', 'permissions');
    }

    /**
     * Put a set of keys on the scratch ROLE. Used only to prove that
     * doing so grants the actor nothing.
     *
     * @param  string[]  $keys
     */
    private function grantScratchRole(array $keys): void
    {
        $this->scratchRole->syncPermissions($this->permissionModels($keys));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        PermissionResolver::flush();
        $this->actor = $this->actor->fresh();
        $this->actor->load('roles.permissions', 'permissions');
    }

    /**
     * @param  string[]  $keys
     * @return Permission[]
     */
    private function permissionModels(array $keys): array
    {
        $models = [];

        foreach ($keys as $key) {
            $permission = Permission::firstOrCreate(['name' => $key, 'guard_name' => 'web']);

            if ($permission->wasRecentlyCreated) {
                $this->createdPermissionIds[] = $permission->id;
            }

            $models[] = $permission;
        }

        return $models;
    }

    private function url(string $routeName, array $params = []): string
    {
        return route($routeName, array_merge(['company' => $this->company->id], $params));
    }

    /**
     * A real row id for this company, or skip.
     *
     * Route-model binding runs (SubstituteBindings, in the `web` group)
     * BEFORE EnforcePermission, so a request naming a row that does not
     * exist — or belongs to another company — is answered 404 and never
     * reaches the permission check. That is the correct outcome (denied
     * either way, and 404 does not disclose whether the row exists), but
     * it means asserting 403 specifically requires a row that resolves.
     */
    private function existingId(string $table): int
    {
        $id = DB::table($table)->where('company_id', $this->company->id)->value('id');

        if (! $id) {
            $this->markTestSkipped("No {$table} row for company {$this->company->id} to exercise.");
        }

        return (int) $id;
    }

    /* ── Denial ────────────────────────────────────────────────────── */

    public function test_user_without_permission_gets_403_on_a_view_route(): void
    {
        $this->grant([]);

        $this->actingAs($this->actor)
            ->get($this->url('view.money.receive'))
            ->assertForbidden();
    }

    /**
     * The exact scenario the hidden-button model cannot cover: the user
     * can see the list, the Delete button is not rendered for them, and
     * they issue the DELETE anyway.
     */
    public function test_view_permission_does_not_imply_delete(): void
    {
        $this->grant(['money_received.view']);

        $this->actingAs($this->actor)
            ->get($this->url('view.money.receive'))
            ->assertOk();

        $this->actingAs($this->actor)
            ->delete($this->url('delete.money.receive', ['moneyReceived' => $this->existingId('money_received')]))
            ->assertForbidden();
    }

    public function test_create_permission_does_not_imply_update_or_delete(): void
    {
        $this->grant(['cash_expense.view', 'cash_expense.create']);

        $this->actingAs($this->actor)
            ->get($this->url('create.cash.expense'))
            ->assertOk();

        $this->actingAs($this->actor)
            ->delete($this->url('delete.cash.expense', ['cashExpense' => $this->existingId('cash_expenses')]))
            ->assertForbidden();
    }

    public function test_permission_for_one_module_does_not_leak_into_another(): void
    {
        $this->grant(['money_received.view', 'money_received.create', 'money_received.update', 'money_received.delete']);

        // Full rights over receipts must not open expenses.
        $this->actingAs($this->actor)
            ->get($this->url('view.cash.expense'))
            ->assertForbidden();

        $this->actingAs($this->actor)
            ->get($this->url('view.money.payment'))
            ->assertForbidden();
    }

    /* ── Grant ─────────────────────────────────────────────────────── */

    public function test_user_with_permission_reaches_the_page(): void
    {
        $this->grant(['money_received.view']);

        $this->actingAs($this->actor)
            ->get($this->url('view.money.receive'))
            ->assertOk();
    }

    public function test_permission_change_takes_effect_immediately(): void
    {
        $this->grant([]);

        $this->actingAs($this->actor)
            ->get($this->url('view.cash.expense'))
            ->assertForbidden();

        $this->grant(['cash_expense.view']);

        $this->actingAs($this->actor)
            ->get($this->url('view.cash.expense'))
            ->assertOk();
    }

    public function test_permissions_resolve_from_the_users_own_grants(): void
    {
        $this->grant(['report_bank_statement.view']);

        $this->assertTrue(PermissionResolver::allows($this->actor, 'report_bank_statement.view'));
        $this->assertFalse(PermissionResolver::allows($this->actor, 'report_bank_statement.export'));
    }

    /**
     * The defining property of the user-based model: a role carrying a
     * permission grants the holder nothing. Only their own rows count.
     */
    public function test_role_permissions_are_not_inherited(): void
    {
        $this->grant([]);
        $this->grantScratchRole(['report_bank_statement.view', 'money_received.view']);

        $this->assertNotEmpty(
            $this->scratchRole->fresh()->permissions,
            'Precondition: the role really does carry these permissions.'
        );

        $this->assertFalse(
            PermissionResolver::allows($this->actor, 'report_bank_statement.view'),
            'A permission held by the role must NOT reach the user.'
        );

        $this->assertSame([], PermissionResolver::grantedKeys($this->actor));

        $this->actingAs($this->actor)
            ->get($this->url('view.money.receive'))
            ->assertForbidden();
    }

    /**
     * The back door: a LEGACY permission name is not a registry key, so
     * `$user->can('view cash expenses')` used to fall past Gate::before
     * to Spatie's own gate — which unions role and direct grants. That
     * would have reinstated role inheritance for every legacy call site
     * (App\Notification alone has 13) while the dotted keys behaved
     * correctly, i.e. a leak that only showed up on some screens.
     */
    public function test_a_role_held_legacy_name_does_not_leak_through_can(): void
    {
        $legacyName = 'view cash expenses';

        $legacy = Permission::firstOrCreate(['name' => $legacyName, 'guard_name' => 'web']);

        if ($legacy->wasRecentlyCreated) {
            $this->createdPermissionIds[] = $legacy->id;
        }

        $this->grant([]);
        $this->scratchRole->syncPermissions([$legacy]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        PermissionResolver::flush();
        $this->actor = $this->actor->fresh();

        $this->assertFalse(
            $this->actor->can($legacyName),
            "can('{$legacyName}') must not pass on a permission held only by the role."
        );

        $this->assertFalse(PermissionResolver::allows($this->actor, 'cash_expense.view'));

        // And it still passes when the user holds it themselves.
        $this->actor->syncPermissions([$legacy]);
        PermissionResolver::flush();
        $this->actor = $this->actor->fresh();

        $this->assertTrue($this->actor->can($legacyName));
    }

    /**
     * Editing a template must not retroactively change anyone. This is
     * the trade the user-based model makes, so it is asserted rather
     * than merely documented.
     */
    public function test_editing_a_role_does_not_change_an_existing_user(): void
    {
        $this->grant(['money_received.view']);

        $before = PermissionResolver::grantedKeys($this->actor);

        $this->grantScratchRole(PermissionRegistry::keys());

        $this->assertSame(
            $before,
            PermissionResolver::grantedKeys($this->actor),
            'Filling the role with every permission must leave existing users untouched.'
        );
    }

    /**
     * Applying a template copies it onto the user — the one moment a
     * role affects access, and only because it was asked for.
     */
    public function test_applying_a_template_copies_permissions_onto_the_user(): void
    {
        $this->grant([]);
        $this->grantScratchRole(['cash_expense.view', 'cash_expense.create']);

        $this->assertFalse(PermissionResolver::allows($this->actor, 'cash_expense.view'));

        \App\Support\Permissions\RoleTemplate::applyTo($this->actor, $this->scratchRole);

        $this->actor = $this->actor->fresh();
        PermissionResolver::flush();

        $this->assertTrue(PermissionResolver::allows($this->actor, 'cash_expense.view'));
        $this->assertTrue(PermissionResolver::allows($this->actor, 'cash_expense.create'));
        $this->assertFalse(PermissionResolver::allows($this->actor, 'cash_expense.delete'));

        // Copied, not linked: the user now owns these rows outright.
        $this->assertContains('cash_expense.view', $this->actor->permissions->pluck('name')->all());
    }

    /**
     * A legacy grant must keep working. This is the whole reason
     * PermissionRegistry carries legacy aliases: on deploy, every
     * existing user holds natural-language names, not dotted keys.
     */
    public function test_a_legacy_permission_name_still_grants_the_canonical_key(): void
    {
        $legacy = Permission::firstOrCreate(['name' => 'view cash expenses', 'guard_name' => 'web']);

        if ($legacy->wasRecentlyCreated) {
            $this->createdPermissionIds[] = $legacy->id;
        }

        $this->actor->syncPermissions([$legacy]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        PermissionResolver::flush();
        $this->actor = $this->actor->fresh();

        $this->assertTrue(
            PermissionResolver::allows($this->actor, 'cash_expense.view'),
            'A user holding only the legacy name must still pass the canonical key.'
        );

        $this->actingAs($this->actor)
            ->get($this->url('view.cash.expense'))
            ->assertOk();
    }

    /* ── Super Admin ───────────────────────────────────────────────── */

    public function test_super_admin_bypasses_every_check_without_holding_rows(): void
    {
        $this->grant([]);

        $this->actor->syncRoles([User::SUPER_ADMIN]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        PermissionResolver::flush();
        $this->actor = $this->actor->fresh();

        $this->assertTrue(PermissionResolver::isSuperAdmin($this->actor));

        foreach (['money_received.delete', 'role.update', 'company.delete'] as $key) {
            $this->assertTrue(
                PermissionResolver::allows($this->actor, $key),
                "Super Admin must pass {$key} through the centralised bypass"
            );
        }

        $this->assertSame(
            PermissionRegistry::keys(),
            PermissionResolver::grantedKeys($this->actor)
        );
    }

    /**
     * Regression test for the audit's headline finding: Gate::before
     * checked `hasRole('Super-Admin')` while the real role is
     * `super-admin`, so the bypass never fired for anyone.
     */
    public function test_super_admin_role_name_matches_the_gate(): void
    {
        $this->assertSame('super-admin', User::SUPER_ADMIN);
        $this->assertTrue(
            Role::where('name', User::SUPER_ADMIN)->where('guard_name', 'web')->exists(),
            'The super-admin role must exist under the exact name the Gate compares against.'
        );
    }

    /* ── Roleless users ────────────────────────────────────────────── */

    public function test_a_user_with_no_role_is_denied_rather_than_erroring(): void
    {
        $this->actor->syncRoles([]);
        PermissionResolver::flush();
        $this->actor = $this->actor->fresh();

        // Previously `$this->roles->first()->name` fatalled here.
        $this->assertFalse($this->actor->isSuperAdmin());
        $this->assertNull($this->actor->getRoleName());
        $this->assertSame([], PermissionResolver::grantedKeys($this->actor));

        $this->actingAs($this->actor)
            ->get($this->url('view.money.receive'))
            ->assertForbidden();
    }

    /* ── Hardened endpoints ────────────────────────────────────────── */

    public function test_truncate_rejects_a_model_outside_the_whitelist(): void
    {
        $this->grant([
            'customer_invoice_data.bulk_delete',
            'supplier_invoice_data.bulk_delete',
            'loan_schedule_data.bulk_delete',
        ]);

        // Even holding every bulk-delete right, an arbitrary class is 404.
        $this->actingAs($this->actor)
            ->delete($this->url('truncate', ['model' => 'User']))
            ->assertNotFound();
    }

    public function test_truncate_is_no_longer_reachable_by_get(): void
    {
        $this->grant(['customer_invoice_data.bulk_delete']);

        $this->actingAs($this->actor)
            ->get($this->url('truncate', ['model' => 'CustomerInvoice']))
            ->assertStatus(405);
    }

    /**
     * The "mark as reviewed" feature was removed as unused, and with it
     * the `confirmed.review` endpoint that wrote `is_reviewed` /
     * `reviewed_by` from a request-supplied table name. Assert the route
     * is really gone rather than merely unlinked from the UI — an
     * endpoint left live with no button is exactly the shape of the
     * problem this suite exists to catch.
     */
    public function test_the_review_endpoint_no_longer_exists(): void
    {
        $this->assertFalse(
            \Illuminate\Support\Facades\Route::has('confirmed.review'),
            'confirmed.review should have been removed with the review feature.'
        );

        foreach (['money_received.review', 'money_payment.review', 'cash_expense.review'] as $key) {
            $this->assertFalse(
                PermissionRegistry::has($key),
                "{$key} should have been removed with the review feature — a permission "
                .'nothing checks is exactly the dead-permission problem this system fixed.'
            );
        }
    }

    /* ── Cheque / payment lifecycle ────────────────────────────────── */

    /**
     * `change_cheque_status` is a right of its own, not a by-product of
     * `update`. Editing a cheque's figures and advancing the cash
     * through its lifecycle are different acts, and someone who tracks
     * collection should be grantable one without the other.
     */
    public function test_update_does_not_imply_changing_cheque_status(): void
    {
        $this->grant(['money_received.view', 'money_received.update']);

        $this->actingAs($this->actor)
            ->get($this->url('view.money.receive'))
            ->assertOk();

        $this->actingAs($this->actor)
            ->post($this->url('cheque.send.to.collection'))
            ->assertForbidden();
    }

    public function test_marking_as_paid_is_separate_from_update(): void
    {
        $this->grant(['money_payment.view', 'money_payment.update']);

        $this->actingAs($this->actor)
            ->post($this->url('payable.cheque.mark.as.paid'))
            ->assertForbidden();

        $this->grant(['cash_expense.view', 'cash_expense.update']);

        $this->actingAs($this->actor)
            ->post($this->url('cash.expense.payable.cheque.mark.as.paid'))
            ->assertForbidden();
    }

    /**
     * The old `*.settle` keys were written onto users by the user-based
     * migration before they were renamed. They are kept as aliases, so a
     * migrated user must still pass — otherwise the rename would have
     * silently revoked the action from everyone already migrated.
     */
    public function test_the_old_settle_key_still_grants_the_renamed_action(): void
    {
        foreach ([
            'money_received.settle' => 'money_received.change_cheque_status',
            'money_payment.settle' => 'money_payment.mark_as_paid',
            'cash_expense.settle' => 'cash_expense.mark_as_paid',
        ] as $legacy => $key) {
            $this->assertContains($legacy, PermissionRegistry::grantNames($key),
                "{$key} must still accept the pre-rename key {$legacy}"
            );
        }

        $this->grant(['money_received.settle']);

        $this->assertTrue(
            PermissionResolver::allows($this->actor, 'money_received.change_cheque_status'),
            'A user migrated with money_received.settle must keep the renamed action.'
        );
    }

    /* ── Role management ───────────────────────────────────────────── */

    public function test_role_management_is_denied_without_the_role_permission(): void
    {
        $this->grant(['money_received.view']);

        $this->actingAs($this->actor)->get($this->url('roles.index'))->assertForbidden();
        $this->actingAs($this->actor)->get($this->url('roles.create'))->assertForbidden();
    }

    public function test_role_management_is_reachable_with_the_role_permission(): void
    {
        $this->grant(['role.view']);

        $this->actingAs($this->actor)->get($this->url('roles.index'))->assertOk();
    }

    /**
     * The escalation route that would otherwise defeat the entire system:
     * someone with role.update building a role that holds permissions they
     * do not, then assigning it to themselves.
     */
    public function test_an_editor_cannot_grant_a_permission_they_do_not_hold(): void
    {
        $this->grant(['role.view', 'role.update', 'money_received.view']);

        $target = Role::firstOrCreate(['name' => 'test-escalation-target', 'guard_name' => 'web']);

        try {
            $this->actingAs($this->actor)->put(
                $this->url('roles.update', ['role' => $target->id]),
                [
                    // `name` is required for a non-built-in role; omitting it
                    // fails validation and nothing would be synced at all.
                    'name' => 'test-escalation-target',
                    'permissions' => ['money_received.view', 'money_received.delete', 'company.delete'],
                ]
            );

            $granted = $target->fresh()->permissions->pluck('name')->all();

            $this->assertContains('money_received.view', $granted,
                'A permission the editor holds should be granted.');
            $this->assertNotContains('money_received.delete', $granted,
                'An editor must not grant a permission they do not hold.');
            $this->assertNotContains('company.delete', $granted,
                'An editor must not grant an administration permission they do not hold.');
        } finally {
            DB::table('role_has_permissions')->where('role_id', $target->id)->delete();
            $target->delete();
        }
    }

    /* ── Shared props ──────────────────────────────────────────────── */

    public function test_granted_keys_contain_only_declared_registry_keys(): void
    {
        $this->grant(['money_received.view', 'cash_expense.view']);

        $keys = PermissionResolver::grantedKeys($this->actor);

        foreach ($keys as $key) {
            $this->assertTrue(PermissionRegistry::has($key),
                "grantedKeys() returned {$key}, which the registry does not declare"
            );
        }

        $this->assertContains('money_received.view', $keys);
        $this->assertContains('cash_expense.view', $keys);
        $this->assertNotContains('money_received.delete', $keys);
    }
}
