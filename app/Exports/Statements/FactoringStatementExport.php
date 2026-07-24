<?php

namespace App\Exports\Statements;

/**
 * FactoringStatementExport
 * ------------------------------------------------------------------
 * Excel export for the Factoring Statement report. No Beginning
 * Balance column here (there isn't one — the running balance starts
 * at 0 for the date range and accumulates forward), so the base
 * class's default Beginning Balance handling simply finds no matching
 * heading and no-ops.
 *
 * Rows are ordered date ASCENDING (oldest first) — the opposite of
 * Bank/Safe/LG & LC Statement's descending order — so the ENDING
 * balance total must reference the LAST data row's own End Balance,
 * not row 2's (the base class's default assumes descending order).
 * Overridden here rather than silently producing a wrong total.
 */
class FactoringStatementExport extends AbstractStatementExport
{
    protected function writeSpecialTotalsCells($sheet, int $totalsRow, int $lastDataRow): void
    {
        if ($endBalanceCol = $this->columnLetterForHeading('End Balance')) {
            $sheet->setCellValue("{$endBalanceCol}{$totalsRow}", "={$endBalanceCol}{$lastDataRow}");
            $sheet->getStyle("{$endBalanceCol}{$totalsRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        }
    }
}
