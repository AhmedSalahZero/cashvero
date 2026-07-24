<?php

namespace App\Exports\Statements;

use Maatwebsite\Excel\Events\AfterSheet;

/**
 * InvoiceReportExport
 * ------------------------------------------------------------------
 * Excel export for the Customer/Supplier Invoice Report
 * (Balances/InvoiceReport.vue, served by
 * CustomerInvoiceDashboardController::showInvoiceReport()). Built on
 * AbstractStatementExport for the same header/banding/border/totals
 * language as every other report export — just a different column
 * set: no Debit/Credit/running balance here. Each invoice's Net
 * Balance is independent of every other row, so (unlike the
 * ledger-style Statement exports) summing it across the totals row
 * IS meaningful — no override of writeSpecialTotalsCells() needed.
 *
 * The "collected/paid" column's heading text is either
 * "Total Collections" (customer) or "Total Payments" (supplier) —
 * passed in rather than hardcoded, matching the same dynamic label
 * already used on screen ($totalCollectionOrPaidText in the
 * controller / totalCollectionOrPaidText prop in InvoiceReport.vue).
 */
class InvoiceReportExport extends AbstractStatementExport
{
    protected string $totalCollectedOrPaidLabel;

    /**
     * @param  array  $headings  column headers, in display order
     * @param  iterable  $rows  each row a plain associative array whose VALUE
     *                          order matches $headings
     * @param  string  $totalCollectedOrPaidLabel  the exact heading text used
     *                          for the "Total Collections"/"Total Payments" column
     */
    public function __construct(array $headings, iterable $rows, string $totalCollectedOrPaidLabel)
    {
        parent::__construct($headings, $rows);
        $this->totalCollectedOrPaidLabel = $totalCollectedOrPaidLabel;
    }

    protected function numericColumnLabels(): array
    {
        return ['Invoice Amount', 'Withhold Amount', 'VAT Amount', 'Total Deductions', $this->totalCollectedOrPaidLabel, 'Net Balance'];
    }

    protected function summableColumnLabels(): array
    {
        return ['Invoice Amount', 'Withhold Amount', 'VAT Amount', 'Total Deductions', $this->totalCollectedOrPaidLabel, 'Net Balance'];
    }

    /** Net Balance gets the sign-based amber/red/green coloring — same convention as every other running-balance-style column. */
    protected function conditionalColorColumnLabel(): ?string
    {
        return 'Net Balance';
    }

    /**
     * Status column color-coded to the same meaning as the on-screen
     * badge (statusBadgeClass() in InvoiceReport.vue): collected =
     * settled/green, either past-due variant = red/critical,
     * everything else (not due yet, due today) = neutral gray. Not
     * part of AbstractStatementExport's shared styling since no
     * other report has a Status column.
     */
    public function afterSheet(AfterSheet $event): void
    {
        parent::afterSheet($event);

        $sheet = $event->sheet->getDelegate();
        if ($this->rowsCollection->isEmpty()) {
            return;
        }
        $lastDataRow = $this->rowsCollection->count() + 1;
        $statusColumn = $this->columnLetterForHeading('Status');
        if (! $statusColumn) {
            return;
        }

        for ($row = 2; $row <= $lastDataRow; $row++) {
            $value = (string) $sheet->getCell("{$statusColumn}{$row}")->getValue();
            $isPastDue = in_array($value, ['pastDue', 'partiallyCollectedAndPastDue'], true);
            $isCollected = $value === 'collected';
            $rgb = $isCollected ? '1D9A6C' : ($isPastDue ? 'C0392B' : '8C97A6');
            $sheet->getStyle("{$statusColumn}{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("{$statusColumn}{$row}")->getFont()->setBold($isCollected || $isPastDue)->getColor()->setRGB($rgb);
        }
    }
}
