<?php

namespace Tests\Feature\LetterOfGuarantee;

use App\Enums\LgTypes;
use App\Http\Requests\StoreLetterOfGuaranteeIssuanceRequest;
use App\Support\LetterOfGuarantee\LgContractRequirement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * When an LG issuance has to name a contract.
 *
 * Bid Bond was already excused — it is issued to WIN work that has no
 * contract yet. What was missing is the beneficiary side: a Final,
 * Advance Payment or Performance LG made out to an authority, a court
 * or a landlord has no customer contract to point at either, and the
 * form refused to save without one.
 *
 * A partner flagged BOTH customer and other can go either way, so the
 * contract is optional there rather than forbidden.
 *
 * @see \App\Support\LetterOfGuarantee\LgContractRequirement
 */
class LgContractRequirementTest extends TestCase
{
    private const CUSTOMER_ONLY = 1;

    private const OTHER_ONLY = 2;

    private const BOTH = 3;

    private const NEITHER = 4;

    protected function setUp(): void
    {
        parent::setUp();

        $database = DB::connection()->getDatabaseName();
        $this->assertStringContainsString('test', $database, "Refusing to run against '{$database}'.");

        Schema::dropIfExists('partners');
        Schema::create('partners', function ($table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('company_id')->nullable();
            $table->string('name')->nullable();
            $table->boolean('is_customer')->default(0);
            $table->boolean('is_other_partner')->default(0);
        });

        DB::table('partners')->insert([
            ['id' => self::CUSTOMER_ONLY, 'company_id' => 148, 'name' => 'Itida', 'is_customer' => 1, 'is_other_partner' => 0],
            ['id' => self::OTHER_ONLY, 'company_id' => 148, 'name' => 'Tax Authority', 'is_customer' => 0, 'is_other_partner' => 1],
            ['id' => self::BOTH, 'company_id' => 148, 'name' => 'Both Ways', 'is_customer' => 1, 'is_other_partner' => 1],
            ['id' => self::NEITHER, 'company_id' => 148, 'name' => 'Supplier Only', 'is_customer' => 0, 'is_other_partner' => 0],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('partners');

        parent::tearDown();
    }

    /**
     * The three types the report named, plus the one that was already
     * excused, against every kind of beneficiary.
     *
     * @dataProvider requirementProvider
     */
    public function test_whether_a_contract_is_required(string $lgType, ?int $partnerId, bool $expected, string $why): void
    {
        $this->assertSame($expected, LgContractRequirement::isRequired($lgType, $partnerId), $why);
    }

    public static function requirementProvider(): array
    {
        $types = ['final-lgs', 'advance-payment-lgs', 'performance-lgs'];
        $cases = [];

        foreach ($types as $type) {
            $cases["{$type} + customer only"] = [$type, self::CUSTOMER_ONLY, true,
                'A pure customer on a contract-backed LG type must name one.'];
            $cases["{$type} + other only"] = [$type, self::OTHER_ONLY, false,
                'An authority or landlord has no customer contract to point at.'];
            $cases["{$type} + customer AND other"] = [$type, self::BOTH, false,
                'Flagged both ways, so the contract is optional — not forced.'];
            $cases["{$type} + no beneficiary picked"] = [$type, null, true,
                'Nothing chosen yet: keep asking for it.'];
        }

        $cases['bid bond + customer only'] = [LgTypes::BID_BOND, self::CUSTOMER_ONLY, false,
            'A tender guarantee precedes the contract entirely.'];
        $cases['bid bond + other only'] = [LgTypes::BID_BOND, self::OTHER_ONLY, false, 'Excused twice over.'];

        return $cases;
    }

    /**
     * A partner that is neither customer nor other should not slip
     * through the "other" exemption.
     */
    public function test_a_partner_that_is_neither_still_needs_a_contract(): void
    {
        $this->assertTrue(LgContractRequirement::isRequired('final-lgs', self::NEITHER));
    }

    public function test_an_unknown_partner_id_does_not_excuse_the_contract(): void
    {
        $this->assertTrue(LgContractRequirement::isRequired('final-lgs', 99999));
        $this->assertFalse(LgContractRequirement::partnerIsOther(99999));
    }

    // ---------------------------------------------------------------
    // what the form is told
    // ---------------------------------------------------------------

    public function test_the_form_is_told_exactly_which_beneficiaries_are_exempt(): void
    {
        $exempt = LgContractRequirement::partnerIdsWithoutContractRequirement([
            self::CUSTOMER_ONLY, self::OTHER_ONLY, self::BOTH, self::NEITHER,
        ]);

        sort($exempt);

        $this->assertSame([self::OTHER_ONLY, self::BOTH], $exempt === [] ? [] : $exempt);
    }

    public function test_an_empty_beneficiary_list_asks_the_database_nothing(): void
    {
        $this->assertSame([], LgContractRequirement::partnerIdsWithoutContractRequirement([]));
    }

    // ---------------------------------------------------------------
    // the validation actually applies it
    // ---------------------------------------------------------------

    private function contractRuleFails(array $input): bool
    {
        $request = StoreLetterOfGuaranteeIssuanceRequest::create('/', 'POST', $input);
        $rules = $request->rules();

        $validator = Validator::make($input, ['contract_id' => $rules['contract_id']]);

        return $validator->fails();
    }

    /**
     * The form posts the literal string "null" for an empty select —
     * see HasBasicStoreRequest::storeBasicForm. A plain 'required' rule
     * would happily accept that string.
     *
     * @dataProvider emptyContractProvider
     */
    public function test_an_empty_contract_is_rejected_for_a_plain_customer($emptyValue): void
    {
        $this->assertTrue($this->contractRuleFails([
            'lg_type' => 'final-lgs',
            'partner_id' => self::CUSTOMER_ONLY,
            'contract_id' => $emptyValue,
        ]), 'A customer on a Final LG must supply a contract.');
    }

    public static function emptyContractProvider(): array
    {
        return [
            'empty string' => [''],
            'null' => [null],
            'the string "null"' => ['null'],
        ];
    }

    /**
     * @dataProvider emptyContractProvider
     */
    public function test_an_empty_contract_is_accepted_for_an_other_partner($emptyValue): void
    {
        $this->assertFalse($this->contractRuleFails([
            'lg_type' => 'final-lgs',
            'partner_id' => self::OTHER_ONLY,
            'contract_id' => $emptyValue,
        ]), 'An "other partner" beneficiary must be able to save without one.');
    }

    /**
     * @dataProvider emptyContractProvider
     */
    public function test_an_empty_contract_is_accepted_for_a_customer_who_is_also_other($emptyValue): void
    {
        $this->assertFalse($this->contractRuleFails([
            'lg_type' => 'final-lgs',
            'partner_id' => self::BOTH,
            'contract_id' => $emptyValue,
        ]), 'Flagged both ways means optional.');
    }

    public function test_a_contract_may_still_be_attached_to_an_other_partner(): void
    {
        $this->assertFalse($this->contractRuleFails([
            'lg_type' => 'final-lgs',
            'partner_id' => self::OTHER_ONLY,
            'contract_id' => 762,
        ]), 'Optional means allowed, not forbidden.');
    }

    public function test_bid_bond_never_needs_one_whoever_the_beneficiary_is(): void
    {
        foreach ([self::CUSTOMER_ONLY, self::OTHER_ONLY, self::BOTH, null] as $partnerId) {
            $this->assertFalse($this->contractRuleFails([
                'lg_type' => LgTypes::BID_BOND,
                'partner_id' => $partnerId,
                'contract_id' => 'null',
            ]));
        }
    }

    /**
     * The message the user gets has to survive: if the rule stops
     * firing entirely nothing would notice, because "no error" is the
     * happy path everywhere else in this test.
     */
    public function test_the_refusal_still_names_the_reason(): void
    {
        $input = ['lg_type' => 'final-lgs', 'partner_id' => self::CUSTOMER_ONLY, 'contract_id' => 'null'];
        $rules = StoreLetterOfGuaranteeIssuanceRequest::create('/', 'POST', $input)->rules();

        $validator = Validator::make($input, ['contract_id' => $rules['contract_id']]);

        $this->assertTrue($validator->fails());
        $this->assertSame(
            __('Contract is required for this LG type.'),
            $validator->errors()->first('contract_id')
        );
    }

    // ---------------------------------------------------------------
    // the page and the server agree
    // ---------------------------------------------------------------

    public function test_the_lookup_publishes_the_exemptions_to_the_form(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/LetterOfGuaranteeFacilityController.php'));

        $this->assertStringContainsString("'customers_without_contract_requirement'", $source);
        $this->assertStringContainsString('LgContractRequirement::partnerIdsWithoutContractRequirement', $source,
            'The page must be told by the same rule the validation uses, not a second copy of it.');
    }

    // ---------------------------------------------------------------
    // the bank list is ordered by what the user reads
    // ---------------------------------------------------------------

    /**
     * The bank's label comes from a relation (bank->getViewName()), not
     * from a column on financial_institutions — so an orderBy in the
     * query would sort by something other than the text on screen. The
     * sort has to happen on the collection, after the names resolve.
     */
    public function test_the_issuance_forms_sort_the_bank_list_by_its_displayed_name(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/LetterOfGuaranteeIssuanceController.php'));

        $this->assertMatchesRegularExpression(
            '/\$financialInstitutionBanks\s*=\s*\$financialInstitutionBanks\s*->sortBy\(fn \(\$bank\) => mb_strtolower\(\(string\) \$bank->getName\(\)\), SORT_NATURAL\)/',
            $source,
            'commonViewVars() must sort the bank list by the name the user actually sees.'
        );

        $this->assertLessThan(
            strpos($source, "'financialInstitutionBanks'=>\$financialInstitutionBanks"),
            strpos($source, '->sortBy(fn ($bank) => mb_strtolower'),
            'Sorting after the array is built would not reach the form.'
        );
    }

    /**
     * The ordering itself: case-insensitive, and natural so "Bank 2"
     * comes before "Bank 10" rather than after it.
     */
    public function test_the_bank_ordering_is_case_insensitive_and_natural(): void
    {
        $banks = collect([
            (object) ['name' => 'Qatar National Bank'],
            (object) ['name' => 'abu dhabi commercial bank'],
            (object) ['name' => 'Bank 10'],
            (object) ['name' => 'Bank 2'],
            (object) ['name' => 'Ahli United Bank'],
        ]);

        $sorted = $banks
            ->sortBy(fn ($bank) => mb_strtolower((string) $bank->name), SORT_NATURAL)
            ->values()
            ->map(fn ($bank) => $bank->name)
            ->all();

        $this->assertSame([
            'abu dhabi commercial bank',
            'Ahli United Bank',
            'Bank 2',
            'Bank 10',
            'Qatar National Bank',
        ], $sorted);
    }

    /**
     * All four issuance forms carry the same Contract field, and the
     * server applies the same rule to all four. A form left behind
     * would keep demanding a contract the backend no longer wants —
     * the user would see a required field they cannot satisfy.
     *
     * @dataProvider issuanceFormProvider
     */
    public function test_every_issuance_form_drops_the_asterisk_from_the_same_list(string $form): void
    {
        $page = file_get_contents(resource_path("js/Pages/LetterOfGuaranteeIssuance/{$form}.vue"));

        $this->assertStringContainsString('customers_without_contract_requirement', $page,
            "{$form} never reads the exemption list the server sends.");
        $this->assertStringContainsString('const contractIsRequired', $page);
        $this->assertMatchesRegularExpression('/Contract <span v-if="contractIsRequired">\*<\/span>/', $page,
            "{$form}'s asterisk must follow the rule, not be hard-coded.");
    }

    /**
     * The SO field hangs off the contract, so its asterisk has to move
     * with it — otherwise the form still looks like it is demanding
     * something.
     *
     * @dataProvider issuanceFormProvider
     */
    public function test_the_sales_order_asterisk_follows_the_contract(string $form): void
    {
        $page = file_get_contents(resource_path("js/Pages/LetterOfGuaranteeIssuance/{$form}.vue"));

        $this->assertMatchesRegularExpression('/SO <span v-if="contractIsRequired">\*<\/span>/', $page);
    }

    /**
     * A disabled placeholder means a contract picked by mistake can
     * never be cleared again. When it is optional, the empty option has
     * to be selectable.
     *
     * @dataProvider issuanceFormProvider
     */
    public function test_the_contract_can_be_cleared_again_when_it_is_optional(string $form): void
    {
        $select = $this->contractSelectOf($form);

        $this->assertStringContainsString(':disabled="contractIsRequired"', $select,
            "{$form} still hard-disables the empty option, so an optional contract cannot be unset.");
        $this->assertDoesNotMatchRegularExpression('/<option[^>]*\sdisabled[\s>]/', $select,
            'An unconditional disabled on the placeholder locks the field.');
    }

    /**
     * Just the Contract <select>, so an unrelated field's disabled
     * placeholder elsewhere in the same form is not mistaken for this
     * one — these forms have seven of them.
     */
    private function contractSelectOf(string $form): string
    {
        $page = file_get_contents(resource_path("js/Pages/LetterOfGuaranteeIssuance/{$form}.vue"));

        $start = strpos($page, 'Contract <span v-if="contractIsRequired">');
        $this->assertNotFalse($start, "{$form} has no Contract field bound to the rule.");

        $end = strpos($page, '</select>', $start);
        $this->assertNotFalse($end);

        return substr($page, $start, $end - $start);
    }

    /**
     * And it says so, rather than leaving the user to infer it from a
     * missing asterisk.
     *
     * @dataProvider issuanceFormProvider
     */
    public function test_the_form_explains_why_the_contract_is_optional(string $form): void
    {
        $page = file_get_contents(resource_path("js/Pages/LetterOfGuaranteeIssuance/{$form}.vue"));

        $this->assertStringContainsString('no customer contract is required', $page);
    }

    public static function issuanceFormProvider(): array
    {
        return [
            'LG Facility' => ['LgFacilityForm'],
            'Against TD' => ['AgainstTdForm'],
            'Against CD' => ['AgainstCdForm'],
            '100% Cash Cover' => ['HundredPercentageCashCoverForm'],
        ];
    }
}
