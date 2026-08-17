<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\Permissions\PermissionRegistry;
use App\Support\Permissions\PermissionResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * `php artisan permissions:migrate-to-user`
 * ==================================================================
 * One-time migration from role-derived permissions to user-owned ones.
 *
 * Authorization now reads ONLY a user's own rows
 * (App\Support\Permissions\PermissionResolver). Anyone who was relying
 * on their role for access would lose it the moment that change ships.
 *
 * This command prevents that: for every user it computes what they can
 * do TODAY — the union of their role's permissions and their own — and
 * writes that union directly onto the user. Nobody gains anything,
 * nobody loses anything, and from then on each user is edited
 * individually.
 *
 * Dry-run by default. Nothing is written until `--force` is passed.
 *
 *   --force        actually write the permissions
 *   --only=id,id   restrict to specific user ids
 *   --skip-super-admins
 *                  leave Super Admins alone (they bypass every check
 *                  anyway, so materialising rows for them is noise)
 */
class MigratePermissionsToUsersCommand extends Command
{
    protected $signature = 'permissions:migrate-to-user
                            {--force : Write the changes (otherwise this is a dry run)}
                            {--only= : Comma-separated user ids to migrate}
                            {--skip-super-admins : Leave Super Admin users untouched}';

    protected $description = 'Materialise each user\'s effective permissions onto the user, for the move from role-based to user-based access';

    public function handle(): int
    {
        $write = (bool) $this->option('force');
        $skipSupers = (bool) $this->option('skip-super-admins');

        $query = User::with(['roles.permissions', 'permissions']);

        if ($only = $this->option('only')) {
            $ids = array_filter(array_map('trim', explode(',', $only)));
            $query->whereIn('id', $ids);
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->warn('No users matched.');

            return self::SUCCESS;
        }

        if (! $this->guardAgainstSeededTemplates($write)) {
            return self::FAILURE;
        }

        $this->info($write
            ? "Migrating {$users->count()} user(s) to user-owned permissions."
            : "DRY RUN — {$users->count()} user(s) would be migrated. Re-run with --force to apply.");
        $this->newLine();

        $rows = [];
        $totalAdded = 0;

        foreach ($users as $user) {
            $isSuper = PermissionResolver::isSuperAdmin($user);

            if ($isSuper && $skipSupers) {
                $rows[] = [$user->id, $user->name, $user->getRoleName() ?? '-', '—', '—', 'skipped (super admin)'];

                continue;
            }

            $direct = $user->permissions->pluck('name');

            $fromRole = $user->roles
                ->flatMap(fn ($role) => $role->permissions->pluck('name'))
                ->unique();

            /**
             * A Super Admin passes everything through Gate::before, so
             * their effective set is the whole registry regardless of
             * what rows exist. Materialising that keeps the permission
             * screen honest about what the account can do.
             */
            $effective = $isSuper
                ? collect(PermissionRegistry::keys())
                : $direct->merge($fromRole)->unique();

            /**
             * Expand a set of stored permission NAMES into the canonical
             * keys they grant. Most stored names are legacy
             * natural-language ones ('delete cash expenses'), so this is
             * where 183 stored names become the ~314 keys they actually
             * authorise — that growth is vocabulary, not new access.
             */
            $expand = fn ($names) => collect(PermissionRegistry::keys())
                ->filter(function ($key) use ($names) {
                    foreach (PermissionRegistry::grantNames($key) as $name) {
                        if ($names->contains($name)) {
                            return true;
                        }
                    }

                    return false;
                });

            $final = $isSuper
                ? collect(PermissionRegistry::keys())
                : $expand($effective)->values();

            // What the user already had on their own, in canonical terms.
            // The difference against $final is the access that genuinely
            // came from the role and would otherwise have been lost.
            $ownAlready = $isSuper ? $final : $expand($direct);
            $gainedFromRole = $final->diff($ownAlready)->count();
            $totalAdded += $gainedFromRole;

            $note = match (true) {
                $isSuper => 'super admin → full set',
                $gainedFromRole > 0 => "+{$gainedFromRole} kept from role",
                default => 'no role-derived access',
            };

            $rows[] = [
                $user->id,
                \Illuminate\Support\Str::limit($user->name, 24),
                $user->getRoleName() ?? '-',
                $direct->count(),
                $final->count(),
                $note,
            ];

            if ($write) {
                $this->ensurePermissionsExist($final->all());
                $user->syncPermissions($final->all());
            }
        }

        $this->table(
            ['ID', 'Name', 'Role', 'Direct before', 'Owned after', 'Change'],
            $rows
        );

        if ($write) {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            PermissionResolver::flush();

            $this->newLine();
            $this->info("Done. {$totalAdded} permission grant(s) materialised onto users.");
            $this->comment('Roles are now templates only — editing a role no longer changes any existing user.');
        } else {
            $this->newLine();
            $this->warn('Nothing was written. Re-run with --force to apply.');
        }

        return self::SUCCESS;
    }

    /**
     * ⚠️ ORDER-OF-OPERATIONS GUARD.
     *
     * This command preserves each user's access by materialising
     * `direct ∪ role`. That is correct only while roles still hold
     * whatever they held before the move to user-based permissions —
     * in this application, nothing (`role_has_permissions` was empty).
     *
     * Run `permissions:sync` FIRST and the roles get filled with their
     * default templates; this command would then copy those templates
     * onto every user, silently GRANTING access nobody had. A user with
     * a `user` role and no direct permissions today would gain the
     * entire read-only template.
     *
     * So: migrate first, sync second. If templates are already
     * populated, say so plainly and make the operator confirm.
     */
    private function guardAgainstSeededTemplates(bool $write): bool
    {
        $seeded = DB::table('role_has_permissions')->count();

        if ($seeded === 0) {
            return true;
        }

        $this->newLine();
        $this->warn('⚠  Role templates are already populated ('.$seeded.' role-permission rows).');
        $this->line('   This command materialises  direct ∪ role  onto each user, so those template');
        $this->line('   permissions WILL be granted to the users holding those roles — which may be');
        $this->line('   more access than they have today.');
        $this->newLine();
        $this->line('   The intended order is:');
        $this->line('     1. permissions:migrate-to-user --force   (while templates are still empty)');
        $this->line('     2. permissions:sync                      (fill templates for future users)');
        $this->newLine();

        if (! $write) {
            $this->comment('   Dry run — the table below already reflects that, so read it carefully.');

            return true;
        }

        return $this->confirm('Continue and grant role-template permissions to these users?', false);
    }

    /**
     * @param  string[]  $names
     */
    private function ensurePermissionsExist(array $names): void
    {
        if ($names === []) {
            return;
        }

        $existing = Permission::where('guard_name', 'web')
            ->whereIn('name', $names)
            ->pluck('name')
            ->flip();

        foreach ($names as $name) {
            if (! $existing->has($name)) {
                Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            }
        }
    }
}
