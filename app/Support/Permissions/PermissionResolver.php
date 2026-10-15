<?php

namespace App\Support\Permissions;

use App\Models\User;

/**
 * PermissionResolver
 * ==================================================================
 * Turns "does this user hold this permission key?" into a single,
 * cached answer — and produces the flat key list the frontend needs.
 *
 * ── THIS APPLICATION IS USER-BASED, NOT ROLE-BASED ────────────────
 *
 * Resolution for a key such as `cash_expense.delete`:
 *
 *   1. Super Admin   → always true (centralised bypass).
 *   2. Direct grants → the user's own rows in `model_has_permissions`.
 *
 * That is the whole list. A role's permissions are deliberately NOT
 * consulted: a role here is a TEMPLATE that gets copied onto a user
 * when the user is created (or when an admin explicitly re-applies
 * it), never a live source of authority. Two people holding the same
 * role can therefore have completely different access, which is the
 * point — permissions are configured per person.
 *
 * The practical consequence, and the reason this is spelled out:
 * editing a role's permissions does NOT change what any existing user
 * can do. Only editing that user changes it.
 *
 * `$user->permissions` is used rather than Spatie's
 * `getAllPermissions()` precisely because the latter unions role and
 * direct grants — which is the behaviour being removed.
 *
 * A key matches if the user holds the canonical key OR any of its
 * legacy aliases — see PermissionRegistry for why that matters.
 */
class PermissionResolver
{
    /**
     * Per-request memo: user id → [permission name => true].
     *
     * @var array<int, array<string, true>>
     */
    private static array $nameCache = [];

    /**
     * Per-request memo: user id → granted canonical keys.
     *
     * @var array<int, string[]>
     */
    private static array $keyCache = [];

    /**
     * Does the user hold this canonical permission key?
     */
    public static function allows(?User $user, string $key): bool
    {
        if (! $user) {
            return false;
        }

        if (self::isSuperAdmin($user)) {
            return true;
        }

        // An unknown key is never granted — fail closed on typos so a
        // misspelt check can't silently authorise anything.
        if (! PermissionRegistry::has($key)) {
            return false;
        }

        $held = self::heldNames($user);

        foreach (PermissionRegistry::grantNames($key) as $name) {
            if (isset($held[$name])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does the user hold this exact permission NAME directly?
     *
     * For legacy natural-language names ('view cash expenses') reached
     * through `$user->can(...)`. Same user-only semantics as allows() —
     * the point is that this never consults the role.
     */
    public static function allowsName(?User $user, string $name): bool
    {
        if (! $user) {
            return false;
        }

        if (self::isSuperAdmin($user)) {
            return true;
        }

        if (isset(self::heldNames($user)[$name])) {
            return true;
        }

        /**
         * The user may hold the CANONICAL key this legacy name stands
         * for. After the move to user-based permissions everyone holds
         * dotted keys only, so without this a legacy call site resolves
         * against a name nobody has left and returns false — which is
         * how `can('view users')` started refusing users who plainly
         * held `user.view`.
         */
        foreach (PermissionRegistry::keysForLegacy($name) as $key) {
            if (self::allows($user, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Does the user hold at least one of these keys?
     *
     * @param  string[]  $keys
     */
    public static function allowsAny(?User $user, array $keys): bool
    {
        foreach ($keys as $key) {
            if (self::allows($user, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Every canonical key the user holds — this is what gets shared
     * with Inertia so the frontend can answer `can()` with no request.
     *
     * @return string[]
     */
    public static function grantedKeys(?User $user): array
    {
        if (! $user) {
            return [];
        }

        if (isset(self::$keyCache[$user->id])) {
            return self::$keyCache[$user->id];
        }

        if (self::isSuperAdmin($user)) {
            return self::$keyCache[$user->id] = PermissionRegistry::keys();
        }

        $held = self::heldNames($user);
        $granted = [];

        foreach (PermissionRegistry::all() as $key => $permission) {
            foreach (PermissionRegistry::grantNames($key) as $name) {
                if (isset($held[$name])) {
                    $granted[] = $key;
                    break;
                }
            }
        }

        return self::$keyCache[$user->id] = $granted;
    }

    /**
     * Safe Super Admin check.
     *
     * `User::isSuperAdmin()` reads `$this->roles->first()->name` and
     * fatals for a user with no role at all — which is most users in
     * this database. This wrapper is null-safe so authorisation can
     * never crash the request.
     */
    public static function isSuperAdmin(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->roles->contains('name', User::SUPER_ADMIN);
    }

    /**
     * Flat lookup of every permission NAME granted DIRECTLY to this
     * user, memoised per request.
     *
     * Deliberately `$user->permissions` (direct only) and not
     * `getAllPermissions()` (direct ∪ role) — see the class docblock.
     * One relation load per request, not one query per check.
     *
     * @return array<string, true>
     */
    private static function heldNames(User $user): array
    {
        if (isset(self::$nameCache[$user->id])) {
            return self::$nameCache[$user->id];
        }

        $names = $user->permissions->pluck('name')->all();

        return self::$nameCache[$user->id] = array_fill_keys($names, true);
    }

    /**
     * Drop the memo — used by tests and after a role/permission change
     * so the next check re-reads from the database.
     */
    public static function flush(?User $user = null): void
    {
        if ($user) {
            unset(self::$nameCache[$user->id], self::$keyCache[$user->id]);

            return;
        }

        self::$nameCache = [];
        self::$keyCache = [];
    }
}
