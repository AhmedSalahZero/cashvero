<?php

namespace Tests\Feature\Security\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Routing\RouteSignatureParameters;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Reflector;
use Illuminate\Support\Str;

/**
 * Shared machinery for the security tests that ATTACK the running application as a throw-away user.
 *
 * It is the same set-up AllRoutesCrossCompanyTest uses (kept separate so that proven test stays untouched):
 *   - only runs in a database literally named cashvero_test (every request is rolled back);
 *   - company B = the company with the most invoices, company A = the next one;
 *   - screen addresses start with the language prefix, like in production;
 *   - outgoing HTTP, queues, mail and notifications are faked.
 *
 * A test class uses ONLY this trait (it already brings UsesScratchAccounts) and calls bootAttack() from setUp().
 */
trait AttacksRoutes
{
    use UsesScratchAccounts;

    protected const SAFE_STATUSES = [401, 403, 404, 419];

    /** Never called: running them would end the test session itself. */
    protected const NEVER_CALL_PATTERN = '#logout#i';

    /** Bulk-delete screens: their GET is still exercised, the write verbs are not. */
    protected const NO_WRITE_PATTERN = '#truncate|delete-?all|deleteAll#i';

    protected const COMPANY_PARAMS = ['company', 'companyId', 'company_id'];

    protected $attacker;

    protected function bootAttack(): void
    {
        $this->bootScratch(true);

        if (DB::connection('mysql')->getDatabaseName() !== 'cashvero_test') {
            $this->markTestSkipped('This test sends write requests to the application, so it only runs in the database named cashvero_test.');
        }

        $busiest = DB::table('customer_invoices')->select('company_id', DB::raw('count(*) as n'))
            ->groupBy('company_id')->orderByDesc('n')->limit(2)->pluck('company_id')->all();

        if (count($busiest) === 2) {
            $this->companyB = \App\Models\Company::find($busiest[0]);
            $this->companyA = \App\Models\Company::find($busiest[1]);
            $this->enableCashVeroForScratchDatabase();
        }

        $this->useProductionAddresses();
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);
        Http::fake();
        Queue::fake();
        Mail::fake();
        Notification::fake();

