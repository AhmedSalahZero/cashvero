<?php

namespace App\Support\Serials;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * SerialBackfiller
 * ==================================================================
 * * بيدّي أرقام مسلسلة للصفوف اللي لسه ما عندهاش رقم — الداتا القديمة
 * * وقت الهجرة ، أو داتا شركة اتنقلت بـ company:import-from-source .
 *
 * * الترتيب جوه كل (شركة + نوع + سنة) بتاريخ الحركة و بعدين الـ id ،
 * * فأقدم حركة في السنة بتاخد 00001 .
 *
 * * بيلمس الصفوف اللي serial بتاعها NULL بس ، و الترقيم بيكمّل من
 * * serial_sequences ، فتشغيله أكتر من مرة آمن .
 *
 * * الكتابة بـ DB::table() مش بالموديل : الحركات المراجَعة مقفولة
 * * على التعديل (IsReviewable) و ده مش تعديل من المستخدم .
 */
class SerialBackfiller
{
    /**
     * @param  class-string|null  $onlyClass
     * @return array<string, array{assigned:int, skipped:int}>  نتيجة لكل كلاس
     */
    public function run(?int $companyId = null, ?string $onlyClass = null, bool $dryRun = false): array
    {
        $report = [];

        foreach (SerialRegistry::MODELS as $class => $config) {
            if ($onlyClass && $onlyClass !== $class) {
                continue;
            }

            $report[$class] = $this->backfillModel($class, $config, $companyId, $dryRun);
        }

        return $report;
    }

    /** @return array{assigned:int, skipped:int} */
    private function backfillModel(string $class, array $config, ?int $companyId, bool $dryRun): array
    {
        $table = (new $class)->getTable();
        $typeColumn = $config['type_column'];
        $dateColumn = $config['date_column'];

        $pending = DB::table($table)
            ->whereNull('serial')
            ->when($companyId, fn ($q) => $q->where('company_id', $companyId));

        $eligible = (clone $pending)
            ->whereNotNull('company_id')
            ->whereIn($typeColumn, array_keys($config['codes']));

        $total = (clone $pending)->count();

        if ($dryRun) {
            $assigned = (clone $eligible)->count();

            return ['assigned' => $assigned, 'skipped' => $total - $assigned];
        }

        $assigned = 0;

        /**
         * * get() مرة واحدة مش chunk : الـ chunk بيمشي بـ offset و الصفوف
         * * اللي بتاخد رقم بتخرج من الـ where فالـ offset يعدّي صفوف .
         * * الأعمدة قليلة فالذاكرة مش مشكلة
         */
        $rows = (clone $eligible)
            ->select(['id', 'company_id', $typeColumn.' as serial_type', $dateColumn.' as serial_date', 'created_at'])
            ->orderBy('company_id')
            ->orderByRaw("COALESCE(`{$dateColumn}`, DATE(`created_at`))")
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $date = rescue(fn () => Carbon::parse($row->serial_date ?: $row->created_at ?: now()), fn () => now(), false);
            $prefix = SerialRegistry::prefixFor($class, $row->serial_type);

            DB::transaction(function () use ($table, $row, $prefix, $date) {
                DB::table($table)->where('id', $row->id)->update([
                    'serial' => SerialAssigner::generate((int) $row->company_id, $prefix, $date),
                ]);
            });

            $assigned++;
        }

        return ['assigned' => $assigned, 'skipped' => $total - $assigned];
    }
}
