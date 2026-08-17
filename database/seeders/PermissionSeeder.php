<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Permissions\PermissionRegistry;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * PermissionSeeder
 * ==================================================================
 * Creates every permission declared by PermissionRegistry and fills in
 * each role's default TEMPLATE.
 *
 * ⚠️ A role's permissions are a template, not authority. This
 * application is user-based: what a person can do comes from their own
 * rows, and a template is only copied onto them at creation (or when an
 * admin applies it). Seeding a role therefore changes what FUTURE users
 * start with and nothing about anyone who already exists.
 *
 * Safe to run repeatedly:
 *   • permissions are created with firstOrCreate — never duplicated;
 *   • roles are created with firstOrCreate;
 *   • role permissions are ADDED, never synced away, so a set of
 *     rights an administrator has customised in the Role Management UI
 *     survives a re-run. Use `permissions:sync --reset-roles` when you
 *     genuinely want the defaults restored.
 *
 * Nothing is ever deleted here. The pre-existing
 * `RefreshAllUsersToDefaultPermissions` command wipes all permissions
 * and all grants system-wide; this seeder deliberately does not.
 */
class PermissionSeeder extends Seeder
{
    /** Roles that exist in this application, widest first. */
    private const ROLES = [
        User::SUPER_ADMIN,
        User::COMPANY_ADMIN,
        User::MANAGER,
        User::USER,
    ];

    public function run(bool $resetRoles = false): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->createPermissions();
        $this->createRoles();
        $this->assignRoleDefaults($resetRoles);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Every canonical key plus every legacy alias still referenced by
     * the registry. Legacy rows are kept because live user grants point
     * at them — dropping them would revoke access.
     */
    private function createPermissions(): void
    {
        $existing = Permission::where('guard_name', 'web')->pluck('name')->flip();
        $created = 0;

        foreach (PermissionRegistry::seedableNames() as $name) {
            if ($existing->has($name)) {
                continue;
            }

            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            $created++;
        }

        $this->command?->info("Permissions: {$created} created, {$existing->count()} already present.");
    }

    private function createRoles(): void
    {
        foreach (self::ROLES as $roleName) {
            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
        }
    }

    /**
     * Give each role its default template.
     *
     * Defaults come from PermissionRegistry::ROLE_DEFAULTS — see the
     * note there on why they are NOT derived from HAuth's own
     * `default-roles` (those grant almost everything to every role and
     * would leave the four roles indistinguishable).
     */
    private function assignRoleDefaults(bool $reset): void
    {
        foreach (self::ROLES as $roleName) {
            /** @var Role $role */
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            if (! $role) {
                continue;
            }

            $keys = PermissionRegistry::defaultKeysForRole($roleName);

            if ($reset) {
                $role->syncPermissions($keys);
                $this->command?->info("Role {$roleName}: reset to ".count($keys).' permissions.');

                continue;
            }

            $already = $role->permissions->pluck('name')->flip();
            $toAdd = array_values(array_filter($keys, fn ($k) => ! $already->has($k)));

            if ($toAdd) {
                $role->givePermissionTo($toAdd);
            }

            $this->command?->info("Role {$roleName}: ".count($toAdd).' added, '.$already->count().' kept.');
        }
    }

}
