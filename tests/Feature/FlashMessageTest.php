<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\TestCase;

/**
 * Guards the flash-message contract: a success/error message reaches the
 * page exactly once and then it is gone.
 *
 * The bug this locks down: a dozen call sites (the Odoo readers,
 * OdooPayment, OdooSync) wrote their error with session()->put('fail'),
 * which is a permanent session key, and HandleInertiaRequests read it
 * with get(), which does not consume it. One failed Odoo sync therefore
 * re-toasted the same message on every page for the rest of the session
 * — it survived reloads, survived fixing the underlying cause, and rode
 * along with unrelated success messages afterwards.
 *
 * Runs against the development database like PaginationSmokeTest, for
 * the same reason: it needs a real user, company and route to exercise.
 * Every request here is a read-only GET.
 */
class FlashMessageTest extends TestCase
{
    private ?User $actor = null;

    private string $home = '';

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.mysql.database' => env('SMOKE_DB', 'cash-vero')]);
        DB::purge('mysql');

        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Development database not reachable: '.$e->getMessage());
        }

        // See PaginationSmokeTest: without this every request 302s on the
        // missing /{lang} segment before reaching a controller.
        $this->withoutMiddleware([
            LocaleSessionRedirect::class,
            LaravelLocalizationRedirectFilter::class,
            LaravelLocalizationViewPath::class,
        ]);

        $this->actor = User::whereHas('roles', fn ($q) => $q->where('roles.id', 1))->first() ?? User::first();
        $company = Company::first();

        if (! $this->actor || ! $company) {
            $this->markTestSkipped('Development database has no user/company to exercise.');
        }

        $this->home = route('contracts.index', ['company' => $company->id, 'type' => 'Customer']);

        /*
         * Inertia's asset version is derived from the Vite manifest and is
         * only pushed onto the facade while a request is being handled, so
         * before the first one Inertia::getVersion() is null and every
         * X-Inertia GET answers 409 (version conflict). One throwaway visit
         * settles it; its status is deliberately not asserted.
         */
        $this->actingAs($this->actor)->get($this->home, ['X-Inertia' => 'true']);
    }

    /**
     * @return array{success: mixed, error: mixed, token: mixed}
     */
    private function visitAndReadFlash(): array
    {
        $response = $this->actingAs($this->actor)->get($this->home, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => Inertia::getVersion(),
        ]);

        $this->assertSame(200, $response->getStatusCode(), 'The page under test did not return 200.');

        return $response->json('props.flash');
    }

    public function test_an_error_message_is_delivered_once_and_then_gone(): void
    {
        session()->flash('fail', 'ODOO EXPLODED');

        $first = $this->visitAndReadFlash();
        $this->assertSame('ODOO EXPLODED', $first['error']);

        $second = $this->visitAndReadFlash();
        $this->assertNull($second['error'], 'The error came back on the next visit — it is not being consumed.');
        $this->assertNull($second['token']);
    }

    /**
     * The regression itself: even a permanent put() must not wedge a
     * message on screen, because the middleware consumes the key.
     */
    public function test_a_permanently_put_message_still_does_not_stick(): void
    {
        session()->put('fail', 'STUCK FOREVER');

        $this->assertSame('STUCK FOREVER', $this->visitAndReadFlash()['error']);
        $this->assertNull(
            $this->visitAndReadFlash()['error'],
            'A session()->put() message survived a second visit — it will re-toast on every page.'
        );
    }

    /**
     * A stale error must not tag along with a later, unrelated success.
     */
    public function test_a_stale_error_does_not_ride_along_with_a_later_success(): void
    {
        session()->put('fail', 'OLD FAILURE');

        // The user sees it once, here
        $this->assertSame('OLD FAILURE', $this->visitAndReadFlash()['error']);

        // Later, an unrelated action succeeds
        session()->flash('success', 'Contracts Reading Has Been Completed');
        $afterSuccess = $this->visitAndReadFlash();

        $this->assertSame('Contracts Reading Has Been Completed', $afterSuccess['success']);
        $this->assertNull($afterSuccess['error'], 'The old failure was shown again next to a fresh success.');
    }

    /**
     * Inertia::always() is what keeps flash alive on partial reloads; a
     * plain shared prop is skipped on `only`/`except` visits and the
     * message would surface a navigation late.
     */
    public function test_a_partial_reload_still_carries_the_message(): void
    {
        session()->flash('fail', 'PARTIAL RELOAD MESSAGE');

        $response = $this->actingAs($this->actor)->get($this->home, [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => Inertia::getVersion(),
            'X-Inertia-Partial-Data' => 'contracts',
            'X-Inertia-Partial-Component' => 'Contracts/Index',
        ]);

        $this->assertSame('PARTIAL RELOAD MESSAGE', $response->json('props.flash.error'));
    }
}
