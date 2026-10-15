<?php

namespace App\Exports\Statements;

/**
 * CustomerSupplierStatementExport
 * ------------------------------------------------------------------
 * Excel export for the Customer/Supplier Statement report
 * (Balances/Statement.vue, served by
 * CustomerInvoiceDashboardController::showInvoiceStatementReport()).
 * Same 8 columns and color language as every other Statement export
 * (see AbstractStatementExport's docblock for the shared header/
 * banding/border/totals styling and the design-token colors) — the
 * ONE thing this overrides is the End Balance total's cell
 * reference.
 *
 * Bank/Safe Statement are ordered newest-first, so the base class's
 * default assumes "the ending balance" sits in row 2. This report is
 * ordered OLDEST-first instead (matches the on-screen running-balance
 * calc in Statement.vue, which sets balances[0] from the first row
 * then accumulates forward) — so the true ending balance is the LAST
 * data row, not the first.
 */
class CustomerSupplierStatementExport extends AbstractStatementExport
{
    protected function writeSpecialTotalsCells($sheet, int $totalsRow, int $lastDataRow): void
    {
        if ($endBalanceCol = $this->columnLetterForHeading('End Balance')) {
            $sheet->setCellValue("{$endBalanceCol}{$totalsRow}", "={$endBalanceCol}{$lastDataRow}");
            $sheet->getStyle("{$endBalanceCol}{$totalsRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        }
    }
}
