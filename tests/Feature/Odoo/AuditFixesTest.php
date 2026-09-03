<?php

namespace Tests\Feature\Odoo;

use App\Services\Api\OdooService;
use Tests\TestCase;

/**
 * The audit fixes, pinned.
 *
 * The Odoo posting ones matter most: the failures they cover are silent by
 * nature — Odoo refuses to post, CashVero records success, and nothing on
 * either side says so until someone reconciles the ledger by hand.
 */
class AuditFixesTest extends TestCase
{
    private function odooPaymentSource(): string
    {
        return file_get_contents(app_path('Services/Api/OdooPayment.php'));
    }

    /* ── H-3: the create fault must be checked BEFORE posting ─────── */

    public function test_the_create_fault_is_checked_before_the_post(): void
    {
        $src = $this->odooPaymentSource();

        // In each create/post pair the faultString guard must come first.
        preg_match_all("/'create',.*?(?=public function|\z)/s", $src, $blocks);

        $offenders = [];
        foreach ($blocks[0] as $i => $block) {
            $post = strpos($block, 'postOrFail(');
            $guard = strpos($block, "faultString']");

            if ($post === false || $guard === false) {
                continue;
            }
            if ($guard > $post) {
                $offenders[] = "create block #{$i}: the fault check sits after the post";
            }
        }

        $this->assertSame([], $offenders, implode("\n  ", $offenders));
    }

    /**
     * No raw action_post may remain: create() can answer with a fault array,
     * and passing that to action_post sends a fault where an id belongs.
     */
    public function test_no_unguarded_action_post_remains(): void
    {
        $src = $this->odooPaymentSource();

        // Strip comments — the docblock explaining this quotes the name.
        $code = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $src);

        preg_match_all("/'action_post'/", $code, $m);

