<?php

namespace Tests\Feature\Wiring;

use App\Support\SidebarMenu;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The pieces that only meet at run time — routes, controllers, middleware, the sidebar and its
 * icons — fit together. A typo in any of them is otherwise found by the first person who clicks.
 *
 * Run: php artisan test --filter=RoutesAndWiringTest
 */
class RoutesAndWiringTest extends TestCase
{
    public function test_every_route_points_to_a_real_controller_method(): void
    {
        $broken = [];

        foreach (Route::getRoutes() as $route) {
            $action = $route->getActionName();

            if ($action === 'Closure' || ! str_contains($action, '@')) {
                continue;
            }

            [$class, $method] = explode('@', $action);

            if (! class_exists($class)) {
                $broken[] = "{$route->uri()} → {$class} does not exist";
            } elseif (! method_exists($class, $method)) {
                $broken[] = "{$route->uri()} → {$class}::{$method}() does not exist";
            }
        }

        $this->assertSame([], $broken);
    }

    public function test_every_middleware_a_route_uses_is_known(): void
    {
        $router = app('router');
        $known = array_merge(array_keys($router->getMiddleware()), array_keys($router->getMiddlewareGroups()));
        $unknown = [];

        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (! is_string($middleware)) {
                    continue;
                }

                $name = explode(':', $middleware)[0];

                if (! in_array($name, $known, true) && ! class_exists($name)) {
                    $unknown[$name] = $route->uri();
                }
            }
        }

        $this->assertSame([], $unknown, 'Middleware names no one registered');
    }

    /**
     * The sidebar a super admin sees (everything visible): every link opens a route of this app
     * and every icon is one NavIcon.vue can draw.
     */
    public function test_every_sidebar_link_and_icon_exists(): void
    {
        $company = $this->makeCompany();
        $admin = $this->makeSuperAdmin($company);
        $this->actingAs($admin);

        $menu = SidebarMenu::build($company, $admin);
        $icons = $this->navIcons();
        $problems = [];

        $walk = function (array $entries) use (&$walk, &$problems, $icons) {
            foreach ($entries as $key => $entry) {
                if (! is_array($entry)) {
                    continue;
                }

                if (isset($entry['icon']) && ! in_array($entry['icon'], $icons, true)) {
                    $problems[] = "icon '{$entry['icon']}' (".($entry['title'] ?? $key).') is not in NavIcon.vue';
                }

                foreach (['link', 'action_url'] as $field) {
                    if (! empty($entry[$field]) && ! $this->routeExistsFor($entry[$field])) {
                        $problems[] = "{$field} {$entry[$field]} (".($entry['title'] ?? $key).') matches no route';
                    }
                }

                if (isset($entry['items'])) {
                    $walk($entry['items']);
                }
            }
        };
        $walk($menu);

        $this->assertNotEmpty($menu);
        $this->assertSame([], $problems);
    }

    /** @return list<string> the icon names NavIcon.vue maps */
    private function navIcons(): array
    {
        $source = (string) file_get_contents(resource_path('js/Components/NavIcon.vue'));
        preg_match('/const ICONS = \{(.*?)\};/s', $source, $block);
        preg_match_all("/^\s*'?([a-z0-9-]+)'?\s*:/m", $block[1] ?? '', $m);

        return $m[1];
    }

    private function routeExistsFor(string $url): bool
    {
        $request = \Illuminate\Http\Request::create($url);

        try {
            Route::getRoutes()->match($request);

            return true;
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException|\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {
            // A sidebar "action" is a POST; matching it as GET answers "method not allowed",
            // which still proves the route exists.
            return str_contains(get_class(new \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException([])), 'MethodNotAllowed')
                && $this->matchesAnyMethod($request);
        }
    }

    private function matchesAnyMethod(\Illuminate\Http\Request $request): bool
    {
        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            try {
                Route::getRoutes()->match(\Illuminate\Http\Request::create($request->getUri(), $method));

                return true;
            } catch (\Throwable) {
            }
        }

        return false;
    }
}
