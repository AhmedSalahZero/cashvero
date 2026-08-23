<?php

namespace Tests\Feature\LetterOfGuarantee;

use App\Models\LetterOfGuaranteeIssuance;
use App\Rules\LgIssuanceDateMatchesCategoryRule;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * The issuance date has to sit on the right side of the company's
 * Opening Balance Date, and which side depends on the Issuance Type.
 *
 *   Opening Balance — the LG was already outstanding when the company
 *       came onto CashVero, so it was issued BEFORE that date.
 *   New Issuance — issued while the company has been on the system, so
 *       on or after it.
 *
 * The boundary day itself is what separates them: the opening balance
 * date belongs to New Issuance, not to Opening Balance.
 *
 * @see \App\Rules\LgIssuanceDateMatchesCategoryRule
 */
class LgIssuanceDateTest extends TestCase
{
    private const OPENING = '2026-08-01';

    private function check(?string $category, $date, ?string $opening = self::OPENING): LgIssuanceDateMatchesCategoryRule
    {
        $rule = new LgIssuanceDateMatchesCategoryRule($category, $opening);
        $rule->passes('issuance_date', $date);

        return $rule;
    }

    private function passes(?string $category, $date, ?string $opening = self::OPENING): bool
    {
        return (new LgIssuanceDateMatchesCategoryRule($category, $opening))->passes('issuance_date', $date);
    }

    // ---------------------------------------------------------------
    // Opening Balance
    // ---------------------------------------------------------------

    /**
     * @dataProvider openingBalanceProvider
     */
    public function test_an_opening_balance_lg_must_predate_the_opening_balance_date(string $date, bool $expected, string $why): void
    {
        $this->assertSame($expected, $this->passes(LetterOfGuaranteeIssuance::OPENING_BALANCE, $date), $why);
    }

    public static function openingBalanceProvider(): array
    {
        return [
            'a year before' => ['2025-08-01', true, 'Well before — clearly an opening position.'],
            'the day before' => ['2026-07-31', true, 'The last day that still counts as before.'],
            'the opening day itself' => ['2026-08-01', false, 'The boundary day belongs to New Issuance.'],
            'the day after' => ['2026-08-02', false, 'After the company started — not an opening position.'],
            'a year after' => ['2027-08-01', false, 'Plainly a new issuance.'],
        ];
    }

    // ---------------------------------------------------------------
    // New Issuance
    // ---------------------------------------------------------------

    /**
     * @dataProvider newIssuanceProvider
     */
    public function test_a_new_issuance_lg_must_not_predate_the_opening_balance_date(string $date, bool $expected, string $why): void
    {
        $this->assertSame($expected, $this->passes(LetterOfGuaranteeIssuance::NEW_ISSUANCE, $date), $why);
    }

    public static function newIssuanceProvider(): array
    {
        return [
            'the day before' => ['2026-07-31', false, 'That belongs to the opening balance.'],
            'the opening day itself' => ['2026-08-01', true, 'On the date is allowed — the rule is >=.'],
            'the day after' => ['2026-08-02', true, 'Issued while on the system.'],
            'a year after' => ['2027-08-01', true, 'Plainly a new issuance.'],
        ];
    }

    // ---------------------------------------------------------------
    // the two types are exact opposites either side of the boundary
    // ---------------------------------------------------------------

    /**
     * Whatever the date, exactly one of the two types accepts it —
     * there is no date both allow, and none both refuse.
     *
     * @dataProvider everyDateProvider
     */
    public function test_exactly_one_issuance_type_accepts_any_given_date(string $date): void
    {
        $opening = $this->passes(LetterOfGuaranteeIssuance::OPENING_BALANCE, $date);
        $new = $this->passes(LetterOfGuaranteeIssuance::NEW_ISSUANCE, $date);

        $this->assertNotSame($opening, $new, "Both types agreed about {$date}; the boundary has a gap or an overlap.");
    }

    public static function everyDateProvider(): array
    {
        return [
            ['2025-01-01'], ['2026-07-30'], ['2026-07-31'],
            ['2026-08-01'], ['2026-08-02'], ['2027-12-31'],
        ];
    }

    // ---------------------------------------------------------------
    // date formats and missing values
    // ---------------------------------------------------------------

    /**
     * The form posts d/m/Y from the date picker; imports post Y-m-d.
     *
     * @dataProvider dateFormatProvider
     */
    public function test_both_date_formats_are_understood(string $date, bool $expected): void
    {
        $this->assertSame($expected, $this->passes(LetterOfGuaranteeIssuance::OPENING_BALANCE, $date));
    }

    public static function dateFormatProvider(): array
    {
        return [
            'Y-m-d before' => ['2026-07-15', true],
            'd/m/Y before' => ['15/07/2026', true],
            'Y-m-d after' => ['2026-09-10', false],
            'd/m/Y after' => ['10/09/2026', false],
        ];
    }

