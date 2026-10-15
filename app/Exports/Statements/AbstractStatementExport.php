<?php

namespace App\Exports\Statements;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * AbstractStatementExport
 * ------------------------------------------------------------------
 * Shared styling for every Statement report's Excel export (Bank
 * Statement, Safe Statement, Cash Expense Statement, Withdrawal
 * Statement — Partners Statement uses its own dedicated export class
 * since its grouped-by-partner shape doesn't fit a flat single-ledger
 * contract, but reuses this same color palette by hand). Extracted
 * here instead of duplicated per report, same reasoning as
 * PaginatesRawCollections: one formula for "what a heavy statement
 * export should look like," used everywhere.
 *
 * Column-specific styling (amount formatting, sign-based conditional
 * coloring, the Reviewed coloring, the totals row) is found by
 * matching each heading's exact text — the same "find the column by
 * its heading label" approach already used in this codebase by
 * ContractLoanScheduleHeadersExport::getDraweeBankColumnLetter(). A
 * concrete subclass whose headings don't include a given label (e.g.
 * Safe Statement has no "Limit" column, Cash Expense Statement has no
 * "End Balance" at all) simply skips that styling step.
 *
 * Three things a subclass can override when its report's columns
 * don't match Bank/Safe Statement's exact shape:
 *   - numericColumnLabels()       which headings get right-aligned,
 *                                  2-decimal number formatting
 *   - summableColumnLabels()      which headings get a real =SUM()
 *                                  formula in the totals row
 *   - writeSpecialTotalsCells()   any totals-row cell that ISN'T a
 *                                  simple sum (e.g. a running-balance
 *                                  column, where "the total" means
 *                                  something else entirely)
 *   - conditionalColorColumnLabel()  which heading gets the sign-based
 *                                  amber/red/green coloring (defaults
 *                                  to "End Balance"; Withdrawal
 *                                  Statement points this at "Balance"
 *                                  instead)
 *
 * Colors are pulled directly from CashVero's own design tokens
 * (resources/css/app.css) so every exported workbook reads as the
 * same product as its on-screen report, not a generic spreadsheet:
 *   - Header fill:            #0D2038 (--cvr-bg-surface, dark mode)
 *   - Conditional column > 0: #B8860B (amber family, --cvr-num-amber)
 *   - Conditional column < 0: #C0392B (red family, --cvr-num-red)
 *   - Conditional column = 0: #1D9A6C (--cvr-green)
 *   - Reviewed = Yes:         #1D9A6C (--cvr-green)
 */
abstract class AbstractStatementExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings
{
    use Exportable;
    use RegistersEventListeners;

    protected array $headingsList;
    protected Collection $rowsCollection;

    /**
     * @param  array  $headings  column headers, in display order
     * @param  iterable  $rows  each row a plain associative array whose VALUE
     *                          order matches $headings (keys are not read by
     *                          Laravel Excel — only position matters)
     */
    public function __construct(array $headings, iterable $rows)
    {
        $this->headingsList = $headings;
        $this->rowsCollection = collect($rows)->values();
    }

    public function headings(): array
    {
        return $this->headingsList;
    }

    public function collection()
    {
        return $this->rowsCollection;
    }

    /** Headings that get right-aligned, 2-decimal number formatting. */
    protected function numericColumnLabels(): array
    {
        return ['Limit', 'Actual Limit', 'Beginning Balance', 'Debit', 'Credit', 'Room', 'Calculated Interest'];
    }

    /** Headings that get a real =SUM() formula in the totals row. */
    protected function summableColumnLabels(): array
    {
        return ['Debit', 'Credit'];
    }

    /** Which heading (if any) gets the sign-based amber/red/green font color. */
    protected function conditionalColorColumnLabel(): ?string
    {
        return 'End Balance';
    }

    public function afterSheet(AfterSheet $event): void
    {
        $sheet = $event->sheet->getDelegate();
        $lastColumnIndex = count($this->headingsList);
        $lastColumnLetter = Coordinate::stringFromColumnIndex($lastColumnIndex);
        $lastDataRow = $this->rowsCollection->count() + 1; // +1 for the header row

        if ($this->rowsCollection->isEmpty()) {
            return;
        }

        $this->styleHeaderRow($sheet, $lastColumnLetter);
        $sheet->freezePane('A2');
        $this->styleBorders($sheet, $lastColumnLetter, $lastDataRow);
        $this->styleBandedRows($sheet, $lastColumnLetter, $lastDataRow);

        foreach ($this->numericColumnLabels() as $amountLabel) {
            $this->styleAmountColumn($sheet, $amountLabel, $lastDataRow);
        }
        if ($conditionalLabel = $this->conditionalColorColumnLabel()) {
            $this->styleConditionalColorColumn($sheet, $conditionalLabel, $lastDataRow);
        }
        $this->styleReviewedColumn($sheet, 'Reviewed', $lastDataRow);

        $this->addTotalsRow($sheet, $lastDataRow, $lastColumnLetter);
    }

    private function styleHeaderRow($sheet, string $lastColumnLetter): void
    {
        $range = "A1:{$lastColumnLetter}1";
        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0D2038');
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(22);
    }

