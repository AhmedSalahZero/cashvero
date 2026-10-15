<?php

namespace App\Exports\LgImport;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class LgIssuanceTemplateSheetExport implements FromArray, WithEvents, WithTitle
{
    use RegistersEventListeners;

    private const MAX_ROWS = 5000;

    public function __construct(
        protected array $columns,
        protected array $dropDownOptions,
        protected ?array $exampleRow = null
    ) {
    }

    public function array(): array
    {
        $rows = [
            $this->columns,
        ];

        if (is_array($this->exampleRow) && count($this->exampleRow)) {
            $rows[] = $this->exampleRow;
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Template';
    }

    public function afterSheet(AfterSheet $event): void
    {
        $sheet = $event->sheet->getDelegate();
        $sheet->freezePane('A2');

        foreach ($this->columns as $index => $columnName) {
            if (! isset($this->dropDownOptions[$columnName])) {
                continue;
            }

            $columnLetter = Coordinate::stringFromColumnIndex($index + 1);
            $lookupColumnIndex = array_search($columnName, array_keys($this->dropDownOptions), true);
            $lookupColumnLetter = Coordinate::stringFromColumnIndex(($lookupColumnIndex ?: 0) + 1);
            $range = sprintf("'Lookups'!\$%s\$2:\$%s\$%d", $lookupColumnLetter, $lookupColumnLetter, max(2, count($this->dropDownOptions[$columnName]) + 1));
            $validation = $this->listValidation($range);
            $validation->setSqref(sprintf('%s2:%s%d', $columnLetter, $columnLetter, self::MAX_ROWS));
            $sheet->setDataValidation($columnLetter.'2', $validation);
        }
    }

    protected function listValidation(string $range): DataValidation
    {
        $validation = new DataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setAllowBlank(true);
        $validation->setShowDropDown(true);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setErrorTitle(__('Invalid Value'));
        $validation->setError(__('Please select one of the allowed values.'));
        $validation->setFormula1($range);

        return $validation;
    }
}
