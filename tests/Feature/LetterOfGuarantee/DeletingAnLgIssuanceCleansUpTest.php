<?php

namespace Tests\Feature\LetterOfGuarantee;

use App\Models\CurrentAccountBankStatement;
use App\Models\LetterOfGuaranteeCashCoverStatement;
use App\Models\LetterOfGuaranteeIssuance;
use App\Models\LetterOfGuaranteeStatement;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Deleting an LG issuance must take its statement rows with it, from
 * ANY delete path — not only the two controller actions that used to
 * remember to call deleteAllRelations() by hand.
 *
 * The leak these cover produced the reported symptom: an empty LG
 * Issuance list page next to an LG/LC dashboard still reporting a
 * 20,000 Outstanding Balance, plus LG commission fees left sitting in
 * a bank account's statement.
 *
 * @see \App\Models\LetterOfGuaranteeIssuance::booted()
 * @see \App\Console\Commands\FindOrphanLgRowsCommand
 */
class DeletingAnLgIssuanceCleansUpTest extends TestCase
{
    use LgSchemaFixture;

    private const COMPANY = 146;
    private const ACCOUNT = 334;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertOnTestDatabase();
        $this->createLgSchema();

        DB::table('companies')->insert(['id' => self::COMPANY]);
        DB::table('financial_institution_accounts')->insert([
            'id' => self::ACCOUNT,
            'company_id' => self::COMPANY,
            'balance_date' => '2026-06-30',
            'synced_end_of_month_years' => json_encode(['2026']),
        ]);
    }

    protected function tearDown(): void
    {
        $this->dropLgSchema();

        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // fixtures
    // ---------------------------------------------------------------

    private function makeIssuance(int $id = 672): LetterOfGuaranteeIssuance
    {
        DB::table('letter_of_guarantee_issuances')->insert([
            'id' => $id,
            'company_id' => self::COMPANY,
            'source' => LetterOfGuaranteeIssuance::LG_FACILITY,
            'status' => LetterOfGuaranteeIssuance::RUNNING,
            'lg_type' => 'final-lgs',
        ]);

        return LetterOfGuaranteeIssuance::findOrFail($id);
    }

    private function makeLgStatement(int $issuanceId, array $overrides = []): int
    {
        return $this->insertRow('letter_of_guarantee_statements', $overrides + [
            'type' => 'credit-lg-amount',
            'source' => LetterOfGuaranteeIssuance::LG_FACILITY,
            'financial_institution_id' => 125,
            'letter_of_guarantee_issuance_id' => $issuanceId,
            'lg_facility_id' => 97,
            'lg_type' => 'final-lgs',
            'currency' => 'EGP',
            'company_id' => self::COMPANY,
            'is_credit' => 1,
            'date' => '2026-01-01',
            'full_date' => '2026-01-01 16:36:55',
            'credit' => 20000,
            'end_balance' => -20000,
        ]);
    }

    private function makeCashCoverStatement(int $issuanceId, array $overrides = []): int
    {
        return $this->insertRow('letter_of_guarantee_cash_cover_statements', $overrides + [
            'type' => 'debit-lg-amount',
            'source' => LetterOfGuaranteeIssuance::LG_FACILITY,
            'financial_institution_id' => 125,
            'letter_of_guarantee_issuance_id' => $issuanceId,
            'lg_facility_id' => 97,
            'lg_type' => 'final-lgs',
            'currency' => 'EGP',
            'company_id' => self::COMPANY,
            'is_debit' => 1,
            'date' => '2026-01-01',
            'full_date' => '2026-01-01 16:36:55',
            'debit' => 5000,
            'end_balance' => 5000,
        ]);
    }

    private function makeCommissionFeeRow(?int $issuanceId, array $overrides = []): int
    {
        return $this->insertRow('current_account_bank_statements', $overrides + [
            'company_id' => self::COMPANY,
            'financial_institution_account_id' => self::ACCOUNT,
            'letter_of_guarantee_issuance_id' => $issuanceId,
            'is_credit' => 1,
            'is_commission_fees' => 1,
            'date' => '2026-07-01',
            'full_date' => '2026-07-01 10:00:00',
            'credit' => 80,
            'end_balance' => -80,
            'comment_en' => 'Commission Fees [ sce zone ] [ final-lgs ]',
        ]);
    }

    private function insertRow(string $table, array $attributes): int
    {
        DB::table($table)->insert($attributes);

        return (int) DB::getPdo()->lastInsertId();
    }

    // ---------------------------------------------------------------
    // the hook
    // ---------------------------------------------------------------

    /**
     * The core guarantee: a plain ->delete(), with nobody calling
     * deleteAllRelations() first, still cleans up.
     */
    public function test_a_plain_delete_takes_every_statement_row_with_it(): void
    {
        $issuance = $this->makeIssuance();
        $lgStatement = $this->makeLgStatement($issuance->id);
        $cashCover = $this->makeCashCoverStatement($issuance->id);
        $commission = $this->makeCommissionFeeRow($issuance->id);

        $issuance->delete();

        $this->assertDatabaseMissing('letter_of_guarantee_issuances', ['id' => $issuance->id]);
        $this->assertDatabaseMissing('letter_of_guarantee_statements', ['id' => $lgStatement]);
        $this->assertDatabaseMissing('letter_of_guarantee_cash_cover_statements', ['id' => $cashCover]);
        $this->assertDatabaseMissing('current_account_bank_statements', ['id' => $commission]);
    }

    /**
     * An LG commission that has not fallen due yet is is_active = 0,
     * which CurrentAccountBankStatement's `only_active` global scope
     * hides. It must still be cleaned up — the reported case had one.
     */
    public function test_it_also_removes_the_inactive_future_commission_rows(): void
    {
        $issuance = $this->makeIssuance();
        $future = $this->makeCommissionFeeRow($issuance->id, [
            'is_active' => 0,
            'date' => '2026-10-01',
            'full_date' => '2026-10-01 10:00:00',
        ]);

        $issuance->delete();

        $this->assertDatabaseMissing('current_account_bank_statements', ['id' => $future]);
    }

    public function test_it_leaves_another_issuances_rows_alone(): void
    {
        $doomed = $this->makeIssuance(672);
        $survivor = $this->makeIssuance(673);

        $doomedStatement = $this->makeLgStatement($doomed->id);
        $survivorStatement = $this->makeLgStatement($survivor->id);
        $survivorCommission = $this->makeCommissionFeeRow($survivor->id);

        $doomed->delete();

        $this->assertDatabaseMissing('letter_of_guarantee_statements', ['id' => $doomedStatement]);
        $this->assertDatabaseHas('letter_of_guarantee_statements', ['id' => $survivorStatement]);
        $this->assertDatabaseHas('current_account_bank_statements', ['id' => $survivorCommission]);
        $this->assertDatabaseHas('letter_of_guarantee_issuances', ['id' => $survivor->id]);
    }

    /**
     * Bank statement rows that belong to no LG at all (the ordinary
     * case — letter_of_guarantee_issuance_id is NULL) must never be
     * touched by an LG delete.
     */
    public function test_it_never_touches_rows_that_belong_to_no_lg(): void
    {
        $issuance = $this->makeIssuance();
        $unrelated = $this->makeCommissionFeeRow(null, [
            'is_commission_fees' => 0,
            'credit' => 4300.22,
            'comment_en' => 'An ordinary bank movement',
        ]);

        $issuance->delete();

        $this->assertDatabaseHas('current_account_bank_statements', ['id' => $unrelated]);
    }

    /**
     * Deleting an issuance with nothing hanging off it must not blow up
     * on an empty relation.
     */
    public function test_an_issuance_with_no_statements_deletes_cleanly(): void
    {
        $issuance = $this->makeIssuance();

        $issuance->delete();

        $this->assertDatabaseMissing('letter_of_guarantee_issuances', ['id' => $issuance->id]);
    }

    // ---------------------------------------------------------------
    // exactly once
    // ---------------------------------------------------------------

    /**
     * deleteAllRelations() is NOT idempotent — it calls OdooSync::defer()
     * once per journal entry column, so running it twice registers a
     * duplicate unlink for the same Odoo journal entry. That is why the
     * two controller call sites had to drop their explicit call when the
     * hook was introduced, rather than keeping both.
     *
     * This pins the count: one delete, one run.
     */
    public function test_the_cleanup_runs_exactly_once_per_delete(): void
    {
        $issuance = $this->makeIssuance();
        $this->makeLgStatement($issuance->id);

        $runs = 0;
        LetterOfGuaranteeIssuance::deleting(function () use (&$runs) {
            $runs++;
        });

        $issuance->delete();

        $this->assertSame(1, $runs);
    }

    /**
     * A guard against the double call coming back by hand. If someone
     * re-adds deleteAllRelations() next to ->delete(), every Odoo
     * unlink in that request is deferred twice.
     */
    public function test_no_controller_calls_delete_all_relations_on_an_issuance_itself(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/LetterOfGuaranteeIssuanceController.php'));

        $this->assertStringNotContainsString(
            '$letterOfGuaranteeIssuance->deleteAllRelations()',
            $source,
            'LetterOfGuaranteeIssuance::deleting() already calls deleteAllRelations(); calling it here too defers every Odoo unlink twice.'
        );
    }

    // ---------------------------------------------------------------
    // balances are recalculated, not just rows removed
    // ---------------------------------------------------------------

    /**
     * Removing a row must go through the model so the following rows in
     * the same series get re-touched (updateNextRows → StatementCascade),
     * which is what makes the database triggers recompute balances. The
     * observable proof without the production triggers installed is that
     * the surviving later row was touched.
     */
    public function test_deleting_re_touches_the_later_rows_in_the_same_series(): void
    {
        $issuance = $this->makeIssuance(672);
        $survivor = $this->makeIssuance(673);

        $this->makeLgStatement($issuance->id, [
            'date' => '2026-01-01',
            'full_date' => '2026-01-01 10:00:00',
        ]);
        $laterRow = $this->makeLgStatement($survivor->id, [
            'date' => '2026-05-01',
            'full_date' => '2026-05-01 10:00:00',
        ]);

        DB::table('letter_of_guarantee_statements')->where('id', $laterRow)
            ->update(['updated_at' => '2020-01-01 00:00:00']);

        $issuance->delete();

        $touchedAt = DB::table('letter_of_guarantee_statements')->where('id', $laterRow)->value('updated_at');

        $this->assertNotSame('2020-01-01 00:00:00', $touchedAt,
            'The later row was never re-touched, so its running balance would keep the deleted row in it.');
    }

    // ---------------------------------------------------------------
    // model classes still line up with the command's map
    // ---------------------------------------------------------------

    public function test_the_cleanup_covers_the_same_tables_the_command_repairs(): void
    {
        $covered = [
            (new LetterOfGuaranteeStatement)->getTable(),
            (new LetterOfGuaranteeCashCoverStatement)->getTable(),
            (new CurrentAccountBankStatement)->getTable(),
        ];

        $reflection = new \ReflectionClass(\App\Console\Commands\FindOrphanLgRowsCommand::class);
        $repaired = array_keys($reflection->getConstant('MODELS'));

        sort($covered);
        sort($repaired);

        $this->assertSame($covered, $repaired,
            'A table the command repairs but the delete hook does not clean (or vice versa) means the leak is only half closed.');
    }
}