        $this->assertCount(1, $m[0],
            "action_post must appear exactly once, inside postOrFail(). Every other call site "
            ."discarded its result, so a payment Odoo refused to post was still marked synced.");
    }

    /* ── H-2: a refused post must stop the caller ─────────────────── */

    /** @dataProvider refusalProvider */
    public function test_a_refused_post_throws_instead_of_reporting_success($odooAnswer, string $why): void
    {
        $service = $this->fakeOdooPayment($odooAnswer);

        $this->expectException(\Exception::class);

        $method = new \ReflectionMethod($service, 'postOrFail');
        $method->setAccessible(true);
        $method->invoke($service, 4242, 'account.payment');

        $this->fail($why);
    }

    public static function refusalProvider(): array
    {
        return [
            'Odoo refuses to post' => [
                ['faultString' => 'You cannot post an entry in a closed period.'],
                'A closed period must stop the caller marking the record synced.',
            ],
        ];
    }

    public function test_a_create_fault_never_reaches_action_post(): void
    {
        $service = $this->fakeOdooPayment(true);
        $method = new \ReflectionMethod($service, 'postOrFail');
        $method->setAccessible(true);

        try {
            // What create() returns when it fails.
            $method->invoke($service, ['faultString' => 'Missing journal'], 'account.payment');
            $this->fail('A create fault must abort before posting.');
        } catch (\Exception $e) {
            $this->assertStringContainsString('Missing journal', $e->getMessage());
        }

        $this->assertSame(0, $service->postCalls,
            'action_post must not be called at all when create returned a fault.');
    }

    public function test_a_successful_post_returns_the_id(): void
    {
        $service = $this->fakeOdooPayment(true);
        $method = new \ReflectionMethod($service, 'postOrFail');
        $method->setAccessible(true);

        $this->assertSame(4242, $method->invoke($service, '4242', 'account.payment'));
        $this->assertSame(1, $service->postCalls);
    }

    /** An OdooPayment whose Odoo call is replaced by a canned answer. */
    private function fakeOdooPayment($answer)
    {
        return new class($answer) extends \App\Services\Api\OdooPayment
        {
            public int $postCalls = 0;

            private $answer;

            public function __construct($answer)
            {
                // Deliberately not calling parent::__construct(): this test
                // exercises postOrFail()'s decision, not the connection.
                $this->answer = $answer;
            }

            public function execute($model, $method, $args, $kwargs = [])
            {
                $this->postCalls++;

                return $this->answer;
            }
        };
    }

    /* ── H-4: no raw Odoo text reaches the user ───────────────────── */

    public function test_no_raw_odoo_message_is_flashed(): void
    {
        $src = $this->odooPaymentSource();
        $offenders = [];

        foreach (explode("\n", $src) as $i => $line) {
            // Both spellings reach the screen: one codebase flashes, the
            // other puts. An assertion on only one of them passed while the
            // other still leaked.
            if (! str_contains($line, "flash('fail'") && ! str_contains($line, "put('fail'")) {
                continue;
            }
            if (str_contains($line, 'OdooSync::userFacingMessage')) {
                continue;
            }
            // The one hand-written, translated message is fine: it names a
            // journal id and nothing else.
            if (str_contains($line, '$message')) {
                continue;
            }
            $offenders[] = 'line '.($i + 1).': '.trim($line);
        }

        $this->assertSame([], $offenders,
            "These flash raw Odoo text, which carries the internal host:\n  ".implode("\n  ", $offenders));
    }

    public function test_a_dynamic_message_is_never_used_as_a_translation_key(): void
    {
        $src = $this->odooPaymentSource();

        $this->assertDoesNotMatchRegularExpression(
            "/__\('[^']*'\s*\.\s*\\\$/",
            $src,
            'Concatenating inside __() makes the whole dynamic string the translation key, '
            .'so it can never resolve — and it carried the raw error with it.'
        );
    }

    /* ── M-3: the invoice import asks for the fields it uses ──────── */

    public function test_the_invoice_import_names_its_fields(): void
    {
        $this->assertNotEmpty(OdooService::INVOICE_FIELDS,
            'An empty field list makes Odoo return every field of account.move.');
    }

    public function test_every_field_the_import_reads_is_requested(): void
    {
        $src = file_get_contents(app_path('Services/Api/OdooService.php'));

        $start = strpos($src, 'function startImportInvoices');
        $end = strpos($src, 'protected function getInvoices');
        preg_match_all("/\\\$invoice\['([a-z_]+)'\]/", substr($src, $start, $end - $start), $m);

        $missing = array_values(array_diff(array_unique($m[1]), OdooService::INVOICE_FIELDS));

        $this->assertSame([], $missing,
            "The import reads these but does not request them, so they arrive undefined:\n  "
            .implode("\n  ", $missing));
    }

    public function test_the_deletion_sweep_reads_ids_only(): void
    {
        $src = file_get_contents(app_path('Services/Api/OdooService.php'));

        $start = strpos($src, 'function syncDeletedInvoices');
        $this->assertNotFalse($start);

        $this->assertMatchesRegularExpression(
            "/getInvoices\(\\\$startDate,\s*\\\$endDate,\s*\['id'\]\)/",
            substr($src, $start, 1200),
            'It only compares ids, so it must not pull whole invoice rows.'
        );
    }

    /* ── M-2: a removed partner must not break the page ───────────── */

    public function test_partner_lookups_are_guarded(): void
    {
        $offenders = [];

        foreach (['BalancesController', 'OpeningBalancesController'] as $controller) {
            $src = file_get_contents(app_path("Http/Controllers/{$controller}.php"));

            foreach (explode("\n", $src) as $i => $line) {
                if (preg_match('/(?<!optional\()Partner::find\([^)]*\)->/', $line)) {
                    $offenders[] = "{$controller}:".($i + 1);
                }
            }
        }

        $this->assertSame([], $offenders,
            "An unguarded Partner::find(...)-> calls a method on null when the partner row is "
            ."gone:\n  ".implode("\n  ", $offenders));
    }

    /* ── M-1: the cheque state transition is atomic ───────────────── */

    public function test_returning_a_cheque_to_under_collection_is_atomic(): void
    {
        $src = file_get_contents(app_path('Http/Controllers/MoneyReceivedController.php'));

        $start = strpos($src, 'function sendToUnderCollection(');
        $this->assertNotFalse($start);
        $body = substr($src, $start, 3000);

        $this->assertStringContainsString('OdooSync::transaction(', $body,
            'The status change and the statement deletions must commit together, or the bank '
            .'balance can disagree with the cheque state.');

        $statusChange = strpos($body, "cheque->update(\$updateChequeData)");
        $transaction = strpos($body, 'OdooSync::transaction(');

        $this->assertGreaterThan($transaction, $statusChange,
            'The status change must happen inside the transaction, not before it.');
    }
}
