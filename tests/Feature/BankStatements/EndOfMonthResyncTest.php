<?php

namespace Tests\Feature\BankStatements;

use App\Models\CurrentAccountBankStatement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Moving an account's Balance Date must leave exactly one month-end
 * interest row for every month the account was open, in both
 * directions.
 *
 * Forward was fixed first (the extra months are deleted). BACKWARDS was
 * the half left over: the months between the new date and the old one
 * never got their rows, and `synced_end_of_month_years` — the only
 * guard against duplicates at the time — made sure they never would.
 * Two accounts on record are missing rows for exactly that reason.
 *
 * What unlocked it is `end_of_month_period`: the row's own date cannot
 * say which month it stands for, because a user can edit it (7 rows on
 * record sit mid-month). With the period stamped on the row, the
 * generator can tell a month that already has a row from one that does
 * not, so re-running it is safe.
 *
 * @see \App\Models\CurrentAccountBankStatement::handleEndOfMonthInterestForCurrentAccountStatement()
 * @see \App\Models\CurrentAccountBankStatement::resyncEndOfMonthInterestForAllYears()
 */
class EndOfMonthResyncTest extends TestCase
{
    private const COMPANY = 146;

    private const ACCOUNT = 77;

    /** @var list<string> */
    private array $tables = [
        'record_activities',
        'current_account_bank_statements',
        'financial_institution_accounts',
        'temp_deleted_statements',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $database = DB::connection()->getDatabaseName();
        $this->assertStringContainsString('test', $database, "Refusing to run against '{$database}'.");

        $this->dropTables();

        Schema::create('financial_institution_accounts', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->date('balance_date')->nullable();
            $table->json('synced_end_of_month_years')->nullable();
            $table->timestamps();
        });

