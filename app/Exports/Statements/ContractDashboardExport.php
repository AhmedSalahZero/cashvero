<?php

namespace App\Exports\Statements;

/**
 * ContractDashboardExport
 * ------------------------------------------------------------------
 * Excel export for the Customer Contract Dashboard
 * (Dashboard/ContractStatus.vue, served by
 * ContractDashboardController). Built on AbstractStatementExport so a
 * downloaded workbook reads as the same product as every other report
 * export — same header fill, banding, borders and totals row.
 *
 * Exports the contract rows the page is built from, at the same as-of
 * date the page was showing, so a number questioned on screen can be
 * traced line by line in the sheet.
 *
 * Remaining is the conditionally coloured column: negative means the
 * contract has been invoiced beyond its value, which is exactly the
 * row a reader should be drawn to.
 *
 * Utilization is deliberately NOT summable — averaging percentages by
 * adding them is meaningless, and the totals row would invite it.
 */
class ContractDashboardExport extends AbstractStatementExport
{
    protected function numericColumnLabels(): array
    {
        return [
            'Contract Value',
            'Invoiced',
            'Remaining',
            'Utilization %',
            'Billed (incl. tax)',
            'Collected',
            'Uncollected',
        ];
    }

    protected function summableColumnLabels(): array
    {
        return [
            'Contract Value',
            'Invoiced',
            'Remaining',
            'Billed (incl. tax)',
            'Collected',
            'Uncollected',
        ];
    }

    protected function conditionalColorColumnLabel(): ?string
    {
        return 'Remaining';
    }
}
