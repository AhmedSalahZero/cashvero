<?php

namespace Tests\Feature\Security;

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
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Security\Support\Finding;
use Tests\Feature\Security\Support\UsesScratchAccounts;
use Tests\TestCase;

/**
 * EVERY company route, attacked as a user of company A who tries to reach company B.
 * ==================================================================
 * The earlier security tests probe a handful of screens. This one walks the application's own
 * route table, so a screen added next month is covered automatically.
 *
 * The attacker is a throw-away user who belongs ONLY to company A and has EVERY permission
 * (so a "403" can only come from the company rule, never from a missing permission).
 *
 *   SEC-30  Every route with a {company} in its address, opened with company B's number.
 *   SEC-31  Every route that loads a record by Laravel binding, given a record of company B
 *           under company A's address.
 *   SEC-32  Every route shaped  .../{modelType}/{modelId}  (the generic screens), asked for a
 *           company B invoice under company A's address.
 *
 * "Safe" answers are 401 / 403 / 404 / 419, or a redirect to the login page. Anything else
 * (200, 302 somewhere else, 422, 500...) means the request got past the company check, and is
 * reported with the exact address.
 *
 * SAFETY
 *   - Runs only in a database literally named cashvero_test (otherwise it stops).
 *   - Every request runs inside a transaction that is rolled back.
 *   - Outgoing HTTP (Odoo), queued jobs, mail and notifications are faked: nothing leaves the machine.
 *   - Routes that log the user out are skipped.
 */
#[Group('security')]
class AllRoutesCrossCompanyTest extends TestCase
{
    use UsesScratchAccounts;

    /** Route names reviewed by a human and accepted as open to any company. Keep this EMPTY unless you must. */
    private const REVIEWED_OPEN = [];

    private const SAFE = [401, 403, 404, 419];

    /** Skipped for every verb: running them would end the test session itself. */
    private const NEVER_CALL = '#logout#i';

    /** Skipped for write verbs only: bulk-delete screens (their GET is still tested). */
    private const NO_WRITE = '#truncate|delete-?all|deleteAll#i';

    private const TYPE_PARAMS = ['modelType', 'modelName', 'model_type', 'model_name'];

    private const ID_PARAMS = ['modelId', 'model_id', 'id', 'invoiceId', 'invoice_id'];

    private const GENERIC_MODELS = ['CustomerInvoice' => \App\Models\CustomerInvoice::class, 'SupplierInvoice' => \App\Models\SupplierInvoice::class];

    private $attacker;

    /** When set, requests are sent as this user instead of the all-permissions attacker. */
    private $actingUser = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootScratch(true);

        if (DB::connection('mysql')->getDatabaseName() !== 'cashvero_test') {
            $this->markTestSkipped('This test sends write requests to every route, so it only runs in the database named cashvero_test.');
        }

        // The attack needs a company B that really owns records, so take the two companies with the
        // most invoices (B = the fullest one, A = the next) instead of simply the first two ids.
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

    protected function tearDown(): void
    {
        $this->cleanupScratch();
        parent::tearDown();
    }

    /**
     * In production every screen address starts with the language ("/en/148/daily-logs"), and the
     * company check (canViewCurrentCompany) reads the SECOND part of it. In a test the route table is
     * built without that prefix, so without this step the check would be looking at the wrong part
     * and the test would report problems that do not exist in production.
     */
    private function useProductionAddresses(): void
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

    /* ────────────────────────────── SEC-30 ────────────────────────────── */

    public function test_every_company_route_refuses_a_user_of_another_company(): void
    {
        $leaks = [];
        $checked = 0;

        foreach ($this->companyRoutes() as $route) {
            $recordIds = $this->recordIdsFor($route, $this->companyB->id);

            foreach ($this->verbsOf($route) as $verb) {
                $values = array_merge($recordIds, $this->companyValues($route, $this->companyB->id));
                $status = $this->send($verb, $this->urlFor($route, $values), $location);
                $checked++;

                if (! $this->isSafe($status, $location) && ! $this->reviewed($route)) {
                    $leaks[] = "{$verb} /{$route->uri()}  →  {$this->describe($status, $location)}   (asked: ".$this->urlFor($route, $values).')';
                }
            }
        }

        $this->assertGreaterThan(20, $checked, 'Too few routes were checked ('.$checked.'): the route table could not be read.');

        $this->assertSame([], $leaks, $this->report(
            'SEC-30', 'HIGH',
            'A user of company A got past the company check by writing company B\'s number in the address',
            $leaks, $checked,
            'The request was not stopped by the company rule, so the screen (or action) works for a company the user does not belong to.',
            'Make sure the {company} number is the SECOND part of the address (that is where canViewCurrentCompany looks), or check $request->user()->companies in the controller / a route middleware.'
        ));
    }

