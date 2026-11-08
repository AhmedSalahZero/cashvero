<?php

namespace Tests\Feature\Security;

use App\Support\Permissions\PermissionRegistry;
use App\Support\Permissions\RoutePermissionMap;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Permission;
use Tests\Feature\Security\Support\AttacksRoutes;
use Tests\Feature\Security\Support\Finding;
use Tests\TestCase;

/**
 * PERMISSION GRANULARITY: "view" must never mean "change"
 * ==================================================================
 * SEC-33 proves a user with NO permissions is refused everywhere. The more realistic risk is a user who has
 * SOME permissions: an accountant who may only LOOK at invoices must not be able to create, edit or delete them.
 *
 *   SEC-35a  Every permission the route map asks for really exists (a misspelt one means nobody but a
 *            Super Admin can ever use that screen, or worse, that the rule silently protects nothing).
 *   SEC-35b  No user holds an old-style permission name that also grants a create / update / delete / import
 *            right (several keys still share one old name).
 *   SEC-35c  A user holding EVERY "view" permission and nothing else is refused on every write screen that
 *            needs more than viewing.
 */
#[Group('security')]
class PermissionGranularitySecurityTest extends TestCase
{
    use AttacksRoutes;

    /** Actions that only read. Anything else (create, update, delete, import, settle, renew, approve...) changes data. */
    private const READ_ACTIONS = ['view', 'export'];

    protected function tearDown(): void
    {
        $this->cleanupScratch();
        parent::tearDown();
    }

    /* ────────────────────────────── SEC-35a ────────────────────────────── */

    public function test_every_permission_the_route_map_asks_for_exists(): void
    {
        $unknown = [];

        foreach (RoutePermissionMap::map() as $routeName => $required) {
            foreach ((array) $required as $key) {
                if (! PermissionRegistry::has($key)) {
                    $unknown[$key][] = $routeName;
                }
            }
        }

        $lines = [];

        foreach ($unknown as $key => $routes) {
            $lines[] = "\"{$key}\" is not a known permission (asked for by: ".implode(', ', array_slice($routes, 0, 4)).(count($routes) > 4 ? ', ...' : '').')';
        }

        $this->assertSame([], $lines, Finding::message(
            'SEC-35a', 'MEDIUM',
            'The route map asks for a permission that does not exist',
            $lines,
            'An unknown permission is never granted, so these screens are unreachable for everybody except a Super Admin. Fixing the typo the other way (by loosening the rule) would open them to the wrong people.',
            'Correct the key in RoutePermissionMap to an existing one from PermissionRegistry.'
        ));
    }

    public function test_every_permission_key_exists_in_the_database(): void
    {
        try {
            $stored = Permission::where('guard_name', 'web')->pluck('name')->flip()->all();
        } catch (\Throwable $e) {
            $this->markTestSkipped('The permissions table could not be read: '.$e->getMessage());
        }

        if ($stored === []) {
            $this->markTestSkipped('The permissions table is empty; run php artisan cashvero:prepare-test-db --from=cashvero');
        }

        $missing = [];

        foreach (PermissionRegistry::keys() as $key) {
            if (! isset($stored[$key])) {
                $missing[$key] = [$key];
            }
        }

        $lines = [];

        foreach ($missing as $name => $keys) {
            $lines[] = "permission \"{$name}\" is in PermissionRegistry but missing from the permissions table";
        }

        $this->assertSame([], $lines, Finding::message(
            'SEC-35a', 'LOW',
            'A permission key points at a stored permission that does not exist in the database',
            $lines,
            'Nobody can be given that permission through the screens, so the matching feature cannot be switched on for a normal user.',
            'Run the PermissionSeeder (php artisan db:seed --class=PermissionSeeder) or correct the name in PermissionRegistry.'
        ));
    }

    /* ────────────────────────────── SEC-35b ────────────────────────────── */

