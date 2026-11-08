<?php

namespace Tests\Feature\Security;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Security\Support\AttacksRoutes;
use Tests\Feature\Security\Support\Finding;
use Tests\TestCase;

/**
 * SCREENS THAT TAKE A PLAIN RECORD NUMBER, AND SCREENS ABOUT OTHER COMPANIES' USERS
 * ==================================================================
 * Laravel can check "does this record belong to the company in the address?" for screens that name the
 * record's model. A screen shaped  .../edit/{id}  does not: the controller looks the record up itself
 * (Invoice::find($id)). If that lookup forgets the company, a user can read or change another company's record
 * by changing a number.
 *
 *   SEC-37a  For every route with exactly one "plain" number in its address, the controller's own source is read
 *            to find which company-owned model it loads; then a record of company B is asked for under
 *            company A's address. (A record of company A is asked for first as a control: if that is refused too,
 *            the route cannot be judged and is skipped.)
 *   SEC-37b  Every route that loads a User (user-permissions, user edit...) is asked for a user who belongs only to
 *            company B.
 */
#[Group('security')]
class PlainRecordNumberSecurityTest extends TestCase
{
    use AttacksRoutes;

    /** Route names reviewed by a human and accepted. Keep EMPTY unless you must. */
    private const REVIEWED_OPEN = [
        // Reviewed by hand: the invoice lookup filters every invoice by the company in the address, so a partner of
        // another company simply yields an empty list.
        'lookup.money.received.invoice.numbers',
        'lookup.money.payment.invoice.numbers',
    ];

    /** A record number that cannot exist. */
    private const MISSING_ID = 2147483000;

    private const TYPE_PARAMS = ['modelType', 'modelName', 'model_type', 'model_name'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootAttack();
    }

    protected function tearDown(): void
    {
        $this->cleanupScratch();
        parent::tearDown();
    }

    /* ────────────────────────────── SEC-37a ────────────────────────────── */

    public function test_screens_with_a_plain_record_number_cannot_reach_another_companys_records(): void
    {
        $a = $this->companyA->id;
        $b = $this->companyB->id;

        $leaks = [];
        $attacked = 0;
        $routesJudged = 0;
        $routesUnreadable = 0;
        $crashed = 0;

        foreach ($this->allRoutes() as $route) {
            $plain = $this->plainParams($route);

            if (count($plain) !== 1 || in_array($plain[0], self::TYPE_PARAMS, true) || $this->reviewed($route)) {
                continue;
            }

            $param = $plain[0];
            $models = $this->modelsLoadedBy($route);

            if ($models === []) {
                $routesUnreadable++;

                continue;
            }

            $judged = false;

            foreach (array_slice($models, 0, 3) as $class) {
                $idB = $this->recordOfCompany($class, $b);
                $idA = $this->recordOfCompany($class, $a);

                if ($idB === null || $idA === null) {
                    continue;
                }

                $base = array_merge($this->companyValues($route, $a), $this->recordIdsFor($route, $a));

                foreach ($this->verbsOf($route) as $verb) {
                    // Control: the same request for the user's OWN company's record must reach the application.
                    $control = $this->send($verb, $this->urlFor($route, array_merge($base, [$param => $idA])), $cl, $cv);

                    if ($this->isSafe($control, $cl)) {
                        continue;   // refused even for its own record: the route cannot be judged this way
                    }

                    $judged = true;
                    $url = $this->urlFor($route, array_merge($base, [$param => $idB]));
                    $status = $this->send($verb, $url, $location, $validationOnly);
                    $bodyB = str_replace((string) $idB, '#', $this->lastBody);
                    $attacked++;

                    if ($validationOnly || $this->isSafe($status, $location)) {
                        continue;
                    }

                    // A crash (HTTP 500) says the request was malformed for this screen, not that a record was handed over.
                    if ($status >= 500) {
                        $crashed++;

                        continue;
                    }

                    // Some screens answer an unknown number with a friendly page or a redirect instead of a 404.
                    // If company B's record gets exactly the same answer as a number that does not exist, nothing leaked.
                    $missing = $this->send($verb, $this->urlFor($route, array_merge($base, [$param => self::MISSING_ID])), $missingLocation, $missingValidation);
                    $bodyMissing = str_replace((string) self::MISSING_ID, '#', $this->lastBody);

                    if ($status === $missing
                        && str_replace((string) $idB, '#', (string) $location) === str_replace((string) self::MISSING_ID, '#', (string) $missingLocation)
                        && $bodyB === $bodyMissing) {
                        continue;
                    }

                    $leaks[] = "{$verb} /{$route->uri()}  →  {$this->describe($status, $location)}   (asked for a ".class_basename($class)." of company {$b}: {$url})";
                }
            }

            $judged ? $routesJudged++ : null;
        }

        $this->assertGreaterThan(0, $attacked, 'No screen with a plain record number could be attacked (no company B records, or the controllers could not be read).');

        $this->assertSame([], $leaks, Finding::message(
            'SEC-37a', 'HIGH',
            'A screen with a plain record number loaded a record of another company',
            $leaks,
            'The screen finds the record by its number alone. Anyone can read, change or delete another customer\'s record by changing the number in the address.',
            'Look the record up through the company ($company->relation()->findOrFail($id)) or add ->where(\'company_id\', $company->id) before find/first, or abort(404) when $record->company_id differs.'
        )."\n\n{$routesJudged} routes were judged ({$attacked} attacks, {$crashed} of them crashed the screen and are not counted); {$routesUnreadable} routes could not be analysed because their controller does not load a company-owned model directly (they are covered by the manual review in the Penetration Test Brief).");
    }

