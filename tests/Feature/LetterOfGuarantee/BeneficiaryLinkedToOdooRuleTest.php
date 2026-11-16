<?php

namespace Tests\Feature\LetterOfGuarantee;

use App\Enums\LgTypes;
use App\Http\Requests\StoreLetterOfGuaranteeIssuanceRequest;
use App\Http\Requests\UpdateLetterOfGuaranteeIssuanceRequest;
use App\Models\Company;
use App\Models\LetterOfGuaranteeIssuance;
use App\Models\Partner;
use App\Models\User;
use App\Rules\BeneficiaryLinkedToOdooRule;
use App\Services\Api\OdooSync;
use Database\Factories\PartnerFactory;
use Illuminate\Support\Facades\Validator;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * An LG that is going to Odoo must name a beneficiary that is linked to
 * Odoo.
 *
 * Before the rule, an LG with an unlinked beneficiary (odoo_id NULL)
 * saved normally, then LetterOfGuaranteeService::createLgIssuanceCashCover
 * (int $odooPartnerId) threw a TypeError after the commit — so the cash
 * cover, issuance fees and commission never reached Odoo.
 *
 * "Is it going to Odoo?" is the model's own answer
 * (handleIssuanceAndCommissionFeesForOdoo), not a second copy of it —
 * the agreement test below holds the two together.
 *
 * The company is linked to Odoo from 2025-11-01, like company 92.
 */
class BeneficiaryLinkedToOdooRuleTest extends TestCase
{
    private const OPENING_BALANCE_DATE = '2025-06-01';

    private const INTEGRATION_START = '2025-11-01';

    private Company $company;

    private Partner $linked;

