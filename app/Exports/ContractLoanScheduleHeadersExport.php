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

    /**
     * @var int[]|null Bank (FinancialInstitution) IDs the user picked on
     * the "Export Fields" screen before downloading. Null/empty falls
     * back to every bank the company has on file (previous behavior).
     */
    protected ?array $bankIds = null;

    /**
     * ⚠️ Confirmed second bug, fixed here: HeadersExport::__construct()
     * deliberately does NOT store $company_id on $this (see its own
     * docblock — "intentionally unused"). This subclass's afterSheet()
     * reads $this->company_id to build the drawee-bank dropdown, so
     * without storing it explicitly here that value was always null —
     * the dropdown silently never populated, independent of the
     * ExportTable::validation() bug that was blocking the download
     * entirely. Storing it locally fixes both together.
     */
    protected $company_id;

    public function __construct($company_id, $columnsWithViewingNames, ?array $bankIds = null)
    {
        parent::__construct($company_id, $columnsWithViewingNames);
        $this->company_id = $company_id;
        $this->bankIds = $bankIds;
    }

    public function afterSheet(AfterSheet $event): void
    {
        $sheet = $event->sheet->getDelegate();
        $draweeBankColumn = $this->getDraweeBankColumnLetter();

        if (! $draweeBankColumn) {
            return;
        }

        $bankNames = getCompanyDraweeBankNames($this->company_id, $this->bankIds);

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
