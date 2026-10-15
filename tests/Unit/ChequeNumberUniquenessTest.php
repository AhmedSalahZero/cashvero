<?php

namespace Tests\Unit;

use App\Rules\UniqueChequeNumberForCustomerRule;
use App\Rules\UniqueChequeNumberRule;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * * رقم الشيك المستلم : فريد بالنسبة للبنك الساحب و العميل
 * * رقم الشيك المدفوع : فريد بالنسبة لرقم الحساب بس
 *
 * * الفحص هنا على الاستعلام نفسه (مش على الداتا) : بنسجّل كل استعلام
 * * بيتبعت للداتابيز و نتأكد ان الشروط اللي فيه هي المطلوبة بالظبط —
 * * كده مش محتاجين داتابيز اختبار مليانة
 */
class ChequeNumberUniquenessTest extends TestCase
{
    /** @var array<int, array{query: string, bindings: array}> */
    private array $queries = [];

    /**
     * * DB::pretend بتشغّل الكود و تسجّل الاستعلامات من غير ما تنفّذها
     * * على الداتابيز — فالتست مايحتاجش داتابيز اختبار فيها الجداول
     */
    private function capture(callable $callback): void
    {
        $this->queries = DB::pretend($callback);
    }

    private function lastQuery(): array
    {
        $this->assertNotEmpty($this->queries, 'القاعدة مانطقتش الداتابيز خالص');

        return end($this->queries);
    }

    /* ───────────── الشيك المستلم ───────────── */

    public function test_received_cheque_is_scoped_to_the_drawee_bank_and_the_customer(): void
    {
        $rule = new UniqueChequeNumberForCustomerRule(7, null, 'msg', 55);
        $this->capture(fn () => $rule->passes('cheque_number', '12345'));

        $sql = $this->lastQuery()['query'];
        $bindings = $this->lastQuery()['bindings'];

        $this->assertStringContainsString('"cheques"', str_replace('`', '"', $sql));
        $this->assertStringContainsString('money_received', $sql, 'لازم يعمل join على money_received عشان يعرف العميل');
        $this->assertStringContainsString('drawee_bank_id', $sql);
        $this->assertStringContainsString('partner_id', $sql, 'العميل لازم يكون جزء من المفتاح');
        $this->assertStringContainsString('cheque_number', $sql);

        $this->assertContains(7, $bindings, 'البنك الساحب');
        $this->assertContains(55, $bindings, 'العميل');
        $this->assertContains('12345', $bindings, 'رقم الشيك');
    }

    public function test_received_cheque_takes_the_customer_from_the_request_when_not_passed(): void
    {
        request()->merge(['customer_id' => 99]);

        $rule = new UniqueChequeNumberForCustomerRule(7, null, 'msg');
        $this->capture(fn () => $rule->passes('cheque_number', '12345'));

        $this->assertContains(99, $this->lastQuery()['bindings'], 'يقرا العميل من الريكوست لو المنادي مابعتوش');
    }

    public function test_received_cheque_excludes_the_row_being_edited(): void
    {
        $rule = new UniqueChequeNumberForCustomerRule(7, 321, 'msg', 55);
        $this->capture(fn () => $rule->passes('cheque_number', '12345'));

        $this->assertContains(321, $this->lastQuery()['bindings'], 'الصف اللي بيتعدّل نفسه مايتحسبش تكرار');
    }

    public function test_received_cheque_without_a_drawee_bank_fails_without_touching_the_database(): void
    {
        $rule = new UniqueChequeNumberForCustomerRule(null, null, 'msg', 55);
        $passed = true;
        $this->capture(function () use ($rule, &$passed) { $passed = $rule->passes('cheque_number', '12345'); });

        $this->assertFalse($passed);
        $this->assertSame([], $this->queries, 'من غير بنك ساحب مايستعلمش أصلاً');
    }

    /* ───────────── الشيك المدفوع ───────────── */

    public function test_payable_cheque_is_scoped_to_the_account_number_only(): void
    {
        $rule = new UniqueChequeNumberRule('1234567890', null, 'msg');
        $this->capture(fn () => $rule->passes('cheque_number', '777'));

        $sql = $this->lastQuery()['query'];
        $bindings = $this->lastQuery()['bindings'];

        $this->assertStringContainsString('payable_cheques', $sql);
        $this->assertStringContainsString('account_number', $sql);
        $this->assertStringNotContainsString('delivery_bank_id', $sql, 'البنك مابقاش جزء من المفتاح');
        $this->assertStringNotContainsString('partner', $sql, 'المورّد مش جزء من المفتاح في الصرف');

        $this->assertContains('1234567890', $bindings);
        $this->assertContains('777', $bindings);
    }

    public function test_payable_cheque_excludes_the_row_being_edited(): void
    {
        $rule = new UniqueChequeNumberRule('1234567890', 654, 'msg');
        $this->capture(fn () => $rule->passes('cheque_number', '777'));

        $this->assertContains(654, $this->lastQuery()['bindings']);
    }

    public function test_payable_cheque_rejects_a_zero_number_before_querying(): void
    {
        $rule = new UniqueChequeNumberRule('1234567890', null, 'msg');
        $passed = true;
        $this->capture(function () use ($rule, &$passed) { $passed = $rule->passes('cheque_number', 0); });

        $this->assertFalse($passed);
        $this->assertSame([], $this->queries);
        $this->assertSame(__('Invalid Cheque Number'), $rule->message());
    }

    public function test_payable_cheque_without_an_account_number_fails_without_touching_the_database(): void
    {
        $rule = new UniqueChequeNumberRule(null, null, 'msg');
        $passed = true;
        $this->capture(function () use ($rule, &$passed) { $passed = $rule->passes('cheque_number', '777'); });

        $this->assertFalse($passed);
        $this->assertSame([], $this->queries, 'من غير رقم حساب مايستعلمش أصلاً');
    }

    /* ───────────── نداءات القواعد من الفورم ريكوست ───────────── */

    public function test_the_form_requests_feed_the_rules_the_right_field(): void
    {
        $cases = [
            ['Http/Requests/StoreMoneyPaymentRequest.php', 'UniqueChequeNumberRule', "account_number.payable_cheque"],
            ['Http/Requests/StoreCashExpenseRequest.php', 'UniqueChequeNumberRule', "account_number.payable_cheque"],
        ];

        foreach ($cases as [$file, $rule, $expected]) {
            $source = file_get_contents(app_path($file));
            $offset = 0;
            $callArgs = [];
            while (($i = strpos($source, 'new '.$rule.'(', $offset)) !== false) {
                $callArgs[] = substr($source, $i, 220);
                $offset = $i + 1;
            }

            $this->assertNotEmpty($callArgs, 'مش لاقي نداء '.$rule.' في '.$file);

            foreach ($callArgs as $args) {
                $this->assertStringContainsString($expected, $args, $file.' لازم يبعت رقم الحساب');
                $this->assertStringNotContainsString('delivery_bank_id', $args, $file.' مابقاش يبعت البنك');
            }
        }
    }
}