        Schema::create('current_account_bank_statements', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->integer('financial_institution_account_id')->default(0);
            $table->boolean('is_beginning_balance')->default(0);
            $table->boolean('is_active')->default(1);
            $table->boolean('is_debit')->default(0);
            $table->boolean('is_credit')->default(0);
            $table->string('type')->nullable();
            $table->string('interest_type')->nullable();
            $table->string('end_of_month_period', 7)->nullable();
            $table->date('date')->nullable();
            $table->dateTime('full_date')->nullable();
            $table->decimal('beginning_balance', 14)->default(0);
            $table->decimal('debit', 14)->default(0);
            $table->decimal('credit', 14)->default(0);
            $table->decimal('end_balance', 14)->default(0);
            $table->unsignedBigInteger('interest_journal_entry_id')->nullable();
            $table->string('interest_odoo_reference')->nullable();
            $table->unsignedBigInteger('interest_account_bank_statement_odoo_id')->nullable();
            $table->string('comment_en')->nullable();
            $table->string('comment_ar')->nullable();
            $table->timestamps();
        });

        // RecordActivityObserver logs the account update the generator makes.
        Schema::create('record_activities', function ($table) {
            $table->bigIncrements('id');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->string('event')->nullable();
            $table->text('description')->nullable();
            $table->longText('field_changes')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('temp_deleted_statements', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->default(0);
            $table->string('table_name')->nullable();
            $table->unsignedBigInteger('deleted_id')->default(0);
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        $this->dropTables();

        parent::tearDown();
    }

    private function dropTables(): void
    {
        foreach ($this->tables as $table) {
            Schema::dropIfExists($table);
        }
    }

    // ---------------------------------------------------------------
    // fixtures
    // ---------------------------------------------------------------

    private function account(string $balanceDate, array $syncedYears = []): void
    {
        DB::table('financial_institution_accounts')->insert([
            'id' => self::ACCOUNT,
            'company_id' => self::COMPANY,
            'balance_date' => $balanceDate,
            'synced_end_of_month_years' => json_encode($syncedYears),
        ]);
    }

    private function beginningBalance(string $date, float $amount = 64450.61): CurrentAccountBankStatement
    {
        DB::table('current_account_bank_statements')->insert([
            'company_id' => self::COMPANY,
            'financial_institution_account_id' => self::ACCOUNT,
            'is_beginning_balance' => 1,
            'date' => $date,
            'full_date' => $date.' 10:00:00',
            'debit' => $amount,
            'comment_en' => 'Beginning Balance',
        ]);

        return CurrentAccountBankStatement::withoutGlobalScopes()
            ->where('financial_institution_account_id', self::ACCOUNT)
            ->where('is_beginning_balance', 1)
            ->firstOrFail();
    }

    private function monthEndRow(string $date, ?string $period = null, array $overrides = []): int
    {
        DB::table('current_account_bank_statements')->insert($overrides + [
            'company_id' => self::COMPANY,
            'financial_institution_account_id' => self::ACCOUNT,
            'type' => 'interest',
            'interest_type' => 'end_of_month',
            'end_of_month_period' => $period ?? substr($date, 0, 7),
            'date' => $date,
            'full_date' => $date.' 00:00:01',
            'debit' => 0,
            'credit' => 0,
            'comment_en' => 'End Of Month Interest',
        ]);

        return (int) DB::getPdo()->lastInsertId();
    }

    /** @return list<string> */
    private function monthEndDates(): array
    {
        return DB::table('current_account_bank_statements')
            ->where('financial_institution_account_id', self::ACCOUNT)
            ->whereIn('interest_type', ['end_of_month', 'end_of_month_final'])
            ->orderBy('date')
            ->pluck('date')
            ->all();
    }

    /** @return list<string> */
    private function monthEndPeriods(): array
    {
        return DB::table('current_account_bank_statements')
            ->where('financial_institution_account_id', self::ACCOUNT)
            ->whereIn('interest_type', ['end_of_month', 'end_of_month_final'])
            ->orderBy('end_of_month_period')
            ->pluck('end_of_month_period')
            ->all();
    }

    // ---------------------------------------------------------------
    // generating from scratch
    // ---------------------------------------------------------------

    public function test_it_generates_one_row_for_every_month_after_the_balance_date(): void
    {
        $this->account('2026-06-30');
        $row = $this->beginningBalance('2026-06-30');

        $row->handleEndOfMonthInterestForCurrentAccountStatement('2026-06-30', self::COMPANY);

        $this->assertSame([
            '2026-07-31', '2026-08-31', '2026-09-30',
            '2026-10-31', '2026-11-30', '2026-12-31',
        ], $this->monthEndDates(), 'July through December — six months after a 30 June balance date.');
    }

    public function test_every_generated_row_records_the_month_it_stands_for(): void
    {
        $this->account('2026-06-30');
        $row = $this->beginningBalance('2026-06-30');

        $row->handleEndOfMonthInterestForCurrentAccountStatement('2026-06-30', self::COMPANY);

        $this->assertSame([
            '2026-07', '2026-08', '2026-09', '2026-10', '2026-11', '2026-12',
        ], $this->monthEndPeriods());
    }

    public function test_nothing_is_generated_before_the_balance_date(): void
    {
        $this->account('2026-06-30');
        $row = $this->beginningBalance('2026-06-30');

        $row->handleEndOfMonthInterestForCurrentAccountStatement('2026-06-30', self::COMPANY);

        foreach ($this->monthEndDates() as $date) {
            $this->assertGreaterThan('2026-06-30', $date, "{$date} is on or before the balance date.");
        }
    }

    // ---------------------------------------------------------------
    // idempotence — what makes re-running safe
    // ---------------------------------------------------------------

    public function test_running_it_again_creates_nothing(): void
    {
        $this->account('2026-06-30');
        $row = $this->beginningBalance('2026-06-30');

        $row->handleEndOfMonthInterestForCurrentAccountStatement('2026-06-30', self::COMPANY);
        $first = $this->monthEndDates();

        // force = true, so the synced-years fast path cannot be what stops it
        $row->handleEndOfMonthInterestForCurrentAccountStatement('2026-06-30', self::COMPANY, true);
        $row->handleEndOfMonthInterestForCurrentAccountStatement('2026-06-30', self::COMPANY, true);

        $this->assertSame($first, $this->monthEndDates(), 'Re-running duplicated rows.');
        $this->assertCount(6, $this->monthEndDates());
    }

    /**
     * The reason the period column had to exist: a user can edit a
     * row's date from the bank statement screen, and 7 rows on record
     * already sit mid-month. Matching on the date alone would decide
     * October has no row and make a second one.
     */
    public function test_a_row_whose_date_was_edited_still_counts_for_its_month(): void
    {
        $this->account('2026-06-30', ['2026']);
        $row = $this->beginningBalance('2026-06-30');

        // July's row, dragged to the 15th by hand.
        $this->monthEndRow('2026-07-15', '2026-07');

        $row->handleEndOfMonthInterestForCurrentAccountStatement('2026-06-30', self::COMPANY, true);

        $this->assertSame(
            ['2026-07', '2026-08', '2026-09', '2026-10', '2026-11', '2026-12'],
            $this->monthEndPeriods(),
            'July must not get a second row just because its date moved.'
        );
        $this->assertContains('2026-07-15', $this->monthEndDates(), 'and the edited date is left alone');
    }

    // ---------------------------------------------------------------
    // moving the balance date BACKWARDS — the bug this fixes
    // ---------------------------------------------------------------

    /**
     * The reported case: rows generated from a June balance date, then
     * the date moved back to April. April, May and June now have a
     * balance to accrue on and had no rows at all.
     */
    public function test_moving_the_balance_date_backwards_fills_the_months_it_opens_up(): void
    {
        $this->account('2026-06-30');
        $row = $this->beginningBalance('2026-06-30');
        $row->handleEndOfMonthInterestForCurrentAccountStatement('2026-06-30', self::COMPANY);

        $this->assertCount(6, $this->monthEndDates(), 'July–December to begin with');

        // The controller's own steps, in order.
        DB::table('financial_institution_accounts')->where('id', self::ACCOUNT)->update(['balance_date' => '2026-04-01']);
        DB::table('current_account_bank_statements')->where('id', $row->id)->update(['date' => '2026-04-01']);
        $added = $row->fresh()->resyncEndOfMonthInterestForAllYears(self::COMPANY);

        $this->assertSame(3, $added, 'April, May and June');
        $this->assertSame([
            '2026-04-30', '2026-05-31', '2026-06-30', '2026-07-31',
            '2026-08-31', '2026-09-30', '2026-10-31', '2026-11-30', '2026-12-31',
        ], $this->monthEndDates());
    }

    /**
     * Before the period column existed, `synced_end_of_month_years`
     * had to block every re-run, which is precisely what made the gap
     * permanent. It is now only a fast path, and force gets past it.
     */
    public function test_a_year_already_marked_synced_no_longer_blocks_the_repair(): void
    {
        $this->account('2026-04-01', ['2026']);   // already "done" for 2026
        $row = $this->beginningBalance('2026-04-01');

        // The fast path still short-circuits...
        $row->handleEndOfMonthInterestForCurrentAccountStatement('2026-04-01', self::COMPANY);
        $this->assertSame([], $this->monthEndDates());

        // ...and force gets through it.
        $added = $row->resyncEndOfMonthInterestForAllYears(self::COMPANY);

        // 30 April is still ahead of a 1 April balance date, so April counts.
        $this->assertSame(9, $added, 'April through December');
        $this->assertSame('2026-04-30', $this->monthEndDates()[0]);
        $this->assertCount(9, $this->monthEndDates());
    }

    public function test_the_repair_spans_every_year_the_account_has_been_synced_for(): void
    {
        $this->account('2025-11-30', ['2025', '2026']);
        $row = $this->beginningBalance('2025-11-30');

        $added = $row->resyncEndOfMonthInterestForAllYears(self::COMPANY);

        $this->assertSame(13, $added, 'Dec 2025 plus all twelve months of 2026');
        $periods = $this->monthEndPeriods();
        $this->assertSame('2025-12', $periods[0]);
        $this->assertSame('2026-12', end($periods));
    }

    /**
     * Moving FORWARD is already handled by the controller deleting the
     * months that fall before the new date; the repair must then add
     * nothing back, or the two would fight each other.
     */
    public function test_moving_the_balance_date_forwards_adds_nothing_back(): void
    {
        $this->account('2026-04-01', ['2026']);
        $row = $this->beginningBalance('2026-04-01');
        $row->resyncEndOfMonthInterestForAllYears(self::COMPANY);
        $this->assertCount(9, $this->monthEndDates(), 'April–December');

        // Forward to 15 July: the controller drops everything on or before it.
        DB::table('financial_institution_accounts')->where('id', self::ACCOUNT)->update(['balance_date' => '2026-07-15']);
        DB::table('current_account_bank_statements')->where('id', $row->id)->update(['date' => '2026-07-15']);
        DB::table('current_account_bank_statements')
            ->where('financial_institution_account_id', self::ACCOUNT)
            ->where('is_beginning_balance', 0)
            ->whereIn('interest_type', ['end_of_month', 'end_of_month_final'])
            ->where('date', '<=', '2026-07-15')
            ->delete();

        $added = $row->fresh()->resyncEndOfMonthInterestForAllYears(self::COMPANY);

        $this->assertSame(0, $added, 'Nothing before the new balance date may come back.');
        $this->assertSame([
            '2026-07-31', '2026-08-31', '2026-09-30',
            '2026-10-31', '2026-11-30', '2026-12-31',
        ], $this->monthEndDates());
    }

    /**
     * Backwards then forwards again has to land exactly where it
     * started — otherwise every edit leaves a little more debris.
     */
    public function test_moving_back_and_forth_returns_to_the_same_rows(): void
    {
        $this->account('2026-06-30');
        $row = $this->beginningBalance('2026-06-30');
        $row->handleEndOfMonthInterestForCurrentAccountStatement('2026-06-30', self::COMPANY);
        $original = $this->monthEndDates();

        foreach (['2026-04-01', '2026-06-30'] as $date) {
            DB::table('financial_institution_accounts')->where('id', self::ACCOUNT)->update(['balance_date' => $date]);
            DB::table('current_account_bank_statements')->where('id', $row->id)->update(['date' => $date]);
            DB::table('current_account_bank_statements')
                ->where('financial_institution_account_id', self::ACCOUNT)
                ->where('is_beginning_balance', 0)
                ->whereIn('interest_type', ['end_of_month', 'end_of_month_final'])
                ->where('date', '<=', $date)
                ->delete();
            $row->fresh()->resyncEndOfMonthInterestForAllYears(self::COMPANY);
        }

        $this->assertSame($original, $this->monthEndDates());
    }

    // ---------------------------------------------------------------
    // safety
    // ---------------------------------------------------------------

    public function test_nothing_is_generated_without_a_beginning_balance(): void
    {
        $this->account('2026-06-30');

        $row = new CurrentAccountBankStatement;
        $row->financial_institution_account_id = self::ACCOUNT;
        $row->handleEndOfMonthInterestForCurrentAccountStatement('2026-06-30', self::COMPANY, true);

        $this->assertSame([], $this->monthEndDates());
    }

    /**
     * A month-end row the trigger has filled in, or one posted to Odoo,
     * is real money — the repair must never treat its month as empty
     * and add a second one beside it.
     *
     * @dataProvider touchedRowProvider
     */
    public function test_a_month_that_already_has_a_real_row_is_left_alone(array $overrides): void
    {
        $this->account('2026-06-30', ['2026']);
        $row = $this->beginningBalance('2026-06-30');
        $this->monthEndRow('2026-08-31', '2026-08', $overrides);

        $row->resyncEndOfMonthInterestForAllYears(self::COMPANY);

        $august = array_filter($this->monthEndPeriods(), fn ($period) => $period === '2026-08');

        $this->assertCount(1, $august, 'August must still have exactly one row.');
    }

    public static function touchedRowProvider(): array
    {
        return [
            'carries interest' => [['debit' => 164781.28]],
            'posted to odoo' => [['interest_journal_entry_id' => 15432]],
        ];
    }
}
