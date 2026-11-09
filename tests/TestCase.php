<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use Concerns\CreatesCompanies;

    /**
     * EVERY test runs inside a transaction that is rolled back afterwards, on both connections a
     * test can reach. Nothing a test writes survives it, so no test depends on what another one
     * left behind, and the order they run in does not matter.
     */
    use \Illuminate\Foundation\Testing\DatabaseTransactions;

    /** @var array<int, string|null> */
    protected $connectionsToTransact = [null, 'mysql_source'];

    /** The only databases a test may be connected to. */
    private const TEST_DATABASES = ['cashvero_test', 'cashvero_test_scratch'];

    /**
     * Per-request memos the app keeps in static properties. A static survives from one test to
     * the next in the same PHP process, so a permission or a rate one test made would still be
     * "remembered" by the next. Emptied before every test.
     */
    private const STATIC_MEMOS = [
        \App\Support\Permissions\PermissionResolver::class => ['nameCache' => [], 'keyCache' => []],
        \App\Models\ForeignExchangeRate::class => ['exchangeRateRequestMemo' => []],
        \App\Services\Api\OdooSync::class => ['deferred' => [], 'depth' => 0],
        \App\Support\Serials\SerialAssigner::class => ['preserved' => []],
        \App\Support\Activity\ActivityLogger::class => ['capture' => [], 'muted' => 0],
        \App\Support\ShareholderAccounts\AccountNumberLabel::class => ['nameCache' => []],
        \App\Http\Middleware\EnforcePermission::class => ['logged' => []],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->refuseAnythingButTheTestDatabases();
        $this->forgetStaticMemos();
        $this->withoutVite();

        // No test ever talks to a real Odoo server (see FakeOdooClientFactory).
        $this->app->instance(\App\Services\Api\OdooClientFactory::class, new Fakes\FakeOdooClientFactory);
    }

    /** The stand-in Odoo every test talks to — tell it what to answer, then check what it was asked. */
    protected function odoo(): Fakes\FakeOdooClientFactory
    {
        return $this->app->make(\App\Services\Api\OdooClientFactory::class);
    }

    private function forgetStaticMemos(): void
    {
        foreach (self::STATIC_MEMOS as $class => $properties) {
            foreach ($properties as $property => $emptyValue) {
                (new \ReflectionProperty($class, $property))->setValue(null, $emptyValue);
            }
        }
    }

    /**
     * A second line of defence after the MySQL account itself (cashvero_tester can only reach
     * the test databases): stop at once if a test, or something it changed, points the app
     * anywhere else.
     */
    private function refuseAnythingButTheTestDatabases(): void
    {
        $database = config('database.connections.mysql.database');
        $user = config('database.connections.mysql.username');
        $source = config('database.connections.mysql_source.database');

        if (! in_array($database, self::TEST_DATABASES, true) || $user !== 'cashvero_tester' || $source !== 'cashvero_test_source') {
            DB::purge('mysql');

            throw new \RuntimeException("Refusing to run: the test is set up to use database '{$database}' as '{$user}' (source: '{$source}'). Tests may only use the cashvero_test databases as cashvero_tester — see phpunit.xml.");
        }
    }
}