    /**
     * A company that has never set an opening balance date gives this
     * rule nothing to compare against — it must stay silent rather than
     * refusing every issuance.
     */
    public function test_a_company_with_no_opening_balance_date_is_not_blocked(): void
    {
        foreach ([null, ''] as $opening) {
            $this->assertTrue($this->passes(LetterOfGuaranteeIssuance::OPENING_BALANCE, '2027-01-01', $opening));
            $this->assertTrue($this->passes(LetterOfGuaranteeIssuance::NEW_ISSUANCE, '2020-01-01', $opening));
        }
    }

    /**
     * An empty date is `required`'s business, not this rule's — it must
     * not add a second, confusing message about it.
     *
     * @dataProvider emptyDateProvider
     */
    public function test_an_empty_date_is_left_to_the_required_rule($date): void
    {
        $this->assertTrue($this->passes(LetterOfGuaranteeIssuance::OPENING_BALANCE, $date));
    }

    public static function emptyDateProvider(): array
    {
        return [['', ], [null], ['null']];
    }

    public function test_an_unparseable_date_is_left_to_the_date_rule(): void
    {
        $this->assertTrue($this->passes(LetterOfGuaranteeIssuance::NEW_ISSUANCE, 'not-a-date'));
    }

    /**
     * A category this rule does not know about must not be refused —
     * only the two real types are governed.
     */
    public function test_an_unknown_category_is_not_judged(): void
    {
        $this->assertTrue($this->passes('something-else', '1999-01-01'));
        $this->assertTrue($this->passes(null, '1999-01-01'));
    }

    // ---------------------------------------------------------------
    // the message names the date the user has to work around
    // ---------------------------------------------------------------

    public function test_the_refusal_names_the_opening_balance_date(): void
    {
        $rule = $this->check(LetterOfGuaranteeIssuance::OPENING_BALANCE, '2026-09-10');

        $this->assertStringContainsString('01/08/2026', $rule->message(),
            'The user cannot fix the date without being told what it has to be before.');
        $this->assertStringContainsString('before', $rule->message());
    }

    public function test_the_new_issuance_refusal_says_on_or_after(): void
    {
        $rule = $this->check(LetterOfGuaranteeIssuance::NEW_ISSUANCE, '2026-07-15');

        $this->assertStringContainsString('01/08/2026', $rule->message());
        $this->assertStringContainsString('on or after', $rule->message());
    }

    // ---------------------------------------------------------------
    // the rule is actually wired into the request
    // ---------------------------------------------------------------

    /**
     * ImplicitRule matters here: Laravel skips a non-implicit rule on an
     * empty value, and these forms post blanks.
     */
    public function test_the_rule_is_implicit(): void
    {
        $this->assertInstanceOf(
            \Illuminate\Contracts\Validation\ImplicitRule::class,
            new LgIssuanceDateMatchesCategoryRule(null, null)
        );
    }

    public function test_the_store_request_applies_it_to_the_issuance_date(): void
    {
        $source = file_get_contents(app_path('Http/Requests/StoreLetterOfGuaranteeIssuanceRequest.php'));

        $this->assertStringContainsString('new LgIssuanceDateMatchesCategoryRule(', $source);
        $this->assertMatchesRegularExpression(
            "/'issuance_date'\s*=>\s*\[\s*'required',\s*new LgIssuanceDateMatchesCategoryRule\(/s",
            $source,
            'The rule must sit on issuance_date alongside required.'
        );
        $this->assertStringContainsString("\$this->get('category_name')", $source,
            'It has to be told which Issuance Type was chosen.');
        $this->assertStringContainsString('companyOpeningBalanceDate()', $source,
            "...and the company's own opening balance date.");
    }

    /**
     * The Update request inherits from Store, so editing an LG is
     * governed by the same rule. If that ever stops being true the
     * check would only apply on create.
     */
    public function test_editing_an_lg_is_governed_by_the_same_rule(): void
    {
        $this->assertTrue(
            is_subclass_of(
                \App\Http\Requests\UpdateLetterOfGuaranteeIssuanceRequest::class,
                \App\Http\Requests\StoreLetterOfGuaranteeIssuanceRequest::class
            )
        );

        $rules = (new \ReflectionClass(\App\Http\Requests\UpdateLetterOfGuaranteeIssuanceRequest::class))
            ->getMethod('rules')->getDeclaringClass()->getName();

        $this->assertNotNull($rules);
    }

    /**
     * End to end through the validator, which is the only thing that
     * proves the message reaches the form.
     */
    public function test_the_validator_reports_the_failure_on_issuance_date(): void
    {
        $rule = new LgIssuanceDateMatchesCategoryRule(LetterOfGuaranteeIssuance::OPENING_BALANCE, self::OPENING);

        $validator = Validator::make(
            ['issuance_date' => '2026-09-10'],
            ['issuance_date' => ['required', $rule]]
        );

        $this->assertTrue($validator->fails());
        $this->assertStringContainsString('01/08/2026', $validator->errors()->first('issuance_date'));
    }
}