    /** Control: with its OWN company the same user must be able to open pages, or "refused" proves nothing. */
    public function test_the_test_user_can_open_pages_of_its_own_company(): void
    {
        $opened = 0;
        $tried = 0;

        foreach ($this->companyRoutes() as $route) {
            if (! in_array('GET', $route->methods(), true) || $this->hasRequiredExtras($route)) {
                continue;
            }

            $tried++;
            $status = $this->send('GET', $this->urlFor($route, $this->companyValues($route, $this->companyA->id)), $location);

            if ($status === 200) {
                $opened++;
            }

            if ($tried >= 80) {
                break;
            }
        }

        $this->assertGreaterThan(0, $opened,
            "Test set-up problem: not one of {$tried} pages of company {$this->companyA->id} opened for the all-permissions test user, so the refusals in SEC-30 prove nothing. Run: php artisan cashvero:prepare-test-db --from=cashvero");
    }

    /* ────────────────────────────── SEC-31 ────────────────────────────── */

    public function test_a_record_of_company_b_cannot_be_loaded_through_company_a(): void
    {
        $leaks = [];
        $checked = 0;

        foreach ($this->companyRoutes() as $route) {
            $bound = $this->boundCompanyModels($route);

            if ($bound === []) {
                continue;
            }

            $values = $this->companyValues($route, $this->companyA->id);

            $planted = 0;

            foreach ($bound as $param => $class) {
                $id = $this->recordOfCompany($class, $this->companyB->id);

                if ($id === null) {
                    continue;
                }

                $values[$param] = $id;
                $planted++;
            }

            // Only attack when a record of company B was really put into the address.
            if ($planted === 0) {
                continue;
            }

            foreach ($this->verbsOf($route) as $verb) {
                $url = $this->urlFor($route, $values);
                $status = $this->send($verb, $url, $location, $validationOnly);
                $checked++;

                if (! $validationOnly && ! $this->isSafe($status, $location) && ! $this->reviewed($route)) {
                    $leaks[] = "{$verb} /{$route->uri()}  →  {$this->describe($status, $location)}   (asked: {$url})";
                }
            }
        }

        $this->assertGreaterThan(0, $checked, 'No route could be attacked: company '.$this->companyB->id.' has no records to plant. Run: php artisan cashvero:prepare-test-db --from=cashvero');

        $this->assertSame([], $leaks, $this->report(
            'SEC-31', 'HIGH',
            'A record of company B was loaded through company A\'s address',
            $leaks, $checked,
            'The screen found the record by its number and did not check that it belongs to the company in the address.',
            'Find the record through the company ($company->relation()->findOrFail($id)) or abort(404) when $record->company_id !== $company->id.'
        ));
    }

    /* ────────────────────────────── SEC-32 ────────────────────────────── */

    public function test_generic_model_screens_cannot_be_pointed_at_another_companys_invoice(): void
    {
        $leaks = [];
        $checked = 0;

        foreach ($this->companyRoutes() as $route) {
            $names = $route->parameterNames();
            $typeParam = collect($names)->first(fn ($n) => in_array($n, self::TYPE_PARAMS, true));
            $idParam = collect($names)->first(fn ($n) => in_array($n, self::ID_PARAMS, true));

            if (! $typeParam || ! $idParam || $this->boundCompanyModels($route) !== []) {
                continue;
            }

            foreach (self::GENERIC_MODELS as $short => $class) {
                $id = $this->recordOfCompany($class, $this->companyB->id);

                if ($id === null) {
                    continue;
                }

                $values = array_merge($this->companyValues($route, $this->companyA->id), [$typeParam => $short, $idParam => $id]);

                foreach ($this->verbsOf($route) as $verb) {
                    $url = $this->urlFor($route, $values);
                    $status = $this->send($verb, $url, $location, $validationOnly);
                    $checked++;

                    if (! $validationOnly && ! $this->isSafe($status, $location) && ! $this->reviewed($route)) {
                        $leaks[] = "{$verb} /{$route->uri()}  →  {$this->describe($status, $location)}   (asked: {$url})";
                    }
                }
            }
        }

        $this->assertGreaterThan(0, $checked, 'No generic {modelType}/{modelId} route could be attacked (no company B invoices on file).');

        $this->assertSame([], $leaks, $this->report(
            'SEC-32', 'HIGH',
            'A generic screen accepted another company\'s invoice number',
            $leaks, $checked,
            'These screens take the model name and the id from the address. If they load the row with Model::find($id), anyone can read or change another customer\'s invoice by guessing numbers.',
            'Load it through the company, e.g. $company->customerInvoices()->findOrFail($id), or compare company_id and abort(404).'
        ));
    }


