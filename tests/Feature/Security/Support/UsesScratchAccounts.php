<?php

namespace Tests\Feature\Security\Support;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use App\Support\Permissions\PermissionResolver;

/**
 * Shared set-up for the security tests that need a real database.
 *
 * SAFETY RULES (every test using this follows them):
 *   - It only ever creates "scratch" users (email  sec-test-xxxx@example.test)
 *     and removes exactly those in tearDown. Real users/companies are never edited.
 *   - It uses the development database, like the other smoke tests.
 *     Point it elsewhere with SMOKE_DB=<name>. If unreachable, tests SKIP.
 *   - Companies are only READ (to borrow two existing ones as the "two customers").
 *
 * The using class must extend Tests\TestCase and call $this->bootScratch() from setUp().
 */
trait UsesScratchAccounts
{
    /** @var int[] */
    private array $scratchUserIds = [];

    /** @var int[] permissions created only for these tests */
    private array $scratchPermissionIds = [];

    /** @var int[] company_systems rows added for the test (only ever in a database named cashvero_test) */
    private array $scratchSystemIds = [];

    private ?Company $companyA = null;

    private ?Company $companyB = null;

    protected function bootScratch(bool $needTwoCompanies = true): void
    {
        config(['database.connections.mysql.database' => env('SMOKE_DB', 'cashvero')]);
        DB::purge('mysql');

        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Development database not reachable: '.$e->getMessage());
        }

        // Same reason as the other smoke tests: the language-prefix redirect would answer 302
        // before any security check runs, and the test would prove nothing.
        $this->withoutMiddleware([
            LocaleSessionRedirect::class,
            LaravelLocalizationRedirectFilter::class,
            LaravelLocalizationViewPath::class,
        ]);

        $companies = Company::orderBy('id')->limit(2)->get();
        $this->companyA = $companies->get(0);
        $this->companyB = $companies->get(1);

        if (! $this->companyA || ($needTwoCompanies && ! $this->companyB)) {
            $this->markTestSkipped('The database needs '.($needTwoCompanies ? 'two companies' : 'a company').' to run this test.');
        }

        $this->enableCashVeroForScratchDatabase();
    }

    /**
     * A freshly seeded test database has companies but none of them has the CashVero system
     * switched on, so every user-management screen answers 403 and the attacks would never run.
     * ONLY in a database literally named "cashvero_test" we switch it on for the two companies
     * used, and switch it off again afterwards. Any other database is left strictly alone.
     */
    private function enableCashVeroForScratchDatabase(): void
    {
        if (DB::connection('mysql')->getDatabaseName() !== 'cashvero_test') {
            return;
        }

        foreach (array_filter([$this->companyA, $this->companyB]) as $company) {
            $has = DB::table('company_systems')->where('company_id', $company->id)->where('system_name', CASH_VERO)->exists();

            if (! $has) {
                $this->scratchSystemIds[] = DB::table('company_systems')->insertGetId([
                    'company_id' => $company->id, 'system_name' => CASH_VERO,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Create a throw-away user.
     *
     * @param  int[]  $companyIds
     * @param  string[]  $permissionKeys  granted directly to the user (this app is user-based)
     */
    protected function scratchUser(array $companyIds, array $permissionKeys = [], ?string $roleName = null): User
    {
        $user = new User;
        $user->name = 'Security Test '.bin2hex(random_bytes(3));
        $user->email = 'sec-test-'.bin2hex(random_bytes(6)).'@example.test';
        $user->password = bcrypt('Scratch-'.bin2hex(random_bytes(8)));
        $user->save();

        $this->scratchUserIds[] = $user->id;
        $user->companies()->attach($companyIds);

        if ($roleName !== null) {
            $role = Role::where('name', $roleName)->where('guard_name', 'web')->first();

            if (! $role) {
                $this->markTestSkipped("Role '{$roleName}' does not exist in this database.");
            }

            $user->assignRole($role);
        }

        $this->grantTo($user, $permissionKeys);

        return $user->fresh();
    }

    /** @param  string[]  $keys */
    protected function grantTo(User $user, array $keys): void
    {
        $models = [];

        foreach ($keys as $key) {
            $permission = Permission::firstOrCreate(['name' => $key, 'guard_name' => 'web']);

            if ($permission->wasRecentlyCreated) {
                $this->scratchPermissionIds[] = $permission->id;
            }

            $models[] = $permission;
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

    protected function cleanupScratch(): void
    {
        if ($this->scratchUserIds) {
            DB::table('companies_users')->whereIn('user_id', $this->scratchUserIds)->delete();
            DB::table('model_has_roles')->where('model_type', User::class)->whereIn('model_id', $this->scratchUserIds)->delete();
            DB::table('model_has_permissions')->where('model_type', User::class)->whereIn('model_id', $this->scratchUserIds)->delete();
            DB::table('users')->whereIn('id', $this->scratchUserIds)->delete();
        }

        if ($this->scratchPermissionIds) {
            DB::table('role_has_permissions')->whereIn('permission_id', $this->scratchPermissionIds)->delete();
            Permission::whereIn('id', $this->scratchPermissionIds)->delete();
        }

        if ($this->scratchSystemIds) {
            DB::table('company_systems')->whereIn('id', $this->scratchSystemIds)->delete();
        }

        $this->scratchUserIds = [];
        $this->scratchPermissionIds = [];
        $this->scratchSystemIds = [];
        $this->flushPermissionCaches();
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
