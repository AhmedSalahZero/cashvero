<?php

namespace Tests\Feature\Dashboard;

use App\ReadyFunctions\ChequeAgingService;
use App\ReadyFunctions\InvoiceAgingService;
use Tests\TestCase;

/**
 * The cheque charts on the Forecast dashboard now use the SAME chart as
 * the invoice aging charts (AgingDivergingBarChart) instead of a donut.
 *
 * ChequeAgingService already computed the identical past_due /
 * current_due / coming_due bucket structure that InvoiceAgingService
 * does — it just never emitted it in the {region, state, sales} shape
 * the bar chart reads, so the page had to fall back to a flat
 * date/amount donut that showed coming-due only.
 *
 * The bucket -> chart formatting now lives once in IsAgingService and
 * both services call it, so the two charts cannot drift apart.
 *
 * @see \App\Traits\Services\IsAgingService::formatAgingBucketsForChart()
 */
class ChequeAgingChartTest extends TestCase
{
    private const COMPANY = 148;
    private const AS_OF = '2026-08-23';

    /**
     * Company 148's real supplier-cheque totals, copied verbatim from
     * production including the mixed types: a bucket touched by one
     * cheque keeps the raw DB string, a bucket touched twice has been
     * coerced to a number. Fed straight to formatForDashboard so the
     * test pins the formatting contract without needing the cheque
     * tables in the test database.
     */
    private const REAL_TOTALS = [
        'total' => [
            'coming_due' => ['91-120' => 1006300, '16-30' => '6300.00', '46-60' => '6300.00', '61-90' => '6300.00'],
        ],
    ];

    private function cheques(array $agings = self::REAL_TOTALS): array
    {
        return $this->service()->formatForDashboard($agings, 'SupplierInvoice');
    }

    private function invoices(): array
    {
        return (new InvoiceAgingService(self::COMPANY, self::AS_OF, 'EGP'))
            ->formatForDashboard(self::REAL_TOTALS, 'SupplierInvoice');
    }

    private function service(): ChequeAgingService
    {
        return new ChequeAgingService(self::COMPANY, self::AS_OF, 'EGP');
    }

    private function page(): string
    {
        return file_get_contents(resource_path('js/Pages/Dashboard/Forecast.vue'));
    }

    // ---------------------------------------------------------------
    // the service emits the bar-chart shape
    // ---------------------------------------------------------------

    public function test_cheque_aging_exposes_an_aging_chart_key(): void
    {
        $this->assertArrayHasKey(
            'aging_chart',
            $this->cheques(),
            'Forecast.vue reads cheques_aging_for_table[...].aging_chart to draw the bar chart.'
        );
    }

    public function test_every_cheque_row_carries_the_three_bar_chart_keys(): void
    {
        $rows = $this->cheques()['aging_chart'];
        $this->assertNotEmpty($rows, 'Company 148 has supplier cheques coming due; the chart must not be empty.');

        foreach ($rows as $row) {
            $this->assertSame(
                ['region', 'state', 'sales'],
                array_keys($row),
                'AgingDivergingBarChart reads region/state/sales — same as the invoice chart.'
            );
        }
    }

    /**
     * The one that actually blanks the chart.
     *
     * ChequeAgingService seeds a bucket with the raw DB value on first
     * write ("6300.00" as a string) and only coerces it to a number on
     * later additions. Forecast.vue drops any row where
     * typeof sales !== 'number', so a bucket touched by exactly one
     * cheque would vanish silently while its neighbours rendered.
     */
    public function test_amounts_are_numbers_not_numeric_strings(): void
    {
        foreach ($this->cheques()['aging_chart'] as $row) {
            $this->assertIsNotString(
                $row['sales'],
                "Bucket {$row['state']} came through as a string; the page filters those out and the bar disappears."
            );
            $this->assertIsNumeric($row['sales']);
        }
    }

    public function test_cheques_and_invoices_emit_the_very_same_row_shape(): void
    {
        $chequeRow = $this->cheques()['aging_chart'][0] ?? null;
        $invoiceRow = $this->invoices()['chart'][0] ?? null;
        $this->assertNotNull($chequeRow);
        $this->assertNotNull($invoiceRow);

        $this->assertSame(
            array_keys($invoiceRow),
            array_keys($chequeRow),
            'Both charts are fed to the same Vue component, so the payloads must match.'
        );
    }

    // ---------------------------------------------------------------
    // the shared formatter
    // ---------------------------------------------------------------

    public function test_past_due_buckets_are_marked_with_a_minus(): void
    {
        $rows = $this->service()->formatAgingBucketsForChart(['past_due' => ['16-30' => 500], 'coming_due' => ['16-30' => 700]]);

        $past = collect($rows)->firstWhere('region', 'Past Due');
        $coming = collect($rows)->firstWhere('region', 'Coming Due');

        $this->assertStringStartsWith('-', $past['state'], 'The page splits the two sides on this minus sign.');
        $this->assertStringStartsNotWith('-', $coming['state']);
    }

