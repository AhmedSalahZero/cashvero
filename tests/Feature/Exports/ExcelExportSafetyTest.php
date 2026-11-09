<?php

namespace Tests\Feature\Exports;

use App\Models\AccountType;
use Illuminate\Support\Facades\DB;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationRedirectFilter;
use Mcamara\LaravelLocalization\Middleware\LaravelLocalizationViewPath;
use Mcamara\LaravelLocalization\Middleware\LocaleSessionRedirect;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\Concerns\MakesBanks;
use Tests\TestCase;

/**
 * A downloaded workbook is a real workbook, its totals are real formulas, and nothing a person
 * typed into the app can turn into a formula when the file is opened (a comment such as
 * =HYPERLINK("http://evil","click") would otherwise run in the accountant's Excel).
 *
 * Checked on the Bank Interest, Commissions & Charges Analysis export, which carries free text
 * (the expense comment) next to its own SUM() totals.
 *
 * Run: php artisan test --filter=ExcelExportSafetyTest
 */
class ExcelExportSafetyTest extends TestCase
{
    use MakesBanks;

    private const ATTACK = '=HYPERLINK("http://evil.invalid","click me")';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([LocaleSessionRedirect::class, LaravelLocalizationRedirectFilter::class, LaravelLocalizationViewPath::class]);
    }

    /** @return \PhpOffice\PhpSpreadsheet\Spreadsheet the workbook the export route returned */
    private function downloadWorkbook(): \PhpOffice\PhpSpreadsheet\Spreadsheet
    {
        $company = $this->makeCompany();
        $bank = $this->bank($company);

        foreach ([[100, 'Bank fees'], [50, self::ATTACK]] as [$amount, $comment]) {
            $expenseId = DB::table('cash_expenses')->insertGetId([
                'company_id' => $company->id, 'type' => 'outgoing-transfer', 'payment_date' => '1999-02-10',
                'paid_amount' => $amount, 'currency' => 'EGP', 'comment_en' => $comment, 'comment_ar' => $comment,
            ]);
            DB::table('outgoing_transfers')->insert([
                'cash_expense_id' => $expenseId, 'is_bank_charges' => 1, 'delivery_bank_id' => $bank->id,
                'account_type' => (string) $this->accountTypeId(AccountType::CURRENT_ACCOUNT), 'account_number' => 'ACC-1',
            ]);
        }

        $response = $this->actingAs($this->makeUser(['view bank statement report'], $company))
            ->get(route('export.bank.charges.analysis.report', [
                'company' => $company->id, 'start_date' => '1999-01-01', 'end_date' => '1999-12-31', 'currencies' => ['EGP'],
            ]))
            ->assertOk();

        $this->assertStringContainsString('spreadsheetml', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));

        $file = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($file, $response->streamedContent() ?: $response->getContent());
        $this->assertSame('PK', substr((string) file_get_contents($file), 0, 2), 'Not a real xlsx (zip) file');

        try {
            return IOFactory::load($file);
        } finally {
            @unlink($file);
        }
    }

    public function test_the_export_is_a_real_workbook_with_one_sheet_per_view(): void
    {
        $book = $this->downloadWorkbook();

        $this->assertSame(['By Bank', 'By Facility', 'Details'], $book->getSheetNames());
    }

    public function test_typed_text_that_looks_like_a_formula_stays_text(): void
    {
        $details = $this->downloadWorkbook()->getSheetByName('Details');

        $found = false;
        foreach ($details->getRowIterator() as $row) {
            foreach ($row->getCellIterator() as $cell) {
                if ($cell->getValue() === self::ATTACK) {
                    $found = true;
                    $this->assertSame(DataType::TYPE_STRING, $cell->getDataType(), 'A typed comment became a formula in '.$cell->getCoordinate());
                }
            }
        }

        $this->assertTrue($found, 'The comment did not reach the workbook at all.');
    }

    public function test_the_totals_are_real_formulas_that_add_up(): void
    {
        $sheet = $this->downloadWorkbook()->getSheetByName('Details');
        $headings = $sheet->rangeToArray('A1:'.$sheet->getHighestColumn().'1')[0];
        $amountColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(array_search('Amount', $headings, true) + 1);
        $totalCell = $sheet->getCell($amountColumn.$sheet->getHighestRow());

        $this->assertSame(DataType::TYPE_FORMULA, $totalCell->getDataType());
        $this->assertStringStartsWith('=SUM(', (string) $totalCell->getValue());
        $this->assertEqualsWithDelta(150.0, (float) $totalCell->getCalculatedValue(), 0.001);
    }
}