    /* ────────────────────────────── SEC-37b ────────────────────────────── */

    public function test_screens_cannot_be_pointed_at_a_user_of_another_company(): void
    {
        $a = $this->companyA->id;

        $victim = $this->scratchUser([$this->companyB->id], []);
        $colleague = $this->scratchUser([$a], []);

        $leaks = [];
        $attacked = 0;
        $controlOpened = 0;

        foreach ($this->allRoutes() as $route) {
            $users = array_filter($this->boundModels($route, false), fn ($class) => is_a($class, \App\Models\User::class, true));

            if ($users === [] || $this->reviewed($route)) {
                continue;
            }

            $param = array_key_first($users);
            $base = array_merge($this->companyValues($route, $a), $this->recordIdsFor($route, $a));

            foreach ($this->verbsOf($route) as $verb) {
                $payload = $this->isWrite($verb) ? ['permissions' => []] : [];

                if ($verb === 'GET') {
                    $control = $this->send($verb, $this->urlFor($route, array_merge($base, [$param => $colleague->id])), $cl, $cv);

                    if (! $this->isSafe($control, $cl)) {
                        $controlOpened++;
                    }
                }

                $url = $this->urlFor($route, array_merge($base, [$param => $victim->id]));
                $status = $this->send($verb, $url, $location, $validationOnly, $payload);
                $attacked++;

                if (! $validationOnly && ! $this->isSafe($status, $location)) {
                    $leaks[] = "{$verb} /{$route->uri()}  →  {$this->describe($status, $location)}   (asked for a user of company {$this->companyB->id}: {$url})";
                }
            }
        }

        $this->assertGreaterThan(0, $attacked, 'No route that loads a User was found.');
        $this->assertGreaterThan(0, $controlOpened, 'Test set-up problem: no user screen could be opened even for a colleague of the same company, so the refusals prove nothing.');

        $this->assertSame([], $leaks, Finding::message(
            'SEC-37b', 'HIGH',
            'A screen about a user accepted a user of another company',
            $leaks,
            'A manager of one company could read, edit the permissions of, or delete a user of a different company by changing the user number.',
            'Before acting, check the target user shares a company with the actor (or the actor is a Super Admin), and abort(403/404) otherwise.'
        )."\n\n{$attacked} requests were sent.");
    }

    /* ───────────────────────────── helpers ───────────────────────────── */

    private function reviewed(Route $route): bool
    {
        return $route->getName() !== null && in_array($route->getName(), self::REVIEWED_OPEN, true);
    }

    /**
     * Company-owned models the route's controller method loads by number (Invoice::find($id), Model::where(...)),
     * read from its source code. Models without a company_id column are ignored.
     *
     * @return list<class-string<Model>>
     */
    private function modelsLoadedBy(Route $route): array
    {
        static $cache = [];
        $key = $route->getActionName();

        if (isset($cache[$key])) {
            return $cache[$key];
        }

        $models = [];

        try {
            [$class, $method] = Str::parseCallback($route->getActionName(), '__invoke');

            if ($class && class_exists($class) && method_exists($class, $method)) {
                $reflection = new \ReflectionMethod($class, $method);
                $lines = file((string) $reflection->getFileName()) ?: [];
                $body = implode('', array_slice($lines, $reflection->getStartLine() - 1, $reflection->getEndLine() - $reflection->getStartLine() + 1));

                preg_match_all('/\b([A-Z][A-Za-z0-9_]+)::(?:withTrashed\(\)->|query\(\)->|with\([^)]*\)->)?(?:find|findOrFail|firstOrFail|first|where|findMany)\s*\(/', $body, $m);

                foreach (array_unique($m[1]) as $short) {
                    $full = 'App\\Models\\'.$short;

                    if (class_exists($full) && is_subclass_of($full, Model::class) && Schema::hasColumn((new $full)->getTable(), 'company_id')) {
                        $models[] = $full;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Unreadable handler: not analysed.
        }

        return $cache[$key] = $models;
    }
}