    public function test_bucket_totals_and_counts_are_not_drawn_as_bars(): void
    {
        $rows = $this->service()->formatAgingBucketsForChart([
                'coming_due' => ['16-30' => 500, 'total' => 500, 'no_invoices' => ['16-30' => 1]],
            ]);

        $this->assertCount(1, $rows, "'total' and 'no_invoices' are bookkeeping keys, not aging buckets.");
        $this->assertSame('16-30 Days', $rows[0]['state']);
    }

    public function test_string_amounts_are_cast_before_reaching_the_chart(): void
    {
        $rows = $this->service()->formatAgingBucketsForChart(['coming_due' => ['16-30' => '6300.00']]);

        $this->assertIsNotString($rows[0]['sales']);
        $this->assertEqualsWithDelta(6300.0, $rows[0]['sales'], 0.001);
    }

    // ---------------------------------------------------------------
    // the page draws it the same way as the invoice chart
    // ---------------------------------------------------------------

    public function test_the_cheque_chart_uses_the_invoice_aging_component(): void
    {
        $page = $this->page();

        $this->assertStringContainsString(
            '<AgingDivergingBarChart :data="chequeBarData(modelType)"',
            $page,
            'The cheque chart must be the same component as the invoice aging chart.'
        );
        $this->assertStringNotContainsString(
            '<DonutChart3D',
            $page,
            'The donut was replaced; leaving it behind means the old empty chart is still rendered.'
        );
    }

    public function test_the_page_reads_the_aging_chart_payload(): void
    {
        $this->assertStringContainsString(
            'cheques_aging_for_table?.[modelType]?.[activeCurrency.value]?.aging_chart',
            $this->page(),
            'Reading the old flat cheques_aging_for_chart list would show coming-due only.'
        );
    }

    public function test_both_charts_are_built_by_one_shared_function(): void
    {
        $page = $this->page();

        $this->assertStringContainsString('function agingBarRows(rows)', $page);
        $this->assertStringContainsString('return agingBarRows(props.dashboardResult?.invoices_aging', $page);
        $this->assertStringContainsString('return agingBarRows(props.dashboardResult?.cheques_aging_for_table', $page);
    }

    public function test_the_controller_still_sends_the_cheque_aging_table(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/CustomerInvoiceDashboardController.php'));

        $this->assertStringContainsString(
            "\$dashboardResult['cheques_aging_for_table']",
            $controller,
            'Drop this key and the cheque chart loses its data source entirely.'
        );
    }

    // ---------------------------------------------------------------
    // past-due cheques reach the chart
    // ---------------------------------------------------------------

    /**
     * A cheque sitting in the safe whose due date has passed is the one
     * you most need to see, and it was the one guaranteed to be hidden:
     * the query carried where('due_date','>=',$agingDate), so nothing
     * overdue ever reached the buckets.
     *
     * Company 148 has a EUR 3,300 opening cheque due 2026-07-03, still
     * marked in-safe. It appeared on the opening balance page and
     * nowhere in the aging chart.
     */
    public function test_overdue_cheques_are_not_filtered_out_of_the_query(): void
    {
        $service = file_get_contents(app_path('ReadyFunctions/ChequeAgingService.php'));

        $this->assertStringNotContainsString(
            "->where('due_date', '>=', \$this->aging_date)",
            $service,
            'This drops every overdue cheque before it can be bucketed.'
        );
    }

    /**
     * The invoice service never had that filter. The two aging services
     * feed the same chart component and must select on the same terms.
     */
    public function test_cheques_and_invoices_agree_on_which_dates_count(): void
    {
        foreach (['ChequeAgingService', 'InvoiceAgingService'] as $name) {
            $this->assertStringNotContainsString(
                "where('due_date', '>=',",
                file_get_contents(app_path("ReadyFunctions/{$name}.php")),
                "{$name} restricts the aging window; the other one does not."
            );
        }
    }

    /**
     * The service builds a past_due bucket and a
     * 'Total Past Dues Aging Analysis Chart' for cheques. Those were
     * dead code for as long as the query excluded overdue rows — which
     * is the evidence the filter was a mistake rather than a choice.
     */
    public function test_the_past_due_bucket_is_reachable(): void
    {
        $service = file_get_contents(app_path('ReadyFunctions/ChequeAgingService.php'));

        $this->assertStringContainsString('Total Past Dues Aging Analysis Chart', $service);
        $this->assertStringContainsString("'past_due'", $service);
    }

    public function test_an_overdue_cheque_lands_on_the_past_due_side(): void
    {
        $rows = $this->service()->formatAgingBucketsForChart([
            'past_due' => ['46-60' => 3300],
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame('Past Due', $rows[0]['region']);
        $this->assertSame('-46-60 Days', $rows[0]['state']);
        $this->assertEqualsWithDelta(3300.0, $rows[0]['sales'], 0.001);
    }
}
