<?php

namespace Tests\Feature\Reports;

use App\Http\Controllers\LgByBankNameReportController;
use ReflectionMethod;
use Tests\TestCase;

/**
 * LG By Bank Name shows the renewal date of a cancelled LG, with the
 * cancellation date under it, and two totals. LG By Beneficiary Name
 * still prints the word cancelled in that column.
 */
class LgByBankNameCancellationTotalsTest extends TestCase
{
    public function test_the_bank_query_returns_both_dates_and_excludes_cancelled_from_one_total(): void
    {
        $bank = file_get_contents(app_path('Http/Controllers/LgByBankNameReportController.php'));
        $beneficiary = file_get_contents(app_path('Http/Controllers/LgByBeneficiaryNameReportController.php'));

        $this->assertStringContainsString("DATE_FORMAT(letter_of_guarantee_issuances.renewal_date,\\'%d-%m-%Y\\') as renewal_date", $bank);
        $this->assertStringContainsString('COALESCE(letter_of_guarantee_issuances.cancellation_date, cancellation_statement.date)', $bank);
        $this->assertStringContainsString("letter_of_guarantee_issuances.status = 'cancelled' THEN 0", $bank);
        $this->assertStringNotContainsString("then \\'cancelled\\' else (DATE_FORMAT(letter_of_guarantee_issuances.renewal_date", $bank);
        $this->assertStringContainsString("then \\'cancelled\\' else (DATE_FORMAT(letter_of_guarantee_issuances.renewal_date", $beneficiary);
    }

    public function test_the_screen_and_the_export_show_the_cancellation_line_and_both_totals(): void
    {
        $vue = file_get_contents(resource_path('js/Pages/Statements/LgByBankName/Result.vue'));
        $bank = file_get_contents(app_path('Http/Controllers/LgByBankNameReportController.php'));
        $beneficiaryVue = file_get_contents(resource_path('js/Pages/Statements/LgByBeneficiaryName/Result.vue'));

        $this->assertStringContainsString('row.cancellationDate', $vue);
        $this->assertStringContainsString("\$t('cancelled (:date)'", $vue);
        $this->assertStringContainsString("\$t('Total (without cancellation)')", $vue);
        $this->assertStringContainsString("\$t('Total (with cancellation)')", $vue);
        $this->assertLessThan(
            strpos($vue, "\$t('Total (with cancellation)')"),
            strpos($vue, "\$t('Total (without cancellation)')")
        );
        $this->assertStringContainsString('totalLgAmountWithoutCancellation', $vue);
        $this->assertStringContainsString("renewalDateCell(\$mapped)", $bank);
        $this->assertStringContainsString("__('Total (without cancellation)')", $bank);
        $this->assertStringContainsString("__('Total (with cancellation)')", $bank);
        $this->assertStringContainsString("row.renewalDate === 'cancelled'", $beneficiaryVue);
    }

    public function test_a_cancelled_export_cell_puts_the_cancellation_date_under_the_renewal_date(): void
    {
        $method = new ReflectionMethod(LgByBankNameReportController::class, 'renewalDateCell');
        $method->setAccessible(true);
        $controller = new LgByBankNameReportController;

        $this->assertSame(
            "01-06-2027\n".__('cancelled (:date)', ['date' => '20-10-2026']),
            $method->invoke($controller, [
                'renewalDate' => '01-06-2027',
                'status' => 'cancelled',
                'cancellationDate' => '20-10-2026',
            ])
        );
        $this->assertSame("01-06-2027\ncancelled", $method->invoke($controller, [
            'renewalDate' => '01-06-2027',
            'status' => 'cancelled',
            'cancellationDate' => null,
        ]));
        $this->assertSame('01-06-2027', $method->invoke($controller, [
            'renewalDate' => '01-06-2027',
            'status' => 'running',
            'cancellationDate' => null,
        ]));
    }
}
