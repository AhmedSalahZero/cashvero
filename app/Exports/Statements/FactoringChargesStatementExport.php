<?php

namespace App\Exports\Statements;

/**
 * FactoringChargesStatementExport
 * ------------------------------------------------------------------
 * Excel export for the Factoring Charges Statement report. All
 * amounts here are positive charges (no debit/credit split, no
 * running balance in the Bank/Safe Statement sense), so no sign-based
 * conditional coloring applies. "Running Total" accumulates forward
 * across the whole date range — its totals-row value is the LAST
 * row's own figure, never a SUM (summing a running total would be
 * meaningless), matching FactoringStatementExport's own override for
 * the same reason.
 */
class FactoringChargesStatementExport extends AbstractStatementExport
{
    protected function numericColumnLabels(): array
    {
        return ['Amount', 'Running Total'];
    }

    protected function summableColumnLabels(): array
    {
        return ['Amount'];
    }

    protected function conditionalColorColumnLabel(): ?string
    {
        return null;
    }

    protected function writeSpecialTotalsCells($sheet, int $totalsRow, int $lastDataRow): void
    {
        if ($col = $this->columnLetterForHeading('Running Total')) {
            $sheet->setCellValue("{$col}{$totalsRow}", "={$col}{$lastDataRow}");
            $sheet->getStyle("{$col}{$totalsRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        }
    }
}
