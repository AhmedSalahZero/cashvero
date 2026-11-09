<?php

namespace App\Services\Api;

/**
 * The one place an XML-RPC connection to Odoo is opened.
 *
 * Every call to Odoo (AuthTrait, the connection-check commands) asks the container for this
 * class instead of calling ripcord::client() itself. In production it is exactly that call; the
 * test suite binds Tests\Fakes\FakeOdooClientFactory in its place, so no test can ever reach a
 * real Odoo server, whatever address a company has.
 */
class OdooClientFactory
{
    public function client(string $url): \Ripcord_Client
    {
        require_once public_path('apis/ripcord.php');

        return \ripcord::client($url);
    }
}
