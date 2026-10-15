<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Permissions\PermissionRegistry;
use Database\Seeders\PermissionSeeder;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * `php artisan permissions:sync`
 * ==================================================================
 * Brings the database in line with PermissionRegistry. Run it after
 * adding a module or an action.
 *
 * Additive by default — it creates what's missing and never revokes.
 * That matters because a plain re-run must not wipe a template an
 * administrator has customised in the Role Management UI.
 *
 * ⚠️ Roles are TEMPLATES here, not authority — permissions are held per
 * user. Nothing this command does to a role changes what any existing
 * user can do; use `permissions:migrate-to-user` for that.
 *
 *   --reset-roles  restore every role to its declared defaults
 *                  (discards customisations; asks first)
 *   --assign-missing-roles
 *                  give every roleless user the `user` role, so the
 *                  Role → Permissions → User chain covers them
 *   --prune        report permissions in the table that the registry
 *                  no longer declares (reports only; never deletes)
 */
class SyncPermissionsCommand extends Command
{
    protected $signature = 'permissions:sync
                            {--reset-roles : Restore all role templates to their declared defaults}
                            {--assign-missing-roles : Give roleless users the default `user` role (a label only)}
                            {--apply-template : With --assign-missing-roles, also copy that role\'s template onto them}
                            {--prune : Report permissions no longer declared in the registry}';

    protected $description = 'Sync roles and permissions from App\\Support\\Permissions\\PermissionRegistry';

    public function handle(): int
    {
        $reset = (bool) $this->option('reset-roles');

        if ($reset && ! $this->confirm('Reset every role to its declared defaults? Custom role permissions will be lost.', false)) {
            $this->warn('Aborted.');

            return self::FAILURE;
        }

        $this->info('Registry declares '.count(PermissionRegistry::keys()).' permission keys across '
            .count(PermissionRegistry::modules()).' modules.');

        $seeder = new PermissionSeeder;
        $seeder->setCommand($this);
        $seeder->run($reset);

        if ($this->option('assign-missing-roles')) {
            $this->assignMissingRoles();
        }

        if ($this->option('prune')) {
            $this->reportOrphans();
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->newLine();
        $this->info('Done.');

        return self::SUCCESS;
    }

    /**
     * Many users in this database have no row in `model_has_roles`.
     *
     * That costs them nothing in access — permissions are per user — but
     * a role is still used as a label and by business logic
     * (User::isSuperAdmin(), the user-creation rules), so a user without
     * one is an odd state worth clearing up.
     *
     * The role alone grants nothing. --apply-template additionally
     * copies that role's permissions onto them, which DOES change what
     * they can do, so it is opt-in and confirmed separately.
     */
    private function assignMissingRoles(): void
    {
        $roleless = User::doesntHave('roles')->get();

        if ($roleless->isEmpty()) {
            $this->info('Every user already has a role.');

            return;
        }

        $this->warn("{$roleless->count()} user(s) have no role.");

        if (! $this->confirm("Assign the '".User::USER."' role to all of them? (a label; grants nothing on its own)", true)) {
            return;
        }

        $applyTemplate = (bool) $this->option('apply-template');

        if ($applyTemplate && ! $this->confirm(
            "Also REPLACE their permissions with the '".User::USER."' template? This changes what they can do.",
            false
        )) {
            $applyTemplate = false;
        }

        foreach ($roleless as $user) {
            $user->assignRole(User::USER);

            if ($applyTemplate) {
                \App\Support\Permissions\RoleTemplate::applyTo($user, User::USER);
            }
        }

        $this->info("Assigned '".User::USER."' to {$roleless->count()} user(s)"
            .($applyTemplate ? ' and applied the template.' : ' (permissions unchanged).'));
    }

    private function reportOrphans(): void
    {
        $declared = array_fill_keys(PermissionRegistry::seedableNames(), true);
        $orphans = Permission::where('guard_name', 'web')
            ->pluck('name')
            ->reject(fn ($name) => isset($declared[$name]))
            ->values();

        if ($orphans->isEmpty()) {
            $this->info('No orphan permissions — the table matches the registry.');

            return;
        }

        $this->newLine();
        $this->warn($orphans->count().' permission(s) exist in the database but are not declared in the registry:');

        foreach ($orphans as $name) {
            $this->line("  - {$name}");
        }

        $this->newLine();
        $this->comment('Nothing was deleted. Users may still hold these; remove them deliberately if they are dead.');
    }
}
