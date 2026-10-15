<?php

namespace App\Support\Permissions;

use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * RoleTemplate
 * ==================================================================
 * A role in this application is a TEMPLATE, not a live source of
 * authority. This class is the one place that copies a template's
 * permissions onto a user.
 *
 * Because permissions are user-based (see PermissionResolver), the copy
 * is a one-time event:
 *
 *   • at user creation, from the role picked on the form;
 *   • whenever an admin explicitly presses "Apply template" on the
 *     user's permission screen.
 *
 * It never happens implicitly afterwards. Editing a role later changes
 * what FUTURE users start with; it changes nothing for anyone who
 * already exists. That is the trade this model makes, and it is why
 * both UIs say so in as many words.
 */
class RoleTemplate
{
    /**
     * The canonical permission keys a template carries.
     *
     * Reads the role's actual rows rather than
     * PermissionRegistry::defaultKeysForRole(), so a template an admin
     * has customised in Role Management is what actually gets applied —
     * the declared defaults are only the starting point a fresh install
     * is seeded with.
     *
     * @return string[]
     */
    public static function keysFor(Role|string|null $role): array
    {
        if ($role === null) {
            return [];
        }

        if (is_string($role)) {
            $role = Role::where('name', $role)->where('guard_name', 'web')->first();

            if (! $role) {
                return [];
            }
        }

        // Super Admin passes everything through the Gate bypass, so its
        // template is the full set whether or not rows exist for it.
        if ($role->name === User::SUPER_ADMIN) {
            return PermissionRegistry::keys();
        }

        $declared = array_fill_keys(PermissionRegistry::keys(), true);

        return $role->permissions
            ->pluck('name')
            ->filter(fn ($name) => isset($declared[$name]))
            ->values()
            ->all();
    }

    /**
     * Copy a template onto a user.
     *
     * @param  User       $user     the user receiving the permissions
     * @param  Role|string|null $role the template to copy
     * @param  User|null  $actor    who is doing it; when set, they cannot
     *                              grant a permission they do not hold
     *                              themselves (Super Admins excepted)
     * @param  bool       $merge    true keeps the user's existing grants
     *                              and adds to them; false replaces them
     * @return string[]   the keys actually applied
     */
    public static function applyTo(User $user, Role|string|null $role, ?User $actor = null, bool $merge = false): array
    {
        $keys = self::keysFor($role);

        if ($actor && ! PermissionResolver::isSuperAdmin($actor)) {
            /**
             * The same escalation guard RoleController applies when a
             * template is built. Without it, anyone able to create a
             * user could hand that user a template richer than their own
             * access, then use the new account.
             */
            $held = array_fill_keys(PermissionResolver::grantedKeys($actor), true);
            $keys = array_values(array_filter($keys, fn ($key) => isset($held[$key])));
        }

        if ($merge) {
            $keys = array_values(array_unique(array_merge(
                $user->permissions->pluck('name')->all(),
                $keys
            )));
        }

        self::ensureExist($keys);

        $user->syncPermissions($keys);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        PermissionResolver::flush($user);

        return $keys;
    }

    /**
     * Replace a user's permissions with an explicit list.
     *
     * Used by the per-user permission screen — the primary way access is
     * configured in this application.
     *
     * @param  string[]  $keys
     * @return string[]  the keys actually applied
     */
    public static function setFor(User $user, array $keys, ?User $actor = null): array
    {
        $declared = array_fill_keys(PermissionRegistry::keys(), true);
        $keys = array_values(array_filter($keys, fn ($key) => isset($declared[$key])));

        if ($actor && ! PermissionResolver::isSuperAdmin($actor)) {
            $held = array_fill_keys(PermissionResolver::grantedKeys($actor), true);
            $existing = array_fill_keys($user->permissions->pluck('name')->all(), true);

            // Keep what the user already has even if the editor cannot
            // see it, so a partial editor saving the form does not
            // silently strip rights that were never theirs to manage.
            $keys = array_values(array_filter(
                $keys,
                fn ($key) => isset($held[$key]) || isset($existing[$key])
            ));

            foreach ($existing as $key => $_) {
                if (! isset($held[$key]) && ! in_array($key, $keys, true)) {
                    $keys[] = $key;
                }
            }
        }

        self::ensureExist($keys);

        $user->syncPermissions($keys);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        PermissionResolver::flush($user);

        return $keys;
    }

    /**
     * Every name must exist as a row before syncPermissions() is called,
     * or Spatie throws PermissionDoesNotExist.
     *
     * @param  string[]  $keys
     */
    private static function ensureExist(array $keys): void
    {
        if ($keys === []) {
            return;
        }

        $existing = Permission::where('guard_name', 'web')
            ->whereIn('name', $keys)
            ->pluck('name')
            ->flip();

        foreach ($keys as $key) {
            if (! $existing->has($key)) {
                Permission::firstOrCreate(['name' => $key, 'guard_name' => 'web']);
            }
        }
    }
}
