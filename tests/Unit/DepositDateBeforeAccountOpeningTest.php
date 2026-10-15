<?php

namespace Tests\Unit;

use App\Models\CertificatesOfDeposit;
use App\Models\FinancialInstitutionAccount;
use App\Models\TimeOfDeposit;
use Tests\TestCase;

/**
 * * أي حركة بتتكتب في كشف الحساب الجاري بتاريخ قبل رصيد أول المدة بتاع
 * * الحساب بتخلي الكشف يبدأ برصيد مش الرصيد الافتتاحي المعتمد
 *
 * * ده اللي حصل فعلاً: وديعة 37 (opening balance ، بدايتها 2024-01-31)
 * * نزّلت فوايد دورية بتواريخ 2025-03-24 و 2025-04-05 و 2025-04-20 و
 * * 2025-04-30 على الحساب 1930001000006119 اللي رصيده الافتتاحي
 * * 2025-07-31 — و مكانش فيه أي فحص يمنع ده
 */
class DepositDateBeforeAccountOpeningTest extends TestCase
{
    private const OPENING = '2025-07-31';

    private function deposit(string $class = TimeOfDeposit::class, ?string $openingDate = self::OPENING): object
    {
        $deposit = new $class;

        if ($openingDate === null) {
            $deposit->setRelation('maturityAmountAddedToAccount', null);

            return $deposit;
        }

        $account = new FinancialInstitutionAccount;
        $account->forceFill(['account_number' => '1930001000006119', 'balance_date' => $openingDate]);
        $deposit->setRelation('maturityAmountAddedToAccount', $account);

        return $deposit;
    }

    /* ───────────── الحالات اللي لازم تترفض ───────────── */

    /**
     * * التواريخ الأربعة الحقيقية اللي دخلت غلط
     */
    public function test_the_four_dates_that_actually_slipped_in_are_now_rejected(): void
    {
        foreach (['2025-03-24', '2025-04-05', '2025-04-20', '2025-04-30'] as $date) {
            $error = $this->deposit()->getDateBeforeAccountOpeningError($date);

            $this->assertNotNull($error, 'المفروض يترفض: '.$date);
            $this->assertStringContainsString('31-07-2025', $error, 'الرسالة لازم تقول تاريخ الرصيد الافتتاحي');
        }
    }

    public function test_the_day_right_before_the_opening_is_rejected(): void
    {
        $this->assertNotNull($this->deposit()->getDateBeforeAccountOpeningError('2025-07-30'));
    }

    public function test_a_much_older_date_is_rejected(): void
    {
        $this->assertNotNull($this->deposit()->getDateBeforeAccountOpeningError('2024-01-31'));
    }

    /* ───────────── الحالات اللي لازم تعدّي ───────────── */

    public function test_the_opening_day_itself_is_allowed(): void
    {
        $this->assertNull(
            $this->deposit()->getDateBeforeAccountOpeningError(self::OPENING),
            'الحد نفسه مسموح — الشرط "أكبر من أو يساوي"'
        );
    }

    public function test_the_day_right_after_the_opening_is_allowed(): void
    {
        $this->assertNull($this->deposit()->getDateBeforeAccountOpeningError('2025-08-01'));
    }

    public function test_a_later_date_is_allowed(): void
    {
        $this->assertNull($this->deposit()->getDateBeforeAccountOpeningError('2026-08-05'));
    }

    public function test_the_time_part_does_not_change_the_verdict(): void
    {
        $this->assertNull(
            $this->deposit()->getDateBeforeAccountOpeningError('2025-07-31 23:59:59'),
            'نفس اليوم مسموح مهما كانت الساعة'
        );
        $this->assertNotNull(
            $this->deposit()->getDateBeforeAccountOpeningError('2025-07-30 23:59:59'),
            'اليوم اللي قبله مرفوض مهما كانت الساعة'
        );
    }

    /* ───────────── الحالات الحدّية ───────────── */

    public function test_a_deposit_without_a_maturity_account_is_not_blocked(): void
    {
        $this->assertNull(
            $this->deposit(TimeOfDeposit::class, null)->getDateBeforeAccountOpeningError('2020-01-01'),
            'من غير حساب استحقاق مفيش كشف تتكتب فيه الحركة، فما بنمنعش'
        );
    }

    public function test_an_account_without_an_opening_date_is_not_blocked(): void
    {
        $this->assertNull($this->deposit(TimeOfDeposit::class, '')->getDateBeforeAccountOpeningError('2020-01-01'));
    }

    public function test_an_empty_date_is_left_to_the_required_check(): void
    {
        $this->assertNull(
            $this->deposit()->getDateBeforeAccountOpeningError(null),
            'التاريخ الفاضي بيتمسك بفحص "مطلوب" اللي قبله، مش هنا'
        );
    }

    /* ───────────── شهادة الإيداع كمان ───────────── */

    public function test_certificates_of_deposit_get_the_same_guard(): void
    {
        $certificate = $this->deposit(CertificatesOfDeposit::class);

        $this->assertNotNull($certificate->getDateBeforeAccountOpeningError('2025-04-30'));
        $this->assertNull($certificate->getDateBeforeAccountOpeningError('2025-08-01'));
    }

    /* ───────────── التوصيلات ───────────── */

    /**
     * * التلات مسارات اللي بتاخد تاريخ من المستخدم و بتكتب في الكشف
     */
    public function test_every_date_entry_point_is_guarded_in_both_deposit_types(): void
    {
        foreach (['TimeOfDepositsController', 'CertificatesOfDepositsController'] as $controller) {
            $source = file_get_contents(app_path('Http/Controllers/'.$controller.'.php'));

            $this->assertSame(
                3,
                substr_count($source, 'getDateBeforeAccountOpeningError'),
                $controller.' لازم يحمي الفايدة الدورية و الاستحقاق و الكسر'
            );

            foreach (['periodInterestDate', 'actualDepositDate', 'breakDate'] as $variable) {
                $this->assertStringContainsString(
                    'getDateBeforeAccountOpeningError($'.$variable.')',
                    $source,
                    $controller.' مش بيحمي '.$variable
                );
            }
        }
    }

    public function test_the_guard_runs_before_anything_is_written(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/TimeOfDepositsController.php'));

        $guardAt = strpos($source, 'getDateBeforeAccountOpeningError($periodInterestDate)');
        $writeAt = strpos($source, 'applyPeriodicInterestInStatement(');

        $this->assertNotFalse($guardAt);
        $this->assertNotFalse($writeAt);
        $this->assertLessThan($writeAt, $guardAt, 'الفحص لازم يسبق الكتابة في الكشف');
    }

    public function test_the_error_message_is_translated(): void
    {
        $translations = json_decode(file_get_contents(resource_path('lang/ar.json')), true);

        $this->assertArrayHasKey('Transaction Date Must Be Greater Than Or Equal Account Opening Balance Date', $translations);
    }
}
