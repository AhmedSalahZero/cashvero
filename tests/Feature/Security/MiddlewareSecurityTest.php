<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\canViewCurrentCompany;
use App\Http\Middleware\CashManagementMiddleware;
use App\Http\Middleware\EnforcePermission;
use App\Http\Middleware\EnsureRouteModelsBelongToCompany;
use App\Http\Middleware\RequirePermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as LaravelRoute;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

/**
 * SECURITY AUDIT — the three gates every request passes through.
 *
 *   1. canViewCurrentCompany            "does this user belong to company N?"
 *   2. EnsureRouteModelsBelongToCompany "does the record in the URL belong to company N?"
 *   3. EnforcePermission                "may this user do this action at all?"
 *
 * Each is fed hand-made requests directly, so the rule itself is proved
 * rather than assumed. No database, no web server: the users are in-memory
 * objects.
 *
 * Nothing else in the project tests (1) or (2): MessagingTest switches them
 * off to reach its controller. These tests exist so that someone "simplifying"
 * a middleware cannot silently open the door between companies.
 *
 * Several tests below are CHARACTERISATION tests: they pin down a limit of the
 * current design (for example "the check only reads URL segment 2"). They pass
 * today and are not findings by themselves; they document WHY other tests in
 * this folder (RouteSecurityTest, AccessControlSecurityTest) insist on certain
 * things.
 */
#[Group('security')]
class MiddlewareSecurityTest extends TestCase
{
    /* ──────────────────────────────────────────────────────────────
     * 1. Company membership
     * ────────────────────────────────────────────────────────────── */

    public function test_a_member_of_the_company_in_the_url_is_let_through(): void
    {
        $request = $this->requestFor('/en/7/dashboard', $this->userWithCompanies([7, 9]));

        $this->assertNull($this->runGate(new canViewCurrentCompany, $request));
    }

    public function test_a_user_who_is_not_a_member_of_the_company_in_the_url_is_refused(): void
    {
        $request = $this->requestFor('/en/8/dashboard', $this->userWithCompanies([7, 9]));

        $this->assertSame(
            403,
            $this->runGate(new canViewCurrentCompany, $request),
            'A user of companies 7 and 9 opened a company 8 URL and was NOT refused. Every company-scoped page would show another company\'s data.'
        );
    }

    /**
     * CHARACTERISATION. The check reads URL segment 2 and only acts when it is a number. A route
     * that carries the company anywhere else is not checked at all. That is why
     * RouteSecurityTest::test_the_company_number_sits_where_the_membership_check_looks exists.
     */
    public function test_the_check_only_acts_when_the_second_url_segment_is_a_number(): void
    {
        $outsider = $this->userWithCompanies([]);

        $this->assertNull(
            $this->runGate(new canViewCurrentCompany, $this->requestFor('/en/remove-company-image/8', $outsider)),
            'Expected the membership check to be skipped when segment 2 is not a number (documented design limit).'
        );

        $this->assertNull(
            $this->runGate(new canViewCurrentCompany, $this->requestFor('/8/dashboard', $outsider)),
            'Without the /en prefix the company number is segment 1 and the check is skipped.'
        );
    }

    /* ──────────────────────────────────────────────────────────────
     * 2. Records belong to the company
     * ────────────────────────────────────────────────────────────── */

    public function test_a_record_of_the_same_company_is_let_through(): void
    {
        $request = $this->boundRequest('/7/invoices/99', '{company}/invoices/{invoice}', ['invoice' => $this->recordOfCompany(7)]);

        $this->assertNull($this->runGate(new EnsureRouteModelsBelongToCompany, $request));
    }

    public function test_a_record_of_another_company_is_refused(): void
    {
        $request = $this->boundRequest('/7/invoices/99', '{company}/invoices/{invoice}', ['invoice' => $this->recordOfCompany(8)]);

        $this->assertSame(
            404,
            $this->runGate(new EnsureRouteModelsBelongToCompany, $request),
            'A company-7 URL pointing at a company-8 record was NOT refused: cross-company access through route-model binding.'
        );
    }

