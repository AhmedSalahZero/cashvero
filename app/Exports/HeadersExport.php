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

    public $company_id;

    public $heads;

    public function __construct($company_id, $heads = [])
    {
        $this->company_id = $company_id;
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
