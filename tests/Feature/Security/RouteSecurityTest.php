<?php

namespace Tests\Feature\Security;

use App\Support\Permissions\RoutePermissionMap;
use Closure;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Group;
use ReflectionClass;
use ReflectionFunction;
use Tests\Feature\Security\Support\Finding;
use Tests\TestCase;

/**
 * SECURITY AUDIT — the map of every URL the application exposes.
 *
 * A penetration tester starts by listing every address the system answers to
 * and asking, for each one: who can reach it, what can it change, and what
 * stops a stranger or a user from another company? This test asks those
 * questions of the real route table, so a new route that is open, unnamed or
 * destructive is reported the day it is added.
 *
 * Needs the application booted but NO database and makes NO web requests.
 *
 * Related, already in the project: tests/Feature/Permissions/RouteCoverageTest
 * (every NAMED route has a permission rule). This file covers what that one
 * cannot: routes with no name, routes that change data on GET, and routes the
 * company-membership check cannot see.
 */
#[Group('security')]
class RouteSecurityTest extends TestCase
{
    /** Words that mean "this changes or destroys data". */
    private const DESTRUCTIVE_PATTERN = '/(?<![a-z])(delete|destroy|remove|truncate|wipe|purge|reset)|insertToMain/i';

    /**
     * Routes that match the pattern above but were reviewed and are harmless:
     * name => why.
     */
    private const REVIEWED_HARMLESS_GET_ROUTES = [
        // Only clears a "where to go next" hint from the caller's OWN session; no stored data.
        'removeSessionForRedirect' => 'clears a redirect hint in the caller\'s own session',
    ];

    /** Public-list routes with a destructive-sounding name that were reviewed and are fine: name => why. */
    private const REVIEWED_DESTRUCTIVE_PUBLIC_ROUTES = [
        'messages.delete' => 'MessagingController::authorizeConversation() — covered by MessagingTest',
        'remove.session.for.redirect' => 'only forgets the "go back to" address kept in the user\'s OWN session; changes no stored data',
        'password.reset' => 'the sign-in flow page where a person chooses a new password; protected by a one-time token',
    ];

    /** Routes owned by developer tooling packages (not installed on a production server). */
    private const TOOLING_ROUTE_PREFIXES = ['debugbar.', 'ignition.', 'livewire.', 'default-livewire.', 'telescope.', 'horizon.'];

    /* ──────────────────────────────────────────────────────────────
     * Who can reach what
     * ────────────────────────────────────────────────────────────── */

    public function test_every_application_route_requires_a_signed_in_user(): void
    {
        $open = [];

        foreach ($this->applicationRoutes() as $route) {
            if ($this->isAuthenticationScreen($route) || $this->requiresLogin($route)) {
                continue;
            }

            $open[] = $this->describe($route);
        }

        $this->assertSame([], $open, Finding::message(
            'SEC-AUTH', 'CRITICAL',
            'Routes that can be used without signing in',
            $open,
            'A stranger on the internet can call them directly. Everything except the login, registration and password-reset screens must sit behind the auth middleware.',
            "Put each route inside Route::middleware(['auth', ...])->group(...), or add ->middleware('auth')."
        ));
    }

    /**
     * Decision for the business owner, not a coding mistake.
     *
     * CashVero is a closed, business-to-business system: companies and their users are created
     * by an administrator. Open sign-up lets ANYONE on the internet create an account, and every
     * page that only checks "is someone logged in" (see the SEC-12 and SEC-13 findings) is then
     * reachable by that stranger. If you do want self-service trials, keep registration but add
     * e-mail verification, a CAPTCHA and rate limiting, and delete this test.
     */
    public function test_public_self_registration_is_closed(): void
    {
        $this->assertFalse(
            Route::has('register'),
            Finding::message(
                'SEC-19', 'MEDIUM',
                'Anyone on the internet can create an account (public /register page)',
                ['Auth::routes() in routes/web.php registers GET/POST register.', 'There is no e-mail verification step and no rate limit on it (see SEC-17).'],
                'A treasury system should only have users an administrator created. A self-registered account has no company, but it IS signed in, and the "login only" pages and the missing-ownership-check findings (SEC-12, SEC-13) then apply to a stranger.',
                "Use Auth::routes(['register' => false]) and keep creating users from the administrator screen."
            )
        );
    }

