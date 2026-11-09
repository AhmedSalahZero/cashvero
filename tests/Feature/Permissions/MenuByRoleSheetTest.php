<?php

namespace Tests\Feature\Permissions;

use App\Models\User;
use App\Support\Permissions\PermissionRegistry;
use App\Support\SidebarMenu;
use Tests\Fixtures\Fixtures;
use Tests\TestCase;

/**
 * The sidebar each role sees, checked against tests/Fixtures/menu_by_role.json — and every entry
 * anyone sees opens for that same person (a link that answers 403 is a broken promise).
 *
 * Run: php artisan test --filter=MenuByRoleSheetTest
 */
class MenuByRoleSheetTest extends TestCase
{
    private function personWithRole(string $role, \App\Models\Company $company): User
    {
        return $role === User::SUPER_ADMIN
            ? $this->makeSuperAdmin($company)
            : $this->makeUser(PermissionRegistry::defaultKeysForRole($role), $company);
    }

    /** @return array<string, string> visible title => link */
    private function visibleEntries(\App\Models\Company $company, User $person): array
    {
        $this->actingAs($person);
        $visible = [];

        $walk = function (array $entries) use (&$walk, &$visible) {
            foreach ($entries as $entry) {
                if (! is_array($entry)) {
                    continue;
                }
                if (($entry['type'] ?? null) === 'link' && ($entry['show'] ?? false)) {
                    $visible[(string) $entry['title']] = (string) $entry['link'];
                }
                if (isset($entry['items']) && ($entry['show'] ?? true)) {
                    $walk($entry['items']);
                }
            }
        };
        $walk(SidebarMenu::build($company, $person));

        return $visible;
    }

    public function test_the_sheet_covers_every_role(): void
    {
        $this->assertEqualsCanonicalizing(PermissionRegistry::rolesWithDefaults(), array_keys(Fixtures::load('menu_by_role')['roles']));
    }

    public function test_each_role_sees_what_the_sheet_says(): void
    {
        $company = $this->makeCompany();

        foreach (Fixtures::load('menu_by_role')['roles'] as $role => $sheet) {
            $titles = array_keys($this->visibleEntries($company, $this->personWithRole($role, $company)));

            foreach ($sheet['must_see'] as $title) {
                $this->assertContains($title, $titles, "{$role} should see {$title}");
            }
            foreach ($sheet['never_see'] as $title) {
                $this->assertNotContains($title, $titles, "{$role} should not see {$title}");
            }
        }
    }

    public function test_every_entry_a_person_sees_also_opens_for_them(): void
    {
        $this->withoutMiddleware([
            \Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect::class,
            \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter::class,
            \Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath::class,
        ]);

        $company = $this->makeCompany();
        $refused = [];

        foreach (['user', 'manager'] as $role) {
            $person = $this->personWithRole($role, $company);

            foreach ($this->visibleEntries($company, $person) as $title => $link) {
                $status = $this->actingAs($person)->get($link)->getStatusCode();

                if (in_array($status, [401, 403, 404], true)) {
                    $refused[] = "{$role} sees '{$title}' but opening it answers {$status}";
                }
            }
        }

        $this->assertSame([], $refused);
    }
}
