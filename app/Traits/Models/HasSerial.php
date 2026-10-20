<?php

namespace App\Traits\Models;

use App\Support\Serials\SerialAssigner;
use App\Support\Serials\SerialRegistry;
use Illuminate\Database\Eloquent\Builder;

/**
 * HasSerial
 * ------------------------------------------------------------------
 * الرقم المسلسل للحركة (MR/CIS/2026/10/00001) — شوف SerialRegistry .
 *
 * * الرقم بيتحط مرة واحدة وقت الإنشاء و ما بيتغيّرش بعد كده : لا
 * * بتعديل التاريخ و لا بتحريك الشيك (تحت التحصيل / محصّل / مرفوض) .
 * * الاستثناء الوحيد إن النوع نفسه يتغيّر في التعديل ، ساعتها الحركة
 * * بتاخد رقم جديد من تسلسل النوع الجديد .
 *
 * * التعديل اللي بيتعمل "احذف و اعمل من جديد" بيحافظ على الرقم من خلال
 * * ActivityLogger::asUpdate() — شوف SerialAssigner::preserving() .
 */
trait HasSerial
{
    public static function bootHasSerial(): void
    {
        /**
         * * أي serial جاي من الريكويست بيتشال : storeBasicForm() بتكتب
         * * أي مفتاح اسمه زي عمود ، فمن غير ده أي حد يقدر يبعت رقمه
         */
        static::creating(function ($model) {
            $model->setAttribute('serial', SerialAssigner::forNew($model));
        });

        static::updating(function ($model) {
            if ($model->isDirty('serial')) {
                $model->setAttribute('serial', $model->getOriginal('serial'));
            }

            $typeColumn = SerialRegistry::config($model::class)['type_column'];
            $oldPrefix = SerialRegistry::prefixFor($model::class, $model->getOriginal($typeColumn));
            $typeChanged = $oldPrefix !== SerialAssigner::prefixOf($model);

            if ($model->getAttribute('serial') && ! $typeChanged) {
                return;
            }

            $model->setAttribute('serial', SerialAssigner::forNew($model) ?? $model->getAttribute('serial'));
        });
    }

    public function getSerial(): ?string
    {
        return $this->getAttribute('serial');
    }

    /** للعرض : الرقم المسلسل ، أو #id لصف لسه ما خدش رقم */
    public function getSerialOrId(): string
    {
        return $this->getSerial() ?: '#'.$this->getKey();
    }

    /** بحث صفحة الـ index بالرقم المسلسل */
    public function scopeSerialLike(Builder $query, ?string $value): Builder
    {
        $value = trim((string) $value);

        return $value === ''
            ? $query
            : $query->where($query->qualifyColumn('serial'), 'like', '%'.$value.'%');
    }
}
