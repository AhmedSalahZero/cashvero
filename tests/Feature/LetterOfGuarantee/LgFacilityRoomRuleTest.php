<?php

namespace Tests\Feature\LetterOfGuarantee;

use App\Enums\LgTypes;
use App\Models\Company;
use App\Models\LetterOfGuaranteeIssuance;
use App\Rules\LetterOfGuaranteeFacilityRoomRule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * An LG issued against a facility must fit in the remaining room.
 *
 * Same number the issuance form shows as Total LGs Room — limit minus
 * outstanding, with this issuance's own amount added back on edit.
 *
 * @see \App\Rules\LetterOfGuaranteeFacilityRoomRule
 */
class LgFacilityRoomRuleTest extends TestCase
{
    private const COMPANY_ID = 9101;

    private const BANK_ID = 9102;

    private const FACILITY_ID = 9103;

    private const ISSUANCE_ID = 9104;

    private const LIMIT = 1_000_000;

    /** @var list<string> */
    private array $tables = [
        'letter_of_guarantee_statements',
        'letter_of_guarantee_issuances',
        'letter_of_guarantee_facility_term_and_conditions',
        'letter_of_guarantee_facilities',
        'financial_institutions',
        'partners',
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

        Schema::create('partners', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name')->nullable();
            $table->boolean('is_customer')->default(0);
            $table->boolean('is_other_partner')->default(0);
        });

        Schema::create('financial_institutions', function ($table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name')->nullable();
            $table->unsignedBigInteger('updated_by')->default(0);
            $table->timestamps();
        });

        Schema::create('letter_of_guarantee_facilities', function ($table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('financial_institution_id')->nullable();
            $table->string('name')->nullable();
            $table->string('currency')->nullable();
            $table->decimal('limit', 14)->default(0);
            $table->timestamps();
        });

        Schema::create('letter_of_guarantee_facility_term_and_conditions', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('letter_of_guarantee_facility_id')->nullable();
            $table->unsignedBigInteger('company_id');
            $table->string('lg_type')->nullable();
            $table->decimal('min_commission_fees', 14)->default(0);
            $table->decimal('commission_rate', 14)->default(0);
            $table->decimal('cash_cover_rate', 14)->default(0);
            $table->decimal('issuance_fees', 14)->default(0);
        });

        Schema::create('letter_of_guarantee_issuances', function ($table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('company_id');
            $table->decimal('lg_amount', 14)->default(0);
            $table->decimal('cash_cover_rate', 14)->default(0);
            $table->decimal('lg_commission_rate', 14)->default(0);
            $table->decimal('issuance_fees', 14)->default(0);
        });

        Schema::create('letter_of_guarantee_statements', function ($table) {
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
        DB::table('financial_institutions')->insert([
            'id' => self::BANK_ID,
            'company_id' => self::COMPANY_ID,
            'name' => 'Test Bank',
            'updated_by' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('letter_of_guarantee_facilities')->insert([
            'id' => self::FACILITY_ID,
            'company_id' => self::COMPANY_ID,
            'financial_institution_id' => self::BANK_ID,
            'name' => 'LG Line',
            'currency' => 'EGP',
            'limit' => self::LIMIT,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        $this->dropTables();

        parent::tearDown();
    }

    public function test_a_non_facility_source_is_not_checked(): void
    {
        $this->assertTrue($this->passes(
            self::LIMIT + 1,
            LetterOfGuaranteeIssuance::AGAINST_TD
        ));
    }

    public function test_missing_facility_ids_are_skipped_not_failed(): void
    {
        $rule = new LetterOfGuaranteeFacilityRoomRule(
            Company::find(self::COMPANY_ID),
            self::LIMIT + 1,
            LetterOfGuaranteeIssuance::LG_FACILITY,
            null,
            null,
            LgTypes::BID_BOND
        );

        $this->assertTrue($rule->passes('lg_facility_room', null));
    }

    public function test_an_amount_above_the_room_is_rejected(): void
    {
        $this->assertFalse($this->passes(self::LIMIT + 1));
        $this->assertSame(
            __('This exceeds what is left of the LG Facility. Reduce the amount or pick another facility.'),
            $this->rule(self::LIMIT + 1)->message()
        );
    }

    public function test_an_amount_equal_to_the_room_is_accepted(): void
    {
        $this->assertTrue($this->passes(self::LIMIT));
    }

    public function test_outstanding_issuances_shrink_the_room(): void
    {
        DB::table('letter_of_guarantee_statements')->insert([
            'company_id' => self::COMPANY_ID,
            'currency' => 'EGP',
            'financial_institution_id' => self::BANK_ID,
            'lg_facility_id' => self::FACILITY_ID,
            'lg_type' => LgTypes::BID_BOND,
            'source' => LetterOfGuaranteeIssuance::LG_FACILITY,
            'date' => '2026-01-01',
            'end_balance' => 800_000,
        ]);

        $this->assertFalse($this->passes(200_001));
        $this->assertTrue($this->passes(200_000));
    }

    public function test_an_unchanged_edit_amount_fits_because_the_lookup_adds_it_back(): void
    {
        DB::table('letter_of_guarantee_issuances')->insert([
            'id' => self::ISSUANCE_ID,
            'company_id' => self::COMPANY_ID,
            'lg_amount' => 400_000,
            'cash_cover_rate' => 0,
            'lg_commission_rate' => 0,
            'issuance_fees' => 0,
        ]);
        DB::table('letter_of_guarantee_statements')->insert([
            'company_id' => self::COMPANY_ID,
            'currency' => 'EGP',
            'financial_institution_id' => self::BANK_ID,
            'lg_facility_id' => self::FACILITY_ID,
            'lg_type' => LgTypes::BID_BOND,
            'source' => LetterOfGuaranteeIssuance::LG_FACILITY,
            'date' => '2026-01-01',
            'end_balance' => 400_000,
        ]);

        $this->assertTrue($this->passes(400_000, LetterOfGuaranteeIssuance::LG_FACILITY, self::ISSUANCE_ID));
        $this->assertFalse($this->passes(self::LIMIT + 1, LetterOfGuaranteeIssuance::LG_FACILITY, self::ISSUANCE_ID));
    }

    private function passes(float $amount, string $source = LetterOfGuaranteeIssuance::LG_FACILITY, $issuanceId = null): bool
    {
        return $this->rule($amount, $source, $issuanceId)->passes('lg_facility_room', null);
    }

    private function rule(float $amount, string $source = LetterOfGuaranteeIssuance::LG_FACILITY, $issuanceId = null): LetterOfGuaranteeFacilityRoomRule
    {
        return new LetterOfGuaranteeFacilityRoomRule(
            Company::find(self::COMPANY_ID),
            $amount,
            $source,
            self::BANK_ID,
            self::FACILITY_ID,
            LgTypes::BID_BOND,
            $issuanceId
        );
    }

    private function dropTables(): void
    {
        foreach ($this->tables as $table) {
            Schema::dropIfExists($table);
        }
    }
}
