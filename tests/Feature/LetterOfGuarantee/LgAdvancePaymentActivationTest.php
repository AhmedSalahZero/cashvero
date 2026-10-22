<?php

namespace Tests\Feature\LetterOfGuarantee;

use App\Enums\LgTypes;
use App\Http\Controllers\LetterOfGuaranteeIssuanceController;
use App\Http\Controllers\LetterOfGuaranteeIssuanceRenewalDateController;
use App\Http\Requests\StoreLetterOfGuaranteeIssuanceRequest;
use App\Http\Requests\StoreLgRenewalDateRequest;
use App\Http\Requests\UpdateLetterOfGuaranteeIssuanceRequest;
use App\Models\Company;
use App\Models\LetterOfGuaranteeIssuance;
use App\Models\LetterOfGuaranteeIssuanceAdvancedPaymentHistory;
use App\Models\User;
use App\Services\Api\OdooSync;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionMethod;
use Tests\TestCase;

/**
 * An Advance Payment LG only takes effect once the bank activates it —
 * usually when the customer's advance payment arrives.
 *
 *   Unactivated — saved without an Activation Date. Nothing is posted
 *       for it: no LG balance, no cash cover, no issuance fees, no
 *       commission, nothing in Odoo.
 *   Activated — the Activation Date is entered from Edit. Everything the
 *       Issuance Date used to drive is posted on the Activation Date
 *       instead, and commission runs from there to the Renewal Date.
 *
 * Every other LG type is untouched: it never waits for activation.
 *
 * @see \App\Models\LetterOfGuaranteeIssuance::getMovementDate()
 * @see \App\Http\Controllers\LetterOfGuaranteeIssuanceController::storeWithinTransaction()
 */
class LgAdvancePaymentActivationTest extends TestCase
{
    use LgSchemaFixture;

    private const COMPANY = 146;

    private const ACCOUNT = 334;

    private const BANK = 125;

    private const PARTNER = 501;

    private const ISSUANCE_DATE = '2026-01-10';

    private const ACTIVATION_DATE = '2026-04-10';

    private const RENEWAL_DATE = '2027-01-10';

    private const COMMISSION = 1000;

    /** @var list<string> */
    private array $storeTables = ['partners', 'account_types', 'financial_institutions', 'letter_of_guarantee_facilities', 'banks'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertOnTestDatabase();
        $this->createLgSchema();
        $this->createStoreSchema();

        /**
         * Frozen so the is_active flag each commission row is written
         * with (now() >= the row's own date) is the same on every run.
         */
        Carbon::setTestNow('2026-08-25');

        DB::table('companies')->insert(['id' => self::COMPANY]);
        DB::table('financial_institution_accounts')->insert([
            'id' => self::ACCOUNT,
            'company_id' => self::COMPANY,
            'account_number' => '111222333',
            'currency' => 'EGP',
            'balance_date' => '2025-12-31',
            'synced_end_of_month_years' => json_encode(['2026', '2027']),
        ]);
        DB::table('partners')->insert([
            'id' => self::PARTNER,
            'company_id' => self::COMPANY,
            'name' => 'Advance Customer',
            'is_customer' => 1,
        ]);
        DB::table('financial_institutions')->insert([
            'id' => self::BANK,
            'company_id' => self::COMPANY,
            'type' => 'other',
            'name' => 'Test Bank',
        ]);
        DB::table('letter_of_guarantee_facilities')->insert([
            'id' => 9,
            'company_id' => self::COMPANY,
        ]);

        $this->actingAs((new User)->forceFill(['id' => 7, 'name' => 'Tester']));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->dropStoreSchema();
        $this->dropLgSchema();

        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // fixtures
    // ---------------------------------------------------------------

    /**
     * The delete-path fixture plus what saving a new LG reads on top.
     */
    private function createStoreSchema(): void
    {
        $this->dropStoreSchema();

        Schema::table('letter_of_guarantee_issuances', function ($table) {
            $table->unsignedBigInteger('created_by')->nullable();
        });

        Schema::create('partners', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name')->nullable();
            $table->boolean('is_customer')->default(0);
        });

        Schema::create('account_types', function ($table) {
            $table->bigIncrements('id');
            $table->string('slug')->nullable();
        });

        Schema::create('banks', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
        });

        Schema::create('financial_institutions', function ($table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('company_id')->nullable();
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->string('type')->nullable();
            $table->string('name')->nullable();
        });

