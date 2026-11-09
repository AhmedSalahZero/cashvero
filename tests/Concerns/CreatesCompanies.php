<?php

namespace Tests\Concerns;

use App\Models\Company;
use App\Models\User;
use App\Support\Permissions\PermissionResolver;
use Database\Factories\CompanyFactory;
use Database\Factories\UserFactory;
use Spatie\Permission\PermissionRegistrar;

/**
 * Companies and people, made fresh for each test (in every test through TestCase).
 *
 *   $company = $this->makeCompany();                       // subscribed to CashVero, EGP
 *   $other   = $this->makeCompany();                       // the "another company" of isolation tests
 *   $user    = $this->makeUser(['report_bank_statement.view'], $company);
 *   $admin   = $this->makeSuperAdmin($company);
 *   $this->signInWith(['cash_expense.view'], $company);    // makes the user and acts as them
 *
 * Permissions are canonical keys from PermissionRegistry (`module.action`) or the old
 * permission names; both exist in the test database (TestReferenceSeeder).
 */
trait CreatesCompanies
{
    protected function makeCompany(array $overrides = [], bool $cashVero = true): Company
    {
        $factory = CompanyFactory::new()->state($overrides);

        return ($cashVero ? $factory->withCashVero() : $factory)->create();
    }

    /**
     * @param  string[]  $permissions  canonical keys or old permission names, granted to the user directly
     */
    protected function makeUser(array $permissions = [], ?Company $company = null, ?string $role = null, array $overrides = []): User
    {
        $company ??= $this->makeCompany();

        /** @var User $user */
        $user = UserFactory::new()->create($overrides);
        $user->companies()->attach($company->id);

        if ($role !== null) {
            $user->assignRole($role);
        }

        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }

        $this->forgetPermissions();

        return $user->load('companies');
    }

    protected function makeSuperAdmin(?Company $company = null): User
    {
        return $this->makeUser([], $company, User::SUPER_ADMIN);
    }

    /** @param  string[]  $permissions */
    protected function signInWith(array $permissions, ?Company $company = null): User
    {
        $user = $this->makeUser($permissions, $company);
        $this->actingAs($user);

        return $user;
    }

    /** After changing someone's permissions in a test, so the next check reads them again. */
    protected function forgetPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        PermissionResolver::flush();
    }
}
