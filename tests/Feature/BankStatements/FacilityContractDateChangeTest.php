<?php

namespace Tests\Feature\BankStatements;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Changing a facility's contract dates must leave month-end interest
 * rows only for the months the contract actually covers.
 *
 * The generator already deleted rows past the contract END date. It had
 * no counterpart for the START: moving a contract from January to
 * August left February through July sitting in the statement forever,
 * before the contract had begun.
 *
 * Four facility types run the same generator, each on its own
 * statements table — so the fix has to hold for all four, and the
 * provider below is what keeps them honest.
 *
 * @see \App\Models\FullySecuredOverdraft::handleEndOfMonthInterestForContractStatements()
 */
class FacilityContractDateChangeTest extends TestCase
{
    private const COMPANY = 92;

    private const FACILITY = 58;

    /**
     * facility model => [facility table, statements table, foreign key]
     */
    private const FACILITIES = [
        \App\Models\CleanOverdraft::class => [
            'clean_overdrafts', 'clean_overdraft_bank_statements', 'clean_overdraft_id',
        ],
        \App\Models\FullySecuredOverdraft::class => [
            'fully_secured_overdrafts', 'fully_secured_overdraft_bank_statements', 'fully_secured_overdraft_id',
        ],
        \App\Models\OverdraftAgainstCommercialPaper::class => [
            'overdraft_against_commercial_papers', 'overdraft_against_commercial_paper_bank_statements', 'overdraft_against_commercial_paper_id',
        ],
        \App\Models\OverdraftAgainstAssignmentOfContract::class => [
            'overdraft_against_assignment_of_contracts', 'overdraft_against_assignment_of_contract_bank_statements', 'overdraft_against_assignment_of_contract_id',
        ],
    ];

    /**
     * ⚠️ SEPARATE PRE-EXISTING BUG, deliberately NOT fixed here.
     *
     * The generator computes
     *   $isLastDayOfMonth = $start->isSameDay($start->endOfMonth());
     * and Carbon's endOfMonth() MUTATES $start, so the comparison is
     * always a date against itself — always true. Index 0 of the loop
     * is therefore always skipped, and the contract's FIRST month never
     * gets a row: a contract starting 1 January produces February
     * onwards, and one starting 1 August produces September onwards.
     *
     * These expectations describe what the code does today, not what it
     * should do. Fixing it changes generated interest rows on every
     * facility on record, so it is the project owner's call — raised
     * separately.
     */
    private const FIRST_MONTH_NOTE = 'The contract\'s first month is skipped — pre-existing Carbon mutation bug, see FIRST_MONTH_NOTE.';

    /** @var list<string> */
    private array $created = [];

    protected function setUp(): void
    {
        parent::setUp();

        $database = DB::connection()->getDatabaseName();
        $this->assertStringContainsString('test', $database, "Refusing to run against '{$database}'.");
    }

    protected function tearDown(): void
    {
        foreach ($this->created as $table) {
            Schema::dropIfExists($table);
        }

        parent::tearDown();
    }

    private function buildSchemaFor(string $facilityTable, string $statementsTable, string $foreignKey): void
    {
        foreach ([$facilityTable, $statementsTable, 'record_activities', 'temp_deleted_statements'] as $table) {
            Schema::dropIfExists($table);
            $this->created[] = $table;
        }

        Schema::create($facilityTable, function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->date('contract_start_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->decimal('limit', 18, 2)->default(0);
            // The statement models' hooks stamp this back onto the facility.
            $table->date('oldest_date')->nullable();
            $table->timestamps();
        });

        Schema::create($statementsTable, function ($table) use ($foreignKey) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger($foreignKey)->default(0);
            $table->string('type')->nullable();
            $table->string('interest_type')->nullable();
            // The cascade orders by date, priority, id.
            $table->integer('priority')->default(0);
            $table->date('date')->nullable();
            $table->dateTime('full_date')->nullable();
            $table->decimal('limit', 18, 2)->default(0);
            $table->decimal('beginning_balance', 14)->default(0);
            $table->decimal('debit', 14)->default(0);
            $table->decimal('credit', 14)->default(0);
            $table->decimal('end_balance', 14)->default(0);
            $table->decimal('interest_amount', 14)->default(0);
            $table->boolean('is_debit')->default(0);
            $table->boolean('is_credit')->default(0);
            $table->string('comment_en')->nullable();
            $table->string('comment_ar')->nullable();
            $table->timestamps();
        });

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

