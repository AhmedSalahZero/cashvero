<?php

namespace Tests\Feature\ContractDashboard;

use App\Exports\Statements\ContractDashboardExport;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The Excel export of the Contract Dashboard.
 *
 * The numbers themselves are covered by ContractDashboardServiceTest —
 * the export re-runs the same service, so what matters here is that the
 * sheet is shaped correctly and that its styling contract lines up with
 * the column names the controller actually writes. A heading renamed on
 * one side and not the other silently loses its formatting and its
 * =SUM() row, which nothing else would catch.
 *
 * @see \App\Exports\Statements\ContractDashboardExport
 * @see \App\Http\Controllers\ContractDashboardController::export()
 */
class ContractDashboardExportTest extends TestCase
{
    /**
     * The exact headings ContractDashboardController::export() writes.
     */
    private const CONTROLLER_HEADINGS = [
        '#', 'Customer', 'Code', 'Name', 'Currency', 'Status',
        'Start Date', 'End Date', 'Invoices',
        'Contract Value', 'Invoiced', 'Remaining', 'Utilization %',
        'Billed (incl. tax)', 'Collected', 'Uncollected',
    ];

    private function labels(string $method): array
    {
        $export = new ContractDashboardExport(self::CONTROLLER_HEADINGS, collect());
        $reflection = new \ReflectionMethod($export, $method);
        $reflection->setAccessible(true);

        return $reflection->invoke($export);
    }

    public function test_the_export_route_exists_and_is_permission_gated(): void
    {
        $this->assertTrue(Route::has('export.contracts.dashboard'));

        $this->assertSame(
            ['dashboard_contracts.export'],
            \App\Support\Permissions\RoutePermissionMap::for('export.contracts.dashboard'),
            'The export must be gated, not reachable by anyone who can reach the page.'
        );

        $this->assertTrue(
            \App\Support\Permissions\PermissionRegistry::has('dashboard_contracts.export'),
            'The gate has to exist in the registry, or nobody can ever be granted it.'
        );
    }

    public function test_the_controller_headings_are_the_ones_the_sheet_writes(): void
    {
        $export = new ContractDashboardExport(self::CONTROLLER_HEADINGS, collect());

        $this->assertSame(self::CONTROLLER_HEADINGS, $export->headings());
    }

    /**
     * Styling is matched by heading text, so a label that does not
     * appear in the sheet is dead configuration.
     *
     * @dataProvider styledLabelProvider
     */
    public function test_every_styled_column_is_a_real_heading(string $method): void
    {
        $unknown = array_values(array_diff($this->labels($method), self::CONTROLLER_HEADINGS));

        $this->assertSame([], $unknown, sprintf(
            '%s() names columns the export does not have: %s',
            $method, implode(', ', $unknown)
        ));
    }

    public static function styledLabelProvider(): array
    {
        return [
            'numeric' => ['numericColumnLabels'],
            'summable' => ['summableColumnLabels'],
        ];
    }

    public function test_the_conditionally_coloured_column_is_a_real_heading(): void
    {
        $export = new ContractDashboardExport(self::CONTROLLER_HEADINGS, collect());
        $reflection = new \ReflectionMethod($export, 'conditionalColorColumnLabel');
        $reflection->setAccessible(true);

        $this->assertContains($reflection->invoke($export), self::CONTROLLER_HEADINGS);
    }

    /**
     * Every money column has to be summable, and Utilization must not
     * be — a totals row that adds percentages together is worse than no
     * totals row at all.
     */
    public function test_money_columns_total_and_utilization_does_not(): void
    {
        $summable = $this->labels('summableColumnLabels');

        foreach (['Contract Value', 'Invoiced', 'Remaining', 'Billed (incl. tax)', 'Collected', 'Uncollected'] as $money) {
            $this->assertContains($money, $summable, "{$money} should be totalled.");
        }

        $this->assertNotContains('Utilization %', $summable, 'Percentages must not be added up.');
        $this->assertContains('Utilization %', $this->labels('numericColumnLabels'), '...but it is still a number.');
    }

    public function test_rows_keep_the_heading_order(): void
    {
        $row = [
            '#' => 1, 'Customer' => 'A', 'Code' => 'C-1', 'Name' => 'N', 'Currency' => 'EGP', 'Status' => 'Running',
            'Start Date' => '2026-01-01', 'End Date' => '2026-12-31', 'Invoices' => 2,
            'Contract Value' => 1000.0, 'Invoiced' => 200.0, 'Remaining' => 800.0, 'Utilization %' => 20.0,
            'Billed (incl. tax)' => 220.0, 'Collected' => 0.0, 'Uncollected' => 220.0,
        ];

        $export = new ContractDashboardExport(self::CONTROLLER_HEADINGS, collect([$row]));

        // Laravel Excel writes by position, not by key.
        $this->assertSame(self::CONTROLLER_HEADINGS, array_keys($export->collection()->first()));
    }

    /**
     * Raw numbers, not the page's comma-formatted display strings —
     * otherwise Excel treats every money column as text and the =SUM()
     * row returns zero.
     */
    public function test_money_values_stay_numeric(): void
    {
        $export = new ContractDashboardExport(self::CONTROLLER_HEADINGS, collect([[
            '#' => 1, 'Customer' => 'A', 'Code' => 'C-1', 'Name' => 'N', 'Currency' => 'EGP', 'Status' => 'Running',
            'Start Date' => null, 'End Date' => null, 'Invoices' => 0,
            'Contract Value' => 1234567.89, 'Invoiced' => 0.0, 'Remaining' => 1234567.89, 'Utilization %' => 0.0,
            'Billed (incl. tax)' => 0.0, 'Collected' => 0.0, 'Uncollected' => 0.0,
        ]]));

        $row = $export->collection()->first();

        foreach (['Contract Value', 'Invoiced', 'Remaining', 'Billed (incl. tax)', 'Collected', 'Uncollected'] as $money) {
            $this->assertIsFloat($row[$money], "{$money} must be a number, not a formatted string.");
        }
    }
}