    /**
     * The registry still understands the OLD permission names ("view customer balances") as aliases, and several keys
     * share one old name: the same name grants "customer_balance.view" AND "customer_balance.update". Users created
     * by the current screens hold only the new keys, so that overlap is harmless. It becomes a hole the moment a
     * user still holds such an old name, because holding it silently grants the change rights too.
     */
    public function test_no_user_holds_an_old_permission_name_that_also_grants_change_rights(): void
    {
        $canonical = array_fill_keys(PermissionRegistry::keys(), true);
        $viewNames = [];
        $grants = [];   // old name => [change keys it grants]

        foreach (PermissionRegistry::keys() as $key) {
            if ($this->actionOf($key) === 'view') {
                foreach (PermissionRegistry::grantNames($key) as $name) {
                    $viewNames[$name] = true;
                }
            }
        }

        $this->assertNotSame([], $viewNames, 'No "view" permission was found in PermissionRegistry: the test could not read it.');

        foreach (PermissionRegistry::keys() as $key) {
            if (in_array($this->actionOf($key), self::READ_ACTIONS, true)) {
                continue;
            }

            foreach (PermissionRegistry::grantNames($key) as $name) {
                if (! isset($canonical[$name]) && isset($viewNames[$name])) {
                    $grants[$name][] = $key;
                }
            }
        }

        if ($grants === []) {
            $this->addToAssertionCount(1);

            return;
        }

        // Every permission name each user holds directly (that is what the application checks).
        $held = [];

        foreach (DB::table('model_has_permissions')
            ->join('permissions', 'permissions.id', '=', 'model_has_permissions.permission_id')
            ->join('users', 'users.id', '=', 'model_has_permissions.model_id')
            ->where('model_has_permissions.model_type', \App\Models\User::class)
            ->where('users.email', 'not like', 'sec-test-%')
            ->get(['users.id as user_id', 'users.email', 'permissions.name as permission']) as $row) {
            $held[$row->user_id]['email'] = $row->email;
            $held[$row->user_id]['names'][$row->permission] = true;
        }

        $lines = [];

        foreach ($held as $userId => $info) {
            foreach ($grants as $oldName => $changeKeys) {
                if (! isset($info['names'][$oldName])) {
                    continue;
                }

                // Only a problem when the old name gives the user something they do not already hold properly:
                // a full administrator who has every new key anyway gains nothing from it.
                $extra = array_values(array_filter($changeKeys, fn ($key) => ! isset($info['names'][$key])));

                if ($extra !== []) {
                    $lines[] = "user #{$userId} ({$info['email']}) holds the old name \"{$oldName}\", which also grants (and the user does not otherwise hold): ".implode(', ', array_slice($extra, 0, 6));
                }
            }
        }

        $this->assertSame([], $lines, Finding::message(
            'SEC-35b', 'MEDIUM',
            'A user holds an old-style permission name that also grants rights to change data',
            $lines,
            'Holding that one old name silently grants view AND create/update/delete rights, so a person meant to be read-only can change data.',
            'Re-save these users on the permissions screen (it stores the new, separate keys), or run the permission migration command again, then remove the old names from them. Long term, remove the shared aliases from PermissionRegistry.'
        ));
    }

    /* ────────────────────────────── SEC-35c ────────────────────────────── */

    public function test_a_user_who_can_only_view_is_refused_on_every_write_screen(): void
    {
        $this->bootAttack();

        if (! config('permissions.enforce', true)) {
            $this->markTestSkipped('Permission enforcement is switched off in this environment (PERMISSIONS_ENFORCE=false), so there is nothing to test.');
        }

        // Every stored permission name that stands for a "view" key, and nothing else.
        $names = [];

        foreach (PermissionRegistry::keys() as $key) {
            if ($this->actionOf($key) === 'view') {
                // The new, separate keys only: that is what the permissions screen stores for a user.
                $names[] = $key;
            }
        }

        $viewer = $this->scratchUser([$this->companyA->id], array_values(array_unique($names)));

        $leaks = [];
        $checked = 0;

        foreach ($this->allRoutes() as $route) {
            $name = $route->getName();

            if ($name === null || ! $this->hasCompanyParam($route) || RoutePermissionMap::isPublic($name)) {
                continue;
            }

            $required = RoutePermissionMap::for($name);

            // A screen that a viewer is meant to reach (some reports are posted forms): not a write screen.
            if ($required === null || $this->anyReadAction($required)) {
                continue;
            }

            $values = array_merge($this->recordIdsFor($route, $this->companyA->id), $this->companyValues($route, $this->companyA->id));

            foreach ($this->verbsOf($route) as $verb) {
                if (! $this->isWrite($verb)) {
                    continue;
                }

                $url = $this->urlFor($route, $values);
                $status = $this->send($verb, $url, $location, $validationOnly, [], null, $viewer);
                $checked++;

                // The permission check runs BEFORE the controller, so even a bounce by validation (422) means
                // the viewer got past it.
                if (! $this->isSafe($status, $location)) {
                    $leaks[] = "{$verb} /{$route->uri()}  →  {$this->describe($status, $location)}   (needs: ".implode(', ', $required).')';
                }
            }
        }

        $this->assertGreaterThan(20, $checked, 'Too few write screens were checked ('.$checked.').');

        $this->assertSame([], $leaks, Finding::message(
            'SEC-35c', 'HIGH',
            'A user who may only VIEW got past the permission check of a screen that changes data',
            $leaks,
            'A read-only role could still create, edit or delete records by sending the request directly.',
            'Check the key RoutePermissionMap gives the route (it should be a create/update/delete key, not a view key), and that no stored permission name is shared between a view key and that key.'
        )."\n\n{$checked} write requests were sent as a view-only user.");
    }

    /* ───────────────────────────── helpers ───────────────────────────── */

    /** 'invoice.update' → 'update' */
    private function actionOf(string $key): string
    {
        return substr(strrchr('.'.$key, '.'), 1);
    }

    /** @param  string[]  $keys */
    private function anyReadAction(array $keys): bool
    {
        foreach ($keys as $key) {
            if (in_array($this->actionOf($key), self::READ_ACTIONS, true)) {
                return true;
            }
        }

        return false;
    }
}
