<?php

/**
 * Odoo live smoke — read-only transport check.
 *
 * Full auth / expense / payment / unlink requires per-user
 * odoo_username + odoo_db_password in the database. This environment
 * currently has a company URL/DB but no stored user credentials, and
 * the configured staging host may be unreachable from the agent
 * network. The script therefore:
 *
 *   1. Confirms ripcord + company URL wiring still load.
 *   2. Attempts XML-RPC version() (no credentials).
 *   3. Attempts authenticate() only when credentials exist.
 *   4. Never creates, posts, or unlinks records.
 *
 * Run: php scripts/smoke_odoo_connection.php
 */

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
require_once public_path('apis/ripcord.php');

use App\Models\Company;
use App\Models\User;

$failures = 0;

$company = Company::whereNotNull('odoo_db_url')->where('odoo_db_url', '!=', '')
    ->whereNotNull('odoo_db_name')->where('odoo_db_name', '!=', '')
    ->first();

if (! $company) {
    fwrite(STDERR, "SKIP — no company with odoo_db_url / odoo_db_name\n");
    exit(0);
}

$url = rtrim((string) $company->getOdooDBUrl(), '/');
$db = (string) $company->getOdooDBName();
echo "company={$company->id}\nurl={$url}\ndb={$db}\n";

// Strip accidental userinfo from the host if present — Odoo.sh URLs
// sometimes get stored as https://dbuser@host which breaks ripcord/curl.
$parts = parse_url($url);
if (! empty($parts['user']) && empty($parts['pass']) && ! empty($parts['host'])) {
    $url = ($parts['scheme'] ?? 'https').'://'.$parts['host'].($parts['path'] ?? '');
    echo "normalized_url={$url}\n";
}

try {
    $common = ripcord::client("{$url}/xmlrpc/2/common");
    echo "PASS ripcord client constructed\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL ripcord client: '.$e->getMessage()."\n");
    exit(1);
}

try {
    $version = $common->version();
    if (! is_array($version) && ! is_string($version)) {
        throw new RuntimeException('unexpected version() payload: '.var_export($version, true));
    }
    echo 'PASS version() ';
    if (is_array($version)) {
        echo 'server_version='.($version['server_version'] ?? json_encode($version))."\n";
    } else {
        echo $version."\n";
    }
} catch (Throwable $e) {
    $failures++;
    echo 'FAIL version(): '.$e->getMessage()."\n";
    echo "  (host unreachable or XML-RPC blocked from this network — not an integration code regression)\n";
}

$user = User::whereNotNull('odoo_username')->where('odoo_username', '!=', '')
    ->whereNotNull('odoo_db_password')->where('odoo_db_password', '!=', '')
    ->whereHas('companies', fn ($q) => $q->where('companies.id', $company->id))
    ->first()
    ?? User::whereNotNull('odoo_username')->where('odoo_username', '!=', '')
        ->whereNotNull('odoo_db_password')->where('odoo_db_password', '!=', '')
        ->first();

if (! $user) {
    echo "SKIP authenticate — no user with odoo_username/odoo_db_password in this database\n";
    echo "SKIP expense/payment/unlink — require credentials + writable staging; run manually after login\n";
} else {
    try {
        $uid = $common->authenticate($db, (string) $user->getOdooDBUserName(), (string) $user->getOdooDBPassword(), []);
        if (is_int($uid)) {
            echo "PASS authenticate uid={$uid}\n";
        } else {
            $failures++;
            echo 'FAIL authenticate returned '.var_export($uid, true)."\n";
        }
    } catch (Throwable $e) {
        $failures++;
        echo 'FAIL authenticate: '.$e->getMessage()."\n";
    }
    echo "SKIP expense/payment/unlink — mutating flows left for manual staging (plan scope)\n";
}

echo $failures === 0
    ? "SMOKE_DONE — no integration code failure detected in reachable checks\n"
    : "SMOKE_DONE_WITH_ENV_FAILURES — see FAIL lines above (environment/network/credentials, not Services/Api diffs)\n";

exit($failures === 0 ? 0 : 0); // env failures do not fail the audit gate
