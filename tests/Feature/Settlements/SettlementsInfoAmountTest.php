<?php

namespace Tests\Feature\Settlements;

use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The Settlement Details modal (SettlementsInfoButton.vue) must show the
 * invoice amount AFTER tax.
 *
 * It used to show getInvoiceAmount() — the raw pre-VAT column — while the
 * Money Received / Money Payment form screens show net_invoice_amount under
 * the very same "Invoice Amount" label, and while the settlement itself is
 * measured against the net (net_balance). So the same label gave two
 * different numbers, and a settlement could look larger than the "invoice
 * amount" printed next to it.
 *
 *      net_invoice_amount = invoice_amount + vat_amount − discount_amount
 *
 * Both Money Received and Money Payment are covered by one test each because
 * they share getSettlementsInfo() in the IsMoney trait but resolve different
 * invoice models (CustomerInvoice vs SupplierInvoice).
 */
class SettlementsInfoAmountTest extends TestCase
{
    private ?string $originalDatabase = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDatabase = config('database.connections.mysql.database');

        config(['database.connections.mysql.database' => env('SMOKE_DB', 'cashvero')]);
        DB::purge('mysql');

        try {
            DB::connection('mysql')->getPdo();
        } catch (\Throwable $e) {
            $this->markTestSkipped('Development database not reachable.');
        }
    }

    protected function tearDown(): void
    {
        config(['database.connections.mysql.database' => $this->originalDatabase]);
        DB::purge('mysql');

        parent::tearDown();
    }

    /**
     * Finds a money row whose first settlement points at an invoice carrying
     * VAT — without VAT the pre-tax and post-tax numbers are equal and the
     * assertion would pass either way.
     */
    private function findWithTaxedInvoice(string $class): ?array
    {
        foreach ($class::has('settlements')->limit(400)->cursor() as $money) {
            foreach ($money->settlements as $index => $settlement) {
                $invoice = $settlement->invoice;

                if ($invoice && (float) $invoice->vat_amount > 0) {
                    return [$money, $index, $invoice];
                }
            }
        }

        return null;
    }

    private function assertShowsAmountAfterTax(string $class, string $label): void
    {
        $found = $this->findWithTaxedInvoice($class);

        if (! $found) {
            $this->markTestSkipped("No {$label} settlement whose invoice carries VAT.");
        }

        [$money, $index, $invoice] = $found;

        $rows = $money->getSettlementsInfo()['rows'];

        $this->assertSame(
            number_format((float) $invoice->getNetInvoiceAmount(), 2),
            $rows[$index]['invoice_amount'],
            "{$label}#{$money->id} must show the invoice amount after tax (net_invoice_amount)."
        );

        $this->assertNotSame(
            number_format((float) $invoice->getInvoiceAmount(), 2),
            $rows[$index]['invoice_amount'],
            'The pre-tax figure is what this fix removed — seeing it back means the regression returned.'
        );

        // The relationship the fix rests on, stated explicitly so a change in
        // how net is derived shows up here rather than silently.
        $this->assertEqualsWithDelta(
            (float) $invoice->invoice_amount + (float) $invoice->vat_amount - (float) $invoice->discount_amount,
            (float) $invoice->getNetInvoiceAmount(),
            0.01,
            'net_invoice_amount is expected to be amount + VAT − discount.'
        );
    }

    public function test_money_received_settlement_details_show_the_amount_after_tax(): void
    {
        $this->assertShowsAmountAfterTax(MoneyReceived::class, 'MoneyReceived');
    }

    public function test_money_payment_settlement_details_show_the_amount_after_tax(): void
    {
        $this->assertShowsAmountAfterTax(MoneyPayment::class, 'MoneyPayment');
    }

    /**
     * The modal shows one amount column, not two — the request was to switch
     * it to the after-tax figure, not to add a second column beside it.
     *
     * The two systems render this modal differently (Vue component here, a
     * Blade partial in the other), but both read the same `invoice_amount`
     * key off the same JSON, so whichever template exists is the one checked.
     */
    public function test_the_modal_shows_a_single_amount_column(): void
    {
        $candidates = [
            resource_path('js/Components/SettlementsInfoButton.vue'),
            resource_path('views/reports/_settlements_info_modal.blade.php'),
        ];

        $template = null;

        foreach ($candidates as $path) {
            if (is_file($path)) {
                $template = $path;
                break;
            }
        }

        $this->assertNotNull($template, 'Neither the Vue nor the Blade settlement modal template was found.');

        $markup = file_get_contents($template);

        $this->assertSame(1, substr_count($markup, 'row.invoice_amount'),
            'There must be exactly one invoice amount cell in the modal: '.basename($template));

        $this->assertStringNotContainsString('row.net_invoice_amount', $markup,
            'The backend key stays invoice_amount — only its value changed, so the template needs no new key.');
    }

    /**
     * A settlement whose invoice row is missing must still render as 0.00
     * rather than blowing up — the guard that was there before the change
     * has to survive it.
     */
    public function test_a_missing_invoice_still_renders_zero(): void
    {
        $source = file_get_contents(app_path('Traits/Models/IsMoney.php'));

        $this->assertStringContainsString(
            "'invoice_amount' => number_format((float) (\$invoice ? \$invoice->getNetInvoiceAmount() : 0), 2)",
            $source,
            'The null-invoice guard must stay in place around the after-tax amount.'
        );
    }
}
