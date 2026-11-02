<?php

namespace Tests\Feature\Facilities;

use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\TestCase;

/**
 * The company-wide facility pages show, next to each limit, what is drawn
 * (Outstanding) and what is left (Room) as of today; the Bank Accounts
 * page shows each account's end balance as of today.
 *
 * Runs against the development database inside a rolled-back transaction.
 */
class FacilityOverviewColumnsTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private FinancialInstitution $bank;

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

        if (! Schema::connection('mysql')->hasTable('clean_overdrafts')) {
            $this->markTestSkipped('No development schema on '.DB::connection('mysql')->getDatabaseName().' - set SMOKE_DB to run this suite.');
        }

        $bank = FinancialInstitution::query()->whereNotNull('company_id')->whereNotNull('bank_id')->first();
        if (! $bank) {
            $this->markTestSkipped('Development database needs a bank-type financial institution.');
        }

        $this->bank = $bank;
        $this->company = Company::findOrFail($bank->company_id);
    }

    private function user(): User
    {
        $user = User::create([
            'name' => 'Facility Overview '.uniqid(),
            'email' => 'facility-overview-'.uniqid().'@example.test',
            'password' => bcrypt('secret-for-tests'),
            'company_id' => $this->company->id,
        ]);
        $user->givePermissionTo(['view financial institutions']);
        $user->companies()->attach($this->company->id);
        $user->load('companies');

        return $user;
    }

    /** @return array<string, mixed> the page row for that id */
    private function row(string $routeName, string $id): array
    {
        $response = $this->actingAs($this->user())->get(route($routeName, ['company' => $this->company->id]));
        $response->assertOk();

        $row = collect($response->viewData('page')['props']['rows'])->firstWhere('id', $id);
        $this->assertNotNull($row, "Row {$id} is missing from {$routeName}.");

        return $row;
    }

    public function test_a_bank_account_shows_its_end_balance_as_of_today(): void
    {
        $account = FinancialInstitutionAccount::create([
            'company_id' => $this->company->id,
            'financial_institution_id' => $this->bank->id,
            'account_number' => 'TEST-FAC-'.uniqid(),
            'currency' => 'EGP',
            'balance_amount' => 0,
            'balance_date' => now()->subYear()->format('Y-m-d'),
            'exchange_rate' => 1,
            'is_active' => 1,
        ]);
        foreach ([
            [now()->subMonth(), 1000, 0, 1],   // opening
            [now()->subWeek(), 0, 250, 1],     // a payment
            [now()->addWeek(), 5000, 0, 1],    // future-dated: not today's balance
        ] as [$date, $debit, $credit, $isActive]) {
            DB::table('current_account_bank_statements')->insert([
                'financial_institution_account_id' => $account->id,
                'company_id' => $this->company->id,
                'date' => $date->format('Y-m-d'),
                'full_date' => $date->format('Y-m-d H:i:s'),
                'beginning_balance' => 0,
                'debit' => $debit,
                'credit' => $credit,
                'is_active' => $isActive,
            ]);
        }

        $this->assertEquals(750, FinancialInstitutionAccount::endBalancesAt($this->company->id, [$account->id], now()->format('Y-m-d'))[$account->id]);

        $row = $this->row('financial-institution-facilities.bank-accounts', (string) $account->id);
        $this->assertEquals(750, $row['end_balance']);
    }

    public function test_an_undrawn_overdraft_shows_nothing_outstanding_and_its_whole_limit_as_room(): void
    {
        $id = DB::table('clean_overdrafts')->insertGetId([
            'company_id' => $this->company->id,
            'financial_institution_id' => $this->bank->id,
            'account_number' => 'TEST-OD-'.uniqid(),
            'currency' => 'EGP',
            'limit' => 400000,
            'contract_start_date' => now()->subYear()->format('Y-m-d'),
            'contract_end_date' => now()->addYear()->format('Y-m-d'),
        ]);

        $row = $this->row('financial-institution-facilities.oda-mtl', __('Clean Overdraft').'-'.$id);
        $this->assertEquals(400000, $row['limit_amount']);
        $this->assertEquals(0, $row['outstanding']);
        $this->assertEquals(400000, $row['room']);
    }

    public function test_an_lg_facility_room_is_its_limit_less_what_is_outstanding(): void
    {
        $id = DB::table('letter_of_guarantee_facilities')->insertGetId([
            'company_id' => $this->company->id,
            'financial_institution_id' => $this->bank->id,
            'name' => 'TEST-LG-FAC',
            'currency' => 'EGP',
            'limit' => 900000,
            'contract_start_date' => now()->subYear()->format('Y-m-d'),
            'contract_end_date' => now()->addYear()->format('Y-m-d'),
        ]);
        // An advance-payment LG not activated yet: the bank holds its amount back.
        DB::table('letter_of_guarantee_issuances')->insert([
            'company_id' => $this->company->id,
            'financial_institution_id' => $this->bank->id,
            'lg_facility_id' => $id,
            'source' => 'lg-facility',
            'lg_type' => 'advanced-payment-lgs',
            'lg_code' => 'TEST-UNACTIVATED',
            'lg_amount' => 150000,
            'lg_currency' => 'EGP',
            'is_activated' => 0,
            'status' => 'running',
            'renewal_date' => now()->addYear()->format('Y-m-d'),
        ]);

        $row = $this->row('financial-institution-facilities.lg-lc', 'lg-'.$id);
        $this->assertSame('lg', $row['kind']);
        $this->assertEquals(150000, $row['outstanding']);
        $this->assertEquals(750000, $row['room']);
    }
}
