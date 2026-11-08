<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Role;
use Tests\Feature\Security\Support\AttacksRoutes;
use Tests\Feature\Security\Support\Finding;
use Tests\TestCase;

/**
 * THE ODOO ADDRESS: can it be used to make the SERVER call somewhere it should not?
 * ==================================================================
 * Each company stores the web address of its Odoo server, and CashVero's server connects to it with the user's
 * Odoo login. If that address is accepted without checks, whoever can edit a company can point it at the server's
 * own internal network ("http://169.254.169.254" is the cloud provider's secret-keys page, "http://127.0.0.1:3306" is
 * the database) and make CashVero knock on those doors for them. This is called server-side request forgery (SSRF).
 *
 *   SEC-39  The company form must refuse an Odoo address that points at the server itself, a private network, or
 *           is not a web address, and must not send the Odoo password over plain http to an outside host.
 *
 * Runs as a Super Admin (the only person who normally edits companies) on a throw-away copy of a company created
 * inside a transaction that is rolled back. No outside connection is made: the copy has no users to log in.
 */
#[Group('security')]
class OdooAddressSecurityTest extends TestCase
{
    use AttacksRoutes;

    private const INTERNAL = [
        'the cloud provider\'s secret-keys page' => 'http://169.254.169.254/latest/meta-data/',
        'the server itself (database port)'      => 'http://127.0.0.1:3306',
        'the server itself (localhost)'          => 'http://localhost',
        'a private network address'              => 'http://10.0.0.5',
        'a local file'                           => 'file:///etc/passwd',
        'a non-web protocol'                     => 'gopher://127.0.0.1:6379/_PING',
    ];

    private const PLAIN_HTTP = [
        'an outside server over plain http' => 'http://odoo.customer-example.test',
    ];

    protected function tearDown(): void
    {
        $this->cleanupScratch();
        parent::tearDown();
    }

    public function test_the_odoo_address_of_a_company_cannot_point_at_the_servers_own_network(): void
    {
        $this->bootAttack();

        if (! Role::where('name', \App\Models\User::SUPER_ADMIN)->where('guard_name', 'web')->exists()) {
            $this->markTestSkipped('The database has no super-admin role, so the company form cannot be used.');
        }

        $superAdmin = $this->scratchUser([$this->companyA->id], [], \App\Models\User::SUPER_ADMIN);

        $level = DB::transactionLevel();
        DB::beginTransaction();

        $stored = [];
        $plain = [];

        try {
            try {
                $target = $this->companyA->replicate();
                $target->name = 'SEC scratch '.bin2hex(random_bytes(3));
                $target->save();
            } catch (\Throwable $e) {
                $this->markTestSkipped('A throw-away company could not be created: '.$e->getMessage());
            }

            $id = $target->id;
            $url = route('companySection.update', ['companySection' => $id], false);

            $try = function (string $odooUrl) use ($url, $id, $target, $superAdmin): array {
                $payload = [
                    'name' => $target->name,
                    'opening_balance_date' => '2020-01-01',
                    'systems' => [CASH_VERO],
                    'odoo_db_url' => $odooUrl,
                    'odoo_db_name' => 'sec_test_db',
                ];

                $status = $this->send('PUT', $url, $loc, $vo, $payload, fn () => Company::find($id)?->odoo_db_url, $superAdmin);

                return [$status, $this->inspected];
            };

            // Control: an ordinary https address must be stored, or the form is not being exercised.
            [$status, $value] = $try('https://odoo.control.example.test');

            if ($value !== 'https://odoo.control.example.test') {
                $this->markTestSkipped("Test set-up problem: the company form did not store an ordinary Odoo address (HTTP {$status}), so the checks cannot be judged here.");
            }

            foreach (self::INTERNAL as $label => $address) {
                [, $value] = $try($address);

                if ($value === $address) {
                    $stored[] = "{$label}: \"{$address}\" was accepted and saved as the company's Odoo address";
                }
            }

            foreach (self::PLAIN_HTTP as $label => $address) {
                [, $value] = $try($address);

                if ($value === $address) {
                    $plain[] = "{$label}: \"{$address}\" was accepted (the Odoo password would travel unencrypted)";
                }
            }
        } finally {
            while (DB::transactionLevel() > $level) {
                DB::rollBack();
            }
        }

        $report = [];

        if ($stored !== []) {
            $report[] = Finding::message(
                'SEC-39', 'MEDIUM',
                'The company form accepts an Odoo address that points at the server itself or a private network',
                $stored,
                'CashVero\'s server connects to this address with the user\'s Odoo login. Anyone who can edit a company can make the server contact its own internal services (database, cloud secret keys, other machines) and read what comes back in error messages (server-side request forgery).',
                'Validate odoo_db_url in CompanyController::store/update: only https, a real host name, and refuse loopback, private (10.x, 172.16-31.x, 192.168.x), link-local (169.254.x.x) and "localhost" addresses, after resolving the name. Re-check the same way inside AuthTrait before connecting. Ask for the ready-made validation rule.'
            );
        }

        if ($plain !== []) {
            $report[] = Finding::message(
                'SEC-39b', 'LOW',
                'The company form accepts an Odoo address without https',
                $plain,
                'The Odoo username and password are sent to that address on every call. Over plain http anyone on the network path can read them.',
                'Require https in the same validation rule (allow http only when the app environment is local).'
            );
        }

        $this->assertSame('', implode("\n\n", $report), 'The Odoo address is not validated.');
    }
}
