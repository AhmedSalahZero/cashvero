<?php

namespace Tests\Feature\LetterOfGuarantee;

use App\Http\Controllers\LetterOfGuaranteeIssuanceRenewalDateController;
use App\Http\Requests\StoreLgRenewalDateRequest;
use App\Models\Company;
use App\Models\LetterOfGuaranteeIssuance;
use App\Models\LgRenewalDateHistory;
use App\Support\LetterOfGuarantee\LgRenewalTerms;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * A bank re-prices an LG when it renews it — it can ask for a bigger
 * cash cover and charge a different commission from the renewal
 * onwards.
 *
 * Two numbers have to be right, and they are right for different
 * reasons:
 *
 *   BEFORE the renewal — everything already posted for the periods
 *     that came before is history. The cover that was blocked then,
 *     the commissions that were charged then, do not move because the
 *     bank changed its mind today.
 *
 *   AFTER the renewal — cash cover is a running BALANCE, so raising
 *     it from 10,000 to 20,000 moves 10,000 more, not 20,000 more,
 *     and it moves on the day the new period starts. Commission is a
 *     per-period CHARGE, so every charge in the new period is the new
 *     amount, in full.
 *
 * The whole scenario below is one LG: issued 01-01-2025 for 100,000
 * with a 10,000 cover and a 500 quarterly commission, expiring
 * 01-01-2026, then renewed for another year on terms the bank changed.
 *
 * @see \App\Support\LetterOfGuarantee\LgRenewalTerms
 * @see \App\Http\Controllers\LetterOfGuaranteeIssuanceRenewalDateController
 */
class LgRenewalTermsTest extends TestCase
{
    use LgSchemaFixture;

    private const COMPANY = 146;

    private const ACCOUNT = 334;

    private const ISSUANCE = 672;

    /** The terms the LG was issued on. */
    private const ISSUANCE_DATE = '2025-01-01';

    private const ORIGINAL_EXPIRY = '2026-01-01';

    private const NEW_EXPIRY = '2027-01-01';

    private const LG_AMOUNT = 100000;

    private const ORIGINAL_CASH_COVER = 10000;

    private const ORIGINAL_COMMISSION = 500;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertOnTestDatabase();
        $this->createLgSchema();

        /**
         * Frozen so the is_active flag the commission rows are written
         * with (now() >= the row's own date) is the same on every run —
         * the last quarter of the renewed period, 01-10-2026, is still
         * in the future here and must be posted inactive.
         */
        Carbon::setTestNow('2026-08-25');

