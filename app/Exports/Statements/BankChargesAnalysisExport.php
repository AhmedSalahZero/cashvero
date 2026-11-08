<?php

namespace App\Exports\Statements;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Bank Interest, Commissions & Charges Analysis workbook — one sheet per
 * view of the same lines: by bank, by facility, and every line. Each sheet
 * keeps the shared statement styling and a real =SUM() totals row, which
 * is why the bank subtotals live on their own sheet instead of between
 * the facility rows (a SUM over those would count every bank twice).
 */
class BankChargesAnalysisExport implements WithMultipleSheets
{
    use Exportable;

    /**
     * @param  array<string, array{headings: array<int, string>, rows: iterable}>  $sheets  title => content
     */
    public function __construct(private array $sheets)
    {
    }

    public function sheets(): array
    {
        $result = [];
        foreach ($this->sheets as $title => $sheet) {
            $result[] = new BankChargesAnalysisSheet($title, $sheet['headings'], $sheet['rows']);
        }

        return $result;
    }
}
