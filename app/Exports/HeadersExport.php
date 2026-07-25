<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class HeadersExport implements WithHeadings, WithColumnFormatting
{
    use Exportable;

    public $heads;

    /**
     * @param mixed $company_id Accepted for call-site compatibility, but intentionally unused —
     *                          company scoping already happens before the heads array reaches
     *                          this class (confirmed, Stage 2 audit finding #3.2, 2026-07-24).
     * @param array $heads
     */
    public function __construct($company_id, $heads = [])
    {
        $this->heads = $heads;
    }

    public function headings(): array
    {
        return array_values($this->heads);
    }

    public function columnFormats(): array
    {
        $formats = [];
        $columnIndex = 1;

        foreach ($this->headings() as $heading) {
            if (isImportIdentifierHeading((string) $heading)) {
                $formats[Coordinate::stringFromColumnIndex($columnIndex)] = NumberFormat::FORMAT_TEXT;
            }

            $columnIndex++;
        }

        return $formats;
    }
}
