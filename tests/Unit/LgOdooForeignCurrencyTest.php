<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\LetterOfGuaranteeIssuance;
use App\Services\Api\LetterOfGuaranteeService;
use ReflectionClass;
use ReflectionMethod;
use Tests\TestCase;

/**
 * * أودو بيعتبر debit/credit على السطر **دايمًا** بعملة الشركة الأساسية ،
 * * و amount_currency هو اللي بيشيل المبلغ بالعملة الأجنبية
 *
 * * قبل كده الكود كان بيبعت المبلغ بالعملة الأجنبية في خانة debit مع
 * * currency_id أجنبي و من غير amount_currency خالص ، فأودو كان بيعيد
 * * حساب الخانتين من بعض و يطلّعهم **أصفار**
 *
 * * ده اتأكد على الداتا الحقيقية : خطاب الضمان 182 بالدولار ، قيد عمولته
 * * في أودو (QNB$/2025/00101) اتقفل posted بصفر في كل الخانات مع إن
 * * العمولة 219 دولار
 */
class LgOdooForeignCurrencyTest extends TestCase
{
    private function payload(float $amount, ?float $inMainCurrency = null): array
    {
        $method = new ReflectionMethod(LetterOfGuaranteeService::class, 'getDataFormatted');
        $method->setAccessible(true);

        $service = (new ReflectionClass(LetterOfGuaranteeService::class))->newInstanceWithoutConstructor();

        return $method->invoke($service, '2025-07-01', $amount, 2, 10, 400, 500, 'ref', 700, 'msg', null, [], $inMainCurrency);
    }

    private function lines(array $payload): array
    {
        return [$payload['line_ids'][0][2], $payload['line_ids'][1][2]];
    }

    /* ───────────── الحالة اللي كانت مكسورة ───────────── */

    /**
     * * غطاء 5,475 دولار بسعر 50 = 273,750 جنيه
     */
    public function test_a_foreign_currency_entry_splits_the_two_amounts_correctly(): void
    {
        [$debitLine, $creditLine] = $this->lines($this->payload(5475.00, 273750.00));

        $this->assertSame(273750.00, $debitLine['debit'], 'debit بالجنيه');
        $this->assertSame(0.0, $debitLine['credit']);
        $this->assertSame(5475.00, $debitLine['amount_currency'], 'amount_currency بالدولار');

        $this->assertSame(0.0, $creditLine['debit']);
        $this->assertSame(273750.00, $creditLine['credit'], 'credit بالجنيه');
        $this->assertSame(-5475.00, $creditLine['amount_currency'], 'سالب في الدائن');
    }

    public function test_the_two_lines_balance_in_both_currencies(): void
    {
        [$debitLine, $creditLine] = $this->lines($this->payload(5475.00, 273750.00));

        $this->assertSame(
            $debitLine['debit'] - $creditLine['credit'],
            0.0,
            'القيد متوازن بعملة الشركة'
        );
        $this->assertSame(
            $debitLine['amount_currency'] + $creditLine['amount_currency'],
            0.0,
            'و متوازن بالعملة الأجنبية'
        );
    }

    /**
     * * الإشارة غلط = أودو يرفض القيد أو يقلب اتجاهه
     */
    public function test_the_currency_amount_is_positive_on_debit_and_negative_on_credit(): void
    {
        [$debitLine, $creditLine] = $this->lines($this->payload(1000.0, 50000.0));

        $this->assertGreaterThan(0, $debitLine['amount_currency']);
        $this->assertLessThan(0, $creditLine['amount_currency']);
    }

    /**
     * * المبلغ السالب (الإصدار بيضرب في -1) لازم يطلع موجب في المدين
     */
    public function test_a_negative_input_still_produces_a_positive_debit(): void
    {
        [$debitLine, $creditLine] = $this->lines($this->payload(-5475.00, -273750.00));

        $this->assertSame(273750.00, $debitLine['debit']);
        $this->assertSame(5475.00, $debitLine['amount_currency']);
        $this->assertSame(-5475.00, $creditLine['amount_currency']);
    }

    /* ───────────── الحالة اللي مالهاش تتغير ───────────── */