    /* ──────────────────────────────────────────────────────────────
     * Permission layer
     * ────────────────────────────────────────────────────────────── */

    public function test_every_route_has_a_name_so_the_permission_check_cannot_skip_it(): void
    {
        $unnamed = [];

        foreach ($this->applicationRoutes() as $route) {
            if ($this->isAuthenticationScreen($route)) {
                continue;
            }

            $name = (string) $route->getName();

            if ($name === '' || str_starts_with($name, 'generated::')) {
                $unnamed[] = $this->describe($route);
            }
        }

        $this->assertSame([], $unnamed, Finding::message(
            'SEC-11', 'HIGH',
            'Routes with no name are never checked against the permission rules',
            $unnamed,
            'EnforcePermission finds the rule for a request by the route\'s NAME. A route without one returns early and is allowed through (proved in MiddlewareSecurityTest). These routes only require a login: a user who has no right to see customers, invoices or accounts can still call them and read the lists they return. RouteCoverageTest cannot catch this because it skips unnamed routes too.',
            "Give each route a ->name('...') and add it to App\\Support\\Permissions\\RoutePermissionMap with the permission that screen already uses (e.g. the money-received lookups use money_received.view)."
        ));
    }

    /**
     * config/permissions.php says a route the map does not know is ALLOWED ("fail open") and merely
     * logged. That was a sensible safety net during roll-out, and RouteCoverageTest keeps the map
     * complete, but it is the wrong ending state for a financial system: forget one rule and the
     * route is silently open instead of silently closed.
     *
     * Order of work: first name + map the unnamed routes (SEC-11), then flip this to 'deny'.
     */
    public function test_a_route_without_a_permission_rule_is_refused_by_default(): void
    {
        $config = (string) file_get_contents(base_path('config/permissions.php'));

        $this->assertMatchesRegularExpression(
            '/[\'"]unmapped[\'"]\s*=>\s*env\(\s*[\'"]PERMISSIONS_UNMAPPED[\'"]\s*,\s*[\'"]deny[\'"]\s*\)/',
            $config,
            Finding::message(
                'SEC-11b', 'LOW',
                'A route missing from the permission map is allowed through',
                ["config/permissions.php: 'unmapped' => env('PERMISSIONS_UNMAPPED', 'allow')"],
                'Security systems should fail closed. If a developer forgets to add a rule, the page should show "403", not quietly work for everyone.',
                "After fixing SEC-11, change the default to 'deny' (and set PERMISSIONS_UNMAPPED=deny on the server)."
            )
        );
    }

    public function test_destructive_routes_are_never_open_to_every_signed_in_user(): void
    {
        $public = (new ReflectionClass(RoutePermissionMap::class))->getConstants()['PUBLIC_ROUTES'] ?? [];

        $this->assertNotEmpty($public, 'Could not read RoutePermissionMap::PUBLIC_ROUTES; update this test.');

        $offenders = [];

        foreach ($public as $name) {
            foreach (self::TOOLING_ROUTE_PREFIXES as $prefix) {
                if (str_starts_with($name, $prefix)) {
                    continue 2;
                }
            }

            if (preg_match(self::DESTRUCTIVE_PATTERN, $name) && ! isset(self::REVIEWED_DESTRUCTIVE_PUBLIC_ROUTES[$name])) {
                $offenders[] = $name.'   (listed in RoutePermissionMap::PUBLIC_ROUTES = login required, no permission required)';
            }
        }

        $this->assertSame([], $offenders, Finding::message(
            'SEC-12', 'HIGH',
            'A destructive route needs only a login, no permission',
            $offenders,
            'remove.company.image is a plain link (GET) that deletes a company\'s logo. It checks no permission and no ownership, and it sits at a URL position the company-membership check does not read (see the next test), so ANY signed-in user — including a self-registered stranger (SEC-19) — can delete the logo of ANY company by trying company numbers. Being listed as "Misc UI helper with no data exposure" is what hid it.',
            'Make it a DELETE/POST request, give it a permission (company.update), and load the company through the signed-in user\'s own companies.'
        ));
    }

