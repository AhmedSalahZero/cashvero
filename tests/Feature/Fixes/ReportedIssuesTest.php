<?php

namespace Tests\Feature\Fixes;

use App\Support\Instructions\PageInstructions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression cover for a batch of reported issues.
 *
 * The one that matters most is the currency select on the invoice
 * create form: it was writing the TRANSLATED currency name into the
 * `currency` column under /ar. Every balances screen, invoice report
 * and statement groups by that exact string, so an invoice saved in
 * Arabic would have become a currency of its own — one customer's
 * balance silently split in two.
 */
class ReportedIssuesTest extends TestCase
{
    /* ── the currency corruption ──────────────────────────────────── */

    /**
     * getCurrencies() returns [code => translated label]. Anything that
     * flattens it must keep the CODE as the value.
     */
    public function test_currency_options_keep_the_code_as_the_stored_value(): void
    {
        app()->setLocale('ar');

        $options = getCurrencies();

        $this->assertArrayHasKey('EGP', $options, 'The currency code must survive as the option value.');
        $this->assertMatchesRegularExpression('/[\x{0600}-\x{06FF}]/u', $options['EGP'],
            'The displayed label should be translated under /ar.');

        foreach (array_keys($options) as $code) {
            $this->assertDoesNotMatchRegularExpression('/[\x{0600}-\x{06FF}]/u', $code,
                "Currency value '{$code}' contains Arabic — it would be written to the database as a new currency.");
        }
    }

    public function test_the_invoice_form_does_not_flatten_currency_options_onto_their_labels(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/SalesGatheringTestController.php'));

        // Strip comments first — the docblock explaining this bug quotes
        // the broken line, and an assertion on the raw file would fail on
        // its own documentation.
        $code = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $src);

        $this->assertStringNotContainsString(
            'mapWithKeys(fn ($c) => [$c => $c])',
            $code,
            'mapWithKeys with a single-argument closure receives the VALUE, so [$c => $c] discards '
            .'the currency code. Under /ar the option value becomes the Arabic name and is written '
            .'to the database as the currency.'
        );