        DB::table('companies')->insert(['id' => self::COMPANY]);
        DB::table('financial_institution_accounts')->insert([
            'id' => self::ACCOUNT,
            'company_id' => self::COMPANY,
            'account_number' => '111222333',
            'currency' => 'EGP',
            'balance_date' => '2024-12-31',
            'synced_end_of_month_years' => json_encode(['2025', '2026', '2027']),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        $this->dropLgSchema();

        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // fixtures
    // ---------------------------------------------------------------

    /**
     * The LG as it stands the moment before it is renewed: priced on
     * its original terms, with the cover and the four quarterly
     * commissions of its first year already posted.
     */
    private function issueLg(array $overrides = []): LetterOfGuaranteeIssuance
    {
        DB::table('letter_of_guarantee_issuances')->insert($overrides + [
            'id' => self::ISSUANCE,
            'company_id' => self::COMPANY,
            'source' => LetterOfGuaranteeIssuance::LG_FACILITY,
            'status' => LetterOfGuaranteeIssuance::RUNNING,
            'category_name' => LetterOfGuaranteeIssuance::NEW_ISSUANCE,
            'lg_type' => 'final-lgs',
            'transaction_name' => 'sce zone',
            'lg_code' => 'LG-1',
            'lg_facility_id' => 97,
            'issuance_date' => self::ISSUANCE_DATE,
            'renewal_date' => self::ORIGINAL_EXPIRY,
            'lg_duration_months' => 12,
            'lg_amount' => self::LG_AMOUNT,
            'lg_currency' => 'EGP',
            'cash_cover_rate' => 10,
            'cash_cover_amount' => self::ORIGINAL_CASH_COVER,
            // 27 = a current account. Anything in [28, 29] would be a
            // CD/TD, where cash cover never moves through a bank
            // account at all — covered by its own test below.
            'cash_cover_deducted_from_account_type' => '27',
            'cash_cover_deducted_from_account_id' => self::ACCOUNT,
            'lg_fees_and_commission_account_id' => self::ACCOUNT,
            'lg_commission_interval' => 'quarterly',
            'lg_commission_amount' => self::ORIGINAL_COMMISSION,
            'min_lg_commission_fees' => 0,
        ]);

        $this->postOriginalCashCover();
        $this->postOriginalCommissions();

        return LetterOfGuaranteeIssuance::findOrFail(self::ISSUANCE);
    }

    private function postOriginalCashCover(): void
    {
        DB::table('letter_of_guarantee_cash_cover_statements')->insert([
            'type' => 'debit-lg-amount',
            'source' => LetterOfGuaranteeIssuance::LG_FACILITY,
            'letter_of_guarantee_issuance_id' => self::ISSUANCE,
            'lg_type' => 'final-lgs',
            'currency' => 'EGP',
            'company_id' => self::COMPANY,
            'date' => self::ISSUANCE_DATE,
            'full_date' => self::ISSUANCE_DATE.' 10:00:00',
            'debit' => self::ORIGINAL_CASH_COVER,
        ]);

        DB::table('current_account_bank_statements')->insert([
            'company_id' => self::COMPANY,
            'financial_institution_account_id' => self::ACCOUNT,
            'letter_of_guarantee_issuance_id' => self::ISSUANCE,
            'is_credit' => 1,
            'credit' => self::ORIGINAL_CASH_COVER,
            'date' => self::ISSUANCE_DATE,
            'full_date' => self::ISSUANCE_DATE.' 10:00:00',
            'comment_en' => 'Cash Cover [ N/A ] [ final-lgs ]',
        ]);
    }

    private function postOriginalCommissions(): void
    {
        foreach (['2025-01-01', '2025-04-01', '2025-07-01', '2025-10-01'] as $date) {
            DB::table('current_account_bank_statements')->insert([
                'company_id' => self::COMPANY,
                'financial_institution_account_id' => self::ACCOUNT,
                'letter_of_guarantee_issuance_id' => self::ISSUANCE,
                'is_credit' => 1,
                'is_commission_fees' => 1,
                'credit' => self::ORIGINAL_COMMISSION,
                'date' => $date,
                'full_date' => $date.' 10:00:00',
            ]);
        }
    }

    /**
     * The renewal form's payload. renewal_date goes out MM/DD/YYYY
     * because that is what the controller parses — see its docblock.
     */
    private function renewalPayload(array $overrides = []): array
    {
        return $overrides + [
            'renewal_date' => '01/01/2027',
            'expiry_date' => self::ORIGINAL_EXPIRY,
            'fees_amount' => '300',
            'cash_cover_amount' => '20,000',
            'lg_commission_amount' => '800',
            'min_lg_commission_fees' => '0',
        ];
    }

    private function renew(LetterOfGuaranteeIssuance $letterOfGuaranteeIssuance, array $payload = []): LgRenewalDateHistory
    {
        (new LetterOfGuaranteeIssuanceRenewalDateController)->store(
            StoreLgRenewalDateRequest::create('/', 'POST', $this->renewalPayload($payload)),
            Company::findOrFail(self::COMPANY),
            $letterOfGuaranteeIssuance
        );

        return $this->lastRenewal();
    }

    private function lastRenewal(): LgRenewalDateHistory
    {
        return LgRenewalDateHistory::where('letter_of_guarantee_issuance_id', self::ISSUANCE)
            ->orderByDesc('id')
            ->firstOrFail();
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    private function cashCoverRows(?string $type = null)
    {
        return DB::table('letter_of_guarantee_cash_cover_statements')
            ->where('letter_of_guarantee_issuance_id', self::ISSUANCE)
            ->when($type, fn ($q) => $q->where('type', $type))
            ->orderBy('date')
            ->get();
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    private function bankRows(string $flag)
    {
        return DB::table('current_account_bank_statements')
            ->where('letter_of_guarantee_issuance_id', self::ISSUANCE)
            ->where($flag, 1)
            ->orderBy('date')
            ->get();
    }

    /** @return array<string, float> date => amount */
    private function commissionsByDate()
    {
        return $this->bankRows('is_commission_fees')
            ->mapWithKeys(fn ($row) => [$row->date => (float) $row->credit])
            ->all();
    }

    // ---------------------------------------------------------------
    // after the renewal — the new numbers
    // ---------------------------------------------------------------

    /**
     * The heart of it: 10,000 became 20,000, so 10,000 moves — not
     * 20,000 — and it moves on 01-01-2026, the day the renewed period
     * starts, which is the same day the renewal fee is charged.
     */
    public function test_it_posts_only_the_cash_cover_difference_at_the_start_of_the_new_period(): void
    {
        $letterOfGuaranteeIssuance = $this->issueLg();

        $renewal = $this->renew($letterOfGuaranteeIssuance);

        $difference = $this->cashCoverRows(LgRenewalTerms::CASH_COVER_TYPE);
        $this->assertCount(1, $difference);
        $this->assertEquals(10000, $difference->first()->debit);
        $this->assertEquals(0, $difference->first()->credit);
        $this->assertSame(self::ORIGINAL_EXPIRY, $difference->first()->date);
        $this->assertEquals($renewal->id, $difference->first()->lg_renewal_date_history_id);

        $bank = $this->bankRows('is_renewal_cash_cover');
        $this->assertCount(1, $bank);
        $this->assertEquals(10000, $bank->first()->credit);
        $this->assertEquals(0, $bank->first()->debit);
        $this->assertSame(self::ORIGINAL_EXPIRY, $bank->first()->date);
        $this->assertEquals($renewal->id, $bank->first()->lg_renewal_date_history_id);
    }

    /**
     * The issuance itself carries the CURRENT terms, so everything
     * downstream — the edit form, the dashboards, a cancellation
     * refund — follows the renewal without knowing renewals exist.
     */
    public function test_the_issuance_ends_up_carrying_the_new_terms(): void
    {
        $letterOfGuaranteeIssuance = $this->issueLg();

        $renewal = $this->renew($letterOfGuaranteeIssuance);

        $letterOfGuaranteeIssuance->refresh();
        $this->assertEquals(20000, $letterOfGuaranteeIssuance->getCashCoverAmount());
        $this->assertEquals(800, $letterOfGuaranteeIssuance->getLgCommissionAmount());
        // 20,000 of a 100,000 LG. The rate has to follow the amount or
        // an Advance Payment LG refunds the wrong money on cancellation.
        $this->assertEquals(20, $letterOfGuaranteeIssuance->getCashCoverRate());
        $this->assertSame(self::NEW_EXPIRY, $letterOfGuaranteeIssuance->getRenewalDate());

        $this->assertEquals(20000, $renewal->getCashCoverAmount());
        $this->assertEquals(10000, $renewal->getPreviousCashCoverAmount());
        $this->assertEquals(10000, $renewal->getCashCoverDifference());
    }

    /**
     * Commission is a per-period charge, so every quarter of the
     * renewed year is the NEW 800 in full — there is no "difference"
     * to take here, unlike the cover.
     */
    public function test_every_commission_in_the_new_period_is_the_new_amount(): void
    {
        $letterOfGuaranteeIssuance = $this->issueLg();

        $this->renew($letterOfGuaranteeIssuance);

        $commissions = $this->commissionsByDate();
        $this->assertSame([
            '2025-01-01' => 500.0,
            '2025-04-01' => 500.0,
            '2025-07-01' => 500.0,
            '2025-10-01' => 500.0,
            '2026-01-01' => 800.0,
            '2026-04-01' => 800.0,
            '2026-07-01' => 800.0,
            '2026-10-01' => 800.0,
        ], $commissions);
    }

    /**
     * The bank charges whichever is bigger — the calculated commission
     * or its floor. A renewal that raises only the floor still has to
     * move the charge.
     */
    public function test_the_minimum_commission_still_wins_when_it_is_higher(): void
    {
        $letterOfGuaranteeIssuance = $this->issueLg();

        $this->renew($letterOfGuaranteeIssuance, [
            'lg_commission_amount' => '100',
            'min_lg_commission_fees' => '900',
        ]);

        $this->assertSame(900.0, $this->commissionsByDate()['2026-01-01']);
        $this->assertEquals(900, $letterOfGuaranteeIssuance->refresh()->getMinLgCommissionFees());
    }

    /**
     * The renewal fee is unrelated to the cover, and must not be
     * confused with it: it is a fee (it belongs in the Commission &
     * Fees reports), the cover is a blocked balance (it does not).
     */
    public function test_the_renewal_fee_is_still_charged_on_its_own_row(): void
    {
        $letterOfGuaranteeIssuance = $this->issueLg();

        $this->renew($letterOfGuaranteeIssuance);

        $fees = $this->bankRows('is_renewal_fees');
        $this->assertCount(1, $fees);
        $this->assertEquals(300, $fees->first()->credit);
        $this->assertSame(self::ORIGINAL_EXPIRY, $fees->first()->date);
        $this->assertEquals(0, $fees->first()->is_renewal_cash_cover);
    }

    /**
     * A bank can also RELEASE cover at renewal. The difference comes
     * back the same way a cancellation refunds it: a credit on the LG
     * ledger, a debit on the current account.
     */
    public function test_a_lower_cash_cover_refunds_the_difference(): void
    {
        $letterOfGuaranteeIssuance = $this->issueLg();

        $this->renew($letterOfGuaranteeIssuance, ['cash_cover_amount' => '4,000']);

        $difference = $this->cashCoverRows(LgRenewalTerms::CASH_COVER_TYPE);
        $this->assertCount(1, $difference);
        $this->assertEquals(6000, $difference->first()->credit);
        $this->assertEquals(0, $difference->first()->debit);

        $bank = $this->bankRows('is_renewal_cash_cover');
        $this->assertEquals(6000, $bank->first()->debit);
        $this->assertEquals(0, $bank->first()->credit);

        $this->assertEquals(4000, $letterOfGuaranteeIssuance->refresh()->getCashCoverAmount());
    }

    public function test_an_unchanged_cash_cover_moves_no_money(): void
    {
        $letterOfGuaranteeIssuance = $this->issueLg();

        $this->renew($letterOfGuaranteeIssuance, ['cash_cover_amount' => '10,000']);

        $this->assertCount(0, $this->cashCoverRows(LgRenewalTerms::CASH_COVER_TYPE));
        $this->assertCount(0, $this->bankRows('is_renewal_cash_cover'));
        $this->assertEquals(10000, $letterOfGuaranteeIssuance->refresh()->getCashCoverAmount());
    }

    // ---------------------------------------------------------------
    // before the renewal — the old numbers stay put
    // ---------------------------------------------------------------

    /**
     * The cover blocked in 2025 and the commissions charged in 2025
     * are history. Re-pricing the LG in 2026 does not reach back.
     */
    public function test_everything_posted_before_the_renewal_is_left_alone(): void
    {
        $letterOfGuaranteeIssuance = $this->issueLg();

        $this->renew($letterOfGuaranteeIssuance);

        $original = $this->cashCoverRows('debit-lg-amount');
        $this->assertCount(1, $original);
        $this->assertEquals(self::ORIGINAL_CASH_COVER, $original->first()->debit);
        $this->assertSame(self::ISSUANCE_DATE, $original->first()->date);
        $this->assertNull($original->first()->lg_renewal_date_history_id);

        foreach (['2025-01-01', '2025-04-01', '2025-07-01', '2025-10-01'] as $date) {
            $this->assertSame(500.0, $this->commissionsByDate()[$date], "commission on {$date} moved");
        }
    }

    /**
     * Every renewal recorded before the bank could re-price one sends
     * a date and a fee, nothing else. Those must not read as "the bank
     * set the cover to zero".
     */
    public function test_a_renewal_that_names_no_terms_leaves_the_pricing_exactly_as_it_was(): void
    {
        $letterOfGuaranteeIssuance = $this->issueLg();

        (new LetterOfGuaranteeIssuanceRenewalDateController)->store(
            StoreLgRenewalDateRequest::create('/', 'POST', [
                'renewal_date' => '01/01/2027',
                'expiry_date' => self::ORIGINAL_EXPIRY,
                'fees_amount' => '300',
            ]),
            Company::findOrFail(self::COMPANY),
            $letterOfGuaranteeIssuance
        );

        $letterOfGuaranteeIssuance->refresh();
        $this->assertEquals(self::ORIGINAL_CASH_COVER, $letterOfGuaranteeIssuance->getCashCoverAmount());
        $this->assertEquals(self::ORIGINAL_COMMISSION, $letterOfGuaranteeIssuance->getLgCommissionAmount());
        $this->assertCount(0, $this->cashCoverRows(LgRenewalTerms::CASH_COVER_TYPE));

        $this->assertSame(500.0, $this->commissionsByDate()['2026-01-01']);
        $this->assertNull($this->lastRenewal()->getCashCoverAmount());
    }

    // ---------------------------------------------------------------
    // editing and deleting a renewal
    // ---------------------------------------------------------------

    /**
     * Editing must re-measure against the cover in force BEFORE this
     * renewal (10,000), never against the one this same row already
     * set. Otherwise saving 20,000 twice would block 20,000 extra.
     */
    public function test_editing_a_renewal_recomputes_the_difference_from_the_cover_it_replaced(): void
    {
        $letterOfGuaranteeIssuance = $this->issueLg();
        $renewal = $this->renew($letterOfGuaranteeIssuance);

        (new LetterOfGuaranteeIssuanceRenewalDateController)->update(
            StoreLgRenewalDateRequest::create('/', 'PATCH', $this->renewalPayload([
                'cash_cover_amount' => '25,000',
                'lg_commission_amount' => '1,000',
            ])),
            Company::findOrFail(self::COMPANY),
            $letterOfGuaranteeIssuance->refresh(),
            $renewal->refresh()
        );

        $difference = $this->cashCoverRows(LgRenewalTerms::CASH_COVER_TYPE);
        $this->assertCount(1, $difference, 'the earlier difference row must be replaced, not added to');
        $this->assertEquals(15000, $difference->first()->debit);

        $bank = $this->bankRows('is_renewal_cash_cover');
        $this->assertCount(1, $bank);
        $this->assertEquals(15000, $bank->first()->credit);

        $letterOfGuaranteeIssuance->refresh();
        $this->assertEquals(25000, $letterOfGuaranteeIssuance->getCashCoverAmount());
        $this->assertEquals(1000, $letterOfGuaranteeIssuance->getLgCommissionAmount());
        $this->assertEquals(10000, $renewal->refresh()->getPreviousCashCoverAmount());

        $this->assertSame(1000.0, $this->commissionsByDate()['2026-01-01']);
        $this->assertSame(500.0, $this->commissionsByDate()['2025-01-01']);
    }

    /**
     * Editing a renewal back down to the cover it started from leaves
     * nothing behind — no zero-valued difference row.
     */
    public function test_editing_a_renewal_back_to_the_old_cover_removes_the_difference(): void
    {
        $letterOfGuaranteeIssuance = $this->issueLg();
        $renewal = $this->renew($letterOfGuaranteeIssuance);

        (new LetterOfGuaranteeIssuanceRenewalDateController)->update(
            StoreLgRenewalDateRequest::create('/', 'PATCH', $this->renewalPayload(['cash_cover_amount' => '10,000'])),
            Company::findOrFail(self::COMPANY),
            $letterOfGuaranteeIssuance->refresh(),
            $renewal->refresh()
        );

        $this->assertCount(0, $this->cashCoverRows(LgRenewalTerms::CASH_COVER_TYPE));
        $this->assertCount(0, $this->bankRows('is_renewal_cash_cover'));
        $this->assertEquals(10000, $letterOfGuaranteeIssuance->refresh()->getCashCoverAmount());
    }

    /**
     * Deleting a renewal un-does its re-pricing: the LG goes back on
     * its old cover and commission, the difference is released, and
     * the cover posted at issuance is left exactly where it was.
     */
    public function test_deleting_a_renewal_puts_the_issuance_back_on_its_old_terms(): void
    {
        $letterOfGuaranteeIssuance = $this->issueLg();
        $renewal = $this->renew($letterOfGuaranteeIssuance);

        (new LetterOfGuaranteeIssuanceRenewalDateController)->destroy(
            Company::findOrFail(self::COMPANY),
            $letterOfGuaranteeIssuance->refresh(),
            $renewal->refresh()
        );

        $letterOfGuaranteeIssuance->refresh();
        $this->assertEquals(self::ORIGINAL_CASH_COVER, $letterOfGuaranteeIssuance->getCashCoverAmount());
        $this->assertEquals(10, $letterOfGuaranteeIssuance->getCashCoverRate());
        $this->assertEquals(self::ORIGINAL_COMMISSION, $letterOfGuaranteeIssuance->getLgCommissionAmount());
        $this->assertSame(self::ORIGINAL_EXPIRY, $letterOfGuaranteeIssuance->getRenewalDate());

        $this->assertCount(0, $this->cashCoverRows(LgRenewalTerms::CASH_COVER_TYPE));
        $this->assertCount(0, $this->bankRows('is_renewal_cash_cover'));

        $original = $this->cashCoverRows('debit-lg-amount');
        $this->assertCount(1, $original);
        $this->assertEquals(self::ORIGINAL_CASH_COVER, $original->first()->debit);
    }

    // ---------------------------------------------------------------
    // what the rest of the app reads off the re-priced issuance
    // ---------------------------------------------------------------

    /**
     * The reason the issuance row is kept current rather than the
     * renewal history being replayed: cancellation refunds whatever
     * cover is blocked TODAY, which after a renewal is the new one.
     */
    public function test_cancelling_after_a_renewal_refunds_the_cover_the_renewal_set(): void
    {
        $letterOfGuaranteeIssuance = $this->issueLg();

        $this->renew($letterOfGuaranteeIssuance);

        $this->assertEquals(20000, $letterOfGuaranteeIssuance->refresh()->getCashCoverCancellationAmount());
    }

    /**
     * Cover held against a CD or a TD never moves through a current
     * account — the same reason the original issuance skips posting it
     * — but the LG still has to end up priced on the new terms.
     */
    public function test_a_cover_held_against_a_cd_or_td_is_re_priced_without_moving_money(): void
    {
        $letterOfGuaranteeIssuance = $this->issueLg(['cash_cover_deducted_from_account_type' => '29']);

        $this->renew($letterOfGuaranteeIssuance);

        $this->assertCount(0, $this->cashCoverRows(LgRenewalTerms::CASH_COVER_TYPE));
        $this->assertCount(0, $this->bankRows('is_renewal_cash_cover'));
        $this->assertEquals(20000, $letterOfGuaranteeIssuance->refresh()->getCashCoverAmount());
    }

    /**
     * An opening-balance LG's cover was never posted — it is already
     * inside the opening balance — so a renewal has nothing to move.
     */
    public function test_an_opening_balance_lg_is_re_priced_without_moving_money(): void
    {
        $letterOfGuaranteeIssuance = $this->issueLg(['category_name' => LetterOfGuaranteeIssuance::OPENING_BALANCE]);

        $this->renew($letterOfGuaranteeIssuance);

        $this->assertCount(0, $this->cashCoverRows(LgRenewalTerms::CASH_COVER_TYPE));
        $this->assertCount(0, $this->bankRows('is_renewal_cash_cover'));
        $this->assertEquals(20000, $letterOfGuaranteeIssuance->refresh()->getCashCoverAmount());
    }

    // ---------------------------------------------------------------
    // what the form accepts
    // ---------------------------------------------------------------

    public function test_the_amounts_arrive_display_formatted_and_an_empty_one_means_unchanged(): void
    {
        $terms = LgRenewalTerms::fromInput([
            'cash_cover_amount' => '1,250,000.50',
            'lg_commission_amount' => '',
        ]);

        $this->assertSame(1250000.5, $terms['cash_cover_amount']);
        $this->assertNull($terms['lg_commission_amount']);
        $this->assertNull($terms['min_lg_commission_fees']);
    }

    public function test_a_negative_cash_cover_is_rejected(): void
    {
        $rules = StoreLgRenewalDateRequest::create('/', 'POST', [
            'renewal_date' => '01/01/2027',
            'expiry_date' => self::ORIGINAL_EXPIRY,
        ])->rules();

        $this->assertTrue(
            Validator::make(['cash_cover_amount' => '-5,000'], ['cash_cover_amount' => $rules['cash_cover_amount']])->fails()
        );
        $this->assertFalse(
            Validator::make(['cash_cover_amount' => '5,000'], ['cash_cover_amount' => $rules['cash_cover_amount']])->fails()
        );
        $this->assertFalse(
            Validator::make(['cash_cover_amount' => ''], ['cash_cover_amount' => $rules['cash_cover_amount']])->fails()
        );
    }
}
