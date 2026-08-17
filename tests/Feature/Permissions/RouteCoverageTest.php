<?php

namespace Tests\Feature\Permissions;

use App\Support\Permissions\PermissionRegistry;
use App\Support\Permissions\RoutePermissionMap;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The guard against drift.
 *
 * EnforcePermission is fail-OPEN for routes the map doesn't know about
 * (config('permissions.unmapped') === 'allow'), which keeps a page this
 * map missed from going dark in production. The cost of that choice is
 * that a new unprotected route would otherwise be invisible.
 *
 * This test is what pays that cost: add a route without mapping it and
 * the build fails here, long before anyone finds it by other means.
 *
 * Needs no database — it reads the route table and two arrays.
 */
class RouteCoverageTest extends TestCase
{
    /**
     * Routes owned by dev/debug packages, which ship their own names
     * and must not be a reason for this suite to fail.
     */
    private const IGNORED_PREFIXES = [
        'debugbar.', 'ignition.', 'livewire.', 'default-livewire.',
        'sanctum.', 'horizon.', 'telescope.',
    ];

    public function test_every_named_route_is_mapped_or_explicitly_public(): void
    {
        $unmapped = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if (! $name || $this->isIgnored($name)) {
                continue;
            }

            if (RoutePermissionMap::isPublic($name)) {
                continue;
            }

            if (RoutePermissionMap::for($name) === null) {
                $unmapped[] = $name.'  ['.implode('|', $route->methods()).' /'.$route->uri().']';
            }
        }

        sort($unmapped);

        $this->assertSame([], $unmapped, sprintf(
            "%d route(s) have no permission mapping.\n\n".
            "Add each to App\\Support\\Permissions\\RoutePermissionMap — either to MAP with the\n".
            "permission key it needs, or to PUBLIC_ROUTES if it genuinely needs none:\n\n  %s\n",
            count($unmapped),
            implode("\n  ", $unmapped)
        ));
    }

    public function test_every_mapped_permission_key_exists_in_the_registry(): void
    {
        $unknown = [];

        foreach (RoutePermissionMap::map() as $routeName => $required) {
            foreach ((array) $required as $key) {
                if (! PermissionRegistry::has($key)) {
                    $unknown[] = "{$routeName} => {$key}";
                }
            }
        }

        sort($unknown);

        $this->assertSame([], $unknown, sprintf(
            "%d route mapping(s) reference a permission key the registry does not declare.\n".
            "A typo here silently denies the route to everyone except Super Admin, because\n".
            "PermissionResolver fails closed on unknown keys:\n\n  %s\n",
            count($unknown),
            implode("\n  ", $unknown)
        ));
    }

    public function test_every_mapped_route_name_actually_exists(): void
    {
        $existing = [];
        foreach (Route::getRoutes() as $route) {
            if ($name = $route->getName()) {
                $existing[$name] = true;
            }
        }

        $stale = array_values(array_filter(
            array_keys(RoutePermissionMap::map()),
            fn ($name) => ! isset($existing[$name])
        ));

        sort($stale);

        $this->assertSame([], $stale, sprintf(
            "%d mapped route name(s) no longer exist — a renamed or deleted route leaves\n".
            "a dead entry that will never match, so the real route becomes unmapped:\n\n  %s\n",
            count($stale),
            implode("\n  ", $stale)
        ));
    }

    public function test_public_route_list_contains_no_stale_entries(): void
    {
        $existing = [];
        foreach (Route::getRoutes() as $route) {
            if ($name = $route->getName()) {
                $existing[$name] = true;
            }
        }

        $stale = array_values(array_filter(
            RoutePermissionMap::publicRoutes(),
            fn ($name) => ! isset($existing[$name]) && ! $this->isIgnored($name)
        ));

        sort($stale);

        $this->assertSame([], $stale,
            "PUBLIC_ROUTES names routes that do not exist:\n  ".implode("\n  ", $stale)
        );
    }

    /**
     * Every write route must demand something. A POST/PUT/PATCH/DELETE
     * sitting in PUBLIC_ROUTES is almost always a mistake — the few
     * legitimate ones are named here so adding another is a deliberate act.
     */
    public function test_no_write_route_is_public_without_justification(): void
    {
        $allowedPublicWrites = [
            'logout', 'password.email', 'password.update', 'theme.toggle',
            'profile.update', 'ignition.executeSolution', 'ignition.updateConfig',
            'debugbar.cache.delete', 'debugbar.queries.explain',
            'livewire.upload-file', 'default-livewire.update',
        ];

        $offenders = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if (! $name || $this->isIgnored($name) || in_array($name, $allowedPublicWrites, true)) {
                continue;
            }

            $isWrite = (bool) array_intersect($route->methods(), ['POST', 'PUT', 'PATCH', 'DELETE']);

            if ($isWrite && RoutePermissionMap::isPublic($name)) {
                $offenders[] = $name.'  ['.implode('|', $route->methods()).']';
            }
        }

        sort($offenders);

        $this->assertSame([], $offenders,
            "Write route(s) marked public — they change data but require no permission:\n  "
            .implode("\n  ", $offenders)
        );
    }

    private function isIgnored(string $name): bool
    {
        foreach (self::IGNORED_PREFIXES as $prefix) {
            if (str_starts_with($name, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
