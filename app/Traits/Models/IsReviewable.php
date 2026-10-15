<?php

namespace App\Traits\Models;

use App\Models\User;
use App\Support\Activity\ActivityLogger;
use App\Support\Activity\ActivityRegistry;
use Illuminate\Database\Eloquent\Builder;

/**
 * IsReviewable
 * ------------------------------------------------------------------
 * حالة المراجعة على حركة مالية .
 *
 * * المراجعة مش مجرد علامة : الحركة المراجَعة **بتتقفل** — ما ينفعش
 * * تتعدل و لا تتحذف لحد ما حد يفكّ المراجعة . ده المقصود منها ، إن حد
 * * مسؤول يقول "أنا شوفتها و دي صح" و بعد كده ما حدش يغيّرها من تحت إيده .
 *
 * * القفل متطبّق في الموديل نفسه (بوتينج على saving/deleting) مش في
 * * الكونترولرز : ١٢ صفحة × (تعديل + حذف) = ٢٤ مكان ممكن حد ينسى واحد
 * * فيهم . في الموديل مفيش طريق يعدّي من غيره .
 *
 * * كل تغيير في حالة المراجعة بيتسجّل في سجل الحركة نفسها بجملة كاملة
 * * فيها مين عملها و إيه الحركة بالظبط ، عشان اللي يقرا السجل بعد سنة
 * * يعرف الصف ده كان إيه من غير ما يفتحه .
 */
trait IsReviewable
{
    /**
     * بوتينج التريت — لارافيل بينادي الميثود دي لوحده .
     *
     * * القفل هنا عشان ما ينفعش يتعدّى من أي مسار : كونترولر ، أمر
     * * كونسول ، أو كود جديد لسه ما اتكتبش
     */
    public static function bootIsReviewable(): void
    {
        static::updating(function ($model) {
            if ($model->reviewLockBypassed()) {
                return;
            }

            /**
             * * القيمة القديمة هي اللي بتحكم : لو الصف كان مراجَع قبل
             * * التعديل يبقى مقفول . كده فكّ المراجعة نفسه بيعدّي (لأنه
             * * بيغيّر is_reviewed) لكن تعديل أي حاجة تانية لأ
             */
            if (! $model->getOriginal('is_reviewed')) {
                return;
            }

            if ($model->isOnlyChangingReviewState()) {
                return;
            }

            abort(403, __('This movement is reviewed and cannot be changed. Remove the review first.'));
        });

        static::deleting(function ($model) {
            if ($model->reviewLockBypassed()) {
                return;
            }

            if ($model->isReviewed()) {
                abort(403, __('This movement is reviewed and cannot be deleted. Remove the review first.'));
            }
        });
    }

    /**
     * * مفتاح طوارئ للمسارات اللي بتصلّح بيانات (أوامر الكونسول
     * * و الهجرات) — مش مفتوح للويب
     */
    protected static bool $reviewLockDisabled = false;

    public static function withoutReviewLock(callable $callback): mixed
    {
        $previous = static::$reviewLockDisabled;
        static::$reviewLockDisabled = true;

        try {
            return $callback();
        } finally {
            static::$reviewLockDisabled = $previous;
        }
    }

    protected function reviewLockBypassed(): bool
    {
        return static::$reviewLockDisabled;
    }

    /** الأعمدة اللي تغييرها لوحده معناه "بنراجع" مش "بنعدّل الحركة" */
    private function isOnlyChangingReviewState(): bool
    {
        $touched = array_keys($this->getDirty());

        return $touched !== [] && array_diff($touched, [
            'is_reviewed', 'reviewed_by', 'reviewed_at', 'review_comment', 'updated_at',
        ]) === [];
    }

    /**
     * يرفض فتح شاشة التعديل لو الحركة مراجَعة .
     *
     * * القفل على الحفظ لوحده مش كفاية : الفورمة كانت بتفتح عادي و
     * * المستخدم يملاها و ياخد ٤٠٣ في الآخر . الرفض بيتم من البداية
     * * عشان محدش يضيّع شغله .
     *
     * * بتتنادى من أول شاشة التعديل في كل كونترولر حركة .
     */
    public function abortIfReviewed(): void
    {
        if ($this->reviewLockBypassed() || ! $this->isReviewed()) {
            return;
        }

        abort(403, __('This movement is reviewed and cannot be changed. Remove the review first.'));
    }

    public function isReviewed(): bool
    {
        return (bool) $this->is_reviewed;
    }