    private Partner $unlinked;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            LocaleSessionRedirect::class,
            LaravelLocalizationRedirectFilter::class,
            LaravelLocalizationViewPath::class,
        ]);

        $this->company = $this->makeOdooCompany();
        $this->linked = PartnerFactory::new()->forCompany($this->company)->customer()->create(['odoo_id' => 4521]);
        $this->unlinked = PartnerFactory::new()->forCompany($this->company)->customer()->create(['name' => 'ادارة المتاحف العسكرية', 'odoo_id' => null]);

        $this->actingAs($this->odooUser($this->company));
    }

    // ---------------------------------------------------------------
    // fixtures
    // ---------------------------------------------------------------

    private function makeOdooCompany(array $overrides = []): Company
    {
        return $this->makeCompany($overrides + [
            'opening_balance_date' => self::OPENING_BALANCE_DATE,
            'odoo_db_url' => 'https://odoo.invalid',
            'odoo_db_name' => 'test',
            'odoo_integration_start_date' => self::INTEGRATION_START,
        ]);
    }

    private function odooUser(Company $company, array $permissions = []): User
    {
        return $this->makeUser($permissions, $company, null, ['odoo_username' => 'tester', 'odoo_db_password' => 'secret']);
    }

    private function input(array $overrides = []): array
    {
        return $overrides + [
            'lg_type' => LgTypes::FINAL_LGS,
            'category_name' => LetterOfGuaranteeIssuance::NEW_ISSUANCE,
            'issuance_date' => '2026-01-10',
            'activation_date' => null,
        ];
    }

    private function validate(mixed $partnerId, array $overrides = [], ?Company $company = null): \Illuminate\Validation\Validator
    {
        $input = $this->input($overrides);
        $rule = new BeneficiaryLinkedToOdooRule(
            $company ?? $this->company,
            $input['lg_type'],
            $input['category_name'],
            $input['issuance_date'],
            $input['activation_date'],
        );

        return Validator::make(['partner_id' => $partnerId], ['partner_id' => ['required', $rule]]);
    }

    private function notLinkedMessage(Partner $partner): string
    {
        return __('The beneficiary :name is not linked to Odoo. Add it in Odoo, run "Read Odoo Partners", then select it again.', ['name' => $partner->name]);
    }

    /**
     * What the model itself decides for the same form values: filled the
     * way storeBasicForm fills it (the form's "null" means empty), then
     * asked to post its fees — the wider of the two Odoo paths.
     */
    private function modelQueuesAnOdooCall(array $input): bool
    {
        $values = array_filter($input, fn ($value) => filled($value) && $value !== 'null');
        $issuance = (new LetterOfGuaranteeIssuance)->forceFill($values + ['company_id' => $this->company->id]);
        $issuance->setRelation('company', $this->company);

        return OdooSync::transaction(function () use ($issuance) {
            $issuance->handleIssuanceAndCommissionFeesForOdoo();
            $queued = OdooSync::hasPending();
            OdooSync::discard();

            return $queued;
        });
    }

    // ---------------------------------------------------------------
    // the rule and the model agree on what goes to Odoo
    // ---------------------------------------------------------------

    public static function scenarioProvider(): array
    {
        $final = LgTypes::FINAL_LGS;
        $advance = LgTypes::ADVANCED_PAYMENT_LGS;
        $new = LetterOfGuaranteeIssuance::NEW_ISSUANCE;
        $opening = LetterOfGuaranteeIssuance::OPENING_BALANCE;
        /**
         * * التكامل قبل تاريخ الرصيد الافتتاحي ، عشان الرصيد الافتتاحي يبقى
         * * هو السبب لوحده مش تاريخ التكامل
         */
        $integratedEarly = ['odoo_integration_start_date' => '2020-01-01'];

        return [
            'final, opening balance, inside the integration' => [[$final, $opening, '2025-03-01', null], true, false, $integratedEarly],
            'advance payment, opening balance activated before the opening date, inside the integration' => [[$advance, $opening, '2025-03-01', '2025-05-01'], true, false, $integratedEarly],
            'final, issued inside the integration' => [[$final, $new, '2026-01-10', null], true, true],
            'bid bond, issued inside the integration' => [[LgTypes::BID_BOND, $new, '2026-01-10', null], true, true],
            'final, issued before the integration start' => [[$final, $new, '2025-09-15', null], true, false],
            'final, opening balance' => [[$final, $opening, '2025-03-01', null], true, false],
            'advance payment, not activated' => [[$advance, $new, '2026-01-10', null], true, false],
            'advance payment, the form\'s "null" activation date' => [[$advance, $new, '2026-01-10', 'null'], true, false],
            'advance payment, activated inside the integration' => [[$advance, $new, '2025-12-01', '2026-02-01'], true, true],
            'advance payment, activated before the integration start' => [[$advance, $new, '2025-07-01', '2025-09-01'], true, false],
            'advance payment, opening balance activated after the opening date' => [[$advance, $opening, '2025-03-01', '2026-02-01'], true, true],
            'advance payment, opening balance activated before the opening date' => [[$advance, $opening, '2025-03-01', '2025-05-01'], true, false],
            'final, inside the integration, user without Odoo credentials' => [[$final, $new, '2026-01-10', null], false, false],
        ];
    }

    /**
     * @param  array{0: string, 1: string, 2: string, 3: ?string}  $values
     * @param  array<string, string>  $companyOverrides
     */
    #[DataProvider('scenarioProvider')]
    public function test_an_unlinked_beneficiary_is_refused_exactly_when_the_lg_goes_to_odoo(array $values, bool $userHasOdoo, bool $goesToOdoo, array $companyOverrides = []): void
    {
        if ($companyOverrides !== []) {
            $this->company->forceFill($companyOverrides)->save();
        }

        if (! $userHasOdoo) {
            $this->actingAs($this->makeUser([], $this->company));
        }

        $input = array_combine(['lg_type', 'category_name', 'issuance_date', 'activation_date'], $values);

        $this->assertSame($goesToOdoo, $this->modelQueuesAnOdooCall($input),
            'The scenario itself is wrong: the model does not decide what it was expected to.');
        $this->assertSame($goesToOdoo, $this->validate($this->unlinked->id, $input)->fails(),
            'The rule disagrees with the model about whether this LG goes to Odoo.');
        $this->assertFalse($this->validate($this->linked->id, $input)->fails(),
            'A linked beneficiary is always accepted.');
    }

    // ---------------------------------------------------------------
    // what the user is told
    // ---------------------------------------------------------------

    public function test_the_refusal_names_the_beneficiary(): void
    {
        $validator = $this->validate($this->unlinked->id);

        $this->assertTrue($validator->fails());
        $this->assertSame($this->notLinkedMessage($this->unlinked), $validator->errors()->first('partner_id'));
        $this->assertStringContainsString('ادارة المتاحف العسكرية', $validator->errors()->first('partner_id'));
    }

    public function test_a_beneficiary_of_another_company_is_invalid(): void
    {
        $otherCompany = $this->makeOdooCompany();
        $theirs = PartnerFactory::new()->forCompany($otherCompany)->customer()->create(['odoo_id' => 777]);

        $validator = $this->validate($theirs->id);

        $this->assertTrue($validator->fails());
        $this->assertSame(__('The selected beneficiary is invalid.'), $validator->errors()->first('partner_id'));
    }

    public static function notAPartnerIdProvider(): array
    {
        return [
            'an array' => [[1, 2]],
            'the form\'s "null"' => ['null'],
            'text' => ['abc'],
            'a missing id' => [999999999],
        ];
    }

    #[DataProvider('notAPartnerIdProvider')]
    public function test_a_value_that_is_not_one_of_the_company_partners_is_invalid(mixed $value): void
    {
        $validator = $this->validate($value);

        $this->assertTrue($validator->fails());
        $this->assertSame(__('The selected beneficiary is invalid.'), $validator->errors()->first('partner_id'));
    }

    // ---------------------------------------------------------------
    // what it leaves alone
    // ---------------------------------------------------------------

    public function test_a_company_without_odoo_accepts_an_unlinked_beneficiary(): void
    {
        $company = $this->makeCompany(['opening_balance_date' => self::OPENING_BALANCE_DATE]);
        $partner = PartnerFactory::new()->forCompany($company)->customer()->create(['odoo_id' => null]);
        $this->actingAs($this->odooUser($company));

        $this->assertFalse($this->validate($partner->id, [], $company)->fails());
    }

    /**
     * The opening balance alone, with the integration date out of the way.
     */
    public function test_an_opening_balance_lg_inside_the_integration_accepts_an_unlinked_beneficiary(): void
    {
        $company = $this->makeOdooCompany(['odoo_integration_start_date' => '2020-01-01']);
        $partner = PartnerFactory::new()->forCompany($company)->customer()->create(['odoo_id' => null]);
        $this->actingAs($this->odooUser($company));

        $this->assertFalse($this->validate($partner->id, ['category_name' => LetterOfGuaranteeIssuance::OPENING_BALANCE, 'issuance_date' => '2025-03-01'], $company)->fails());
        $this->assertTrue($this->validate($partner->id, ['issuance_date' => '2025-07-01'], $company)->fails(),
            'The same LG as a New Issuance does go to Odoo — so the pass above is the opening balance, not something else.');
    }

    /**
     * The Excel import builds the request without a route, so there is no
     * company to look in — the same as the issuance date rule.
     */
    public function test_without_a_company_the_rule_has_no_opinion(): void
    {
        $rule = new BeneficiaryLinkedToOdooRule(null, LgTypes::FINAL_LGS, LetterOfGuaranteeIssuance::NEW_ISSUANCE, '2026-01-10', null);

        $this->assertTrue($rule->passes('partner_id', $this->unlinked->id));
    }

    public static function unreadableInputProvider(): array
    {
        return [
            'text as the issuance date' => [['issuance_date' => 'not-a-date']],
            'a month that does not exist' => [['issuance_date' => '13/45/2025']],
            'no issuance type' => [['category_name' => null]],
        ];
    }

    /**
     * Such an LG cannot be saved anyway; the rule must not turn the
     * validation into a server error.
     */
    #[DataProvider('unreadableInputProvider')]
    public function test_unreadable_input_is_left_to_its_own_checks(array $overrides): void
    {
        $this->assertFalse($this->validate($this->unlinked->id, $overrides)->fails());
    }

    // ---------------------------------------------------------------
    // wired into the forms
    // ---------------------------------------------------------------

    public static function requestProvider(): array
    {
        return [
            'store' => [StoreLetterOfGuaranteeIssuanceRequest::class],
            'update' => [UpdateLetterOfGuaranteeIssuanceRequest::class],
        ];
    }

    /**
     * Editing is covered too — and editing is how an Advance Payment LG
     * gets its Activation Date.
     */
    #[DataProvider('requestProvider')]
    public function test_the_request_applies_the_rule_to_the_beneficiary(string $requestClass): void
    {
        $rules = $requestClass::create('/', 'POST', $this->input(['partner_id' => $this->unlinked->id]))->rules();

        $this->assertContains('required', $rules['partner_id']);
        $this->assertNotEmpty(
            array_filter($rules['partner_id'], fn ($rule) => $rule instanceof BeneficiaryLinkedToOdooRule),
            'partner_id must carry BeneficiaryLinkedToOdooRule.'
        );
    }

    /**
     * End to end through the real route. transaction_name is left out on
     * purpose so validation always fails and nothing is saved — only the
     * beneficiary's error is under test.
     */
    public function test_saving_an_lg_with_an_unlinked_beneficiary_is_refused_with_the_reason(): void
    {
        $this->actingAs($this->odooUser($this->company, ['lg_issuance.create']));
        $url = route('store.letter.of.guarantee.issuance', ['company' => $this->company->id, 'source' => LetterOfGuaranteeIssuance::HUNDRED_PERCENTAGE_CASH_COVER]);

        $this->post($url, $this->input(['partner_id' => $this->unlinked->id]))
            ->assertSessionHasErrors(['partner_id' => $this->notLinkedMessage($this->unlinked)]);

        $this->post($url, $this->input(['partner_id' => $this->linked->id]))
            ->assertSessionHasErrors('transaction_name')
            ->assertSessionDoesntHaveErrors('partner_id');

        $this->assertDatabaseMissing('letter_of_guarantee_issuances', ['company_id' => $this->company->id]);
    }
}
