<?php

namespace Tests\Feature\Reports;

use App\Models\AccountType;
use App\Models\Bank;
use App\Models\Company;
use App\Models\FinancialInstitution;
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
 * Bank Interest, Commissions & Charges Analysis.
 *
 * The report refuses an end date after today (overdraft statements carry
 * month-end interest rows for months that have not happened yet), so the
 * usual "everything in 2099" isolation is not available here. Instead every
 * row is dated 1999 — years before any real data in the development
 * database — sits under two banks created for the test, and is rolled back
 * with the transaction.
 */
class BankChargesAnalysisReportTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;

    private int $bankA;

    private int $bankB;

    private int $egpAccountA;

    private int $currentAccountTypeId;

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

        if (! Schema::connection('mysql')->hasTable('medium_term_loan_early_settlements')) {
            $this->markTestSkipped('No development schema on '.DB::connection('mysql')->getDatabaseName().' - set SMOKE_DB to run this suite.');
        }

        $institution = FinancialInstitution::query()->whereNotNull('company_id')->where('type', FinancialInstitution::BANK)->first();
        $banks = Bank::query()->limit(2)->get();
        $this->currentAccountTypeId = (int) AccountType::where('slug', AccountType::CURRENT_ACCOUNT)->value('id');
        if (! $institution || $banks->count() < 2 || ! $this->currentAccountTypeId) {
            $this->markTestSkipped('Development database needs a company with a bank, two banks and the account types.');
        }

        $this->company = Company::findOrFail($institution->company_id);
        $this->bankA = $this->institution($banks[0]->id);
        $this->bankB = $this->institution($banks[1]->id);
        $this->egpAccountA = $this->account($this->bankA, 'EGP');
    }

    /* ───────────────────────── fixtures ───────────────────────── */

    private function institution(int $bankId): int
    {
        return DB::table('financial_institutions')->insertGetId([
            'type' => FinancialInstitution::BANK,
            'bank_id' => $bankId,
            'company_id' => $this->company->id,
            'updated_by' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function account(int $institutionId, string $currency): int
    {
        return DB::table('financial_institution_accounts')->insertGetId([
            'financial_institution_id' => $institutionId,
            'company_id' => $this->company->id,
            'account_number' => 'BCA-'.uniqid(),
            'currency' => $currency,
            'is_active' => 1,
        ]);
    }

    private function letterOfGuarantee(int $institutionId): int
    {
        $facilityId = DB::table('letter_of_guarantee_facilities')->insertGetId([
            'company_id' => $this->company->id,
            'financial_institution_id' => $institutionId,
            'name' => 'BCA LG Facility',
            'currency' => 'EGP',
            'limit' => 1000000,
        ]);

        return DB::table('letter_of_guarantee_issuances')->insertGetId([
            'company_id' => $this->company->id,
            'financial_institution_id' => $institutionId,
            'lg_facility_id' => $facilityId,
            'source' => 'lg-facility',
            'lg_type' => 'bid-bond',
            'lg_currency' => 'EGP',
            'transaction_name' => 'BCA LG',
        ]);
    }

    private function feeRow(int $issuanceId, string $date, float $amount, array $flags, int $isActive = 1): void
    {
        DB::table('current_account_bank_statements')->insert([
            'company_id' => $this->company->id,
            'money_received_id' => 0,
            'money_payment_id' => 0,
            'financial_institution_account_id' => $this->egpAccountA,
            'letter_of_guarantee_issuance_id' => $issuanceId,
            'date' => $date,
            'full_date' => $date.' 00:00:00',
            'debit' => 0,
            'credit' => $amount,
            'is_active' => $isActive,
            'is_commission_fees' => $flags['commission'] ?? 0,
            'is_issuance_fees' => $flags['issuance'] ?? 0,
            'is_renewal_fees' => $flags['renewal'] ?? 0,
        ]);
    }

    private function bankCharge(int $institutionId, string $currency, float $amount, string $date): void
    {
        $expenseId = DB::table('cash_expenses')->insertGetId([
            'company_id' => $this->company->id,
            'type' => 'outgoing-transfer',
            'payment_date' => $date,
            'paid_amount' => $amount,
            'currency' => $currency,
            'comment_en' => 'BCA bank charge',
        ]);
        DB::table('outgoing_transfers')->insert([
            'cash_expense_id' => $expenseId,
            'is_bank_charges' => 1,
            'delivery_bank_id' => $institutionId,
            'account_type' => (string) $this->currentAccountTypeId,
            'account_number' => 'BCA-CHARGES-'.$currency,
        ]);
    }

    private function userWith(array $permissions): User
    {
        $user = User::create([
            'name' => 'Bank Charges Analysis '.uniqid(),
            'email' => 'bank-charges-analysis-'.uniqid().'@example.test',
            'password' => bcrypt('secret-for-tests'),
            'company_id' => $this->company->id,
        ]);
        $user->givePermissionTo($permissions);
        $user->companies()->attach($this->company->id);
        $user->load('companies');

        return $user;
    }

    private function viewer(): User
    {
        return $this->userWith(['view bank statement report']);
    }

    private function resultUrl(array $query, string $route = 'result.bank.charges.analysis.report'): string
    {
        return route($route, ['company' => $this->company->id] + $query + [
            'start_date' => '1999-01-01',
            'end_date' => '1999-12-31',
            'financial_institution_ids' => [$this->bankA, $this->bankB],
        ]);
    }

    private function props($response): array
    {
        return $response->viewData('page')['props'];
    }

    private function lines($response): \Illuminate\Support\Collection
    {
        return collect($this->props($response)['paginator']['data']);
    }

    /* ───────────────────────── tests ───────────────────────── */

    public function test_an_lg_commission_row_counts_as_commission_even_though_it_carries_the_issuance_flag(): void
    {
        $issuance = $this->letterOfGuarantee($this->bankA);
        // HasCommissionStatements writes commission rows with BOTH flags set.
        $this->feeRow($issuance, '1999-03-31', 1000, ['commission' => 1, 'issuance' => 1]);
        $this->feeRow($issuance, '1999-01-15', 250, ['issuance' => 1]);
        $this->feeRow($issuance, '1999-06-30', 75, ['renewal' => 1]);

        $response = $this->actingAs($this->viewer())->get($this->resultUrl([]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Statements/BankChargesAnalysis/Result', false));

        $kpis = $this->props($response)['kpis'];
        $this->assertEquals(1000.0, $kpis['commission']);
        $this->assertEquals(325.0, $kpis['charges']);
        $this->assertEquals(1325.0, $kpis['total']);

        $items = $this->lines($response)->pluck('category', 'item')->all();
        $this->assertSame('commission', $items[__('LG Commission')]);
        $this->assertSame('charges', $items[__('Issuance Fees')]);
        $this->assertSame('charges', $items[__('Renewal Fees')]);

        $facility = $this->props($response)['summary']['banks'][0]['facilities'][0];
        $this->assertSame('BCA LG Facility', $facility['facility_label']);
        $this->assertSame(\App\Services\Reports\BankChargesAnalysisService::LETTER_OF_GUARANTEE, $facility['facility_type']);
    }

    public function test_commission_rows_not_yet_active_are_left_out(): void
    {
        $issuance = $this->letterOfGuarantee($this->bankA);
        $this->feeRow($issuance, '1999-03-31', 1000, ['commission' => 1, 'issuance' => 1]);
        $this->feeRow($issuance, '1999-06-30', 1000, ['commission' => 1, 'issuance' => 1], isActive: 0);

        $response = $this->actingAs($this->viewer())->get($this->resultUrl([]))->assertOk();

        $this->assertEquals(1000.0, $this->props($response)['kpis']['commission']);
        $this->assertSame(1, $this->props($response)['kpis']['lineCount']);
    }

    public function test_loan_interest_is_what_was_due_and_an_early_settled_installment_only_counts_what_was_paid(): void
    {
        $loanId = DB::table('medium_term_loans')->insertGetId([
            'company_id' => $this->company->id,
            'financial_institution_id' => $this->bankA,
            'currency' => 'EGP',
            'installment_payment_interval' => 'monthly',
            'name' => 'BCA Loan',
            'account_number' => 'BCA-MTL-1',
        ]);
        $settlementId = DB::table('medium_term_loan_early_settlements')->insertGetId([
            'company_id' => $this->company->id,
            'medium_term_loan_id' => $loanId,
            'date' => '1999-05-15',
            'amount' => 10000,
            'fee_amount' => 120,
            'account_type_id' => $this->currentAccountTypeId,
            'account_number' => 'BCA-CA-1',
            'interest_waived' => 500,
        ]);
        DB::table('loan_schedules')->insert([
            'medium_term_loan_id' => $loanId, 'company_id' => $this->company->id, 'date' => '1999-04-30',
            'schedule_payment' => 1500, 'interest_amount' => 500, 'principle_amount' => 1000, 'remaining' => 1500,
        ]);
        $settledScheduleId = DB::table('loan_schedules')->insertGetId([
            'medium_term_loan_id' => $loanId, 'company_id' => $this->company->id, 'date' => '1999-05-31',
            'schedule_payment' => 1800, 'interest_amount' => 800, 'principle_amount' => 1000, 'remaining' => 1500,
            'medium_term_loan_early_settlement_id' => $settlementId,
        ]);
        DB::table('loan_schedule_settlements')->insert([
            'loan_schedule_id' => $settledScheduleId, 'amount' => 300, 'date' => '1999-05-10', 'current_account_number' => 'BCA-CA-1',
        ]);
        // Outside the period — not counted.
        DB::table('loan_schedules')->insert([
            'medium_term_loan_id' => $loanId, 'company_id' => $this->company->id, 'date' => '2000-01-31',
            'schedule_payment' => 1500, 'interest_amount' => 999, 'principle_amount' => 501, 'remaining' => 1500,
        ]);

        $response = $this->actingAs($this->viewer())->get($this->resultUrl([]))->assertOk();

        $kpis = $this->props($response)['kpis'];
        $this->assertEquals(800.0, $kpis['interest'], '500 due + 300 paid before the early settlement');
        $this->assertEquals(120.0, $kpis['charges'], 'the early settlement fee');

        $facility = $this->props($response)['summary']['banks'][0]['facilities'][0];
        $this->assertSame('BCA Loan (BCA-MTL-1)', $facility['facility_label']);
        $this->assertEquals(920.0, $facility['total']);
    }

    public function test_several_currencies_are_converted_to_the_main_currency_and_one_currency_is_shown_as_is(): void
    {
        $this->account($this->bankB, 'USD');
        $main = $this->company->getMainFunctionalCurrency();
        DB::table('foreign_exchange_rates')->insert([
            'company_id' => $this->company->id, 'from_currency' => 'USD', 'to_currency' => $main,
            'date' => '1999-01-01', 'exchange_rate' => 5,
        ]);
        $this->bankCharge($this->bankB, 'USD', 10, '1999-02-10');
        $this->bankCharge($this->bankA, $main, 7, '1999-02-11');
        $user = $this->viewer();

        $all = $this->actingAs($user)->get($this->resultUrl([]))->assertOk();
        $this->assertTrue($this->props($all)['isConverted']);
        $this->assertSame($main, $this->props($all)['displayCurrency']);
        $this->assertEquals(57.0, $this->props($all)['kpis']['charges'], '10 USD × 5 + 7');
        $usdLine = $this->lines($all)->firstWhere('currency', 'USD');
        $this->assertEquals(10.0, $usdLine['amount']);
        $this->assertEquals(5.0, $usdLine['exchange_rate']);
        $this->assertEquals(50.0, $usdLine['amount_main']);
        $this->assertSame(\App\Models\AccountType::CURRENT_ACCOUNT, $usdLine['facility_type']);

        $usdOnly = $this->actingAs($user)->get($this->resultUrl(['currencies' => ['USD']]))->assertOk();
        $this->assertFalse($this->props($usdOnly)['isConverted']);
        $this->assertSame('USD', $this->props($usdOnly)['displayCurrency']);
        $this->assertEquals(10.0, $this->props($usdOnly)['kpis']['total']);
        $this->assertSame(['USD'], $this->lines($usdOnly)->pluck('currency')->unique()->values()->all());
    }

    public function test_a_currency_without_any_rate_is_reported(): void
    {
        $this->account($this->bankB, 'USD');
        $this->bankCharge($this->bankB, 'USD', 10, '1999-02-10');
        $this->bankCharge($this->bankA, $this->company->getMainFunctionalCurrency(), 7, '1999-02-11');

        $response = $this->actingAs($this->viewer())->get($this->resultUrl([]))->assertOk();

        $this->assertSame(['USD'], $this->props($response)['missingRates']);
    }

    public function test_the_bank_filter_narrows_the_report(): void
    {
        $this->bankCharge($this->bankA, 'EGP', 7, '1999-02-11');
        $this->bankCharge($this->bankB, 'EGP', 3, '1999-02-12');

        $response = $this->actingAs($this->viewer())
            ->get($this->resultUrl(['financial_institution_ids' => [$this->bankB], 'currencies' => ['EGP']]))
            ->assertOk();

        $this->assertEquals(3.0, $this->props($response)['kpis']['total']);
        $this->assertSame([$this->bankB], collect($this->props($response)['summary']['banks'])->pluck('bank_id')->all());
    }

    public function test_the_facility_type_filter_narrows_the_report(): void
    {
        $issuance = $this->letterOfGuarantee($this->bankA);
        $this->feeRow($issuance, '1999-03-31', 1000, ['commission' => 1, 'issuance' => 1]);
        $this->bankCharge($this->bankA, 'EGP', 7, '1999-02-11');

        $response = $this->actingAs($this->viewer())
            ->get($this->resultUrl(['facility_types' => [AccountType::CURRENT_ACCOUNT]]))
            ->assertOk();

        $this->assertSame([AccountType::CURRENT_ACCOUNT], $this->lines($response)->pluck('facility_type')->unique()->values()->all());
        $this->assertEquals(7.0, $this->props($response)['kpis']['total']);
    }

    /**
     * Overdraft interest is written by the statement triggers, so rather
     * than fake it, reconcile against a real month-end interest row.
     */
    public function test_overdraft_interest_is_the_month_end_interest_row_on_the_statement(): void
    {
        $row = DB::table('clean_overdraft_bank_statements as s')
            ->join('clean_overdrafts as f', 'f.id', '=', 's.clean_overdraft_id')
            ->where('s.type', 'interest')->where('s.interest_type', 'end_of_month')
            ->where('s.credit', '>', 0)->where('s.date', '<=', now()->toDateString())
            ->first(['s.company_id', 's.date', 's.credit', 'f.financial_institution_id', 'f.account_number', 'f.currency']);

        if (! $row) {
            $this->markTestSkipped('No posted clean overdraft month-end interest in the development database.');
        }

        // Whichever company that row belongs to — the viewer is attached to it.
        $this->company = Company::findOrFail($row->company_id);

        $response = $this->actingAs($this->viewer())->get(route('result.bank.charges.analysis.report', [
            'company' => $this->company->id,
            'start_date' => $row->date,
            'end_date' => $row->date,
            'financial_institution_ids' => [$row->financial_institution_id],
            'facility_types' => [AccountType::CLEAN_OVERDRAFT],
            'currencies' => [strtoupper($row->currency)],
        ]))->assertOk();

        $lines = $this->lines($response)->where('facility_label', $row->account_number);
        $this->assertSame([__('End Of Month Interest')], $lines->pluck('item')->unique()->values()->all());
        $this->assertSame('interest', $lines->first()['category']);
        $this->assertEqualsWithDelta((float) $row->credit, $lines->sum('amount'), 0.01);
    }

    public function test_a_bank_of_another_company_is_refused(): void
    {
        $foreign = FinancialInstitution::query()->where('company_id', '!=', $this->company->id)->value('id');
        if (! $foreign) {
            $this->markTestSkipped('Needs a second company with a bank.');
        }

        $this->actingAs($this->viewer())
            ->get($this->resultUrl(['financial_institution_ids' => [$foreign]]))
            ->assertSessionHasErrors('financial_institution_ids.0');
    }

    public function test_an_end_date_after_today_is_refused(): void
    {
        $this->actingAs($this->viewer())
            ->get($this->resultUrl(['end_date' => now()->addDay()->toDateString()]))
            ->assertSessionHasErrors('end_date');
    }

    public function test_a_user_without_the_permission_is_forbidden(): void
    {
        $this->actingAs($this->userWith([]))
            ->get($this->resultUrl([]))
            ->assertForbidden();
    }

    public function test_export_and_print_cover_the_whole_report(): void
    {
        $issuance = $this->letterOfGuarantee($this->bankA);
        $this->feeRow($issuance, '1999-03-31', 1000, ['commission' => 1, 'issuance' => 1]);
        $this->bankCharge($this->bankB, 'EGP', 7, '1999-02-11');
        $user = $this->viewer();

        $export = $this->actingAs($user)->get($this->resultUrl(['currencies' => ['EGP']], 'export.bank.charges.analysis.report'))->assertOk();
        $this->assertStringContainsString('.xlsx', (string) $export->headers->get('content-disposition'));

        $print = $this->actingAs($user)->get($this->resultUrl(['currencies' => ['EGP']], 'print.bank.charges.analysis.report'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Statements/Print', false));
        $props = $this->props($print);
        // Two facility rows, each followed by its bank's subtotal row.
        $this->assertCount(4, $props['rows']);
        $this->assertSame('1,007.00', $props['totals']['Total']);
    }
}
