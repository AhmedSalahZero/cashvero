<?php

namespace Tests\Feature\LetterOfGuarantee;

use App\Models\LetterOfGuaranteeIssuance;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * php artisan lg:orphan-rows — the repair pass for statement rows whose
 * LG issuance was deleted before LetterOfGuaranteeIssuance::deleting()
 * existed.
 *
 * It runs against production, so the two properties that matter most
 * are covered explicitly: it changes NOTHING without --fix, and it
 * never mistakes a live row for an orphan.
 *
 * @see \App\Console\Commands\FindOrphanLgRowsCommand
 */
class OrphanLgRowsCommandTest extends TestCase
{
    use LgSchemaFixture;

    private const COMPANY = 146;
    private const OTHER_COMPANY = 92;
    private const ACCOUNT = 334;
    private const DEAD_ISSUANCE = 672;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertOnTestDatabase();
        $this->createLgSchema();

        DB::table('companies')->insert([
            ['id' => self::COMPANY],
            ['id' => self::OTHER_COMPANY],
        ]);
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

    private function makeLiveIssuance(int $id, int $companyId = self::COMPANY): int
    {
        DB::table('letter_of_guarantee_issuances')->insert([
            'id' => $id,
            'company_id' => $companyId,
            'source' => LetterOfGuaranteeIssuance::LG_FACILITY,
            'status' => LetterOfGuaranteeIssuance::RUNNING,
            'lg_type' => 'final-lgs',
        ]);

        return $id;
    }

    private function lgStatement(int $issuanceId, array $overrides = []): int
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

    private function cashCoverStatement(int $issuanceId, array $overrides = []): int
    {
        return $this->insertRow('letter_of_guarantee_cash_cover_statements', $overrides + [
            'type' => 'debit-lg-amount',
            'source' => LetterOfGuaranteeIssuance::LG_FACILITY,
            'financial_institution_id' => 123,
            'letter_of_guarantee_issuance_id' => $issuanceId,
            'lg_facility_id' => 91,
            'lg_type' => 'bid-bond',
            'currency' => 'EGP',
            'company_id' => self::OTHER_COMPANY,
            'is_debit' => 1,
            'date' => '2024-10-08',
            'full_date' => '2024-10-08 17:03:31',
            'debit' => 34005,
            'end_balance' => 34005,
        ]);
    }

    private function commissionFeeRow(?int $issuanceId, array $overrides = []): int
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
    // report mode
    // ---------------------------------------------------------------

    public function test_it_reports_orphans_without_changing_anything(): void
    {
        $orphan = $this->lgStatement(self::DEAD_ISSUANCE);
        $orphanFee = $this->commissionFeeRow(self::DEAD_ISSUANCE);

        $this->artisan('lg:orphan-rows')
            ->expectsOutputToContain('MODE: REPORT ONLY')
            ->expectsOutputToContain('TOTAL orphan rows: 2')
            ->expectsOutputToContain('Re-run with --fix')
            ->assertSuccessful();

        $this->assertDatabaseHas('letter_of_guarantee_statements', ['id' => $orphan]);
        $this->assertDatabaseHas('current_account_bank_statements', ['id' => $orphanFee]);
    }

    public function test_it_reports_nothing_on_a_clean_database(): void
    {
        $live = $this->makeLiveIssuance(700);
        $this->lgStatement($live);
        $this->commissionFeeRow($live);
        $this->commissionFeeRow(null);

        $this->artisan('lg:orphan-rows')
            ->expectsOutputToContain('No orphan rows found.')
            ->assertSuccessful();
    }

    // ---------------------------------------------------------------
    // what is, and is not, an orphan
    // ---------------------------------------------------------------

    public function test_a_row_whose_issuance_still_exists_is_never_an_orphan(): void
    {
        $live = $this->makeLiveIssuance(700);
        $liveStatement = $this->lgStatement($live);

        $this->artisan('lg:orphan-rows --fix')->assertSuccessful();

        $this->assertDatabaseHas('letter_of_guarantee_statements', ['id' => $liveStatement]);
    }

    /**
     * A facility-level beginning-balance row legitimately carries 0 in
     * letter_of_guarantee_issuance_id — it belongs to the facility, not
     * to any issuance. Treating 0 as "issuance #0 is missing" would
     * delete real data.
     */
    public function test_a_facility_row_with_issuance_id_zero_is_left_alone(): void
    {
        $facilityRow = $this->lgStatement(0, [
            'type' => LetterOfGuaranteeIssuance::LG_FACILITY_BEGINNING_BALANCE,
        ]);

        $this->artisan('lg:orphan-rows --fix')
            ->expectsOutputToContain('No orphan rows found.')
            ->assertSuccessful();

        $this->assertDatabaseHas('letter_of_guarantee_statements', ['id' => $facilityRow]);
    }

    public function test_a_bank_row_belonging_to_no_lg_at_all_is_left_alone(): void
    {
        $ordinary = $this->commissionFeeRow(null, ['is_commission_fees' => 0, 'comment_en' => 'Ordinary movement']);

        $this->artisan('lg:orphan-rows --fix')
            ->expectsOutputToContain('No orphan rows found.')
            ->assertSuccessful();

        $this->assertDatabaseHas('current_account_bank_statements', ['id' => $ordinary]);
    }

