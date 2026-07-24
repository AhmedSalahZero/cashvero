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
 * PartnersStatementExport
 * ------------------------------------------------------------------
 * Excel export for the Partners Statement report — a flat sheet (one
 * row per transaction, with a leading "Partner" column) covering every
 * selected partner at once. Not built on AbstractStatementExport:
 * that base class's totals-row logic assumes a SINGLE running-balance
 * account (End Balance total = the most recent row's own value), but
 * this report can span many partners/accounts in one export, where
 * the meaningful "ending balance" total is the SUM of each partner's
 * own most recent row — a genuinely different calculation, not just a
 * different column set. Shares the same color palette and visual
 * language by hand instead of forcing an ill-fitting inheritance.
 *
 * Colors match every other Statement export (see
 * AbstractStatementExport's docblock for the source design tokens).
 */
class PartnersStatementExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings
{
    use Exportable;
    use RegistersEventListeners;

    protected array $headingsList;
    protected Collection $rowsCollection;
    protected float $endingBalanceTotal;

    /**
     * @param  array  $headings  column headers, in display order
     * @param  iterable  $rows  each row a plain associative array whose VALUE
     *                          order matches $headings
     * @param  float  $endingBalanceTotal  sum of each selected partner's own
     *                          most recent End Balance — computed once in
     *                          PartnersStatementController from the real
     *                          grouped source data, since no contiguous
     *                          range in this flat, multi-partner sheet
     *                          corresponds to "one row per partner"
     */
    public function __construct(array $headings, iterable $rows, float $endingBalanceTotal)
    {
        $this->headingsList = $headings;
        $this->rowsCollection = collect($rows)->values();
        $this->endingBalanceTotal = $endingBalanceTotal;
    }

    public function headings(): array
    {
        return $this->headingsList;
    }

    public function collection()
    {
        return $this->rowsCollection;
    }

    public function afterSheet(AfterSheet $event): void
    {
        $sheet = $event->sheet->getDelegate();
        if ($this->rowsCollection->isEmpty()) {
            return;
        }

        $lastColumnIndex = count($this->headingsList);
        $lastColumnLetter = Coordinate::stringFromColumnIndex($lastColumnIndex);
        $lastDataRow = $this->rowsCollection->count() + 1;

        $headerRange = "A1:{$lastColumnLetter}1";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0D2038');
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(22);
        $sheet->freezePane('A2');

        $fullRange = "A1:{$lastColumnLetter}{$lastDataRow}";
        $sheet->getStyle($fullRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D9DEE5');

        for ($row = 2; $row <= $lastDataRow; $row++) {
            if ($row % 2 === 0) {
                $sheet->getStyle("A{$row}:{$lastColumnLetter}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3F5F8');
            }
        }

        $columnFor = function (string $label) {
            foreach ($this->headingsList as $index => $heading) {
                if ($heading === $label) {
                    return Coordinate::stringFromColumnIndex($index + 1);
                }
            }

            return null;
        };

        foreach (['Beginning Balance', 'Debit', 'Credit'] as $label) {
            if ($col = $columnFor($label)) {
                $range = "{$col}2:{$col}{$lastDataRow}";
                $sheet->getStyle($range)->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            }
        }

        // End Balance — same sign convention as every other Statement
        // export: positive → amber, negative → red, zero → green.
        if ($endBalanceCol = $columnFor('End Balance')) {
            $range = "{$endBalanceCol}2:{$endBalanceCol}{$lastDataRow}";
            $sheet->getStyle($range)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle($range)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle($range)->getFont()->setBold(true);
            for ($row = 2; $row <= $lastDataRow; $row++) {
                $value = (float) $sheet->getCell("{$endBalanceCol}{$row}")->getValue();
                $rgb = $value > 0 ? 'B8860B' : ($value < 0 ? 'C0392B' : '1D9A6C');
                $sheet->getStyle("{$endBalanceCol}{$row}")->getFont()->getColor()->setRGB($rgb);
            }
        }

        // Totals row. Debit/Credit are real =SUM() formulas across every
        // partner's rows (matches the original Blade page's own running
        // totals, which accumulate across ALL partners, not per-partner).
        // Beginning Balance is intentionally left blank — many partners'
        // real beginning balances have no single meaningful sum or
        // reference (matches the original, which never showed one either).
        // End Balance is a literal number, not a formula: the sum of each
        // partner's own most recent row, computed server-side from the
        // real grouped source data — documented here since it can't be
        // expressed as a contiguous-range formula in this flat layout.
        $totalsRow = $lastDataRow + 1;
        $sheet->setCellValue("A{$totalsRow}", 'TOTAL');
        foreach (['Debit', 'Credit'] as $label) {
            if ($col = $columnFor($label)) {
                $sheet->setCellValue("{$col}{$totalsRow}", "=SUM({$col}2:{$col}{$lastDataRow})");
                $sheet->getStyle("{$col}{$totalsRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            }
        }
        if ($endBalanceCol) {
            $sheet->setCellValue("{$endBalanceCol}{$totalsRow}", $this->endingBalanceTotal);
            $sheet->getStyle("{$endBalanceCol}{$totalsRow}")->getNumberFormat()->setFormatCode('#,##0.00');
        }
        $totalsRange = "A{$totalsRow}:{$lastColumnLetter}{$totalsRow}";
        $sheet->getStyle($totalsRange)->getFont()->setBold(true);
        $sheet->getStyle($totalsRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8ECF1');
        $sheet->getStyle($totalsRange)->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
    }
}
