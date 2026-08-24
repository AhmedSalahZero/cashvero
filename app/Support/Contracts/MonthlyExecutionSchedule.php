<?php

namespace App\Support\Contracts;

use Carbon\Carbon;

/**
 * * جدول التنفيذ الشهري للعقود اللي عليها is_monthly_executed.
 *
 * * العقد العادي بيتعلّق بأوامر بيع/شراء، وكل أمر بينزل في التدفق النقدي
 * * دفعة واحدة في تاريخ نهاية آخر مرحلة تنفيذ + أيام التحصيل. يعني عقد
 * * بمليون اتنفّذ منه 400 ألف، الـ 600 ألف الباقية كلها بتنزل في يوم واحد.
 *
 * * العقد بتنفيذ شهري مختلف: المتبقي بيتقسّم بالتساوي على الشهور اللي
 * * لسه ما بدأتش من مدّة العقد. عقد بمليون و200 ألف لسنة ومتبقي منه
 * * 600 ألف في شهر 6 → 100 ألف في كل شهر من 7 لحد 12.
 *
 * * ⚠️ الشهر الجاري مش بياخد شريحة — هو تحت التنفيذ دلوقتي وفاتورته
 * * بتيجي من دورة الفواتير العادية. دي القاعدة اللي مثال صاحب المشروع
 * * ماشي عليها (تقرير في شهر 6 → الشرائح تبدأ من شهر 7).
 */
class MonthlyExecutionSchedule
{
    /**
     * * تواريخ نهاية كل شهر لسه ما بدأش من مدّة العقد، مرتّبة تصاعدياً.
     *
     * * آخر شهر بيتقصّ عند تاريخ نهاية العقد لو العقد بينتهي في نص الشهر،
     * * عشان ما ننزلش فلوس بعد ما العقد يخلص.
     *
     * @return array<int, string> تواريخ Y-m-d
     */
    public static function remainingMonthEnds(string $contractStart, string $contractEnd, ?string $asOf = null): array
    {
        $end = Carbon::parse($contractEnd)->startOfDay();
        $contractFirstMonth = Carbon::parse($contractStart)->startOfMonth();
        // الشهر اللي بعد شهر تاريخ الحساب — أول شهر لسه ما بدأش
        $firstUnstartedMonth = Carbon::parse($asOf ?: Carbon::now())->startOfMonth()->addMonthNoOverflow();

        // العقد اللي لسه ما بدأش بيبدأ من أول شهر فيه، مش من الشهر الجاي
        $cursor = $contractFirstMonth->greaterThan($firstUnstartedMonth)
            ? $contractFirstMonth->copy()
            : $firstUnstartedMonth->copy();

        $monthEnds = [];
        while ($cursor->lessThanOrEqualTo($end)) {
            // ⚠️ endOfMonth() بتعدّل الكائن نفسه، فلازم copy() الأول
            $monthEnd = $cursor->copy()->endOfMonth();
            $monthEnds[] = ($monthEnd->greaterThan($end) ? $end->copy() : $monthEnd)->format('Y-m-d');
            $cursor = $cursor->copy()->addMonthNoOverflow()->startOfMonth();
        }

        return $monthEnds;
    }

    /**
     * * توزيع المتبقي على الشهور المتاحة.
     *
     * * الكسور بتتجمّع في آخر شريحة عشان مجموع الشرائح يساوي المتبقي
     * * بالظبط — لو وزّعناها بالتقريب على كل شريحة الصف كان هيختلف عن
     * * قيمة العقد بقروش.
     *
     * @param  array<int, string>  $monthEnds
     * @return array<string, float> تاريخ => مبلغ
     */
    public static function slices(float $remainingAmount, array $monthEnds): array
    {
        if (! $monthEnds || $remainingAmount <= 0) {
            return [];
        }

        $count = count($monthEnds);
        $perMonth = round($remainingAmount / $count, 2);

        $slices = [];
        $allocated = 0.0;
        foreach ($monthEnds as $index => $date) {
            $isLast = $index === $count - 1;
            $amount = $isLast ? round($remainingAmount - $allocated, 2) : $perMonth;
            $allocated += $amount;
            $slices[$date] = $amount;
        }

        return $slices;
    }

    /**
     * * الجدول كامل: المتبقي موزّع على الشهور اللي لسه جايّة.
     *
     * @return array<string, float> تاريخ => مبلغ
     */
    public static function forContract(float $remainingAmount, string $contractStart, string $contractEnd, ?string $asOf = null): array
    {
        return self::slices(
            $remainingAmount,
            self::remainingMonthEnds($contractStart, $contractEnd, $asOf)
        );
    }
}
