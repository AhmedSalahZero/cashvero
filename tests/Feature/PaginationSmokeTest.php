<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\TestCase;

/**
 * Runtime smoke test for the pages converted to server-side pagination.
 *
 * These assertions are the ones static analysis cannot make: that the
 * route still boots, that the controller's query object actually accepts
 * the calls made on it, and that page 2 comes back as happily as page 1.
 * The TypeError from hinting an Eloquent Builder where a HasMany relation
 * is passed, for instance, only shows up here.
 *
 * The test runs against the development database rather than the empty
 * test schema, because it is verifying behaviour against real row counts
 * and every request is a read-only GET. Point it elsewhere with
 * SMOKE_DB=<name>, and it skips itself when that database is unreachable.
 */
class PaginationSmokeTest extends TestCase
{
    private ?User $actor = null;

    private ?Company $company = null;

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

        /*
         * The locale middleware bounces any URL without a /{lang} segment
         * back to the localised home page. Under `php artisan serve` the
         * route group picks up that prefix; in a test process it does not,
         * so every request would 302 before reaching a controller and the
         * suite would pass without executing a single line of the code it
         * is meant to cover. Auth and the permission gates stay on.
         */
        $this->withoutMiddleware([
            LocaleSessionRedirect::class,
            LaravelLocalizationRedirectFilter::class,
            LaravelLocalizationViewPath::class,
        ]);

        $this->actor = User::whereHas('roles', fn ($q) => $q->where('roles.id', 1))->first() ?? User::first();
        $this->company = Company::whereHas('factoringTransactions')->first() ?? Company::first();

