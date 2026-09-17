<?php

namespace App\Services\Reports;

use App\Models\LetterOfGuaranteeIssuance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * * الكاش كفر الراجع من خطابات الضمان — الفعلي و المتوقّع
 *
 * * البنك بيحجز كاش كفر وقت إصدار الخطاب و بيرجّعه لما الخطاب يخلص.
 * * التقرير كان بيقرا الرد من حركات الإلغاء بس
 * * (letter_of_guarantee_cash_cover_statements بـ type = for-cancellation) ،
 * * و الحركة دي ما بتتكتبش إلا لما حد يلغي الخطاب بإيده.
 *
 * * و بما إن الإلغاء حدث ماضي دايما — اتأكدنا من الداتا: كل حركات
 * * الإلغاء في الماضي و مفيش ولا واحدة في المستقبل — فصف الرد كان
 * * بيفضل فاضي في أي تقرير بيبص لقدام. يعني الكاش كفر كان بيخرج و
 * * عمره ما بيرجع.
 *
 * * الحل هو نفس اللي معمول في الودايع
 * * (CashFlowCompanyPeriodBatchLoader::applyTimeOfDepositMovements) :
 * * بنقرا من جدول الخطابات نفسه مش من جدول الحركات ، و بنحدد لكل خطاب
 * * تاريخ و مبلغ حسب حالته:
 * *
 * *   | الحالة        | التاريخ           | المبلغ                |
 * *   | اتلغى         | تاريخ الإلغاء     | المبلغ اللي رجع فعلا  |
 * *   | لسه شغال      | renewal_date      | المتبقي من الكفر      |
 * *
 * * ── المتبقي من الكفر ────────────────────────────────────────────
 * * خطابات الدفعة المقدمة تغطيتها بتتناقص مع سداد الدفعة ، فمينفعش
 * * نتوقّع رجوع المبلغ المسجّل وقت الإصدار. المعادلة:
 * *
 * *   المتبقي = cash_cover_amount − (المسدّد × cash_cover_rate ÷ 100)
 * *
 * * اتحققنا منها على خطاب اتلغى فعلا (lg 124): الكفر المسجّل
 * * ١٬٢٨١٬٩٢٤ و المسدّد ٨٬٢٩٤٬٤٦١٫٧٥ بنسبة ١٠٪ ← ٤٥٢٬٤٧٧٫٨٣ ، و هو
 * * بالظبط المبلغ اللي البنك رجّعه.
 */
final class LgCashCoverRefunds
{
    /**
     * * خطاب اتلغى بإيد المستخدم بس حركته اتمسحت — مش هنخترعله رد
     * * مستقبلي ، الفلوس بتاعته خلاص خرجت من الحسبة
     */
    private const CANCELLED_STATUS = LetterOfGuaranteeIssuance::CANCELLED;

    /**
     * * تاريخ رجوع الكاش كفر: تاريخ الإلغاء لو اتلغى ، و إلا تاريخ
     * * الانتهاء الحالي (بيتحرك لقدام لوحده لو الخطاب اتجدّد)
     */
    private const EFFECTIVE_DATE = 'COALESCE(cancellation.cancellation_date, letter_of_guarantee_issuances.renewal_date)';

    private const REMAINING_COVER = 'GREATEST(letter_of_guarantee_issuances.cash_cover_amount'
        .' - (COALESCE(repayment.repaid_amount, 0) * letter_of_guarantee_issuances.cash_cover_rate / 100), 0)';

    private const AMOUNT = 'COALESCE(cancellation.refunded_amount, '.self::REMAINING_COVER.')';

    /**
     * * الملغي بعملة حركته ، و المتوقّع بعملة الخطاب
     */
    private const CURRENCY = 'COALESCE(cancellation.cancellation_currency, letter_of_guarantee_issuances.lg_currency)';

    /**
     * * صف لكل خطاب هيرجع كفره جوّه الفترة — مرة واحدة لكل خطاب ،
     * * مفيش تكرار
     *
     * @param  int|array<int, int>|null  $contractId  عقد واحد أو مجموعة عقود — null يعني الشركة كلها
     * @param  string|null  $currency  تبويب العملة في تقرير الشركة — null يعني كل العملات
     * @return Collection<int, \stdClass>
     */
    public static function between(
        int $companyId,
        string $periodStart,
        string $periodEnd,
        int|array|null $contractId = null,
        ?string $currency = null,
    ): Collection {
        return DB::table('letter_of_guarantee_issuances')
            ->join('partners', 'partners.id', '=', 'letter_of_guarantee_issuances.partner_id')
            ->leftJoinSub(self::cancellations(), 'cancellation', 'cancellation.letter_of_guarantee_issuance_id', '=', 'letter_of_guarantee_issuances.id')
            ->leftJoinSub(self::repayments(), 'repayment', 'repayment.letter_of_guarantee_issuance_id', '=', 'letter_of_guarantee_issuances.id')
            ->where('letter_of_guarantee_issuances.company_id', $companyId)
            ->when($contractId, function ($query) use ($contractId) {
                is_array($contractId)
                    ? $query->whereIn('letter_of_guarantee_issuances.contract_id', $contractId)
                    : $query->where('letter_of_guarantee_issuances.contract_id', $contractId);
            })
            /**
             * * الملغي اللي مالوش حركة مابيرجّعش حاجة — من غير الشرط ده
             * * الـ COALESCE كان هيوقع على renewal_date و يتوقّعله رد
             */
            ->where(function ($query) {
                $query->whereNotNull('cancellation.cancellation_date')
                    ->orWhere('letter_of_guarantee_issuances.status', '!=', self::CANCELLED_STATUS);
            })
            ->whereRaw(self::EFFECTIVE_DATE.' BETWEEN ? AND ?', [$periodStart, $periodEnd])
            ->when($currency !== null, function ($query) use ($currency) {
                $query->whereRaw(self::CURRENCY.' = ?', [$currency]);
            })
            ->whereRaw(self::AMOUNT.' > 0')
            ->selectRaw(
                'letter_of_guarantee_issuances.lg_type as lg_type, '
                .self::AMOUNT.' as total_amount, '
                .self::CURRENCY.' as currency, '
                .self::EFFECTIVE_DATE.' as movement_date, '
                .'partners.name as partner_name, '
                .'letter_of_guarantee_issuances.lg_code as lg_code, '
                .'(cancellation.cancellation_date is null) as is_forecast'
            )
            ->get();
    }

    /**
     * * حركة الإلغاء: تاريخها و المبلغ اللي رجع فعلا. بنجمّع بالخطاب
     * * عشان كل خطاب يطلع صف واحد مهما كان عدد حركاته
     */
    private static function cancellations(): \Illuminate\Database\Query\Builder
    {
        return DB::table('letter_of_guarantee_cash_cover_statements')
            ->select(
                'letter_of_guarantee_issuance_id',
                DB::raw('min(date) as cancellation_date'),
                DB::raw('sum(credit) as refunded_amount'),
                DB::raw('min(currency) as cancellation_currency'),
            )
            ->where('type', LetterOfGuaranteeIssuance::FOR_CANCELLATION)
            ->groupBy('letter_of_guarantee_issuance_id');
    }

    /**
     * * الدفعات المقدمة المسدّدة — دي اللي بتنقّص تغطية خطابات
     * * الدفعة المقدمة
     */
    private static function repayments(): \Illuminate\Database\Query\Builder
    {
        return DB::table('lg_issuance_advanced_payment_histories')
            ->select('letter_of_guarantee_issuance_id', DB::raw('sum(amount) as repaid_amount'))
            ->groupBy('letter_of_guarantee_issuance_id');
    }
}
