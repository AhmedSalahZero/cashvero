<?php

namespace Tests\Feature\Instructions;

use Tests\TestCase;

/**
 * Every screen in a documented area must carry the Instructions button.
 *
 * This exists because a hand-kept checklist failed twice: the Index pages
 * were wired while every create/edit form was left without a button, and
 * the gap was reported by a user rather than caught here. So nothing is
 * listed by hand — the test DISCOVERS the pages by walking the areas that
 * have a guide, and requires the button on each one a controller renders.
 *
 * Three things must line up for a button to actually work, and only the
 * first is something `npm run build` would ever complain about:
 *   1. the .vue page renders the button,
 *   2. the page DECLARES the prop — Vue silently drops undeclared props,
 *      so without this the href is undefined,
 *   3. EVERY render() of that page sends it — miss one branch and the
 *      button vanishes on that path alone.
 */
class GuideButtonCoverageTest extends TestCase
{
    /**
     * Page directories whose screens are covered by a written guide.
     */
    private const DOCUMENTED_AREAS = [
        'MoneyReceived', 'MoneyPayment', 'CashExpense', 'InternalMoneyTransfer',
        'BuyOrSellCurrencies', 'LetterOfGuaranteeIssuance', 'LetterOfCreditIssuance',
        'LcSettlementInternalMoneyTransfer', 'Factoring', 'FactoringContract',
        'FactoringWithRecourse', 'FactoringWithoutRecourse', 'Settings',
        'Balances', 'FinancialInstitutions', 'BankAccounts', 'TimeOfDeposits',
        'CertificatesOfDeposits', 'CleanOverdraft', 'FullySecuredOverdraft',
        'OverdraftAgainstCommercialPaper', 'OverdraftAgainstAssignmentOfContract',
        'LetterOfGuaranteeFacility', 'LetterOfCreditFacility', 'MediumTermLoan',
    ];

    /**
     * Pages exempt from the button, each with the reason.
     *
     * Keep this list short and justified. An entry here is a claim that
     * the screen genuinely should not carry a guide link — not a place to
     * park one that was forgotten.
     */
    private const EXEMPT = [
        // Never referenced by any controller; superseded by Index.vue.
        'LetterOfGuaranteeIssuance/LetterOfGuaranteeIssuance_Index' => 'dead file, rendered by nothing',

        /*
         * Print views render one record for paper. They carry no controls
         * to explain, and a button that cannot be pressed on a printout is
         * noise on the page — the guide belongs on the screen the reader
         * printed from.
         */
        'BuyOrSellCurrencies/Print' => 'print view — no controls to explain',
        'CashExpense/Print' => 'print view — no controls to explain',
        'InternalMoneyTransfer/Print' => 'print view — no controls to explain',
        'MoneyPayment/Print' => 'print view — no controls to explain',
        'MoneyReceived/Print' => 'print view — no controls to explain',
    ];

    /** Every page component some controller renders. */
    private function renderedPages(): array
    {
        $pages = [];
        $dir = app_path('Http/Controllers');
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

        foreach ($it as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            preg_match_all("/render\(\s*'([^']+)'/", file_get_contents($file->getPathname()), $m);
            foreach ($m[1] as $page) {
                $pages[$page] = true;
            }
        }

        return $pages;
    }

    /** Every .vue page inside a documented area. */
    private function documentedPages(): array
    {
        $pages = [];

        foreach (self::DOCUMENTED_AREAS as $area) {
            $dir = resource_path("js/Pages/{$area}");
            if (! is_dir($dir)) {
                continue;
            }
            foreach (glob("{$dir}/*.vue") as $file) {
                $pages["{$area}/".basename($file, '.vue')] = $file;
            }
        }

        ksort($pages);

        return $pages;
    }

    public function test_every_rendered_screen_in_a_documented_area_has_the_button(): void
    {
        $rendered = $this->renderedPages();
        $missing = [];

        foreach ($this->documentedPages() as $page => $file) {
            if (array_key_exists($page, self::EXEMPT) || ! isset($rendered[$page])) {
                continue;
            }

            $source = file_get_contents($file);

            if (! str_contains($source, 'v-if="instructionsUrl"')) {
                $missing[] = "{$page} — no Instructions button";
            }
        }

        $this->assertSame([], $missing, sprintf(
            "%d screen(s) in a documented area have no Instructions button:\n  %s\n"
            ."Add the button, or list the page in EXEMPT with a reason.",
            count($missing), implode("\n  ", $missing)
        ));
    }

