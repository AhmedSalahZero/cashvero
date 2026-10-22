<?php

namespace Tests\Feature\Reports;

use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\Partner;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\TestCase;

/**
 * Down Payment Report: the down payments received from customers (Money
 * Received) or paid to suppliers (Money Payment) on or after a date, in
 * the chosen currencies - each with the amount settled against invoices
 * and what is left, zero balances included.
 *
 * Every row is written inside a transaction that is rolled back, and every
 * date sits in 2099 so the company's real down payments never reach an
 * assertion.
 */
class DownPaymentReportTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private Partner $customer;

    private Partner $supplier;

    protected function setUpTraits()
    {
        config(['database.connections.mysql.database' => env('SMOKE_DB', 'cashvero')]);
        DB::purge('mysql');

        return parent::setUpTraits();
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            LocaleSessionRedirect::class,
            LaravelLocalizationRedirectFilter::class,
            LaravelLocalizationViewPath::class,
        ]);

        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Development database not reachable: '.$e->getMessage());
        }

        if (! Schema::connection('mysql')->hasTable('payment_settlements')) {
            $this->markTestSkipped('No development schema on '.DB::connection('mysql')->getDatabaseName().' - set SMOKE_DB to run this suite.');
        }

        $institution = FinancialInstitution::query()->whereNotNull('company_id')->first();
        if (! $institution) {
            $this->markTestSkipped('Development database has no financial institution to attach company context to.');
        }

        $this->company = Company::findOrFail($institution->company_id);
        $this->customer = Partner::create(['company_id' => $this->company->id, 'name' => 'Down Payment Customer '.uniqid(), 'is_customer' => 1]);
        $this->supplier = Partner::create(['company_id' => $this->company->id, 'name' => 'Down Payment Supplier '.uniqid(), 'is_supplier' => 1]);
    }

    private function userWith(array $permissions): User
    {
        $user = User::create([
            'name' => 'Down Payment Report '.uniqid(),
            'email' => 'down-payment-report-'.uniqid().'@example.test',
            'password' => bcrypt('secret-for-tests'),
            'company_id' => $this->company->id,
        ]);
        $user->givePermissionTo($permissions);
        $user->companies()->attach($this->company->id);
        $user->load('companies');

        return $user;
    }

    /**
     * @param  array<int, float>  $settlements  amounts settled against invoices
     */
    private function customerDownPayment(string $date, string $currency, float $amount, array $settlements = []): int
    {
        $id = DB::table('money_received')->insertGetId([
            'company_id' => $this->company->id,
            'partner_id' => $this->customer->id,
            'money_type' => 'down-payment',
            'type' => 'cash-in-safe',
            'receiving_date' => $date,
            'received_amount' => $amount,
            'amount_in_invoice_currency' => $amount,
            'currency' => $currency,
            'receiving_currency' => $currency,
        ]);
        foreach ($settlements as $settled) {
            DB::table('settlements')->insert([
                'money_received_id' => $id,
                'company_id' => $this->company->id,
                'partner_id' => $this->customer->id,
                'settlement_amount' => (string) $settled,
            ]);
        }

        return $id;
    }

    private function supplierDownPayment(string $date, string $currency, float $amount, array $settlements = []): int
    {
        $id = DB::table('money_payments')->insertGetId([
            'company_id' => $this->company->id,
            'partner_id' => $this->supplier->id,
            'money_type' => 'down-payment',
            'type' => 'cash-payment',
            'delivery_date' => $date,
            'paid_amount' => $amount,
            'amount_in_invoice_currency' => $amount,
            'currency' => $currency,
            'payment_currency' => $currency,
        ]);
        foreach ($settlements as $settled) {
            DB::table('payment_settlements')->insert([
                'money_payment_id' => $id,
                'company_id' => $this->company->id,
                'partner_id' => $this->supplier->id,
                'settlement_amount' => (string) $settled,
                'letter_of_credit_issuance_id' => 0,
            ]);
        }

        return $id;
    }

    private function resultUrl(array $query): string
    {
        return route('result.down.payment.report', ['company' => $this->company->id] + $query);
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function rowsOf($response)
    {
        return collect($response->viewData('page')['props']['paginator']['data']);
    }

    public function test_customer_down_payments_show_amount_settled_and_balance(): void
    {
        $partlyUsed = $this->customerDownPayment('2099-03-01', 'USD', 1000, [300, 200]);
        $untouched = $this->customerDownPayment('2099-03-02', 'USD', 500);

        $response = $this->actingAs($this->userWith(['view money received']))
            ->get($this->resultUrl(['type' => 'customer', 'from_date' => '2099-03-01', 'currencies' => ['USD']]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Statements/DownPaymentReport/Result', false));

        $partly = $this->rowsOf($response)->firstWhere('id', $partlyUsed);
        $this->assertSame($this->customer->name, $partly['partnerName']);
        $this->assertSame(1000.0, $partly['amount']);
        $this->assertSame(500.0, $partly['settled']);
        $this->assertSame(500.0, $partly['balance']);
        $this->assertSame('USD', $partly['currency']);

        $none = $this->rowsOf($response)->firstWhere('id', $untouched);
        $this->assertSame(0.0, $none['settled']);
        $this->assertSame(500.0, $none['balance']);
    }

    public function test_a_fully_settled_down_payment_is_still_listed_with_a_zero_balance(): void
    {
        $used = $this->customerDownPayment('2099-04-01', 'EGP', 800, [500, 300]);

        $response = $this->actingAs($this->userWith(['view money received']))
            ->get($this->resultUrl(['type' => 'customer', 'from_date' => '2099-04-01']))
            ->assertOk();

        $row = $this->rowsOf($response)->firstWhere('id', $used);
        $this->assertNotNull($row, 'A down payment with nothing left must still appear.');
        $this->assertSame(0.0, $row['balance']);
    }

    public function test_only_down_payments_from_the_date_onwards_are_listed(): void
    {
        $early = $this->customerDownPayment('2099-05-01', 'EGP', 10);
        $onDate = $this->customerDownPayment('2099-05-10', 'EGP', 20);
        $later = $this->customerDownPayment('2099-05-20', 'EGP', 30);

        $response = $this->actingAs($this->userWith(['view money received']))
            ->get($this->resultUrl(['type' => 'customer', 'from_date' => '2099-05-10']));

        $ids = $this->rowsOf($response)->pluck('id')->all();
        $this->assertContains($onDate, $ids);
        $this->assertContains($later, $ids);
        $this->assertNotContains($early, $ids);
    }

    public function test_an_ordinary_money_received_is_not_a_down_payment(): void
    {
        $ordinary = DB::table('money_received')->insertGetId([
            'company_id' => $this->company->id,
            'partner_id' => $this->customer->id,
            'money_type' => 'money-received',
            'type' => 'cash-in-safe',
            'receiving_date' => '2099-06-01',
            'received_amount' => 99,
            'amount_in_invoice_currency' => 99,
            'currency' => 'EGP',
            'receiving_currency' => 'EGP',
        ]);

        $response = $this->actingAs($this->userWith(['view money received']))
            ->get($this->resultUrl(['type' => 'customer', 'from_date' => '2099-06-01']));

        $this->assertNotContains($ordinary, $this->rowsOf($response)->pluck('id')->all());
    }

    public function test_only_the_chosen_currencies_are_listed_and_totals_are_per_currency(): void
    {
        $this->customerDownPayment('2099-07-01', 'EGP', 100, [40]);
        $this->customerDownPayment('2099-07-02', 'USD', 10);
        $this->customerDownPayment('2099-07-03', 'USD', 30, [30]);
        $user = $this->userWith(['view money received']);

        $usdOnly = $this->actingAs($user)->get($this->resultUrl(['type' => 'customer', 'from_date' => '2099-07-01', 'currencies' => ['USD']]));
        $this->assertEqualsCanonicalizing(['USD'], $this->rowsOf($usdOnly)->pluck('currency')->unique()->all());
        $this->assertEquals(
            [['currency' => 'USD', 'amount' => 40.0, 'settled' => 30.0, 'balance' => 10.0]],
            $usdOnly->viewData('page')['props']['kpis']['perCurrency']
        );

        $both = $this->actingAs($user)->get($this->resultUrl(['type' => 'customer', 'from_date' => '2099-07-01', 'currencies' => ['USD', 'EGP']]));
        $this->assertSame(3, $both->viewData('page')['props']['kpis']['count']);
        $this->assertEqualsCanonicalizing(['EGP', 'USD'], collect($both->viewData('page')['props']['kpis']['perCurrency'])->pluck('currency')->all());
    }

    public function test_supplier_down_payments_come_from_money_payments(): void
    {
        $paid = $this->supplierDownPayment('2099-08-01', 'EGP', 900, [400]);
        $this->customerDownPayment('2099-08-01', 'EGP', 5);

        $response = $this->actingAs($this->userWith(['view supplier payment']))
            ->get($this->resultUrl(['type' => 'supplier', 'from_date' => '2099-08-01']))
            ->assertOk();

        $this->assertSame([$paid], $this->rowsOf($response)->pluck('id')->all());
        $row = $this->rowsOf($response)->first();
        $this->assertSame($this->supplier->name, $row['partnerName']);
        $this->assertSame(900.0, $row['amount']);
        $this->assertSame(400.0, $row['settled']);
        $this->assertSame(500.0, $row['balance']);
    }

    public function test_a_user_cannot_open_a_kind_they_cannot_see_on_its_own_screen(): void
    {
        $this->supplierDownPayment('2099-09-01', 'EGP', 1);

        $this->actingAs($this->userWith(['view money received']))
            ->get($this->resultUrl(['type' => 'supplier', 'from_date' => '2099-09-01']))
            ->assertForbidden();
    }

    public function test_the_form_only_offers_the_kinds_the_user_may_open(): void
    {
        $this->actingAs($this->userWith(['view money received']))
            ->get(route('view.down.payment.report', ['company' => $this->company->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Statements/DownPaymentReport/Index', false)
                ->where('types', fn ($types) => collect($types)->pluck('value')->all() === ['customer']));
    }

    public function test_the_excel_export_is_a_download_of_the_filtered_range(): void
    {
        $this->customerDownPayment('2099-10-01', 'EGP', 10);

        $response = $this->actingAs($this->userWith(['view money received']))
            ->get(route('export.down.payment.report', ['company' => $this->company->id, 'type' => 'customer', 'from_date' => '2099-10-01']))
            ->assertOk();

        $this->assertStringContainsString('Down-Payment-Report-customer-2099-10-01.xlsx', $response->headers->get('content-disposition'));
    }
}