        DB::table($facilityTable)->insert([
            'id' => self::FACILITY,
            'company_id' => self::COMPANY,
            'contract_start_date' => '2026-01-01',
            'contract_end_date' => '2026-12-31',
            'limit' => 1000000,
        ]);
    }

    /** @return list<string> */
    private function monthEndDates(string $statementsTable, string $foreignKey): array
    {
        return DB::table($statementsTable)
            ->where($foreignKey, self::FACILITY)
            ->where('interest_type', 'end_of_month')
            ->orderBy('date')
            ->pluck('date')
            ->all();
    }

    private function generate(string $model, string $facilityTable, string $start, string $end): void
    {
        DB::table($facilityTable)->where('id', self::FACILITY)->update([
            'contract_start_date' => $start,
            'contract_end_date' => $end,
        ]);

        $model::findOrFail(self::FACILITY)
            ->handleEndOfMonthInterestForContractStatements($start, $end, self::COMPANY);
    }

    public static function facilityProvider(): array
    {
        $cases = [];

        foreach (self::FACILITIES as $model => [$facilityTable, $statementsTable, $foreignKey]) {
            $cases[class_basename($model)] = [$model, $facilityTable, $statementsTable, $foreignKey];
        }

        return $cases;
    }

    // ---------------------------------------------------------------
    // the bug
    // ---------------------------------------------------------------

    /**
     * The reported case: a contract starting in January, moved to
     * August. February through July had been generated and nothing
     * removed them.
     *
     * @dataProvider facilityProvider
     */
    public function test_moving_the_start_date_forwards_drops_the_months_before_it(
        string $model, string $facilityTable, string $statementsTable, string $foreignKey
    ): void {
        $this->buildSchemaFor($facilityTable, $statementsTable, $foreignKey);

        $this->generate($model, $facilityTable, '2026-01-01', '2026-12-31');
        $this->assertSame([
            '2026-02-28', '2026-03-31', '2026-04-30', '2026-05-31', '2026-06-30',
            '2026-07-31', '2026-08-31', '2026-09-30', '2026-10-31', '2026-11-30', '2026-12-31',
        ], $this->monthEndDates($statementsTable, $foreignKey), self::FIRST_MONTH_NOTE);

        $this->generate($model, $facilityTable, '2026-08-01', '2026-12-31');

        $this->assertSame([
            '2026-08-31', '2026-09-30', '2026-10-31', '2026-11-30', '2026-12-31',
        ], $this->monthEndDates($statementsTable, $foreignKey), 'January–July must be gone');
    }

    /**
     * A month-end row the trigger has already filled with real interest
     * is money, not a placeholder — it must survive even when it falls
     * before the new start date, so the amount is not silently erased.
     *
     * @dataProvider facilityProvider
     */
    public function test_a_month_carrying_real_interest_is_never_dropped(
        string $model, string $facilityTable, string $statementsTable, string $foreignKey
    ): void {
        $this->buildSchemaFor($facilityTable, $statementsTable, $foreignKey);

        $this->generate($model, $facilityTable, '2026-01-01', '2026-12-31');
        DB::table($statementsTable)
            ->where($foreignKey, self::FACILITY)
            ->where('date', '2026-03-31')
            ->update(['credit' => 100921, 'interest_amount' => 100921]);

        $this->generate($model, $facilityTable, '2026-08-01', '2026-12-31');

        $this->assertSame([
            '2026-03-31', '2026-08-31', '2026-09-30', '2026-10-31', '2026-11-30', '2026-12-31',
        ], $this->monthEndDates($statementsTable, $foreignKey), 'March carries 100,921 and stays');
    }

    // ---------------------------------------------------------------
    // the half that already worked must keep working
    // ---------------------------------------------------------------

    /**
     * @dataProvider facilityProvider
     */
    public function test_moving_the_end_date_backwards_still_drops_the_months_after_it(
        string $model, string $facilityTable, string $statementsTable, string $foreignKey
    ): void {
        $this->buildSchemaFor($facilityTable, $statementsTable, $foreignKey);

        $this->generate($model, $facilityTable, '2026-01-01', '2026-12-31');
        $this->generate($model, $facilityTable, '2026-01-01', '2026-06-30');

        $this->assertSame([
            '2026-02-28', '2026-03-31', '2026-04-30', '2026-05-31', '2026-06-30',
        ], $this->monthEndDates($statementsTable, $foreignKey), self::FIRST_MONTH_NOTE);
    }

    /**
     * Narrowing from both ends at once — the two deletes must not
     * interfere with each other.
     *
     * @dataProvider facilityProvider
     */
    public function test_narrowing_the_contract_from_both_ends(
        string $model, string $facilityTable, string $statementsTable, string $foreignKey
    ): void {
        $this->buildSchemaFor($facilityTable, $statementsTable, $foreignKey);

        $this->generate($model, $facilityTable, '2026-01-01', '2026-12-31');
        $this->generate($model, $facilityTable, '2026-05-01', '2026-08-31');

        $this->assertSame([
            '2026-05-31', '2026-06-30', '2026-07-31', '2026-08-31',
        ], $this->monthEndDates($statementsTable, $foreignKey));
    }

    /**
     * Widening it again must bring the months back rather than leaving
     * a hole — the existence check inside the generator is what does
     * that, and it has always been active here.
     *
     * @dataProvider facilityProvider
     */
    public function test_widening_the_contract_again_restores_the_months(
        string $model, string $facilityTable, string $statementsTable, string $foreignKey
    ): void {
        $this->buildSchemaFor($facilityTable, $statementsTable, $foreignKey);

        $this->generate($model, $facilityTable, '2026-01-01', '2026-12-31');
        $this->generate($model, $facilityTable, '2026-08-01', '2026-12-31');
        $this->assertCount(5, $this->monthEndDates($statementsTable, $foreignKey));

        $this->generate($model, $facilityTable, '2026-01-01', '2026-12-31');

        $this->assertCount(11, $this->monthEndDates($statementsTable, $foreignKey), self::FIRST_MONTH_NOTE);
    }

    /**
     * Re-running with the same dates must change nothing.
     *
     * @dataProvider facilityProvider
     */
    public function test_regenerating_with_unchanged_dates_is_a_no_op(
        string $model, string $facilityTable, string $statementsTable, string $foreignKey
    ): void {
        $this->buildSchemaFor($facilityTable, $statementsTable, $foreignKey);

        $this->generate($model, $facilityTable, '2026-03-01', '2026-09-30');
        $first = $this->monthEndDates($statementsTable, $foreignKey);

        $this->generate($model, $facilityTable, '2026-03-01', '2026-09-30');
        $this->generate($model, $facilityTable, '2026-03-01', '2026-09-30');

        $this->assertSame($first, $this->monthEndDates($statementsTable, $foreignKey));
    }

    /**
     * Rows belonging to another facility on the same table must not be
     * touched — the deletes are scoped by the foreign key.
     *
     * @dataProvider facilityProvider
     */
    public function test_another_facilitys_rows_are_left_alone(
        string $model, string $facilityTable, string $statementsTable, string $foreignKey
    ): void {
        $this->buildSchemaFor($facilityTable, $statementsTable, $foreignKey);

        DB::table($statementsTable)->insert([
            'company_id' => self::COMPANY,
            $foreignKey => 999,
            'type' => 'interest',
            'interest_type' => 'end_of_month',
            'date' => '2026-02-28',
            'full_date' => '2026-02-28 00:00:01',
        ]);

        $this->generate($model, $facilityTable, '2026-01-01', '2026-12-31');
        $this->generate($model, $facilityTable, '2026-08-01', '2026-12-31');

        $this->assertSame(1, DB::table($statementsTable)->where($foreignKey, 999)->count(),
            "The other facility's February row was deleted.");
    }
}
