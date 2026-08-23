<?php

namespace Tests\Feature\Dashboard;

use App\ReadyFunctions\ChequeAgingService;
use Tests\TestCase;

/**
 * The cheque donuts were empty even with cheques coming due.
 *
 * ChequeAgingService BUILDS a { due_date => amount } map, then
 * formatChart() converts it into a LIST of { date, value } before
 * returning it. Forecast.vue was still reading the map form with
 * Object.entries(), which over a list yields the array INDICES as keys
 * and the row objects as values — so every point came out as
 * { category: '0', value: NaN } and nothing rendered. Company 148 had
 * five supplier cheques coming due and the chart showed none of them.
 *
 * This pins the contract from both ends: the shape the service emits,
 * and the shape the page reads.
 *
 * @see \App\ReadyFunctions\ChequeAgingService::formatChart()
 */
class ChequeChartPayloadTest extends TestCase
{
    private function service(): ChequeAgingService
    {
        return new ChequeAgingService(148, '2026-08-23', 'EGP');
    }

    private function page(): string
    {
        return file_get_contents(resource_path('js/Pages/Dashboard/Forecast.vue'));
    }

    // ---------------------------------------------------------------
    // what the service emits
    // ---------------------------------------------------------------

    public function test_the_chart_payload_is_a_list_not_a_keyed_map(): void
    {
        $chart = $this->service()->formatChart([
            '2026-09-10' => 6300,
            '2026-11-30' => 1000000,
        ]);

        $this->assertTrue(array_is_list($chart),
            'formatChart returns a list — anything reading it as a map gets array indices instead of dates.');
    }

    public function test_every_point_carries_a_date_and_a_value(): void
    {
        $chart = $this->service()->formatChart(['2026-09-10' => 6300]);

        $this->assertSame([['date' => '2026-09-10', 'value' => 6300]], $chart);
    }

    /**
     * Chart points are drawn left to right, so the dates have to come
     * out in order regardless of the order they accumulated in.
     */
    public function test_the_points_come_out_in_date_order(): void
    {
        $chart = $this->service()->formatChart([
            '2026-12-10' => 3,
            '2026-09-10' => 1,
            '2026-11-30' => 2,
        ]);

        $this->assertSame(['2026-09-10', '2026-11-30', '2026-12-10'], array_column($chart, 'date'));
        $this->assertSame([1, 2, 3], array_column($chart, 'value'));
    }

    public function test_no_cheques_produces_an_empty_list(): void
    {
        $this->assertSame([], $this->service()->formatChart([]));
    }

    // ---------------------------------------------------------------
    // what the page reads
    // ---------------------------------------------------------------

    /**
     * The fix itself: the page must handle the list it is actually
     * given. Reading it with a bare Object.entries() is the bug.
     */
    public function test_the_page_reads_the_payload_as_a_list(): void
    {
        $page = $this->page();

        $this->assertStringContainsString('Array.isArray(rows)', $page,
            'Forecast.vue must read the cheque payload as the list the service sends.');
        $this->assertStringNotContainsString(
            'return Object.entries(rows).map(([date, amount]) => ({ category: date, value: Math.abs(Number(amount || 0)) }));',
            $page,
            'The page still reads the list as a map — every point becomes { category: index, value: NaN }.'
        );
    }

    public function test_the_page_reads_the_date_and_value_keys_the_service_emits(): void
    {
        $page = $this->page();

        $this->assertMatchesRegularExpression(
            '/\.map\(r => \(\{ category: r\.date, value: Math\.abs\(Number\(r\.value \|\| 0\)\) \}\)\)/',
            $page,
            'The keys the page reads must be the ones formatChart writes.'
        );
    }

    /**
     * A point with no date, or an amount that will not parse, must be
     * dropped rather than drawn as a NaN slice — that is precisely how
     * the empty chart looked.
     */
    public function test_the_page_drops_unusable_points(): void
    {
        $this->assertStringContainsString(
            'Number.isFinite(r.value)',
            $this->page(),
            'A NaN value must not reach the chart.'
        );
    }

    /**
     * Both donuts read the same helper, so neither can be fixed
     * without the other.
     */
    public function test_both_cheque_donuts_read_the_same_helper(): void
    {
        $page = $this->page();

        $this->assertSame(
            1,
            substr_count($page, 'function chequeChartData'),
            'One helper feeds both the customer and supplier donut.'
        );
        $this->assertStringContainsString(':data="chequeChartData(modelType)"', $page);
    }
}
