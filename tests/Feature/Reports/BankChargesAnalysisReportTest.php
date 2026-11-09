<?php

namespace Tests\Feature\Reports;

use App\Models\AccountType;
use App\Models\Company;
use App\Models\FinancialInstitution;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use Tests\Concerns\MakesBanks;
use Tests\TestCase;

/**
 * Bank Interest, Commissions & Charges Analysis.
 *
 * Every record is made by the test itself, for a company made by the test, and rolled back
 * afterwards. The report refuses an end date after today, so the rows are dated 1999.
 *
 * Run: php artisan test --filter=BankChargesAnalysisReportTest
 */
class BankChargesAnalysisReportTest extends TestCase
{
    use DatabaseTransactions;
    use MakesBanks;

    private Company $company;

    private int $bankA;

    private int $bankB;

    private int $egpAccountA;

    private int $currentAccountTypeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            LocaleSessionRedirect::class,
            LaravelLocalizationRedirectFilter::class,
            LaravelLocalizationViewPath::class,
        ]);

        $this->company = $this->makeCompany();
        $this->currentAccountTypeId = $this->accountTypeId(AccountType::CURRENT_ACCOUNT);
        $this->bankA = $this->bank($this->company)->id;
        $this->bankB = $this->bank($this->company)->id;
        $this->egpAccountA = $this->account($this->bankA, 'EGP');
    }

    /* ───────────────────────── fixtures ───────────────────────── */

    private function account(int $institutionId, string $currency): int
    {
        return $this->currentAccount(FinancialInstitution::findOrFail($institutionId), $currency)->id;
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
        return $this->makeUser($permissions, $this->company);
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
     * Overdraft interest is worked out by the statement trigger, not by the test: 100,000 drawn
     * on 1 January at 36% a year, so the trigger's month-end row for January charges
     * 100,000 × 36% / 360 × 30 days = 3,000.00 (worked out by hand). The report must show
     * exactly that. A whole-percent rate on purpose: the trigger rounds the rate to a whole
     * percent (36.5% is charged as 37%) — a separate finding, not what this test is about.
     */
    public function test_overdraft_interest_is_the_month_end_interest_row_on_the_statement(): void
    {
        $overdraftId = DB::table('clean_overdrafts')->insertGetId([
            'company_id' => $this->company->id, 'financial_institution_id' => $this->bankA,
            'account_number' => 'OD-BCA-1', 'currency' => 'EGP', 'limit' => '1000000',
            'contract_start_date' => '1999-01-01', 'contract_end_date' => '1999-12-31',
        ]);
        DB::table('clean_overdraft_rates')->insert([
            'company_id' => $this->company->id, 'clean_overdraft_id' => $overdraftId,
            'date' => '1999-01-01', 'interest_rate' => 36, 'min_interest_rate' => 0,
        ]);
        $statement = fn (array $row) => DB::table('clean_overdraft_bank_statements')->insertGetId($row + [
            'clean_overdraft_id' => $overdraftId, 'money_received_id' => 0, 'company_id' => $this->company->id, 'limit' => 1000000, 'debit' => 0,
        ]);
        $statement(['type' => 'payment', 'date' => '1999-01-01', 'full_date' => '1999-01-01 10:00:00', 'credit' => 100000]);
        $interestRow = $statement(['type' => 'interest', 'priority' => 1, 'interest_type' => 'end_of_month', 'date' => '1999-01-31', 'full_date' => '1999-01-31 23:59:59', 'credit' => 0]);
        // The app saves the month-end row again once the month's movements are in; the trigger
        // works the interest out on that save.
        DB::table('clean_overdraft_bank_statements')->where('id', $interestRow)->update(['updated_at' => now()]);

        $response = $this->actingAs($this->viewer())->get($this->resultUrl([
            'facility_types' => [AccountType::CLEAN_OVERDRAFT],
            'currencies' => ['EGP'],
        ]))->assertOk();

        $lines = $this->lines($response);
        $this->assertSame(['OD-BCA-1'], $lines->pluck('facility_label')->unique()->values()->all());
        $this->assertSame([__('End Of Month Interest')], $lines->pluck('item')->unique()->values()->all());
        $this->assertSame('interest', $lines->first()['category']);
        $this->assertEqualsWithDelta(3000.00, $lines->sum('amount'), 0.01);
    }

    public function test_a_bank_of_another_company_is_refused(): void
    {
        $foreign = $this->bank($this->makeCompany())->id;

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
