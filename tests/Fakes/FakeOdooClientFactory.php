<?php

namespace Tests\Fakes;

use App\Services\Api\OdooClientFactory;

require_once dirname(__DIR__, 2).'/public/apis/ripcord_client.php';

/**
 * Stands in for OdooClientFactory in every test (TestCase binds it), so no test ever opens a
 * connection to a real Odoo server.
 *
 * By default Odoo "refuses" everything: authenticate() answers false and every other call
 * answers []. A test that needs Odoo to answer tells it what to say:
 *
 *   $odoo = $this->odoo();
 *   $odoo->respond('authenticate', 7);
 *   $odoo->respond('execute_kw', fn (array $args) => [['id' => 1, 'name' => 'INV/1']]);
 *   ... exercise the app ...
 *   $odoo->assertCalled('execute_kw');
 */
class FakeOdooClientFactory extends OdooClientFactory
{
    /** @var list<array{url: string, method: string, args: array}> */
    public array $calls = [];

    /** @var array<string, mixed|callable> */
    private array $responses = [];

    public function client(string $url): \Ripcord_Client
    {
        return new FakeRipcordClient($this, $url);
    }

    /** @param  mixed|callable(array $args): mixed  $answer */
    public function respond(string $method, mixed $answer): static
    {
        $this->responses[$method] = $answer;

        return $this;
    }

    /** @internal called by FakeRipcordClient */
    public function answer(string $url, string $method, array $args): mixed
    {
        $this->calls[] = ['url' => $url, 'method' => $method, 'args' => $args];

        if (array_key_exists($method, $this->responses)) {
            $answer = $this->responses[$method];

            return is_callable($answer) ? $answer($args) : $answer;
        }

        return $method === 'authenticate' ? false : [];
    }

    public function assertCalled(string $method): void
    {
        \PHPUnit\Framework\Assert::assertContains($method, array_column($this->calls, 'method'), "Odoo was never asked to {$method}.");
    }

    public function assertNothingCalled(): void
    {
        \PHPUnit\Framework\Assert::assertSame([], $this->calls, 'Odoo was called, but nothing should have reached it.');
    }
}

/** A Ripcord_Client (the type the app's code declares) that never touches the network. */
class FakeRipcordClient extends \Ripcord_Client
{
    public function __construct(private FakeOdooClientFactory $fake, private string $fakeUrl)
    {
        // The real constructor is deliberately not called: it prepares a network transport.
    }

    public function __call($name, $args)
    {
        return $this->fake->answer($this->fakeUrl, $name, $args);
    }
}
