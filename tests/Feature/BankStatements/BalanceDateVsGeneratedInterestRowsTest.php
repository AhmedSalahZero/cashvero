<?php

namespace Tests\Feature\BankStatements;

use App\Rules\DateCanNotBeAfterAnyStatementRule;
use App\Support\BankStatements\GeneratedMonthEndInterestRows;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Editing an account's Balance Date must not be blocked by the empty
 * month-end interest rows the system generates for itself, but must
 * still be blocked by anything that represents real movement.
 *
 * Scope: these exercise the two queries that changed —
 * DateCanNotBeAfterAnyStatementRule and the orphan cleanup in
 * FinancialInstitutionAccountController::update() — against real MySQL,
 * on a purpose-built table holding only the columns they read. Running
 * the controller action end to end would need the full 224-migration
 * schema plus the statement triggers, which is a different test.
 *
 * @see \App\Support\BankStatements\GeneratedMonthEndInterestRows
 */
class BalanceDateVsGeneratedInterestRowsTest extends TestCase
{
    private const TABLE = 'current_account_bank_statements';
    private const ACCOUNT = 356;
    private const OTHER_ACCOUNT = 315;
    private const COMPANY = 146;
    private const NEW_BALANCE_DATE = '2026-08-01';

    private int $autoId = 1;