    private function styleBorders($sheet, string $lastColumnLetter, int $lastDataRow): void
    {
        $range = "A1:{$lastColumnLetter}{$lastDataRow}";
        $sheet->getStyle($range)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)
            ->getColor()->setRGB('D9DEE5');
    }

    private function styleBandedRows($sheet, string $lastColumnLetter, int $lastDataRow): void
    {
        for ($row = 2; $row <= $lastDataRow; $row++) {
            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:{$lastColumnLetter}{$row}")
                    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3F5F8');
            }
        }
    }

    protected function columnLetterForHeading(string $label): ?string
    {
        foreach ($this->headingsList as $index => $heading) {
            if ($heading === $label) {
                return Coordinate::stringFromColumnIndex($index + 1);
            }
        }

        return null;
    }

    private function styleAmountColumn($sheet, string $label, int $lastDataRow): void
    {
        $column = $this->columnLetterForHeading($label);
        if (! $column) {
            return;
        }
        $range = "{$column}2:{$column}{$lastDataRow}";
        $sheet->getStyle($range)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
    }

    /**
     * Same sign convention as the on-screen table (endBalanceColorVar() in
     * Result.vue / Balances/Statement.vue): positive → amber, negative →
     * red, zero → green. Which column this applies to is decided by
     * conditionalColorColumnLabel() — "End Balance" for the running-balance
     * reports, "Balance" (outstanding amount) for Withdrawal Statement.
     */
    private function styleConditionalColorColumn($sheet, string $label, int $lastDataRow): void
    {
        $column = $this->columnLetterForHeading($label);
        if (! $column) {
            return;
        }
        $range = "{$column}2:{$column}{$lastDataRow}";
        $sheet->getStyle($range)->getNumberFormat()->setFormatCode('#,##0.00');
        $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle($range)->getFont()->setBold(true);

        for ($row = 2; $row <= $lastDataRow; $row++) {
            $value = (float) $sheet->getCell("{$column}{$row}")->getValue();
            $rgb = $value > 0 ? 'B8860B' : ($value < 0 ? 'C0392B' : '1D9A6C');
            $sheet->getStyle("{$column}{$row}")->getFont()->getColor()->setRGB($rgb);
        }
    }

    private function styleReviewedColumn($sheet, string $label, int $lastDataRow): void
    {
        $column = $this->columnLetterForHeading($label);
        if (! $column) {
            return;
        }
        $sheet->getStyle("{$column}2:{$column}{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        for ($row = 2; $row <= $lastDataRow; $row++) {
            $value = (string) $sheet->getCell("{$column}{$row}")->getValue();
            $isYes = $value === 'Yes';
            $sheet->getStyle("{$column}{$row}")->getFont()
                ->setBold($isYes)
                ->getColor()->setRGB($isYes ? '1D9A6C' : '8C97A6');
        }
    }

    /**
     * Bold totals row at the bottom. summableColumnLabels() headings get a
     * real =SUM() formula (never a hardcoded number — recalculates if a
     * cell is edited). Anything else that needs a totals-row value with
     * different business meaning (e.g. a running-balance column, where
     * summing across rows is meaningless) is handled by
     * writeSpecialTotalsCells(), which subclasses override.
     */
    private function addTotalsRow($sheet, int $lastDataRow, string $lastColumnLetter): void
    {
        $totalsRow = $lastDataRow + 1;
        $sheet->setCellValue("A{$totalsRow}", 'TOTAL');

        foreach ($this->summableColumnLabels() as $label) {
            if ($column = $this->columnLetterForHeading($label)) {
                $sheet->setCellValue("{$column}{$totalsRow}", "=SUM({$column}2:{$column}{$lastDataRow})");
                $sheet->getStyle("{$column}{$totalsRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            }
        }

        $this->writeSpecialTotalsCells($sheet, $totalsRow, $lastDataRow);

        $range = "A{$totalsRow}:{$lastColumnLetter}{$totalsRow}";
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8ECF1');
        $sheet->getStyle($range)->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
    }

    /**
     * Default behavior matches Bank Statement / Safe Statement exactly:
     * results are ordered date desc (most recent first), so "Beginning
     * Balance" total references the range's actual earliest row (the last
     * data row) and "End Balance" total references the range's actual most
     * recent row (row 2) — never summed, since summing a running-balance
     * column is meaningless. Subclasses whose report has no such columns
     * (Cash Expense Statement) simply never match a heading here and this
     * is a no-op; subclasses with a genuinely different running-balance
     * shape (Partners Statement, which spans many partners) override this
     * entirely.
     */
    protected function writeSpecialTotalsCells($sheet, int $totalsRow, int $lastDataRow): void
    {
        if ($beginningCol = $this->columnLetterForHeading('Beginning Balance')) {
            $sheet->setCellValue("{$beginningCol}{$totalsRow}", "={$beginningCol}{$lastDataRow}");
            $sheet->getStyle("{$beginningCol}{$totalsRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        }
        if ($endBalanceCol = $this->columnLetterForHeading('End Balance')) {
            $sheet->setCellValue("{$endBalanceCol}{$totalsRow}", "={$endBalanceCol}2");
            $sheet->getStyle("{$endBalanceCol}{$totalsRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        }
    }
}