    /* ──────────────────────────────────────────────────────────────
     * Which company?
     * ────────────────────────────────────────────────────────────── */

    /**
     * canViewCurrentCompany decides "may this user see company N?" by reading the SECOND segment
     * of the URL (/en/{company}/…). A route that carries the company number anywhere else is
     * invisible to that check, so it needs its own — and a route that forgets is a hole that
     * lets any user touch any company.
     *
     * Only REQUIRED company parameters are checked here; the optional `{company?}` on the user
     * and role screens is exercised by AccessControlSecurityTest.
     */
    public function test_the_company_number_sits_where_the_membership_check_looks(): void
    {
        $misplaced = [];

        // Routes that carry the company number elsewhere ON PURPOSE and check membership themselves.
        // Each entry must say where the check lives; keep this list as short as possible.
        $selfChecked = [
            'remove.company.image', // routes/web.php: abort_unless(isSuperAdmin || user's companies contain it) + DELETE + company.update right
        ];

        foreach ($this->applicationRoutes() as $route) {
            if ($this->isAuthenticationScreen($route) || in_array((string) $route->getName(), $selfChecked, true)) {
                continue;
            }

            $segments = explode('/', trim($route->uri(), '/'));

            // If a language code was already baked into the URI when routes were registered, drop it:
            // below we add it back for every route in the locale group, exactly as production does.
            $localeCodes = array_keys((array) config('laravellocalization.supportedLocales', []));

            if (isset($segments[0]) && in_array($segments[0], $localeCodes, true)) {
                array_shift($segments);
            }

            $position = null;

            foreach ($segments as $index => $segment) {
                if (in_array($segment, ['{company}', '{companyId}', '{company_id}'], true)) {
                    $position = $index + 1;

                    break;
                }
            }

            if ($position === null) {
                continue;
            }

            // Routes inside the locale group get "/en" put in front of them in production.
            $inLocaleGroup = in_array('localeSessionRedirect', $route->gatherMiddleware(), true);
            $productionPosition = $position + ($inLocaleGroup ? 1 : 0);

            if ($productionPosition !== 2) {
                $misplaced[] = $this->describe($route)."   → company number is URL segment {$productionPosition}, the check reads segment 2";
            }
        }

        $this->assertSame([], $misplaced, Finding::message(
            'SEC-12b', 'HIGH',
            'The company number is in a place the company-membership check never looks',
            $misplaced,
            'For these routes "does this user belong to company N?" is never asked by the middleware. Unless the controller asks it itself, any signed-in user can act on any company.',
            'Put the company first, as every other route does ({company}/…), or load the company through $request->user()->companies()->findOrFail($id).'
        ));
    }

    /* ──────────────────────────────────────────────────────────────
     * Links that change data
     * ────────────────────────────────────────────────────────────── */

    public function test_nothing_that_changes_data_is_reachable_with_a_plain_link(): void
    {
        $findings = [];

        foreach ($this->applicationRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true) || $this->isAuthenticationScreen($route)) {
                continue;
            }

            $name = (string) $route->getName();
            $subject = $name.' /'.$route->uri();

            if (! preg_match(self::DESTRUCTIVE_PATTERN, $subject)) {
                continue;
            }

            if (isset(self::REVIEWED_HARMLESS_GET_ROUTES[$name]) || isset(self::REVIEWED_HARMLESS_GET_ROUTES[trim($route->uri(), '/')])) {
                continue;
            }

