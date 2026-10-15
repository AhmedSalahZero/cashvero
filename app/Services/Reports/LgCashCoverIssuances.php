<?php

namespace App\Services\Reports;

use App\Models\LetterOfGuaranteeIssuance;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * * الكاش كفر اللي بيتحجز وقت إصدار خطاب الضمان — فلوس خارجة
 *
 * * الوش التاني لـ LgCashCoverRefunds: دي بتجيب اللي خرج ، و دي بتجيب
 * * اللي رجع. من غير الاتنين مع بعض التقرير بيبقى غير متوازن.
 *
 * * مقصورة على category_name = New Issuance و مستبعدة Opening Balance
 * * بقرار منتج صريح: كفر خطاب الرصيد الافتتاحي اتدفع قبل ما النظام
 * * يشتغل ، فمفيش خروج نعرضه — لكن رجوعه حقيقي فبيفضل في صف الرد.
 *
 * * التاريخ هنا تاريخ الحركة نفسها مش تاريخ محسوب: الإصدار حدث حصل
 * * فعلا و اتكتبت له حركة ، على عكس الرد اللي بيتوقّع لحد ما يحصل.
 */
final class LgCashCoverIssuances
{
    /**
     * @param  int|array<int, int>|null  $contractId  عقد واحد أو مجموعة عقود — null يعني الشركة كلها
     * @param  string|null  $currency  تبويب العملة — null يعني كل العملات
     * @return Collection<int, \stdClass>
     */
    public static function between(
        int $companyId,
        string $periodStart,
        string $periodEnd,
        int|array|null $contractId = null,
        ?string $currency = null,
    ): Collection {
        return DB::table('letter_of_guarantee_cash_cover_statements')
            ->join('letter_of_guarantee_issuances', 'letter_of_guarantee_issuances.id', '=', 'letter_of_guarantee_cash_cover_statements.letter_of_guarantee_issuance_id')
            ->join('partners', 'partners.id', '=', 'letter_of_guarantee_issuances.partner_id')
            ->where('letter_of_guarantee_cash_cover_statements.company_id', $companyId)
            ->where('letter_of_guarantee_cash_cover_statements.type', 'debit-lg-amount')
            ->where('letter_of_guarantee_issuances.category_name', LetterOfGuaranteeIssuance::NEW_ISSUANCE)
            ->where('letter_of_guarantee_cash_cover_statements.letter_of_guarantee_issuance_id', '>', 0)
            ->whereBetween('letter_of_guarantee_cash_cover_statements.date', [$periodStart, $periodEnd])
            ->when($contractId, function ($query) use ($contractId) {
                is_array($contractId)
                    ? $query->whereIn('letter_of_guarantee_issuances.contract_id', $contractId)
                    : $query->where('letter_of_guarantee_issuances.contract_id', $contractId);
            })
            ->when($currency !== null, function ($query) use ($currency) {
                $query->where('letter_of_guarantee_cash_cover_statements.currency', $currency);
            })
            ->where('letter_of_guarantee_cash_cover_statements.debit', '>', 0)
            ->selectRaw(
                'letter_of_guarantee_issuances.lg_type as lg_type, '
                .'letter_of_guarantee_cash_cover_statements.debit as total_amount, '
                .'letter_of_guarantee_cash_cover_statements.currency as currency, '
                .'letter_of_guarantee_cash_cover_statements.date as movement_date, '
                .'partners.name as partner_name, '
                .'letter_of_guarantee_issuances.lg_code as lg_code'
            )
            ->get();
    }
}
