<?php

namespace Tests\Feature\Permissions;

use App\Models\User;
use App\Support\Permissions\PermissionRegistry;
use Database\Seeders\PermissionSeeder;
use Spatie\Permission\Models\Role;
use Tests\Fixtures\Fixtures;
use Tests\TestCase;

/**
 * What each role may do out of the box, checked against the agreed sheet
 * tests/Fixtures/permissions_by_role.json — first on the templates the registry declares, then
 * for a real person given the role's template, through the same check every screen uses.
 *
 * Run: php artisan test --filter=RoleTemplatesSheetTest
 */
class RoleTemplatesSheetTest extends TestCase
{
    public function test_the_sheet_covers_every_role(): void
    {
        $this->assertEqualsCanonicalizing(
            PermissionRegistry::rolesWithDefaults(),
            array_keys(Fixtures::load('permissions_by_role')['roles']),
        );
    }

    public function test_every_role_template_says_what_the_sheet_says(): void
    {
        foreach (Fixtures::load('permissions_by_role')['roles'] as $role => $sheet) {
            $template = PermissionRegistry::defaultKeysForRole($role);

            foreach ($sheet['may'] as $key) {
                $this->assertTrue(PermissionRegistry::has($key), "{$key} is not a permission");
                $this->assertContains($key, $template, "{$role} should be able to {$key}");
            }

            foreach ($sheet['may_not'] as $key) {
                $this->assertTrue(PermissionRegistry::has($key), "{$key} is not a permission");
                $this->assertNotContains($key, $template, "{$role} should NOT be able to {$key}");
            }
        }
    }

    /**
     * The app is user-based: a role is only a template, copied onto the person. A person given
     * exactly the role's template can do what the sheet says, and nothing it forbids.
     */
    public function test_a_person_given_a_role_template_can_do_exactly_what_the_sheet_says(): void
    {
        $company = $this->makeCompany();

        foreach (Fixtures::load('permissions_by_role')['roles'] as $role => $sheet) {
            $person = $role === User::SUPER_ADMIN
                ? $this->makeSuperAdmin($company)
                : $this->makeUser(PermissionRegistry::defaultKeysForRole($role), $company);

            foreach ($sheet['may'] as $key) {
                $this->assertTrue($person->hasPermissionKey($key), "{$role}: should be allowed {$key}");
            }

            foreach ($sheet['may_not'] as $key) {
                $this->assertFalse($person->hasPermissionKey($key), "{$role}: should be refused {$key}");
            }
        }
    }

    /** The seeded roles carry their templates, so "reset to the template" gives the agreed rights. */
    public function test_the_seeded_roles_carry_their_templates(): void
    {
        $this->seed(PermissionSeeder::class);

        foreach (PermissionRegistry::rolesWithDefaults() as $role) {
            $held = Role::findByName($role, 'web')->permissions->pluck('name')->all();

            foreach (Fixtures::load('permissions_by_role')['roles'][$role]['may'] as $key) {
                $this->assertContains($key, $held, "the {$role} role should carry {$key}");
            }
        }
    }
}
