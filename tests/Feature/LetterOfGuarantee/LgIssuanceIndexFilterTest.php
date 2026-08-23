<?php

namespace Tests\Feature\LetterOfGuarantee;

use App\Enums\LgTypes;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * LG Issuance index tabData(): default list is limited to LGs whose
 * expiry (renewal_date) falls in the rolling window; search ignores
 * that window and scans all rows.
 */
class LgIssuanceIndexFilterTest extends TestCase
{
    use LgSchemaFixture;

    private const COMPANY_ID = 901;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertOnTestDatabase();
        $this->createLgSchema();

        DB::table('companies')->insert([
            'id' => self::COMPANY_ID,
            'odoo_db_url' => null,
            'odoo_db_name' => null,
        ]);

        Schema::table('letter_of_guarantee_issuances', function ($table) {
            $table->string('transaction_name')->nullable();
            $table->string('lg_code')->nullable();
            $table->date('issuance_date')->nullable();
        });
    }

    protected function tearDown(): void
    {
        $this->dropLgSchema();

        parent::tearDown();
    }

    public function test_default_tab_list_filters_by_renewal_date_within_expiry_window(): void
    {
        DB::table('letter_of_guarantee_issuances')->insert([
            [
                'company_id' => self::COMPANY_ID,
                'lg_type' => LgTypes::BID_BOND,
                'transaction_name' => 'Recent Expiry LG',
                'lg_code' => 'REC-001',
                'issuance_date' => now()->subYears(2)->format('Y-m-d'),
                'renewal_date' => now()->addMonth()->format('Y-m-d'),
                'status' => 'running',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'company_id' => self::COMPANY_ID,
                'lg_type' => LgTypes::BID_BOND,
                'transaction_name' => 'Ancient Expiry LG',
                'lg_code' => 'OLD-001',
                'issuance_date' => now()->subYears(10)->format('Y-m-d'),
                'renewal_date' => now()->subYears(8)->format('Y-m-d'),
                'status' => 'running',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $company = Company::find(self::COMPANY_ID);
        $response = app(\App\Http\Controllers\LetterOfGuaranteeIssuanceController::class)
            ->tabData($company, request()->merge([
                'type' => LgTypes::BID_BOND,
            ]));

        $payload = $response->getData(true);
        $names = array_column($payload['rows'], 'transaction_name');

        $this->assertSame(['Recent Expiry LG'], $names);
    }

    public function test_search_finds_records_outside_expiry_window(): void
    {
        DB::table('letter_of_guarantee_issuances')->insert([
            'company_id' => self::COMPANY_ID,
            'lg_type' => LgTypes::BID_BOND,
            'transaction_name' => 'Ancient Expiry LG',
            'lg_code' => 'OLD-SEARCH',
            'issuance_date' => now()->subYears(10)->format('Y-m-d'),
            'renewal_date' => now()->subYears(8)->format('Y-m-d'),
            'status' => 'running',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $company = Company::find(self::COMPANY_ID);
        $response = app(\App\Http\Controllers\LetterOfGuaranteeIssuanceController::class)
            ->tabData($company, request()->merge([
                'type' => LgTypes::BID_BOND,
                'field' => 'transaction_name',
                'value' => 'Ancient Expiry',
            ]));

        $payload = $response->getData(true);

        $this->assertCount(1, $payload['rows']);
        $this->assertSame('Ancient Expiry LG', $payload['rows'][0]['transaction_name']);
    }
}
