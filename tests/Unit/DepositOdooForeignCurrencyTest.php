<?php

namespace Tests\Unit;

use App\Models\CertificatesOfDeposit;
use App\Models\Company;
use App\Models\TimeOfDeposit;
use App\Services\Api\TimeOrCertificateOfDepositOdooService;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * * نفس باج خطابات الضمان بالظبط لكن في الودائع الزمنية و شهادات
 * * الإيداع : debit/credit في أودو **دايمًا** بعملة الشركة الأساسية ،
 * * و amount_currency هو اللي بيشيل المبلغ بالعملة الأجنبية
 *
 * * الفرق هنا إن اتجاه السطرين بيتقلب حسب isBreakOrApplyDeposit ،
 * * فإشارة amount_currency لازم تتقلب معاه
 */
class DepositOdooForeignCurrencyTest extends TestCase
{
    private function service(): TimeOrCertificateOfDepositOdooService
    {
        return (new ReflectionClass(TimeOrCertificateOfDepositOdooService::class))->newInstanceWithoutConstructor();
    }

    /** * قيد إنشاء / استحقاق الوديعة */
    private function depositPayload(float $amount, ?float $inMain, bool $isBreakOrApply): array
    {
        $method = new ReflectionMethod(TimeOrCertificateOfDepositOdooService::class, 'getDataFormatted');
        $method->setAccessible(true);

        $payload = $method->invoke($this->service(), '2025-07-01', $amount, 2, 10, 400, 500, 'ref', null, 'msg', null, $isBreakOrApply, $inMain);

        return [$payload['line_ids'][0][2], $payload['line_ids'][1][2]];
    }

    /** * قيد الفوايد */
    private function interestPayload(float $amount, ?float $inMain): array
    {
        $method = new ReflectionMethod(TimeOrCertificateOfDepositOdooService::class, 'getMoneyDepositDataFormatted');
        $method->setAccessible(true);

        $payload = $method->invoke($this->service(), '2025-07-01', $amount, 2, 10, 400, 500, 'ref', null, 'msg', null, $inMain);

        return [$payload['line_ids'][0][2], $payload['line_ids'][1][2]];
    }

    /* ───────────── إنشاء الوديعة ───────────── */

    /**
     * * وديعة 10,000 دولار بسعر 50 = 500,000 جنيه
     */
    public function test_creating_a_foreign_deposit_splits_the_two_amounts(): void
    {
        [$first, $second] = $this->depositPayload(10000.00, 500000.00, false);

        $this->assertSame(500000.00, $first['debit'], 'debit بالجنيه');
        $this->assertSame(0.0, $first['credit']);
        $this->assertSame(10000.00, $first['amount_currency'], 'amount_currency بالدولار');

        $this->assertSame(0.0, $second['debit']);
        $this->assertSame(500000.00, $second['credit']);
        $this->assertSame(-10000.00, $second['amount_currency']);
    }

    /* ───────────── الاستحقاق / الكسر — الاتجاه بيتقلب ───────────── */

    public function test_maturing_a_foreign_deposit_flips_both_the_side_and_the_sign(): void
    {
        [$first, $second] = $this->depositPayload(10000.00, 500000.00, true);

        $this->assertSame(0.0, $first['debit'], 'الاتجاه اتقلب');
        $this->assertSame(500000.00, $first['credit']);
        $this->assertSame(-10000.00, $first['amount_currency'], 'الإشارة اتقلبت مع الاتجاه');

        $this->assertSame(500000.00, $second['debit']);
        $this->assertSame(0.0, $second['credit']);
        $this->assertSame(10000.00, $second['amount_currency']);
    }

    /**
     * * أهم قاعدة : الإشارة لازم تمشي مع اتجاه السطر ، و إلا أودو يرفض
     * * القيد أو يقلبه
     */
    public function test_the_sign_always_follows_the_side_in_both_directions(): void
    {
        foreach ([false, true] as $isBreakOrApply) {
            foreach ($this->depositPayload(7500.00, 375000.00, $isBreakOrApply) as $index => $line) {
                if ($line['debit'] > 0) {
                    $this->assertGreaterThan(0, $line['amount_currency'], 'المدين لازم يكون موجب');
                } else {
                    $this->assertLessThan(0, $line['amount_currency'], 'الدائن لازم يكون سالب');
                }
            }
        }
    }

