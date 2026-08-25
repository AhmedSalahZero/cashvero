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

        /**
         * transaction_name, lg_code, issuance_date and partner_id —
         * the LG's customer/beneficiary, what "Search By → Customer
         * Name" resolves through — all come from LgSchemaFixture now,
         * so this used to add them here and no longer needs to.
         */
        Schema::dropIfExists('partners');
        Schema::create('partners', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('partners');
        $this->dropLgSchema();

        parent::tearDown();
    }

    /**
     * One LG belonging to a named customer.
     */
    private function lgForCustomer(string $customerName, string $transactionName, array $overrides = []): void
    {
        DB::table('partners')->insert([
            'company_id' => self::COMPANY_ID,
            'name' => $customerName,
        ]);
        $partnerId = (int) DB::getPdo()->lastInsertId();

        DB::table('letter_of_guarantee_issuances')->insert($overrides + [
            'company_id' => self::COMPANY_ID,
            'lg_type' => LgTypes::BID_BOND,
            'transaction_name' => $transactionName,
            'lg_code' => 'LG-'.$partnerId,
            'partner_id' => $partnerId,
            'issuance_date' => now()->subMonth()->format('Y-m-d'),
            'renewal_date' => now()->addMonth()->format('Y-m-d'),
            'status' => 'running',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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

    /**
     * Search By → Customer Name, the third option next to Transaction
     * Name and LG Code. It matches on the beneficiary's name — the same
     * value the list's own "Beneficiary" column displays — not on
     * anything stored on the LG row itself.
     */
    public function test_search_by_customer_name_matches_the_beneficiary(): void
    {
        $this->lgForCustomer('Orange Egypt', 'Orange Bid Bond');
        $this->lgForCustomer('Vodafone Egypt', 'Vodafone Bid Bond');

        $company = Company::find(self::COMPANY_ID);
        $response = app(\App\Http\Controllers\LetterOfGuaranteeIssuanceController::class)
            ->tabData($company, request()->merge([
                'type' => LgTypes::BID_BOND,
                'field' => 'customer_name',
                'value' => 'Vodafone',
            ]));

        $payload = $response->getData(true);

        $this->assertCount(1, $payload['rows']);
        $this->assertSame('Vodafone Bid Bond', $payload['rows'][0]['transaction_name']);
        $this->assertSame('Vodafone Egypt', $payload['rows'][0]['beneficiary_name']);
        $this->assertSame(1, $payload['total'], 'The paginator total must match the rows listed.');
    }

    /** A partial term is enough, the same as the other two searches. */
    public function test_search_by_customer_name_matches_on_part_of_the_name(): void
    {
        $this->lgForCustomer('El Sewedy Electric', 'Sewedy LG');
        $this->lgForCustomer('Orange Egypt', 'Orange LG');

        $company = Company::find(self::COMPANY_ID);
        $response = app(\App\Http\Controllers\LetterOfGuaranteeIssuanceController::class)
            ->tabData($company, request()->merge([
                'type' => LgTypes::BID_BOND,
                'field' => 'customer_name',
                'value' => 'sewedy',
            ]));

        $this->assertSame(['Sewedy LG'], array_column($response->getData(true)['rows'], 'transaction_name'));
    }

    /**
     * Like the other two searches, it must reach records whose expiry
     * fell outside the default 60-month window — otherwise an old LG
     * could be searched for by code but not by the customer it was
     * issued for.
     */
    public function test_search_by_customer_name_ignores_the_expiry_window(): void
    {
        $this->lgForCustomer('Ancient Customer', 'Ancient LG', [
            'renewal_date' => now()->subYears(8)->format('Y-m-d'),
        ]);

        $company = Company::find(self::COMPANY_ID);
        $response = app(\App\Http\Controllers\LetterOfGuaranteeIssuanceController::class)
            ->tabData($company, request()->merge([
                'type' => LgTypes::BID_BOND,
                'field' => 'customer_name',
                'value' => 'Ancient Customer',
            ]));

        $this->assertCount(1, $response->getData(true)['rows']);
    }

    /** An LG with no customer at all must never match a name search. */
    public function test_an_lg_without_a_customer_is_not_matched(): void
    {
        DB::table('letter_of_guarantee_issuances')->insert([
            'company_id' => self::COMPANY_ID,
            'lg_type' => LgTypes::BID_BOND,
            'transaction_name' => 'No Customer LG',
            'lg_code' => 'NC-1',
            'partner_id' => null,
            'issuance_date' => now()->subMonth()->format('Y-m-d'),
            'renewal_date' => now()->addMonth()->format('Y-m-d'),
            'status' => 'running',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $company = Company::find(self::COMPANY_ID);
        $response = app(\App\Http\Controllers\LetterOfGuaranteeIssuanceController::class)
            ->tabData($company, request()->merge([
                'type' => LgTypes::BID_BOND,
                'field' => 'customer_name',
                'value' => 'Customer',
            ]));

        $this->assertCount(0, $response->getData(true)['rows']);
    }
}
