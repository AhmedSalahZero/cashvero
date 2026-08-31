<?php

namespace Tests\Feature\Instructions;

use App\Support\Instructions\PageInstructions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * "Do not leave any account type."
 *
 * The account types are rows in `account_types`, not a list in the code,
 * so a hand-written checklist here would drift the moment somebody adds
 * a twelfth type. This test asks the DATABASE what the types are and
 * requires a guide for every one that has a screen behind it.
 *
 * A type with no model has nothing to explain, so it is exempted — but
 * only by name, so the exemption itself has to be justified rather than
 * silently swallowing a type somebody forgot.
 *
 * Runs against the development schema; skips itself when unreachable.
 */
class AccountTypeGuideCoverageTest extends TestCase
{
    /**
     * account_types.slug → the guide that explains it.
     */
    private const GUIDE_FOR_SLUG = [
        'current-account' => PageInstructions::CURRENT_ACCOUNT,
        'time-of-deposit-td' => PageInstructions::TIME_OF_DEPOSIT,
        'certificate-of-deposit-cd' => PageInstructions::CERTIFICATE_OF_DEPOSIT,
        'fully-secured-overdraft' => PageInstructions::FULLY_SECURED_OVERDRAFT,
        'clean-overdraft' => PageInstructions::CLEAN_OVERDRAFT,
        'overdraft-against-commercial-paper' => PageInstructions::OVERDRAFT_COMMERCIAL_PAPER,
        'overdraft-against-assignment-of-contracts' => PageInstructions::OVERDRAFT_ASSIGNMENT_OF_CONTRACTS,
        'letter-of-guarantee-lgs' => PageInstructions::LG_FACILITY,
        'letter-of-credit-lcs' => PageInstructions::LC_FACILITY,
        'medium-term-loan' => PageInstructions::MEDIUM_TERM_LOAN,
    ];

    /**
     * Types with no screen to document, and why.
     *
     * `discounting-cheques` is a row in account_types with no model,
     * controller or Vue page anywhere in the codebase — there is nothing
     * for a reader to be guided through. If it is ever built, this entry
     * must come out and a guide must go in.
     */
    private const NO_SCREEN_YET = [
        'discounting-cheques' => 'DiscountCheque',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.connections.mysql.database' => env('SMOKE_DB', 'cashvero')]);
        DB::purge('mysql');

        try {
            DB::connection('mysql')->getPdo();
            DB::connection('mysql')->table('account_types')->exists();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Development schema not reachable: '.$e->getMessage());
        }
    }

    /** @return array<string, string> slug => English name */
    private function accountTypes(): array
    {
        return DB::connection('mysql')->table('account_types')
            ->orderBy('id')->pluck('name_en', 'slug')->all();
    }

    public function test_every_account_type_in_the_database_is_either_documented_or_declared_unbuilt(): void
    {
        $undocumented = [];

        foreach ($this->accountTypes() as $slug => $name) {
            if (array_key_exists($slug, self::NO_SCREEN_YET)) {
                continue;
            }

            if (! array_key_exists($slug, self::GUIDE_FOR_SLUG)) {
                $undocumented[] = "{$name} ({$slug})";
            }
        }

        $this->assertSame([], $undocumented,
            "These account types exist in the database but have no guide:\n  "
            .implode("\n  ", $undocumented)
            ."\nAdd one to PageInstructions, or list it in NO_SCREEN_YET with the reason.");
    }

    public function test_every_mapped_guide_actually_exists_and_has_content(): void
    {
        $broken = [];

        foreach (self::GUIDE_FOR_SLUG as $slug => $key) {
            if (! PageInstructions::has($key)) {
                $broken[] = "{$slug} → {$key} (not a registered guide)";

                continue;
            }

            $page = PageInstructions::get($key);

            if (empty($page['sections'])) {
                $broken[] = "{$slug} → {$key} (guide is empty)";
            }
        }

        $this->assertSame([], $broken, "Broken account-type guide mappings:\n  ".implode("\n  ", $broken));
    }

    /**
     * The exemption must stay honest: if somebody builds the screen, the
     * "nothing to document" reason stops being true and this fails.
     */
    public function test_a_type_declared_unbuilt_really_has_no_screen(): void
    {
        $nowBuilt = [];

        foreach (self::NO_SCREEN_YET as $slug => $modelName) {
            $hasModel = file_exists(app_path("Models/{$modelName}.php"));
            $hasPages = is_dir(resource_path("js/Pages/{$modelName}"));

            if ($hasModel || $hasPages) {
                $nowBuilt[] = "{$slug} ({$modelName})";
            }
        }

        $this->assertSame([], $nowBuilt,
            "These account types now have a screen, so they need a guide:\n  "
            .implode("\n  ", $nowBuilt)
            ."\nRemove them from NO_SCREEN_YET and add the guide.");
    }