        $this->attacker = $this->scratchUser(
            [$this->companyA->id],
            \Spatie\Permission\Models\Permission::where('guard_name', 'web')->pluck('name')->all()
        );
    }

    /** In production every address starts with the language ("/en/148/..."); a test route table does not. */
    protected function useProductionAddresses(): void
    {
        $compiled = new \ReflectionProperty(Route::class, 'compiled');
        $compiled->setAccessible(true);

        foreach (RouteFacade::getRoutes()->getRoutes() as $route) {
            if (in_array('localeSessionRedirect', $route->middleware(), true) && ! str_starts_with($route->uri(), 'en/')) {
                $route->prefix('en');
                $compiled->setValue($route, null);
            }
        }

        RouteFacade::getRoutes()->refreshNameLookups();
        RouteFacade::getRoutes()->refreshActionLookups();
    }

    /* ───────────────────────── route table helpers ───────────────────────── */

    /** @return list<Route> every route the test may call */
    protected function allRoutes(): array
    {
        return collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $r) => ! preg_match(self::NEVER_CALL_PATTERN, $r->uri()))
            ->values()->all();
    }

    protected function hasCompanyParam(Route $route): bool
    {
        return (bool) array_intersect($route->parameterNames(), self::COMPANY_PARAMS);
    }

    /** @return list<string> */
    protected function verbsOf(Route $route): array
    {
        $verbs = array_values(array_diff($route->methods(), ['HEAD', 'OPTIONS']));

        if (preg_match(self::NO_WRITE_PATTERN, $route->uri())) {
            $verbs = array_values(array_intersect($verbs, ['GET']));
        }

        return $verbs;
    }

    protected function isWrite(string $verb): bool
    {
        return in_array($verb, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }

    protected function companyValues(Route $route, int $companyId): array
    {
        $values = [];

        foreach ($route->parameterNames() as $name) {
            if (in_array($name, self::COMPANY_PARAMS, true)) {
                $values[$name] = $companyId;
            } elseif (in_array($name, ['lang', 'locale'], true)) {
                $values[$name] = 'en';
            }
        }

        return $values;
    }

    /**
     * Parameters the route binds to an Eloquent model.
     *
     * @return array<string, class-string<Model>>  parameter name => model class
     */
    protected function boundModels(Route $route, bool $companyOwnedOnly = true): array
    {
        static $cache = [];
        $key = implode('|', $route->methods()).' '.$route->uri().($companyOwnedOnly ? ' c' : ' a');

        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $found = [];

        try {
            foreach (RouteSignatureParameters::fromAction($route->getAction(), Model::class) as $parameter) {
                $class = Reflector::getParameterClassName($parameter);

                $name = collect([$parameter->getName(), Str::snake($parameter->getName())])
                    ->first(fn ($n) => in_array($n, $route->parameterNames(), true));

                if (! $name || in_array($name, self::COMPANY_PARAMS, true) || ! $class || ! is_subclass_of($class, Model::class)) {
                    continue;
                }

                if (! $companyOwnedOnly || Schema::hasColumn((new $class)->getTable(), 'company_id')) {
                    $found[$name] = $class;
                }
            }
        } catch (\Throwable $e) {
            // A handler that cannot be inspected is simply not model-bound for the tests.
        }

        return $cache[$key] = $found;
    }

    /** Real records of a company for the model-bound parameters (so Laravel can resolve them). */
    protected function recordIdsFor(Route $route, int $companyId): array
    {
        $ids = [];

        foreach ($this->boundModels($route) as $param => $class) {
            $id = $this->recordOfCompany($class, $companyId);

            if ($id !== null) {
                $ids[$param] = $id;
            }
        }

        return $ids;
    }

    protected function recordOfCompany(string $class, int $companyId): string|int|null
    {
        return (new $class)->newQuery()->where('company_id', $companyId)->first()?->getRouteKey();
    }

    /** Parameters that are neither the company, the language, nor a bound model. */
    protected function plainParams(Route $route): array
    {
        $bound = array_keys($this->boundModels($route, false));

        return array_values(array_filter(
            $route->parameterNames(),
            fn ($n) => ! in_array($n, array_merge(self::COMPANY_PARAMS, ['lang', 'locale'], $bound), true)
                && ! preg_match('#\{'.preg_quote($n, '#').'\?\}#', $route->uri())
        ));
    }

    protected function urlFor(Route $route, array $values): string
    {
        $uri = preg_replace_callback('/\{(\w+)(\?)?\}/', function ($m) use ($values) {
            if (array_key_exists($m[1], $values)) {
                return (string) $values[$m[1]];
            }

            return ($m[2] ?? '') === '?' ? '' : '1';
        }, $route->uri());

        return '/'.trim((string) preg_replace('#/+#', '/', $uri), '/');
    }

    /* ─────────────────────────── database helpers ─────────────────────────── */

    /** @return list<string> tables that carry a company_id column */
    protected function companyTables(): array
    {
        static $tables = null;

        return $tables ??= collect(DB::connection('mysql')->select(
            'select table_name as t from information_schema.columns where table_schema = ? and column_name = ?',
            [DB::connection('mysql')->getDatabaseName(), 'company_id']
        ))->pluck('t')->unique()->values()->all();
    }

    /** @return list<array{0:string,1:string}> [table, column] pairs whose column name ends in _id, on company tables */
    protected function foreignKeyColumns(): array
    {
        $tables = $this->companyTables();

        $rows = DB::connection('mysql')->select(
            "select table_name as t, column_name as c from information_schema.columns where table_schema = ? and column_name like '%\\_id'",
            [DB::connection('mysql')->getDatabaseName()]
        );

        return collect($rows)->filter(fn ($r) => in_array($r->t, $tables, true))
            ->map(fn ($r) => [$r->t, $r->c])->values()->all();
    }

    /* ───────────────────────────── sending ───────────────────────────── */

    /**
     * Send one request inside a transaction that is always rolled back.
     *
     * @param  callable|null  $inspect  runs after the response and BEFORE the rollback, receiving (status, location, validationOnly); its result is left in $this->inspected
     */
    protected function send(
        string $verb,
        string $url,
        ?string &$location = null,
        ?bool &$validationOnly = null,
        array $payload = [],
        ?callable $inspect = null,
        $actor = null
    ): int {
        $location = null;
        $validationOnly = false;
        $this->inspected = null;
        $this->lastBody = '';
        app('session.store')->forget('errors');
        $level = DB::transactionLevel();

        DB::beginTransaction();

        try {
            $response = $this->actingAs($actor ?? $this->attacker)->call($verb, $url, $payload);
            $location = $response->headers->get('Location');
            $status = $response->getStatusCode();
            $this->lastBody = (string) $response->getContent();

            $errors = app('session.store')->get('errors');
            $validationOnly = $this->isWrite($verb) && ($status === 422 || ($status === 302 && $errors && $errors->any()));

            if ($inspect !== null) {
                $this->inspected = $inspect($status, $location, $validationOnly);
            }

            return $status;
        } catch (\Throwable $e) {
            return 599;
        } finally {
            while (DB::transactionLevel() > $level) {
                DB::rollBack();
            }
        }
    }

    /** Result of the $inspect callback of the last send(). */
    protected $inspected = null;

    /** Body of the last response (empty when the request crashed). */
    protected string $lastBody = '';

    protected function isSafe(int $status, ?string $location): bool
    {
        if (in_array($status, self::SAFE_STATUSES, true)) {
            return true;
        }

        return in_array($status, [301, 302, 303, 307, 308], true)
            && $location !== null
            && (bool) preg_match('#/(login|logout)\b#i', $location);
    }

    /** The request was accepted and (probably) did what it asked: a 2xx, or a redirect that is not a login bounce or an error bounce. */
    protected function looksSuccessful(int $status, ?string $location, bool $validationOnly): bool
    {
        if ($validationOnly || $status === 599) {
            return false;
        }

        if ($status >= 200 && $status < 300) {
            return true;
        }

        return in_array($status, [301, 302, 303, 307, 308], true)
            && ! ($location !== null && preg_match('#/(login|logout)\b#i', $location));
    }

    protected function describe(int $status, ?string $location): string
    {
        return match (true) {
            $status === 599 => 'crashed inside the application (not stopped by a rule)',
            $location !== null => "HTTP {$status} redirect to {$location}",
            default => "HTTP {$status}",
        };
    }
}