    public function test_every_screen_with_the_button_also_declares_the_prop(): void
    {
        $undeclared = [];

        foreach ($this->documentedPages() as $page => $file) {
            $source = file_get_contents($file);

            if (! str_contains($source, 'v-if="instructionsUrl"')) {
                continue;
            }
            if (! preg_match('/^\s*instructionsUrl:\s*String,/m', $source)) {
                $undeclared[] = $page;
            }
        }

        $this->assertSame([], $undeclared,
            "These pages render the button but never declare the prop, so Vue drops it\n"
            ."and the link points nowhere:\n  ".implode("\n  ", $undeclared));
    }

    /**
     * The half that a page-level check cannot see: a page wired on its
     * create path but not its edit path still shows an empty button on
     * edit. Every render() of a button-bearing page must send the prop.
     */
    public function test_every_render_of_a_button_bearing_page_sends_the_link(): void
    {
        $needsLink = [];
        foreach ($this->documentedPages() as $page => $file) {
            if (str_contains(file_get_contents($file), 'v-if="instructionsUrl"')) {
                $needsLink[$page] = true;
            }
        }

        $silent = [];
        $dir = app_path('Http/Controllers');
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));

        foreach ($it as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $source = file_get_contents($file->getPathname());

            preg_match_all("/render\(\s*'([^']+)'\s*,/", $source, $m, PREG_OFFSET_CAPTURE);

            foreach ($m[1] as $i => [$page, $_]) {
                if (! isset($needsLink[$page])) {
                    continue;
                }
                // The props expression for this render call: from the comma
                // up to the start of the next render(), or 3000 chars.
                $start = $m[0][$i][1];
                $end = $m[0][$i + 1][1] ?? min($start + 3000, strlen($source));
                $call = substr($source, $start, $end - $start);

                if (str_contains($call, 'instructionsUrl')) {
                    continue;
                }

                // The props may come from a helper on the same class
                // (`render('X', $this->buildProps(...))`). That is a fine
                // place to set the link — follow it before complaining.
                if (preg_match('/\$this->(\w+)\(/', $call, $helper)) {
                    // From the helper's declaration to the next function
                    // declaration — a nested `}` must not cut it short.
                    $decl = preg_quote($helper[1], '/');
                    if (preg_match('/function\s+'.$decl.'\s*\(.*?(?=\n\s*(?:public|protected|private|static)?\s*function\s|\z)/s', $source, $body)
                        && str_contains($body[0], 'instructionsUrl')) {
                        continue;
                    }
                }

                $line = substr_count(substr($source, 0, $start), "\n") + 1;
                $silent[] = basename($file->getPathname()).":{$line}  renders {$page} without the link";
            }
        }

        $this->assertSame([], $silent, sprintf(
            "%d render call(s) show a page whose button has no destination:\n  %s\n",
            count($silent), implode("\n  ", $silent)
        ));
    }

    /** An exemption must name a page that exists, so the list cannot rot. */
    public function test_exemptions_refer_to_real_pages_and_stay_justified(): void
    {
        $stale = [];

        foreach (self::EXEMPT as $page => $reason) {
            $this->assertNotEmpty($reason, "{$page} is exempt with no reason given.");

            if (! file_exists(resource_path("js/Pages/{$page}.vue"))) {
                $stale[] = "{$page} (no such page — remove the exemption)";
            }
        }

        // A page exempted as "rendered by nothing" must still be rendered
        // by nothing; if somebody wires it up, it needs a button.
        $rendered = $this->renderedPages();
        foreach (self::EXEMPT as $page => $reason) {
            if (str_contains($reason, 'rendered by nothing') && isset($rendered[$page])) {
                $stale[] = "{$page} is now rendered by a controller — it needs the button";
            }
        }

        $this->assertSame([], $stale, "Stale exemptions:\n  ".implode("\n  ", $stale));
    }

    /**
     * A screen must point at a guide that describes THAT screen.
     *
     * The drill-downs were first wired to their parent's guide — so the
     * invoice report opened a page explaining the balances list, which is
     * not the screen the reader is looking at. `npm run build` is happy
     * with that, every guide still returns 200, and only a person reading
     * the text notices. Hence this map.
     *
     * @dataProvider screenGuideProvider
     */
    public function test_a_screen_points_at_the_guide_that_describes_it(string $controller, string $page, string $guideConst): void
    {
        $source = file_get_contents(app_path("Http/Controllers/{$controller}.php"));

        $this->assertStringContainsString("PageInstructions::{$guideConst}", $source,
            "{$page} must open the guide that describes it (PageInstructions::{$guideConst}), "
            .'not the guide of the screen it was reached from.');

        $key = constant(\App\Support\Instructions\PageInstructions::class."::{$guideConst}");
        $guide = \App\Support\Instructions\PageInstructions::get($key);

        $this->assertNotNull($guide, "{$guideConst} has no content.");
        $this->assertNotEmpty($guide['sections'], "{$guideConst} is empty.");
    }

    public static function screenGuideProvider(): array
    {
        return [
            'Invoice Report' => ['CustomerInvoiceDashboardController', 'Balances/InvoiceReport', 'INVOICE_REPORT'],
            'Statement Report' => ['CustomerInvoiceDashboardController', 'Balances/Statement', 'INVOICE_STATEMENT'],
            'Net Balance Details' => ['BalancesController', 'Balances/TotalNetBalanceDetails', 'NET_BALANCE_DETAILS'],
            'Down Payment Settlement' => ['DownPaymentContractsController', 'Balances/DownPaymentContracts', 'DOWN_PAYMENT_SETTLEMENT'],
            'Adjust Due Date' => ['AdjustedDueDateHistoriesController', 'Balances/AdjustDueDateHistory', 'ADJUST_DUE_DATE'],
            'TD Period Interest' => ['TimeOfDepositsController', 'TimeOfDeposits/PeriodInterest', 'TD_PERIOD_INTEREST'],
            'TD Renewal History' => ['TimeOfDepositRenewalDateController', 'TimeOfDeposits/RenewalHistory', 'TD_RENEWAL_HISTORY'],
            'CD Period Interest' => ['CertificatesOfDepositsController', 'CertificatesOfDeposits/PeriodInterest', 'CD_PERIOD_INTEREST'],
            'MTL Statement' => ['MediumTermLoanController', 'MediumTermLoan/Statement', 'MTL_STATEMENT'],
            'LG Renewal History' => ['LetterOfGuaranteeIssuanceRenewalDateController', 'LetterOfGuaranteeIssuance/RenewalHistory', 'LG_RENEWAL_HISTORY'],
        ];
    }

    /**
     * Two screens sharing one guide is how the borrowing happened. Each
     * guide key may serve one screen only — except the balances list,
     * which genuinely is one component rendered for two sides.
     */
    public function test_no_two_screens_share_a_guide_by_accident(): void
    {
        $used = [];
        foreach (self::screenGuideProvider() as $label => [$controller, $page, $guide]) {
            $used[$guide][] = $page;
        }

        $shared = array_filter($used, fn ($pages) => count($pages) > 1);

        $report = [];
        foreach ($shared as $guide => $pages) {
            $report[] = "{$guide} is used by: ".implode(', ', $pages);
        }

        $this->assertSame([], $report, "Each drill-down needs its own guide:\n  ".implode("\n  ", $report));
    }

    /**
     * A list screen and the form that feeds it ask different questions —
     * "what is this row?" versus "what do I type here?" — so they must not
     * share a guide.
     *
     * They did. Twenty-six form screens opened the list's guide, which
     * explains none of their fields. Nothing caught it: the build passes,
     * every guide returns 200, and only a person reading the page notices.
     */
    public function test_no_form_screen_borrows_a_list_screens_guide(): void
    {
        $byGuide = [];

        foreach ($this->renderCallsWithGuides() as [$page, $guide]) {
            $byGuide[$guide][$page] = true;
        }

        $shared = [];
        foreach ($byGuide as $guide => $pages) {
            $pages = array_keys($pages);
            $lists = array_filter($pages, fn ($p) => str_ends_with($p, '/Index'));
            $forms = array_filter($pages, fn ($p) => ! str_ends_with($p, '/Index'));

            if ($lists && $forms) {
                $shared[] = $guide.' — list('.implode(', ', $lists).') and form('.implode(', ', $forms).')';
            }
        }

        $this->assertSame([], $shared, sprintf(
            "%d guide(s) serve both a list screen and a form:\n  %s\n"
            ."Give the form its own guide describing its fields.",
            count($shared), implode("\n  ", $shared)
        ));
    }

    /** @return array<int, array{0:string,1:string}> [page, guide constant] */
    private function renderCallsWithGuides(): array
    {
        $out = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path('Http/Controllers')));

        foreach ($it as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $src = file_get_contents($file->getPathname());
            preg_match_all("/render\(\s*'([^']+)'\s*,/", $src, $m, PREG_OFFSET_CAPTURE);

            foreach ($m[1] as $i => [$page, $_]) {
                $start = $m[0][$i][1];
                $end = $m[0][$i + 1][1] ?? min($start + 1500, strlen($src));
                if (preg_match('/PageInstructions::([A-Z_]+)/', substr($src, $start, $end - $start), $g)) {
                    $out[] = [$page, $g[1]];
                }
            }
        }

        return $out;
    }
}