    /* ────────────────────────────── SEC-33 ────────────────────────────── */

    /**
     * The other kind of wrong access: a user of the RIGHT company who has been given NO permissions.
     * Every route that the permission map protects must refuse them.
     */
    public function test_a_user_with_no_permissions_is_refused_on_every_protected_route(): void
    {
        $this->actingUser = $this->scratchUser([$this->companyA->id], []);

        $leaks = [];
        $checked = 0;

        try {
            foreach ($this->companyRoutes() as $route) {
                $name = $route->getName();

                // Only routes that the permission map protects: public ones are open to every signed-in user by
                // design, and unmapped ones are the separate SEC-11 / SEC-11b finding.
                if ($name === null || \App\Support\Permissions\RoutePermissionMap::isPublic($name)
                    || \App\Support\Permissions\RoutePermissionMap::for($name) === null) {
                    continue;
                }

                $values = array_merge($this->recordIdsFor($route, $this->companyA->id), $this->companyValues($route, $this->companyA->id));

                foreach ($this->verbsOf($route) as $verb) {
                    $url = $this->urlFor($route, $values);
                    $status = $this->send($verb, $url, $location, $validationOnly);
                    $checked++;

                    if (! $validationOnly && ! $this->isSafe($status, $location) && ! $this->reviewed($route)) {
                        $leaks[] = "{$verb} /{$route->uri()}  →  {$this->describe($status, $location)}   (needs: ".implode(', ', \App\Support\Permissions\RoutePermissionMap::for($name)).")";
                    }
                }
            }
        } finally {
            $this->actingUser = null;
        }

        $this->assertGreaterThan(20, $checked, 'Too few protected routes were checked ('.$checked.').');

        $this->assertSame([], $leaks, $this->report(
            'SEC-33', 'HIGH',
            'A user with no permissions got through a route the permission map says needs one',
            $leaks, $checked,
            'The permission rule is listed but does not stop the request, so a user can use screens or actions their role was never given.',
            'Check that the route really carries the permission middleware and that the permission name in RoutePermissionMap exists and is enforced; for controller-level checks, make sure they run before the action.'
        ));
    }

    /* ───────────────────────────── helpers ───────────────────────────── */

    /** @return list<Route> */
    private function companyRoutes(): array
    {
        return collect(RouteFacade::getRoutes()->getRoutes())
            ->filter(fn (Route $r) => (bool) array_intersect($r->parameterNames(), ['company', 'companyId', 'company_id'])
                && ! preg_match(self::NEVER_CALL, $r->uri()))
            ->values()->all();
    }

    /** @return list<string> */
    private function verbsOf(Route $route): array
    {
        $verbs = array_values(array_diff($route->methods(), ['HEAD', 'OPTIONS']));

        if (preg_match(self::NO_WRITE, $route->uri())) {
            $verbs = array_values(array_intersect($verbs, ['GET']));
        }

        return $verbs;
    }

    private function companyValues(Route $route, int $companyId): array
    {
        $values = [];

        foreach ($route->parameterNames() as $name) {
            if (in_array($name, ['company', 'companyId', 'company_id'], true)) {
                $values[$name] = $companyId;
            } elseif (in_array($name, ['lang', 'locale'], true)) {
                $values[$name] = 'en';
            }
        }

        return $values;
    }

    /** Real records of company $companyId for the model-bound parameters of a route (so Laravel can resolve them). */
    private function recordIdsFor(Route $route, int $companyId): array
    {
        $ids = [];

        foreach ($this->boundCompanyModels($route) as $param => $class) {
            $id = $this->recordOfCompany($class, $companyId);

            if ($id !== null) {
                $ids[$param] = $id;
            }
        }

        return $ids;
    }