    public function test_the_entry_balances_in_both_currencies(): void
    {
        foreach ([false, true] as $isBreakOrApply) {
            [$first, $second] = $this->depositPayload(7500.00, 375000.00, $isBreakOrApply);

            $this->assertSame(0.0, $first['debit'] + $second['debit'] - $first['credit'] - $second['credit'], 'متوازن بعملة الشركة');
            $this->assertSame(0.0, $first['amount_currency'] + $second['amount_currency'], 'متوازن بالعملة الأجنبية');
        }
    }

    /* ───────────── قيد الفوايد ───────────── */

    public function test_the_interest_entry_splits_the_two_amounts_too(): void
    {
        [$first, $second] = $this->interestPayload(250.00, 12500.00);

        $this->assertSame(12500.00, $first['debit']);
        $this->assertSame(250.00, $first['amount_currency']);
        $this->assertSame(12500.00, $second['credit']);
        $this->assertSame(-250.00, $second['amount_currency']);
    }

    /* ───────────── الحالة اللي مالهاش تتغير ───────────── */

    public function test_a_same_currency_deposit_is_unchanged(): void
    {
        [$first, $second] = $this->depositPayload(30000.00, 30000.00, false);

        $this->assertSame(30000.00, $first['debit']);
        $this->assertSame(30000.00, $first['amount_currency']);
        $this->assertSame(-30000.00, $second['amount_currency']);
    }

    public function test_omitting_the_main_currency_amount_falls_back(): void
    {
        [$first] = $this->depositPayload(4000.00, null, false);

        $this->assertSame(4000.00, $first['debit']);
        $this->assertSame(4000.00, $first['amount_currency']);

        [$interestFirst] = $this->interestPayload(4000.00, null);
        $this->assertSame(4000.00, $interestFirst['debit']);
        $this->assertSame(4000.00, $interestFirst['amount_currency']);
    }

    public function test_every_line_carries_amount_currency(): void
    {
        $lines = array_merge(
            $this->depositPayload(100.0, 5000.0, false),
            $this->depositPayload(100.0, 5000.0, true),
            $this->interestPayload(100.0, 5000.0),
        );

        foreach ($lines as $index => $line) {
            $this->assertArrayHasKey('amount_currency', $line, 'السطر '.$index);
            $this->assertSame(2, $line['currency_id']);
        }
    }

    /* ───────────── التحويل ───────────── */

    public function test_the_helper_returns_the_amount_untouched_for_the_company_currency(): void
    {
        foreach ([TimeOfDeposit::class, CertificatesOfDeposit::class] as $class) {
            $company = new Company;
            $company->forceFill(['id' => 92, 'main_functional_currency' => 'EGP']);

            $deposit = new $class;
            $deposit->forceFill(['currency' => 'EGP', 'start_date' => '2025-07-01']);
            $deposit->setRelation('company', $company);

            $this->assertSame(5000.0, $deposit->getAmountInMainFunctionalCurrency(5000.0, '2025-07-01'), $class);
        }
    }

    public function test_the_helper_is_safe_without_a_company(): void
    {
        $deposit = new TimeOfDeposit;
        $deposit->forceFill(['currency' => 'USD']);
        $deposit->setRelation('company', null);

        $this->assertSame(5000.0, $deposit->getAmountInMainFunctionalCurrency(5000.0, '2025-07-01'));
    }

    /* ───────────── التوصيلات ───────────── */

    public function test_both_call_sites_pass_the_converted_amount(): void
    {
        $source = file_get_contents(app_path('Traits/HasDepositAccount.php'));

        $this->assertStringContainsString(
            'createAndPostJournalEntry($date,$amount*-1',
            $source
        );
        $this->assertSame(
            2,
            substr_count($source, '$this->getAmountInMainFunctionalCurrency('),
            'النداءين لازم يمرروا المبلغ المحوّل'
        );
    }

    public function test_the_service_no_longer_puts_the_foreign_amount_in_debit(): void
    {
        $source = file_get_contents(app_path('Services/Api/TimeOrCertificateOfDepositOdooService.php'));
        $source = preg_replace('#/\*.*?\*/#s', '', $source);

        $this->assertStringNotContainsString("'debit' => abs(\$amount),", $source);
        $this->assertStringNotContainsString("'credit' => abs(\$amount),", $source);
        $this->assertSame(4, substr_count($source, "'amount_currency'"), 'أربع سطور');
    }
}