        if (! $this->actor || ! $this->company) {
            $this->markTestSkipped('Development database has no user/company to exercise.');
        }
    }

    /**
     * Asserts a hard 200 rather than merely "not a 500". A redirect is a
     * silent pass otherwise, and the controller never runs — which is
     * exactly how an earlier version of this test reported success while
     * exercising nothing.
     *
     * @return array<string,int>
     */
    private function assertPagesLoad(string $uri, array $queries = ['', 'page=2']): array
    {
        $statuses = [];
        foreach ($queries as $query) {
            $target = $query === '' ? $uri : $uri.(str_contains($uri, '?') ? '&' : '?').$query;
            $response = $this->actingAs($this->actor)->get($target);

            $this->assertSame(
                200,
                $response->getStatusCode(),
                "{$target} returned {$response->getStatusCode()} instead of 200"
                    .($response->headers->get('Location') ? ' (redirect to '.$response->headers->get('Location').')' : '')
            );
            $statuses[$target] = $response->getStatusCode();
        }

        return $statuses;
    }

    public function test_super_admin_companies_list_paginates(): void
    {
        $this->assertPagesLoad(route('companySection.index'), ['', 'page=2', 'search=a']);
    }

    public function test_super_admin_users_list_paginates(): void
    {
        $this->assertPagesLoad(route('user.index'), ['', 'page=2', 'search=a']);
    }

    /** @dataProvider factoringTypes */
    public function test_factoring_list_paginates_and_filters(string $type): void
    {
        $uri = route("factoring.{$type}.index", ['company' => $this->company->id]);

        $this->assertPagesLoad($uri, [
            '',
            'page=2',
            'field=customer_id&value=a',
            'field=factoring_company_id&value=a',
            'field=factoring_date&from=2020-01-01&to=2030-01-01',
        ]);
    }

    /**
     * The search field name is interpolated into a WHERE clause, so it is
     * matched against a whitelist. An unknown one must be ignored rather
     * than reaching SQL.
     *
     * @dataProvider factoringTypes
     */
    public function test_factoring_list_ignores_unknown_search_field(string $type): void
    {
        $uri = route("factoring.{$type}.index", ['company' => $this->company->id]);

        $unfiltered = $this->actingAs($this->actor)->get($uri);
        $injected = $this->actingAs($this->actor)->get($uri."?field=id'--&value=x");

        $this->assertSame(200, $unfiltered->getStatusCode());
        $this->assertSame(
            200,
            $injected->getStatusCode(),
            'An unknown search field should be dropped, not passed through to SQL.'
        );
    }

    public static function factoringTypes(): array
    {
        return [
            'with recourse' => ['with-recourse'],
            'without recourse' => ['without-recourse'],
        ];
    }

    public function test_lg_issuance_index_and_tab_data_paginate(): void
    {
        $this->assertPagesLoad(
            route('view.letter.of.guarantee.issuance', ['company' => $this->company->id]),
            ['']
        );

        // The tab endpoint is what the page bar actually calls. It used to
        // paginate under a page name nobody sent, so page 2 silently
        // returned page 1.
        $tabUri = route('letter.of.guarantee.issuance.tab.data', ['company' => $this->company->id]);
        $this->assertPagesLoad($tabUri, [
            'type=lg-facility-lgs',
            'type=lg-facility-lgs&page=2',
            'type=lg-facility-lgs&field=lg_code&value=a',
        ]);
    }

    /**
     * Page 2 of the LG tab endpoint must not repeat page 1. This is the
     * regression the wrong page name caused.
     */
    public function test_lg_issuance_tab_page_two_differs_from_page_one(): void
    {
        $tabUri = route('letter.of.guarantee.issuance.tab.data', ['company' => $this->company->id]);

        $first = $this->actingAs($this->actor)->get($tabUri.'?type=lg-facility-lgs&page=1');
        $firstBody = $first->json();

        if (($firstBody['last_page'] ?? 1) < 2) {
            $this->markTestSkipped('Development data has only one page of LG facility LGs.');
        }

        $second = $this->actingAs($this->actor)->get($tabUri.'?type=lg-facility-lgs&page=2')->json();

        $this->assertSame(2, $second['current_page']);
        $this->assertNotEquals(
            array_column($firstBody['rows'], 'id'),
            array_column($second['rows'], 'id'),
            'Page 2 returned the same rows as page 1 — the paginator is ignoring the page parameter.'
        );
    }

    public function test_invoice_report_paginates(): void
    {
        $invoice = DB::table('customer_invoices')
            ->select('company_id', 'customer_id', 'currency')
            ->groupBy('company_id', 'customer_id', 'currency')
            ->orderByRaw('COUNT(*) DESC')
            ->first();

        if (! $invoice) {
            $this->markTestSkipped('No customer invoices to report on.');
        }

        $this->assertPagesLoad(route('view.invoice.report', [
            'company' => $invoice->company_id,
            'partnerId' => $invoice->customer_id,
            'currency' => $invoice->currency,
            'modelType' => 'CustomerInvoice',
        ]), ['', 'page=2']);
    }

    public function test_statement_reports_paginate_from_the_database(): void
    {
        $combo = DB::table('cash_in_safe_statements')
            ->select('company_id', 'currency', 'branch_id')
            ->groupBy('company_id', 'currency', 'branch_id')
            ->orderByRaw('COUNT(*) DESC')
            ->first();

        if (! $combo) {
            $this->markTestSkipped('No safe statement rows to report on.');
        }

        $range = DB::table('cash_in_safe_statements')
            ->where('company_id', $combo->company_id)
            ->where('currency', $combo->currency)
            ->where('branch_id', $combo->branch_id)
            ->selectRaw('MIN(date) AS start_date, MAX(date) AS end_date')
            ->first();

        $uri = route('result.safe.statement', ['company' => $combo->company_id]).'?'.http_build_query([
            'start_date' => $range->start_date,
            'end_date' => $range->end_date,
            'branch_id' => $combo->branch_id,
            'currency' => $combo->currency,
        ]);

        $this->assertPagesLoad($uri, ['', 'page=2']);
    }

    /**
     * The Contracts list paginates each of its three status tabs under its
     * own `<status>_page` name, so the shared `page` parameter is not what
     * moves it. Both types are exercised because they eager-load different
     * relations (purchasesOrders.allocations vs salesOrders) and the
     * Supplier query is the one that can hit a HasMany/Builder mismatch.
     *
     * @dataProvider contractTypes
     */
    public function test_contracts_list_paginates_per_tab(string $type): void
    {
        $uri = route('contracts.index', ['company' => $this->company->id, 'type' => $type]);

        $this->assertPagesLoad($uri, [
            '',
            'running_page=2',
            'active=finished&finished_page=2',
            // Every tab moving at once — each paginator must read only its own name
            'running_page=2&running_and_against_page=2&finished_page=2',
        ]);

        // The page reads these off every row / at the top level; a missing
        // one is a blank render, not an error, so assert them here.
        $props = $this->actingAs($this->actor)->get($uri)->viewData('page')['props'];

        $this->assertArrayHasKey('hasOdooCredentials', $props);
        $this->assertArrayHasKey('paginators', $props);

        foreach ($props['contracts'] as $rows) {
            foreach ($rows as $row) {
                $this->assertArrayHasKey('related_contracts', $row);
                $this->assertArrayHasKey('related_contracts_totals', $row);
            }
        }
    }

    public static function contractTypes(): array
    {
        return [
            'customer' => ['Customer'],
            'supplier' => ['Supplier'],
        ];
    }

    /**
     * Page 2 of a tab must not repeat page 1 — the failure mode when a
     * paginator is built with a page name nobody sends.
     */
    public function test_contracts_tab_page_two_differs_from_page_one(): void
    {
        $uri = route('contracts.index', ['company' => $this->company->id, 'type' => 'Customer']);

        $first = $this->actingAs($this->actor)->get($uri)->viewData('page')['props'] ?? null;

        if (! $first || ($first['paginators']['running']['last_page'] ?? 1) < 2) {
            $this->markTestSkipped('Development data has only one page of running customer contracts.');
        }

        $second = $this->actingAs($this->actor)->get($uri.'?running_page=2')->viewData('page')['props'];

        $this->assertSame(2, $second['paginators']['running']['current_page']);
        $this->assertNotEquals(
            array_column($first['contracts']['running'], 'id'),
            array_column($second['contracts']['running'], 'id'),
            'Page 2 returned the same contracts as page 1 — the paginator is ignoring running_page.'
        );
    }
}
