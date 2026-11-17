<?php

namespace Tests\Feature\Permissions;

use App\Models\User;
use App\Support\Permissions\PermissionRegistry;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * `permissions:sync` runs on every deploy (deploy.sh). It must keep an administrator's edits to a
 * role template — removals included — and still hand the defaults of a NEW permission to the roles
 * that declare it.
 *
 * Regression: it used to add back every default missing from a role, so a default an administrator
 * had removed in Role Management came back on the next deploy.
 *
 * Defaults used here (PermissionRegistry::ROLE_DEFAULTS): every built-in role carries
 * cash_expense.view; only company-admin carries cash_expense.delete.
 */
class PermissionSyncRespectsRoleEditsTest extends TestCase
{
    private function role(string $name): Role
    {
        return Role::findByName($name, 'web')->fresh();
    }

    /** @return string[] */
    private function roleKeys(string $name): array
    {
        return $this->role($name)->permissions->pluck('name')->sort()->values()->all();
    }

    private function sync(array $options = []): void
    {
        $this->artisan('permissions:sync', $options)->assertSuccessful();
        $this->forgetPermissions();
    }

    public function test_a_default_removed_from_an_existing_role_stays_removed(): void
    {
        $this->sync();
        $this->role(User::USER)->revokePermissionTo('cash_expense.view');
        $this->forgetPermissions();

        $this->sync();

        $this->assertNotContains('cash_expense.view', $this->roleKeys(User::USER));
    }

    public function test_a_permission_added_to_a_role_stays(): void
    {
        $this->sync();
        $this->role(User::USER)->givePermissionTo('cash_expense.delete');
        $this->forgetPermissions();

        $this->sync();

        $this->assertContains('cash_expense.delete', $this->roleKeys(User::USER));
    }

    public function test_a_new_permission_reaches_only_the_roles_whose_defaults_include_it(): void
    {
        $this->sync();
        // Make cash_expense.delete "new": with its row gone, the next sync creates it again.
        Permission::findByName('cash_expense.delete', 'web')->delete();
        $this->forgetPermissions();

        $this->sync();

        $this->assertTrue(Permission::where('name', 'cash_expense.delete')->where('guard_name', 'web')->exists());
        $this->assertContains('cash_expense.delete', $this->roleKeys(User::COMPANY_ADMIN));
        $this->assertNotContains('cash_expense.delete', $this->roleKeys(User::MANAGER));
        $this->assertNotContains('cash_expense.delete', $this->roleKeys(User::USER));
    }

    public function test_a_role_the_run_creates_starts_from_its_full_template(): void
    {
        $this->role(User::MANAGER)->delete();
        $this->forgetPermissions();

        $this->sync();

        $expected = collect(PermissionRegistry::defaultKeysForRole(User::MANAGER))->sort()->values()->all();
        $this->assertSame($expected, $this->roleKeys(User::MANAGER));
    }

    public function test_reset_roles_still_restores_the_defaults(): void
    {
        $this->sync();
        $this->role(User::USER)->revokePermissionTo('cash_expense.view');
        $this->forgetPermissions();

        $this->artisan('permissions:sync', ['--reset-roles' => true])
            ->expectsConfirmation('Reset every role to its declared defaults? Custom role permissions will be lost.', 'yes')
            ->assertSuccessful();
        $this->forgetPermissions();

        $this->assertContains('cash_expense.view', $this->roleKeys(User::USER));
    }

    public function test_no_users_own_permissions_change(): void
    {
        $user = $this->makeUser(['cash_expense.view'], null, User::USER);
        Permission::findByName('cash_expense.delete', 'web')->delete();
        $this->role(User::USER)->revokePermissionTo('report_bank_statement.view');
        $this->forgetPermissions();

        $this->sync();

        $this->assertSame(['cash_expense.view'], $user->fresh()->permissions->pluck('name')->all());
    }
}
