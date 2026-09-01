<?php

namespace App\Services\Api\Traits;

use App\Exceptions\OdooOperationNotAllowedException;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * * اودو مافيهوش transaction عبر XML-RPC ، و الحذف عنده بيتعمل على
 * * خطوتين : ترجّع السجل لـ draft ، بعدين تعمله unlink
 *
 * * لو الخطوة التانية فشلت — و دي بتفشل فعلا لما القيد يكون استهلك رقم
 * * تسلسلي و مش آخر واحد في السلسلة — كان السجل بيفضل draft في اودو ،
 * * يعني نص عملية : لا اتحذف و لا رجع زي ما كان ، و القيد بيبقى مفتوح
 * * محاسبيا من غير ما حد ياخد باله
 *
 * * الترايت دي بتعمل compensating action : بتحفظ الحالة الاصلية ، و لو
 * * الحذف فشل بترجّعها قبل ما ترمي الاستثناء . فالنتيجة اما العملية
 * * تتنفذ كاملة او اودو يفضل زي ما هو بالظبط
 */
trait HasAtomicOdooDeletion
{
    /**
     * @param  string  $model  اسم الموديل في اودو (account.move / account.payment)
     * @param  string  $draftMethod  الميثود اللي بترجّع السجل لـ draft
     * @param  array<string, string>  $restoreMethods  الحالة الاصلية => الميثود اللي بترجّعها
     * @param  bool  $skipWhenAlreadyDraft  للحفاظ على سلوك المسارات اللي كانت بتسيب الـ draft زي ما هو
     * @return bool  true لو اتحذف فعلا ، false لو مكانش موجود او اتساب عمدا
     *
     * @throws OdooOperationNotAllowedException لو اودو رفض الحذف (بعد ما الحالة ترجع)
     */
    protected function unlinkOdooRecordAtomically(
        string $model,
        int $recordId,
        string $draftMethod,
        array $restoreMethods,
        string $context,
        bool $skipWhenAlreadyDraft = false
    ): bool {
        $entry = $this->execute($model, 'read', [[$recordId], ['id', 'state']]);

        /**
         * * السجل مش موجود في اودو اصلا — مفيش حاجة نعملها و مفيش حاجة
         * * اتغيرت ، فمش خطأ
         */
        if (empty($entry)) {
            return false;
        }

        $originalState = $entry[0]['state'] ?? null;

        if ($originalState === 'draft' && $skipWhenAlreadyDraft) {
            return false;
        }

        $movedToDraft = false;

        try {
            if ($originalState !== 'draft') {
                $this->execute($model, $draftMethod, [[$recordId]]);
                $movedToDraft = true;
            }

            $this->execute($model, 'unlink', [[$recordId]]);
        } catch (Throwable $e) {
            if ($movedToDraft) {
                $this->restoreOdooRecordState($model, $recordId, $originalState, $restoreMethods);
            }

            throw new OdooOperationNotAllowedException($context.': '.$e->getMessage(), null, $e);
        }

        return true;
    }

    /**
     * * بترجّع السجل لحالته الاصلية بعد فشل الحذف
     *
     * * الفشل هنا ما ينفعش يرمي استثناء تاني و يخفي السبب الاصلي ، فبيتسجل
     * * في الـ log بس — و دي الحالة الوحيدة اللي ممكن يفضل فيها السجل draft
     *
     * @param  array<string, string>  $restoreMethods
     */
    private function restoreOdooRecordState(string $model, int $recordId, ?string $originalState, array $restoreMethods): void
    {
        $restoreMethod = $restoreMethods[$originalState] ?? null;

        if (! $restoreMethod) {
            Log::error('Odoo rollback skipped — no restore method for state', [
                'model' => $model,
                'record_id' => $recordId,
                'original_state' => $originalState,
            ]);

            return;
        }

        try {
            $current = $this->execute($model, 'read', [[$recordId], ['id', 'state']]);

            /**
             * * لو اتحذف فعلا برغم الاستثناء ، او رجع لحالته لوحده ، مفيش
             * * حاجة نعملها
             */
            if (empty($current) || ($current[0]['state'] ?? null) === $originalState) {
                return;
            }

            $this->execute($model, $restoreMethod, [[$recordId]]);
        } catch (Throwable $e) {
            Log::error('Odoo rollback failed — record left in draft', [
                'model' => $model,
                'record_id' => $recordId,
                'original_state' => $originalState,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
