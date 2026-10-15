<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * CashFlowMatrixExport
 * ------------------------------------------------------------------
 * Shared colored Excel export for the "wide matrix" shape common to
 * every Cash Flow report: one row per line item, one column per
 * period (week/month/day), plus a Total column. Used by:
 *   - CashFlowReportController::exportExcel()  (Company Cash Flow AND
 *     Contract Cash Flow — both share the same Result.vue page)
 *   - Reports\ConsolidatedCashFlowReportController::exportExcel()
 *
 * Same color palette and "find styling by role" philosophy as
 * App\Exports\Statements\AbstractStatementExport, adapted for a
 * matrix instead of a flat ledger: instead of styling COLUMNS by
 * heading label (which assumes unique, known column names), each ROW
 * carries its own $type ('section' | 'row' | 'total' | 'net') decided
 * by the caller — this class only paints, it never re-decides which
 * row means what. This also sidesteps a real hazard the label-lookup
 * approach would hit here: period-column headings (e.g. "Week 12
 * [2026]") are NOT guaranteed unique across a long report the way
 * "End Balance" is in a statement, so cells are addressed by column
 * INDEX, never by re-finding a heading string.
 *
 * ⚠️ Calculation-free by design. Every number handed to this class is
 * already fully computed by the existing, untouched report engines:
 *   - Company/Contract Cash Flow: Result.vue's own buildCurrencyTable()
 *     performs a deliberate row-mutation pass client-side (Total Cash
 *     Inflow/Outflow, Net Cash, Accumulated Net Cash all depend on
 *     rows computed earlier in that SAME pass — see that function's
 *     docblock). The export payload is captured from the
 *     ALREADY-RENDERED table and POSTed here, never recomputed in
 *     PHP — re-implementing that intricate logic a second time would
 *     risk silently drifting from what's on screen.
 *   - Consolidated Cash Flow: ConsolidatedCashFlowService::build()
 *     already computes everything server-side, so its controller
 *     shapes rows directly from that same payload.
 */
class CashFlowMatrixExport implements FromArray, ShouldAutoSize, WithEvents
{
    use Exportable;
    use RegistersEventListeners;

    /** @var list<string> */
    private array $headings;

    /** @var list<array{label:string,type:string,values:list<float|int|null>,total:float|int|null}> */
    private array $rows;

    private string $title;

    /**
     * @param  list<string>  $headings  ['Item', period label, period label, ..., 'Total']
     * @param  list<array{label:string,type:string,values:list<float|int|null>,total:float|int|null}>  $rows
     */
    public function __construct(array $headings, array $rows, string $title = '')
    {
        $this->headings = $headings;
        $this->rows = $rows;
        $this->title = $title;
    }

    public function array(): array
    {
        $sheet = [];

        if ($this->title !== '') {
            $sheet[] = array_merge([$this->title], array_fill(0, max(0, count($this->headings) - 1), ''));
            $sheet[] = array_fill(0, count($this->headings), '');
        }

        $sheet[] = $this->headings;

        foreach ($this->rows as $row) {
            $sheet[] = array_merge(
                [(string) ($row['label'] ?? '')],
                array_map(static fn ($v) => (float) $v, $row['values'] ?? []),
                [(float) ($row['total'] ?? 0)]
            );
        }

        return $sheet;
    }

    public function afterSheet(AfterSheet $event): void
    {
        $sheet = $event->sheet->getDelegate();
        $lastColumnIndex = count($this->headings);
        if ($lastColumnIndex < 1) {
            return;
        }
        $lastColumnLetter = Coordinate::stringFromColumnIndex($lastColumnIndex);

        $titleRows = $this->title !== '' ? 2 : 0;
        $headerRow = $titleRows + 1;
        $firstDataRow = $headerRow + 1;
        $lastDataRow = $headerRow + count($this->rows);

        if ($this->title !== '') {
            $sheet->mergeCells("A1:{$lastColumnLetter}1");
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
            $sheet->getRowDimension(1)->setRowHeight(24);
        }

        // Header row (period labels)
        $headerRange = "A{$headerRow}:{$lastColumnLetter}{$headerRow}";
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0D2038');
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension($headerRow)->setRowHeight(22);
        $sheet->freezePane('B'.$firstDataRow);

        if (empty($this->rows)) {
            return;
        }

        $sheet->getStyle("A{$firstDataRow}:{$lastColumnLetter}{$lastDataRow}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D9DEE5');

        // All value cells (everything except column A) get right-aligned, 2-decimal numbers.
        if ($lastColumnIndex >= 2) {
            $valueRange = "B{$firstDataRow}:{$lastColumnLetter}{$lastDataRow}";
            $sheet->getStyle($valueRange)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle($valueRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        }

        $currentRow = $firstDataRow;
        $bandIndex = 0;

        foreach ($this->rows as $row) {
            $type = $row['type'] ?? 'row';
            $rowRange = "A{$currentRow}:{$lastColumnLetter}{$currentRow}";

            if ($type === 'section') {
                $sheet->mergeCells($rowRange);
                $sheet->getStyle($rowRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle($rowRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1F3A5F');
                $sheet->getStyle($rowRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            } elseif ($type === 'total') {
                $sheet->getStyle($rowRange)->getFont()->setBold(true);
                $sheet->getStyle($rowRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8ECF1');
                $sheet->getStyle($rowRange)->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM);
            } elseif ($type === 'net') {
                $sheet->getStyle($rowRange)->getFont()->setBold(true);
                for ($col = 2; $col <= $lastColumnIndex; $col++) {
                    $letter = Coordinate::stringFromColumnIndex($col);
                    $value = (float) $sheet->getCell("{$letter}{$currentRow}")->getValue();
                    $rgb = $value > 0 ? '1D9A6C' : ($value < 0 ? 'C0392B' : '8C97A6');
                    $sheet->getStyle("{$letter}{$currentRow}")->getFont()->getColor()->setRGB($rgb);
                }
            } else {
                if ($bandIndex % 2 === 1) {
                    $sheet->getStyle($rowRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3F5F8');
                }
                $bandIndex++;
            }

            $currentRow++;
        }
    }
}
