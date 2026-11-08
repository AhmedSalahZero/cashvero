<?php

namespace App\Support\Excel;

use Maatwebsite\Excel\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

/**
 * Writes text typed by users as TEXT, never as a spreadsheet formula.
 *
 * The default binder turns any cell value that starts with "=" into a live formula, so a customer named
 * =HYPERLINK("http://evil","Click") would run in the accountant's Excel (formula injection). The only formulas the
 * application writes itself are the totals row (=SUM(E2:E40)) and a single cell reference (=E40); those are the only
 * ones kept. Anything else starting with "=" is stored as plain text.
 */
class SafeValueBinder extends DefaultValueBinder
{
    private const OWN_FORMULAS = '/^=(SUM\([A-Z]{1,3}\d+:[A-Z]{1,3}\d+\)|[A-Z]{1,3}\d+)$/';

    public function bindValue(Cell $cell, $value): bool
    {
        if (is_string($value) && strlen($value) > 1 && $value[0] === '=' && ! preg_match(self::OWN_FORMULAS, $value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
