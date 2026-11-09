<?php

namespace App\Support\Permissions;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Hands each NEW permission key, once, to the users who already hold the screen it came from.
 *
 * Permissions are held per user and `permissions:sync` only fills role templates, so a key added to
 * PermissionRegistry reaches no existing user on its own. Its `legacy` alias would have covered
 * that before `permissions:migrate-to-user`, but migrated users hold canonical keys only, and
 * PermissionResolver matches names literally. So a new report carved out of the bank-statement
 * screen (legacy 'view bank statement report') answered 403 to everyone who could open that screen.
 *
 * The rule is the one migrate-to-user used: a user gets the key if they could have opened it while
 * still holding their old names, i.e. they hold one of its legacy names or another canonical key
 * that stands for the same legacy name.
 *
 * Once. Every pending key gets a row in `permission_rollouts` (zero grants included), and a key
 * with a row is never looked at again. An admin who later takes it away from someone stays obeyed.
 * The migration that creates the table records every key that was already handed out.
 *
 * Super Admins are skipped (they pass everything through Gate::before). A key with no legacy alias
 * has no parent, so it reaches nobody and a Super Admin grants it by hand.
 */
class PermissionRollout
{
    public const TABLE = 'permission_rollouts';

    /**
     * Registry keys that have not been handed out yet.
     *
     * @return string[]
     */
    public static function pendingKeys(): array
    {
        $done = DB::table(self::TABLE)->pluck('key')->flip();

        return array_values(array_filter(
            PermissionRegistry::keys(),
            fn (string $key) => ! $done->has($key)
        ));
    }

    /**
     * Permission names whose holders inherit this key: its legacy names, plus every other canonical
     * key standing for one of them. Other pending keys don't count — only access users already had.
     *
     * @param  string[]  $pending
     * @return string[]
     */
    public static function parentNamesFor(string $key, array $pending = []): array
    {
        $legacy = array_values(array_diff(PermissionRegistry::grantNames($key), [$key]));

        $names = $legacy;
        foreach ($legacy as $name) {
            foreach (PermissionRegistry::keysForLegacy($name) as $sibling) {
                $names[] = $sibling;
            }
        }

        return array_values(array_diff(array_unique($names), [$key], $pending));
    }

    /**
     * Who would receive each pending key. Writes nothing.
     *
     * @return array<string, Collection<int, User>> pending key => users, in registry order
     */
    public static function plan(): array
    {
        $pending = self::pendingKeys();
        $plan = [];

        foreach ($pending as $key) {
            $parents = self::parentNamesFor($key, $pending);

            $plan[$key] = $parents === []
                ? collect()
                : self::usersHoldingAny($parents)
                    ->reject(fn (User $user) => PermissionResolver::isSuperAdmin($user)
                        || PermissionResolver::allows($user, $key))
                    ->values();
        }

        return $plan;
    }

    /**
     * Grant the plan and record every key in it, so none of them is handed out again.
     *
     * @param  array<string, Collection<int, User>>  $plan  from plan()
     * @return int number of grants made
     */
    public static function apply(array $plan): int
    {
        $granted = 0;

        DB::transaction(function () use ($plan, &$granted) {
            $now = now();

            foreach ($plan as $key => $users) {
                if ($users->isNotEmpty()) {
                    $permission = Permission::firstOrCreate(['name' => $key, 'guard_name' => 'web']);

                    foreach ($users as $user) {
                        $user->givePermissionTo($permission);
                        $granted++;
                    }
                }

                DB::table(self::TABLE)->insertOrIgnore([
                    'key' => $key,
                    'granted_users' => $users->count(),
                    'rolled_out_at' => $now,
                ]);
            }
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        PermissionResolver::flush();

        return $granted;
    }

    /**
     * Users holding any of these permission names DIRECTLY — the only grants that count here.
     *
     * @param  string[]  $names
     * @return Collection<int, User>
     */
    private static function usersHoldingAny(array $names): Collection
    {
        $userIds = DB::table('model_has_permissions as held')
            ->join('permissions', 'permissions.id', '=', 'held.permission_id')
            ->where('held.model_type', (new User)->getMorphClass())
            ->where('permissions.guard_name', 'web')
            ->whereIn('permissions.name', $names)
            ->distinct()
            ->pluck('held.model_id');

        if ($userIds->isEmpty()) {
            return collect();
        }

        return User::with(['roles', 'permissions'])->whereIn('id', $userIds)->orderBy('id')->get();
    }
}
