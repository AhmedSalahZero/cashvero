<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\NamedRange;
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

        $this->addAccountNumberValidation($sheet, $workbook, $draweeBankColumn);
    }

    /**
     * Bug fix (client-flagged, confirmed 2026-08-15): the template already
     * forced Drawee Bank to be picked from a real list, but Account Number
     * was still free text — nothing stopped a typo, and nothing tied the
     * imported row to one specific account (see
     * ContractLoanSchedule::getAccountNumberAttribute() and the
     * 2026-08-15 migrations for the storage side of this fix).
     *
     * This adds the matching dropdown, scoped to whichever bank the user
     * picks on that row: a hidden "BankAccounts" sheet holds one column of
     * account numbers per bank plus a bank-name -> column-code lookup
     * table, and the Account Number cell's validation list is
     * INDIRECT(VLOOKUP(<this row's bank cell>, map, 2, 0)) — so picking a
     * different bank changes which accounts are offered, same row.
     */
    protected function addAccountNumberValidation($sheet, $workbook, string $draweeBankColumn): void
    {
        $accountNumberColumn = $this->getAccountNumberColumnLetter();
        if (! $accountNumberColumn) {
            return;
        }

        $accountsByBank = getCompanyDraweeBankAccountNumbersGrouped($this->company_id, $this->bankIds);
        if ($accountsByBank === []) {
            return;
        }

        $mapSheet = new Worksheet($workbook, 'BankAccounts');
        $workbook->addSheet($mapSheet);
        $mapSheet->setSheetState(Worksheet::SHEETSTATE_VERYHIDDEN);

        $bankIndex = 0;
        foreach ($accountsByBank as $bankName => $accountNumbers) {
            $bankIndex++;
            $code = 'BankAcc'.$bankIndex;

            // bank name -> code, for the VLOOKUP map (columns A/B)
            $mapSheet->setCellValue('A'.$bankIndex, $bankName);
            $mapSheet->setCellValue('B'.$bankIndex, $code);

            // that bank's account numbers, in their own column starting at C
            $accountColumn = Coordinate::stringFromColumnIndex(3 + $bankIndex - 1);
            foreach ($accountNumbers as $rowOffset => $accountNumber) {
                $mapSheet->setCellValue($accountColumn.($rowOffset + 1), $accountNumber);
            }

            $workbook->addNamedRange(new NamedRange(
                $code,
                $mapSheet,
                sprintf('$%1$s$1:$%1$s$%2$d', $accountColumn, count($accountNumbers))
            ));
        }

        $workbook->addNamedRange(new NamedRange(
            'BankAccountCodeMap',
            $mapSheet,
            sprintf('$A$1:$B$%d', $bankIndex)
        ));

        $validation = new DataValidation();
        $validation->setType(DataValidation::TYPE_LIST);
        $validation->setErrorStyle(DataValidation::STYLE_STOP);
        $validation->setAllowBlank(true);
        $validation->setShowInputMessage(true);
        $validation->setShowErrorMessage(true);
        $validation->setShowDropDown(true);
        $validation->setErrorTitle(__('Invalid Account Number'));
        $validation->setError(__('Please pick the Drawee Bank first, then select one of its account numbers from the list'));
        $validation->setPromptTitle(__('Account Number'));
        $validation->setPrompt(__('Select an account number for the bank chosen in this row'));
        $validation->setFormula1(sprintf('INDIRECT(VLOOKUP(%s2,BankAccountCodeMap,2,0))', $draweeBankColumn));
        $validation->setSqref(sprintf('%s2:%s%d', $accountNumberColumn, $accountNumberColumn, self::DATA_ROWS));

        $sheet->setDataValidation($accountNumberColumn.'2', $validation);
        $sheet->getColumnDimension($accountNumberColumn)->setWidth(30);
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

    protected function getAccountNumberColumnLetter(): ?string
    {
        $columnIndex = 1;

        foreach ($this->headings() as $heading) {
            if (isAccountNumberImportHeading((string) $heading)) {
                return Coordinate::stringFromColumnIndex($columnIndex);
            }

            $columnIndex++;
        }

        return null;
    }
}