        $start = strpos($code, "\$fieldName === 'currency'");
        $this->assertNotFalse($start, 'The currency branch is gone.');
        $this->assertStringContainsString('$options = getCurrencies();', substr($code, $start, 400),
            'getCurrencies() is already [code => label]; pass it through rather than rebuilding it.');
    }

    public function test_the_currency_select_binds_the_code_not_the_label(): void
    {
        $vue = file_get_contents(resource_path('js/Pages/InvoiceUpload/InvoiceForm.vue'));

        $this->assertMatchesRegularExpression(
            '/currency_select.*?<option v-for="\(name, code\) in f\.options"[^>]*:value="code"/s',
            $vue,
            'The option value must be the currency code; only the displayed name may be translated.'
        );
    }

    /**
     * The live data must not already contain a translated currency —
     * if it does, those rows need correcting, not just the form.
     */
    public function test_no_invoice_has_a_translated_currency_stored(): void
    {
        config(['database.connections.mysql.database' => env('SMOKE_DB', 'cashvero')]);
        DB::purge('mysql');

        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Development database not reachable.');
        }

        $bad = [];
        foreach (['customer_invoices', 'supplier_invoices'] as $table) {
            foreach (DB::connection('mysql')->table($table)->distinct()->pluck('currency') as $currency) {
                if ($currency !== null && preg_match('/[\x{0600}-\x{06FF}]/u', (string) $currency)) {
                    $bad[] = "{$table}: {$currency}";
                }
            }
        }

        $this->assertSame([], $bad,
            "These rows store a translated currency name instead of a code:\n  ".implode("\n  ", $bad));
    }

    /* ── the smaller fixes ────────────────────────────────────────── */

    public function test_the_create_item_field_labels_are_translated(): void
    {
        $vue = file_get_contents(resource_path('js/Pages/InvoiceUpload/InvoiceForm.vue'));

        $this->assertStringContainsString('{{ $t(f.label) }}', $vue,
            'Field labels come from the server in English; without $t() the form stays English under /ar.');
        $this->assertStringNotContainsString('">{{ f.label }}</label>', $vue);
    }

    public function test_deduct_is_hidden_on_a_settled_invoice(): void
    {
        $vue = file_get_contents(resource_path('js/Pages/Balances/InvoiceReport.vue'));

        $this->assertMatchesRegularExpression(
            '/v-if="invoice\.net_balance > 0"[^>]*@click="openDeductModal\(invoice\)"/s',
            $vue,
            'A deduction only has meaning against an open balance, so the button must not show at zero.'
        );
    }

    public function test_settled_invoices_are_not_offered_for_down_payment_settlement(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/DownPaymentContractsController.php'));

        $this->assertMatchesRegularExpression(
            "/->where\('net_balance',\s*'>',\s*0\)/",
            $src,
            'An invoice with nothing outstanding cannot absorb a down payment, so it must be filtered out.'
        );
        $this->assertStringNotContainsString('was never actually applied', $src,
            'The note describing the filter as unreachable should be gone now that it is applied.');
    }

    public function test_deleting_a_renewal_returns_to_the_issuance_list(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/LetterOfGuaranteeIssuanceRenewalDateController.php'));

        $start = strpos($src, 'public function destroy(');
        $this->assertNotFalse($start);
        $body = substr($src, $start);

        $this->assertStringContainsString("route('view.letter.of.guarantee.issuance'", $body,
            'Deleting the last renewal also removes the row above it, so the history page it came from '
            .'may no longer have a subject — go back to the issuance list.');
    }

    /* ── the LG renewal cash cover question ───────────────────────── */

    /**
     * A renewal that changes the cash cover posts the DIFFERENCE, whatever
     * the LG's origin.
     *
     * This test previously asserted the opposite for an opening-balance
     * LG, and that was wrong. The two events are separate: the original
     * cover was never posted because it is already inside the opening
     * balance — correct — but raising it on renewal is a transaction
     * happening now, and the bank really does take the extra.
     *
     * A cover held against a CD/TD is still skipped: it is secured by the
     * deposit itself and never moves through a current account.
     */
    public function test_only_a_cd_or_td_cover_skips_the_renewal_difference(): void
    {
        $src = file_get_contents(app_path('Support/LetterOfGuarantee/LgRenewalTerms.php'));

        $start = strpos($src, 'function postCashCoverDifference(');
        $this->assertNotFalse($start, 'postCashCoverDifference() is gone.');
        $body = substr($src, $start, 2000);

        $this->assertStringContainsString('isCdOrTd()', $body,
            'A CD/TD-backed cover never moves through a current account, so it stays skipped.');
        $this->assertStringNotContainsString('isOpeningBalance()', $body,
            'An opening-balance LG must NOT be skipped: its renewal difference is real money '
            .'moving today, and skipping it left that money missing from both ledgers.');
    }

    /**
     * And that when it DOES apply, the difference reaches both places —
     * the cash-cover ledger and the current account.
     */
    public function test_renewal_cash_cover_posts_to_both_ledgers_when_it_applies(): void
    {
        $src = file_get_contents(app_path('Support/LetterOfGuarantee/LgRenewalTerms.php'));

        $start = strpos($src, 'function postCashCoverDifference(');
        $body = substr($src, $start, 2500);

        $this->assertStringContainsString('handleLetterOfGuaranteeCashCoverStatement', $body,
            'The LG cash-cover ledger must receive the difference.');
        $this->assertStringContainsString('CurrentAccountBankStatement::create', $body,
            'The current account the cover is deducted from must receive it too.');
        $this->assertStringContainsString("'is_renewal_cash_cover' => 1", $body,
            'The bank row must be tagged so it can be found and reversed on delete.');
    }
}
