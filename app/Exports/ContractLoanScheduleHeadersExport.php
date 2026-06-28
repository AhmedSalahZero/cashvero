<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ContractLoanScheduleHeadersExport extends HeadersExport implements WithEvents
{
    use RegistersEventListeners;

    private const DATA_ROWS = 5000;

    public function afterSheet(AfterSheet $event): void
    {
        $sheet = $event->sheet->getDelegate();
        $draweeBankColumn = $this->getDraweeBankColumnLetter();

        if (! $draweeBankColumn) {
            return;
        }

        $bankNames = getCompanyDraweeBankNames($this->company_id);

        if ($bankNames === []) {
            return;
        }

        $workbook = $sheet->getParent();
        if (! $workbook) {
            return;
        }

        $listSheet = new Worksheet($workbook, 'DraweeBanks');
        $workbook->addSheet($listSheet);
        $listSheet->setSheetState(Worksheet::SHEETSTATE_VERYHIDDEN);

        foreach ($bankNames as $index => $bankName) {
            $listSheet->setCellValue('A'.($index + 1), $bankName);
        }

        $listRange = sprintf("'DraweeBanks'!\$A\$1:\$A\$%d", count($bankNames));
        $validation = $this->createDraweeBankValidation($listRange);
        $validation->setSqref(sprintf('%s2:%s%d', $draweeBankColumn, $draweeBankColumn, self::DATA_ROWS));
        $sheet->setDataValidation($draweeBankColumn.'2', $validation);

        $sheet->getColumnDimension($draweeBankColumn)->setWidth(55);
    }

    protected function createDraweeBankValidation(string $listRange): DataValidation
    {
        $validation = new DataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setErrorTitle(__('Invalid Drawee Bank'));
        $validation->setError(__('Please select a bank from the list'));
        $validation->setPromptTitle(__('Drawee Bank'));
        $validation->setPrompt(__('Select a bank from your financial institutions'));
        $validation->setFormula1($listRange);

        return $validation;
    }

    protected function getDraweeBankColumnLetter(): ?string
    {
        $columnIndex = 1;

        foreach ($this->headings() as $heading) {
            if (isDraweeBankImportHeading((string) $heading)) {
                return Coordinate::stringFromColumnIndex($columnIndex);
            }

            $columnIndex++;
        }

        return null;
    }
}
