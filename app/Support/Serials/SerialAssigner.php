<?php

namespace App\Support\Serials;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * SerialAssigner
 * ==================================================================
 * * بيدّي كل حركة جديدة رقمها المسلسل — شوف SerialRegistry للشكل .
 *
 * * الرقم بيتاخد من جدول serial_sequences : صف لكل
 * * (شركة + بريفكس + سنة) فيه آخر رقم اتصرف .
 */
class SerialAssigner
{
    /**
     * * أرقام الصفوف اللي بتتعدل بطريقة "احذف و اعمل من جديد" .
     * * شوف preserving() .
     *
     * @var array<int, array{class:string, company_id:mixed, prefix:string, serial:string, used:bool}>
     */
    private static array $preserved = [];

    /**
     * الرقم الجاي لـ (شركة + بريفكس + سنة) .
     *
     * * upsert واحد بياخد lock حصري على الصف من أول لحظة . الطريقة
     * * التانية (insert ignore و بعدين select for update) بتعمل deadlock
     * * لما طلبين ييجوا في نفس الوقت : الـ insert ignore بياخد shared lock
     * * و كل طلب يستنى التاني يسيب .
     *
     * * الـ lock بيفضل لحد ما الترانزاكشن الكبيرة تخلص ، فلو الحفظ وقع
     * * الرقم بيرجع معاه و ما بيبقاش فيه فراغ .
     */
    public static function next(int $companyId, string $prefix, int $year): int
    {
        return DB::transaction(function () use ($companyId, $prefix, $year) {
            $now = now();

            DB::statement(
                'INSERT INTO serial_sequences (company_id, prefix, year, last_number, created_at, updated_at)
                 VALUES (?, ?, ?, 1, ?, ?)
                 ON DUPLICATE KEY UPDATE last_number = last_number + 1, updated_at = ?',
                [$companyId, $prefix, $year, $now, $now, $now]
            );

            return (int) DB::table('serial_sequences')
                ->where('company_id', $companyId)
                ->where('prefix', $prefix)
                ->where('year', $year)
                ->lockForUpdate()
                ->value('last_number');
        });
    }

    /** رقم جديد كامل : MR/CIS/2026/10/00001 */
    public static function generate(int $companyId, string $prefix, Carbon $date): string
    {
        return SerialRegistry::format($prefix, $date, self::next($companyId, $prefix, $date->year));
    }

    /**
     * رقم صف جديد — أو null لو الصف ما ينفعش يتعدّ (من غير شركة أو
     * نوعه مش معروف) .
     */
    public static function forNew(Model $model): ?string
    {
        $companyId = $model->getAttribute('company_id');
        $prefix = self::prefixOf($model);

        if (! $companyId || ! $prefix) {
            Log::warning('Serial not assigned: missing company or unmapped type', [
                'model' => $model::class,
                'company_id' => $companyId,
                'type' => $model->getAttribute(SerialRegistry::config($model::class)['type_column']),
            ]);

            return null;
        }

        return self::takePreserved($model::class, $companyId, $prefix)
            ?? self::generate((int) $companyId, $prefix, self::dateOf($model));
    }

    /**
     * يحافظ على رقم الصف لما التعديل بيتعمل "احذف القديم و اعمل جديد" .
     *
     * * أول صف جديد من نفس الكلاس و نفس الشركة و **نفس النوع** بيتعمل
     * * جوه الـ callback بياخد الرقم القديم . لو المستخدم غيّر النوع في
     * * التعديل البريفكس بيختلف فالصف بياخد رقم جديد من تسلسل النوع
     * * الجديد و القديم بيبقى فراغ .
     *
     * * متنادية من ActivityLogger::asUpdate() — كل الكونترولرز اللي
     * * بتعمل كده بتعدّي من هناك .
     *
     * @template T
     *
     * @param  callable():T  $callback
     * @return T
     */
    public static function preserving(Model $old, callable $callback): mixed
    {
        $serial = SerialRegistry::tracks($old::class) ? $old->getAttribute('serial') : null;
        $prefix = $serial ? self::prefixOf($old) : null;

        if (! $serial || ! $prefix) {
            return $callback();
        }

        self::$preserved[] = [
            'class' => $old::class,
            'company_id' => $old->getAttribute('company_id'),
            'prefix' => $prefix,
            'serial' => $serial,
            'used' => false,
        ];

        try {
            return $callback();
        } finally {
            array_pop(self::$preserved);
        }
    }

    /** البريفكس من نوع الصف : MR/CIS */
    public static function prefixOf(Model $model): ?string
    {
        $config = SerialRegistry::config($model::class);

        return SerialRegistry::prefixFor($model::class, $model->getAttribute($config['type_column']));
    }

    /** تاريخ الحركة — هو اللي بيحدد السنة و الشهر في الرقم */
    public static function dateOf(Model $model): Carbon
    {
        $config = SerialRegistry::config($model::class);
        $value = $model->getAttribute($config['date_column']) ?: $model->getAttribute('created_at');

        return rescue(fn () => $value ? Carbon::parse($value) : now(), fn () => now(), false);
    }

    private static function takePreserved(string $class, mixed $companyId, string $prefix): ?string
    {
        for ($i = count(self::$preserved) - 1; $i >= 0; $i--) {
            $entry = self::$preserved[$i];

            if ($entry['used'] || $entry['class'] !== $class || $entry['prefix'] !== $prefix || (string) $entry['company_id'] !== (string) $companyId) {
                continue;
            }

            self::$preserved[$i]['used'] = true;

            /**
             * * شبكة أمان : الكونترولرز الحالية كلها بتحذف القديم الأول ،
             * * لكن لو مسار جديد عمل العكس الرقم يبقى لسه مستعمل و الـ
             * * unique index هيضرب — ساعتها ناخد رقم جديد أحسن
             */
            $taken = DB::table((new $class)->getTable())
                ->where('company_id', $companyId)
                ->where('serial', $entry['serial'])
                ->exists();

            return $taken ? null : $entry['serial'];
        }

        return null;
    }
}