            $findings[] = $this->describe($route);
        }

        $this->assertSame([], $findings, Finding::message(
            'SEC-10', 'MEDIUM',
            'Actions that delete or change data answer to a plain GET link',
            $findings,
            'A GET request can be triggered by a link in an e-mail, a chat message or another website (CSRF: the browser sends the user\'s login cookie along). One click while signed in deletes a rate, an advance payment, an expense, the import cache — or "inserts to main table" — with no confirmation and no CSRF token, because Laravel only checks the token on POST/PUT/PATCH/DELETE. Web crawlers, link-preview bots and browser prefetch also follow GET links.',
            'Change each to DELETE (or POST) and call it from a button with the CSRF token, as the other delete actions already do.'
        ));
    }

    /* ──────────────────────────────────────────────────────────────
     * Protection that must be on every write
     * ────────────────────────────────────────────────────────────── */

    public function test_every_route_that_writes_is_inside_the_csrf_protected_web_group(): void
    {
        $unprotected = [];

        foreach ($this->applicationRoutes() as $route) {
            $writes = array_intersect(['POST', 'PUT', 'PATCH', 'DELETE'], $route->methods());

            if ($writes === [] || in_array('web', $route->gatherMiddleware(), true)) {
                continue;
            }

            $unprotected[] = $this->describe($route);
        }

        $this->assertSame([], $unprotected, Finding::message(
            'SEC-CSRF', 'HIGH',
            'Routes that write data are outside the web group, which carries CSRF protection',
            $unprotected,
            'Another website could submit these as the signed-in user.',
            'Register them in routes/web.php (the web group) or require a token-based guard.'
        ));
    }

    /**
     * Login is protected by Laravel's built-in lock-out (5 tries a minute, per e-mail + address).
     * The other public POST endpoints have nothing: anyone can create accounts, or trigger
     * password-reset e-mails to any address, as fast as they can send requests.
     */
    public function test_public_forms_are_rate_limited(): void
    {
        $unlimited = [];

        foreach ($this->applicationRoutes() as $route) {
            $name = (string) $route->getName();

            if (! $this->isAuthenticationScreen($route)
                || ! in_array('POST', $route->methods(), true)
                || in_array($name, ['login', 'logout'], true)
                || preg_match('#(^|/)(login|logout)$#', $route->uri()) // POST /login is unnamed; it has Laravel's own lock-out
                || $this->requiresLogin($route)) {
                continue;
            }

            $throttled = false;

            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'throttle')) {
                    $throttled = true;
                }
            }

            if (! $throttled) {
                $unlimited[] = $this->describe($route);
            }
        }

        $this->assertSame([], $unlimited, Finding::message(
            'SEC-17', 'LOW',
            'Public forms have no rate limit',
            $unlimited,
            'Unlimited sign-ups and unlimited "reset password" requests allow spam, mail-bombing of real users and mass account creation.',
            "Add ->middleware('throttle:6,1') to these routes, or customise Auth::routes() in routes/web.php."
        ));
    }

    /* ── helpers ──────────────────────────────────────────────────── */

    /**
     * Only routes the CashVero team wrote: controllers under App\ and closures declared in routes/.
     * Routes added by packages (debug bar, Livewire, …) are not this project's code to judge.
     *
     * @return list<LaravelRoute>
     */
    private function applicationRoutes(): array
    {
        $mine = [];

        foreach (Route::getRoutes() as $route) {
            if ($this->isApplicationRoute($route)) {
                $mine[] = $route;
            }
        }

        return $mine;
    }

    private function isApplicationRoute(LaravelRoute $route): bool
    {
        $uses = $route->getAction('uses');

        if ($uses instanceof Closure) {
            $file = (new ReflectionFunction($uses))->getFileName();

            return $file !== false
                && str_starts_with(str_replace('\\', '/', $file), str_replace('\\', '/', base_path('routes')));
        }

        $controller = $route->getControllerClass();

        return is_string($controller) && str_starts_with(ltrim($controller, '\\'), 'App\\');
    }

    /** Login, registration and password-reset screens: the only pages that are meant to be public. */
    private function isAuthenticationScreen(LaravelRoute $route): bool
    {
        $controller = $route->getControllerClass();

        return is_string($controller) && str_starts_with(ltrim($controller, '\\'), 'App\\Http\\Controllers\\Auth\\');
    }

    private function requiresLogin(LaravelRoute $route): bool
    {
        foreach ($route->gatherMiddleware() as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            if ($middleware === 'auth' || str_starts_with($middleware, 'auth:')
                || str_ends_with($middleware, '\\Authenticate')) {
                return true;
            }
        }

        return false;
    }

    private function describe(LaravelRoute $route): string
    {
        $name = $route->getName() ?: 'unnamed';

        return implode('|', array_diff($route->methods(), ['HEAD'])).' /'.ltrim($route->uri(), '/')."  [{$name}]";
    }
}
