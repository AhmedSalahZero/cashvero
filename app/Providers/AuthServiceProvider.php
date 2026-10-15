<?php

namespace App\Providers;

use App\Models\User;
use App\Support\Permissions\PermissionRegistry;
use App\Support\Permissions\PermissionResolver;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        // 'App\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        /**
         * ⚠️ REAL BUG FIXED HERE (2026-08 permissions audit):
         *
         * This hook used to read:
         *
         *     if ($user->hasRole('Super-Admin')) { return true; }
         *
         * The role in the database is `super-admin` — lowercase, and
         * Spatie role names are case-sensitive. So the Super Admin
         * bypass NEVER fired. It only appeared to work because user 1
         * had been granted all 182 permissions individually via
         * `model_has_permissions`; a fresh Super Admin got nothing.
         *
         * There was also a second, empty `Gate::before(fn ($user) => …)`
         * above it that returned null on every call — dead code, now
         * removed.
         *
         * PermissionResolver::isSuperAdmin() is used rather than
         * `$user->isSuperAdmin()` because the latter does
         * `$this->roles->first()->name` and fatals for a user with no
         * role at all — which is most users in this database.
         */
        Gate::before(function ($user, string $ability) {
            if (! $user instanceof User) {
                return null;
            }

            if (PermissionResolver::isSuperAdmin($user)) {
                return true;
            }

            // Canonical key — resolve against the user's own grants.
            if (PermissionRegistry::has($ability)) {
                return PermissionResolver::allows($user, $ability);
            }

            /**
             * Legacy natural-language name ('view cash expenses').
             *
             * These MUST be answered here too. Falling through would
             * hand them to Spatie's own permission gate, which unions
             * role and direct grants — quietly reinstating role
             * inheritance for every legacy call site (App\Notification
             * alone has 13) after this application moved to user-based
             * permissions. Same user-only semantics as above.
             */
            if (PermissionRegistry::isLegacyName($ability)) {
                return PermissionResolver::allowsName($user, $ability);
            }

            // Anything else (policies, ad-hoc gates) behaves as before.
            return null;
        });
    }
}