    protected function setUp(): void
    {
        parent::setUp();

        // Hard stop: this test creates and drops the statements table.
        $database = DB::connection()->getDatabaseName();
        $this->assertStringContainsString(
            'test',
            $database,
            "Refusing to run: connected to '{$database}', which is not a test database."
        );

        Schema::dropIfExists(self::TABLE);
        Schema::create(self::TABLE, function ($table) {
            $table->bigIncrements('id');
            $table->integer('financial_institution_account_id');
            $table->unsignedBigInteger('company_id');
            $table->boolean('is_beginning_balance')->default(false);
            $table->string('type')->nullable();
            $table->string('interest_type')->nullable();
            $table->date('date')->nullable();
            $table->decimal('debit', 14)->nullable()->default(0);
            $table->decimal('credit', 14)->nullable()->default(0);
            $table->unsignedBigInteger('interest_journal_entry_id')->nullable();
            $table->string('interest_odoo_reference')->nullable();
            $table->unsignedBigInteger('interest_account_bank_statement_odoo_id')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists(self::TABLE);

        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // helpers
    // ---------------------------------------------------------------

    /** An empty month-end row exactly as the generator writes it. */
    private function placeholder(string $date, array $overrides = []): int
    {
        // $overrides first: with PHP's array union the LEFT operand wins.
        return $this->row($overrides + [
            'type' => 'interest',
            'interest_type' => 'end_of_month',
            'date' => $date,
            'debit' => 0,
            'credit' => 0,
        ]);
    }

    /** A row the user actually entered — interest_type is NULL. */
    private function transaction(string $date, array $overrides = []): int
    {
        return $this->row($overrides + [
            'type' => null,
            'interest_type' => null,
            'date' => $date,
            'debit' => 0,
            'credit' => 5000,
        ]);
    }

    private function row(array $attributes): int
    {
        $id = $this->autoId++;

        DB::table(self::TABLE)->insert($attributes + [
            'id' => $id,
            'financial_institution_account_id' => self::ACCOUNT,
            'company_id' => self::COMPANY,
            'is_beginning_balance' => 0,
            'type' => null,
            'interest_type' => null,
            'debit' => 0,
            'credit' => 0,
            'interest_journal_entry_id' => null,
            'interest_odoo_reference' => null,
            'interest_account_bank_statement_odoo_id' => null,
        ]);

        return $id;
    }

    /** True when the Balance Date edit is allowed through. */
    private function ruleAllows(string $newBalanceDate = self::NEW_BALANCE_DATE, int $account = self::ACCOUNT): bool
    {
        return (new DateCanNotBeAfterAnyStatementRule($account, $newBalanceDate))
            ->passes('beginning_balance_rule', null);
    }

    /** The controller's cleanup, built exactly as update() builds it. */
    private function runOrphanCleanup(string $balanceDate = self::NEW_BALANCE_DATE, int $account = self::ACCOUNT): int
    {
        $query = DB::table(self::TABLE)
            ->where('financial_institution_account_id', $account)
            ->where('is_beginning_balance', 0)
            ->where('date', '<=', $balanceDate);

        return GeneratedMonthEndInterestRows::onlyUntouchedIn($query)->delete();
    }

    /** @return int[] */
    private function survivingIds(): array
    {
        return DB::table(self::TABLE)->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    // ---------------------------------------------------------------
    // the rule — what must NOT block
    // ---------------------------------------------------------------

    /**
     * The reported bug: account 356 held nothing but its beginning
     * balance and six empty month-end rows, yet the edit was refused.
     */
    public function test_an_empty_generated_month_end_row_does_not_block_the_edit(): void
    {
        $this->row(['is_beginning_balance' => 1, 'date' => '2026-06-30', 'debit' => 64450.61]);
        $this->placeholder('2026-07-31');
        $this->placeholder('2026-08-31');
        $this->placeholder('2026-09-30');

        $this->assertTrue($this->ruleAllows());
    }

    public function test_an_empty_end_of_month_final_row_does_not_block_the_edit(): void
    {
        $this->placeholder('2026-07-31', ['interest_type' => 'end_of_month_final']);

        $this->assertTrue($this->ruleAllows());
    }

    public function test_the_beginning_balance_row_never_blocks_the_edit(): void
    {
        $this->row(['is_beginning_balance' => 1, 'date' => '2026-06-30', 'debit' => 64450.61]);

        $this->assertTrue($this->ruleAllows());
    }

    public function test_a_transaction_on_or_after_the_new_date_does_not_block_the_edit(): void
    {
        $this->transaction(self::NEW_BALANCE_DATE); // the comparison is strictly "<"
        $this->transaction('2026-09-15');

        $this->assertTrue($this->ruleAllows());
    }

    public function test_another_accounts_transactions_do_not_block_the_edit(): void
    {
        $this->transaction('2026-01-01', ['financial_institution_account_id' => self::OTHER_ACCOUNT]);

        $this->assertTrue($this->ruleAllows());
    }

    // ---------------------------------------------------------------
    // the rule — what must STILL block
    // ---------------------------------------------------------------

    public function test_a_real_transaction_before_the_new_date_blocks_the_edit(): void
    {
        $this->transaction('2026-07-30');

        $this->assertFalse($this->ruleAllows());
    }

    /**
     * Regression guard for the NULL trap. A real transaction has
     * interest_type = NULL, and `NULL IN (...)` is NULL, not FALSE —
     * without the whereNotNull() guard MySQL's `NOT (...)` swallows every
     * zero-amount transaction. 82 rows in production look like this.
     */
    public function test_a_zero_amount_real_transaction_still_blocks_the_edit(): void
    {
        $this->transaction('2026-07-30', ['debit' => 0, 'credit' => 0]);

        $this->assertFalse($this->ruleAllows());
    }

    /**
     * The trigger fills debit on a month-end row once the account earns
     * interest; account 315 carries 164,781.28 on such a row.
     */
    public function test_a_month_end_row_carrying_real_interest_blocks_the_edit(): void
    {
        $this->placeholder('2026-07-31', ['debit' => 164781.28]);

        $this->assertFalse($this->ruleAllows());
    }

    public function test_a_month_end_row_with_a_credit_blocks_the_edit(): void
    {
        $this->placeholder('2026-07-31', ['credit' => 12.5]);

        $this->assertFalse($this->ruleAllows());
    }

    /**
     * Each Odoo link is checked on its own so that dropping any one of
     * the three whereNull() clauses fails a test.
     *
     * @dataProvider odooLinkProvider
     */
    public function test_a_month_end_row_posted_to_odoo_blocks_the_edit(string $column, $value): void
    {
        $this->placeholder('2026-07-31', [$column => $value]);

        $this->assertFalse($this->ruleAllows(), "A row linked via {$column} must still block the edit.");
    }

    public static function odooLinkProvider(): array
    {
        return [
            'journal entry' => ['interest_journal_entry_id', 15432],
            'odoo reference' => ['interest_odoo_reference', 'QNB1/2025/00742 (Interest Revenue)'],
            'statement odoo id' => ['interest_account_bank_statement_odoo_id', 4711],
        ];
    }

    public function test_a_non_interest_typed_row_blocks_the_edit(): void
    {
        $this->row(['type' => 'deducted-for-deposit', 'date' => '2026-07-15', 'debit' => 900]);

        $this->assertFalse($this->ruleAllows());
    }

    // ---------------------------------------------------------------
    // the cleanup
    // ---------------------------------------------------------------

    public function test_cleanup_removes_only_the_empty_rows_at_or_before_the_new_balance_date(): void
    {
        $beginningBalance = $this->row(['is_beginning_balance' => 1, 'date' => self::NEW_BALANCE_DATE, 'debit' => 64450.61]);
        $orphanBefore = $this->placeholder('2026-07-31');
        $orphanOnDate = $this->placeholder(self::NEW_BALANCE_DATE);
        $futureRow = $this->placeholder('2026-08-31');
        $postedInterest = $this->placeholder('2026-06-30', ['debit' => 164781.28, 'interest_journal_entry_id' => 15432]);
        $emptyButPosted = $this->placeholder('2026-06-30', ['interest_odoo_reference' => 'QNB1/2026/00070']);
        $realTransaction = $this->transaction('2026-07-20');
        $zeroTransaction = $this->transaction('2026-07-21', ['debit' => 0, 'credit' => 0]);
        $otherAccount = $this->placeholder('2026-07-31', ['financial_institution_account_id' => self::OTHER_ACCOUNT]);

        $this->assertSame(2, $this->runOrphanCleanup());

        $this->assertSame([
            $beginningBalance,
            $futureRow,
            $postedInterest,
            $emptyButPosted,
            $realTransaction,
            $zeroTransaction,
            $otherAccount,
        ], $this->survivingIds());

        $this->assertNotContains($orphanBefore, $this->survivingIds());
        $this->assertNotContains($orphanOnDate, $this->survivingIds());
    }

    public function test_cleanup_is_idempotent(): void
    {
        $this->placeholder('2026-07-31');

        $this->assertSame(1, $this->runOrphanCleanup());
        $this->assertSame(0, $this->runOrphanCleanup());
    }

    public function test_cleanup_leaves_an_account_with_nothing_to_clean_untouched(): void
    {
        $this->row(['is_beginning_balance' => 1, 'date' => '2026-06-30', 'debit' => 64450.61]);
        $this->transaction('2026-07-20');
        $this->placeholder('2026-08-31');

        $this->assertSame(0, $this->runOrphanCleanup());
        $this->assertSame([1, 2, 3], $this->survivingIds());
    }

    // ---------------------------------------------------------------
    // the two must stay in step
    // ---------------------------------------------------------------

    /**
     * The rule ignores exactly the rows the cleanup deletes. If the two
     * definitions ever drift, a row could block the edit and yet be left
     * behind as an orphan — or, worse, be ignored by the rule and then
     * survive the cleanup as a row sitting before the beginning balance.
     */
    public function test_ignored_by_the_rule_and_deleted_by_the_cleanup_are_the_same_set(): void
    {
        $this->placeholder('2026-07-31');
        $this->placeholder('2026-07-31', ['interest_type' => 'end_of_month_final']);
        $this->placeholder('2026-07-31', ['debit' => 164781.28]);
        $this->placeholder('2026-07-31', ['credit' => 12.5]);
        $this->placeholder('2026-07-31', ['interest_journal_entry_id' => 15432]);
        $this->placeholder('2026-07-31', ['interest_odoo_reference' => 'QNB1/2025/00742']);
        $this->placeholder('2026-07-31', ['interest_account_bank_statement_odoo_id' => 4711]);
        $this->transaction('2026-07-31');
        $this->transaction('2026-07-31', ['debit' => 0, 'credit' => 0]);
        $this->row(['type' => 'deducted-for-deposit', 'date' => '2026-07-31', 'debit' => 900]);

        $all = $this->survivingIds();

        $blocking = GeneratedMonthEndInterestRows::excludeUntouchedFrom(DB::table(self::TABLE))
            ->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();

        $deletable = GeneratedMonthEndInterestRows::onlyUntouchedIn(DB::table(self::TABLE))
            ->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();

        $partitioned = array_merge($blocking, $deletable);
        sort($partitioned);

        $this->assertSame([], array_values(array_intersect($blocking, $deletable)), 'No row may be both.');
        $this->assertSame($all, $partitioned, 'Every row must be one or the other, exactly once.');
        $this->assertNotEmpty($blocking);
        $this->assertNotEmpty($deletable);
    }
}
