<?php

namespace Tests\Feature\Dashboard;

use App\Enums\LgTypes;
use App\Models\LetterOfGuaranteeIssuance;
use App\Models\LetterOfGuaranteeStatement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The Limit popup on the LG/LC dashboard reads cash cover from
 * LetterOfGuaranteeStatement::getTotalCashCoverForAllTypes().
 *
 * Opening-balance issuances never write a cash-cover statement row, so
 * the method has to fall back to summing cash_cover_amount on
 * lg-facility issuances — the same figure the LG table already shows.
 *
 * @see \App\Models\LetterOfGuaranteeStatement::getTotalCashCoverForAllTypes()
 */
class LgFacilityLimitCashCoverTest extends TestCase
{
    private const COMPANY_ID = 9301;

    private const BANK_ID = 9302;

    private const FACILITY_ID = 9303;

    /** @var list<string> */
    private array $tables = [
        'letter_of_guarantee_cash_cover_statements',
        'letter_of_guarantee_issuances',
        'companies',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $database = DB::connection()->getDatabaseName();
        $this->assertStringContainsString('test', $database, "Refusing to run against '{$database}'.");

        $this->dropTables();

        Schema::create('companies', function ($table) {
            $table->unsignedBigInteger('id')->primary();
            $table->string('odoo_db_url')->nullable();
            $table->string('odoo_db_name')->nullable();
        });

        Schema::create('letter_of_guarantee_issuances', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('lg_facility_id')->nullable();
            $table->unsignedBigInteger('financial_institution_id')->nullable();
            $table->string('source')->nullable();
            $table->string('status')->nullable();
            $table->string('lg_type')->nullable();
            $table->string('lg_currency')->nullable();
            $table->decimal('cash_cover_amount', 14)->default(0);
        });

        Schema::create('letter_of_guarantee_cash_cover_statements', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id');
            $table->string('currency')->nullable();
            $table->unsignedBigInteger('financial_institution_id')->default(0);
            $table->unsignedBigInteger('lg_facility_id')->default(0);
            $table->string('lg_type')->nullable();
            $table->string('source')->nullable();
            $table->date('date')->nullable();
            $table->decimal('end_balance', 14)->default(0);
        });

        DB::table('companies')->insert([
            'id' => self::COMPANY_ID,
            'odoo_db_url' => null,
            'odoo_db_name' => null,
        ]);
    }

    protected function tearDown(): void
    {
        $this->dropTables();

        parent::tearDown();
    }

    public function test_issuance_cash_cover_is_used_when_there_are_no_statement_rows(): void
    {
        $this->insertIssuance([
            'cash_cover_amount' => 75_000,
            'source' => LetterOfGuaranteeIssuance::LG_FACILITY,
        ]);

        $this->assertSame(75_000.0, $this->total());
    }

    public function test_against_td_or_cd_issuances_at_the_same_bank_are_excluded(): void
    {
        $this->insertIssuance([
            'cash_cover_amount' => 75_000,
            'source' => LetterOfGuaranteeIssuance::LG_FACILITY,
        ]);
        $this->insertIssuance([
            'cash_cover_amount' => 40_000,
            'source' => LetterOfGuaranteeIssuance::AGAINST_TD,
        ]);
        $this->insertIssuance([
            'cash_cover_amount' => 10_000,
            'source' => LetterOfGuaranteeIssuance::AGAINST_CD,
            'lg_facility_id' => null,
        ]);

        $this->assertSame(75_000.0, $this->total());
    }

    public function test_cancelled_issuances_are_excluded(): void
    {
        $this->insertIssuance([
            'cash_cover_amount' => 75_000,
            'source' => LetterOfGuaranteeIssuance::LG_FACILITY,
        ]);
        $this->insertIssuance([
            'cash_cover_amount' => 40_000,
            'source' => LetterOfGuaranteeIssuance::LG_FACILITY,
            'status' => LetterOfGuaranteeIssuance::CANCELLED,
        ]);

        $this->assertSame(75_000.0, $this->total());
    }

    public function test_a_statement_end_balance_wins_over_issuance_amounts(): void
    {
        $this->insertIssuance([
            'cash_cover_amount' => 999_000,
            'source' => LetterOfGuaranteeIssuance::LG_FACILITY,
        ]);
        DB::table('letter_of_guarantee_cash_cover_statements')->insert([
            'company_id' => self::COMPANY_ID,
            'currency' => 'EGP',
            'financial_institution_id' => self::BANK_ID,
            'lg_facility_id' => self::FACILITY_ID,
            'lg_type' => LgTypes::BID_BOND,
            'source' => LetterOfGuaranteeIssuance::LG_FACILITY,
            'date' => '2026-01-15',
            'end_balance' => 150_000,
        ]);

        $this->assertSame(150_000.0, $this->total());
    }

    private function total(): float
    {
        return LetterOfGuaranteeStatement::getTotalCashCoverForAllTypes(
            self::FACILITY_ID,
            self::COMPANY_ID,
            self::BANK_ID,
            'EGP'
        );
    }

    private function insertIssuance(array $overrides): void
    {
        DB::table('letter_of_guarantee_issuances')->insert($overrides + [
            'company_id' => self::COMPANY_ID,
            'lg_facility_id' => self::FACILITY_ID,
            'financial_institution_id' => self::BANK_ID,
            'status' => LetterOfGuaranteeIssuance::RUNNING,
            'lg_type' => LgTypes::BID_BOND,
            'lg_currency' => 'EGP',
            'cash_cover_amount' => 0,
            'source' => LetterOfGuaranteeIssuance::LG_FACILITY,
        ]);
    }

    private function dropTables(): void
    {
        foreach ($this->tables as $table) {
            Schema::dropIfExists($table);
        }
    }
}