    /**
     * * 44 خطاب من 45 عندك بالجنيه — العملة نفسها فالرقمين واحد
     */
    public function test_a_same_currency_entry_keeps_the_amount_in_both_places(): void
    {
        [$debitLine, $creditLine] = $this->lines($this->payload(12000.00, 12000.00));

        $this->assertSame(12000.00, $debitLine['debit']);
        $this->assertSame(12000.00, $debitLine['amount_currency']);
        $this->assertSame(12000.00, $creditLine['credit']);
        $this->assertSame(-12000.00, $creditLine['amount_currency']);
    }

    /**
     * * لو المنادي مبعتش المبلغ بالعملة الأساسية بنفترض إنها نفس العملة —
     * * فالنداءات القديمة تفضل شغالة زي ما هي
     */
    public function test_omitting_the_main_currency_amount_falls_back_to_the_same_number(): void
    {
        [$debitLine, $creditLine] = $this->lines($this->payload(8000.00));

        $this->assertSame(8000.00, $debitLine['debit']);
        $this->assertSame(8000.00, $debitLine['amount_currency']);
        $this->assertSame(-8000.00, $creditLine['amount_currency']);
    }

    /* ───────────── الحقول لازم تكون موجودة أصلاً ───────────── */

    public function test_every_line_carries_amount_currency(): void
    {
        foreach ($this->lines($this->payload(100.0, 5000.0)) as $index => $line) {
            $this->assertArrayHasKey('amount_currency', $line, 'السطر '.$index.' من غير amount_currency');
            $this->assertArrayHasKey('currency_id', $line);
        }
    }

    public function test_the_currency_id_still_goes_on_both_lines(): void
    {
        foreach ($this->lines($this->payload(100.0, 5000.0)) as $line) {
            $this->assertSame(2, $line['currency_id']);
        }
    }

    /* ───────────── التحويل نفسه ───────────── */

    public function test_the_helper_returns_the_amount_untouched_for_the_company_currency(): void
    {
        $company = new Company;
        $company->forceFill(['id' => 92, 'main_functional_currency' => 'EGP']);

        $lg = new LetterOfGuaranteeIssuance;
        $lg->forceFill(['lg_currency' => 'EGP', 'issuance_date' => '2025-07-01']);
        $lg->setRelation('company', $company);

        $this->assertSame(5000.0, $lg->getAmountInMainFunctionalCurrency(5000.0, '2025-07-01'));
    }

    public function test_the_helper_is_safe_when_there_is_no_company(): void
    {
        $lg = new LetterOfGuaranteeIssuance;
        $lg->forceFill(['lg_currency' => 'USD']);
        $lg->setRelation('company', null);

        $this->assertSame(5000.0, $lg->getAmountInMainFunctionalCurrency(5000.0, '2025-07-01'));
    }

    /* ───────────── التوصيلات ───────────── */

    /**
     * * السبع نداءات : الإصدار و العمولة و مصاريف الإصدار و التجديد و
     * * الغطاء من الشاشتين ، و **الإلغاء**
     */
    public function test_every_call_site_passes_the_converted_amount(): void
    {
        $files = [
            'Models/LetterOfGuaranteeIssuance.php' => 4,
            'Http/Controllers/LetterOfGuaranteeIssuanceController.php' => 1,
            'Models/LgRenewalDateHistory.php' => 1,
            'Http/Controllers/BankStatementController.php' => 1,
        ];

        $total = 0;

        foreach ($files as $file => $expected) {
            $source = file_get_contents(app_path($file));
            $found = substr_count($source, 'getAmountInMainFunctionalCurrency(');

            if ($file === 'Models/LetterOfGuaranteeIssuance.php') {
                $found--; // تعريف الميثود نفسها
            }

            $this->assertSame($expected, $found, $file.' ناقصه نداءات');
            $total += $found;
        }

        $this->assertSame(7, $total, 'السبع نداءات كلها');
    }

    public function test_the_cancel_path_is_covered_too(): void
    {
        $source = file_get_contents(app_path('Models/LetterOfGuaranteeIssuance.php'));

        preg_match('/createLgCancelCashCover\([^;]+/', $source, $matches);

        $this->assertNotEmpty($matches, 'مش لاقي نداء الإلغاء');
        $this->assertStringContainsString(
            'getAmountInMainFunctionalCurrency(',
            $matches[0],
            'الإلغاء فيه نفس الباج و لازم يتصلح معاهم'
        );
    }
}