    /**
     * CHARACTERISATION. Only records that Laravel loaded for the route ({invoice} bound to a model)
     * are checked. A plain id in the URL ({modelId}) is just a string to this middleware, so a
     * controller that does `Model::find($id)` with it is unprotected unless it filters by company
     * itself. Several do not: see AccessControlSecurityTest (SEC-13).
     */
    public function test_plain_ids_in_the_url_are_not_checked_so_controllers_must_scope_their_own_lookups(): void
    {
        $request = $this->boundRequest('/7/adjust-due-dates/99/CustomerInvoice', '{company}/adjust-due-dates/{modelId}/{modelType}', []);

        $this->assertNull(
            $this->runGate(new EnsureRouteModelsBelongToCompany, $request),
            'Expected plain ids to pass this middleware untouched (documented design limit).'
        );
    }

    /* ──────────────────────────────────────────────────────────────
     * 3. Permissions
     * ────────────────────────────────────────────────────────────── */

    /**
     * CHARACTERISATION — the mechanism behind finding SEC-11.
     * EnforcePermission looks the rule up by route NAME. With no name it returns immediately, for
     * everyone. So an unnamed route is protected by login alone, whatever the user's permissions.
     */
    public function test_the_permission_check_does_nothing_for_a_route_without_a_name(): void
    {
        $request = Request::create('/anything');
        $route = new LaravelRoute('GET', 'anything', fn () => 'ok');
        $route->bind($request);
        $request->setRouteResolver(static fn () => $route);
        $request->setUserResolver(fn () => $this->userWithCompanies([]));

        $this->assertNull(
            $this->runGate(new EnforcePermission, $request),
            'Expected an unnamed route to bypass EnforcePermission (this is the weakness RouteSecurityTest reports as SEC-11).'
        );
    }

    public function test_the_explicit_permission_gate_refuses_a_guest(): void
    {
        $request = $this->requestFor('/en/roles/all', null);

        $this->assertSame(401, $this->runGate(new RequirePermission, $request, 'role.view'));
    }

    public function test_the_cash_management_gate_refuses_a_guest(): void
    {
        $this->assertSame(403, $this->runGate(new CashManagementMiddleware, $this->requestFor('/en/user/all', null)));
    }

    public function test_the_cash_management_gate_refuses_an_account_flagged_as_deleted(): void
    {
        $user = $this->userWithCompanies([7]);
        $user->deleted_at = now();

        $this->assertSame(
            403,
            $this->runGate(new CashManagementMiddleware, $this->requestFor('/en/user/all', $user)),
            'An account flagged as deleted (deleted_at set) must not pass this gate, even with a stale session.'
        );
    }

    /* ── helpers ──────────────────────────────────────────────────── */

    /**
     * Run a middleware and report what happened.
     *
     * @return int|null  null = the request was let through; otherwise the HTTP status it was refused with
     */
    private function runGate(object $middleware, Request $request, string ...$arguments): ?int
    {
        try {
            $middleware->handle($request, static fn () => response('let through'), ...$arguments);

            return null;
        } catch (HttpException $e) {
            return $e->getStatusCode();
        }
    }

    /** A GET request for $uri made by $user (null = a guest). */
    private function requestFor(string $uri, ?User $user): Request
    {
        $request = Request::create($uri, 'GET');
        $request->setUserResolver(static fn () => $user);

        // canViewCurrentCompany reads the *global* request(), not the one it is handed.
        // Binding it makes Laravel reset the user resolver, so set ours AFTER binding.
        $this->app->instance('request', $request);
        $request->setUserResolver(static fn () => $user);

        return $request;
    }

    /** An in-memory user attached to the given company ids. No database is touched. */
    private function userWithCompanies(array $companyIds): User
    {
        $user = new User;
        $user->setRelation('companies', collect(array_map(static fn (int $id) => (object) ['id' => $id], $companyIds)));

        return $user;
    }

    /**
     * A request whose route has been matched against $routeUri, as it would be at run time,
     * with some parameters replaced by already-loaded models.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function boundRequest(string $path, string $routeUri, array $overrides): Request
    {
        $request = Request::create($path, 'GET');
        $route = new LaravelRoute('GET', $routeUri, static fn () => 'ok');
        $route->bind($request);

        foreach ($overrides as $name => $value) {
            $route->setParameter($name, $value);
        }

        $request->setRouteResolver(static fn () => $route);

        return $request;
    }

    /** An in-memory record that carries a company_id, like an invoice or a payment. */
    private function recordOfCompany(int $companyId): Model
    {
        $record = new class extends Model
        {
            protected $guarded = [];
        };

        $record->setRawAttributes(['id' => 99, 'company_id' => $companyId]);

        return $record;
    }
}
