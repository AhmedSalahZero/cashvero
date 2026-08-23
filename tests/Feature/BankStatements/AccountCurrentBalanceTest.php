<?php

namespace Tests\Feature\BankStatements;

use App\Support\BankStatements\AccountCurrentBalance;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The account balance the LG / LC issuance forms offer as available
 * cash cover.
 *
 * Both forms read the end_balance of the account's LAST statement row
 * by date, unbounded. Every account carries generated month-end
 * interest rows out to the end of whatever year the system has synced —
 * often years ahead — so "the last row" is a FUTURE row.
 *
 * On live data account 314 showed −1,946,026.24, read from a row dated
 * 2028-12-31, while its balance today was +1,880,259.76. Wrong by 3.8
 * million, and the wrong sign.
 *
 * @see \App\Support\BankStatements\AccountCurrentBalance
 */
class AccountCurrentBalanceTest extends TestCase
{
    private const ACCOUNT = 314;

    private const OTHER_ACCOUNT = 315;

    private const COMPANY = 92;

    protected function setUp(): void
    {
        parent::setUp();

        $database = DB::connection()->getDatabaseName();
        $this->assertStringContainsString('test', $database, "Refusing to run against '{$database}'.");

        Schema::dropIfExists('current_account_bank_statements');
        Schema::create('current_account_bank_statements', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->integer('financial_institution_account_id')->default(0);
            $table->boolean('is_beginning_balance')->default(0);
            $table->boolean('is_active')->default(1);
            $table->string('type')->nullable();
            $table->string('interest_type')->nullable();
            $table->date('date')->nullable();
            $table->dateTime('full_date')->nullable();
            $table->decimal('beginning_balance', 14)->default(0);
            $table->decimal('debit', 14)->default(0);
            $table->decimal('credit', 14)->default(0);
            $table->decimal('end_balance', 14)->default(0);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('current_account_bank_statements');

        parent::tearDown();
    }

    private function row(string $date, float $endBalance, array $overrides = []): void
    {
        DB::table('current_account_bank_statements')->insert($overrides + [
            'company_id' => self::COMPANY,
            'financial_institution_account_id' => self::ACCOUNT,
            'date' => $date,
            'full_date' => $date.' 10:00:00',
            'end_balance' => $endBalance,
        ]);
    }

    private function balanceOf(int $account = self::ACCOUNT, ?string $asOf = null): float
    {
        return AccountCurrentBalance::forAccounts([self::ACCOUNT, self::OTHER_ACCOUNT], $asOf)->get($account, 0.0);
    }

    // ---------------------------------------------------------------
    // the reported case
    // ---------------------------------------------------------------

    /**
     * Account 314's exact shape: real movement up to today, then
     * generated month-end rows carrying accrued interest out to 2028.
     */
    public function test_a_future_generated_row_does_not_become_the_current_balance(): void
    {
        $this->row(Carbon::today()->subMonths(2)->format('Y-m-d'), 1880259.76);
        $this->row('2027-12-31', -900000.00, ['type' => 'interest', 'interest_type' => 'end_of_month']);
        $this->row('2028-12-31', -1946026.24, ['type' => 'interest', 'interest_type' => 'end_of_month']);

        $this->assertSame(1880259.76, $this->balanceOf(),
            'The balance offered as cash cover must be today\'s, not a projection two years out.');
    }

    public function test_the_latest_row_up_to_today_wins(): void
    {
        $this->row(Carbon::today()->subDays(30)->format('Y-m-d'), 100.00);
        $this->row(Carbon::today()->subDays(5)->format('Y-m-d'), 250.00);
        $this->row(Carbon::today()->addDays(5)->format('Y-m-d'), 999.00);

        $this->assertSame(250.00, $this->balanceOf());
    }

    public function test_a_row_dated_today_counts(): void
    {
        $this->row(Carbon::today()->subDays(3)->format('Y-m-d'), 100.00);
        $this->row(Carbon::today()->format('Y-m-d'), 777.00);

        $this->assertSame(777.00, $this->balanceOf(), 'Today is on or before today.');
    }

    /**
     * Two rows on the same date: the later id is the later entry.
     */
    public function test_the_last_entry_of_the_day_wins(): void
    {
        $date = Carbon::today()->subDay()->format('Y-m-d');
        $this->row($date, 100.00);
        $this->row($date, 480.00);

        $this->assertSame(480.00, $this->balanceOf());
    }

    // ---------------------------------------------------------------
    // edges
    // ---------------------------------------------------------------

    public function test_an_account_with_no_rows_reads_zero(): void
    {
        $this->assertSame(0.0, $this->balanceOf());
    }

    public function test_an_account_whose_every_row_is_in_the_future_reads_zero(): void
    {
        $this->row(Carbon::today()->addMonth()->format('Y-m-d'), 5000.00);

        $this->assertSame(0.0, $this->balanceOf(),
            'Nothing has happened yet, so there is no balance to offer.');
    }

    public function test_an_empty_account_list_asks_the_database_nothing(): void
    {
        $this->row(Carbon::today()->format('Y-m-d'), 123.00);

        $this->assertTrue(AccountCurrentBalance::forAccounts([])->isEmpty());
    }

    public function test_accounts_do_not_read_each_others_balances(): void
    {
        $this->row(Carbon::today()->subDay()->format('Y-m-d'), 111.00);
        $this->row(Carbon::today()->subDay()->format('Y-m-d'), 222.00, [
            'financial_institution_account_id' => self::OTHER_ACCOUNT,
        ]);

        $this->assertSame(111.00, $this->balanceOf(self::ACCOUNT));
        $this->assertSame(222.00, $this->balanceOf(self::OTHER_ACCOUNT));
    }

    /**
     * A negative balance is a real overdrawn position, not something to
     * clamp away.
     */
    public function test_a_negative_balance_is_reported_as_it_stands(): void
    {
        $this->row(Carbon::today()->subDay()->format('Y-m-d'), -45000.00);

        $this->assertSame(-45000.00, $this->balanceOf());
    }

    /**
     * The as-of date is a parameter, so the same figure can be asked
     * for at a past point in time.
     */
    public function test_the_balance_can_be_asked_for_at_an_earlier_date(): void
    {
        $this->row('2026-01-31', 1000.00);
        $this->row('2026-06-30', 2000.00);
        $this->row(Carbon::today()->subDay()->format('Y-m-d'), 3000.00);

        $this->assertSame(1000.00, $this->balanceOf(self::ACCOUNT, '2026-03-01'));
        $this->assertSame(2000.00, $this->balanceOf(self::ACCOUNT, '2026-07-01'));
        $this->assertSame(3000.00, $this->balanceOf());
    }

    // ---------------------------------------------------------------
    // both forms use it
    // ---------------------------------------------------------------

    /**
     * @dataProvider issuanceControllerProvider
     */
    public function test_the_issuance_forms_read_the_balance_as_of_today(string $controller): void
    {
        $source = file_get_contents(app_path("Http/Controllers/{$controller}.php"));

        $this->assertStringContainsString('AccountCurrentBalance::forAccounts', $source,
            "{$controller} still reads the last statement row without bounding it at today.");
        $this->assertStringNotContainsString("->orderByDesc('date')->orderByDesc('id')->get()\n            ->groupBy('financial_institution_account_id')", $source,
            "{$controller} still carries its own unbounded copy of the lookup.");
    }

    public static function issuanceControllerProvider(): array
    {
        return [
            'LG issuance' => ['LetterOfGuaranteeIssuanceController'],
            'LC issuance' => ['LetterOfCreditIssuanceController'],
        ];
    }
}