    /** @return array<string, class-string<Model>> bound parameters whose model carries a company_id */
    private function boundCompanyModels(Route $route): array
    {
        static $cache = [];
        // Keyed by address, not by object number: every test boots a fresh application, and a freed
        // object's number can be reused by a different route.
        $key = implode('|', $route->methods()).' '.$route->uri();

        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $found = [];

        try {
            foreach (RouteSignatureParameters::fromAction($route->getAction(), Model::class) as $parameter) {
                $class = Reflector::getParameterClassName($parameter);

                // Laravel matches {suppliers_opening_balance} to $suppliersOpeningBalance: do the same.
                $name = collect([$parameter->getName(), \Illuminate\Support\Str::snake($parameter->getName())])
                    ->first(fn ($n) => in_array($n, $route->parameterNames(), true));

                if ($name && ! in_array($name, ['company', 'companyId', 'company_id'], true) && $class && is_subclass_of($class, Model::class) && Schema::hasColumn((new $class)->getTable(), 'company_id')) {
                    $found[$name] = $class;
                }
            }
        } catch (\Throwable $e) {
            // A route whose handler cannot be inspected is simply not model-bound for this test.
        }

        return $cache[$key] = $found;
    }

    /** The value the route needs to find a record of that company (its route key). */
    private function recordOfCompany(string $class, int $companyId): string|int|null
    {
        /** @var Model $model */
        $model = (new $class)->newQuery()->where('company_id', $companyId)->first();

        return $model?->getRouteKey();
    }

    /** True when the route needs something we cannot invent (a required parameter besides company/locale). */
    private function hasRequiredExtras(Route $route): bool
    {
        foreach ($route->parameterNames() as $name) {
            if (in_array($name, ['company', 'companyId', 'company_id', 'lang', 'locale'], true)) {
                continue;
            }

            if (! preg_match('#\{'.preg_quote($name, '#').'\?\}#', $route->uri())) {
                return true;
            }
        }

        return false;
    }

    private function urlFor(Route $route, array $values): string
    {
        $uri = preg_replace_callback('/\{(\w+)(\?)?\}/', function ($m) use ($values) {
            if (array_key_exists($m[1], $values)) {
                return (string) $values[$m[1]];
            }

            return ($m[2] ?? '') === '?' ? '' : '1';   // 1 = harmless placeholder for a parameter we cannot know
        }, $route->uri());

        return '/'.trim((string) preg_replace('#/+#', '/', $uri), '/');
    }

    private function send(string $verb, string $url, ?string &$location = null, ?bool &$validationOnly = null): int
    {
        $location = null;
        $validationOnly = false;
        app('session.store')->forget('errors');
        $level = DB::transactionLevel();

        DB::beginTransaction();

        try {
            $response = $this->actingAs($this->actingUser ?? $this->attacker)->call($verb, $url);
            $location = $response->headers->get('Location');
            $status = $response->getStatusCode();

            // A write sent with an empty form is bounced by validation before anything else can happen.
            // That says nothing about the ownership check that comes after it, so it is "inconclusive".
            $errors = app('session.store')->get('errors');
            $validationOnly = $verb !== 'GET' && ($status === 422 || ($status === 302 && $errors && $errors->any()));

            return $status;
        } catch (\Throwable $e) {
            return 599;   // the request got into the application and crashed: not a company-rule answer
        } finally {
            while (DB::transactionLevel() > $level) {
                DB::rollBack();
            }
        }
    }

    private function isSafe(int $status, ?string $location): bool
    {
        if (in_array($status, self::SAFE, true)) {
            return true;
        }

        return in_array($status, [301, 302, 303, 307, 308], true)
            && $location !== null
            && (bool) preg_match('#/(login|logout)\b#i', $location);
    }

    private function describe(int $status, ?string $location): string
    {
        return match (true) {
            $status === 599 => 'crashed inside the application (not stopped by the company rule)',
            $location !== null => "HTTP {$status} redirect to {$location}",
            default => "HTTP {$status}",
        };
    }

    private function reviewed(Route $route): bool
    {
        return $route->getName() !== null && in_array($route->getName(), self::REVIEWED_OPEN, true);
    }

    private function report(string $id, string $severity, string $title, array $leaks, int $checked, string $why, string $fix): string
    {
        if ($leaks === []) {
            return '';
        }

        return Finding::message($id, $severity, $title, $leaks, $why, $fix)
            ."\n\n{$checked} requests were sent; ".count($leaks)." got past the company check. Full list:\n"
            .implode("\n", $leaks);
    }
}