    // ---------------------------------------------------------------
    // fix mode
    // ---------------------------------------------------------------

    public function test_fix_deletes_every_orphan_across_all_three_tables(): void
    {
        $orphanStatement = $this->lgStatement(self::DEAD_ISSUANCE);
        $orphanCashCover = $this->cashCoverStatement(98);
        $orphanFee = $this->commissionFeeRow(self::DEAD_ISSUANCE);

        $live = $this->makeLiveIssuance(700);
        $liveStatement = $this->lgStatement($live);
        $liveFee = $this->commissionFeeRow($live);

        $this->artisan('lg:orphan-rows --fix')
            ->expectsOutputToContain('MODE: FIX')
            ->expectsOutputToContain('Verified: no orphan rows remain.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('letter_of_guarantee_statements', ['id' => $orphanStatement]);
        $this->assertDatabaseMissing('letter_of_guarantee_cash_cover_statements', ['id' => $orphanCashCover]);
        $this->assertDatabaseMissing('current_account_bank_statements', ['id' => $orphanFee]);

        $this->assertDatabaseHas('letter_of_guarantee_statements', ['id' => $liveStatement]);
        $this->assertDatabaseHas('current_account_bank_statements', ['id' => $liveFee]);
    }

    /**
     * The inactive not-yet-due commission row is hidden by
     * CurrentAccountBankStatement's `only_active` global scope. Without
     * withoutGlobalScopes() the command would report it and then
     * silently fail to delete it.
     */
    public function test_fix_deletes_an_inactive_orphan_commission_row(): void
    {
        $inactive = $this->commissionFeeRow(self::DEAD_ISSUANCE, [
            'is_active' => 0,
            'date' => '2026-10-01',
            'full_date' => '2026-10-01 10:00:00',
        ]);

        $this->artisan('lg:orphan-rows --fix')
            ->expectsOutputToContain('Verified: no orphan rows remain.')
            ->assertSuccessful();

        $this->assertDatabaseMissing('current_account_bank_statements', ['id' => $inactive]);
    }

    public function test_fix_is_re_runnable(): void
    {
        $this->lgStatement(self::DEAD_ISSUANCE);

        $this->artisan('lg:orphan-rows --fix')->assertSuccessful();
        $this->artisan('lg:orphan-rows --fix')
            ->expectsOutputToContain('No orphan rows found.')
            ->assertSuccessful();
    }

    // ---------------------------------------------------------------
    // scoping options — the safety valves for a production run
    // ---------------------------------------------------------------

    public function test_the_company_filter_leaves_other_companies_untouched(): void
    {
        $mine = $this->lgStatement(self::DEAD_ISSUANCE);
        $theirs = $this->cashCoverStatement(98); // company 92

        $this->artisan('lg:orphan-rows --fix --company='.self::COMPANY)->assertSuccessful();

        $this->assertDatabaseMissing('letter_of_guarantee_statements', ['id' => $mine]);
        $this->assertDatabaseHas('letter_of_guarantee_cash_cover_statements', ['id' => $theirs]);
    }

    public function test_the_table_filter_restricts_the_run(): void
    {
        $statement = $this->lgStatement(self::DEAD_ISSUANCE);
        $fee = $this->commissionFeeRow(self::DEAD_ISSUANCE);

        $this->artisan('lg:orphan-rows --fix --table=letter_of_guarantee_statements')->assertSuccessful();

        $this->assertDatabaseMissing('letter_of_guarantee_statements', ['id' => $statement]);
        $this->assertDatabaseHas('current_account_bank_statements', ['id' => $fee]);
    }

    public function test_an_unknown_table_is_rejected_rather_than_silently_doing_nothing(): void
    {
        $orphan = $this->lgStatement(self::DEAD_ISSUANCE);

        $this->artisan('lg:orphan-rows --fix --table=letter_of_guarantee_statement')
            ->expectsOutputToContain('Unknown --table=')
            ->assertFailed();

        $this->assertDatabaseHas('letter_of_guarantee_statements', ['id' => $orphan]);
    }

    // ---------------------------------------------------------------
    // balances
    // ---------------------------------------------------------------

    /**
     * The whole reason this is a command and not a migration: deleting
     * through the model re-touches the later rows in the same series so
     * the balance triggers recompute them. A raw SQL DELETE would leave
     * every following balance carrying the removed row.
     */
    public function test_fix_re_touches_the_later_rows_in_the_same_series(): void
    {
        $this->lgStatement(self::DEAD_ISSUANCE, [
            'date' => '2026-01-01',
            'full_date' => '2026-01-01 10:00:00',
        ]);
        $live = $this->makeLiveIssuance(700);
        $laterRow = $this->lgStatement($live, [
            'date' => '2026-05-01',
            'full_date' => '2026-05-01 10:00:00',
        ]);

        DB::table('letter_of_guarantee_statements')->where('id', $laterRow)
            ->update(['updated_at' => '2020-01-01 00:00:00']);

        $this->artisan('lg:orphan-rows --fix')->assertSuccessful();

        $this->assertNotSame(
            '2020-01-01 00:00:00',
            DB::table('letter_of_guarantee_statements')->where('id', $laterRow)->value('updated_at'),
            'The later row was never re-touched, so its running balance would keep the deleted row in it.'
        );
    }
}