        Schema::create('letter_of_guarantee_facilities', function ($table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('company_id')->nullable();
        });
    }

    private function dropStoreSchema(): void
    {
        foreach ($this->storeTables as $table) {
            Schema::dropIfExists($table);
        }
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'category_name' => LetterOfGuaranteeIssuance::NEW_ISSUANCE,
            'transaction_name' => 'Advance LG',
            'lg_code' => 'LG-ADV-1',
            'financial_institution_id' => self::BANK,
            'lg_type' => LgTypes::ADVANCED_PAYMENT_LGS,
            'partner_id' => self::PARTNER,
            'activation_date' => null,
            'issuance_date' => self::ISSUANCE_DATE,
            'renewal_date' => self::RENEWAL_DATE,
            'lg_duration_months' => 12,
            'lg_amount' => 100000,
            'lg_currency' => 'EGP',
            'cash_cover_rate' => 10,
            'cash_cover_amount' => 10000,
            'lg_commission_rate' => 1,
            'lg_commission_amount' => self::COMMISSION,
            'min_lg_commission_fees' => 0,
            'issuance_fees' => 500,
            'lg_commission_interval' => 'quarterly',
            // 27 = a current account, so the cash cover moves through it.
            'lg_fees_and_commission_account_type' => '27',
            'lg_fees_and_commission_account_id' => self::ACCOUNT,
            'cash_cover_deducted_from_account_type' => '27',
            'cash_cover_deducted_from_account_id' => self::ACCOUNT,
        ];
    }

    private function issue(array $overrides = [], string $source = LetterOfGuaranteeIssuance::HUNDRED_PERCENTAGE_CASH_COVER): LetterOfGuaranteeIssuance
    {
        (new LetterOfGuaranteeIssuanceController)->store(
            Company::findOrFail(self::COMPANY),
            StoreLetterOfGuaranteeIssuanceRequest::create('/', 'POST', $this->payload($overrides)),
            $source
        );

        return $this->latestIssuance();
    }

    /**
     * Edit deletes the LG and saves it again from the form — which is
     * exactly how an Activation Date gets entered.
     */
    private function edit(LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance, array $overrides, string $source = LetterOfGuaranteeIssuance::HUNDRED_PERCENTAGE_CASH_COVER): LetterOfGuaranteeIssuance
    {
        (new LetterOfGuaranteeIssuanceController)->update(
            Company::findOrFail(self::COMPANY),
            UpdateLetterOfGuaranteeIssuanceRequest::create('/', 'PUT', $this->payload($overrides)),
            $letterOfGuaranteeIssuance,
            $source
        );

        return $this->latestIssuance();
    }

    private function latestIssuance(): LetterOfGuaranteeIssuance
    {
        return LetterOfGuaranteeIssuance::query()->orderByDesc('id')->firstOrFail();
    }

    private function lgStatements(int $issuanceId): array
    {
        return DB::table('letter_of_guarantee_statements')->where('letter_of_guarantee_issuance_id', $issuanceId)->get()->all();
    }

    private function cashCoverStatements(int $issuanceId): array
    {
        return DB::table('letter_of_guarantee_cash_cover_statements')->where('letter_of_guarantee_issuance_id', $issuanceId)->get()->all();
    }

    private function bankRows(int $issuanceId): array
    {
        return DB::table('current_account_bank_statements')->where('letter_of_guarantee_issuance_id', $issuanceId)->orderBy('date')->orderBy('id')->get()->all();
    }

    /** @return list<string> */
    private function commissionDates(int $issuanceId): array
    {
        return DB::table('current_account_bank_statements')
            ->where('letter_of_guarantee_issuance_id', $issuanceId)
            ->where('is_commission_fees', 1)
            ->orderBy('date')
            ->pluck('date')
            ->map(fn ($date) => Carbon::make($date)->format('Y-m-d'))
            ->all();
    }

    /**
     * Runs $issue inside an outer OdooSync transaction, so whatever it
     * defers is still pending afterwards — then drops it unsent.
     */
    private function odooCallsQueuedBy(callable $issue): bool
    {
        return OdooSync::transaction(function () use ($issue) {
            $issue();
            $queued = OdooSync::hasPending();
            OdooSync::discard();

            return $queued;
        });
    }

    private function nothingPostedFor(int $issuanceId): void
    {
        $this->assertSame([], $this->lgStatements($issuanceId), 'No LG balance before activation.');
        $this->assertSame([], $this->cashCoverStatements($issuanceId), 'No cash cover before activation.');
        $this->assertSame([], $this->bankRows($issuanceId), 'No cash cover, fees or commission taken from the account before activation.');
    }

    /**
     * Production sets is_debit / is_credit in a MySQL trigger. This
     * fixture has no triggers, so apply the same rule before a path
     * that looks those flags up.
     */
    private function applyStatementDirectionFlags(): void
    {
        DB::table('current_account_bank_statements')->where('debit', '>', 0)->update(['is_debit' => 1, 'is_credit' => 0]);
        DB::table('current_account_bank_statements')->where('credit', '>', 0)->update(['is_credit' => 1, 'is_debit' => 0]);
    }

    // ---------------------------------------------------------------
    // before activation
    // ---------------------------------------------------------------

    public function test_an_advance_payment_lg_saved_without_an_activation_date_posts_nothing(): void
    {
        $letterOfGuaranteeIssuance = $this->issue();

        $this->assertNull($letterOfGuaranteeIssuance->activation_date);
        $this->assertFalse($letterOfGuaranteeIssuance->is_activated);
        $this->assertTrue($letterOfGuaranteeIssuance->isUnactivated());
        $this->nothingPostedFor($letterOfGuaranteeIssuance->id);
    }

    /**
     * Odoo mirrors what is posted here, so it waits for the same date.
     */
    public function test_nothing_is_sent_to_odoo_until_the_lg_is_activated(): void
    {
        DB::table('companies')->where('id', self::COMPANY)->update(['odoo_db_url' => 'https://odoo.invalid', 'odoo_db_name' => 'cashvero']);
        $this->actingAs((new User)->forceFill(['id' => 7, 'name' => 'Tester', 'odoo_username' => 'tester', 'odoo_db_password' => 'secret']));

        $this->assertFalse($this->odooCallsQueuedBy(fn () => $this->issue()));
        $this->assertTrue(
            $this->odooCallsQueuedBy(fn () => $this->issue(['lg_code' => 'LG-ADV-2', 'activation_date' => self::ACTIVATION_DATE])),
            'Once activated the same LG is sent to Odoo — so the check above is not passing for some other reason.'
        );
    }

    // ---------------------------------------------------------------
    // activating it
    // ---------------------------------------------------------------

    public function test_entering_the_activation_date_posts_everything_on_that_date(): void
    {
        $letterOfGuaranteeIssuance = $this->edit($this->issue(), ['activation_date' => self::ACTIVATION_DATE]);

        $this->assertTrue($letterOfGuaranteeIssuance->is_activated);
        $this->assertSame(self::ACTIVATION_DATE, $letterOfGuaranteeIssuance->getMovementDate());

        $lgStatements = $this->lgStatements($letterOfGuaranteeIssuance->id);
        $this->assertCount(1, $lgStatements);
        $this->assertSame(self::ACTIVATION_DATE, Carbon::make($lgStatements[0]->date)->format('Y-m-d'));
        $this->assertEquals(100000, $lgStatements[0]->credit);

        $cashCoverStatements = $this->cashCoverStatements($letterOfGuaranteeIssuance->id);
        $this->assertCount(1, $cashCoverStatements);
        $this->assertSame(self::ACTIVATION_DATE, Carbon::make($cashCoverStatements[0]->date)->format('Y-m-d'));
        $this->assertEquals(10000, $cashCoverStatements[0]->debit);

        $nonCommissionRows = array_values(array_filter($this->bankRows($letterOfGuaranteeIssuance->id), fn ($row) => ! $row->is_commission_fees));
        $this->assertCount(2, $nonCommissionRows, 'The cash cover and the issuance fees.');
        foreach ($nonCommissionRows as $row) {
            $this->assertSame(self::ACTIVATION_DATE, Carbon::make($row->date)->format('Y-m-d'));
        }
        $this->assertEqualsCanonicalizing([10000, 500], array_map(fn ($row) => (float) $row->credit, $nonCommissionRows));
    }

    /**
     * The form's duration counts from the Issuance Date (12 months, so 4
     * quarters). Activated three months in, only 3 quarters are left
     * before it expires — charging the 4th would bill a quarter after
     * the LG is gone.
     */
    public function test_commission_runs_from_the_activation_date_to_the_renewal_date(): void
    {
        $letterOfGuaranteeIssuance = $this->edit($this->issue(), ['activation_date' => self::ACTIVATION_DATE]);

        $this->assertSame(['2026-04-10', '2026-07-10', '2026-10-10'], $this->commissionDates($letterOfGuaranteeIssuance->id));
    }

    /**
     * Existing Advance Payment LGs were back-filled with an Activation
     * Date equal to their Issuance Date — re-saving one must post exactly
     * what it always did.
     */
    public function test_an_lg_activated_on_its_issuance_date_is_charged_exactly_as_before(): void
    {
        $letterOfGuaranteeIssuance = $this->issue(['activation_date' => self::ISSUANCE_DATE]);

        $this->assertTrue($letterOfGuaranteeIssuance->is_activated);
        $this->assertSame(['2026-01-10', '2026-04-10', '2026-07-10', '2026-10-10'], $this->commissionDates($letterOfGuaranteeIssuance->id));
        $this->assertSame(self::ISSUANCE_DATE, Carbon::make($this->lgStatements($letterOfGuaranteeIssuance->id)[0]->date)->format('Y-m-d'));
    }

    public function test_clearing_the_activation_date_again_takes_back_what_it_posted(): void
    {
        $activated = $this->edit($this->issue(), ['activation_date' => self::ACTIVATION_DATE]);

        $unactivated = $this->edit($activated, ['activation_date' => null]);

        $this->assertFalse($unactivated->is_activated);
        $this->nothingPostedFor($unactivated->id);
        $this->assertSame(0, DB::table('letter_of_guarantee_statements')->count(), 'The LG balance posted on activation is gone.');
        $this->assertSame(0, DB::table('letter_of_guarantee_cash_cover_statements')->count(), 'So is its cash cover.');
    }

    // ---------------------------------------------------------------
    // other LG types
    // ---------------------------------------------------------------

    public function test_other_lg_types_ignore_the_activation_date_and_post_on_the_issuance_date(): void
    {
        $letterOfGuaranteeIssuance = $this->issue([
            'lg_type' => LgTypes::FINAL_LGS,
            'activation_date' => self::ACTIVATION_DATE,
        ]);

        $this->assertNull($letterOfGuaranteeIssuance->activation_date, 'Only an Advance Payment LG keeps an Activation Date.');
        $this->assertTrue($letterOfGuaranteeIssuance->is_activated);
        $this->assertSame(self::ISSUANCE_DATE, $letterOfGuaranteeIssuance->getMovementDate());
        $this->assertSame(self::ISSUANCE_DATE, Carbon::make($this->lgStatements($letterOfGuaranteeIssuance->id)[0]->date)->format('Y-m-d'));
        $this->assertCount(4, $this->commissionDates($letterOfGuaranteeIssuance->id));
    }

    // ---------------------------------------------------------------
    // cancel and back to running
    // ---------------------------------------------------------------

    /**
     * The advance payment may never arrive, so cancelling has to stay
     * possible — but there is no cash cover to refund and no balance to
     * close.
     */
    public function test_cancelling_an_unactivated_lg_only_changes_its_status(): void
    {
        $letterOfGuaranteeIssuance = $this->issue();

        (new LetterOfGuaranteeIssuanceController)->cancel(
            Company::findOrFail(self::COMPANY),
            Request::create('/', 'POST', ['cancellation_date' => '2026-06-01']),
            $letterOfGuaranteeIssuance,
            LetterOfGuaranteeIssuance::HUNDRED_PERCENTAGE_CASH_COVER
        );

        $letterOfGuaranteeIssuance->refresh();
        $this->assertSame(LetterOfGuaranteeIssuance::CANCELLED, $letterOfGuaranteeIssuance->status);
        $this->nothingPostedFor($letterOfGuaranteeIssuance->id);
    }

    public function test_setting_an_unactivated_lg_back_to_running_posts_nothing(): void
    {
        $letterOfGuaranteeIssuance = $this->issue();
        $letterOfGuaranteeIssuance->update(['status' => LetterOfGuaranteeIssuance::CANCELLED, 'cancellation_date' => '2026-06-01']);

        (new LetterOfGuaranteeIssuanceController)->backToRunningStatus(
            Company::findOrFail(self::COMPANY),
            Request::create('/', 'POST'),
            $letterOfGuaranteeIssuance->refresh(),
            LetterOfGuaranteeIssuance::HUNDRED_PERCENTAGE_CASH_COVER
        );

        $letterOfGuaranteeIssuance->refresh();
        $this->assertSame(LetterOfGuaranteeIssuance::RUNNING, $letterOfGuaranteeIssuance->status);
        $this->nothingPostedFor($letterOfGuaranteeIssuance->id);
    }

    // ---------------------------------------------------------------
    // what waits for activation
    // ---------------------------------------------------------------

    public function test_renewing_is_refused_until_the_lg_is_activated(): void
    {
        $letterOfGuaranteeIssuance = $this->issue();

        $response = (new LetterOfGuaranteeIssuanceRenewalDateController)->store(
            StoreLgRenewalDateRequest::create('/', 'POST', [
                'renewal_date' => '01/01/2028',
                'expiry_date' => self::RENEWAL_DATE,
                'fees_amount' => '300',
            ]),
            Company::findOrFail(self::COMPANY),
            $letterOfGuaranteeIssuance
        );

        $this->assertSame(__('This LG is not activated yet. Enter its Activation Date first.'), $response->getSession()->get('fail'));
        $this->assertSame(0, DB::table('lg_renewal_date_histories')->count());
        $this->assertSame(self::RENEWAL_DATE, Carbon::make($letterOfGuaranteeIssuance->refresh()->renewal_date)->format('Y-m-d'));
    }

    public function test_amount_to_be_decreased_is_refused_until_the_lg_is_activated(): void
    {
        $letterOfGuaranteeIssuance = $this->issue();

        $response = (new LetterOfGuaranteeIssuanceController)->applyAmountToBeDecreased(
            Company::findOrFail(self::COMPANY),
            Request::create('/', 'POST', ['date' => '2026-06-01', 'amount' => 40000]),
            $letterOfGuaranteeIssuance,
            LetterOfGuaranteeIssuance::HUNDRED_PERCENTAGE_CASH_COVER
        );

        $this->assertSame(__('This LG is not activated yet. Enter its Activation Date first.'), $response->getSession()->get('fail'));
        $this->assertSame(0, DB::table('lg_issuance_advanced_payment_histories')->count());
        $this->nothingPostedFor($letterOfGuaranteeIssuance->id);
    }

    // ---------------------------------------------------------------
    // the model's own rules
    // ---------------------------------------------------------------

    /**
     * @dataProvider activationDateInputProvider
     */
    public function test_the_activation_date_accepts_what_the_forms_and_imports_send(?string $input, ?string $stored): void
    {
        $letterOfGuaranteeIssuance = new LetterOfGuaranteeIssuance;
        $letterOfGuaranteeIssuance->activation_date = $input;

        $this->assertSame($stored, $letterOfGuaranteeIssuance->getActivationDate());
    }

    public static function activationDateInputProvider(): array
    {
        return [
            'the date input (Y-m-d)' => ['2026-04-10', '2026-04-10'],
            'the old picker (m/d/Y)' => ['04/10/2026', '2026-04-10'],
            'left empty' => ['', null],
            'the literal null the forms post' => ['null', null],
            'nothing at all' => [null, null],
        ];
    }

    /**
     * Same numbers the issuance forms show as LG Duration (Months), so
     * the commission the server posts matches what the user saw.
     *
     * @dataProvider monthsBetweenProvider
     */
    public function test_months_between_rounds_up_like_the_form(string $start, string $end, int $months): void
    {
        $this->assertSame($months, LetterOfGuaranteeIssuance::monthsBetweenCeil($start, $end));
    }

    public static function monthsBetweenProvider(): array
    {
        return [
            'a whole year' => ['2026-01-10', '2027-01-10', 12],
            'one day past a whole year' => ['2026-01-10', '2027-01-11', 13],
            'nine whole months' => ['2026-04-10', '2027-01-10', 9],
            'never less than one' => ['2026-04-10', '2026-04-10', 1],
        ];
    }

    /**
     * An Opening Balance LG was outstanding before the company joined, so
     * its movements are already inside the opening balances. An Advance
     * Payment LG activated after that date was not — its movements are
     * new and have to be posted.
     */
    public function test_an_opening_balance_lg_activated_after_the_opening_date_posts_as_new(): void
    {
        $company = (new Company)->forceFill(['id' => self::COMPANY, 'opening_balance_date' => '2026-03-01']);

        $activatedBefore = (new LetterOfGuaranteeIssuance)->forceFill([
            'category_name' => LetterOfGuaranteeIssuance::OPENING_BALANCE,
            'lg_type' => LgTypes::ADVANCED_PAYMENT_LGS,
            'issuance_date' => '2025-11-01',
            'activation_date' => '2026-02-01',
        ])->setRelation('company', $company);
        $activatedAfter = (clone $activatedBefore)->forceFill(['activation_date' => '2026-03-15']);
        $finalLg = (new LetterOfGuaranteeIssuance)->forceFill([
            'category_name' => LetterOfGuaranteeIssuance::OPENING_BALANCE,
            'lg_type' => LgTypes::FINAL_LGS,
            'issuance_date' => '2025-11-01',
        ])->setRelation('company', $company);

        $this->assertTrue($activatedBefore->postsAsOpeningBalance());
        $this->assertFalse($activatedAfter->postsAsOpeningBalance());
        $this->assertTrue($finalLg->postsAsOpeningBalance(), 'Every other type keeps following its Issuance Type.');
    }

    // ---------------------------------------------------------------
    // every funding source and every other LG type
    // ---------------------------------------------------------------

    /**
     * Cash cover moves through a current account. Issued against a CD
     * or a TD, the deposit itself is the cover, so no cash-cover row
     * is posted — fees, commission and the LG balance still are, and
     * still on the Activation Date.
     *
     * @dataProvider fundingSourceProvider
     */
    public function test_every_funding_source_posts_on_the_activation_date(string $source, bool $movesCashCover, array $extra): void
    {
        $letterOfGuaranteeIssuance = $this->issue($extra + [
            'lg_code' => 'LG-'.$source,
            'activation_date' => self::ACTIVATION_DATE,
        ], $source);

        $this->assertSame(self::ACTIVATION_DATE, $letterOfGuaranteeIssuance->getMovementDate());
        $this->assertSame(['2026-04-10', '2026-07-10', '2026-10-10'], $this->commissionDates($letterOfGuaranteeIssuance->id));

        $lgStatements = $this->lgStatements($letterOfGuaranteeIssuance->id);
        $this->assertCount(1, $lgStatements);
        $this->assertSame(self::ACTIVATION_DATE, Carbon::make($lgStatements[0]->date)->format('Y-m-d'));
        $this->assertEquals(100000, $lgStatements[0]->credit);
        $this->assertSame($source, $lgStatements[0]->source);

        // Commission rows are also stored with is_issuance_fees = 1.
        $fees = array_values(array_filter(
            $this->bankRows($letterOfGuaranteeIssuance->id),
            fn ($row) => (int) $row->is_issuance_fees === 1 && ! $row->is_commission_fees
        ));
        $this->assertCount(1, $fees);
        $this->assertEquals(500, $fees[0]->credit);
        $this->assertSame(self::ACTIVATION_DATE, Carbon::make($fees[0]->date)->format('Y-m-d'));

        $cashCoverBank = array_values(array_filter(
            $this->bankRows($letterOfGuaranteeIssuance->id),
            fn ($row) => ! $row->is_commission_fees && ! $row->is_issuance_fees
        ));
        if ($movesCashCover) {
            $this->assertCount(1, $this->cashCoverStatements($letterOfGuaranteeIssuance->id));
            $this->assertEquals(10000, $this->cashCoverStatements($letterOfGuaranteeIssuance->id)[0]->debit);
            $this->assertSame(self::ACTIVATION_DATE, Carbon::make($this->cashCoverStatements($letterOfGuaranteeIssuance->id)[0]->date)->format('Y-m-d'));
            $this->assertCount(1, $cashCoverBank);
            $this->assertEquals(10000, $cashCoverBank[0]->credit);
        } else {
            $this->assertSame([], $this->cashCoverStatements($letterOfGuaranteeIssuance->id));
            $this->assertSame([], $cashCoverBank);
        }
    }

    public static function fundingSourceProvider(): array
    {
        return [
            'an LG facility' => [LetterOfGuaranteeIssuance::LG_FACILITY, true, ['lg_facility_id' => 9]],
            '100% cash cover' => [LetterOfGuaranteeIssuance::HUNDRED_PERCENTAGE_CASH_COVER, true, []],
            'against a certificate of deposit' => [LetterOfGuaranteeIssuance::AGAINST_CD, false, ['cash_cover_deducted_from_account_type' => '28']],
            'against a time deposit' => [LetterOfGuaranteeIssuance::AGAINST_TD, false, ['cash_cover_deducted_from_account_type' => '29']],
        ];
    }

    /**
     * @dataProvider otherLgTypeProvider
     */
    public function test_every_other_lg_type_posts_on_its_issuance_date(string $lgType): void
    {
        $letterOfGuaranteeIssuance = $this->issue([
            'lg_type' => $lgType,
            'lg_code' => 'LG-'.$lgType,
            'activation_date' => self::ACTIVATION_DATE,
        ]);

        $this->assertNull($letterOfGuaranteeIssuance->activation_date);
        $this->assertTrue($letterOfGuaranteeIssuance->is_activated);
        $this->assertSame(self::ISSUANCE_DATE, $letterOfGuaranteeIssuance->getMovementDate());
        $this->assertSame(self::ISSUANCE_DATE, Carbon::make($this->lgStatements($letterOfGuaranteeIssuance->id)[0]->date)->format('Y-m-d'));
        $this->assertSame(['2026-01-10', '2026-04-10', '2026-07-10', '2026-10-10'], $this->commissionDates($letterOfGuaranteeIssuance->id));
    }

    public static function otherLgTypeProvider(): array
    {
        return [
            'bid bond' => [LgTypes::BID_BOND],
            'final' => [LgTypes::FINAL_LGS],
            'performance' => [LgTypes::PERFORMANCE_LG],
        ];
    }

    public function test_annual_commission_is_a_single_row_on_the_activation_date(): void
    {
        $letterOfGuaranteeIssuance = $this->issue([
            'activation_date' => self::ACTIVATION_DATE,
            'lg_commission_interval' => 'annually',
        ]);

        $this->assertSame([self::ACTIVATION_DATE], $this->commissionDates($letterOfGuaranteeIssuance->id));
        $this->assertEquals(self::COMMISSION, DB::table('current_account_bank_statements')->where('is_commission_fees', 1)->value('credit'));
    }

    // ---------------------------------------------------------------
    // what stays reserved, and what the screens are told
    // ---------------------------------------------------------------

    public function test_only_an_unactivated_lg_is_reserved_against_the_limit(): void
    {
        $this->issue(['lg_amount' => 100000]);
        $this->issue(['lg_code' => 'LG-ON', 'lg_amount' => 50000, 'activation_date' => self::ACTIVATION_DATE]);
        $cancelled = $this->issue(['lg_code' => 'LG-OFF', 'lg_amount' => 70000]);
        $cancelled->update(['status' => LetterOfGuaranteeIssuance::CANCELLED]);
        $this->issue(['lg_code' => 'LG-FIN', 'lg_type' => LgTypes::FINAL_LGS, 'lg_amount' => 90000]);

        $this->assertEquals(100000, LetterOfGuaranteeIssuance::reservedForUnactivated(
            self::COMPANY,
            'EGP',
            self::BANK,
            LetterOfGuaranteeIssuance::HUNDRED_PERCENTAGE_CASH_COVER
        ));
        $this->assertEquals(0, LetterOfGuaranteeIssuance::reservedForUnactivated(
            self::COMPANY,
            'USD',
            self::BANK,
            LetterOfGuaranteeIssuance::HUNDRED_PERCENTAGE_CASH_COVER
        ));
    }

    public function test_the_index_row_carries_the_movement_date_the_modals_cannot_go_before(): void
    {
        $this->issue();
        $this->issue(['lg_code' => 'LG-ON', 'activation_date' => self::ACTIVATION_DATE]);

        $method = new ReflectionMethod(LetterOfGuaranteeIssuanceController::class, 'buildTabRows');
        $method->setAccessible(true);
        $tab = $method->invoke(
            new LetterOfGuaranteeIssuanceController,
            new LengthAwarePaginator(LetterOfGuaranteeIssuance::query()->orderBy('id')->get()->all(), 2, 15),
            Company::findOrFail(self::COMPANY)
        );

        $byCode = collect($tab['rows'])->keyBy('lg_code');
        $this->assertNull($byCode['LG-ADV-1']['movement_date']);
        $this->assertFalse($byCode['LG-ADV-1']['is_activated']);
        $this->assertSame(self::ACTIVATION_DATE, $byCode['LG-ON']['movement_date']);
        $this->assertTrue($byCode['LG-ON']['is_activated']);
    }

    public function test_the_renewal_page_shows_the_activation_date_for_an_advance_payment_lg(): void
    {
        $activated = $this->issue(['activation_date' => self::ACTIVATION_DATE]);
        $final = $this->issue(['lg_code' => 'LG-FIN', 'lg_type' => LgTypes::FINAL_LGS]);

        $activatedPage = $this->renewalPage($activated);
        $this->assertSame('10-04-2026', $activatedPage['letterOfGuaranteeIssuance']['activation_date_formatted']);

        $finalPage = $this->renewalPage($final);
        $this->assertNull($finalPage['letterOfGuaranteeIssuance']['activation_date_formatted']);
    }

    public function test_the_renewal_form_is_shown_before_the_current_expiry_when_the_lg_is_activated(): void
    {
        $activated = $this->issue(['activation_date' => self::ACTIVATION_DATE]);

        $this->assertFalse($activated->isExpired());
        $this->assertTrue($this->renewalPage($activated)['canShowForm']);
    }

    public function test_the_renewal_form_is_hidden_until_an_advance_payment_lg_is_activated(): void
    {
        $unactivated = $this->issue();

        $this->assertFalse($unactivated->isExpired());
        $this->assertFalse($this->renewalPage($unactivated)['canShowForm']);
    }

    public function test_the_renewal_form_is_hidden_for_a_cancelled_lg(): void
    {
        $letterOfGuaranteeIssuance = $this->issue(['activation_date' => self::ACTIVATION_DATE]);
        $letterOfGuaranteeIssuance->update(['status' => LetterOfGuaranteeIssuance::CANCELLED]);

        $this->assertFalse($this->renewalPage($letterOfGuaranteeIssuance->fresh())['canShowForm']);
    }

    // ---------------------------------------------------------------
    // cancellation
    // ---------------------------------------------------------------

    public function test_a_cancellation_dated_before_the_movement_date_is_refused(): void
    {
        $letterOfGuaranteeIssuance = $this->issue(['activation_date' => self::ACTIVATION_DATE]);

        $response = $this->cancel($letterOfGuaranteeIssuance, '2026-04-09');

        $this->assertSame(__('This date cannot be before :date.', ['date' => '10-04-2026']), $response->getSession()->get('fail'));
        $letterOfGuaranteeIssuance->refresh();
        $this->assertNotSame(LetterOfGuaranteeIssuance::CANCELLED, $letterOfGuaranteeIssuance->status);
        $this->assertNull($letterOfGuaranteeIssuance->cancellation_date);
        $this->assertSame(0, DB::table('letter_of_guarantee_statements')->where('type', LetterOfGuaranteeIssuance::FOR_CANCELLATION)->count());
    }

    public function test_a_final_lg_cannot_be_cancelled_before_its_issuance_date(): void
    {
        $letterOfGuaranteeIssuance = $this->issue(['lg_type' => LgTypes::FINAL_LGS, 'lg_code' => 'LG-FIN']);

        $response = $this->cancel($letterOfGuaranteeIssuance, '2026-01-09');

        $this->assertSame(__('This date cannot be before :date.', ['date' => '10-01-2026']), $response->getSession()->get('fail'));
        $this->assertNotSame(LetterOfGuaranteeIssuance::CANCELLED, $letterOfGuaranteeIssuance->refresh()->status);
    }

    public function test_cancelling_an_activated_lg_closes_it_on_the_cancellation_date(): void
    {
        $letterOfGuaranteeIssuance = $this->issue(['activation_date' => self::ACTIVATION_DATE]);

        $this->cancel($letterOfGuaranteeIssuance, self::ACTIVATION_DATE);

        $letterOfGuaranteeIssuance->refresh();
        $this->assertSame(LetterOfGuaranteeIssuance::CANCELLED, $letterOfGuaranteeIssuance->status);
        $this->assertSame(self::ACTIVATION_DATE, Carbon::make($letterOfGuaranteeIssuance->cancellation_date)->format('Y-m-d'));

        $closing = DB::table('letter_of_guarantee_statements')->where('type', LetterOfGuaranteeIssuance::FOR_CANCELLATION)->get();
        $this->assertCount(1, $closing);
        $this->assertEquals(100000, $closing[0]->debit);
        $this->assertSame(self::ACTIVATION_DATE, Carbon::make($closing[0]->date)->format('Y-m-d'));
        $this->assertSame(1, DB::table('letter_of_guarantee_statements')->where('type', 'credit-lg-amount')->count(), 'The original balance stays, closed by the cancellation row.');

        $cover = DB::table('letter_of_guarantee_cash_cover_statements')->where('type', LetterOfGuaranteeIssuance::FOR_CANCELLATION)->get();
        $this->assertCount(1, $cover);
        $this->assertEquals(10000, $cover[0]->credit);

        $refunds = DB::table('current_account_bank_statements')->where('debit', '>', 0)->get();
        $this->assertCount(1, $refunds);
        $this->assertEquals(10000, $refunds[0]->debit);
        $this->assertSame(self::ACTIVATION_DATE, Carbon::make($refunds[0]->date)->format('Y-m-d'));
    }

    public function test_a_cancelled_index_row_carries_the_cancellation_date(): void
    {
        $this->issue(['lg_code' => 'LG-RUN']);
        $cancelled = $this->issue(['lg_code' => 'LG-OFF', 'activation_date' => self::ACTIVATION_DATE]);
        $this->cancel($cancelled, '2026-06-01');

        $method = new ReflectionMethod(LetterOfGuaranteeIssuanceController::class, 'buildTabRows');
        $method->setAccessible(true);
        $tab = $method->invoke(
            new LetterOfGuaranteeIssuanceController,
            new LengthAwarePaginator(LetterOfGuaranteeIssuance::query()->orderBy('id')->get()->all(), 2, 15),
            Company::findOrFail(self::COMPANY)
        );

        $byCode = collect($tab['rows'])->keyBy('lg_code');
        $this->assertFalse($byCode['LG-RUN']['is_cancelled']);
        $this->assertNull($byCode['LG-RUN']['cancellation_date_formatted']);
        $this->assertTrue($byCode['LG-OFF']['is_cancelled']);
        $this->assertSame('01-06-2026', $byCode['LG-OFF']['cancellation_date_formatted']);
    }

    public function test_the_index_vue_shows_the_cancellation_date_for_cancelled_rows(): void
    {
        $vue = file_get_contents(resource_path('js/Pages/LetterOfGuaranteeIssuance/Index.vue'));

        $this->assertStringContainsString('v-if="row.is_cancelled"', $vue);
        $this->assertStringContainsString('openCancellationDate(row)', $vue);
        $this->assertStringContainsString('cancellationDateTarget.cancellation_date_formatted', $vue);
        $this->assertStringContainsString('$t(\'Cancellation Date\')', $vue);
    }

    public function test_setting_a_cancelled_lg_back_to_running_restores_the_cash_cover(): void
    {
        $letterOfGuaranteeIssuance = $this->issue(['activation_date' => self::ACTIVATION_DATE]);
        $this->cancel($letterOfGuaranteeIssuance, '2026-06-01');
        $this->applyStatementDirectionFlags();

        (new LetterOfGuaranteeIssuanceController)->backToRunningStatus(
            Company::findOrFail(self::COMPANY),
            Request::create('/', 'POST'),
            $letterOfGuaranteeIssuance->refresh(),
            LetterOfGuaranteeIssuance::HUNDRED_PERCENTAGE_CASH_COVER
        );

        $letterOfGuaranteeIssuance->refresh();
        $this->assertSame(LetterOfGuaranteeIssuance::RUNNING, $letterOfGuaranteeIssuance->status);
        $this->assertNull($letterOfGuaranteeIssuance->cancellation_date);
        $this->assertSame(0, DB::table('letter_of_guarantee_statements')->where('type', LetterOfGuaranteeIssuance::FOR_CANCELLATION)->count());
        $this->assertSame(0, DB::table('current_account_bank_statements')->where('debit', '>', 0)->count(), 'The cash-cover refund is taken back.');

        $cover = DB::table('letter_of_guarantee_cash_cover_statements')->where('type', 'debit-lg-amount')->get();
        $this->assertCount(1, $cover);
        $this->assertEquals(10000, $cover[0]->debit);
        $this->assertSame(self::ACTIVATION_DATE, Carbon::make($cover[0]->date)->format('Y-m-d'));
    }

    // ---------------------------------------------------------------
    // amount to be decreased
    // ---------------------------------------------------------------

    public function test_a_decrease_dated_before_the_movement_date_is_refused(): void
    {
        $letterOfGuaranteeIssuance = $this->issue(['activation_date' => self::ACTIVATION_DATE]);

        $response = $this->decrease($letterOfGuaranteeIssuance, '2026-04-09', 40000);

        $this->assertSame(__('This date cannot be before :date.', ['date' => '10-04-2026']), $response->getSession()->get('fail'));
        $this->assertSame(0, DB::table('lg_issuance_advanced_payment_histories')->count());
        $this->assertSame(0, DB::table('letter_of_guarantee_statements')->where('type', LetterOfGuaranteeIssuance::AMOUNT_TO_BE_DECREASED)->count());
    }

    public function test_decreasing_an_activated_lg_posts_the_reduction_on_that_date(): void
    {
        $letterOfGuaranteeIssuance = $this->issue(['activation_date' => self::ACTIVATION_DATE]);

        $this->decrease($letterOfGuaranteeIssuance, self::ACTIVATION_DATE, 40000);

        $this->assertEquals(60000, $letterOfGuaranteeIssuance->fresh()->getLgCurrentAmount());

        $reduction = DB::table('letter_of_guarantee_statements')->where('type', LetterOfGuaranteeIssuance::AMOUNT_TO_BE_DECREASED)->get();
        $this->assertCount(1, $reduction);
        $this->assertEquals(40000, $reduction[0]->debit);
        $this->assertSame(self::ACTIVATION_DATE, Carbon::make($reduction[0]->date)->format('Y-m-d'));

        $cover = DB::table('letter_of_guarantee_cash_cover_statements')->where('type', LetterOfGuaranteeIssuance::AMOUNT_TO_BE_DECREASED)->get();
        $this->assertCount(1, $cover);
        $this->assertEquals(4000, $cover[0]->credit);

        $refunds = DB::table('current_account_bank_statements')->where('debit', '>', 0)->get();
        $this->assertCount(1, $refunds);
        $this->assertEquals(4000, $refunds[0]->debit);
        $this->assertSame(self::ACTIVATION_DATE, Carbon::make($refunds[0]->date)->format('Y-m-d'));
    }

    public function test_editing_a_decrease_moves_its_date_and_amount(): void
    {
        $letterOfGuaranteeIssuance = $this->issue(['activation_date' => self::ACTIVATION_DATE]);
        $this->decrease($letterOfGuaranteeIssuance, self::ACTIVATION_DATE, 40000);
        $this->applyStatementDirectionFlags();
        $history = LetterOfGuaranteeIssuanceAdvancedPaymentHistory::query()->firstOrFail();

        (new LetterOfGuaranteeIssuanceController)->editAmountToBeDecreased(
            Company::findOrFail(self::COMPANY),
            Request::create('/', 'POST', ['decrease_date' => '2026-05-01', 'amount_to_be_decreased' => 25000]),
            $history,
            LetterOfGuaranteeIssuance::HUNDRED_PERCENTAGE_CASH_COVER
        );

        $this->assertEquals(75000, $letterOfGuaranteeIssuance->fresh()->getLgCurrentAmount());
        $reduction = DB::table('letter_of_guarantee_statements')->where('type', LetterOfGuaranteeIssuance::AMOUNT_TO_BE_DECREASED)->first();
        $this->assertEquals(25000, $reduction->debit);
        $this->assertSame('2026-05-01', Carbon::make($reduction->date)->format('Y-m-d'));

        $cover = DB::table('letter_of_guarantee_cash_cover_statements')->where('type', LetterOfGuaranteeIssuance::AMOUNT_TO_BE_DECREASED)->first();
        $this->assertEquals(2500, $cover->credit);
        $this->assertSame('2026-05-01', Carbon::make($cover->date)->format('Y-m-d'));

        $refund = DB::table('current_account_bank_statements')->where('lg_advanced_payment_history_id', $history->id)->where('debit', '>', 0)->first();
        $this->assertEquals(2500, $refund->debit);
        $this->assertSame('2026-05-01', Carbon::make($refund->date)->format('Y-m-d'));
    }

    public function test_editing_a_decrease_to_before_the_movement_date_is_refused(): void
    {
        $letterOfGuaranteeIssuance = $this->issue(['activation_date' => self::ACTIVATION_DATE]);
        $this->decrease($letterOfGuaranteeIssuance, self::ACTIVATION_DATE, 40000);
        $history = LetterOfGuaranteeIssuanceAdvancedPaymentHistory::query()->firstOrFail();

        $response = (new LetterOfGuaranteeIssuanceController)->editAmountToBeDecreased(
            Company::findOrFail(self::COMPANY),
            Request::create('/', 'POST', ['decrease_date' => '2026-04-09', 'amount_to_be_decreased' => 25000]),
            $history,
            LetterOfGuaranteeIssuance::HUNDRED_PERCENTAGE_CASH_COVER
        );

        $this->assertSame(__('This date cannot be before :date.', ['date' => '10-04-2026']), $response->getSession()->get('fail'));
        $this->assertEquals(40000, DB::table('letter_of_guarantee_statements')->where('type', LetterOfGuaranteeIssuance::AMOUNT_TO_BE_DECREASED)->value('debit'));
    }

    public function test_deleting_a_decrease_removes_only_what_that_decrease_posted(): void
    {
        $letterOfGuaranteeIssuance = $this->issue(['activation_date' => self::ACTIVATION_DATE]);
        $this->decrease($letterOfGuaranteeIssuance, self::ACTIVATION_DATE, 40000);
        $history = LetterOfGuaranteeIssuanceAdvancedPaymentHistory::query()->firstOrFail();

        (new LetterOfGuaranteeIssuanceController)->deleteAdvancedPayment(
            Company::findOrFail(self::COMPANY),
            Request::create('/', 'GET'),
            $history
        );

        $this->assertSame(0, DB::table('lg_issuance_advanced_payment_histories')->count());
        $this->assertSame(0, DB::table('letter_of_guarantee_statements')->where('type', LetterOfGuaranteeIssuance::AMOUNT_TO_BE_DECREASED)->count());
        $this->assertSame(0, DB::table('letter_of_guarantee_cash_cover_statements')->where('type', LetterOfGuaranteeIssuance::AMOUNT_TO_BE_DECREASED)->count());
        $this->assertSame(0, DB::table('current_account_bank_statements')->where('debit', '>', 0)->count());
        $this->assertSame(1, DB::table('letter_of_guarantee_statements')->where('type', 'credit-lg-amount')->count());
        $this->assertSame(['2026-04-10', '2026-07-10', '2026-10-10'], $this->commissionDates($letterOfGuaranteeIssuance->id));
        $this->assertEquals(100000, $letterOfGuaranteeIssuance->fresh()->getLgCurrentAmount());
    }

    // ---------------------------------------------------------------
    // opening balance, and renewal of an activated LG
    // ---------------------------------------------------------------

    public function test_an_opening_balance_lg_activated_before_the_opening_date_posts_no_cash_cover_or_fees(): void
    {
        DB::table('companies')->where('id', self::COMPANY)->update(['opening_balance_date' => '2026-03-01']);

        $letterOfGuaranteeIssuance = $this->issue([
            'category_name' => LetterOfGuaranteeIssuance::OPENING_BALANCE,
            'activation_date' => '2026-02-01',
            'issuance_date' => '2025-11-01',
            'renewal_date' => '2026-11-01',
        ]);

        $this->assertTrue($letterOfGuaranteeIssuance->postsAsOpeningBalance());
        $this->assertSame([], $this->cashCoverStatements($letterOfGuaranteeIssuance->id));
        $this->assertSame(0, DB::table('current_account_bank_statements')->where('is_issuance_fees', 1)->where('is_commission_fees', 0)->count());
        $this->assertSame([], array_values(array_filter(
            $this->bankRows($letterOfGuaranteeIssuance->id),
            fn ($row) => ! $row->is_commission_fees && ! $row->is_issuance_fees
        )));
        $this->assertSame('2026-02-01', Carbon::make($this->lgStatements($letterOfGuaranteeIssuance->id)[0]->date)->format('Y-m-d'));
    }

    public function test_an_opening_balance_lg_activated_after_the_opening_date_posts_like_a_new_issuance(): void
    {
        DB::table('companies')->where('id', self::COMPANY)->update(['opening_balance_date' => '2026-03-01']);

        $letterOfGuaranteeIssuance = $this->issue([
            'category_name' => LetterOfGuaranteeIssuance::OPENING_BALANCE,
            'activation_date' => self::ACTIVATION_DATE,
            'issuance_date' => '2025-11-01',
            'renewal_date' => self::RENEWAL_DATE,
        ]);

        $this->assertFalse($letterOfGuaranteeIssuance->postsAsOpeningBalance());
        $this->assertCount(1, $this->cashCoverStatements($letterOfGuaranteeIssuance->id));
        $this->assertSame(self::ACTIVATION_DATE, Carbon::make($this->cashCoverStatements($letterOfGuaranteeIssuance->id)[0]->date)->format('Y-m-d'));
        $this->assertEquals(10000, $this->cashCoverStatements($letterOfGuaranteeIssuance->id)[0]->debit);
        $this->assertSame(1, DB::table('current_account_bank_statements')->where('is_issuance_fees', 1)->where('is_commission_fees', 0)->count());
        $this->assertSame(['2026-04-10', '2026-07-10', '2026-10-10'], $this->commissionDates($letterOfGuaranteeIssuance->id));
    }

    public function test_renewing_an_activated_lg_posts_the_new_term_from_the_old_expiry(): void
    {
        $letterOfGuaranteeIssuance = $this->issue(['activation_date' => self::ACTIVATION_DATE]);
        $this->assertFalse($letterOfGuaranteeIssuance->isExpired());

        (new LetterOfGuaranteeIssuanceRenewalDateController)->store(
            StoreLgRenewalDateRequest::create('/', 'POST', [
                'renewal_date' => '01/01/2028',
                'expiry_date' => self::RENEWAL_DATE,
                'fees_amount' => '300',
            ]),
            Company::findOrFail(self::COMPANY),
            $letterOfGuaranteeIssuance
        );

        $letterOfGuaranteeIssuance->refresh();
        $this->assertSame('2028-01-01', Carbon::make($letterOfGuaranteeIssuance->renewal_date)->format('Y-m-d'));

        $renewalFees = DB::table('current_account_bank_statements')->where('is_renewal_fees', 1)->get();
        $this->assertCount(1, $renewalFees);
        $this->assertEquals(300, $renewalFees[0]->credit);
        $this->assertSame(self::RENEWAL_DATE, Carbon::make($renewalFees[0]->date)->format('Y-m-d'));

        $originalTerm = DB::table('current_account_bank_statements')
            ->where('is_commission_fees', 1)
            ->whereNull('lg_renewal_date_history_id')
            ->orderBy('date')
            ->pluck('date')
            ->map(fn ($date) => Carbon::make($date)->format('Y-m-d'))
            ->all();
        $newTerm = DB::table('current_account_bank_statements')
            ->where('is_commission_fees', 1)
            ->whereNotNull('lg_renewal_date_history_id')
            ->orderBy('date')
            ->pluck('date')
            ->map(fn ($date) => Carbon::make($date)->format('Y-m-d'))
            ->all();

        $this->assertSame(['2026-04-10', '2026-07-10', '2026-10-10'], $originalTerm);
        $this->assertSame(['2027-01-10', '2027-04-10', '2027-07-10', '2027-10-10'], $newTerm);
    }

    // ---------------------------------------------------------------
    // helpers for the movements above
    // ---------------------------------------------------------------

    private function cancel(LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance, string $date)
    {
        return (new LetterOfGuaranteeIssuanceController)->cancel(
            Company::findOrFail(self::COMPANY),
            Request::create('/', 'POST', ['cancellation_date' => $date]),
            $letterOfGuaranteeIssuance,
            LetterOfGuaranteeIssuance::HUNDRED_PERCENTAGE_CASH_COVER
        );
    }

    private function decrease(LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance, string $date, float $amount)
    {
        return (new LetterOfGuaranteeIssuanceController)->applyAmountToBeDecreased(
            Company::findOrFail(self::COMPANY),
            Request::create('/', 'POST', ['date' => $date, 'amount' => $amount]),
            $letterOfGuaranteeIssuance,
            LetterOfGuaranteeIssuance::HUNDRED_PERCENTAGE_CASH_COVER
        );
    }

    private function renewalPage(LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance): array
    {
        $response = (new LetterOfGuaranteeIssuanceRenewalDateController)->index(
            Company::findOrFail(self::COMPANY),
            Request::create('/', 'GET', [], [], [], ['HTTP_X_INERTIA' => 'true']),
            $letterOfGuaranteeIssuance
        );

        $http = $response->toResponse(Request::create('/', 'GET', [], [], [], ['HTTP_X_INERTIA' => 'true']));

        return json_decode($http->getContent(), true)['props'];
    }
}
