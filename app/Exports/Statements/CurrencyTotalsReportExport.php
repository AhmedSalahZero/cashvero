<?php

namespace App\Exports\Statements;

use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * CurrencyTotalsReportExport
 * ------------------------------------------------------------------
 * Excel export for the flat reports whose rows come in several
 * currencies (Cheques Report, Down Payment Report). The base class ends
 * every workbook with one =SUM() row, which here would add dollars to
 * pounds — so this swaps it for one totals row PER currency, computed by
 * the caller over the whole filtered range.
 */
class CurrencyTotalsReportExport extends AbstractStatementExport
{
    /**
     * @param  array<int, string>  $headings
     * @param  array<int, string>  $numericLabels  headings formatted as amounts
     * @param  array<string, array<string, float>>  $totalsPerCurrency  currency => [heading => total]
     */
    public function __construct(
        array $headings,
        iterable $rows,
        private array $numericLabels,
        private array $totalsPerCurrency,
        private string $currencyHeading = 'Currency',
    ) {
        parent::__construct($headings, $rows);
    }

    protected function numericColumnLabels(): array
    {
        return $this->numericLabels;
    }

    protected function summableColumnLabels(): array
    {
        return [];
    }

    protected function conditionalColorColumnLabel(): ?string
    {
        return null;
    }

    protected function addTotalsRow($sheet, int $lastDataRow, string $lastColumnLetter): void
    {
        $row = $lastDataRow;
        $currencyColumn = $this->columnLetterForHeading($this->currencyHeading);

        foreach ($this->totalsPerCurrency as $currency => $totals) {
            $row++;
            $sheet->setCellValue("A{$row}", 'TOTAL');
            if ($currencyColumn) {
                $sheet->setCellValue("{$currencyColumn}{$row}", $currency);
            }
            foreach ($totals as $heading => $value) {
                if ($column = $this->columnLetterForHeading($heading)) {
                    $sheet->setCellValue("{$column}{$row}", $value);
                    $sheet->getStyle("{$column}{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                }
            }

            $range = "A{$row}:{$lastColumnLetter}{$row}";
            $sheet->getStyle($range)->getFont()->setBold(true);
            $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8ECF1');
            if ($row === $lastDataRow + 1) {
                $sheet->getStyle($range)->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
            }
        }
    }
}
