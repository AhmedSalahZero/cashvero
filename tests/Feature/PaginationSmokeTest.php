<?php

namespace Tests\Feature;

use App\Enums\LgTypes;
use App\Models\Company;
use App\Models\Contract;
use App\Models\FactoringTransaction;
use App\Models\User;
use Database\Factories\PartnerFactory;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\Concerns\MakesBanks;
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
 * Each test builds the rows it pages through: always more than one page
 * of them, so "page 2" is a real second page and not an empty one. Every
 * request is a read-only GET.
 */
class PaginationSmokeTest extends TestCase
{
    use MakesBanks;

    /** One more than the 20-row pages most of these lists use. */
    private const MORE_THAN_A_PAGE = 21;

    private ?User $actor = null;

    private ?Company $company = null;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->company = $this->makeCompany(['name' => ['en' => 'Alpha Paging Co', 'ar' => 'Alpha Paging Co']]);
        $this->actor = $this->makeSuperAdmin($this->company);
    }

    /* ── the rows each list pages through ─────────────────────────── */

    private function customer(string $name = 'Alpha Customer'): int
    {
        return PartnerFactory::new()->forCompany($this->company)->customer()->create(['name' => $name])->id;
    }

    private function supplier(string $name = 'Alpha Supplier'): int
    {
        return PartnerFactory::new()->forCompany($this->company)->supplier()->create(['name' => $name])->id;
    }

    /** @return list<int> */
    private function customerInvoices(int $customerId, int $count): array
    {
        $ids = [];

        for ($i = 1; $i <= $count; $i++) {
            $date = date('Y-m-d', strtotime("2026-01-01 +{$i} days"));
            $ids[] = (int) DB::table('customer_invoices')->insertGetId([
                'company_id' => $this->company->id,
                'customer_id' => $customerId,
                'customer_name' => 'Alpha Customer',
                'invoice_number' => sprintf('INV-PAGE-%03d', $i),
                'invoice_date' => $date,
                'invoice_due_date' => date('Y-m-d', strtotime($date.' +30 days')),
                'currency' => 'EGP',
                'exchange_rate' => 1,
                'invoice_amount' => 1000 + $i,
                'net_invoice_amount' => 1000 + $i,
                'net_balance' => 1000 + $i,
            ]);
        }

        return $ids;
    }

    private function factoringTransactions(string $recourseType, int $count): void
    {
        $customerId = $this->customer();
        $invoiceIds = $this->customerInvoices($customerId, $count);
        $bank = $this->bank($this->company);
        $account = $this->currentAccount($bank);

        $factoringCompanyId = DB::table('factoring_companies')->insertGetId([
            'company_id' => $this->company->id,
            'name' => 'Alpha Factoring',
        ]);
        $contractId = DB::table('factoring_contracts')->insertGetId([
            'company_id' => $this->company->id,
            'factoring_company_id' => $factoringCompanyId,
            'recourse_type' => $recourseType,
            'currency' => 'EGP',
            'limit' => 10000000,
            'interest_rate' => 10,
            'contract_start_date' => '2026-01-01',
            'contract_end_date' => '2027-12-31',
        ]);

        foreach ($invoiceIds as $i => $invoiceId) {
            DB::table('factoring_transactions')->insert([
                'company_id' => $this->company->id,
                'recourse_type' => $recourseType,
                'factoring_date' => date('Y-m-d', strtotime('2026-02-01 +'.$i.' days')),
                'factoring_company_id' => $factoringCompanyId,
                'factoring_contract_id' => $contractId,
                'customer_id' => $customerId,
                'customer_invoice_id' => $invoiceId,
                'invoice_currency' => 'EGP',
                'invoice_amount' => 1000,
                'factoring_percentage' => 80,
                'factoring_amount' => 800,
                'contract_interest_rate' => 10,
                'diff_in_days' => 30,
                'factoring_interest_amount' => 6.58,
                'received_amount' => 793.42,
                'financial_institution_id' => $bank->id,
                'account_type_id' => $this->accountTypeId('current-account'),
                'account_number' => $account->account_number,
            ]);
        }
    }

    private function letterOfGuaranteeIssuances(string $lgType, int $count): void
    {
        $bank = $this->bank($this->company);
        $beneficiaryId = $this->customer();

        for ($i = 1; $i <= $count; $i++) {
            DB::table('letter_of_guarantee_issuances')->insert([
                'company_id' => $this->company->id,
                'lg_type' => $lgType,
                'financial_institution_id' => $bank->id,
                'partner_id' => $beneficiaryId,
                'transaction_name' => 'Alpha Tender '.$i,
                'lg_code' => sprintf('LG-PAGE-%03d', $i),
                'lg_currency' => 'EGP',
                'lg_amount' => 10000 + $i,
                'issuance_date' => now()->subMonths(2)->format('Y-m-d'),
                'renewal_date' => now()->addMonths(2)->addDays($i)->format('Y-m-d'),
                'status' => 'running',
            ]);
        }
    }

    /** @return object{branch_id: int, start_date: string, end_date: string} */
    private function safeStatementRows(int $count): object
    {
        $branchId = DB::table('branch')->insertGetId([
            'company_id' => $this->company->id,
            'name' => 'Alpha Safe',
            'currency' => 'EGP',
        ]);
        $customerId = $this->customer();

        for ($i = 1; $i <= $count; $i++) {
            $date = date('Y-m-d', strtotime("2026-01-01 +{$i} days"));
            $receivedId = DB::table('money_received')->insertGetId([
                'company_id' => $this->company->id,
                'partner_id' => $customerId,
                'type' => 'cash-in-safe',
                'receiving_date' => $date,
                'received_amount' => 100,
                'currency' => 'EGP',
                'receiving_currency' => 'EGP',
            ]);
            DB::table('cash_in_safe_statements')->insert([
                'company_id' => $this->company->id,
                'branch_id' => $branchId,
                'money_received_id' => $receivedId,
                'currency' => 'EGP',
                'date' => $date,
                'full_date' => $date.' 10:00:00',
                'debit' => 100,
                'credit' => 0,
                'is_debit' => 1,
                'is_credit' => 0,
                'type' => 'cash-in-safe',
            ]);
        }

        return (object) [
            'branch_id' => $branchId,
            'start_date' => date('Y-m-d', strtotime('2026-01-02')),
            'end_date' => date('Y-m-d', strtotime("2026-01-01 +{$count} days")),
        ];
    }

    private function contracts(string $type, string $status, int $count): void
    {
        $partnerId = $type === 'Customer' ? $this->customer() : $this->supplier();

        for ($i = 1; $i <= $count; $i++) {
            $id = DB::table('contracts')->insertGetId([
                'company_id' => $this->company->id,
                'partner_id' => $partnerId,
                'model_type' => $type,
                'status' => $status,
                'name' => "Alpha {$type} {$status} Contract {$i}",
                'code' => sprintf('C-%s-%s-%03d', substr($type, 0, 1), $status, $i),
                'currency' => 'EGP',
                'exchange_rate' => 1,
                'amount' => 100000 + $i,
                'start_date' => date('Y-m-d', strtotime("2026-01-01 +{$i} days")),
                'end_date' => '2026-12-31',
            ]);

            // One order under each contract, so the eager-loaded order relations have rows to load.
            DB::table($type === 'Customer' ? 'sales_orders' : 'purchase_orders')->insert([
                'company_id' => $this->company->id,
                'contract_id' => $id,
                'amount' => 1000,
                'execution_percentage_1' => 100,
            ]);
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
        for ($i = 1; $i <= self::MORE_THAN_A_PAGE; $i++) {
            $this->makeCompany(['name' => ['en' => "Alpha Listed Co {$i}", 'ar' => "Alpha Listed Co {$i}"]]);
        }

        $this->assertPagesLoad(route('companySection.index'), ['', 'page=2', 'search=a']);
    }

    public function test_super_admin_users_list_paginates(): void
    {
        for ($i = 1; $i <= self::MORE_THAN_A_PAGE; $i++) {
            $this->makeUser([], $this->company, null, ['name' => "Alpha User {$i}"]);
        }

        $this->assertPagesLoad(route('user.index'), ['', 'page=2', 'search=a']);
    }

    #[DataProvider('factoringTypes')]
    public function test_factoring_list_paginates_and_filters(string $type): void
    {
        $this->factoringTransactions($this->recourseType($type), self::MORE_THAN_A_PAGE);

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
     */
    #[DataProvider('factoringTypes')]
    public function test_factoring_list_ignores_unknown_search_field(string $type): void
    {
        $this->factoringTransactions($this->recourseType($type), 2);

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

    private function recourseType(string $routeType): string
    {
        return $routeType === 'with-recourse' ? FactoringTransaction::WITH_RECOURSE : FactoringTransaction::WITHOUT_RECOURSE;
    }

    public function test_lg_issuance_index_and_tab_data_paginate(): void
    {
        $this->letterOfGuaranteeIssuances(LgTypes::FINAL_LGS, self::MORE_THAN_A_PAGE);

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
        $this->letterOfGuaranteeIssuances(LgTypes::FINAL_LGS, self::MORE_THAN_A_PAGE);

        $tabUri = route('letter.of.guarantee.issuance.tab.data', ['company' => $this->company->id]);

        $first = $this->actingAs($this->actor)->get($tabUri.'?type=final-lgs&page=1');
        $firstBody = $first->json();

        $this->assertSame(2, $firstBody['last_page'] ?? 1, 'The planted final LGs fill two pages.');

        $second = $this->actingAs($this->actor)->get($tabUri.'?type=final-lgs&page=2')->json();

        $this->assertSame(2, $second['current_page']);
        $this->assertNotEquals(
            array_column($firstBody['rows'], 'id'),
            array_column($second['rows'], 'id'),
            'Page 2 returned the same rows as page 1 — the paginator is ignoring the page parameter.'
        );
    }

    public function test_invoice_report_paginates(): void
    {
        // The report shows 50 invoices a page.
        $customerId = $this->customer();
        $this->customerInvoices($customerId, 51);

        $this->assertPagesLoad(route('view.invoice.report', [
            'company' => $this->company->id,
            'partnerId' => $customerId,
            'currency' => 'EGP',
            'modelType' => 'CustomerInvoice',
        ]), ['', 'page=2']);
    }

    public function test_statement_reports_paginate_from_the_database(): void
    {
        // The statement shows 50 rows a page.
        $range = $this->safeStatementRows(51);

        $uri = route('result.safe.statement', ['company' => $this->company->id]).'?'.http_build_query([
            'start_date' => $range->start_date,
            'end_date' => $range->end_date,
            'branch_id' => $range->branch_id,
            'currency' => 'EGP',
        ]);

        $this->assertPagesLoad($uri, ['', 'page=2']);
    }

    /**
     * The Contracts list paginates each of its three status tabs under its
     * own `<status>_page` name, so the shared `page` parameter is not what
     * moves it. Both types are exercised because they eager-load different
     * relations (purchasesOrders.allocations vs salesOrders) and the
     * Supplier query is the one that can hit a HasMany/Builder mismatch.
     */
    #[DataProvider('contractTypes')]
    public function test_contracts_list_paginates_per_tab(string $type): void
    {
        foreach ([Contract::RUNNING, Contract::RUNNING_AND_AGAINST, Contract::FINISHED] as $status) {
            $this->contracts($type, $status, self::MORE_THAN_A_PAGE);
        }

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
        $this->contracts('Customer', Contract::RUNNING, self::MORE_THAN_A_PAGE);

        $uri = route('contracts.index', ['company' => $this->company->id, 'type' => 'Customer']);

        $first = $this->actingAs($this->actor)->get($uri)->viewData('page')['props'] ?? null;

        $this->assertNotNull($first);
        $this->assertSame(2, $first['paginators']['running']['last_page'] ?? 1, 'The planted running contracts fill two pages.');

        $second = $this->actingAs($this->actor)->get($uri.'?running_page=2')->viewData('page')['props'];

        $this->assertSame(2, $second['paginators']['running']['current_page']);
        $this->assertNotEquals(
            array_column($first['contracts']['running'], 'id'),
            array_column($second['contracts']['running'], 'id'),
            'Page 2 returned the same contracts as page 1 — the paginator is ignoring running_page.'
        );
    }
}
