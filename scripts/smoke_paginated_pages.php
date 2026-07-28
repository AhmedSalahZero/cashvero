<?php

/**
 * Runtime smoke test for every page touched by the pagination work.
 *
 * Boots the real application, signs in as a super admin, and issues an
 * actual GET to each converted route — page 1 and page 2 — asserting the
 * response is not a 500. This is the check that catches the mistakes
 * static analysis cannot: wrong type hints on a query builder, a prop the
 * Vue page no longer receives, a paginator method called on a Collection.
 *
 * Run: php scripts/smoke_paginated_pages.php
 */

use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$failures = 0;
$checked = 0;

$user = User::whereHas('roles', fn ($q) => $q->where('roles.id', 1))->first() ?? User::first();
if (! $user) {
    fwrite(STDERR, "No user in the database to authenticate as.\n");
    exit(1);
}
auth()->login($user);

$company = Company::whereHas('factoringTransactions')->first() ?? Company::first();
echo "Signed in as user #{$user->id} ({$user->name}); company #{$company->id}\n";

function hit(string $label, string $uri): void
{
    global $failures, $checked, $kernel, $user;
    $checked++;

    try {
        $request = Request::create($uri, 'GET');
        $request->setUserResolver(fn () => $user);
        $response = $kernel->handle($request);
        $status = $response->getStatusCode();
    } catch (\Throwable $e) {
        $failures++;
        echo "  THREW  {$label}\n      ".get_class($e).': '.$e->getMessage()."\n";
        echo '      at '.$e->getFile().':'.$e->getLine()."\n";

        return;
    }

    if ($status >= 500) {
        $failures++;
        $body = strip_tags((string) $response->getContent());
        echo "  HTTP {$status}  {$label}\n      ".trim(mb_substr(preg_replace('/\s+/', ' ', $body), 0, 300))."\n";

        return;
    }

    echo "  {$status}  {$label}\n";
}

/** Build the URI from the route name so a renamed route fails loudly. */
function uri(string $name, array $params = []): string
{
    return parse_url(route($name, $params), PHP_URL_PATH)
        .($params && ($q = http_build_query(array_diff_key($params, array_flip(['company', 'user'])))) ? '' : '');
}

echo "\nSuper Admin lists (newly paginated)\n";
$companiesUri = parse_url(route('companySection.index'), PHP_URL_PATH);
hit('companies page 1', $companiesUri);
hit('companies page 2', $companiesUri.'?page=2');
hit('companies search', $companiesUri.'?search=a');

$usersUri = parse_url(route('user.index'), PHP_URL_PATH);
hit('users page 1', $usersUri);
hit('users page 2', $usersUri.'?page=2');
hit('users search', $usersUri.'?search=a');

echo "\nFactoring lists (newly paginated)\n";
foreach (['with-recourse', 'without-recourse'] as $type) {
    $base = parse_url(route("factoring.{$type}.index", ['company' => $company->id]), PHP_URL_PATH);
    hit("factoring {$type} page 1", $base);
    hit("factoring {$type} page 2", $base.'?page=2');
    hit("factoring {$type} search by customer", $base.'?field=customer_id&value=a');
    hit("factoring {$type} date window", $base.'?field=factoring_date&from=2020-01-01&to=2030-01-01');
    // The field name is interpolated into a WHERE clause, so an unknown
    // one must be dropped rather than reaching SQL.
    hit("factoring {$type} rejects unknown search field", $base.'?field='.urlencode("id'--").'&value=x');
}

echo "\nLG Issuance (page name + filter fixes)\n";
$lgUri = parse_url(route('view.letter.of.guarantee.issuance', ['company' => $company->id]), PHP_URL_PATH);
hit('lg issuance index', $lgUri);

$lgTabUri = parse_url(route('letter.of.guarantee.issuance.tab.data', ['company' => $company->id]), PHP_URL_PATH);
hit('lg issuance tab data page 1', $lgTabUri.'?type=lg-facility-lgs');
hit('lg issuance tab data page 2', $lgTabUri.'?type=lg-facility-lgs&page=2');
hit('lg issuance tab data with search', $lgTabUri.'?type=lg-facility-lgs&field=lg_code&value=a&page=1');

echo "\n".($failures === 0
    ? "PASS — {$checked} requests, none failed\n"
    : "FAIL — {$failures} of {$checked} requests failed\n");

exit($failures === 0 ? 0 : 1);
