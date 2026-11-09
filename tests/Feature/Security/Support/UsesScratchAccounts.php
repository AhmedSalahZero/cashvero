<?php

namespace Tests\Feature\Security\Support;

use App\Models\Company;
use App\Models\User;
use App\Support\Permissions\PermissionResolver;
use Database\Factories\UserFactory;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Shared set-up for the security tests that need people and companies.
 *
 * Everything is made by the test itself: two fresh companies ("two customers" of CashVero, both
 * subscribed to the CashVero system) and throw-away users (sec-test-xxxx@example.test). It all lives
 * inside the transaction every test runs in (Tests\TestCase), so nothing is left behind and no real
 * company or user is ever read or touched.
 *
 * The using class must extend Tests\TestCase and call $this->bootScratch() from setUp().
 */
trait UsesScratchAccounts
{
    private ?Company $companyA = null;

    private ?Company $companyB = null;

    protected function bootScratch(bool $needTwoCompanies = true): void
    {
        // The language-prefix redirect would answer 302 before any security check runs, and the test
        // would prove nothing.
        $this->withoutMiddleware([
            LocaleSessionRedirect::class,
            LaravelLocalizationRedirectFilter::class,
            LaravelLocalizationViewPath::class,
        ]);

        $this->companyA = $this->makeCompany();
        $this->companyB = $needTwoCompanies ? $this->makeCompany() : null;
    }

    /**
     * Create a throw-away user.
     *
     * @param  int[]  $companyIds
     * @param  string[]  $permissionKeys  granted directly to the user (this app is user-based)
     */
    protected function scratchUser(array $companyIds, array $permissionKeys = [], ?string $roleName = null): User
    {
        /** @var User $user */
        $user = UserFactory::new()->create([
            'name' => 'Security Test '.bin2hex(random_bytes(3)),
            'email' => 'sec-test-'.bin2hex(random_bytes(6)).'@example.test',
        ]);

        $user->companies()->attach($companyIds);

        if ($roleName !== null) {
            // The roles come from PermissionRegistry (TestReferenceSeeder): a missing one is a set-up error.
            $user->assignRole(Role::findByName($roleName, 'web'));
        }

        $this->grantTo($user, $permissionKeys);

        return $user->fresh();
    }

    /** @param  string[]  $keys */
    protected function grantTo(User $user, array $keys): void
    {
        $models = [];

        foreach ($keys as $key) {
            $models[] = Permission::firstOrCreate(['name' => $key, 'guard_name' => 'web']);
        }

        $user->syncPermissions($models);
        $this->flushPermissionCaches();
    }

    protected function flushPermissionCaches(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        PermissionResolver::flush();
    }

    /** Fresh copy of a user, so permission caches on the old object cannot hide a result. */
    protected function reload(User $user): User
    {
        $this->flushPermissionCaches();

        return User::findOrFail($user->id);
    }

    /** Is the user still in the database? (CashVero deletes users for real; there is no "deleted" flag.) */
    protected function exists(User $user): bool
    {
        return User::whereKey($user->id)->exists();
    }

    /** Pull the Inertia page data (props) out of an HTML response. */
    protected function inertiaProps(\Illuminate\Testing\TestResponse $response): array
    {
        $html = (string) $response->getContent();
        $candidates = [];

        // Older style: <div id="app" data-page="{...}">  (value is HTML-escaped JSON)
        if (preg_match_all('/data-page="([^"]*)"/', $html, $m)) {
            foreach ($m[1] as $raw) {
                $candidates[] = html_entity_decode($raw, ENT_QUOTES);
            }
        }

        // Newer style: <script data-page="app" type="application/json">{...}</script>
        if (preg_match_all('#<script[^>]*data-page[^>]*>(.*?)</script>#s', $html, $m)) {
            foreach ($m[1] as $raw) {
                $candidates[] = $raw;
            }
        }

        foreach ($candidates as $json) {
            $decoded = json_decode($json, true);

            if (is_array($decoded) && isset($decoded['props'])) {
                return $decoded['props'];
            }
        }

        return [];
    }
}
