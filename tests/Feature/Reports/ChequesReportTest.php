<?php

namespace Tests\Feature\Reports;

use App\Models\Bank;
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
 * Cheques Report: the cheques received from customers or payable to
 * suppliers whose due date is on or after a chosen date, in the chosen
 * currencies, optionally narrowed to one bank.
 *
 * Every row is written inside a transaction that is rolled back, and every
 * date sits in 2099 so the company's real cheques never reach an assertion.
 */
class ChequesReportTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private Partner $customer;

    private Partner $supplier;

    private Bank $bankA;

    private Bank $bankB;

    private int $deliveryBankId;

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

        if (! Schema::connection('mysql')->hasTable('payable_cheques')) {
            $this->markTestSkipped('No development schema on '.DB::connection('mysql')->getDatabaseName().' - set SMOKE_DB to run this suite.');
        }

        $institution = FinancialInstitution::query()->whereNotNull('company_id')->whereNotNull('bank_id')->first();
        $banks = Bank::query()->limit(2)->get();
        if (! $institution || $banks->count() < 2) {
            $this->markTestSkipped('Development database needs a bank-type financial institution and two banks.');
        }

        $this->company = Company::findOrFail($institution->company_id);
        $this->deliveryBankId = $institution->id;
        [$this->bankA, $this->bankB] = [$banks[0], $banks[1]];

        $this->customer = Partner::create(['company_id' => $this->company->id, 'name' => 'Cheques Customer '.uniqid(), 'is_customer' => 1]);
        $this->supplier = Partner::create(['company_id' => $this->company->id, 'name' => 'Cheques Supplier '.uniqid(), 'is_supplier' => 1]);
    }

    private function userWith(array $permissions): User
    {
        $user = User::create([
            'name' => 'Cheques Report '.uniqid(),
            'email' => 'cheques-report-'.uniqid().'@example.test',
            'password' => bcrypt('secret-for-tests'),
            'company_id' => $this->company->id,
        ]);
        $user->givePermissionTo($permissions);
        $user->companies()->attach($this->company->id);
        $user->load('companies');

        return $user;
    }

    private function receivedCheque(string $number, string $dueDate, string $currency, float $amount, Bank $bank, string $status = 'in-safe'): void
    {
        $moneyId = DB::table('money_received')->insertGetId([
            'company_id' => $this->company->id,
            'partner_id' => $this->customer->id,
            'type' => 'cheque',
            'receiving_date' => '2099-01-01',
            'received_amount' => $amount,
            'currency' => $currency,
            'receiving_currency' => $currency,
        ]);
        DB::table('cheques')->insert([
            'money_received_id' => $moneyId,
            'company_id' => $this->company->id,
            'cheque_number' => $number,
            'status' => $status,
            'drawee_bank_id' => $bank->id,
            'account_type' => 'current',
            'due_date' => $dueDate,
        ]);
    }

    private function payableCheque(string $number, string $dueDate, string $currency, float $amount): void
    {
        $moneyId = DB::table('money_payments')->insertGetId([
            'company_id' => $this->company->id,
            'partner_id' => $this->supplier->id,
            'type' => 'payable-cheque',
            'delivery_date' => '2099-01-01',
            'paid_amount' => $amount,
            'currency' => $currency,
            'payment_currency' => $currency,
        ]);
        DB::table('payable_cheques')->insert([
            'money_payment_id' => $moneyId,
            'company_id' => $this->company->id,
            'cheque_number' => $number,
            'status' => 'pending',
            'delivery_bank_id' => $this->deliveryBankId,
            'account_type' => 'current',
            'due_date' => $dueDate,
        ]);
    }

    private function resultUrl(array $query): string
    {
        return route('result.cheques.report', ['company' => $this->company->id] + $query);
    }

    /** @return array<int, string> cheque numbers on the page */
    private function chequeNumbers($response): array
    {
        return collect($response->viewData('page')['props']['paginator']['data'])->pluck('chequeNumber')->all();
    }

    public function test_received_cheques_are_listed_from_the_due_date_onwards(): void
    {
        $this->receivedCheque('CHQ-EARLY', '2099-03-01', 'EGP', 100, $this->bankA);
        $this->receivedCheque('CHQ-ON-DATE', '2099-04-01', 'EGP', 200, $this->bankA);
        $this->receivedCheque('CHQ-LATER', '2099-06-01', 'EGP', 300, $this->bankA);

        $response = $this->actingAs($this->userWith(['view money received']))
            ->get($this->resultUrl(['type' => 'received', 'due_date' => '2099-04-01']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Statements/ChequesReport/Result', false));

        $numbers = $this->chequeNumbers($response);
        $this->assertContains('CHQ-ON-DATE', $numbers);
        $this->assertContains('CHQ-LATER', $numbers);
        $this->assertNotContains('CHQ-EARLY', $numbers);
    }

    public function test_a_row_carries_customer_bank_amount_currency_due_date_and_status(): void
    {
        $this->receivedCheque('CHQ-DETAIL', '2099-05-01', 'USD', 1250.5, $this->bankA, 'rejected');

        $response = $this->actingAs($this->userWith(['view money received']))
            ->get($this->resultUrl(['type' => 'received', 'due_date' => '2099-05-01', 'currencies' => ['USD']]))
            ->assertOk();

        $row = collect($response->viewData('page')['props']['paginator']['data'])->firstWhere('chequeNumber', 'CHQ-DETAIL');
        $this->assertSame($this->customer->name, $row['partnerName']);
        $this->assertSame(1250.5, $row['amount']);
        $this->assertSame('USD', $row['currency']);
        $this->assertSame('01-05-2099', $row['dueDate']);
        $this->assertSame('rejected', $row['status']);
        $this->assertSame(__('Rejected'), $row['statusLabel']);
    }

    public function test_only_the_chosen_currencies_are_listed_and_totals_are_per_currency(): void
    {
        $this->receivedCheque('CHQ-EGP', '2099-07-01', 'EGP', 100, $this->bankA);
        $this->receivedCheque('CHQ-USD-1', '2099-07-02', 'USD', 40, $this->bankA);
        $this->receivedCheque('CHQ-USD-2', '2099-07-03', 'USD', 60, $this->bankA);
        $user = $this->userWith(['view money received']);

        $usdOnly = $this->actingAs($user)->get($this->resultUrl(['type' => 'received', 'due_date' => '2099-07-01', 'currencies' => ['USD']]));
        $this->assertEqualsCanonicalizing(['CHQ-USD-1', 'CHQ-USD-2'], $this->chequeNumbers($usdOnly));
        $this->assertEquals([['currency' => 'USD', 'total' => 100.0]], $usdOnly->viewData('page')['props']['kpis']['perCurrency']);

        $both = $this->actingAs($user)->get($this->resultUrl(['type' => 'received', 'due_date' => '2099-07-01', 'currencies' => ['USD', 'EGP']]));
        $this->assertEqualsCanonicalizing(['CHQ-EGP', 'CHQ-USD-1', 'CHQ-USD-2'], $this->chequeNumbers($both));
        $this->assertSame(3, $both->viewData('page')['props']['kpis']['count']);

        $all = $this->actingAs($user)->get($this->resultUrl(['type' => 'received', 'due_date' => '2099-07-01']));
        $this->assertEqualsCanonicalizing(['CHQ-EGP', 'CHQ-USD-1', 'CHQ-USD-2'], $this->chequeNumbers($all));
    }

    public function test_the_bank_filter_narrows_the_rows_but_keeps_every_bank_in_the_list(): void
    {
        $this->receivedCheque('CHQ-BANK-A', '2099-08-01', 'EGP', 10, $this->bankA);
        $this->receivedCheque('CHQ-BANK-B', '2099-08-02', 'EGP', 20, $this->bankB);

        $response = $this->actingAs($this->userWith(['view money received']))
            ->get($this->resultUrl(['type' => 'received', 'due_date' => '2099-08-01', 'bank_id' => $this->bankB->id]))
            ->assertOk();

        $this->assertSame(['CHQ-BANK-B'], $this->chequeNumbers($response));
        $this->assertEquals([20.0], collect($response->viewData('page')['props']['kpis']['perCurrency'])->pluck('total')->all());
        $this->assertEqualsCanonicalizing(
            [$this->bankA->id, $this->bankB->id],
            collect($response->viewData('page')['props']['banks'])->pluck('id')->all()
        );
    }

    public function test_payable_includes_leasing_installment_cheques(): void
    {
        $this->payableCheque('PAY-CHQ-WITH-LEASE', '2099-11-01', 'EGP', 400);

        $leasingCompanyName = 'Leasing Co '.uniqid();
        $leasingCompanyId = DB::table('leasing_companies')->insertGetId([
            'company_id' => $this->company->id,
            'name' => $leasingCompanyName,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $contractId = DB::table('leasing_contracts')->insertGetId([
            'company_id' => $this->company->id,
            'leasing_company_id' => $leasingCompanyId,
            'status' => 'running',
            'name' => 'Lease '.uniqid(),
            'currency' => 'egp',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('contract_loan_schedules')->insert([
            [
                'leasing_contract_id' => $contractId,
                'company_id' => $this->company->id,
                'date' => '2099-11-02',
                'cheque_amount' => 900,
                'remaining' => 0,
                'cheque_number' => 'LEASE-CHQ-PENDING',
                'drawee_bank_id' => $this->deliveryBankId,
                'actual_payment_date' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'leasing_contract_id' => $contractId,
                'company_id' => $this->company->id,
                'date' => '2099-11-03',
                'cheque_amount' => 1200,
                'remaining' => 50,
                'cheque_number' => 'LEASE-CHQ-PAID',
                'drawee_bank_id' => $this->deliveryBankId,
                'actual_payment_date' => '2099-11-03',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'leasing_contract_id' => $contractId,
                'company_id' => $this->company->id,
                'date' => '2099-10-01',
                'cheque_amount' => 10,
                'remaining' => 10,
                'cheque_number' => 'LEASE-CHQ-EARLY',
                'drawee_bank_id' => $this->deliveryBankId,
                'actual_payment_date' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'leasing_contract_id' => $contractId,
                'company_id' => $this->company->id,
                'date' => '2099-11-04',
                'cheque_amount' => 0,
                'remaining' => 0,
                'cheque_number' => 'LEASE-CHQ-ZERO',
                'drawee_bank_id' => $this->deliveryBankId,
                'actual_payment_date' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($this->userWith(['view supplier payment']))
            ->get($this->resultUrl(['type' => 'payable', 'due_date' => '2099-11-01', 'currencies' => ['EGP']]))
            ->assertOk();

        $rows = collect($response->viewData('page')['props']['paginator']['data']);
        $numbers = $rows->pluck('chequeNumber')->all();
        $this->assertContains('PAY-CHQ-WITH-LEASE', $numbers);
        $this->assertContains('LEASE-CHQ-PENDING', $numbers);
        $this->assertContains('LEASE-CHQ-PAID', $numbers);
        $this->assertNotContains('LEASE-CHQ-EARLY', $numbers);
        $this->assertNotContains('LEASE-CHQ-ZERO', $numbers);

        $pending = $rows->firstWhere('chequeNumber', 'LEASE-CHQ-PENDING');
        $this->assertSame($leasingCompanyName, $pending['partnerName']);
        $this->assertSame(900.0, $pending['amount']);
        $this->assertSame('EGP', $pending['currency']);
        $this->assertSame('pending', $pending['status']);
        $this->assertSame('leasing', $pending['source']);

        $paid = $rows->firstWhere('chequeNumber', 'LEASE-CHQ-PAID');
        $this->assertSame(1200.0, $paid['amount']);
        $this->assertSame('paid', $paid['status']);
        $this->assertSame(__('Paid'), $paid['statusLabel']);

        $this->assertSame(2500.0, collect($response->viewData('page')['props']['kpis']['perCurrency'])->firstWhere('currency', 'EGP')['total']);
    }

    public function test_payable_cheques_come_from_money_payments(): void
    {
        $this->payableCheque('PAY-CHQ-1', '2099-09-01', 'EGP', 750);
        $this->receivedCheque('CHQ-NOT-PAYABLE', '2099-09-01', 'EGP', 5, $this->bankA);

        $response = $this->actingAs($this->userWith(['view supplier payment']))
            ->get($this->resultUrl(['type' => 'payable', 'due_date' => '2099-09-01']))
            ->assertOk();

        $this->assertSame(['PAY-CHQ-1'], $this->chequeNumbers($response));
        $row = $response->viewData('page')['props']['paginator']['data'][0];
        $this->assertSame($this->supplier->name, $row['partnerName']);
        $this->assertSame(750.0, $row['amount']);
        $this->assertSame(__('Pending'), $row['statusLabel']);
    }

    public function test_a_user_cannot_open_a_kind_they_cannot_see_on_its_own_screen(): void
    {
        $this->payableCheque('PAY-CHQ-FORBIDDEN', '2099-09-01', 'EGP', 1);

        $this->actingAs($this->userWith(['view money received']))
            ->get($this->resultUrl(['type' => 'payable', 'due_date' => '2099-09-01']))
            ->assertForbidden();
    }

    public function test_the_form_only_offers_the_kinds_the_user_may_open(): void
    {
        $this->actingAs($this->userWith(['view supplier payment']))
            ->get(route('view.cheques.report', ['company' => $this->company->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Statements/ChequesReport/Index', false)
                ->where('types', fn ($types) => collect($types)->pluck('value')->all() === ['payable']));
    }

    public function test_the_due_date_and_type_are_required(): void
    {
        $this->actingAs($this->userWith(['view money received']))
            ->get($this->resultUrl(['type' => 'received']))
            ->assertSessionHasErrors('due_date');
    }

    public function test_the_excel_export_is_a_download_of_the_filtered_range(): void
    {
        $this->receivedCheque('CHQ-EXPORT', '2099-10-01', 'EGP', 10, $this->bankA);

        $response = $this->actingAs($this->userWith(['view money received']))
            ->get(route('export.cheques.report', ['company' => $this->company->id, 'type' => 'received', 'due_date' => '2099-10-01']))
            ->assertOk();

        $this->assertStringContainsString('Cheques-Report-received-2099-10-01.xlsx', $response->headers->get('content-disposition'));
    }
}