    /**
     * Balances and Financial Institutions are not account types, so the
     * loop above cannot catch them. Pin them explicitly.
     */
    public function test_the_balances_and_institution_guides_exist(): void
    {
        foreach ([
            PageInstructions::CUSTOMER_BALANCES,
            PageInstructions::SUPPLIER_BALANCES,
            PageInstructions::FINANCIAL_INSTITUTIONS,
        ] as $key) {
            $this->assertTrue(PageInstructions::has($key), "{$key} is not registered.");
            $this->assertNotEmpty(PageInstructions::get($key)['sections'], "{$key} is empty.");
        }
    }

    /**
     * A guide nobody can reach is not delivered. Both halves must hold:
     * the controller sends `instructionsUrl`, and the page declares the
     * prop — the second is the one `npm run build` will not catch, and
     * the one that has broken before.
     *
     * @dataProvider wiredScreenProvider
     */
    public function test_each_screen_sends_and_declares_the_guide_link(string $controller, string $page): void
    {
        $controllerSource = file_get_contents(app_path("Http/Controllers/{$controller}.php"));
        $pageSource = file_get_contents(resource_path("js/Pages/{$page}.vue"));

        $this->assertStringContainsString("'instructionsUrl' => route('view.instructions'", $controllerSource,
            "{$controller} does not send instructionsUrl, so the button on {$page} has nowhere to go.");

        $this->assertMatchesRegularExpression('/^\s*instructionsUrl:\s*String,/m', $pageSource,
            "{$page}.vue does not declare the instructionsUrl prop. Vue silently drops undeclared "
            .'props, so the button renders with an undefined href.');

        $this->assertStringContainsString('v-if="instructionsUrl"', $pageSource,
            "{$page}.vue has no Instructions button.");
    }

    public static function wiredScreenProvider(): array
    {
        return [
            'Customer & Supplier Balances' => ['BalancesController', 'Balances/Index'],
            'Financial Institutions' => ['FinancialInstitutionController', 'FinancialInstitutions/Index'],
            'Time Of Deposit' => ['TimeOfDepositsController', 'TimeOfDeposits/Index'],
            'Certificate Of Deposit' => ['CertificatesOfDepositsController', 'CertificatesOfDeposits/Index'],
            'Clean Overdraft' => ['CleanOverdraftController', 'CleanOverdraft/Index'],
            'Fully Secured Overdraft' => ['FullySecuredOverdraftController', 'FullySecuredOverdraft/Index'],
            'Overdraft Against Commercial Paper' => ['OverdraftAgainstCommercialPaperController', 'OverdraftAgainstCommercialPaper/Index'],
            'Overdraft Against Assignment Of Contracts' => ['OverdraftAgainstAssignmentOfContractController', 'OverdraftAgainstAssignmentOfContract/Index'],
            'LG Facility' => ['LetterOfGuaranteeFacilityController', 'LetterOfGuaranteeFacility/Index'],
            'LC Facility' => ['LetterOfCreditFacilityController', 'LetterOfCreditFacility/Index'],
            'Medium Term Loan' => ['MediumTermLoanController', 'MediumTermLoan/Index'],
        ];
    }

    /**
     * The Current Account guide hangs off the bank-accounts screen, which
     * FinancialInstitutionController renders under a different component
     * name — so the provider above cannot reach it.
     *
     * This replaced a check on backUrlFor()'s arity, which passed while
     * the screen had no button at all. Assert the wiring, not the shape.
     */
    public function test_the_current_account_screen_is_wired(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/FinancialInstitutionController.php'));
        $page = file_get_contents(resource_path('js/Pages/BankAccounts/Index.vue'));

        $this->assertMatchesRegularExpression(
            "/render\('BankAccounts\/Index'.{0,400}PageInstructions::CURRENT_ACCOUNT/s",
            $controller,
            'The bank-accounts screen must send the Current Account guide link.'
        );
        $this->assertMatchesRegularExpression('/^\s*instructionsUrl:\s*String,/m', $page,
            'BankAccounts/Index.vue must declare the instructionsUrl prop.');
        $this->assertStringContainsString('v-if="instructionsUrl"', $page,
            'BankAccounts/Index.vue must render the Instructions button.');
    }

    /**
     * Account-type guides sit under one bank, so Back needs that bank.
     * Without the third parameter every one of them returns the reader
     * to the institutions list instead of the screen they came from.
     */
    public function test_back_from_an_account_guide_can_return_to_its_bank(): void
    {
        $reflection = new \ReflectionMethod(\App\Http\Controllers\InstructionsController::class, 'backUrlFor');

        $names = array_map(fn ($p) => $p->getName(), $reflection->getParameters());

        // Assert on the parameter that matters, not the count — the count
        // changed the moment Balances drill-downs needed a modelType too,
        // and an arity check has nothing to say about correctness.
        $this->assertContains('institutionId', $names,
            'backUrlFor must accept the financial institution, or account-type guides '
            .'cannot return to the screen the reader came from. Parameters: '.implode(', ', $names));
    }

}