    public function reviewedBy()
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'id');
    }

    public function getReviewedByName(): ?string
    {
        return $this->reviewedBy?->name;
    }

    public function getReviewedAtFormatted(): ?string
    {
        return $this->reviewed_at ? \Carbon\Carbon::parse($this->reviewed_at)->format('d-m-Y H:i') : null;
    }

    public function getReviewComment(): ?string
    {
        return $this->review_comment;
    }

    /**
     * يعلّم الحركة كمراجَعة .
     *
     * @return bool  false لو كانت مراجَعة أصلا (عملية مكرّرة ، مش خطأ)
     */
    public function markReviewed(User $actor, ?string $comment = null): bool
    {
        if ($this->isReviewed()) {
            return false;
        }

        $this->forceFill([
            'is_reviewed' => true,
            'reviewed_by' => $actor->id,
            'reviewed_at' => now(),
            'review_comment' => $comment ?: null,
        ])->save();

        $this->logReview(true, $actor, $comment);

        return true;
    }

    /** يفكّ المراجعة ، فترجع الحركة تتعدل و تتحذف تاني */
    public function markUnreviewed(User $actor, ?string $comment = null): bool
    {
        if (! $this->isReviewed()) {
            return false;
        }

        $this->forceFill([
            'is_reviewed' => false,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'review_comment' => $comment ?: null,
        ])->save();

        $this->logReview(false, $actor, $comment);

        return true;
    }

    /**
     * جملة السجل .
     *
     * * مكتوبة عشان اللي يقراها بعد سنة يعرف الحركة دي كانت إيه من غير
     * * ما يفتحها : النوع ، الرقم ، المبلغ ، الطرف ، التاريخ ، و مين
     * * اللي راجعها . التعليق بيتكتب في الآخر لو المستخدم كتبه .
     */
    private function logReview(bool $reviewed, User $actor, ?string $comment): void
    {
        $sentence = $reviewed
            ? __(':actor marked :record as reviewed', ['actor' => $actor->name, 'record' => $this->reviewDescriptor()])
            : __(':actor removed the review from :record', ['actor' => $actor->name, 'record' => $this->reviewDescriptor()]);

        if ($comment) {
            $sentence .= ' — '.__('Comment').': '.$comment;
        }

        ActivityLogger::custom($this, $sentence, [
            [
                'field' => 'is_reviewed',
                'label' => __('Reviewed'),
                'from' => $reviewed ? __('No') : __('Yes'),
                'to' => $reviewed ? __('Yes') : __('No'),
            ],
        ]);
    }

    /**
     * وصف الحركة في جملة واحدة .
     *
     * * الموديل يقدر يعمل override لو عنده وصف أحسن ؛ الافتراضي بيلمّ
     * * اللي يتلم من الميثودز الموجودة على أغلب الحركات
     */
    public function reviewDescriptor(): string
    {
        $parts = [ActivityRegistry::labelFor(static::class).' #'.$this->getKey()];

        foreach (['getPartnerName', 'getCustomerName', 'getSupplierName', 'getName'] as $method) {
            if (method_exists($this, $method) && ($name = $this->{$method}())) {
                $parts[] = (string) $name;
                break;
            }
        }

        foreach (['getAmountFormatted', 'getPaidAmountFormatted', 'getReceivedAmountFormatted', 'getLgAmountFormatted', 'getLcAmountFormatted'] as $method) {
            if (method_exists($this, $method) && ($amount = $this->{$method}())) {
                $parts[] = (string) $amount.' '.(string) ($this->currency ?? '');
                break;
            }
        }

        foreach (['getDateFormatted', 'getReceivingDateFormatted', 'getPaymentDateFormatted'] as $method) {
            if (method_exists($this, $method) && ($date = $this->{$method}())) {
                $parts[] = (string) $date;
                break;
            }
        }

        return implode(' · ', array_filter($parts));
    }

    /**
     * فلتر صفحة الـ index : الكل / المراجَع / غير المراجَع
     */
    public function scopeReviewState(Builder $query, ?string $state): Builder
    {
        if ($state === 'reviewed') {
            return $query->where('is_reviewed', 1);
        }

        if ($state === 'not_reviewed') {
            return $query->where(fn ($q) => $q->where('is_reviewed', 0)->orWhereNull('is_reviewed'));
        }

        return $query;
    }

    /** اللي الواجهة محتاجاه لعرض حالة المراجعة على الصف */
    public function reviewPayload(): array
    {
        return [
            'is_reviewed' => $this->isReviewed(),
            'reviewed_by_name' => $this->getReviewedByName(),
            'reviewed_at' => $this->getReviewedAtFormatted(),
            'review_comment' => $this->getReviewComment(),
        ];
    }
}
